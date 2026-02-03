<?php
/**
 * 屬性輔助函數
 * includes/attribute_helper.php
 */

/**
 * 取得使用者的所有屬性值 ID
 * @param int $user_id
 * @param mysqli $conn
 * @return array 屬性值 ID 陣列
 */
function get_user_attribute_ids($user_id, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $stmt = $conn->prepare("SELECT attribute_value_id FROM user_attributes WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['attribute_value_id'];
    }
    $stmt->close();

    if ($local_conn) {
        $conn->close();
    }

    return $ids;
}

/**
 * 取得使用者的所有屬性（帶類型資訊）
 * @param int $user_id
 * @param mysqli $conn
 * @return array 按類型分組的屬性
 */
function get_user_attributes_grouped($user_id, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $sql = "
        SELECT 
            at.code as type_code, at.name as type_name,
            av.id as value_id, av.name as value_name
        FROM user_attributes ua
        JOIN attribute_values av ON ua.attribute_value_id = av.id
        JOIN attribute_types at ON av.type_id = at.id
        WHERE ua.user_id = ?
        ORDER BY at.display_order, av.display_order
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $grouped = [];
    while ($row = $result->fetch_assoc()) {
        $type_code = $row['type_code'];
        if (!isset($grouped[$type_code])) {
            $grouped[$type_code] = [
                'type_name' => $row['type_name'],
                'values' => []
            ];
        }
        $grouped[$type_code]['values'][] = [
            'id' => (int) $row['value_id'],
            'name' => $row['value_name']
        ];
    }
    $stmt->close();

    if ($local_conn) {
        $conn->close();
    }

    return $grouped;
}

/**
 * 取得使用者的系統角色
 * @param int $user_id
 * @param mysqli $conn
 * @return string 'admin' / 'hospital_admin' / 'course_creator' / 'student'
 */
function get_user_system_role($user_id, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $sql = "
        SELECT av.code
        FROM user_attributes ua
        JOIN attribute_values av ON ua.attribute_value_id = av.id
        JOIN attribute_types at ON av.type_id = at.id
        WHERE ua.user_id = ? AND at.code = 'system_role'
        ORDER BY av.display_order
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $role = 'student'; // 預設
    if ($row = $result->fetch_assoc()) {
        $role = $row['code'];
    }
    $stmt->close();

    if ($local_conn) {
        $conn->close();
    }

    return $role;
}

/**
 * 取得使用者的院區資訊
 * @param int $user_id
 * @param mysqli $conn
 * @return array|null ['id' => int, 'name' => string, 'code' => string]
 */
function get_user_hospital($user_id, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $sql = "
        SELECT av.id, av.code, av.name
        FROM user_attributes ua
        JOIN attribute_values av ON ua.attribute_value_id = av.id
        JOIN attribute_types at ON av.type_id = at.id
        WHERE ua.user_id = ? AND at.code = 'hospital'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $hospital = null;
    if ($row = $result->fetch_assoc()) {
        $hospital = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'name' => $row['name']
        ];
    }
    $stmt->close();

    if ($local_conn) {
        $conn->close();
    }

    return $hospital;
}

/**
 * 從屬性設定 Session（登入時使用）
 * @param int $user_id
 * @param mysqli $conn
 */
function set_session_from_attributes($user_id, $conn = null)
{
    $role = get_user_system_role($user_id, $conn);
    $hospital = get_user_hospital($user_id, $conn);

    $_SESSION['is_admin'] = ($role === 'admin');
    $_SESSION['is_hospital_admin'] = ($role === 'hospital_admin');
    $_SESSION['is_coursecreator'] = ($role === 'course_creator');

    // 院區資訊
    $_SESSION['hospital_id'] = $hospital['id'] ?? null;
    $_SESSION['hospital_name'] = $hospital['name'] ?? '';
    $_SESSION['institution'] = $hospital['name'] ?? ''; // 相容舊欄位

    // 院區管理員也算是管理員（顯示管理介面）
    if ($_SESSION['is_hospital_admin']) {
        $_SESSION['is_admin'] = true;
    }

    // 設定 Cookie（供 Moodle 前端判斷）
    setcookie('portal_is_admin', $_SESSION['is_admin'] ? '1' : '0', 0, '/');
    setcookie('portal_is_hospital_admin', $_SESSION['is_hospital_admin'] ? '1' : '0', 0, '/');
    setcookie('portal_is_coursecreator', $_SESSION['is_coursecreator'] ? '1' : '0', 0, '/');
}

/**
 * 設定使用者的屬性（覆蓋模式）
 * @param int $user_id
 * @param array $attribute_value_ids
 * @param int|null $assigned_by
 * @param mysqli $conn
 * @return bool
 */
function set_user_attributes($user_id, $attribute_value_ids, $assigned_by = null, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $conn->begin_transaction();

    try {
        // 刪除現有
        $stmt = $conn->prepare("DELETE FROM user_attributes WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 插入新的
        if (!empty($attribute_value_ids)) {
            $stmt = $conn->prepare("
                INSERT INTO user_attributes (user_id, attribute_value_id, assigned_by) 
                VALUES (?, ?, ?)
            ");

            foreach ($attribute_value_ids as $attr_id) {
                $attr_id = (int) $attr_id;
                if ($attr_id > 0) {
                    $stmt->bind_param("iii", $user_id, $attr_id, $assigned_by);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        $conn->commit();

        if ($local_conn) {
            $conn->close();
        }

        return true;

    } catch (Exception $e) {
        $conn->rollback();
        if ($local_conn) {
            $conn->close();
        }
        return false;
    }
}

/**
 * 取得所有醫院列表
 * @param bool $active_only
 * @param mysqli $conn
 * @return array
 */
function get_hospitals($active_only = true, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $where = $active_only ? "WHERE is_active = 1" : "";
    $result = $conn->query("
        SELECT id, code, name, moodle_category_id 
        FROM hospitals 
        $where
        ORDER BY display_order, id
    ");

    $hospitals = [];
    while ($row = $result->fetch_assoc()) {
        $hospitals[] = $row;
    }

    if ($local_conn) {
        $conn->close();
    }

    return $hospitals;
}

/**
 * 取得屬性值列表
 * @param int|null $type_id 屬性類型 ID
 * @param int|null $hospital_id 醫院 ID（null 表示全域）
 * @param bool $include_global 是否包含全域屬性
 * @param mysqli $conn
 * @return array
 */
function get_attribute_values($type_id = null, $hospital_id = null, $include_global = true, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $conditions = ["av.is_active = 1"];
    $params = [];
    $types = '';

    if ($type_id) {
        $conditions[] = "av.type_id = ?";
        $params[] = $type_id;
        $types .= 'i';
    }

    if ($hospital_id !== null) {
        if ($include_global) {
            $conditions[] = "(av.hospital_id = ? OR av.hospital_id IS NULL)";
        } else {
            $conditions[] = "av.hospital_id = ?";
        }
        $params[] = $hospital_id;
        $types .= 'i';
    }

    $where = implode(" AND ", $conditions);
    $sql = "
        SELECT av.id, av.type_id, av.code, av.name, av.hospital_id, av.parent_id
        FROM attribute_values av
        WHERE $where
        ORDER BY av.display_order, av.id
    ";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[] = $row;
    }

    if ($local_conn) {
        $conn->close();
    }

    return $values;
}
?>