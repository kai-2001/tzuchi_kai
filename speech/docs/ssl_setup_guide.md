# Apache HTTPS/SSL 設定完整教學

> **適用環境：** Windows + Apache 2.4  
> **目標：** 為 Speech 學術影片平台啟用 HTTPS 加密連線

---

## 📋 目錄

1. [為什麼需要 HTTPS](#為什麼需要-https)
2. [方案選擇](#方案選擇)
3. [方案 A：Let's Encrypt 免費憑證（正式環境）](#方案-a-lets-encrypt-免費憑證正式環境)
4. [方案 B：自簽憑證（測試環境）](#方案-b-自簽憑證測試環境)
5. [Apache 設定](#apache-設定)
6. [強制 HTTPS 轉址](#強制-https-轉址)
7. [常見問題排解](#常見問題排解)

---

## 🔒 為什麼需要 HTTPS

### HTTP vs HTTPS

| 項目 | HTTP | HTTPS |
|------|------|-------|
| 資料傳輸 | 明文 | 加密 🔒 |
| 登入密碼 | ❌ 可被竊聽 | ✅ 加密保護 |
| 瀏覽器警告 | ⚠️ 不安全 | ✅ 綠色鎖頭 |
| SEO 排名 | 普通 | 加分 ⭐ |
| 現代功能 | 受限 | 完整支援 |

### 使用情境

- ✅ **正式上線必備**（保護用戶資料）
- ✅ **有對外網域**（如 `speech.tzuchi.edu.tw`）
- ❌ 本機開發測試（可選，非必要）

---

## 🎯 方案選擇

### 方案 A：Let's Encrypt（推薦）

**適用：** 正式環境，有對外網域

- ✅ **完全免費**
- ✅ 被所有瀏覽器信任
- ✅ 自動更新（90 天）
- ✅ 5 分鐘完成設定

### 方案 B：自簽憑證

**適用：** 測試環境，無對外網域

- ✅ 免費
- ⚠️ 瀏覽器會警告「不安全」
- ✅ 僅供內部測試

---

## 🆓 方案 A: Let's Encrypt 免費憑證（正式環境）

### 前置需求

1. **網域名稱**（例如：`speech.tzuchi.edu.tw`）
2. **網域 DNS 已指向你的伺服器**
3. **防火牆開放 80 和 443 port**

---

### Step 1: 下載 Certbot

**官方下載：** https://certbot.eff.org/

1. 選擇 "Apache" + "Windows"
2. 下載 `certbot-beta-installer-win_amd64.exe`
3. 執行安裝（預設路徑：`C:\Program Files\Certbot\`）

---

### Step 2: 申請憑證

開啟 **PowerShell（管理員）**：

```powershell
# 切換到 Certbot 目錄
cd "C:\Program Files\Certbot\bin"

# 申請憑證並自動設定 Apache
.\certbot.exe --apache -d speech.tzuchi.edu.tw
```

**互動式問答：**
```
Email address: your-email@tzuchi.edu.tw  # 用於憑證到期通知
(A)gree: A  # 同意服務條款
(Y)es/(N)o: N  # 不接收 EFF 通訊（可選）
```

**完成！** Certbot 會自動：
- ✅ 產生憑證
- ✅ 修改 Apache 設定
- ✅ 啟用 HTTPS

---

### Step 3: 測試網站

開啟瀏覽器：
```
https://speech.tzuchi.edu.tw
```

**檢查結果：**
- ✅ 看到 🔒 綠色鎖頭 → 成功！
- ❌ 錯誤訊息 → 查看[常見問題](#常見問題排解)

---

### Step 4: 自動更新設定

Let's Encrypt 憑證 **90 天到期**，需設定自動更新。

**方法 1：Windows 工作排程器**

1. 開啟 `taskschd.msc`
2. 建立基本工作
   - 名稱：`Certbot Renew`
   - 觸發程序：每日 12:00
   - 動作：啟動程式
     - 程式：`C:\Program Files\Certbot\bin\certbot.exe`
     - 引數：`renew --quiet`

**方法 2：手動測試**

```powershell
# 測試更新（不會實際更新）
certbot renew --dry-run

# 實際更新
certbot renew
```

---

## 🧪 方案 B: 自簽憑證（測試環境）

### 前置需求

- OpenSSL（Apache 內建）

---

### Step 1: 產生自簽憑證

開啟 PowerShell：

```powershell
# 切換到 Apache 設定目錄
cd C:\Apache24\conf

# 建立憑證目錄
mkdir ssl
cd ssl

# 產生憑證（有效期 365 天）
openssl req -new -x509 -days 365 -nodes -out server.crt -keyout server.key
```

**互動式問答：**
```
Country Name: TW
State or Province: Taiwan
Locality Name: Hualien
Organization Name: Tzu Chi
Organizational Unit: IT
Common Name: localhost          # ← 重要！填網域或 localhost
Email Address: admin@tzuchi.edu.tw
```

**產生的檔案：**
- `server.crt` - 憑證檔
- `server.key` - 私鑰檔

---

### Step 2: 設定權限（選用）

```powershell
# 限制私鑰檔案權限
icacls server.key /inheritance:r
icacls server.key /grant:r "NT AUTHORITY\SYSTEM:R"
icacls server.key /grant:r "BUILTIN\Administrators:R"
```

---

## ⚙️ Apache 設定

### Step 1: 啟用 SSL 模組

編輯 `C:\Apache24\conf\httpd.conf`：

**找到並取消註解（移除 `#`）：**
```apache
LoadModule ssl_module modules/mod_ssl.so
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
Include conf/extra/httpd-ssl.conf
```

---

### Step 2: 設定 SSL 虛擬主機

編輯 `C:\Apache24\conf\extra\httpd-ssl.conf`：

```apache
# 基本 SSL 設定
Listen 443

SSLCipherSuite HIGH:MEDIUM:!MD5:!RC4:!3DES
SSLProxyCipherSuite HIGH:MEDIUM:!MD5:!RC4:!3DES
SSLHonorCipherOrder on
SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
SSLProxyProtocol all -SSLv3 -TLSv1 -TLSv1.1
SSLPassPhraseDialog  builtin
SSLSessionCache "shmcb:C:/Apache24/logs/ssl_scache(512000)"
SSLSessionCacheTimeout 300

# SSL 虛擬主機
<VirtualHost *:443>
    # 網域名稱（改成你的網域或 localhost）
    ServerName speech.tzuchi.edu.tw
    ServerAdmin admin@tzuchi.edu.tw
    
    # 網站根目錄
    DocumentRoot "C:/Apache24/htdocs/speech"
    
    # 錯誤日誌
    ErrorLog "C:/Apache24/logs/ssl_error.log"
    TransferLog "C:/Apache24/logs/ssl_access.log"
    LogLevel warn
    
    # 啟用 SSL
    SSLEngine on
    
    # === 憑證路徑（根據方案選擇） ===
    
    # Let's Encrypt 憑證路徑（方案 A）
    SSLCertificateFile "C:/Certbot/live/speech.tzuchi.edu.tw/cert.pem"
    SSLCertificateKeyFile "C:/Certbot/live/speech.tzuchi.edu.tw/privkey.pem"
    SSLCertificateChainFile "C:/Certbot/live/speech.tzuchi.edu.tw/chain.pem"
    
    # 自簽憑證路徑（方案 B）
    # SSLCertificateFile "C:/Apache24/conf/ssl/server.crt"
    # SSLCertificateKeyFile "C:/Apache24/conf/ssl/server.key"
    
    # 目錄權限
    <Directory "C:/Apache24/htdocs/speech">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # PHP 支援
    <FilesMatch "\.php$">
        SetHandler application/x-httpd-php
    </FilesMatch>
</VirtualHost>
```

---

### Step 3: 重啟 Apache

```powershell
# 測試設定檔語法
C:\Apache24\bin\httpd.exe -t

# 重啟 Apache
net stop Apache2.4
net start Apache2.4

# 或透過服務管理
services.msc → Apache2.4 → 重新啟動
```

---

## 🔄 強制 HTTPS 轉址

讓所有 HTTP 自動轉到 HTTPS。

### 方法 1: .htaccess（推薦）

在 `c:\Apache24\htdocs\speech\.htaccess` 加入：

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # 強制 HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
```

### 方法 2: Apache 設定

在 `httpd.conf` 的 HTTP 虛擬主機加入：

```apache
<VirtualHost *:80>
    ServerName speech.tzuchi.edu.tw
    Redirect permanent / https://speech.tzuchi.edu.tw/
</VirtualHost>
```

---

## 🐛 常見問題排解

### 1. 瀏覽器顯示「您的連線不安全」

**原因：** 自簽憑證

**解決方法：**
- 測試環境：點選「進階」→「繼續前往」
- 正式環境：改用 Let's Encrypt

---

### 2. Apache 啟動失敗

**檢查錯誤日誌：**
```powershell
Get-Content C:\Apache24\logs\error.log -Tail 50
```

**常見錯誤：**

#### 錯誤：`SSLCertificateFile: file does not exist`
```
解決：檢查憑證路徑是否正確
```

#### 錯誤：`port 443 already in use`
```powershell
# 查看占用 443 port 的程式
netstat -ano | findstr :443

# 終止該程序（PID 替換為實際值）
taskkill /PID 1234 /F
```

---

### 3. Let's Encrypt 申請失敗

**錯誤：`Failed authorization procedure`**

**檢查清單：**
- ✅ DNS 已指向正確 IP
- ✅ 防火牆開放 80 port（Let's Encrypt 驗證用）
- ✅ Apache 正在運行
- ✅ 網域可從外部存取

**測試 DNS：**
```powershell
nslookup speech.tzuchi.edu.tw
ping speech.tzuchi.edu.tw
```

---

### 4. 憑證過期

```powershell
# 手動更新 Let's Encrypt 憑證
certbot renew

# 檢查憑證狀態
certbot certificates
```

---

### 5. Mixed Content 警告

**問題：** 網站部分資源仍用 HTTP

**解決：** 確保所有資源都用 HTTPS

```html
<!-- ❌ 錯誤 -->
<script src="http://example.com/script.js"></script>

<!-- ✅ 正確 -->
<script src="https://example.com/script.js"></script>

<!-- ✅ 協議相對 -->
<script src="//example.com/script.js"></script>
```

---

## 📊 憑證檢查工具

### 線上工具

- **SSL Labs:** https://www.ssllabs.com/ssltest/
  - 輸入網域，檢查 SSL 設定評分
  - 目標：A 或 A+ 評級

- **WhyNoPadlock:** https://www.whynopadlock.com/
  - 檢查 Mixed Content 問題

### 命令列工具

```powershell
# 檢查憑證資訊
openssl s_client -connect speech.tzuchi.edu.tw:443 -servername speech.tzuchi.edu.tw

# 檢查憑證到期日
openssl s_client -connect speech.tzuchi.edu.tw:443 2>/dev/null | openssl x509 -noout -dates
```

---

## 📝 設定檔備份

**重要檔案：**
```
C:\Apache24\conf\httpd.conf
C:\Apache24\conf\extra\httpd-ssl.conf
C:\Apache24\htdocs\speech\.htaccess
C:\Apache24\conf\ssl\server.crt  (自簽憑證)
C:\Apache24\conf\ssl\server.key  (自簽私鑰)
```

**備份指令：**
```powershell
# 建立備份目錄
mkdir C:\Apache24\backup

# 複製設定檔
Copy-Item C:\Apache24\conf\*.conf C:\Apache24\backup\
Copy-Item C:\Apache24\conf\extra\*.conf C:\Apache24\backup\
Copy-Item C:\Apache24\conf\ssl\* C:\Apache24\backup\
```

---

## ✅ 設定完成檢查清單

### 正式上線前

- [ ] SSL 憑證已安裝（Let's Encrypt 或商業憑證）
- [ ] Apache SSL 模組已啟用
- [ ] HTTPS 網站可正常存取
- [ ] 瀏覽器顯示綠色鎖頭 🔒
- [ ] HTTP 自動轉址到 HTTPS
- [ ] SSL Labs 評分 A 或 A+
- [ ] 無 Mixed Content 警告
- [ ] 憑證自動更新已設定（Let's Encrypt）
- [ ] 設定檔已備份

### 更新 .env 設定

**如果其他服務需要連線到你的 HTTPS 網站：**

```env
# speech/.env
APP_URL=https://speech.tzuchi.edu.tw
```

---

## 🔗 參考資源

- **Let's Encrypt 官網:** https://letsencrypt.org/
- **Certbot 文件:** https://certbot.eff.org/docs/
- **Apache SSL 文件:** https://httpd.apache.org/docs/2.4/ssl/
- **SSL Labs 測試:** https://www.ssllabs.com/ssltest/

---

## 💡 最佳實踐

1. **使用 Let's Encrypt**（免費且自動更新）
2. **設定自動更新**（避免憑證過期）
3. **強制 HTTPS**（自動轉址）
4. **定期備份**（設定檔和憑證）
5. **監控到期時間**（設定提醒）

---

**🎉 完成設定後，你的網站將擁有安全的 HTTPS 連線！**

如有問題，請查閱：
- Apache 錯誤日誌：`C:\Apache24\logs\error.log`
- SSL 錯誤日誌：`C:\Apache24\logs\ssl_error.log`
- Certbot 日誌：`C:\Certbot\log\letsencrypt.log`
