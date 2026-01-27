# Apache HTTPS/SSL 設定完整教學（Linux 版）

> **適用環境：** Ubuntu/Debian + Apache 2.4  
> **目標：** 為 Speech 學術影片平台啟用 HTTPS 加密連線

---

## 🚀 快速開始（5 分鐘）

**如果你有網域名稱且 DNS 已設定好：**

```bash
# 安裝 Certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# 一鍵啟用 HTTPS（自動申請憑證 + 設定 Apache）
sudo certbot --apache -d speech.tzuchi.edu.tw

# 完成！瀏覽器開啟 https://speech.tzuchi.edu.tw
```

---

## 📋 目錄

1. [為什麼需要 HTTPS](#為什麼需要-https)
2. [Let's Encrypt 免費憑證（推薦）](#lets-encrypt-免費憑證推薦)
3. [自簽憑證（測試環境）](#自簽憑證測試環境)
4. [Apache 設定](#apache-設定)
5. [強制 HTTPS 轉址](#強制-https-轉址)
6. [常見問題排解](#常見問題排解)

---

## 🔒 為什麼需要 HTTPS

| 項目 | HTTP | HTTPS |
|------|------|-------|
| 資料傳輸 | 明文 | 加密 🔒 |
| 登入密碼 | ❌ 可被竊聽 | ✅ 加密保護 |
| 瀏覽器警告 | ⚠️ 不安全 | ✅ 綠色鎖頭 |
| SEO 排名 | 普通 | 加分 ⭐ |

---

## 🆓 Let's Encrypt 免費憑證（推薦）

### 前置需求

- ✅ 網域名稱（如 `speech.tzuchi.edu.tw`）
- ✅ DNS 已指向伺服器 IP
- ✅ 防火牆開放 80 和 443 port

---

### Step 1: 安裝 Certbot

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install certbot python3-certbot-apache
```

**CentOS/RHEL:**
```bash
sudo yum install certbot python3-certbot-apache
# 或
sudo dnf install certbot python3-certbot-apache
```

---

### Step 2: 申請憑證並自動設定

```bash
# 單一網域
sudo certbot --apache -d speech.tzuchi.edu.tw

# 多個子網域
sudo certbot --apache -d speech.tzuchi.edu.tw -d www.speech.tzuchi.edu.tw
```

**互動式問答：**
```
Email: admin@tzuchi.edu.tw          # 用於到期提醒
(A)gree: A                           # 同意服務條款
(Y)es/(N)o: N                        # 不接收 EFF 通訊（可選）
Redirect HTTP to HTTPS? 2            # 選 2（自動轉址）
```

**完成！** Certbot 會自動：
- ✅ 申請並安裝憑證
- ✅ 修改 Apache 設定
- ✅ 啟用 HTTPS
- ✅ 設定 HTTP → HTTPS 轉址

---

### Step 3: 驗證網站

```bash
# 開啟瀏覽器
https://speech.tzuchi.edu.tw
```

**檢查：**
- ✅ 看到 🔒 綠色鎖頭 → 成功！
- ✅ HTTP 自動轉到 HTTPS → 成功！

---

### Step 4: 設定自動更新

Let's Encrypt 憑證 **90 天到期**，Certbot 會自動設定更新排程。

**檢查自動更新：**
```bash
# 檢查定時任務
sudo systemctl status certbot.timer

# 測試更新（不會實際更新）
sudo certbot renew --dry-run

# 手動更新
sudo certbot renew
```

**如果沒有自動排程：**
```bash
# 新增 cron job（每天凌晨 2:00）
sudo crontab -e

# 加入這行
0 2 * * * certbot renew --quiet
```

---

## 🧪 自簽憑證（測試環境）

**適用：** 無對外網域，僅供內部測試

### Step 1: 產生憑證

```bash
# 建立憑證目錄
sudo mkdir -p /etc/apache2/ssl
cd /etc/apache2/ssl

# 產生憑證（有效期 365 天）
sudo openssl req -new -x509 -days 365 -nodes \
  -out server.crt \
  -keyout server.key \
  -subj "/C=TW/ST=Taiwan/L=Hualien/O=Tzu Chi/CN=localhost"

# 設定權限
sudo chmod 600 server.key
sudo chmod 644 server.crt
```

---

### Step 2: 設定 Apache

建立 SSL 設定檔：
```bash
sudo nano /etc/apache2/sites-available/speech-ssl.conf
```

**貼上以下內容：**
```apache
<VirtualHost *:443>
    ServerName localhost
    DocumentRoot /var/www/html/speech
    
    SSLEngine on
    SSLCertificateFile /etc/apache2/ssl/server.crt
    SSLCertificateKeyFile /etc/apache2/ssl/server.key
    
    <Directory /var/www/html/speech>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/ssl_access.log combined
</VirtualHost>
```

**啟用設定：**
```bash
sudo a2enmod ssl
sudo a2ensite speech-ssl.conf
sudo systemctl restart apache2
```

---

## ⚙️ Apache 設定（進階）

### 手動設定 SSL 虛擬主機

**編輯設定檔：**
```bash
# Ubuntu/Debian
sudo nano /etc/apache2/sites-available/speech-ssl.conf

# CentOS/RHEL
sudo nano /etc/httpd/conf.d/speech-ssl.conf
```

**完整設定範例：**
```apache
<VirtualHost *:443>
    ServerName speech.tzuchi.edu.tw
    ServerAdmin admin@tzuchi.edu.tw
    DocumentRoot /var/www/html/speech
    
    # SSL 設定
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/speech.tzuchi.edu.tw/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/speech.tzuchi.edu.tw/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/speech.tzuchi.edu.tw/chain.pem
    
    # 安全性設定
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
    SSLHonorCipherOrder on
    
    # HSTS (可選，強制瀏覽器使用 HTTPS)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    # 目錄權限
    <Directory /var/www/html/speech>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # 日誌
    ErrorLog ${APACHE_LOG_DIR}/speech_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/speech_ssl_access.log combined
</VirtualHost>
```

**啟用設定：**
```bash
# 啟用 SSL 模組
sudo a2enmod ssl
sudo a2enmod headers

# 啟用網站設定
sudo a2ensite speech-ssl.conf

# 測試設定
sudo apache2ctl configtest

# 重啟 Apache
sudo systemctl restart apache2
```

---

## 🔄 強制 HTTPS 轉址

### 方法 1: .htaccess（推薦）

**編輯 `.htaccess`：**
```bash
sudo nano /var/www/html/speech/.htaccess
```

**加入以下內容：**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # 強制 HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
```

**啟用 mod_rewrite：**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

### 方法 2: Apache VirtualHost

**編輯 HTTP 設定：**
```bash
sudo nano /etc/apache2/sites-available/speech.conf
```

**加入轉址：**
```apache
<VirtualHost *:80>
    ServerName speech.tzuchi.edu.tw
    Redirect permanent / https://speech.tzuchi.edu.tw/
</VirtualHost>
```

**重新載入設定：**
```bash
sudo systemctl reload apache2
```

---

## 🔥 防火牆設定

### Ubuntu (UFW)
```bash
# 開放 HTTP 和 HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 或使用預設規則
sudo ufw allow 'Apache Full'

# 檢查狀態
sudo ufw status
```

### CentOS (Firewalld)
```bash
# 開放 HTTP 和 HTTPS
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

# 檢查
sudo firewall-cmd --list-all
```

---

## 🐛 常見問題排解

### 1. Apache 無法啟動

**檢查錯誤日誌：**
```bash
sudo journalctl -u apache2 -n 50
# 或
sudo tail -50 /var/log/apache2/error.log
```

**常見錯誤：**

#### 憑證路徑錯誤
```bash
# 檢查憑證是否存在
sudo ls -la /etc/letsencrypt/live/speech.tzuchi.edu.tw/
```

#### Port 443 被占用
```bash
# 查看占用 443 的程序
sudo lsof -i :443
sudo netstat -tlnp | grep :443

# 終止程序（PID 替換為實際值）
sudo kill -9 PID
```

---

### 2. Let's Encrypt 申請失敗

**錯誤：`Failed authorization procedure`**

**檢查清單：**
```bash
# 1. 檢查 DNS
nslookup speech.tzuchi.edu.tw
dig speech.tzuchi.edu.tw

# 2. 檢查防火牆
sudo ufw status
sudo firewall-cmd --list-all

# 3. 檢查 Apache 是否運行
sudo systemctl status apache2

# 4. 測試網站是否可從外部存取
curl http://speech.tzuchi.edu.tw
```

**解決方式：**
```bash
# 確保 Apache 監聽 80 port
sudo netstat -tlnp | grep :80

# 臨時關閉防火牆測試（測試後記得開回來）
sudo ufw disable
sudo certbot --apache -d speech.tzuchi.edu.tw
sudo ufw enable
```

---

### 3. 憑證更新失敗

```bash
# 查看更新日誌
sudo cat /var/log/letsencrypt/letsencrypt.log

# 手動更新
sudo certbot renew --dry-run

# 強制更新
sudo certbot renew --force-renewal
```

---

### 4. Mixed Content 警告

**問題：** 部分資源仍用 HTTP

**檢查：**
```bash
# 搜尋 HTTP 連結
grep -r "http://" /var/www/html/speech/ --include="*.php" --include="*.html"
```

**修正：**
```php
// ❌ 錯誤
<script src="http://example.com/script.js"></script>

// ✅ 正確
<script src="https://example.com/script.js"></script>
```

---

### 5. SSL Labs 評分不是 A+

**改善建議：**

```apache
# 在 VirtualHost 加入以下設定

# 1. 使用現代加密協定
SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1

# 2. 強加密套件
SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384

# 3. HSTS
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

# 4. 其他安全標頭
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
```

---

## 📊 憑證管理指令

### 檢查憑證狀態
```bash
# 列出所有憑證
sudo certbot certificates

# 檢查憑證到期時間
sudo certbot certificates | grep Expiry

# 查看憑證詳細資訊
sudo openssl x509 -in /etc/letsencrypt/live/speech.tzuchi.edu.tw/cert.pem -text -noout
```

### 更新憑證
```bash
# 測試更新
sudo certbot renew --dry-run

# 實際更新
sudo certbot renew

# 強制更新（不建議，除非測試）
sudo certbot renew --force-renewal
```

### 撤銷憑證
```bash
# 撤銷並刪除憑證
sudo certbot revoke --cert-path /etc/letsencrypt/live/speech.tzuchi.edu.tw/cert.pem
sudo certbot delete --cert-name speech.tzuchi.edu.tw
```

---

## 🧪 測試工具

### SSL Labs 測試
```bash
# 線上測試（需等待 2-3 分鐘）
https://www.ssllabs.com/ssltest/analyze.html?d=speech.tzuchi.edu.tw
```

### 命令列測試
```bash
# 測試 SSL 連線
openssl s_client -connect speech.tzuchi.edu.tw:443 -servername speech.tzuchi.edu.tw

# 檢查憑證鏈
openssl s_client -showcerts -connect speech.tzuchi.edu.tw:443

# 測試特定 TLS 版本
openssl s_client -connect speech.tzuchi.edu.tw:443 -tls1_2
```

---

## 📁 重要檔案位置

### Ubuntu/Debian
```
設定檔：
/etc/apache2/sites-available/speech-ssl.conf
/etc/apache2/apache2.conf
/var/www/html/speech/.htaccess

憑證：
/etc/letsencrypt/live/speech.tzuchi.edu.tw/cert.pem
/etc/letsencrypt/live/speech.tzuchi.edu.tw/privkey.pem
/etc/letsencrypt/live/speech.tzuchi.edu.tw/chain.pem

日誌：
/var/log/apache2/error.log
/var/log/apache2/ssl_error.log
/var/log/letsencrypt/letsencrypt.log
```

### CentOS/RHEL
```
設定檔：
/etc/httpd/conf.d/speech-ssl.conf
/etc/httpd/conf/httpd.conf

憑證：
/etc/letsencrypt/live/speech.tzuchi.edu.tw/

日誌：
/var/log/httpd/error_log
/var/log/httpd/ssl_error_log
```

---

## 📝 部署檢查清單

**正式上線前：**

- [ ] DNS 已指向伺服器 IP
- [ ] 防火牆開放 80 和 443 port
- [ ] Apache 已安裝並運行
- [ ] 網域可從外部存取
- [ ] Certbot 已安裝
- [ ] SSL 憑證已申請並安裝
- [ ] HTTPS 網站可正常存取
- [ ] 瀏覽器顯示綠色鎖頭 🔒
- [ ] HTTP 自動轉址到 HTTPS
- [ ] SSL Labs 評分 A 或 A+
- [ ] 憑證自動更新已設定
- [ ] 備份已完成

---

## 🔄 系統備份

**備份重要檔案：**
```bash
# 建立備份目錄
sudo mkdir -p /backup/apache-ssl

# 備份 Apache 設定
sudo cp -r /etc/apache2/sites-available /backup/apache-ssl/
sudo cp -r /etc/letsencrypt /backup/apache-ssl/

# 備份 .htaccess
sudo cp /var/www/html/speech/.htaccess /backup/apache-ssl/

# 打包
sudo tar -czf /backup/apache-ssl-$(date +%Y%m%d).tar.gz /backup/apache-ssl/
```

---

## 🔗 參考資源

- **Let's Encrypt:** https://letsencrypt.org/
- **Certbot:** https://certbot.eff.org/
- **Apache SSL 文件:** https://httpd.apache.org/docs/2.4/ssl/
- **SSL Labs:** https://www.ssllabs.com/ssltest/
- **Mozilla SSL 設定產生器:** https://ssl-config.mozilla.org/

---

## ✅ 完成！

**設定完成後：**

1. **開啟瀏覽器**
   ```
   https://speech.tzuchi.edu.tw
   ```

2. **確認綠色鎖頭 🔒**

3. **測試 SSL Labs**
   - 目標評分：A 或 A+

4. **設定監控**
   - 憑證到期提醒
   - 自動更新日誌

---

**🎉 恭喜！你的網站現在擁有安全的 HTTPS 連線！**

**如遇問題，請檢查：**
```bash
sudo journalctl -u apache2 -f          # Apache 即時日誌
sudo tail -f /var/log/apache2/error.log # Apache 錯誤日誌
sudo tail -f /var/log/letsencrypt/letsencrypt.log # Certbot 日誌
```
