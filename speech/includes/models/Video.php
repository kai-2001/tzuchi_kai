<?php
/**
 * Video Model
 * Centralizes database operations for the videos table.
 */

function video_get_ready($campus_id = 0, $search = '', $limit = 10, $offset = 0)
{
    global $conn;

    $query = "SELECT v.*, 
                     c.name as campus_name,
                     GROUP_CONCAT(DISTINCT s.name ORDER BY vs.display_order SEPARATOR ', ') as speaker_names,
                     GROUP_CONCAT(DISTINCT s.affiliation ORDER BY vs.display_order SEPARATOR ', ') as speaker_affiliations,
                     GROUP_CONCAT(DISTINCT s.position ORDER BY vs.display_order SEPARATOR ', ') as speaker_positions,
                     GROUP_CONCAT(DISTINCT CONCAT(s.id, ':', s.name, ':', IFNULL(s.affiliation, '')) ORDER BY vs.display_order SEPARATOR '|') as speakers_detail
              FROM videos v
              LEFT JOIN campuses c ON v.campus_id = c.id
              LEFT JOIN video_speakers vs ON v.id = vs.video_id
              LEFT JOIN speakers s ON vs.speaker_id = s.id
              WHERE v.status = 'ready'";

    $params = [];
    $types = "";

    if ($campus_id > 0) {
        $query .= " AND v.campus_id = ?";
        $params[] = $campus_id;
        $types .= "i";
    }

    $query .= " GROUP BY v.id";

    // 講者搜尋使用 HAVING（包含姓名、單位、職位）
    if (!empty($search)) {
        $query .= " HAVING v.title LIKE ? OR speaker_names LIKE ? OR speaker_affiliations LIKE ? OR speaker_positions LIKE ?";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }

    $query .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
    $params[] = (int) $limit;
    $params[] = (int) $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function video_count_ready($campus_id = 0, $search = '')
{
    global $conn;

    $query = "SELECT COUNT(DISTINCT v.id) FROM videos v
              LEFT JOIN video_speakers vs ON v.id = vs.video_id
              LEFT JOIN speakers s ON vs.speaker_id = s.id
              WHERE v.status = 'ready'";

    $params = [];
    $types = "";

    if ($campus_id > 0) {
        $query .= " AND v.campus_id = ?";
        $params[] = $campus_id;
        $types .= "i";
    }

    if (!empty($search)) {
        $query .= " AND (v.title LIKE ? OR s.name LIKE ? OR s.affiliation LIKE ? OR s.position LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}

function video_get_by_id($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT v.*, 
                          c.name as campus_name,
                          GROUP_CONCAT(DISTINCT s.name ORDER BY vs.display_order SEPARATOR ', ') as speaker_names,
                          GROUP_CONCAT(DISTINCT CONCAT(s.id, ':', s.name, ':', IFNULL(s.affiliation, ''), ':', IFNULL(s.position, '')) ORDER BY vs.display_order SEPARATOR '|') as speakers_detail
                          FROM videos v
                          LEFT JOIN campuses c ON v.campus_id = c.id
                          LEFT JOIN video_speakers vs ON v.id = vs.video_id
                          LEFT JOIN speakers s ON vs.speaker_id = s.id
                          WHERE v.id = ?
                          GROUP BY v.id");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
