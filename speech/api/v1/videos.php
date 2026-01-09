<?php
/**
 * Speech Portal API v1 - Videos Endpoint
 * 
 * Functions:
 * 1. Get single video by ID: ?id=1
 * 2. Get multiple videos by IDs: ?ids=1,2,3
 * 3. Get latest videos: ?limit=10 (Default 20)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Allow external access
header('Access-Control-Allow-Methods: GET');

// Disable error display to prevent HTML pollution in JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start buffering to catch any accidental output (whitespace, BOMs)
ob_start();

try {
    // Include DB connection
    // Adjust path based on location: speech/api/v1/videos.php -> goes up 3 levels to speech root?
    // Folder structure: c:\Apache24\htdocs\speech\api\v1\videos.php
    // Config: c:\Apache24\htdocs\speech\includes\config.php (2 levels up + includes)
    $config_path = __DIR__ . '/../../includes/config.php';

    if (!file_exists($config_path)) {
        throw new Exception("Config file not found.");
    }

    require_once $config_path;

    // Clear buffer before outputting anything
    ob_clean();

    // Ensure DB connection exists
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed.");
    }

    // Set UTF-8
    $conn->set_charset("utf8mb4");

    // Initialize Response
    $response = [
        'success' => true,
        'data' => [],
        'meta' => []
    ];

    // ==========================================
    // MODE 1: Single Video (?id=)
    // ==========================================
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $conn->prepare("
            SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name 
            FROM videos v
            LEFT JOIN speakers s ON v.speaker_id = s.id
            LEFT JOIN campuses c ON v.campus_id = c.id
            WHERE v.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $response['data'] = process_video($row);
        } else {
            $response['success'] = false;
            $response['error'] = 'Video not found';
            http_response_code(404);
        }
    }
    // ==========================================
    // MODE 2: Multiple Videos (?ids=)
    // ==========================================
    elseif (isset($_GET['ids'])) {
        $ids_raw = explode(',', $_GET['ids']);
        $ids = array_map('intval', $ids_raw);
        $ids = array_filter($ids); // Remove 0s

        if (empty($ids)) {
            $response['data'] = [];
        } else {
            // Safe impl for WHERE IN (?)
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $sql = "
                SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name 
                FROM videos v
                LEFT JOIN speakers s ON v.speaker_id = s.id
                LEFT JOIN campuses c ON v.campus_id = c.id
                WHERE v.id IN ($placeholders)
                ORDER BY FIELD(v.id, " . implode(',', $ids) . ")
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();

            $videos = [];
            while ($row = $result->fetch_assoc()) {
                $videos[] = process_video($row);
            }
            $response['data'] = $videos;
            $response['meta']['count'] = count($videos);
        }
    }
    // ==========================================
    // MODE 3: Latest Videos (?limit=)
    // ==========================================
    else {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

        // Hard limit to prevent abuse
        if ($limit > 100)
            $limit = 100;
        if ($limit < 1)
            $limit = 20;

        // Get Data
        $stmt = $conn->prepare("
            SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name 
            FROM videos v
            LEFT JOIN speakers s ON v.speaker_id = s.id
            LEFT JOIN campuses c ON v.campus_id = c.id
            ORDER BY v.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = process_video($row);
        }
        $response['data'] = $videos;

        // Add meta info
        $count_res = $conn->query("SELECT COUNT(*) FROM videos");
        $total = $count_res->fetch_row()[0];

        $response['meta'] = [
            'count' => count($videos),
            'total' => (int) $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Format video data for API output
 */
function process_video($row)
{
    // Determine full URL for assets if not absolute
    // Note: Request scheme/host might fail in CLI, so use relative or try to guess
    $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    // Assuming Speech portal is at /speech/
    // If input path is "uploads/img.jpg", output "http://.../speech/uploads/img.jpg"

    // Naive path fix
    $thumb = $row['thumbnail_path'];
    if ($thumb && !filter_var($thumb, FILTER_VALIDATE_URL)) {
        $thumb = $base_url . '/speech/' . ltrim($thumb, '/');
    }

    $video_url = $row['file_path']; // Or wherever it's stored. 'file_path' usually
    if ($video_url && !filter_var($video_url, FILTER_VALIDATE_URL)) {
        $video_url = $base_url . '/speech/' . ltrim($video_url, '/');
    }

    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'description' => $row['description'], // Careful if HTML entites
        'speaker_name' => $row['speaker_name'],
        'affiliation' => $row['affiliation'],
        'campus' => $row['campus_name'],
        'event_date' => $row['event_date'],
        'views' => (int) $row['views'],
        'thumbnail_url' => $thumb,
        'video_url' => $video_url,
        'created_at' => $row['created_at']
    ];
}
