<?php
/**
 * Campus Model
 * 
 * Database operations for campuses table
 */

/**
 * Get all campuses
 * 
 * @return array Array of campus records
 */
function campus_get_all()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM campuses ORDER BY id");
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get campus by ID
 * 
 * @param int $campus_id Campus ID
 * @return array|null Campus record or null if not found
 */
function campus_get_by_id($campus_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM campuses WHERE id = ?");
    $stmt->bind_param("i", $campus_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get campus name by ID
 * 
 * @param int $campus_id Campus ID
 * @return string|null Campus name or null if not found
 */
function campus_get_name($campus_id)
{
    $campus = campus_get_by_id($campus_id);
    return $campus ? $campus['name'] : null;
}
