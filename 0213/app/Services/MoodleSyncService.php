<?php
/**
 * Moodle Sync Service
 * Encapsulates logic for syncing data between Portal and Moodle.
 * app/Services/MoodleSyncService.php
 */

class MoodleSyncService
{
    private $db;
    private $moodleDb;
    private $moodleUrl;
    private $moodleToken;

    public function __construct()
    {
        $this->db = Database::getInstance();
        
        // Load config (ideally this should be injected, but for now we pull from global/config)
        global $moodle_url, $moodle_token, $db_host, $db_user, $db_pass, $moodle_db_name;
        
        $this->moodleUrl = $moodle_url;
        $this->moodleToken = $moodle_token;
        
        // Setup Moodle DB connection
        $this->moodleDb = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
        if ($this->moodleDb->connect_error) {
            throw new Exception("Moodle DB Connection failed: " . $this->moodleDb->connect_error);
        }
        $this->moodleDb->set_charset("utf8mb4");
    }

    public function __destruct()
    {
        if ($this->moodleDb) {
            $this->moodleDb->close();
        }
    }

    /**
     * Ensure Moodle user exists (Sync from Portal to Moodle)
     */
    public function ensureUserExists($username, $fullname, $email, $institution = '')
    {
        // Check if user exists in Moodle
        $stmt = $this->moodleDb->prepare("SELECT id, auth FROM mdl_user WHERE username = ? AND deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        $stmt->close();

        // If not exists, create user via API
        return $this->createUserViaApi($username, $fullname, $email, $institution);
    }

    /**
     * Call Moodle Web Service to create user
     */
    private function createUserViaApi($username, $fullname, $email, $institution)
    {
        $functionName = 'core_user_create_users';
        $names = $this->splitName($fullname);
        
        $user = [
            'username' => strtolower($username),
            'password' => 'P@ssword123', // Temp password, should change
            'firstname' => $names['first'],
            'lastname' => $names['last'],
            'email' => $email,
            'auth' => 'manual',
            'department' => $institution,
            'lang' => 'zh_tw'
        ];

        $params = ['users' => [$user]];
        $resp = $this->callMoodleApi($functionName, $params);

        if (isset($resp[0]['id'])) {
            return ['id' => $resp[0]['id'], 'auth' => 'manual'];
        }
        
        error_log("Moodle create user failed: " . json_encode($resp));
        return null;
    }

    /**
     * Call Moodle API (CURL)
     */
    private function callMoodleApi($functionName, $params)
    {
        $serverUrl = $this->moodleUrl . '/webservice/rest/server.php' . 
                     '?wstoken=' . $this->moodleToken . 
                     '&wsfunction=' . $functionName . 
                     '&moodlewsrestformat=json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Get Moodle User ID by Username
     */
    public function getMoodleUserId($username)
    {
        $stmt = $this->moodleDb->prepare("SELECT id FROM mdl_user WHERE username = ? AND deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return $row['id'];
        }
        return null;
    }

    /**
     * Update Password
     */
    public function updatePassword($username, $newPassword)
    {
        $uid = $this->getMoodleUserId($username);
        if (!$uid) return false;

        $payload = ['users' => [['id' => $uid, 'password' => $newPassword]]];
        $resp = $this->callMoodleApi('core_user_update_users', $payload);
        return empty($resp); // Success returns null/empty array
    }

    /**
     * Assign Role
     */
    public function assignRole($username, $roleShortname, $categoryId)
    {
        $userid = $this->getMoodleUserId($username);
        if (!$userid) return false;

        // Get Role ID
        $stmt = $this->moodleDb->prepare("SELECT id FROM mdl_role WHERE shortname = ?");
        $stmt->bind_param("s", $roleShortname);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $roleId = $row['id'];
        } else {
            return false;
        }
        $stmt->close();

        // Assign via API
        $assignments = [
            'assignments' => [[
                'roleid' => $roleId,
                'userid' => $userid,
                'contextlevel' => 'coursecat',
                'instanceid' => $categoryId
            ]]
        ];

        $resp = $this->callMoodleApi('core_role_assign_roles', $assignments);
        return empty($resp);
    }
    
    /**
     * Unassign Role
     */
    public function unassignRole($username, $roleShortname, $categoryId)
    {
        $userid = $this->getMoodleUserId($username);
        if (!$userid) return false;

        // Get Role ID
        $stmt = $this->moodleDb->prepare("SELECT id FROM mdl_role WHERE shortname = ?");
        $stmt->bind_param("s", $roleShortname);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $roleId = $row['id'];
        } else {
            return false;
        }
        $stmt->close();

        // Unassign via API
        $unassignments = [
            'unassignments' => [[
                'roleid' => $roleId,
                'userid' => $userid,
                'contextlevel' => 'coursecat',
                'instanceid' => $categoryId
            ]]
        ];

        $resp = $this->callMoodleApi('core_role_unassign_roles', $unassignments);
        return empty($resp);
    }

    /**
     * Set User Tags (Direct DB)
     * Replaces all existing Moodle tags for a user with the given tag names.
     * Uses direct DB operations since core_tag_set_item_tags requires 
     * webservice capabilities that may not be enabled.
     */
    public function setUserTags($username, $tagsStr)
    {
        $userid = $this->getMoodleUserId($username);
        if (!$userid) return false;

        $contextid = $this->getUserContextId($userid);
        if (!$contextid) return false;

        // Parse tag names
        $tagNames = [];
        if (!empty($tagsStr)) {
            $parts = preg_split('/[,;]+/', $tagsStr);
            foreach ($parts as $t) {
                $t = trim($t);
                if (!empty($t)) {
                    $tagNames[] = $t;
                }
            }
        }

        // Get the default tag collection ID
        $collRes = $this->moodleDb->query("SELECT id FROM mdl_tag_coll WHERE isdefault = 1 LIMIT 1");
        $tagCollId = 1; // fallback
        if ($collRow = $collRes->fetch_assoc()) {
            $tagCollId = $collRow['id'];
        }

        // 1. Remove all existing tag instances for this user
        $delStmt = $this->moodleDb->prepare(
            "DELETE FROM mdl_tag_instance WHERE component = 'core' AND itemtype = 'user' AND itemid = ?"
        );
        $delStmt->bind_param("i", $userid);
        $delStmt->execute();
        $delStmt->close();

        // 2. For each tag, ensure it exists in mdl_tag and create instance
        $now = time();
        foreach ($tagNames as $idx => $tagName) {
            $tagNameLower = mb_strtolower($tagName, 'UTF-8');

            // Check if tag exists
            $chkStmt = $this->moodleDb->prepare("SELECT id FROM mdl_tag WHERE name = ? AND tagcollid = ?");
            $chkStmt->bind_param("si", $tagNameLower, $tagCollId);
            $chkStmt->execute();
            $chkRes = $chkStmt->get_result();

            if ($row = $chkRes->fetch_assoc()) {
                $tagId = $row['id'];
            } else {
                // Create new tag
                $insTag = $this->moodleDb->prepare(
                    "INSERT INTO mdl_tag (userid, tagcollid, name, rawname, isstandard, timemodified) VALUES (2, ?, ?, ?, 0, ?)"
                );
                $insTag->bind_param("issi", $tagCollId, $tagNameLower, $tagName, $now);
                $insTag->execute();
                $tagId = $this->moodleDb->insert_id;
                $insTag->close();
            }
            $chkStmt->close();

            // Create tag instance
            $insInst = $this->moodleDb->prepare(
                "INSERT INTO mdl_tag_instance (tagid, component, itemtype, itemid, contextid, ordering, timecreated, timemodified) VALUES (?, 'core', 'user', ?, ?, ?, ?, ?)"
            );
            $insInst->bind_param("iiiiii", $tagId, $userid, $contextid, $idx, $now, $now);
            $insInst->execute();
            $insInst->close();
        }

        return true;
    }


    /**
     * Get User Context ID
     */
    private function getUserContextId($userid)
    {
        // contextlevel 30 = USER
        $stmt = $this->moodleDb->prepare("SELECT id FROM mdl_context WHERE contextlevel = 30 AND instanceid = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return $row['id'];
        }
        return null;
    }

    /**
     * Helper: Split full name into First/Last
     */
    private function splitName($fullname)
    {
        // Simple logic for CJK names: Lastname = 1st char, Firstname = Rest
        // This is a naive implementation, can be improved
        if (mb_strlen($fullname) > 1) {
             return [
                 'last' => mb_substr($fullname, 0, 1),
                 'first' => mb_substr($fullname, 1)
             ];
        }
        return ['last' => $fullname, 'first' => $fullname];
    }
}

