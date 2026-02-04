<?php
/**
 * Speaker Model
 * 
 * Database operations for speakers table
 */

/**
 * Find speaker by name and affiliation
 * 
 * @param string $name Speaker name
 * @param string $affiliation Speaker affiliation
 * @return int|null Speaker ID or null if not found
 */
function speaker_find_by_name_affiliation($name, $affiliation)
{
    global $conn;

    $stmt = $conn->prepare("SELECT id FROM speakers WHERE name = ? AND affiliation = ?");
    $stmt->bind_param("ss", $name, $affiliation);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return $result ? $result['id'] : null;
}

/**
 * Create new speaker
 * 
 * @param string $name Speaker name
 * @param string $affiliation Speaker affiliation
 * @param string $position Speaker position
 * @return int Speaker ID
 */
function speaker_create($name, $affiliation, $position)
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO speakers (name, affiliation, position) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $affiliation, $position);
    $stmt->execute();

    return $conn->insert_id;
}

/**
 * Update speaker position
 * 
 * @param int $speaker_id Speaker ID
 * @param string $position New position
 * @return bool Success
 */
function speaker_update_position($speaker_id, $position)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE speakers SET position = ? WHERE id = ?");
    $stmt->bind_param("si", $position, $speaker_id);

    return $stmt->execute();
}

/**
 * Find or create speaker (convenience function)
 * 
 * @param string $name Speaker name
 * @param string $affiliation Speaker affiliation
 * @param string $position Speaker position
 * @return int Speaker ID
 */
function speaker_find_or_create($name, $affiliation, $position)
{
    $speaker_id = speaker_find_by_name_affiliation($name, $affiliation);

    if (!$speaker_id) {
        $speaker_id = speaker_create($name, $affiliation, $position);
    } else {
        // Update position if speaker exists
        speaker_update_position($speaker_id, $position);
    }

    return $speaker_id;
}
