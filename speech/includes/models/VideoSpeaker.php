<?php
/**
 * VideoSpeaker Model - 管理演講與講者的多對多關聯
 */

/**
 * 新增演講-講者關聯
 * 
 * @param int $video_id 演講 ID
 * @param int $speaker_id 講者 ID
 * @param string $role 角色 (speaker|moderator|panelist|guest)
 * @param int $display_order 顯示順序
 * @return bool 是否成功
 */
function video_speaker_add($video_id, $speaker_id, $role = 'speaker', $display_order = 0)
{
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO video_speakers (video_id, speaker_id, role, display_order)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE role = ?, display_order = ?
    ");
    $stmt->bind_param("iisisi", $video_id, $speaker_id, $role, $display_order, $role, $display_order);

    return $stmt->execute();
}

/**
 * 移除演講的所有講者關聯
 * 
 * @param int $video_id 演講 ID
 * @return bool 是否成功
 */
function video_speaker_remove_all($video_id)
{
    global $conn;

    $stmt = $conn->prepare("DELETE FROM video_speakers WHERE video_id = ?");
    $stmt->bind_param("i", $video_id);

    return $stmt->execute();
}

/**
 * 取得演講的所有講者
 * 
 * @param int $video_id 演講 ID
 * @return array 講者陣列
 */
function video_speaker_get_by_video($video_id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            vs.id,
            vs.speaker_id,
            s.name,
            s.affiliation,
            s.position,
            vs.role,
            vs.display_order
        FROM video_speakers vs
        JOIN speakers s ON vs.speaker_id = s.id
        WHERE vs.video_id = ?
        ORDER BY vs.display_order ASC, vs.id ASC
    ");
    $stmt->bind_param("i", $video_id);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * 取得講者的所有演講
 * 
 * @param int $speaker_id 講者 ID
 * @return array 演講陣列
 */
function video_speaker_get_by_speaker($speaker_id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            vs.id,
            vs.video_id,
            v.title,
            v.event_date,
            vs.role,
            vs.display_order
        FROM video_speakers vs
        JOIN videos v ON vs.video_id = v.id
        WHERE vs.speaker_id = ?
        ORDER BY v.event_date DESC
    ");
    $stmt->bind_param("i", $speaker_id);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * 批次設定演講的講者（先清空再重建）
 * 
 * @param int $video_id 演講 ID
 * @param array $speakers 講者陣列 [['speaker_id' => 1, 'role' => 'speaker', 'display_order' => 0], ...]
 * @return bool 是否成功
 */
function video_speaker_set_speakers($video_id, $speakers)
{
    global $conn;

    // 開始交易
    $conn->begin_transaction();

    try {
        // 1. 清空現有關聯
        video_speaker_remove_all($video_id);

        // 2. 插入新關聯
        foreach ($speakers as $index => $speaker) {
            $speaker_id = $speaker['speaker_id'] ?? $speaker['id'] ?? null;
            $role = $speaker['role'] ?? 'speaker';
            $display_order = $speaker['display_order'] ?? $index;

            if (!$speaker_id) {
                throw new Exception("Missing speaker_id in speakers array");
            }

            video_speaker_add($video_id, $speaker_id, $role, $display_order);
        }

        // 提交交易
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // 回滾交易
        $conn->rollback();
        error_log("video_speaker_set_speakers error: " . $e->getMessage());
        return false;
    }
}

/**
 * 檢查演講是否有指定講者
 * 
 * @param int $video_id 演講 ID
 * @param int $speaker_id 講者 ID
 * @return bool 是否存在
 */
function video_speaker_exists($video_id, $speaker_id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM video_speakers 
        WHERE video_id = ? AND speaker_id = ?
    ");
    $stmt->bind_param("ii", $video_id, $speaker_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'] > 0;
}

/**
 * 取得演講的講者數量
 * 
 * @param int $video_id 演講 ID
 * @return int 講者數量
 */
function video_speaker_count($video_id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM video_speakers 
        WHERE video_id = ?
    ");
    $stmt->bind_param("i", $video_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return (int) $row['count'];
}
