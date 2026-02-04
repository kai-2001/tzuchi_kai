<?php
/**
 * Compression Helper Functions
 * 
 * Centralized logic for determining video compression status
 */

/**
 * Determine video status based on compression settings
 * 
 * @param int $campus_id Campus ID
 * @param mysqli $conn Database connection
 * @return array ['status' => string, 'trigger' => bool, 'message' => string]
 */
function determine_video_status($campus_id, $conn)
{
    // Check campus-specific auto_compression setting
    $auto_compression = '0';

    $sql = "SELECT campus_id, setting_value FROM system_settings 
            WHERE setting_key = 'auto_compression' AND campus_id IN (?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $campus_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $settings = [];
    while ($row = $res->fetch_assoc()) {
        $settings[$row['campus_id']] = $row['setting_value'];
    }

    // Campus-specific setting takes priority over global
    if (isset($settings[$campus_id])) {
        $auto_compression = $settings[$campus_id];
    } elseif (isset($settings[0])) {
        $auto_compression = $settings[0];
    }

    // Determine status based on compression mode
    // Priority: COMPRESSION_MODE (global env) > auto_compression (database setting)
    if (COMPRESSION_MODE === 'disabled') {
        // Skip compression entirely - go directly to ready
        return [
            'status' => 'ready',
            'trigger' => false,
            'message' => '演講上傳成功！系統設為「無壓縮模式」，影片已可立即觀看。'
        ];
    } elseif ($auto_compression === '1') {
        // Compression enabled + auto mode
        return [
            'status' => 'pending',
            'trigger' => true,
            'message' => '演講上傳成功！系統設為「自動壓縮」，已通知轉檔主機開始作業。'
        ];
    } else {
        // Compression enabled + manual mode
        return [
            'status' => 'waiting',
            'trigger' => false,
            'message' => '演講上傳成功！已加入「待處理清單」。請前往佇列管理頁面手動啟動壓縮。'
        ];
    }
}
