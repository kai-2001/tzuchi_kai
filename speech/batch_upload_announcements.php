<?php
/**
 * Batch Upload Announcements Controller
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/SimpleXLSX.php'; // Include our new helper

if (!is_manager() && !is_campus_admin()) {
    header("Location: login.php");
    exit;
}

// DEBUG: Enable Error Reporting
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = '';
$success_msg = '';
$results = [];

// Determine text vs csv
function detectDelimiter($csvFile)
{
    $delimiters = ["," => 0, "\t" => 0, ";" => 0];
    $handle = fopen($csvFile, "r");
    if ($handle) {
        $firstLine = fgets($handle);
        fclose($handle);
        foreach ($delimiters as $delimiter => &$count) {
            $count = count(explode($delimiter, $firstLine));
        }
        return array_search(max($delimiters), $delimiters);
    }
    return ",";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['batch_file']) && $_FILES['batch_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['batch_file']['tmp_name'];
        $fileName = $_FILES['batch_file']['name'];
        $fileSize = $_FILES['batch_file']['size'];
        $fileType = $_FILES['batch_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('csv', 'xlsx');

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $rows = [];

            // Parse File
            if ($fileExtension === 'xlsx') {
                $xlsx = SimpleXLSX::parse($fileTmpPath);
                if ($xlsx && $xlsx->success) {
                    $rows = $xlsx->rows();
                } else {
                    $error = '無法解析 Excel 檔案: ' . ($xlsx ? $xlsx->error : 'Unknown Error');
                }
            } else {
                // CSV
                $delimiter = detectDelimiter($fileTmpPath);
                if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                    // Remove BOM if present
                    $bom = "\xef\xbb\xbf";
                    $first = true;
                    while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                        if ($first) {
                            $first = false;
                            // Check BOM on first cell of first row
                            if (substr($data[0], 0, 3) === $bom) {
                                $data[0] = substr($data[0], 3);
                            }
                        }
                        $rows[] = $data;
                    }
                    fclose($handle);
                } else {
                    $error = '無法開啟 CSV 檔案';
                }
            }

            if (empty($error) && !empty($rows)) {
                // Process Rows
                // Assume Row 1 is Header, skip it
                $header = array_shift($rows);

                $success_count = 0;
                $fail_count = 0;
                $fail_reasons = [];

                global $conn;
                $stmt = $conn->prepare("INSERT INTO announcements (title, speaker_name, affiliation, event_date, location, description, campus_id, is_hero, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");

                foreach ($rows as $index => $row) {
                    // Expected Format:
                    // 0: Title (Required)
                    // 1: Speaker
                    // 2: Affiliation
                    // 3: Event Date (YYYY-MM-DD)
                    // 4: Location
                    // 5: Description
                    // 6: Campus ID (default 0)

                    // Basic cleaning
                    $title = isset($row[0]) ? trim($row[0]) : '';
                    if (empty($title)) {
                        $fail_count++;
                        $fail_reasons[] = "第 " . ($index + 2) . " 列：標題為空";
                        continue;
                    }

                    $speaker = isset($row[1]) ? trim($row[1]) : '';
                    $affiliation = isset($row[2]) ? trim($row[2]) : '';
                    $event_date = isset($row[3]) ? trim($row[3]) : null;
                    if (empty($event_date)) {
                        $event_date = null;
                    } elseif (is_numeric($event_date)) {
                        // FIX: Convert Excel Serial Date to YYYY-MM-DD
                        $unix_date = ($event_date - 25569) * 86400;
                        $event_date = gmdate("Y-m-d", $unix_date);
                    } else {
                        // FIX: Handle string formats like 2027/01/01
                        // MySQL might reject slashes, so we normalize to Y-m-d
                        $timestamp = strtotime(str_replace('/', '-', $event_date));
                        if ($timestamp) {
                            $event_date = date("Y-m-d", $timestamp);
                        } else {
                            // If invalid format, let MySQL try or set to NULL? 
                            // Better to set default or leave as is if user really messed up.
                            // But for safety, let's keep original string if strtotime fails, MySQL usually truncates it.
                        }
                    }

                    $location = isset($row[4]) ? trim($row[4]) : '';
                    $description = isset($row[5]) ? trim($row[5]) : '';

                    if (is_campus_admin()) {
                        $campus_id = $_SESSION['campus_id'];
                    } else {
                        $campus_id = isset($row[6]) ? (int) $row[6] : 0;
                    }

                    $stmt->bind_param("ssssssi", $title, $speaker, $affiliation, $event_date, $location, $description, $campus_id);

                    try {
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $fail_count++;
                            $fail_reasons[] = "第 " . ($index + 2) . " 列：資料庫錯誤 (" . $stmt->error . ")";
                        }
                    } catch (Exception $e) {
                        $fail_count++;
                        $fail_reasons[] = "第 " . ($index + 2) . " 列：系統錯誤 (" . $e->getMessage() . ")";
                    }
                }
                $stmt->close();

                $success_msg = "上傳完成！成功新增 {$success_count} 筆公告。";
                if ($fail_count > 0) {
                    $error = "有 {$fail_count} 筆失敗。";
                    $results['fails'] = $fail_reasons;
                }
            } elseif (empty($rows) && empty($error)) {
                $error = "檔案內容為空";
            }

        } else {
            $error = '只允許上傳 .csv 或 .xlsx 檔案';
        }
    } else {
        $error = '上傳失敗或未選擇檔案';
    }
}

// Prepare view
$page_title = '批次上傳公告';
$page_css_files = ['forms.css', 'manage.css'];
include 'templates/batch_upload_announcements.php';
