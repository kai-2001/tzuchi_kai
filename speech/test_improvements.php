<?php
/**
 * Code Review 改善項目測試腳本
 * 自動驗證環境變數、資料庫連線、Helper 函式等
 */

echo "=== Code Review 改善項目測試 ===\n\n";

$tests_passed = 0;
$tests_failed = 0;

// ============================================
// Test 1: .env 檔案存在
// ============================================
echo "[Test 1] 檢查 .env 檔案是否存在...\n";
if (file_exists(__DIR__ . '/.env')) {
    echo "✓ .env 檔案存在\n\n";
    $tests_passed++;
} else {
    echo "✗ .env 檔案不存在！請從 .env.example 複製並設定。\n\n";
    $tests_failed++;
}

// ============================================
// Test 2: 載入 config.php 並測試環境變數
// ============================================
echo "[Test 2] 測試 config.php 環境變數載入...\n";
try {
    require_once __DIR__ . '/includes/config.php';

    // 檢查 env() 函式是否可用
    if (!function_exists('env')) {
        throw new Exception("env() 函式未定義");
    }

    // 檢查關鍵常數是否定義
    $required_constants = [
        'APP_ENV',
        'AUTH_MODE',
        'SOAP_LOCATION',
        'SOAP_URI',
        'SOAP_VERIFY_SSL',
        'FFMPEG_PATH',
        'REMOTE_WORKER_URL',
        'WORKER_SECRET_TOKEN'
    ];

    $missing = [];
    foreach ($required_constants as $const) {
        if (!defined($const)) {
            $missing[] = $const;
        }
    }

    if (empty($missing)) {
        echo "✓ 所有常數已正確定義\n";
        echo "  - APP_ENV: " . APP_ENV . "\n";
        echo "  - AUTH_MODE: " . AUTH_MODE . "\n";
        echo "  - SOAP_VERIFY_SSL: " . (SOAP_VERIFY_SSL ? 'true' : 'false') . "\n\n";
        $tests_passed++;
    } else {
        throw new Exception("缺少常數: " . implode(', ', $missing));
    }
} catch (Exception $e) {
    echo "✗ 環境變數載入失敗: {$e->getMessage()}\n\n";
    $tests_failed++;
}

// ============================================
// Test 3: 資料庫連線測試
// ============================================
echo "[Test 3] 測試資料庫連線...\n";
if (isset($conn) && $conn instanceof mysqli) {
    if ($conn->ping()) {
        echo "✓ 資料庫連線成功\n";
        echo "  - Host: {$conn->host_info}\n";
        echo "  - Character Set: {$conn->character_set_name()}\n\n";
        $tests_passed++;
    } else {
        echo "✗ 資料庫連線失敗\n\n";
        $tests_failed++;
    }
} else {
    echo "✗ 資料庫連線物件不存在\n\n";
    $tests_failed++;
}

// ============================================
// Test 4: Helper 函式測試
// ============================================
echo "[Test 4] 測試 Helper 函式...\n";
$helper_functions = [
    'sanitize_filename',
    'format_filesize',
    'deleteDirectory',
    'parse_evercam_config',
    'process_evercam_zip'
];

$missing_functions = [];
foreach ($helper_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (empty($missing_functions)) {
    echo "✓ 所有 Helper 函式已定義\n";

    // 測試 format_filesize
    $test_size = format_filesize(1048576); // 1MB
    echo "  - format_filesize(1048576) = {$test_size}\n";

    // 測試 sanitize_filename
    $test_name = sanitize_filename("test file (1).mp4");
    echo "  - sanitize_filename('test file (1).mp4') = {$test_name}\n\n";

    $tests_passed++;
} else {
    echo "✗ 缺少函式: " . implode(', ', $missing_functions) . "\n\n";
    $tests_failed++;
}

// ============================================
// Test 5: 資料庫索引檢查
// ============================================
echo "[Test 5] 檢查資料庫索引...\n";
try {
    $indexes_to_check = [
        'videos' => ['idx_videos_status', 'idx_videos_campus_status', 'idx_videos_created_at'],
        'speakers' => ['idx_speakers_name'],
        'announcements' => ['idx_announcements_active', 'idx_announcements_hero', 'idx_announcements_event_date']
    ];

    $found_indexes = 0;
    $total_expected = 0;

    foreach ($indexes_to_check as $table => $indexes) {
        $result = $conn->query("SHOW INDEX FROM {$table}");
        $existing = [];
        while ($row = $result->fetch_assoc()) {
            $existing[] = $row['Key_name'];
        }

        foreach ($indexes as $idx) {
            $total_expected++;
            if (in_array($idx, $existing)) {
                $found_indexes++;
            }
        }
    }

    echo "✓ 找到 {$found_indexes}/{$total_expected} 個索引\n";
    if ($found_indexes === $total_expected) {
        echo "  所有索引已正確建立！\n\n";
        $tests_passed++;
    } else {
        echo "  ⚠ 部分索引尚未建立，請執行 add_database_indexes.php\n\n";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "✗ 索引檢查失敗: {$e->getMessage()}\n\n";
    $tests_failed++;
}

// ============================================
// Test 6: FileSystem 權限測試
// ============================================
echo "[Test 6] 測試檔案系統權限...\n";
$dirs_to_check = [
    'uploads/videos' => UPLOAD_DIR_VIDEOS,
    'uploads/thumbnails' => UPLOAD_DIR_THUMBS
];

$permissions_ok = true;
foreach ($dirs_to_check as $name => $dir) {
    if (!is_dir($dir)) {
        echo "  ⚠ 目錄不存在: {$name}\n";
        $permissions_ok = false;
    } elseif (!is_writable($dir)) {
        echo "  ✗ 目錄無寫入權限: {$name}\n";
        $permissions_ok = false;
    } else {
        echo "  ✓ {$name} 可寫入\n";
    }
}

if ($permissions_ok) {
    echo "✓ 所有目錄權限正常\n\n";
    $tests_passed++;
} else {
    echo "✗ 部分目錄權限有問題\n\n";
    $tests_failed++;
}

// ============================================
// Test 7: .gitignore 檢查
// ============================================
echo "[Test 7] 檢查 .gitignore 配置...\n";
if (file_exists(__DIR__ . '/.gitignore')) {
    $gitignore = file_get_contents(__DIR__ . '/.gitignore');
    $required_patterns = ['.env', 'uploads', 'logs'];
    $missing_patterns = [];

    foreach ($required_patterns as $pattern) {
        if (strpos($gitignore, $pattern) === false) {
            $missing_patterns[] = $pattern;
        }
    }

    if (empty($missing_patterns)) {
        echo "✓ .gitignore 包含所有必要規則\n";
        echo "  - .env 已被忽略\n";
        echo "  - uploads/ 已被忽略\n";
        echo "  - logs/ 已被忽略\n\n";
        $tests_passed++;
    } else {
        echo "⚠ .gitignore 缺少規則: " . implode(', ', $missing_patterns) . "\n\n";
        $tests_failed++;
    }
} else {
    echo "⚠ .gitignore 檔案不存在\n\n";
    $tests_failed++;
}

// ============================================
// 測試結果總結
// ============================================
echo "======================\n";
echo "測試結果總結\n";
echo "======================\n";
echo "通過: {$tests_passed}\n";
echo "失敗: {$tests_failed}\n";
echo "總計: " . ($tests_passed + $tests_failed) . "\n\n";

if ($tests_failed === 0) {
    echo "🎉 所有測試通過！Code Review 改善項目已正確實施。\n";
    exit(0);
} else {
    echo "⚠️  有 {$tests_failed} 個測試失敗，請檢查上述錯誤訊息。\n";
    exit(1);
}
?>