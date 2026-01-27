---
name: Speech 專案架構規範
description: PHP MVC 架構模式，包含前後端分離、雙重驗證、統一 API 回應
---

# Speech 專案架構規範

本規範定義了 PHP 網站專案的標準架構模式，適用於中小型 Web 應用程式。

## 目錄結構

```
專案根目錄/
├── index.php                   # 首頁 Controller
├── login.php                   # 登入 Controller
├── [功能名].php                # 其他 Controller
│
├── includes/                   # 核心函式庫
│   ├── config.php              # 設定檔 (資料庫、常數)
│   ├── auth.php                # 認證函式
│   ├── helpers.php             # 輔助函式
│   ├── Validator.php           # 統一驗證類別
│   ├── ApiResponse.php         # 統一 API 回應類別
│   └── models/                 # 資料模型
│       ├── Video.php           # 範例：影片模型
│       └── Announcement.php    # 範例：公告模型
│
├── templates/                  # 視圖模板
│   ├── home.php                # 首頁視圖
│   ├── login.php               # 登入視圖
│   ├── [功能名].php            # 其他視圖
│   └── partials/               # 共用元件
│       ├── header.php          # 頁首 (HTML head)
│       ├── navbar.php          # 導覽列
│       └── footer.php          # 頁尾 + JS 引入
│
├── assets/                     # 靜態資源
│   ├── css/
│   │   ├── style.css           # 主樣式
│   │   └── forms.css           # 表單樣式
│   └── js/
│       ├── main.js             # 主 JavaScript
│       └── validators.js       # 前端驗證
│
├── api/                        # API 端點 (選用)
│   └── v1/
│       └── [資源名].php
│
├── uploads/                    # 使用者上傳檔案
│
└── tests/                      # 測試檔案
    └── test_validator.php
```

---

## MVC 架構模式

### Controller (控制器) - 根目錄 PHP 檔案

```php
<?php
/**
 * [功能名] Controller
 * 
 * Handles: [描述功能]
 * Template: templates/[功能名].php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Validator.php';

// ============================================
// LOGIC: Access Control (權限檢查)
// ============================================
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// ============================================
// LOGIC: Handle Form Submission (表單處理)
// ============================================
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 後端驗證
    $validation = Validator::validate($_POST, 'form_name');
    if (!$validation['valid']) {
        $error = Validator::getFirstError($validation['errors']);
    } else {
        // 處理邏輯...
    }
}

// ============================================
// LOGIC: Fetch Data (取得資料)
// ============================================
$data = model_get_data();

// ============================================
// TEMPLATE: Pass data to template (傳遞給視圖)
// ============================================
$page_title = '頁面標題';
$page_css_files = ['forms.css'];

include 'templates/[功能名].php';
```

### Model (資料模型) - includes/models/

```php
<?php
/**
 * [資源名] Model
 * Centralizes database operations for the [資源名] table.
 */

function resource_get_all($limit = 10, $offset = 0) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM table_name LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function resource_get_by_id($id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM table_name WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function resource_create($data) {
    global $conn;
    // INSERT logic...
}
```

### View (視圖) - templates/

```php
<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- 頁面內容 -->
    <form id="formName" method="POST">
        <!-- 表單欄位 -->
    </form>
</div>

<script src="assets/js/validators.js"></script>
<script>
    <?php require_once __DIR__ . '/../includes/Validator.php'; ?>
    const rules = <?= Validator::getRulesJson('form_name') ?>;
    FormValidator.init('formName', rules);
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
```

---

## 雙重驗證系統

### 後端驗證 - includes/Validator.php

```php
class Validator {
    const RULES = [
        'form_name' => [
            'field_name' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'type' => 'email|date|select',  // 選用
                'label' => '欄位顯示名稱'
            ]
        ]
    ];
    
    public static function validate(array $data, string $formName): array {
        // 驗證邏輯...
        return ['valid' => bool, 'errors' => array];
    }
    
    public static function getRulesJson(string $formName): string {
        return json_encode(self::RULES[$formName] ?? []);
    }
}
```

### 前端驗證 - assets/js/validators.js

```javascript
const FormValidator = {
    init(formId, rules) {
        // 綁定 submit 事件
        // 綁定 blur 事件做即時驗證
    },
    validateField(fieldName) {
        // 檢查 required, min, max, type
    },
    showError(input, message) {
        input.classList.add('is-invalid');
        // 顯示錯誤訊息
    }
};
```

---

## 統一 API 回應格式

### 後端 - includes/ApiResponse.php

```php
class ApiResponse {
    public static function success($data = null, $message = '') {
        return ['status' => 'ok', 'data' => $data, 'msg' => $message];
    }
    
    public static function error($message) {
        return ['status' => 'error', 'msg' => $message];
    }
    
    public static function validationError($errors) {
        return ['status' => 'validation_error', 'errors' => $errors];
    }
    
    public static function send($response) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
```

### 前端處理

```javascript
function handleApiResponse(response) {
    switch (response.status) {
        case 'ok':
            if (response.redirect) window.location.href = response.redirect;
            return true;
        case 'validation_error':
            showToast(response.msg, 'error');
            return false;
        case 'error':
        default:
            showToast(response.msg || '發生錯誤', 'error');
            return false;
    }
}
```

---

## 命名規範

| 類型 | 規範 | 範例 |
|------|------|------|
| Controller | 小寫_底線.php | `manage_videos.php` |
| Model 函式 | resource_動詞_名詞 | `video_get_by_id()` |
| Template | 小寫_底線.php | `edit_video.php` |
| CSS 類別 | 小寫-連字號 | `.btn-submit` |
| JS 函式 | camelCase | `handleApiResponse()` |
| 資料庫表 | 複數小寫 | `videos`, `announcements` |

---

## 實作 Checklist

建立新專案時，請依序完成：

1. [ ] 建立目錄結構
2. [ ] 設定 `includes/config.php` (資料庫連線)
3. [ ] 建立 `includes/auth.php` (認證函式)
4. [ ] 建立 `includes/Validator.php` (驗證規則)
5. [ ] 建立 `includes/ApiResponse.php` (API 回應)
6. [ ] 建立 `templates/partials/` (共用元件)
7. [ ] 建立 `assets/js/validators.js` (前端驗證)
8. [ ] 建立 `assets/css/forms.css` (表單樣式含驗證錯誤)
9. [ ] 建立第一個完整的 CRUD 功能作為範例
