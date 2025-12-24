# 跨平台 SSO 單一登入整合規範 (範例)

本文件提供一套標準化的 SSO 整合流程，供公司內部其他學習平台參考實作。此方案基於「共享金鑰 (Shared Secret)」機制，確保在不直接傳輸密碼的情況下，安全地完成身分驗證。

## 1. 核心技術參數
所有對接平台必須統一使用以下演算法：

- **加密演算法**：AES-256-CBC
- **簽名演算法**：HMAC-SHA256
- **通訊格式**：JSON
- **傳輸編碼**：Base64

## 2. SSO 流程圖
1. **使用者**：在入口網點擊「進入外部平台」。
2. **入口網**：產生加密資料項 (Data) 與簽名 (Signature)。
3. **重新導向**：導向外部平台 URL，帶入 `?data=...&sig=...`。
4. **外部平台**：驗證簽名、解開資料、建立 Session 並登入。

## 3. 範例程式碼 (接收端 - PHP 實作)
如果您的平台是 PHP 撰寫的，可以參考以下邏輯來收受 SSO 請求：

```php
<?php
// 配置資訊 (必須與入口網一致)
$shared_secret = "YOUR_SHARED_SECRET_KEY_32_CHARS"; 

// 1. 取得參數
$encrypted_data = $_GET['data'] ?? '';
$signature = $_GET['sig'] ?? '';

if (!$encrypted_data || !$signature) {
    die("未授權的存取");
}

// 2. 驗證簽名 (HMAC-SHA256)
// 範例：對密文驗證簽名
$expected_sig = hash_hmac('sha256', $encrypted_data, $shared_secret);

if ($signature !== $expected_sig) {
    die("簽名驗證失敗，請求可能已被篡改");
}

// 3. 解換資料 (AES-256-CBC)
$decoded = base64_decode($encrypted_data);
list($ciphertext, $iv) = explode('::', $decoded);
$payload_json = openssl_decrypt($ciphertext, 'aes-256-cbc', $shared_secret, 0, $iv);
$data = json_decode($payload_json, true);

// 4. 時效檢查 (防止重放攻擊)
$current_time = time();
if (abs($current_time - $data['timestamp']) > 300) { // 5 分鐘內有效
    die("請求已過期");
}

// 5. 執行登入程序
$username = $data['username'];
// TODO: 在您的系統中建立該使用者的 Session 或進行自動登入
echo "歡迎登入, " . htmlspecialchars($username);
?>
```

## 4. 關鍵安全要點 (主管必看)

> [!IMPORTANT]
> **共享金鑰管理**：每一對平台(入口網-App A, 入口網-App B) 應使用獨立的金鑰。即便 App A 的金鑰外洩，也不會影響到 App B 的安全。

> [!WARNING]
> **時間同步**：由於 SSO 包含時間戳記驗證，所有相關伺服器必須同步至標準網路時間 (NTP)。

> [!TIP]
> **HTTPS 強制要求**：雖然資料已加密，但為了保護網址不被瀏覽器歷史紀錄或暫存伺服器洩漏，跨主機通訊一定要強制使用 HTTPS。
