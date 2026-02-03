<?php
/**
 * 課程報名規則引擎
 * includes/rule_engine.php
 */

/**
 * 檢查使用者是否符合課程報名資格
 * @param int $user_id
 * @param int $moodle_course_id
 * @param mysqli $conn
 * @return array ['eligible' => bool, 'matched_rule' => string|null]
 */
function check_course_eligibility($user_id, $moodle_course_id, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    // 取得使用者的所有屬性值 ID
    require_once __DIR__ . '/attribute_helper.php';
    $user_attrs = get_user_attribute_ids($user_id, $conn);

    if (empty($user_attrs)) {
        if ($local_conn)
            $conn->close();
        return ['eligible' => false, 'matched_rule' => null, 'reason' => '使用者沒有任何屬性'];
    }

    // 取得課程的所有規則
    $stmt = $conn->prepare("
        SELECT id, rule_name, logic_type 
        FROM course_rules 
        WHERE moodle_course_id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $moodle_course_id);
    $stmt->execute();
    $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 如果沒有規則，預設不開放
    if (empty($rules)) {
        if ($local_conn)
            $conn->close();
        return ['eligible' => false, 'matched_rule' => null, 'reason' => '課程尚未設定報名規則'];
    }

    // 檢查每個規則（規則之間是 OR 關係）
    foreach ($rules as $rule) {
        // 取得規則的條件
        $stmt = $conn->prepare("
            SELECT attribute_value_id 
            FROM rule_conditions 
            WHERE rule_id = ?
        ");
        $stmt->bind_param("i", $rule['id']);
        $stmt->execute();
        $conditions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($conditions)) {
            continue; // 沒有條件的規則跳過
        }

        $required_attrs = array_column($conditions, 'attribute_value_id');
        $matched = array_intersect($user_attrs, $required_attrs);

        if ($rule['logic_type'] === 'AND') {
            // 交集：必須符合所有條件
            if (count($matched) === count($required_attrs)) {
                if ($local_conn)
                    $conn->close();
                return [
                    'eligible' => true,
                    'matched_rule' => $rule['rule_name'] ?? '規則 #' . $rule['id']
                ];
            }
        } else {
            // 聯集：符合任一條件即可
            if (count($matched) > 0) {
                if ($local_conn)
                    $conn->close();
                return [
                    'eligible' => true,
                    'matched_rule' => $rule['rule_name'] ?? '規則 #' . $rule['id']
                ];
            }
        }
    }

    if ($local_conn)
        $conn->close();
    return ['eligible' => false, 'matched_rule' => null, 'reason' => '不符合任何報名規則'];
}

/**
 * 預估符合課程規則的人數
 * @param array $rules 規則陣列 [['logic_type' => 'AND', 'conditions' => [1, 2, 3]], ...]
 * @param mysqli $conn
 * @return array ['total' => int, 'by_hospital' => [...]]
 */
function estimate_course_audience($rules, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    if (empty($rules)) {
        if ($local_conn)
            $conn->close();
        return ['total' => 0, 'by_hospital' => []];
    }

    // 取得所有使用者的屬性
    $result = $conn->query("
        SELECT 
            u.id as user_id, 
            u.hospital_id,
            h.name as hospital_name,
            GROUP_CONCAT(ua.attribute_value_id) as attrs
        FROM users u
        LEFT JOIN hospitals h ON u.hospital_id = h.id
        LEFT JOIN user_attributes ua ON u.id = ua.user_id
        WHERE u.is_active = 1 OR u.is_active IS NULL
        GROUP BY u.id
    ");

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['attrs'] = $row['attrs'] ? array_map('intval', explode(',', $row['attrs'])) : [];
        $users[] = $row;
    }

    $eligible_users = [];
    $by_hospital = [];

    foreach ($users as $user) {
        $is_eligible = false;

        // 檢查每個規則（OR 關係）
        foreach ($rules as $rule) {
            $logic_type = $rule['logic_type'] ?? 'AND';
            $conditions = $rule['conditions'] ?? [];

            if (empty($conditions))
                continue;

            $matched = array_intersect($user['attrs'], $conditions);

            if ($logic_type === 'AND') {
                if (count($matched) === count($conditions)) {
                    $is_eligible = true;
                    break;
                }
            } else {
                if (count($matched) > 0) {
                    $is_eligible = true;
                    break;
                }
            }
        }

        if ($is_eligible) {
            $eligible_users[] = $user['user_id'];
            $hospital_name = $user['hospital_name'] ?? '未分配';
            if (!isset($by_hospital[$hospital_name])) {
                $by_hospital[$hospital_name] = 0;
            }
            $by_hospital[$hospital_name]++;
        }
    }

    if ($local_conn)
        $conn->close();

    return [
        'total' => count($eligible_users),
        'by_hospital' => $by_hospital
    ];
}

/**
 * 取得課程的報名規則
 * @param int $moodle_course_id
 * @param mysqli $conn
 * @return array
 */
function get_course_rules($moodle_course_id, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $stmt = $conn->prepare("
        SELECT id, rule_name, logic_type, is_active 
        FROM course_rules 
        WHERE moodle_course_id = ?
        ORDER BY id
    ");
    $stmt->bind_param("i", $moodle_course_id);
    $stmt->execute();
    $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 取得每個規則的條件
    foreach ($rules as &$rule) {
        $stmt = $conn->prepare("
            SELECT 
                rc.attribute_value_id,
                av.name as value_name,
                at.code as type_code,
                at.name as type_name
            FROM rule_conditions rc
            JOIN attribute_values av ON rc.attribute_value_id = av.id
            JOIN attribute_types at ON av.type_id = at.id
            WHERE rc.rule_id = ?
        ");
        $stmt->bind_param("i", $rule['id']);
        $stmt->execute();
        $rule['conditions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if ($local_conn)
        $conn->close();
    return $rules;
}

/**
 * 儲存課程的報名規則（覆蓋模式）
 * @param int $moodle_course_id
 * @param array $rules 規則陣列
 * @param int|null $created_by
 * @param mysqli $conn
 * @return bool
 */
function save_course_rules($moodle_course_id, $rules, $created_by = null, $conn = null)
{
    global $db_host, $db_user, $db_pass, $db_name;
    $local_conn = false;

    if (!$conn) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $local_conn = true;
    }

    $conn->begin_transaction();

    try {
        // 刪除現有規則（會連帶刪除 conditions）
        $stmt = $conn->prepare("DELETE FROM course_rules WHERE moodle_course_id = ?");
        $stmt->bind_param("i", $moodle_course_id);
        $stmt->execute();
        $stmt->close();

        // 新增規則
        foreach ($rules as $rule) {
            $rule_name = $rule['rule_name'] ?? null;
            $logic_type = $rule['logic_type'] ?? 'AND';
            $conditions = $rule['conditions'] ?? [];

            if (empty($conditions))
                continue;

            $stmt = $conn->prepare("
                INSERT INTO course_rules (moodle_course_id, rule_name, logic_type, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("issi", $moodle_course_id, $rule_name, $logic_type, $created_by);
            $stmt->execute();
            $rule_id = $conn->insert_id;
            $stmt->close();

            // 新增條件
            $stmt = $conn->prepare("
                INSERT INTO rule_conditions (rule_id, attribute_value_id) VALUES (?, ?)
            ");
            foreach ($conditions as $attr_id) {
                $attr_id = (int) $attr_id;
                if ($attr_id > 0) {
                    $stmt->bind_param("ii", $rule_id, $attr_id);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        $conn->commit();
        if ($local_conn)
            $conn->close();
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        if ($local_conn)
            $conn->close();
        return false;
    }
}
?>