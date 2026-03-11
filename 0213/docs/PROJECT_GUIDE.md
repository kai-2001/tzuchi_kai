# 雲嘉學習網 (Portal + Moodle) 專案文件

## 專案概述

這是一個**入口網 (Portal)** 與 **Moodle 學習平台**整合系統。入口網作為主要的用戶入口，提供登入、儀表板、帳號管理等功能，並透過 SSO 無縫連接到 Moodle 學習系統。

### 核心概念

```
┌─────────────────┐     SSO      ┌─────────────────┐
│   入口網 Portal  │ ──────────> │     Moodle      │
│  (主要操作介面)   │ <────────── │   (學習平台)     │
└─────────────────┘   API/DB    └─────────────────┘
         │                              │
         ▼                              ▼
   portal_db (MySQL)           moodle_db (MySQL)
```

---

## 目錄結構

```
c:\Apache24\htdocs\
├── index.php                    # 主入口
├── login.php / logout.php       # 登入登出
├── register.php                 # 註冊
├── change_password.php          # 修改密碼
├── get_sso_url.php              # SSO URL 產生器 (AJAX)
│
├── includes/                    # 核心 PHP 模組
│   ├── config.php               # 資料庫和 Moodle 設定
│   ├── auth.php                 # 認證邏輯、角色同步
│   ├── moodle_api.php           # Moodle API 呼叫封裝
│   ├── functions.php            # 通用函式
│   └── db_connect.php           # 資料庫連線
│
├── templates/                   # 前端模板
│   ├── header.php               # 頁首 (含導覽列)
│   ├── footer.php               # 頁尾
│   ├── dashboard.php            # 儀表板主框架
│   ├── landing.php              # 登入前首頁
│   └── tabs/                    # 功能頁籤
│       ├── admin_console.php    # 管理員控制台
│       ├── student_home.php     # 學生首頁
│       ├── teacher_home.php     # 教師首頁
│       └── ...
│
├── api/                         # AJAX API 端點
│   └── (待建立 hospital_admin/)
│
├── scripts/                     # CLI 腳本 (直接操作 Moodle DB)
│   ├── get_user_category.php    # 取得用戶角色和類別
│   ├── sync_cohort.php          # 同步 Cohort 成員
│   └── ...
│
├── assets/                      # 前端資源
│   ├── css/
│   │   ├── style.css            # 入口網主樣式
│   │   └── moodle-head-inject.html  # Moodle HEAD 注入 CSS
│   ├── js/
│   │   ├── main.js              # 入口網主 JS
│   │   └── moodle-custom-integration.js  # Moodle 整合腳本
│   └── moodle-inject/
│       └── body-footer.html     # Moodle BODY 注入 (舊版)
│
├── moodle/                      # Moodle 核心 (勿隨意修改)
│   ├── config.php               # Moodle 設定
│   ├── local/ssologin/          # SSO 登入外掛
│   └── theme/academi_clean/     # 自訂主題
│
└── speech/                      # 影音演講系統 (獨立模組)
```

---

## 資料庫

### portal_db (入口網資料庫)

```sql
-- 主要用戶表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    fullname VARCHAR(100),
    email VARCHAR(100),
    role ENUM('student', 'teacherplus', 'hospital_admin', 'admin'),
    institution VARCHAR(50),         -- 機構: 台北, 嘉義, 大林, 花蓮
    remember_token VARCHAR(64)
);
```

### Moodle 資料庫 (透過 Moodle API 或 scripts/ 操作)

重要表格：
- `mdl_user` - 用戶
- `mdl_cohort` / `mdl_cohort_members` - 群組
- `mdl_role_assignments` - 角色指派
- `mdl_course_categories` - 課程類別

---

## 角色系統

| 角色 | 說明 | 權限 |
|------|------|------|
| `student` | 學生 | 選課、學習 |
| `teacherplus` | 開課教師 | 可建立課程 |
| `hospital_admin` | 院區管理員 | 管理所屬院區的成員和課程 |
| `admin` | 系統管理員 | 完整權限 |

### 角色檢測流程

```php
// scripts/get_user_category.php
// 透過 Moodle DB 查詢 mdl_role_assignments
// 判斷用戶在哪個 context 有什麼角色
// 回傳: { category_id, portal_role }
```

---

## SSO (Single Sign-On) 機制

### 流程

1. 用戶在入口網登入
2. 點擊 Moodle 連結時，呼叫 `get_sso_url.php`
3. 產生加密的 token + signature
4. 導向 `/moodle/local/ssologin/login.php`
5. Moodle 驗證 token 並完成登入

### 關鍵設定

```php
// includes/config.php
$moodle_url = 'http://kai/moodle';
$moodle_token = '...';           // Web Service Token
$moodle_sso_secret = '...';      // SSO 共享金鑰
```

---

## Cohort (群組) 系統

### 機構對應

```php
$cohort_map = [
    '台北' => 'cohort_taipei',
    '嘉義' => 'cohort_chiayi',
    '大林' => 'cohort_dalin',
    '花蓮' => 'cohort_hualien'
];
```

### 同步腳本

```bash
php scripts/sync_cohort.php [username] [cohort_idnumber]
```

---

## Moodle 整合注入

### 額外的 HTML (Moodle 後台設定)

1. **HEAD 區段內**: 貼上 `assets/css/moodle-head-inject.html`
   - CSS 變數、全域樣式
   - `/my/` 頁面重定向腳本

2. **BODY 結尾**: 引用 `assets/js/moodle-custom-integration.js`
   ```html
   <script src="/assets/js/moodle-custom-integration.js"></script>
   ```
   - 注入自訂導覽列
   - 角色切換功能
   - 強制移除 Sticky Footer

---

## API 呼叫模式

### Moodle Web Service API

```php
// includes/moodle_api.php
function call_moodle($url, $token, $function, $params) { ... }
function call_moodle_parallel($url, $token, $requests) { ... }

// 範例
$courses = call_moodle($moodle_url, $moodle_token, 
    'core_enrol_get_users_courses', 
    ['userid' => $moodle_uid]
);
```

### 直接 Moodle DB 操作 (CLI 腳本)

```php
// scripts/*.php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');
// 現在可以使用 $DB, $CFG 等 Moodle 全域變數
```

---

## Session 變數

登入後可用的 Session：

```php
$_SESSION['username']              // 帳號
$_SESSION['fullname']              // 全名
$_SESSION['is_admin']              // 是否為管理員
$_SESSION['is_hospital_admin']     // 是否為院區管理員
$_SESSION['is_teacherplus']        // 是否為開課教師
$_SESSION['institution']           // 機構名稱
$_SESSION['management_category_id'] // 可管理的課程類別 ID
$_SESSION['moodle_uid']            // Moodle 用戶 ID
```

---

## 開發注意事項

### 1. 修改 Moodle 核心
**不要直接修改 `moodle/` 目錄下的核心檔案**，使用：
- 額外的 HTML 注入
- 自訂主題 (`theme/academi_clean/`)
- 本地外掛 (`local/ssologin/`)

### 2. 前端樣式
- 入口網: `assets/css/style.css`
- Moodle: `assets/css/moodle-head-inject.html`

### 3. 新增 API 端點
放在 `api/` 目錄，格式：
```php
<?php
session_start();
require_once '../includes/config.php';
header('Content-Type: application/json');

// 權限檢查
if (!$_SESSION['is_hospital_admin']) {
    die(json_encode(['error' => '權限不足']));
}

// 業務邏輯...
echo json_encode(['success' => true, 'data' => ...]);
```

### 4. CLI 腳本 (操作 Moodle DB)
放在 `scripts/` 目錄，格式：
```php
<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');
// 使用 $DB->get_record(), $DB->insert_record() 等
```

---

## 待實作功能：Hospital Admin 管理

### 功能清單
1. **成員管理** - CRUD portal_db.users，同步到 Moodle
2. **權限調整** - 學生 ↔ 開課教師
3. **Cohort 管理** - 管理院區群組成員
4. **課程管理** - 跳轉 Moodle 帶 categoryid 參數
5. **報表統計** - 跳轉 Moodle 報表介面

### 權限隔離規則
- hospital_admin 只能操作自己 `institution` 對應的 cohort
- 只能管理 `management_category_id` 下的課程
