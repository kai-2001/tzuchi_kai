<?php
/**
 * Announcement Model
 */

function announcement_get_hero($limit = 5)
{
    global $conn;
    $query = "SELECT s.*, c.name as campus_name FROM announcements s 
               LEFT JOIN campuses c ON s.campus_id = c.id 
               WHERE s.is_active = 1 
               AND s.is_hero = 1
               AND (s.hero_start_date IS NULL OR s.hero_start_date <= CURDATE())
               AND (s.hero_end_date IS NULL OR s.hero_end_date > CURDATE())
               ORDER BY s.sort_order ASC, s.created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function announcement_get_upcoming()
{
    global $conn;
    $query = "SELECT u.*, c.name as campus_name 
              FROM announcements u
              LEFT JOIN campuses c ON u.campus_id = c.id
              WHERE u.event_date >= CURRENT_DATE AND u.is_active = 1
              ORDER BY u.event_date ASC";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}
