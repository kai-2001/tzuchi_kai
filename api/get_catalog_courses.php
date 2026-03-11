<?php
// api/get_catalog_courses.php - Get Paginated and Filtered Course Catalog Data

session_set_cookie_params(0);
session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;
if ($is_admin) {
    echo json_encode(['success' => true, 'total' => 0, 'data' => []]);
    exit;
}

// 1. Get query parameters
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 12;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$campus = isset($_GET['campus']) ? trim($_GET['campus']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$exclude_enrolled = isset($_GET['exclude_enrolled']) && $_GET['exclude_enrolled'] === 'true';

// Resolve Moodle UID
$moodle_uid = null;
if (isset($_SESSION['moodle_uid'])) {
    $moodle_uid = $_SESSION['moodle_uid'];
} else {
    // Quick resolve
    global $db_host, $db_user, $db_pass;
    $m_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
    if (!$m_conn->connect_error) {
        $m_conn->set_charset('utf8mb4');
        $m_stmt = $m_conn->prepare("SELECT id FROM mdl_user WHERE username = ? AND deleted = 0");
        $m_stmt->bind_param("s", $_SESSION['username']);
        $m_stmt->execute();
        $m_res = $m_stmt->get_result();
        if ($m_row = $m_res->fetch_assoc()) {
            $moodle_uid = $m_row['id'];
            $_SESSION['moodle_uid'] = $moodle_uid;
        }
        $m_stmt->close();
        $m_conn->close();
    }
}

try {
    global $db_host, $db_user, $db_pass;
    $mconn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
    if ($mconn->connect_error) {
        throw new Exception("Moodle DB Connection failed");
    }
    $mconn->set_charset('utf8mb4');

    // 2. Fetch category mapping
    $cat_info = [];
    $res_cat = $mconn->query("SELECT id, name, parent FROM mdl_course_categories");
    if ($res_cat) {
        while ($row = $res_cat->fetch_assoc()) {
            $cat_info[(int) $row['id']] = $row;
        }
    }

    // 3. Find user enrolled courses
    $enrolled_info = [];
    if ($moodle_uid) {
        $sql_enrol = "SELECT c.id, 
                             cc.timecompleted,
                             MAX(CASE WHEN cmc.completionstate = 3 THEN 1 ELSE 0 END) as has_failed_module,
                             COUNT(DISTINCT CASE WHEN cm.completion > 0 THEN cm.id END) as total_required_modules,
                             COUNT(DISTINCT CASE WHEN cm.completion > 0 AND cmc.completionstate IN (1, 2) THEN cm.id END) as completed_modules
                      FROM mdl_course c
                      JOIN mdl_enrol e ON e.courseid = c.id
                      JOIN mdl_user_enrolments ue ON ue.enrolid = e.id
                      LEFT JOIN mdl_course_completions cc ON cc.course = c.id AND cc.userid = ue.userid
                      LEFT JOIN mdl_course_modules cm ON cm.course = c.id
                      LEFT JOIN mdl_course_modules_completion cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = ue.userid
                      WHERE ue.userid = ? AND ue.status = 0
                      GROUP BY c.id, cc.timecompleted";
        $stmt_enrol = $mconn->prepare($sql_enrol);
        $stmt_enrol->bind_param("i", $moodle_uid);
        $stmt_enrol->execute();
        $res_enrol = $stmt_enrol->get_result();
        while ($row = $res_enrol->fetch_assoc()) {
            $c_id = (int) $row['id'];
            $is_completed = false;
            $has_cc_completed = !empty($row['timecompleted']) && $row['timecompleted'] > 0;
            $is_failed = !empty($row['has_failed_module']) && $row['has_failed_module'] > 0;
            $total_req = (int) $row['total_required_modules'];
            $comp_req = (int) $row['completed_modules'];

            if ($has_cc_completed) {
                $is_completed = true;
            } else if ($total_req > 0 && $comp_req >= $total_req && !$is_failed) {
                $is_completed = true;
            }

            $enrolled_info[$c_id] = [
                'is_enrolled' => true,
                'completed' => $is_completed,
                'is_failed' => $is_failed
            ];
        }
        $stmt_enrol->close();
    }

    // === 可見度邏輯 ===
    // 規則：
    //   visible=0 → 隱藏，任何人都看不到
    //   visible=1 + 沒有 course_visibility_rules → 所有人看得到
    //   visible=1 + 有 course_visibility_rules + 有 course_visibility_exclusions → 在排除名單中的人看不到
    //   visible=1 + 有 course_visibility_rules + 不在排除名單 → 符合規則即可見
    
    $portal_user_id = $_SESSION['user_id'] ?? null;
    
    // 取得有設定開放條件的課程 IDs 及其規則
    $restricted_courses = [];      // course_id => rule_snapshot(parsed)
    $user_excluded_courses = [];    // 被明確指定可看的 course_ids
    $student_cohort_ids = [];      // 學生所屬的 cohort IDs
    
    if ($portal_user_id) {
        try {
            $pconn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            $pconn->set_charset('utf8mb4');
            
            // (A) 取得所有有規則的課程
            $rulesRes = $pconn->query("SELECT course_id, rule_snapshot FROM course_visibility_rules WHERE is_active = 1");
            if ($rulesRes) {
                while ($row = $rulesRes->fetch_assoc()) {
                    $snapshot = json_decode($row['rule_snapshot'], true);
                    if ($snapshot && !empty($snapshot['filter_groups'])) {
                        $restricted_courses[(int) $row['course_id']] = $snapshot;
                    }
                }
            }
            
            // (B) 取得該學生被排除的課程
            $cvStmt = $pconn->prepare("SELECT DISTINCT course_id FROM course_visibility_exclusions WHERE user_id = ?");
            if ($cvStmt) {
                $cvStmt->bind_param("i", $portal_user_id);
                $cvStmt->execute();
                $cvRes = $cvStmt->get_result();
                while ($row = $cvRes->fetch_assoc()) {
                    $user_excluded_courses[] = (int) $row['course_id'];
                }
                $cvStmt->close();
            }
            
            // (B2) 取得有「精確指定」的課程列表（有任何 course_visibility_exclusions 記錄的課程）
            $courses_with_grants = [];
            $cwgRes = $pconn->query("SELECT DISTINCT course_id FROM course_visibility_exclusions");
            if ($cwgRes) {
                while ($row = $cwgRes->fetch_assoc()) {
                    $courses_with_grants[] = (int) $row['course_id'];
                }
            }
            
            // (C) 取得學生的 cohort 歸屬
            if ($moodle_uid) {
                $cohortStmt = $mconn->prepare("SELECT cohortid FROM mdl_cohort_members WHERE userid = ?");
                if ($cohortStmt) {
                    $cohortStmt->bind_param("i", $moodle_uid);
                    $cohortStmt->execute();
                    $cohortRes = $cohortStmt->get_result();
                    while ($row = $cohortRes->fetch_assoc()) {
                        $student_cohort_ids[] = (int) $row['cohortid'];
                    }
                    $cohortStmt->close();
                }
            }
            
            $pconn->close();
        } catch (Exception $e) {
            error_log("Visibility check error: " . $e->getMessage());
        }
    }


        // 4. Build WHERE clauses
    // 撈 visible=1 + 有 active 規則的 visible=0 課程
    $active_rule_ids = array_keys($restricted_courses);
    if (!empty($active_rule_ids)) {
        $rule_ids_str = implode(',', $active_rule_ids);
        $where = ["(visible = 1 OR id IN ($rule_ids_str))", "id > 1"];
    } else {
        $where = ["visible = 1", "id > 1"];
    }
    $params = [];
    $types = "";

    if (!empty($search)) {
        // Simple search on fullname
        $searchterm = '%' . $search . '%';
        $where[] = "(fullname LIKE ? OR shortname LIKE ?)";
        $params[] = $searchterm;
        $params[] = $searchterm;
        $types .= "ss";
    }

    if ($exclude_enrolled && !empty($enrolled_info)) {
        $id_list = implode(',', array_keys($enrolled_info));
        $where[] = "id NOT IN ($id_list)";
    }

    // Since categories are hierarchical, campus/category filters 
    // strictly need an exact match on parent/child. 
    // To do this simply in SQL without complex recursive CTEs, we filter by PHP after a broad fetch, 
    // OR we pre-calculate flatten tables. For now, since "fetch all" is the bottleneck, 
    // let's do soft filtering on the DB if possible, or fallback to fetching ALL visible and paginating in PHP.
    // Given Moodle\'s structure, getting all visible courses (id, name, cat) is vastly faster than joining.
    // We will do a lightweight fetch of all active courses, filter in PHP, then slice the array.

    $sql = "SELECT id, fullname, shortname, category, startdate, enddate, visible FROM mdl_course WHERE " . implode(" AND ", $where);

    $stmt = $mconn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res_courses = $stmt->get_result();

    $all_courses = [];
    while ($row = $res_courses->fetch_assoc()) {
        $cat_id = (int) $row['category'];
        $parent_name = '其他';
        $child_name = '';

        if ($cat_id && isset($cat_info[$cat_id])) {
            $curr_cat = $cat_info[$cat_id];
            $child_name = $curr_cat['name'];
            $temp_cat = $curr_cat;
            while (($temp_cat['parent'] ?? 0) > 0 && isset($cat_info[$temp_cat['parent']])) {
                $temp_cat = $cat_info[$temp_cat['parent']];
            }
            $parent_name = $temp_cat['name'];
            if ($curr_cat['id'] == $temp_cat['id']) {
                $child_name = '';
            }
        }

        // Apply Campus & Category Filters in memory 
        // (Memory filtering array of a few thousand items takes <5ms in PHP)
        if (!empty($campus) && $parent_name !== $campus)
            continue;
        if (!empty($category)) {
            $catNameCheck = $child_name ? $child_name : $parent_name;
            if ($category !== '__REQUIRED__' && $catNameCheck !== $category)
                continue;
            // Note: __REQUIRED__ logic needs cross referencing with curriculum which is complex. 
            // We'll skip __REQUIRED__ strictness in this baseline or expect frontend to handle it.
        }

        $cid = (int) $row['id'];
        $is_enrolled = isset($enrolled_info[$cid]) ? $enrolled_info[$cid]['is_enrolled'] : false;
        $completed = isset($enrolled_info[$cid]) ? $enrolled_info[$cid]['completed'] : false;
        $is_failed = isset($enrolled_info[$cid]) ? $enrolled_info[$cid]['is_failed'] : false;

        $all_courses[] = [
            'id' => $cid,
            'fullname' => $row['fullname'],
            'shortname' => $row['shortname'],
            'category' => $cat_id,
            'parent_category' => $parent_name,
            'child_category' => $child_name !== $parent_name ? $child_name : '',
            'startdate' => (int) $row['startdate'],
            'enddate' => (int) $row['enddate'],
            'visible' => (int) $row['visible'],
            'is_enrolled' => $is_enrolled,
            'completed' => $completed,
            'is_failed' => $is_failed
        ];
    }
    $stmt->close();
    $mconn->close();

    // 4.5 過濾受限課程 — 有設定開放條件的課程只有符合的人能看
    if (!empty($restricted_courses)) {
        $all_courses = array_filter($all_courses, function($course) use ($restricted_courses, $user_excluded_courses, $student_cohort_ids) {
            $cid = $course['id'];
            
            // 此課程沒有開放條件限制 → 所有人可見
            if (!isset($restricted_courses[$cid])) {
                return true;
            }
            
            // 評估規則匹配
            $snapshot = $restricted_courses[$cid];
            $filter_groups = $snapshot['filter_groups'];
            $operators = $snapshot['operators'] ?? [];
            
            $group_results = [];
            foreach ($filter_groups as $group) {
                $all_match = true;
                foreach ($group as $cohort_id) {
                    if (!in_array((int) $cohort_id, $student_cohort_ids)) {
                        $all_match = false;
                        break;
                    }
                }
                $group_results[] = $all_match;
            }
            
            $final_result = $group_results[0] ?? false;
            for ($i = 1; $i < count($group_results); $i++) {
                $op = $operators[$i - 1] ?? 'or';
                if ($op === 'and') {
                    $final_result = $final_result && $group_results[$i];
                } else {
                    $final_result = $final_result || $group_results[$i];
                }
            }
            
            if (!$final_result) return false; // 不符合規則 → 不可見
            
            // 符合規則，但被排除的人看不到
            if (in_array($cid, $user_excluded_courses)) {
                return false; // 在排除名單中 → 不可見
            }
            
            return true; // 符合規則且不在排除名單 → 可見
        });
        $all_courses = array_values($all_courses);
    }

        // 5. Apply Sorting
    usort($all_courses, function ($a, $b) use ($sort) {
        if ($sort === 'name_asc') {
            return strcasecmp($a['fullname'], $b['fullname']);
        } else if ($sort === 'name_desc') {
            return strcasecmp($b['fullname'], $a['fullname']);
        } else if ($sort === 'time_new') {
            return $b['id'] - $a['id']; // ID correlates to creation time roughly
        }
        return 0;
    });

    // 6. Pagination Slice
    $total_count = count($all_courses);
    $paginated_courses = array_slice($all_courses, $offset, $limit);

    echo json_encode([
        'success' => true,
        'total' => $total_count,
        'page' => $page,
        'limit' => $limit,
        'data' => $paginated_courses
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
