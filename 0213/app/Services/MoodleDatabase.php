<?php
/**
 * Moodle Ë≥áÊ?Â∫´Ê???
 * app/Services/MoodleDatabase.php
 * 
 * Áµ±‰?ÁÆ°Á? Moodle Ë≥áÊ?Â∫´ÈÄ??ÔºåÊ?‰æõÂ∏∏?®Êü•Ë©¢ÊñπÊ≥?
 */

class MoodleDatabase
{
    private static ?MoodleDatabase $instance = null;
    private ?\mysqli $conn = null;

    private string $host;
    private string $user;
    private string $pass;
    private string $dbname;

    /**
     * ÁßÅÊ?Âª∫Ê?Â≠êÔ??Æ‰?Ê®°Â?Ôº?
     */
    private function __construct()
    {
        global $moodle_db_host, $moodle_db_user, $moodle_db_pass, $moodle_db_name;

        $this->host = $moodle_db_host ?? $GLOBALS['db_host'] ?? 'localhost';
        $this->user = $moodle_db_user ?? $GLOBALS['db_user'] ?? 'root';
        $this->pass = $moodle_db_pass ?? $GLOBALS['db_pass'] ?? '';
        $this->dbname = $moodle_db_name ?? 'moodle';
    }

    /**
     * ?ñÂ??Æ‰?ÂØ¶‰?
     */
    public static function getInstance(): MoodleDatabase
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ?ñÂ????
     */
    public function getConnection(): \mysqli
    {
        if ($this->conn === null || !$this->conn->ping()) {
            $this->conn = new \mysqli($this->host, $this->user, $this->pass, $this->dbname);
            if ($this->conn->connect_error) {
                throw new \Exception("Moodle DB ???Â§±Ê?: " . $this->conn->connect_error);
            }
            $this->conn->set_charset('utf8mb4');
        }
        return $this->conn;
    }

    /**
     * ?úÈ????
     */
    public function close(): void
    {
        if ($this->conn !== null) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    // ==================
    // Â∏∏Áî®?•Ë©¢?πÊ?
    // ==================

    /**
     * ??idnumber ?ñÂ?Áæ§Á??äÂÖ∂?ÄÂ±¨È???
     */
    public function getCohortByIdnumber(string $idnumber): ?array
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.idnumber, ctx.instanceid as category_id
            FROM mdl_cohort c
            JOIN mdl_context ctx ON c.contextid = ctx.id AND ctx.contextlevel = 40
            WHERE c.idnumber = ?
        ");
        $stmt->bind_param('s', $idnumber);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?: null;
    }

    /**
     * ?ñÂ?È°ûÂà•?ÑÊ??âÂ?È°ûÂà• IDÔºàÈ?Ëø¥Ô?
     */
    public function getAllDescendantCategoryIds(int $parentId): array
    {
        $conn = $this->getConnection();
        $result = $conn->query("SELECT id, parent FROM mdl_course_categories");

        $allCats = [];
        while ($row = $result->fetch_assoc()) {
            $allCats[$row['id']] = (int) $row['parent'];
        }

        $categoryIds = [$parentId];
        $findChildren = function ($pid) use (&$findChildren, $allCats, &$categoryIds) {
            foreach ($allCats as $id => $parent) {
                if ($parent == $pid) {
                    $categoryIds[] = $id;
                    $findChildren($id);
                }
            }
        };
        $findChildren($parentId);

        return $categoryIds;
    }

    /**
     * ?ñÂ??áÂ?È°ûÂà•‰∏ãÁ??Ä?âÁæ§Áµ?
     */
    public function getCohortsInCategories(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $conn = $this->getConnection();
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $types = str_repeat('i', count($categoryIds));

        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.idnumber, c.description,
                   (SELECT COUNT(*) FROM mdl_cohort_members cm WHERE cm.cohortid = c.id) as member_count,
                   ctx.instanceid as category_id
            FROM mdl_cohort c
            JOIN mdl_context ctx ON c.contextid = ctx.id AND ctx.contextlevel = 40
            WHERE ctx.instanceid IN ($placeholders)
            ORDER BY c.name
        ");
        $stmt->bind_param($types, ...$categoryIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $cohorts = [];
        while ($row = $result->fetch_assoc()) {
            $cohorts[] = $row;
        }
        $stmt->close();

        return $cohorts;
    }

    /**
     * ?ñÂ?È°ûÂà•Ë∑ØÂ?
     */
    public function getCategoryPath(int $categoryId): ?string
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("SELECT path FROM mdl_course_categories WHERE id = ?");
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['path'] ?? null;
    }

    /**
     * ??username ?ñÂ? Moodle ‰ΩøÁî®??
     */
    public function getUserByUsername(string $username): ?array
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("
            SELECT id, username, firstname, lastname, email, institution
            FROM mdl_user
            WHERE username = ? AND deleted = 0
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?: null;
    }

    /**
     * ?ñÂ?Áæ§Á??êÂì°??username ?óË°®
     */
    public function getCohortMemberUsernames(int $cohortId): array
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("
            SELECT u.username
            FROM mdl_cohort_members cm
            JOIN mdl_user u ON cm.userid = u.id
            WHERE cm.cohortid = ? AND u.deleted = 0
        ");
        $stmt->bind_param('i', $cohortId);
        $stmt->execute();
        $result = $stmt->get_result();

        $usernames = [];
        while ($row = $result->fetch_assoc()) {
            $usernames[] = $row['username'];
        }
        $stmt->close();

        return $usernames;
    }

    /**
     * ÂæûË™≤Á®ã‰∏≠ÁßªÈô§Â≠∏Âì°ÔºàÁõ¥?•Ê?‰ΩúË??ôÂ∫´ÔºåÊîØ?¥Ê??âÈÅ∏Ë™≤ÊñπÂºèÔ?
     * 
     * ?™Èô§?∏È?Ë°®Ô?
     * 1. mdl_user_enrolments ???∏Ë™≤Ë®òÈ?
     * 2. mdl_role_assignments ??ËßíËâ≤?áÊ¥æÔºàË™≤Á®?contextÔº?
     * 
     * @param int $moodleUserId Moodle ?®Êà∂ ID
     * @param int $courseId     Ë™≤Á? ID
     * @return array ['success' => bool, 'message' => string, 'details' => [...]]
     */
    public function unenrolUser(int $moodleUserId, int $courseId): array
    {
        $conn = $this->getConnection();

        try {
            // ?ñÂ?Ë™≤Á???context id (contextlevel=50 ‰ª?°® CONTEXT_COURSE)
            $ctxStmt = $conn->prepare("SELECT id FROM mdl_context WHERE contextlevel = 50 AND instanceid = ?");
            $ctxStmt->bind_param('i', $courseId);
            $ctxStmt->execute();
            $ctxResult = $ctxStmt->get_result()->fetch_assoc();
            $ctxStmt->close();

            $contextId = $ctxResult ? (int) $ctxResult['id'] : 0;

            // 1. ?™Èô§ user_enrolmentsÔºàÈÄèÈ? enrol Ë°®È???courseidÔº?
            $ueStmt = $conn->prepare("
                DELETE ue FROM mdl_user_enrolments ue
                JOIN mdl_enrol e ON ue.enrolid = e.id
                WHERE ue.userid = ? AND e.courseid = ?
            ");
            $ueStmt->bind_param('ii', $moodleUserId, $courseId);
            $ueStmt->execute();
            $enrolDeleted = $ueStmt->affected_rows;
            $ueStmt->close();

            // 2. ?™Èô§ role_assignmentsÔºàË™≤Á®?context ‰∏ãÁ?ËßíËâ≤Ôº?
            $raDeleted = 0;
            if ($contextId > 0) {
                $raStmt = $conn->prepare("
                    DELETE FROM mdl_role_assignments
                    WHERE userid = ? AND contextid = ?
                ");
                $raStmt->bind_param('ii', $moodleUserId, $contextId);
                $raStmt->execute();
                $raDeleted = $raStmt->affected_rows;
                $raStmt->close();
            }

            return [
                'success' => $enrolDeleted > 0,
                'message' => $enrolDeleted > 0
                    ? "Â∑≤Â? Moodle ÁßªÈô§Â≠∏Âì° (?™Èô§ {$enrolDeleted} Á≠ÜÈÅ∏Ë™≤Ë??? {$raDeleted} Á≠ÜË??≤Ê?Ê¥?"
                    : "??Moodle ‰∏≠Êâæ‰∏çÂà∞Ê≠§Â≠∏?°Á??∏Ë™≤Ë®òÈ?",
                'details' => [
                    'enrol_deleted' => $enrolDeleted,
                    'role_deleted' => $raDeleted,
                    'context_id' => $contextId,
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Moodle DB ?ç‰?Â§±Ê?: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * ?ñÂ??ôÂ∏´Âª∫Á??ÑË™≤Á®?ID ?óË°®
     * ?•Ë©¢ mdl_logstore_standard_logÔºåÊâæ?∫Ë©≤‰ΩøÁî®?ÖÂª∫Á´ã‰??™‰?Ë™≤Á?
     * 
     * @param int $moodleUserId Moodle ?®Êà∂ ID
     * @return array Ë™≤Á? ID ???
     */
    public function getTeacherCourseIds(int $moodleUserId): array
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare("
                SELECT DISTINCT courseid
                FROM mdl_logstore_standard_log
                WHERE userid = ?
                  AND eventname LIKE '%course_created'
                  AND courseid > 1
            ");
            $stmt->bind_param('i', $moodleUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = (int) $row['courseid'];
            }
            $stmt->close();
            return $ids;
        } catch (\Exception $e) {
            error_log("[MoodleDB] getTeacherCourseIds failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ?≤Ê≠¢Ë§áË£Ω
     */
    private function __clone()
    {
    }

    /**
     * ?≤Ê≠¢?çÂ??óÂ?
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
