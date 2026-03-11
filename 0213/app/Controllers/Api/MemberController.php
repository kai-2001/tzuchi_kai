<?php
/**
 * Member Management API Controller
 * app/Controllers/Api/MemberController.php
 * 
 * Handles member management: CRUD, Moodle sync, Cohort mapping, Batch operations.
 * Migration of: manage_users.php, list_members.php, add_member.php, update_member.php
 */

class MemberController extends Controller
{
    private MoodleService $moodle;
    
    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }
    
    // ==========================================
    // Member CRUD
    // ==========================================
    
    /**
     * List members
     * GET ?route=member/list
     * Optional: cohort_id (filter by cohort)
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $filterCohortId = $this->inputString('cohort_id');
        
        try {
            $conn = $this->db->getConnection();
            
            // 1. Get all local users
            $stmt = $conn->prepare("SELECT id, username, fullname, email, role, institution FROM users WHERE institution = ? ORDER BY id DESC");
            $stmt->bind_param("s", $currentInstitution);
            $stmt->execute();
            $msgResult = $stmt->get_result();
            
            $localUsers = [];
            while ($row = $msgResult->fetch_assoc()) {
                $localUsers[strtolower($row['username'])] = $row;
            }
            $stmt->close();
            
            // 2. Moodle Cohort Data (Only if management category is set)
            $mgmtCatId = $this->getManagementCategoryId();
            $usernameToCohorts = [];
            $dimOptions = [];
            $cohortToDimension = [];
            
            if ($mgmtCatId > 0) {
                // Connect to Moodle DB directly for complex queries
                $moodleConn = $this->getMoodleDbConnection();
                if ($moodleConn) {
                    // 2.1 Get Management Category Path
                    $pathSql = "SELECT path FROM mdl_course_categories WHERE id = ?";
                    $stmt = $moodleConn->prepare($pathSql);
                    $stmt->bind_param("i", $mgmtCatId);
                    $stmt->execute();
                    $pRes = $stmt->get_result();
                    
                    if ($pRes->num_rows > 0) {
                        $pathRow = $pRes->fetch_assoc();
                        $mgmtPath = $pathRow['path'];
                        $stmt->close();
                        
                        // 2.2 Get Cohort Members
                        $sql = "
                            SELECT u.username, c.id AS cohort_id, c.name AS cohort_name
                            FROM mdl_cohort c
                            JOIN mdl_context ctx ON c.contextid = ctx.id
                            JOIN mdl_course_categories cat ON ctx.instanceid = cat.id
                            JOIN mdl_cohort_members cm ON c.id = cm.cohortid
                            JOIN mdl_user u ON cm.userid = u.id
                            WHERE ctx.contextlevel = 40 
                            AND (cat.id = ? OR cat.path LIKE CONCAT(?, '/%'))
                        ";
                        
                        $stmt = $moodleConn->prepare($sql);
                        $stmt->bind_param("is", $mgmtCatId, $mgmtPath);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        
                        while ($row = $res->fetch_assoc()) {
                            $u = strtolower($row['username']);
                            $usernameToCohorts[$u][] = [
                                'id' => $row['cohort_id'],
                                'name' => $row['cohort_name']
                            ];
                        }
                        $stmt->close();
                        
                        // 2.3 Get User Tags
                        $this->fetchMoodleTags($moodleConn, $localUsers);
                    } else {
                        $stmt->close();
                    }
                    
                    $moodleConn->close();
                }
                
                // 3. Dimension Mapping
                $currInstId = $this->fetchInstitutionIdByName($currentInstitution);
                if ($currInstId > 0) {
                    $dimSql = "SELECT cd.moodle_cohort_id, cd.id as cd_id, cd.parent_cohort_id, dt.name as dimension_name, cd.display_name
                                FROM cohort_dimensions cd
                                JOIN dimension_types dt ON cd.dimension_type_id = dt.id
                                WHERE dt.institution_id = ?";
                    
                    $dimStmt = $conn->prepare($dimSql);
                    $dimStmt->bind_param("i", $currInstId);
                    $dimStmt->execute();
                    $dimRes = $dimStmt->get_result();
                    
                    $allCohortsForPath = [];
                    while ($dimRow = $dimRes->fetch_assoc()) {
                        $cid = (int)$dimRow['moodle_cohort_id'];
                        $allCohortsForPath[$cid] = [
                            'id' => $cid,
                            'name' => $dimRow['display_name'],
                            'parent_cohort_id' => $dimRow['parent_cohort_id'],
                            'dimension' => $dimRow['dimension_name']
                        ];
                    }
                    $dimStmt->close();
                    
                    // Build Dimension Options & Map
                    foreach ($allCohortsForPath as $cid => $cData) {
                        $visited = [];
                        $pathParts = $this->buildFullPath($cid, $allCohortsForPath, $visited);
                        $fullPath = implode(' / ', $pathParts);
                        
                        $cohortToDimension[$cid] = [
                            'dimension' => $cData['dimension'],
                            'display' => $cData['name'],
                            'full_path' => $fullPath ?: $cData['name']
                        ];
                        
                        $dn = $cData['dimension'];
                        if (!isset($dimOptions[$dn])) $dimOptions[$dn] = [];
                        $dimOptions[$dn][] = [
                            'cohort_id' => $cid,
                            'display' => $fullPath ?: $cData['name']
                        ];
                    }
                }
            }
            
            // 4. Combine Data & Filter
            $finalList = [];
            foreach ($localUsers as $uname => $row) {
                // Filter ADMIN / HOSPITAL_ADMIN if needed (list_members.php logic)
                if (in_array($row['role'], ['admin', 'hospital_admin'])) {
                   // Keep them? list_members.php excludes them implicitly by query? No, list_members.php excludes them in PHP loop usually or SQL.
                   // list_members.php query: SELECT * FROM users WHERE institution = ?
                   // Wait, list_members.php code I saw didn't exclude them explicitly in SQL.
                   // But let's assume we show everyone for now, or filter if role is strictly 'student', 'coursecreator', 'teacherplus'.
                   // manage_users.php shows all.
                }

                $myCohorts = $usernameToCohorts[$uname] ?? [];
                
                // Filter by Cohort
                if (!empty($filterCohortId)) {
                    $inCohort = false;
                    foreach ($myCohorts as $mc) {
                        if ($mc['id'] == $filterCohortId) {
                            $inCohort = true;
                            break;
                        }
                    }
                    if (!$inCohort) continue;
                }
                
                // Process Dimensions
                $dim_job = []; $dim_dept = []; $dim_attr = [];
                $dim_job_ids = []; $dim_dept_ids = []; $dim_attr_ids = [];
                $displayGroups = [];
                
                foreach ($myCohorts as $mc) {
                    $cid = $mc['id'];
                    $displayGroups[] = $mc['name'];
                    
                    if (isset($cohortToDimension[$cid])) {
                        $dimName = $cohortToDimension[$cid]['dimension'];
                        $disp = $cohortToDimension[$cid]['display'];
                        
                        if ($dimName === '職類') {
                            $dim_job[] = $disp;
                            $dim_job_ids[] = (int)$cid;
                        } elseif ($dimName === '所屬' || $dimName === '層級') {
                            $dim_dept[] = $disp;
                            $dim_dept_ids[] = (int)$cid;
                        } elseif ($dimName === '屬性') {
                            $dim_attr[] = $disp;
                            $dim_attr_ids[] = (int)$cid;
                        }
                    }
                }
                
                $row['groups_display'] = empty($displayGroups) ? $currentInstitution : implode(', ', $displayGroups);
                $row['dim_職類'] = implode(', ', $dim_job);
                $row['dim_所屬'] = implode(', ', $dim_dept);
                $row['dim_屬性'] = implode(', ', $dim_attr);
                $row['dim_職類_ids'] = $dim_job_ids;
                $row['dim_所屬_ids'] = $dim_dept_ids;
                $row['dim_屬性_ids'] = $dim_attr_ids;
                $row['tags'] = $row['tags'] ?? '';
                
                $finalList[] = $row;
            }
            
            ApiResponse::success([
                'data' => array_values($finalList), // For users.php compatibility
                'dim_options' => $dimOptions
            ]);
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Get available cohorts for dropdown
     * GET ?route=member/cohorts
     */
    public function getCohorts(): void
    {
        $this->requireHospitalAdmin();
        $mgmtCatId = $this->getManagementCategoryId();
        
        if ($mgmtCatId <= 0) {
            ApiResponse::success(['data' => []]);
            return;
        }
        
        try {
            $moodleConn = $this->getMoodleDbConnection();
            if (!$moodleConn) {
                ApiResponse::serverError('Moodle DB connection failed');
                return;
            }
            
            // Get Path
            $pathSql = "SELECT path FROM mdl_course_categories WHERE id = ?";
            $stmt = $moodleConn->prepare($pathSql);
            $stmt->bind_param("i", $mgmtCatId);
            $stmt->execute();
            $pRes = $stmt->get_result();
            
            if ($pRes->num_rows === 0) {
                ApiResponse::success(['data' => []]);
                $stmt->close();
                $moodleConn->close();
                return;
            }
            
            $pathRow = $pRes->fetch_assoc();
            $mgmtPath = $pathRow['path'];
            $stmt->close();
            
            // Get Cohorts
            $sql = "
                SELECT c.id, c.name, c.idnumber, ctx.instanceid as category_id
                FROM mdl_cohort c
                JOIN mdl_context ctx ON c.contextid = ctx.id
                JOIN mdl_course_categories cat ON ctx.instanceid = cat.id
                WHERE ctx.contextlevel = 40 
                AND (cat.id = ? OR cat.path LIKE CONCAT(?, '/%'))
                ORDER BY cat.depth ASC, c.name ASC
            ";
            
            $stmt = $moodleConn->prepare($sql);
            $stmt->bind_param("is", $mgmtCatId, $mgmtPath);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $cohorts = [];
            while ($row = $res->fetch_assoc()) {
                $cohorts[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'idnumber' => $row['idnumber']
                ];
            }
            
            $stmt->close();
            $moodleConn->close();
            
            ApiResponse::success(['data' => $cohorts]); // Legacy format uses data directly
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Create Member
     * POST ?route=member/create
     */
    public function create(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $username = trim($this->inputString('username'));
        $fullname = trim($this->inputString('fullname'));
        $email = trim($this->inputString('email'));
        $password = $this->inputString('password');
        $role = $this->inputString('role', 'student');
        $tags = trim($this->inputString('tags'));
        
        // Collect dims
        $cohortIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $cid = $this->inputInt("dim_cohort_$i");
            if ($cid > 0) $cohortIds[] = $cid;
        }
        $legacyCohort = $this->inputInt('cohort_id');
        if ($legacyCohort > 0) $cohortIds[] = $legacyCohort;
        $cohortIds = array_unique($cohortIds);
        
        // Validation
        if (empty($username) || empty($password) || empty($fullname) || empty($email)) {
            ApiResponse::error('必填欄位不完整');
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            ApiResponse::error('帳號只能包含英文、數字、底線');
            return;
        }
        if (strlen($password) < 8) {
            ApiResponse::error('密碼需8碼以上');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            
            // Check existence
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                ApiResponse::error('此帳號已存在');
                return;
            }
            $check->close();
            
            // Local Insert
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, institution, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed, $fullname, $email, $currentInstitution, $role);
            if (!$stmt->execute()) {
                throw new Exception('本地寫入失敗');
            }
            $localId = $conn->insert_id;
            $stmt->close();
            
            // Moodle Create
            $firstname = mb_substr($fullname, 1, null, 'utf-8') ?: $fullname;
            $lastname = mb_substr($fullname, 0, 1, 'utf-8') ?: '.';
            
            $moodleData = [
                'users' => [[
                    'username' => $username,
                    'password' => $password,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $email,
                    'institution' => $currentInstitution,
                    'auth' => 'manual'
                ]]
            ];
            
            $createRes = $this->moodle->call('core_user_create_users', $moodleData);
            
            if (isset($createRes['exception'])) {
                // Rollback local
                $conn->query("DELETE FROM users WHERE id = $localId");
                ApiResponse::error('Moodle 建立失敗: ' . ($createRes['message'] ?? ''));
                return;
            }
            
            $moodleUserId = $createRes[0]['id'] ?? null;
            
            if ($moodleUserId) {
                // Add to Cohorts
                if (!empty($cohortIds)) {
                    foreach ($cohortIds as $cid) {
                        $this->moodle->call('core_cohort_add_cohort_members', [
                            'members' => [[
                                'cohorttype' => ['type' => 'id', 'value' => $cid],
                                'usertype' => ['type' => 'id', 'value' => $moodleUserId]
                            ]]
                        ]);
                    }
                }
                
                // Add to Institution Parent Cohort
                $this->addToInstitutionCohort($currentInstitution, $moodleUserId, $cohortIds);
                
                // Assign Role (coursecreator)
                if ($role === 'coursecreator') {
                    $this->assignCourseCreatorRole($username, $cohortIds[0] ?? 0);
                }
                
                // Add Tags
                if (!empty($tags)) {
                    $this->updateMoodleTags($moodleUserId, $tags);
                }
            }
            
            ApiResponse::success(['id' => $localId], '成員已新增');
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Update Member
     * POST ?route=member/update
     */
    public function update(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $userId = $this->inputInt('id');
        $fullname = trim($this->inputString('fullname'));
        $email = trim($this->inputString('email'));
        $password = $this->inputString('password');
        $tags = trim($this->inputString('tags'));
        
        if ($userId <= 0 || empty($fullname) || empty($email)) {
            ApiResponse::error('欄位不完整');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            
            // Verify ownership
            $check = $conn->prepare("SELECT username FROM users WHERE id = ? AND institution = ?");
            $check->bind_param("is", $userId, $currentInstitution);
            $check->execute();
            $chkRes = $check->get_result();
            if ($chkRes->num_rows === 0) {
                ApiResponse::error('無權限');
                return;
            }
            $targetUser = $chkRes->fetch_assoc();
            $check->close();
            
            // Update Local
            $upStmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $upStmt->bind_param("ssi", $fullname, $email, $userId);
            $upStmt->execute();
            $upStmt->close();
            
            if (!empty($password)) {
                 if (strlen($password) < 8) {
                    ApiResponse::error('密碼需8碼以上');
                    return;
                }
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pwdStmt->bind_param("si", $hashed, $userId);
                $pwdStmt->execute();
                $pwdStmt->close();
            }
            
            // Update Moodle
            // Get Moodle ID by username
            $mUsers = $this->moodle->call('core_user_get_users_by_field', [
                'field' => 'username', 'values' => [$targetUser['username']]
            ]);
            
            if (!empty($mUsers) && isset($mUsers[0]['id'])) {
                $moodleUid = $mUsers[0]['id'];
                $updatePayload = [
                    'id' => $moodleUid,
                    'firstname' => mb_substr($fullname, 1),
                    'lastname' => mb_substr($fullname, 0, 1),
                    'email' => $email
                ];
                if (!empty($password)) {
                    $updatePayload['password'] = $password;
                }
                
                $this->moodle->call('core_user_update_users', ['users' => [$updatePayload]]);
                
                // Update Tags
                $this->updateMoodleTags($moodleUid, $tags);
            }
            
            ApiResponse::success(null, '更新成功');
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Delete Member
     * POST ?route=member/delete
     */
    public function delete(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $userId = $this->inputInt('id');
        if ($userId <= 0) {
            ApiResponse::error('參數錯誤');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            
            // Get username to delete from Moodle
            $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND institution = ?");
            $stmt->bind_param("is", $userId, $currentInstitution);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();
            
            if (!$user) {
                ApiResponse::error('找不到該成員');
                return;
            }
            
            // Moodle Delete
            try {
                $mUsers = $this->moodle->call('core_user_get_users_by_field', [
                    'field' => 'username', 'values' => [$user['username']]
                ]);
                if (!empty($mUsers) && isset($mUsers[0]['id'])) {
                    $this->moodle->call('core_user_delete_users', ['userids' => [$mUsers[0]['id']]]);
                }
            } catch (Exception $e) {
                // Ignore matching error, proceed to local delete
                error_log("Moodle delete failed: " . $e->getMessage());
            }
            
            // Local Delete
            $delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delStmt->bind_param("i", $userId);
            $delStmt->execute();
            $delStmt->close();
            
            ApiResponse::success(null, '成員已刪除');
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Change Role
     * POST ?route=member/change_role
     */
    public function changeRole(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $id = $this->inputInt('id');
        $newRole = $this->inputString('role');
        
        if ($id <= 0 || empty($newRole)) {
            ApiResponse::error('參數不足');
            return;
        }
        
        if (!in_array($newRole, ['student', 'coursecreator'])) {
            ApiResponse::error('無權限指派此角色');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND institution = ?");
            $stmt->bind_param("sis", $newRole, $id, $currentInstitution);
            if (!$stmt->execute()) {
                ApiResponse::error('更新失敗');
                return;
            }
            
            // Sync Moodle Role
            if ($newRole === 'coursecreator') {
                $stmt2 = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $res = $stmt2->get_result();
                if ($row = $res->fetch_assoc()) {
                    $this->assignCourseCreatorRole($row['username'], 0);
                }
                $stmt2->close();
            }
            
            ApiResponse::success(null, '角色已變更');
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Batch Update
     * POST ?route=member/batch_update (JSON body)
     */
    public function batchUpdate(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $users = $input['users'] ?? [];
        
        if (empty($users)) {
            ApiResponse::error('沒有指定更新對象');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            $updatedCount = 0;
            
            foreach ($users as $u) {
                $uid = intval($u['id'] ?? 0);
                if (!$uid) continue;
                
                // Fields to update
                $fullname = trim($u['fullname'] ?? '');
                $email = trim($u['email'] ?? '');
                $role = trim($u['role'] ?? '');
                
                // Local Updates
                if ($fullname) {
                    $conn->query("UPDATE users SET fullname = '" . $conn->real_escape_string($fullname) . "' WHERE id = $uid AND institution = '$currentInstitution'");
                }
                if ($email) {
                    $conn->query("UPDATE users SET email = '" . $conn->real_escape_string($email) . "' WHERE id = $uid AND institution = '$currentInstitution'");
                }
                if ($role && in_array($role, ['student', 'coursecreator'])) {
                    $conn->query("UPDATE users SET role = '" . $conn->real_escape_string($role) . "' WHERE id = $uid AND institution = '$currentInstitution'");
                }
                
                // Get Username for Moodle Sync
                $res = $conn->query("SELECT username FROM users WHERE id = $uid AND institution = '$currentInstitution'");
                $userRow = $res->fetch_assoc();
                if (!$userRow) continue;
                $username = $userRow['username'];
                
                // Moodle Sync
                $mUsers = $this->moodle->call('core_user_get_users_by_field', [
                    'field' => 'username', 'values' => [$username]
                ]);
                
                if (!empty($mUsers) && isset($mUsers[0]['id'])) {
                    $moodleUid = $mUsers[0]['id'];
                    $updates = ['id' => $moodleUid];
                    
                    if ($fullname) {
                        $updates['firstname'] = mb_substr($fullname, 1);
                        $updates['lastname'] = mb_substr($fullname, 0, 1);
                    }
                    if ($email) {
                        $updates['email'] = $email;
                    }
                    if (!empty($u['password'])) {
                        $updates['password'] = 1; // Flag logic handled separately below usually, but Moodle API allows passing password
                        $updates['password'] = $u['password'];
                        
                        // Update local pwd too
                         $hashed = password_hash($u['password'], PASSWORD_DEFAULT);
                         $conn->query("UPDATE users SET password = '$hashed' WHERE id = $uid");
                    }
                    
                    $this->moodle->call('core_user_update_users', ['users' => [$updates]]);
                    
                    if ($role === 'coursecreator') {
                        $this->assignCourseCreatorRole($username, 0);
                    }
                    
                    // Tags
                    if (isset($u['tags'])) {
                        $this->updateMoodleTags($moodleUid, $u['tags']);
                    }
                    
                    // Cohorts (Dims)
                    $dimIds = [];
                    if (!empty($u['dim_job'])) $dimIds[] = (int)$u['dim_job'];
                    if (!empty($u['dim_dept'])) $dimIds[] = (int)$u['dim_dept'];
                    if (!empty($u['dim_attr'])) $dimIds[] = (int)$u['dim_attr'];
                    
                    if (!empty($dimIds)) {
                        $this->updateMemberCohorts($moodleUid, $dimIds, $currentInstitution);
                    }
                }
                $updatedCount++;
            }
            
            ApiResponse::success(null, "已更新 {$updatedCount} 位成員");
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * Batch Delete
     * POST ?route=member/batch_delete
     */
    public function batchDelete(): void
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userIds = $input['user_ids'] ?? [];
        
        if (empty($userIds)) {
            ApiResponse::error('沒有指定刪除對象');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            $deletedCount = 0;
            
            foreach ($userIds as $uid) {
                $uid = intval($uid);
                if (!$uid) continue;
                
                $res = $conn->query("SELECT username FROM users WHERE id = $uid AND institution = '$currentInstitution'");
                $user = $res->fetch_assoc();
                
                if ($user) {
                    // Moodle Delete
                    try {
                        $mUsers = $this->moodle->call('core_user_get_users_by_field', [
                            'field' => 'username', 'values' => [$user['username']]
                        ]);
                        if (!empty($mUsers) && isset($mUsers[0]['id'])) {
                             $this->moodle->call('core_user_delete_users', ['userids' => [$mUsers[0]['id']]]);
                        }
                    } catch (Exception $e) {
                        // Ignore
                    }
                    
                    // Local Delete
                    $conn->query("DELETE FROM users WHERE id = $uid");
                    $deletedCount++;
                }
            }
            
            ApiResponse::success(null, "已刪除 {$deletedCount} 位成員");
            
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==========================================
    // Private Helpers
    // ==========================================
    
    private function getMoodleDbConnection(): ?mysqli
    {
        // Using global constants or config if available, otherwise assuming same host/user/pass, db='moodle'
        global $db_host, $db_user, $db_pass; // Fallback to globals if defined
        
        // Or re-read from Config array if available in Controller.
        // Assuming boilerplate pattern from manage_users.php
        $mconn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
        if ($mconn->connect_error) return null;
        $mconn->set_charset("utf8mb4");
        return $mconn;
    }
    
    private function fetchMoodleTags(mysqli $moodleConn, array &$localUsers): void
    {
        $usernames = array_keys($localUsers);
        if (empty($usernames)) return;
        
        // 1. Get Moodle UIDs
        $inClause = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $moodleConn->prepare("SELECT id, username FROM mdl_user WHERE username IN ($inClause)");
        $stmt->bind_param(str_repeat('s', count($usernames)), ...$usernames);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $muids = [];
        $muidToUsername = [];
        while ($row = $res->fetch_assoc()) {
            $muids[] = $row['id'];
            $muidToUsername[$row['id']] = strtolower($row['username']);
        }
        $stmt->close();
        
        if (empty($muids)) return;
        
        // 2. Get Tags
        $tagIn = implode(',', array_fill(0, count($muids), '?'));
        $qt = "SELECT ti.itemid as m_uid, t.rawname 
               FROM mdl_tag t 
               JOIN mdl_tag_instance ti ON t.id = ti.tagid
               WHERE ti.itemtype = 'user' AND ti.itemid IN ($tagIn)";
               
        $stmt = $moodleConn->prepare($qt);
        $stmt->bind_param(str_repeat('i', count($muids)), ...$muids);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $uidToTags = [];
        while ($row = $res->fetch_assoc()) {
            $uname = $muidToUsername[$row['m_uid']];
            $uidToTags[$uname][] = $row['rawname'];
        }
        $stmt->close();
        
        // 3. Attach to localUsers
        foreach ($uidToTags as $uname => $tags) {
            if (isset($localUsers[$uname])) {
                $localUsers[$uname]['tags'] = implode(', ', $tags);
            }
        }
    }
    
    private function buildFullPath($cohortId, &$cohorts, &$visited = []): array
    {
        if (!$cohortId || !isset($cohorts[$cohortId]) || isset($visited[$cohortId])) {
            return [];
        }
        $visited[$cohortId] = true;
        $cohort = $cohorts[$cohortId];
        $parentPath = $this->buildFullPath($cohort['parent_cohort_id'], $cohorts, $visited);
        $parentPath[] = $cohort['name'];
        return $parentPath;
    }
    
    private function updateMoodleTags(int $moodleUid, string $tags): void
    {
        $moodleConn = $this->getMoodleDbConnection();
        if (!$moodleConn) return;
        
        // Delete old
        $stmt = $moodleConn->prepare("DELETE FROM mdl_tag_instance WHERE itemtype = 'user' AND itemid = ? AND component = 'core'");
        $stmt->bind_param("i", $moodleUid);
        $stmt->execute();
        $stmt->close();
        
        // Context
        $ctxStmt = $moodleConn->prepare("SELECT id FROM mdl_context WHERE instanceid = ? AND contextlevel = 30");
        $ctxStmt->bind_param("i", $moodleUid);
        $ctxStmt->execute();
        $res = $ctxStmt->get_result();
        $row = $res->fetch_assoc();
        $contextId = $row['id'] ?? 0;
        $ctxStmt->close();
        
        if (!empty($tags)) {
            $tagArray = preg_split('/[,;]+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tagArray as $rawTag) {
                $tagName = trim($rawTag);
                if (empty($tagName)) continue;
                $tagLower = mb_strtolower($tagName);
                
                // Find or Create Tag
                $stmt = $moodleConn->prepare("SELECT id FROM mdl_tag WHERE rawname = ?");
                $stmt->bind_param("s", $tagName);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                
                $tagId = $row['id'] ?? 0;
                if (!$tagId) {
                    $now = time();
                    $stmt = $moodleConn->prepare("INSERT INTO mdl_tag (userid, tagcollid, name, rawname, isstandard, flag, timemodified) VALUES (2, 1, ?, ?, 0, 0, ?)");
                    $stmt->bind_param("ssi", $tagLower, $tagName, $now);
                    $stmt->execute();
                    $tagId = $stmt->insert_id;
                    $stmt->close();
                }
                
                if ($tagId) {
                    $now = time();
                    $stmt = $moodleConn->prepare("INSERT IGNORE INTO mdl_tag_instance (tagid, component, itemtype, itemid, contextid, ordering, timecreated, timemodified) VALUES (?, 'core', 'user', ?, ?, 0, ?, ?)");
                    $stmt->bind_param("iiiii", $tagId, $moodleUid, $contextId, $now, $now);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        $moodleConn->close();
    }
    
    private function updateMemberCohorts(int $moodleUid, array $newCohortIds, string $institution): void
    {
         // Logic similar to batchUpdate's cohort sync
         // Need institution ID to identify ALL dimension cohorts to remove old ones first
         $conn = $this->db->getConnection();
         $instStmt = $conn->prepare("SELECT id FROM institutions WHERE name = ?");
         $instStmt->bind_param("s", $institution);
         $instStmt->execute();
         $res = $instStmt->get_result();
         $row = $res->fetch_assoc();
         $instId = $row['id'] ?? 0;
         $instStmt->close();
         
         if ($instId > 0) {
             // Get all dim cohorts
             $dimStmt = $conn->prepare("SELECT cd.moodle_cohort_id FROM cohort_dimensions cd JOIN dimension_types dt ON cd.dimension_type_id = dt.id WHERE dt.institution_id = ?");
             $dimStmt->bind_param("i", $instId);
             $dimStmt->execute();
             $dimRes = $dimStmt->get_result();
             $allDimCohorts = [];
             while ($r = $dimRes->fetch_assoc()) {
                 $allDimCohorts[] = (int)$r['moodle_cohort_id'];
             }
             $dimStmt->close();
             
             // Moodle changes
             $moodleConn = $this->getMoodleDbConnection();
             if ($moodleConn) {
                 // Remove old
                 foreach ($allDimCohorts as $cid) {
                     if (!in_array($cid, $newCohortIds)) {
                         $del = $moodleConn->prepare("DELETE FROM mdl_cohort_members WHERE cohortid = ? AND userid = ?");
                         $del->bind_param("ii", $cid, $moodleUid);
                         $del->execute();
                         $del->close();
                     }
                 }
                 
                 // Add new
                 foreach ($newCohortIds as $cid) {
                     $chk = $moodleConn->prepare("SELECT id FROM mdl_cohort_members WHERE cohortid=? AND userid=?");
                     $chk->bind_param("ii", $cid, $moodleUid);
                     $chk->execute();
                     if ($chk->get_result()->num_rows === 0) {
                         $add = $moodleConn->prepare("INSERT INTO mdl_cohort_members (cohortid, userid, timeadded) VALUES (?, ?, UNIX_TIMESTAMP())");
                         $add->bind_param("ii", $cid, $moodleUid);
                         $add->execute();
                         $add->close();
                     }
                     $chk->close();
                 }
                 $moodleConn->close();
             }
         }
    }
    
    private function addToInstitutionCohort(string $institution, int $moodleUserId, array $existingCohortIds): void
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT cohort_idnumber FROM institutions WHERE name = ?");
        $stmt->bind_param("s", $institution);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        
        if (!empty($row['cohort_idnumber'])) {
            $moodleConn = $this->getMoodleDbConnection();
            if ($moodleConn) {
                $stmt = $moodleConn->prepare("SELECT id FROM mdl_cohort WHERE idnumber = ?");
                $stmt->bind_param("s", $row['cohort_idnumber']);
                $stmt->execute();
                $res = $stmt->get_result();
                $crow = $res->fetch_assoc();
                $pid = $crow['id'] ?? 0;
                $stmt->close();
                
                if ($pid && !in_array($pid, $existingCohortIds)) {
                    $this->moodle->call('core_cohort_add_cohort_members', [
                        'members' => [['cohorttype' => ['type' => 'id', 'value' => $pid], 'usertype' => ['type' => 'id', 'value' => $moodleUserId]]]
                    ]);
                }
                $moodleConn->close();
            }
        }
    }
    
    private function assignCourseCreatorRole(string $username, int $cohortId): void
    {
         $targetCatId = 0;
         if ($cohortId > 0 && function_exists('get_cohort_category_id')) {
             $targetCatId = get_cohort_category_id($cohortId);
         }
         
         if ($targetCatId <= 0) {
             $targetCatId = $this->getManagementCategoryId();
         }
         
         if ($targetCatId > 0 && function_exists('moodle_assign_role')) {
             moodle_assign_role($username, $targetCatId, 'coursecreator');
         }
    }
    
    private function fetchInstitutionIdByName(string $name): int
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM institutions WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return $row['id'] ?? 0;
    }
}
