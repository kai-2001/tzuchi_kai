<?php
/**
 * Video Model
 * Centralizes database operations for the videos table.
 */

function video_get_ready($campus_id = 0, $search = '', $limit = 10, $offset = 0)
{
    global $conn;

    $query = "SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name 
              FROM videos v
              LEFT JOIN speakers s ON v.speaker_id = s.id
              LEFT JOIN campuses c ON v.campus_id = c.id
              WHERE v.status = 'ready'";

    $params = [];
    $types = "";

    if ($campus_id > 0) {
        $query .= " AND v.campus_id = ?";
        $params[] = $campus_id;
        $types .= "i";
    }

    if (!empty($search)) {
        $query .= " AND (v.title LIKE ? OR s.name LIKE ? OR s.affiliation LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
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

    $query = "SELECT COUNT(*) FROM videos v
              LEFT JOIN speakers s ON v.speaker_id = s.id
              WHERE v.status = 'ready'";

    $params = [];
    $types = "";

    if ($campus_id > 0) {
        $query .= " AND v.campus_id = ?";
        $params[] = $campus_id;
        $types .= "i";
    }

    if (!empty($search)) {
        $query .= " AND (v.title LIKE ? OR s.name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
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
    $stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position, c.name as campus_name 
                          FROM videos v
                          LEFT JOIN speakers s ON v.speaker_id = s.id
                          LEFT JOIN campuses c ON v.campus_id = c.id
                          WHERE v.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
