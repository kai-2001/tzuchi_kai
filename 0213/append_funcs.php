<?php
$funcs = <<<'EOD'

/**
 * 輔助函式：快速取得我的課程清單
 */
function fetch_my_courses_simple($moodle_uid)
{
    global $moodle_url, $moodle_token;
    $result = call_moodle($moodle_url, $moodle_token, 'core_enrol_get_users_courses', ['userid' => $moodle_uid]);
    return is_array($result) ? $result : [];
}

/**
 * 輔助函式: 在本地處理必修進度邏輯 (不連線 Moodle API)
 * 新版: 支援 portal_category_settings 的必修類別設定
 */
function process_curriculum_locally($all_courses, $my_courses_raw, $cat_info)
{
    if (empty($all_courses))
        return [];

    // 建立使用者已選修課程對照表
    $my_courses_lookup = [];
    if (is_array($my_courses_raw)) {
        foreach ($my_courses_raw as $c) {
            $my_courses_lookup[$c['id'] ?? 0] = $c;
        }
    }

    // 取得類別設定 (必修類別、需通過次數等)
    $category_settings = get_category_settings_map();

    // 取得課程必修標記
    $mandatory_courses = get_mandatory_courses_map();

    $curriculum_status = [];

    // 先按類別分組所有課程
    $courses_by_category = [];
    $seen_course_ids = []; // 追蹤已加入的課程ID

    $process_course = function($course, $is_enrolled_only = false) use (&$courses_by_category, &$seen_course_ids, &$my_courses_lookup, &$mandatory_courses, &$category_settings) {
        $course_id = $course['id'] ?? 0;
        if ($course_id <= 1 || isset($seen_course_ids[$course_id])) return;

        $cat_id = $course['categoryid'] ?? $course['category'] ?? null;
        if (!$cat_id) return;

        $target_cat_id = $cat_id;
        $is_mandatory_cat = isset($category_settings[$target_cat_id]) && $category_settings[$target_cat_id]['is_mandatory_category'];

        $is_enrolled = isset($my_courses_lookup[$course_id]);
        
        // 如果不是必修系統的課，且使用者也沒選課，為了效能可以跳過 (但為了自由選修列表，我們還是保留所有 visible 課程)
        $visible = isset($course['visible']) ? (int)$course['visible'] : 1;
        if ($visible == 0 && !$is_enrolled) {
            return;
        }

        if (!isset($courses_by_category[$target_cat_id])) {
            $courses_by_category[$target_cat_id] = [];
        }

        $seen_course_ids[$course_id] = true;

        $status = 'red';
        if ($is_enrolled) {
            $uc = $my_courses_lookup[$course_id];
            if (($uc['progress'] ?? 0) >= 100 || ($uc['completed'] ?? false)) {
                $status = 'green';
            } else {
                $status = 'yellow';
            }
        }

        $courses_by_category[$target_cat_id][] = [
            'id' => $course_id,
            'fullname' => $course['fullname'] ?? $course['shortname'] ?? '未知課程',
            'status' => $status,
            'is_mandatory' => isset($mandatory_courses[$course_id]) && $mandatory_courses[$course_id]['is_mandatory'] == 1,
            'display_order' => $mandatory_courses[$course_id]['display_order'] ?? 999
        ];
    };

    // 處理所有課程
    foreach ($all_courses as $course) {
        $process_course($course);
    }
    // 加入隱藏但已選的課程
    if (is_array($my_courses_raw)) {
        foreach ($my_courses_raw as $mc) {
            $process_course($mc, true);
        }
    }

    // 輔助函式：優化分類名稱顯示模式
    $generate_group_name = function ($cat_id) use (&$cat_info) {
        if (!isset($cat_info[$cat_id])) return '其他';
        
        $curr_cat = $cat_info[$cat_id];
        $child_name = $curr_cat['name'];
        $parent_id = $curr_cat['parent'] ?? 0;
        
        // 往上找一層 (而不是找最上層)
        if ($parent_id > 0 && isset($cat_info[$parent_id])) {
            $parent_name = $cat_info[$parent_id]['name'];
            if ($parent_name !== $child_name) {
                return $parent_name . ' - ' . $child_name;
            }
        }
        
        return $child_name;
    };

    // 分配到各必修領域
    foreach ($courses_by_category as $cat_id => $courses) {
        $settings = $category_settings[$cat_id] ?? null;
        $is_mandatory_cat = $settings && $settings['is_mandatory_category'];

        // ✨ 關鍵修正: 若非必修領域，不要把它放進 curriculum_status! 讓前端自己分類到「自由選修」
        if (!$is_mandatory_cat) {
            continue;
        }

        $group_name = $generate_group_name($cat_id);

        if ($settings['required_pass_count'] > 0) {
            $required_count = (int) $settings['required_pass_count'];

            $mandatory_course_ids_in_cat = [];
            foreach ($courses as $c) {
                if ($c['is_mandatory']) {
                    $mc_id = $c['id'];
                    $mc_info = $mandatory_courses[$mc_id] ?? [];
                    $mandatory_course_ids_in_cat[] = [
                        'id' => (int) $mc_id,
                        'order' => (int) ($mc_info['display_order'] ?? 999)
                    ];
                }
            }
            usort($mandatory_course_ids_in_cat, fn($a, $b) => $a['order'] - $b['order']);
            $mandatory_course_ids_in_cat = array_column($mandatory_course_ids_in_cat, 'id');

            $courses_lookup = [];
            foreach ($courses as $course) {
                $courses_lookup[$course['id']] = $course;
            }

            $lights = [];
            $filled = 0;
            $used_course_ids = [];

            // 1. 必填課
            foreach ($mandatory_course_ids_in_cat as $mc_id) {
                if ($filled >= $required_count) break;

                if (isset($courses_lookup[$mc_id])) {
                    $course = $courses_lookup[$mc_id];
                    $lights[] = [
                        'id' => $course['id'],
                        'fullname' => $course['fullname'],
                        'status' => $course['status'],
                        'category_name' => $group_name,
                        'is_mandatory_section' => true
                    ];
                    $used_course_ids[$mc_id] = true;
                    $filled++;
                }
            }

            // 2. 綠燈優先補
            if ($filled < $required_count) {
                foreach ($courses as $course) {
                    if ($filled >= $required_count) break;
                    if (!isset($used_course_ids[$course['id']]) && $course['status'] === 'green') {
                        $lights[] = [
                            'id' => $course['id'],
                            'fullname' => $course['fullname'],
                            'status' => $course['status'],
                            'category_name' => $group_name,
                            'is_mandatory_section' => false // 這裡改成 false 變成領域選修的進度列
                        ];
                        $used_course_ids[$course['id']] = true;
                        $filled++;
                    }
                }
            }
            
            // 3. 黃燈補
            if ($filled < $required_count) {
                foreach ($courses as $course) {
                    if ($filled >= $required_count) break;
                    if (!isset($used_course_ids[$course['id']]) && $course['status'] === 'yellow') {
                        $lights[] = [
                            'id' => $course['id'],
                            'fullname' => $course['fullname'],
                            'status' => $course['status'],
                            'category_name' => $group_name,
                            'is_mandatory_section' => false // 領域選修
                        ];
                        $used_course_ids[$course['id']] = true;
                        $filled++;
                    }
                }
            }

            // 4. 其他紅燈補
            while ($filled < $required_count) {
                $lights[] = [
                    'id' => 0,
                    'fullname' => '請挑選此領域中的課程修課',
                    'status' => 'red',
                    'category_name' => $group_name,
                    'available_count' => count($courses) - count($used_course_ids),
                    'is_mandatory_section' => false // 領域選修空窗
                ];
                $filled++;
            }

            // 放剩下的課進去，但 is_mandatory_section = false
            foreach ($courses as $course) {
                if (!isset($used_course_ids[$course['id']])) {
                    $lights[] = [
                        'id' => $course['id'],
                        'fullname' => $course['fullname'],
                        'status' => $course['status'],
                        'category_name' => $group_name,
                        'is_mandatory_section' => false
                    ];
                }
            }

            if (!isset($curriculum_status[$group_name])) {
                $curriculum_status[$group_name] = [];
            }
            $curriculum_status[$group_name] = array_merge($lights, $curriculum_status[$group_name]);

        } else {
            // required_pass_count === 0 處理
            foreach ($courses as $course) {
                $curriculum_status[$group_name][] = [
                    'id' => $course['id'],
                    'fullname' => $course['fullname'],
                    'status' => $course['status'],
                    'category_name' => $group_name,
                    'is_mandatory_section' => false
                ];
            }
        }
    }

    // 補空領域 (因為有些領域如果沒有任何課程，上面的 foreach 不會遍歷)
    foreach ($category_settings as $cat_id => $settings) {
        if ($settings['is_mandatory_category'] && $settings['required_pass_count'] > 0) {
            $group_name = $generate_group_name($cat_id);
            if (!isset($curriculum_status[$group_name]) || empty($curriculum_status[$group_name])) {
                $required_count = (int) $settings['required_pass_count'];
                $lights = [];
                for ($i = 0; $i < $required_count; $i++) {
                    $lights[] = [
                        'id' => 0,
                        'fullname' => '該領域目前無開放課程',
                        'status' => 'red',
                        'category_name' => $group_name,
                        'available_count' => 0,
                        'is_mandatory_section' => false
                    ];
                }
                $curriculum_status[$group_name] = $lights;
            }
        }
    }

    return $curriculum_status;
}

/**
 * 取得所有類別設定的對照表
 */
function get_category_settings_map()
{
    try {
        $conn = get_portal_db();
        $result = $conn->query("SELECT * FROM portal_category_settings WHERE is_mandatory_category = 1");

        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[$row['moodle_category_id']] = $row;
            }
        }
        $conn->close();
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 取得所有必修課程的對照表
 */
function get_mandatory_courses_map()
{
    try {
        $conn = get_portal_db();
        $result = $conn->query("SELECT * FROM portal_mandatory_courses");

        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[$row['moodle_course_id']] = $row;
            }
        }
        $conn->close();
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

EOD;

$file = 'c:\Apache24\htdocs\0213\includes\moodle_api.php';
$content = file_get_contents($file);

// Insert right before moodle_get_user_role_context
$content = str_replace("function moodle_get_user_role_context(", $funcs . "function moodle_get_user_role_context(", $content);

file_put_contents($file, $content);

echo "Functions appended perfectly!\n";
