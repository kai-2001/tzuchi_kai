<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Fetch all active announcements, ordered by Campus then Date
// We only want future or recent events? usually upcoming means >= today.
// But maybe user wants all? "Upcoming" implies future. 
// Let's stick to future >= TODAY for now, or maybe all active? 
// The prompt said "跟以前近期預告的顯示方式一樣" (Like the old Upcoming Lectures).
// Let's filter date >= CURDATE() to be safe for "Upcoming".

$query = "SELECT a.*, c.name as campus_name, c.id as campus_id_val 
          FROM announcements a 
          LEFT JOIN campuses c ON a.campus_id = c.id 
          WHERE a.is_active = 1 
          ORDER BY a.event_date DESC, a.campus_id ASC";

$result = $conn->query($query);
$announcements_flat = $result->fetch_all(MYSQLI_ASSOC);

// Grouping Logic: Campus -> Month -> List
$grouped_announcements = [];

foreach ($announcements_flat as $item) {
    $campus = $item['campus_name'] ?? '全院活動';
    // Format Month: e.g., '2023年 12月'
    $ts = strtotime($item['event_date']);
    $month_key = date('Y年 n月', $ts);

    if (!isset($grouped_announcements[$campus])) {
        $grouped_announcements[$campus] = [];
    }
    if (!isset($grouped_announcements[$campus][$month_key])) {
        $grouped_announcements[$campus][$month_key] = [];
    }

    $grouped_announcements[$campus][$month_key][] = $item;
}

$page_title = '公告';
$page_css_files = []; // We can add specific CSS if needed, or inline

include 'templates/announcements.php';
?>