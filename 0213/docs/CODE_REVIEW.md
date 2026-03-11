# Portal 專案 Code Review 報告

## 📊 整體評估

| 項目 | 評分 | 說明 |
|------|------|------|
| **前後端分離** | 3/10 | PHP 模板嵌入大量 JS，無 API 層統一 |
| **MVC 架構** | 5/10 | 有基礎框架但未完全採用 |
| **可讀性** | 6/10 | 命名清晰但單檔過長 |
| **可修改性** | 4/10 | 改動風險高，高耦合 |
| **可擴展性** | 4/10 | 難以新增功能不動到現有代碼 |
| **效能** | 5/10 | 有 N+1 問題，但有部分優化 |

---

## 🏗️ 架構問題

### 1. 前後端分離不足 (Critical)

**現況**：
```
templates/tabs/hospital_admin_users.php (1382 行)
├── PHP 後端邏輯
├── HTML 結構
├── CSS 樣式 (<style> 標籤)
└── JavaScript 邏輯 (<script> 標籤 500+ 行)
```

**問題**：
- 每個 PHP 模板都包含數百行 inline JavaScript
- 無法獨立測試前端或後端
- 無法利用前端工具鏈 (bundler, minifier, linting)

**建議架構**：
```
├── public/
│   ├── js/
│   │   ├── modules/
│   │   │   ├── user-management.js
│   │   │   ├── course-enrollment.js
│   │   │   └── category-settings.js
│   │   └── app.js
│   └── css/
│       └── components/
├── api/v2/
│   └── (RESTful endpoints)
└── templates/
    └── (純 HTML 結構)
```

---

### 2. 雙軌 API 架構 (Anti-pattern)

專案同時存在兩種 API 風格：

| 類型 | 位置 | 問題 |
|------|------|------|
| 程序式 | `api/hospital_admin/*.php` | 16 個獨立檔案，重複的權限檢查、DB 連線 |
| OOP | `app/Controllers/Api/*.php` | 7 個 Controller，使用基礎類別 |

**範例：程序式重複代碼** (`manage_users.php`):
```php
// 每個 API 檔案都重複這段
session_start();
if (empty($_SESSION['is_hospital_admin'])) {
    throw new Exception('無權限存取');
}
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset("utf8mb4");
```

**建議**：統一使用 Controller 模式，透過 Router 統一入口

---

### 3. MVC 不完整

**現況**：
```
core/
├── Controller.php  ✅ 有基礎類別
├── Model.php       ⚠️  存在但幾乎未使用
├── Database.php    ✅ Singleton 模式
└── Router.php      ⚠️  存在但未啟用

app/Models/         ❌ 只有 1 個檔案
```

**問題**：
- 業務邏輯散落在 Controller 和 API 檔案中
- 無 Service Layer 抽象業務邏輯
- Model 層形同虛設

---

## ⚡ 效能問題

### 1. N+1 查詢問題 (Critical)

**範例** (`manage_users.php` Line 378-411):
```php
// 對每個標籤執行單獨的 INSERT/SELECT
foreach ($tag_array as $raw_tag) {
    // SELECT 檢查標籤是否存在
    $t_stmt = $moodle_conn->prepare("SELECT id FROM mdl_tag WHERE name = ?");
    // ...
    if ($existing_tag) {
        $tag_id = $existing_tag['id'];
    } else {
        // INSERT 新標籤
        $ins_stmt = $moodle_conn->prepare("INSERT INTO mdl_tag ...");
    }
    // INSERT 標籤關聯
    $inst_stmt = $moodle_conn->prepare("INSERT INTO mdl_tag_instance ...");
}
```

**影響**：如果使用者有 N 個標籤，會執行 3N 次查詢

**建議**：
```php
// 批次查詢所有標籤
$existing_tags = $this->getTagsByNames($tag_array);
// 一次 INSERT 所有新標籤
$this->batchInsertTags($new_tags);
// 一次 INSERT 所有關聯
$this->batchInsertTagInstances($tag_instances);
```

---

### 2. 重複的 DB 連線

**範例** (`manage_users.php` Line 545-565):
```php
// 同一個函數中多次建立連線
$local_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);  // 第2次連線
$moodle_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle'); // 第3次連線

// 已有 $conn 可用，卻又新建 $local_conn
```

**建議**：使用 `Database::getInstance()` 單例模式

---

## 🔒 Race Condition 風險

### 1. 標籤創建 Race Condition

**範例** (`manage_users.php` Line 386-403):
```php
// 檢查標籤是否存在
$t_stmt = $moodle_conn->prepare("SELECT id FROM mdl_tag WHERE name = ?");
// ... 執行查詢 ...

if ($existing_tag) {
    $tag_id = $existing_tag['id'];
} else {
    // 🔴 TOCTOU (Time-of-check to time-of-use) Race Condition
    // 另一個請求可能在這個瞬間創建同名標籤
    $ins_stmt = $moodle_conn->prepare("INSERT INTO mdl_tag ...");
}
```

**建議**：使用 `INSERT ... ON DUPLICATE KEY UPDATE` 或 database lock

---

### 2. 使用者創建無事務保護

**範例** (`manage_users.php` Line 492-523):
```php
// 1. 寫入本地資料庫
$stmt = $conn->prepare("INSERT INTO users ...");
$stmt->execute();
$local_id = $conn->insert_id;

// 2. Moodle 建立帳號
$create_res = call_moodle(..., 'core_user_create_users', ...);

if (isset($create_res['exception'])) {
    // ⚠️ Rollback 只靠單獨 DELETE，不是事務
    $conn->query("DELETE FROM users WHERE id = $local_id");
    throw new Exception('Moodle 建立失敗');
}

// 🔴 如果 Moodle 創建成功但後續步驟(加群組/標籤)失敗，
// 會導致資料不一致
```

**建議**：
```php
$conn->begin_transaction();
try {
    // 所有操作
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    // 呼叫 Moodle API 刪除用戶
    throw $e;
}
```

---

## 📝 可讀性/可維護性問題

### 1. 單檔過長

| 檔案 | 行數/大小 | 建議 |
|------|------|------|
| `manage_users.php` | 687 行 | 拆分為 UserController |
| `manage_category.php` | 35KB | 拆分為 CategoryService |
| `hospital_admin_users.php` | 1382 行 | 分離 JS 到獨立檔案 |

### 2. 硬編碼魔術數字

```php
// 魔術數字
WHERE ctx.contextlevel = 40   // 什麼是 40?
WHERE contextlevel = 30       // 什麼是 30?

// 建議定義常數
const CONTEXT_COURSECAT = 40;
const CONTEXT_USER = 30;
```

### 3. 中英混用變數名

```php
$dim_職類 = [];
$dim_所屬 = [];
$dim_屬性 = [];
```

**建議**：統一使用英文或拼音

---

## ✅ 優點

1. **Database Singleton**：`Database.php` 正確實作單例模式
2. **基礎 Controller**：提供 `input()`, `inputInt()` 等輔助方法
3. **權限檢查封裝**：`requireHospitalAdmin()` 方便重用
4. **批次查詢優化**：`manage_users.php` 的 `list` action 有做批次查詢減少 N+1

---

## 🛠️ 優先改善建議

### Phase 1: 關鍵修復 (1-2 週)
1. **事務處理**：為用戶創建/更新添加 transaction
2. **Race Condition**：使用 `INSERT ON DUPLICATE KEY` 修復標籤創建
3. **統一 DB 連線**：移除重複的 `new mysqli()`，使用 singleton

### Phase 2: 架構整理 (2-4 週)
1. **統一 API 入口**：將程序式 API 遷移到 Controller
2. **分離前端 JS**：將 inline JS 抽出為獨立模組
3. **建立 Service Layer**：抽取業務邏輯

### Phase 3: 長期改善 (1-2 月)
1. **引入 Repository Pattern**：統一資料存取
2. **前端框架**：考慮 Vue/React 重構前端
3. **API 版本化**：建立 `/api/v2/` RESTful API

---

## 📋 總結

這是一個**功能導向**開發的專案，為了快速交付功能而累積了技術債。核心問題是**混合架構**和**高耦合**。建議優先解決 Race Condition 和事務問題，然後逐步重構為更清晰的分層架構。

---
*Generated: 2026-02-09*
