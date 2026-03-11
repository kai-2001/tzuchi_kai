<?php
/**
 * Hospital Admin API Controller
 * app/Controllers/Api/HospitalAdminController.php
 * 
 * Handles user management for hospital admins.
 * Replaces api/hospital_admin/manage_users.php
 */

require_once __DIR__ . '/../../Services/MoodleSyncService.php';

class HospitalAdminController extends Controller
{
    private MoodleSyncService $moodleSync;

    public function __construct()
    {
        parent::__construct();
        $this->moodleSync = new MoodleSyncService();
    }

    /**
     * Get Cohorts for Dropdown
     * GET ?route=hospital/cohorts
     */
    public function getCohorts()
    {
        $this->requireHospitalAdmin();
        $mgmtCatId = $this->getManagementCategoryId();

        if (empty($mgmtCatId)) {
            ApiResponse::success([]);
            return;
        }

        try {
            $moodleConn = $this->db->getMoodleConnection();

            // 1. Get path
            $stmt = $moodleConn->prepare("SELECT path FROM mdl_course_categories WHERE id = ?");
            $stmt->bind_param("i", $mgmtCatId);
            $stmt->execute();
            $pathRes = $stmt->get_result();

            if ($pathRes->num_rows === 0) {
                ApiResponse::success([]);
                return;
            }

            $pathRow = $pathRes->fetch_assoc();
            $mgmtPath = $pathRow['path'];
            $stmt->close();

            // 2. Query cohorts
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

            ApiResponse::success($cohorts);

        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * List Users
     * GET ?route=hospital/users
     */
    public function index()
    {
        $this->requireHospitalAdmin();
        $currentInstitution = $this->getInstitutionName();
        $mgmtCatId = $this->getManagementCategoryId();
        $filterCohortId = $this->input('cohort_id');

        try {
            // 1. Get Local Users
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT id, username, fullname, email, role FROM users WHERE institution = ? ORDER BY id DESC");
            $stmt->bind_param("s", $currentInstitution);
            $stmt->execute();
            $res = $stmt->get_result();

            $localUsers = [];
            while ($row = $res->fetch_assoc()) {
                $localUsers[strtolower($row['username'])] = $row;
            }
            $stmt->close();

            // 2. Get Moodle Cohorts
            $usernameToCohorts = [];
            if ($mgmtCatId) {
                $moodleConn = $this->db->getMoodleConnection();

                $stmt = $moodleConn->prepare("SELECT path FROM mdl_course_categories WHERE id = ?");
                $stmt->bind_param("i", $mgmtCatId);
                $stmt->execute();
                $pathRes = $stmt->get_result();

                if ($pathRes->num_rows > 0) {
                    $pathRow = $pathRes->fetch_assoc();
                    $mgmtPath = $pathRow['path'];
                    $stmt->close();

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

                    // 3. Get Moodle Tags
                    $usernames = array_keys($localUsers);
                    if (!empty($usernames)) {
                        // Batch fetch Moodle IDs
                        $muids = [];
                        $muidToUsername = [];
                        $chunks = array_chunk($usernames, 1000);

                        foreach ($chunks as $chunk) {
                            $types = str_repeat('s', count($chunk));
                            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                            $uStmt = $moodleConn->prepare("SELECT id, username FROM mdl_user WHERE username IN ($placeholders)");
                            $uStmt->bind_param($types, ...$chunk);
                            $uStmt->execute();
                            $uRes = $uStmt->get_result();
                            while ($uRow = $uRes->fetch_assoc()) {
                                $mId = $uRow['id'];
                                $uName = strtolower($uRow['username']);
                                $muids[] = $mId;
                                $muidToUsername[$mId] = $uName;
                                if (isset($localUsers[$uName])) {
                                    $localUsers[$uName]['moodle_uid'] = $mId;
                                }
                            }
                            $uStmt->close();
                        }

                        // Batch fetch Tags
                        if (!empty($muids)) {
                            $muidChunks = array_chunk($muids, 1000);
                            $uidToTags = [];
                            foreach ($muidChunks as $chunk) {
                                $types = str_repeat('i', count($chunk));
                                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                                $tagSql = "
                                    SELECT ti.itemid as m_uid, t.rawname
                                    FROM mdl_tag t
                                    JOIN mdl_tag_instance ti ON t.id = ti.tagid
                                    WHERE ti.itemtype = 'user' AND ti.itemid IN ($placeholders)
                                 ";
                                $tStmt = $moodleConn->prepare($tagSql);
                                $tStmt->bind_param($types, ...$chunk);
                                $tStmt->execute();
                                $tRes = $tStmt->get_result();
                                while ($tRow = $tRes->fetch_assoc()) {
                                    $mId = $tRow['m_uid'];
                                    if (isset($muidToUsername[$mId])) {
                                        $uName = $muidToUsername[$mId];
                                        $uidToTags[$uName][] = $tRow['rawname'];
                                    }
                                }
                                $tStmt->close();
                            }

                            // Assign tags to localUsers
                            foreach ($uidToTags as $uName => $tags) {
                                if (isset($localUsers[$uName])) {
                                    $localUsers[$uName]['tags'] = implode(', ', $tags);
                                }
                            }
                        }
                    }

                } else {
                    $stmt->close();
                }
            }

            // 4. Dimension Mapping and Options Collection (With Path)
            $cohortToDimension = $this->getDimensionMapping($currentInstitution);

            // Build dim_options from the mapping itself (all available options)
            $dimOptions = [];
            foreach ($cohortToDimension as $cid => $data) {
                $dn = $data['dimension'];
                $dimOptions[$dn][] = [
                    'cohort_id' => $cid,
                    'display' => $data['full_path'] // Use Full Path for Dropdown
                ];
            }

            // 5. Combine Data
            $finalList = [];
            foreach ($localUsers as $uname => $row) {
                $myCohorts = $usernameToCohorts[$uname] ?? [];

                // Filter
                if (!empty($filterCohortId)) {
                    $inCohort = false;
                    foreach ($myCohorts as $mc) {
                        if ($mc['id'] == $filterCohortId) {
                            $inCohort = true;
                            break;
                        }
                    }
                    if (!$inCohort)
                        continue;
                }

                $dims = ['職類' => [], '所屬' => [], '屬性' => []];
                $dimIds = ['職類' => [], '所屬' => [], '屬性' => []];
                $displayGroups = [];

                foreach ($myCohorts as $mc) {
                    $cid = $mc['id'];
                    $displayGroups[] = $mc['name'];

                    if (isset($cohortToDimension[$cid])) {
                        $dimName = $cohortToDimension[$cid]['dimension'];
                        $shortName = $cohortToDimension[$cid]['display']; // Short Name for Table

                        // Collect for user column
                        if (isset($dims[$dimName])) {
                            $dims[$dimName][] = $shortName;
                            $dimIds[$dimName][] = (int) $cid;
                        } else if ($dimName === '層級') {
                            $dims['所屬'][] = $shortName;
                            $dimIds['所屬'][] = (int) $cid;
                        }
                    }
                }

                $row['groups_display'] = empty($displayGroups) ? $currentInstitution : implode(', ', $displayGroups);
                $row['dim_職類'] = implode(', ', $dims['職類']);
                $row['dim_所屬'] = implode(', ', $dims['所屬']);
                $row['dim_屬性'] = implode(', ', $dims['屬性']);
                $row['dim_職類_ids'] = $dimIds['職類'];
                $row['dim_所屬_ids'] = $dimIds['所屬'];
                $row['dim_屬性_ids'] = $dimIds['屬性'];
                $row['tags'] = $row['tags'] ?? ''; // Default empty if no tags found

                $finalList[] = $row;
            }

            ApiResponse::success([
                'users' => array_values($finalList),
                'dim_options' => $dimOptions
            ]);

        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Create User
     * POST ?route=hospital/users/create
     */
    public function create()
    {
        $this->requireHospitalAdmin();

        $username = $this->inputString('username');
        $fullname = $this->inputString('fullname');
        $email = $this->inputString('email');
        $password = $this->inputString('password');
        $role = $this->inputString('role', 'student');
        $tags = $this->inputString('tags');

        $cohortIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $cid = $this->inputInt("dim_cohort_$i");
            if ($cid > 0)
                $cohortIds[] = $cid;
        }
        $legacyCohort = $this->inputInt('cohort_id');
        if ($legacyCohort > 0)
            $cohortIds[] = $legacyCohort;
        $cohortIds = array_unique($cohortIds);

        if (empty($username) || empty($password) || empty($fullname) || empty($email)) {
            ApiResponse::error('必填欄位不完整');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            ApiResponse::error('帳號只能包含英文、數字、底線');
        }
        if (strlen($password) < 8) {
            ApiResponse::error('密碼需8碼以上');
        }

        try {
            $check = $this->db->getConnection()->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                ApiResponse::error('此帳號已存在');
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $institution = $this->getInstitutionName();

            $stmt = $this->db->getConnection()->prepare("INSERT INTO users (username, password, fullname, email, institution, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed, $fullname, $email, $institution, $role);
            if (!$stmt->execute()) {
                throw new Exception('本地寫入失敗');
            }
            $localId = $stmt->insert_id;

            try {
                $moodleUser = $this->moodleSync->ensureUserExists($username, $fullname, $email, $institution);
                if (!$moodleUser || !isset($moodleUser['id'])) {
                    throw new Exception('Moodle 建立失敗');
                }
                $moodleUserId = $moodleUser['id'];

                $this->assignCohorts($moodleUserId, $cohortIds, $institution);

                if (!empty($tags)) {
                    $this->moodleSync->setUserTags($username, $tags);
                }

                ApiResponse::success(['id' => $localId, 'message' => '建立成功']);

            } catch (Exception $e) {
                $this->db->getConnection()->query("DELETE FROM users WHERE id = $localId");
                throw $e;
            }

        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Update User
     * POST ?route=hospital/users/update
     */
    public function update()
    {
        $this->requireHospitalAdmin();

        $userId = $this->inputInt('id');
        $fullname = $this->inputString('fullname');
        $email = $this->inputString('email');
        $password = $this->inputString('password');
        $tags = $this->inputString('tags');

        if (empty($userId) || empty($fullname) || empty($email)) {
            ApiResponse::error('欄位不完整');
        }

        try {
            $conn = $this->db->getConnection();
            $check = $conn->prepare("SELECT username FROM users WHERE id = ? AND institution = ?");
            $inst = $this->getInstitutionName();
            $check->bind_param("is", $userId, $inst);
            $check->execute();
            $chkRes = $check->get_result();
            if ($chkRes->num_rows === 0)
                ApiResponse::forbidden('無權限');
            $user = $chkRes->fetch_assoc();
            $check->close();

            $upStmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $upStmt->bind_param("ssi", $fullname, $email, $userId);
            $upStmt->execute();

            if (!empty($password)) {
                if (strlen($password) < 8)
                    ApiResponse::error('密碼需8碼以上');
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pwdStmt->bind_param("si", $hashed, $userId);
                $pwdStmt->execute();

                // Sync Moodle Password
                $this->moodleSync->updatePassword($user['username'], $password);
            }

            // Sync Tags (Always sync if present in payload, or check if set?)
            // Frontend always sends tags.
            if ($tags !== null) {
                $this->moodleSync->setUserTags($user['username'], $tags);
            }

            ApiResponse::success(['message' => '更新成功']);

        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Update Role
     * POST ?route=hospital/users/update_role
     */
    public function updateRole()
    {
        $this->requireHospitalAdmin();
        $id = $this->inputInt('id');
        $newRole = $this->inputString('role');

        if (!$id || empty($newRole)) {
            ApiResponse::error('參數不足');
        }

        $allowedRoles = ['student', 'coursecreator'];
        if (!in_array($newRole, $allowedRoles)) {
            ApiResponse::error("無權限指派此角色: $newRole");
        }

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT username, role, institution FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0)
                ApiResponse::error('找不到使用者');
            $user = $res->fetch_assoc();
            $stmt->close();

            // Check institution permission
            if ($user['institution'] !== $this->getInstitutionName()) {
                ApiResponse::forbidden('無權限');
            }

            $currentRole = $user['role'];
            if ($currentRole === $newRole) {
                ApiResponse::success(['message' => '角色未變更']);
                return;
            }

            // Update Local
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $newRole, $id);
            $stmt->execute();
            $stmt->close();

            // Sync Moodle (best effort)
            $moodleWarning = '';
            try {
                $username = $user['username'];
                $mgmtCatId = $this->getManagementCategoryId();

                // Allow specific category assignment
                $targetCatId = $this->inputInt('target_category_id');
                $assignmentCatId = ($targetCatId > 0) ? $targetCatId : $mgmtCatId;

                // 1. Assign Course Creator
                if ($newRole === 'coursecreator') {
                    if ($assignmentCatId > 0) {
                        $this->moodleSync->assignRole($username, 'coursecreator', $assignmentCatId);
                    }
                }

                // 2. Unassign if demoted
                if ($currentRole === 'coursecreator' && $newRole !== 'coursecreator') {
                    if ($mgmtCatId > 0) {
                        $this->moodleSync->unassignRole($username, 'coursecreator', $mgmtCatId);
                    }
                }
            } catch (Exception $moodleEx) {
                $moodleWarning = '（Moodle 同步警告: ' . $moodleEx->getMessage() . '）';
            }

            ApiResponse::success(['message' => '權限已更新' . $moodleWarning]);

        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Reset Password
     * POST ?route=hospital/users/reset_password
     */
    public function resetPassword()
    {
        $this->requireHospitalAdmin();
        $id = $this->inputInt('id');
        $password = $this->inputString('password');

        if (!$id || empty($password)) {
            ApiResponse::error('參數不足');
        }
        if (strlen($password) < 8) {
            ApiResponse::error('密碼需8碼以上');
        }

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT username, institution FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0)
                ApiResponse::error('找不到使用者');
            $user = $res->fetch_assoc();
            $stmt->close();

            if ($user['institution'] !== $this->getInstitutionName()) {
                ApiResponse::forbidden('無權限');
            }

            // Update Local
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $id);
            $stmt->execute();
            $stmt->close();

            // Update Moodle
            $this->moodleSync->updatePassword($user['username'], $password);

            ApiResponse::success(['message' => '密碼重設成功']);

        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Helper: Assign Cohorts
     */
    private function assignCohorts($moodleUserId, $cohortIds, $institutionName)
    {
        if (!$moodleUserId)
            return;
        $moodleConn = $this->db->getMoodleConnection();
        $now = time();

        foreach ($cohortIds as $cid) {
            $check = $moodleConn->prepare("SELECT id FROM mdl_cohort_members WHERE cohortid = ? AND userid = ?");
            $check->bind_param("ii", $cid, $moodleUserId);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $stmt = $moodleConn->prepare("INSERT INTO mdl_cohort_members (cohortid, userid, timeadded) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $cid, $moodleUserId, $now);
                $stmt->execute();
            }
        }

        $localConn = $this->db->getConnection();
        $stmt = $localConn->prepare("SELECT cohort_idnumber FROM institutions WHERE name = ?");
        $stmt->bind_param("s", $institutionName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $parentIdNum = $row['cohort_idnumber'];
            if ($parentIdNum) {
                $pCheck = $moodleConn->prepare("SELECT id FROM mdl_cohort WHERE idnumber = ?");
                $pCheck->bind_param("s", $parentIdNum);
                $pCheck->execute();
                $pRes = $pCheck->get_result();
                if ($pRow = $pRes->fetch_assoc()) {
                    $pid = $pRow['id'];
                    $check = $moodleConn->prepare("SELECT id FROM mdl_cohort_members WHERE cohortid = ? AND userid = ?");
                    $check->bind_param("ii", $pid, $moodleUserId);
                    $check->execute();
                    if ($check->get_result()->num_rows === 0) {
                        $stmt = $moodleConn->prepare("INSERT INTO mdl_cohort_members (cohortid, userid, timeadded) VALUES (?, ?, ?)");
                        $stmt->bind_param("iii", $pid, $moodleUserId, $now);
                        $stmt->execute();
                    }
                }
            }
        }
    }

    /**
     * Helper: Get Dimension Mapping with Full Path
     */
    private function getDimensionMapping($institutionName)
    {
        $mapping = [];
        $currInstId = $this->getInstitutionId();

        if ($currInstId <= 0) {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT id FROM institutions WHERE name = ? LIMIT 1");
            $stmt->bind_param("s", $institutionName);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $currInstId = $row['id'];
            }
            $stmt->close();
        }

        if ($currInstId > 0) {
            $conn = $this->db->getConnection();
            // Fetch parent_cohort_id to build hierarchy
            $sql = "SELECT cd.moodle_cohort_id, cd.parent_cohort_id, dt.name as dimension_name, cd.display_name
                    FROM cohort_dimensions cd
                    JOIN dimension_types dt ON cd.dimension_type_id = dt.id
                    WHERE dt.institution_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $currInstId);
            $stmt->execute();
            $res = $stmt->get_result();

            $allCohorts = [];
            while ($row = $res->fetch_assoc()) {
                $allCohorts[$row['moodle_cohort_id']] = [
                    'id' => $row['moodle_cohort_id'],
                    'parent_id' => $row['parent_cohort_id'],
                    'dimension' => $row['dimension_name'],
                    'display' => $row['display_name']
                ];
            }
            $stmt->close();

            // Helper closure to recursive build path
            // Note: In PHP < 7.4 we use explicit closure binding, but here we can just pass array.
            // Or better, iteration.

            foreach ($allCohorts as $cid => $data) {
                $path = [];
                $curr = $data;
                $visited = [$cid => true];

                // Build path upwards
                $tempPath = [$data['display']];
                $pid = $data['parent_id'];

                while ($pid && isset($allCohorts[$pid]) && !isset($visited[$pid])) {
                    $visited[$pid] = true;
                    $parentInfo = $allCohorts[$pid];
                    array_unshift($tempPath, $parentInfo['display']);
                    $pid = $parentInfo['parent_id'];
                }

                $fullPath = implode(' / ', $tempPath);

                $mapping[$cid] = [
                    'dimension' => $data['dimension'],
                    'display' => $data['display'], // Short name
                    'full_path' => $fullPath // Hierarchy
                ];
            }
        }
        return $mapping;
    }
}
