# SSO 單一登入實作說明

我們使用 **Moodle SSO Login 外掛** 實作了從入口網到 Moodle 的單一登入機制。

## 1. 核心概念

這是一個基於 **共享金鑰 (Shared Secret)** 的信任機制。

- **入口網**：身分提供者 (Identity Provider)。已經確認了使用者的身分。
- **Moodle**：服務提供者 (Service Provider)。信任入口網的驗證結果。
- **Shared Secret Key**：雙方約定的暗號，用來加密和簽署通訊內容，確保安全。

## 2. 詳細流程

整個流程發生在使用者點擊連結的一瞬間：

### (1) 使用者點擊連結
使用者在入口網點擊「課程列表」或「進入後台管理」按鈕。此時 JavaScript 攔截點擊事件，發送請求給 `get_sso_url.php`。

### (2) 入口網產生通行證 (Token)
`get_sso_url.php` 執行以下步驟：

1. **準備資料 (Payload)**：
   ```json
   {
       "username": "admin",
       "timestamp": 1702824000
   }
   ```
2. **加密 (Encryption)**：
   使用 **AES-256-CBC** 演算法和 **Shared Secret Key** 將資料加密。這樣即使有人攔截了網址，也看不懂裡面的內容。
3. **簽名 (Signature)**：
   使用 **HMAC-SHA256** 演算法產生簽名。這確保資料在傳輸過程中沒有被篡改。

### (3) 瀏覽器跳轉
JavaScript 收到加密後的 URL，將瀏覽器導向 Moodle：
```
http://localhost/moodle/local/ssologin/login.php?data=加密資料&sig=簽名&wantsurl=目標頁面
```

### (4) Moodle 驗證與登入
Moodle 的 SSO Login 外掛收到請求後：

1. **驗證簽名**：用同一個 Shared Secret Key 計算簽名，確認與 `sig` 參數一致。如果不一致，代表有人試圖篡改資料。
2. **解密資料**：解開 `data` 参数，取得 `username` 和 `timestamp`。
3. **檢查時效**：檢查 `timestamp` 是否在允許誤差範圍內（例如 5 分鐘）。這防止了「重放攻擊」（有人攔截舊的 URL 重複使用）。
4. **自動登入**：如果一切驗證通過，Moodle 直接將該 `username` 的使用者設為登入狀態 (Session)，並跳轉到 `wantsurl` 指定的頁面。

## 3. 安全性分析

| 安全威脅 | 防護機制 |
|----------|----------|
| **偽造身分** | 攻擊者沒有 Secret Key，無法產生有效的加密資料和簽名。 |
| **篡改請求** | 修改 URL 參數會導致簽名驗證失敗。 |
| **重放攻擊** | 即使攻擊者攔截了合法 URL，也因為時間戳記過期而無法重複使用。 |
| **密碼洩漏** | 整個過程**完全不傳輸密碼**。 |

這就是為什麼這個方案比「儲存可解密密碼」或「傳輸密碼」更安全的原因。
