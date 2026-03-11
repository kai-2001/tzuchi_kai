# 專案深度審計報告

> 審查範圍：`c:\Apache24\htdocs` 全專案
> 審查時間：2026-02-13
> 審查維度：前後端分離、MVC 架構、可讀性、可修改性、可擴展性、可優化性、效能、安全性

---

## 整體評分

| 維度 | 分數 (1-10) | 說明 |
|------|:-----------:|------|
| 前後端分離 | **3** | 模板混合 PHP+HTML+CSS+JS，無 SPA 或 API-first 設計 |
| MVC 架構 | **4** | 有 Controller 層，但 Model/Service 幾乎為空殼 |
| 可讀性 | **5** | 命名清楚、有中文註解，但單檔過大、結構不一致 |
| 可修改性 | **3** | 高耦合、無介面/契約、改一處牽動多處 |
| 可擴展性 | **3** | 無 middleware、無 plugin 機制、路由表硬編碼 |
| 可優化性 | **4** | 有基本快取概念，但 N+1 查詢和同步阻塞嚴重 |
| 效能 | **4** | Session 快取、懶載入有雛形，但阻塞 curl 迴圈拖累 |
| 安全性 | **2** | 存在多個嚴重安全漏洞，見下方詳述 |

**綜合評價：4 / 10** — 能跑、功能完整，但離「可維護的生產級系統」有明顯差距。

---

## 1. 安全性 — 2/10（最嚴重）

### 🔴 致命問題

| # | 問題 | 位置 | 影響 |
|---|------|------|------|
| S1 | **密碼、Token、SSO Secret 明文寫死在原始碼** | [config.php](file:///c:/Apache24/htdocs/includes/config.php) L9-17 | DB 密碼 `wsx311226qwe`、Moodle Token、SSO Secret 全在 source code 裡；若 git repo 外洩等同全系統被攻破 |
| S2 | **生產環境開啟 `display_errors=1`** | [bootstrap.php](file:///c:/Apache24/htdocs/core/bootstrap.php) L10-11 | 錯誤訊息會直接顯示給使用者，洩漏資料庫結構、檔案路徑、內部變數 |
| S3 | **remember_token 明文存資料庫** | [auth.php](file:///c:/Apache24/htdocs/includes/auth.php) L425-432 | 若 DB 洩漏，攻擊者直接偽造登入 cookie；應存 `hash('sha256', $token)` |
| S4 | **角色判斷用不安全 Cookie** | [auth.php](file:///c:/Apache24/htdocs/includes/auth.php) L121-124, L405-408 | `portal_is_admin` 等 cookie 無 `HttpOnly`、`Secure`、`SameSite` 旗標；用戶端可竄改 cookie 提權 |
| S5 | **SOAP 登入傳 `md5(password)`** | [auth.php](file:///c:/Apache24/htdocs/includes/auth.php) L177 | MD5 不是安全的密碼雜湊，且看起來是傳給外部 SOAP 服務 |
| S6 | **SSL 驗證全部關閉** | [auth.php](file:///c:/Apache24/htdocs/includes/auth.php) L162-166 | `verify_peer=false` 允許 MITM 攻擊 |
| S7 | **V2 API 入口無全域認證守衛** | [api/v2/index.php](file:///c:/Apache24/htdocs/api/v2/index.php) L136-139 | 路由直接 `new $controllerClass()`，無 middleware 確認身份；靠每個 method 手動呼叫 `requireHospitalAdmin()`，容易漏掉 |
| S8 | **無 Rate Limiting** | 全專案 | 登入、API、批次操作均無速率限制；暴力破解和 DoS 無屏障 |
| S9 | **明文密碼回退** | [auth.php](file:///c:/Apache24/htdocs/includes/auth.php) L354 | `$user_row['password'] === $input_pass` — 支援明文密碼比對 |

### 🟡 中等問題

- **無 Content-Security-Policy (CSP)** headers
- **Session cookie 無 `SameSite` 屬性**（`session_set_cookie_params(0)` 只設 lifetime）
- **CSRF 保護只在主頁登入表單**，API 端點無 CSRF token 驗證
- **`ApiResponse::error()` 返回原始錯誤訊息**，可能洩漏內部資訊

---

## 2. MVC 架構 — 4/10

### 現狀分析

```
app/
├── Controllers/Api/   ← 11 個 Controller，5 個超過 27KB
├── Models/            ← 只有 1 個 Model (Tag.php, 5KB)
├── Services/          ← 只有 3 個 Service (Moodle 相關)
└── Views/             ← 空目錄
```

### 嚴重問題

| # | 問題 | 具體表現 |
|---|------|----------|
| A1 | **Fat Controller 反模式** | `MemberController.php` = 42KB、`CategoryController.php` = 36KB、`CourseController.php` = 33KB。Controller 直接寫 SQL、組裝 cURL、建構樹狀結構、處理業務邏輯 — 這些全應在 Service/Repository 層 |
| A2 | **Model 層形同虛設** | 11 個 Controller 對應 1 個 Model；絕大多數資料操作直接在 Controller 用 raw SQL |
| A3 | **Service 層不完整** | 只有 3 個 Moodle 相關 Service，其他業務（使用者管理、課程管理、群組管理）完全沒有 Service |
| A4 | **View 層不存在** | `app/Views/` 是空資料夾；所有模板在 `templates/` 目錄，與 app 結構脫節 |
| A5 | **雙重 API 系統共存** | `api/hospital_admin/` (16個舊檔) + `api/v2/index.php` (新)，legacy proxies 用 `$_GET['route']` 注入，hack 味重 |
| A6 | **無 Namespace** | 全專案 0 個 namespace，全靠 PSR-0 式 Autoloader，類別名稱無法表達歸屬 |

### 理想 vs 現實

```diff
- Controller → 直接寫 SQL、curl、組資料
+ Controller → 只負責輸入驗證 + 呼叫 Service + 回傳
+ Service    → 業務邏輯、流程編排
+ Repository → 資料存取 (封裝 SQL)
+ Model      → 資料結構 + 驗證規則
```

---

## 3. 前後端分離 — 3/10

### 模板層問題

| 檔案 | 大小 | 問題 |
|------|------|------|
| [hospital_admin_course_create_page.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_create_page.php) | 75KB (1414行) | PHP + HTML + 540行 inline JS，JS 混入 PHP echo |
| [hospital_admin_course_enrol.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_enrol.php) | 45KB | 同樣 PHP+HTML+JS 混合 |
| [async_loader.php](file:///c:/Apache24/htdocs/templates/async_loader.php) | 41KB | PHP 模板產生大量 JavaScript |
| [header.php](file:///c:/Apache24/htdocs/templates/header.php) | 13KB | 把 session 變數 echo 進 JS 物件 |

### 核心矛盾

專案同時存在兩種架構思路，互相衝突：
1. **傳統 Server-Side Rendering**：PHP 模板直接輸出 HTML，session 變數直接 echo
2. **AJAX + V2 API**：前端 fetch API 取資料，動態渲染

結果是：
- JS 裡嵌入 `<?php echo $var; ?>`，導致無法分離成 `.js` 檔
- 模板檔既是 View 又是 Controller（內含業務邏輯）
- 無法做前端建構（webpack/vite），無法做模板快取
- PHP 和 JS 的職責邊界模糊

---

## 4. 可讀性 — 5/10

### 優點 ✅
- 中文註解充分，意圖清楚
- 變數命名大致合理（`$institution`、`$cohort_map`）
- 基礎架構類注釋完整（`Router.php`、`Controller.php`）
- `HANDOFF.md` 和 `VERSIONS.md` 有文件意識

### 缺點 ❌
- **單檔過大**：5 個 Controller > 27KB，`moodle_api.php` = 54KB/1298行
- **不一致的程式風格**：`core/` 用 OOP + type hints，`includes/` 用 global 函式 + `$GLOBALS`
- **重複 PHPDoc**：[moodle_api.php](file:///c:/Apache24/htdocs/includes/moodle_api.php) 第 4-18 行有兩個重複的 `ensure_moodle_user_exists` docblock
- **混合中英文**：commit 和程式碼混用中英文，無統一規範
- **根目錄雜亂**：`debug_*.php`、`test_*.php`、`fix_*.php` 共 12 個散落在 webroot

---

## 5. 可修改性 — 3/10

### 主要問題

| 問題 | 具體表現 | 影響 |
|------|----------|------|
| **高耦合** | Database 靠 `$GLOBALS` 取 config；auth.php 靠 `global $db_host...`；Controller 直寫 SQL | 改資料庫結構要改所有 Controller |
| **無介面/契約** | Service 層無 interface；函式簽名不一致 | 無法 mock 測試，無法替換實作 |
| **硬編碼映射** | `$cohort_map`（院區→群組）在 auth.php 裡硬編碼 | 新增院區要改 code 兩處 |
| **Moodle DB 名稱硬編碼** | [Database.php](file:///c:/Apache24/htdocs/core/Database.php) L60 `'moodle'` | 換 DB 要改 source code |
| **路由表在 index.php 裡** | 80+ 路由寫死在一個 PHP array | 每加一個 API 要改 V2 入口檔 |
| **Debug/Test 檔在 webroot** | 12 個 test/debug/fix PHP 檔案 | 可被外部直接訪問執行 |

### 測試能力

- `tests/` 目錄只有 4 個檔案
- 無 PHPUnit 配置
- 無自動化測試 CI
- Controller 因直接依賴 `$_SESSION`、`$GLOBALS`、`Database::getInstance()`，幾乎不可單元測試

---

## 6. 可擴展性 — 3/10

| 缺失 | 說明 |
|------|------|
| **無 Middleware 管道** | Router 沒有 middleware 支援，auth/logging/rate-limit 無法統一注入 |
| **無事件/Hook 系統** | 加功能只能改 Controller 原始碼，無法「掛載」新邏輯 |
| **無 DI Container** | Controller 建構時 `new $class()` 無依賴注入；換實作要改呼叫端 |
| **API 版本管理粗糙** | V1/V2 靠目錄區分，legacy proxy 用 `$_GET` hack 轉發 |
| **模板無繼承/Layout** | 每個模板自帶完整 `<html><head>...</head>`，重複代碼嚴重 |

---

## 7. 效能 — 4/10

### 具體問題

| # | 問題 | 位置 | 影響 |
|---|------|------|------|
| P1 | **阻塞式 Moodle API 驗證迴圈** | [moodle_api.php](file:///c:/Apache24/htdocs/includes/moodle_api.php) L72-84 | 建立使用者後 `usleep(500000)` × 5 次輪詢，最差阻塞 2.5 秒 |
| P2 | **每次 API 呼叫解析全部 80+ 路由** | [api/v2/index.php](file:///c:/Apache24/htdocs/api/v2/index.php) | 陣列遍歷（低影響），但反映未做路由快取/分組 |
| P3 | **每次請求 require 全部基礎設施** | [bootstrap.php](file:///c:/Apache24/htdocs/core/bootstrap.php) | 6 個 require + autoloader，無 opcache 配置建議 |
| P4 | **Session 存 Moodle 快取** | [index.php](file:///c:/Apache24/htdocs/index.php) L29-33 | Session 膨脹，Redis/Memcached 更適合 |
| P5 | **資料庫查詢無 index 建議** | 全專案 | `migrations/` 有 5 個檔案但無 index 定義文件 |
| P6 | **Controller 內可能存在 N+1** | 各大 Controller | 批次操作逐筆 curl/SQL，無 batch 意識 |

---

## 8. 可優化性 — 4/10

### 值得肯定 ✅
- Bootstrap + Database singleton 避免重複連線
- 有 Session 快取機制（`moodle_cache`）
- API 已遷移至 V2 集中式路由
- 前端有 async_loader 概念

### 急需改善 ❌
- 無 Composer、無 PSR-4 autoload
- 無 `.env` 檔案 + dotenv 載入器
- 無統一的 Exception Handler
- 無 Logger（只用 `error_log()`），無日誌等級和輪轉
- 無 Response 快取 (ETags、Cache-Control)
- CSS/JS 無版本號/hash（瀏覽器快取失效問題）

---

## 優先修復建議

### 🔴 立刻處理（安全性）

1. **移除 `config.php` 中的明文密碼**，改用 `.env` + `vlucas/phpdotenv`
2. **關閉 `display_errors`**，改用 `error_log` + 自訂錯誤處理
3. **Cookie 加上安全旗標**：`HttpOnly`, `Secure`, `SameSite=Lax`
4. **remember_token 改存 hash**：DB 存 `hash('sha256', $token)`
5. **刪除或保護 webroot 下的 debug/test/fix 檔案**
6. **API 入口加全域認證 middleware**

### 🟡 短期改善（架構）

7. 引入 Service 層，把 Controller 裡的業務邏輯抽出
8. 引入 Repository/DAO 層，封裝 SQL 查詢
9. 清理雙重 API 系統，全面切換到 V2
10. 引入 Namespace + Composer autoload

### 🟢 中期規劃（品質）

11. 引入 PHPUnit + 測試覆蓋率
12. 前端改用 data-attribute 替代 PHP echo in JS
13. 模板引入 layout/extends 機制
14. 引入統一 Logger (Monolog)
15. 引入 Rate Limiting middleware

---

## 專案結構問題一覽

```
htdocs/                          ← webroot = 專案根目錄（危險）
├── debug_*.php (5個)            ← 🔴 可公開訪問的 debug 檔案
├── test_*.php (6個)             ← 🔴 可公開訪問的測試檔案
├── fix_*.php (2個)              ← 🔴 可公開訪問的修復腳本
├── config.php via includes/     ← 🔴 明文密碼在 source code
├── api/                         ← 🟡 16 個 legacy proxy（應刪除）
├── api/v2/index.php             ← 80+ 路由硬編碼在一個 array
├── app/Controllers/Api/         ← 5 個 > 27KB 的 Fat Controller
├── app/Models/                  ← 只有 1 個 Model
├── app/Services/                ← 只有 3 個 Service
├── app/Views/                   ← 空目錄
├── core/                        ← 基礎架構（尚可）
├── includes/                    ← legacy 函式庫：moodle_api.php=54KB
└── templates/                   ← PHP+HTML+CSS+JS 混合模板
```

---

> **結論**：這是一個「功能先行」的專案 — 功能開發速度快，但技術債累積嚴重。安全問題是最急迫的，架構問題會隨功能增長越來越拖累開發效率。建議以安全修復為第一優先，架構重構分階段漸進推進。
