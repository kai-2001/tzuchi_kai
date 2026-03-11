# 開課教師功能 — 完整實作計畫 v2

## 目標

將開課教師從「2 個簡易 tab」升級為完整的教學管理平台：

| Tab | 功能 | 權限範圍 |
|-----|------|----------|
| 🏠 首頁 | 統計儀表板 | — |
| 📚 課程管理 | CRUD + 可見性 + 必修標記 | **限定被指派的類別及其子類別** |
| 👥 招生管理 | 加人/開放選修，支援群組/屬性/標籤篩選 | **不受類別限制**，可操作自己所有課程 |
| 📊 報表 | 空介面預留 | — |

---

## 核心設計：多類別指派 + 階層繼承

### 現況

```
login → sync_user_moodle_role() → moodle_get_user_role_context()
  → 查 Moodle mdl_role_assignments WHERE shortname='coursecreator'
  → 只回傳「一個」category_id
  → 存入 $_SESSION['management_category_id']
```

### 問題

1. Moodle 允許一個人被指派到**多個類別**的 coursecreator
2. 現有 code 只取第一個，忽略其餘
3. 沒有階層繼承（被指派到「台北」應該能開「台北/護理部」下的課）

### 改造方案

```
login → sync_user_moodle_role() → moodle_get_user_role_context()
  → 收集「所有」coursecreator 的 category_ids
  → 回傳 category_ids: [5, 12, 0]  (0=系統層級=全部)
  → 存入 $_SESSION['coursecreator_category_ids']
```

前端查課時：
```
courses/my_list API 接收 category_ids
  → 用 Moodle 類別樹展開所有子類別 ID
  → 查 mdl_course WHERE category IN (展開後的所有 ID)
  → 回傳結果
```

---

## 需變更的檔案

### Phase 1：後端 — 角色同步改造

#### [MODIFY] [moodle_api.php](file:///c:/Apache24/htdocs/includes/moodle_api.php) [moodle_get_user_role_context()](file:///c:/Apache24/htdocs/includes/moodle_api.php#1176-1298)

```diff
- $found_teacher = false;
- $target_cat = 0;
+ $teacher_cats = [];   // 收集所有 coursecreator 類別
  
  while ($ra = $assignments_res->fetch_assoc()) {
      // ... hospital_admin 邏輯不變 ...
      if ($ra['shortname'] === 'coursecreator') {
-         if (!$found_teacher) {
-             $found_teacher = true;
-             $target_cat = (...);
-         }
+         if ($ra['contextlevel'] == 10) {
+             $teacher_cats = [0]; // 系統層級 = 全部
+             break;
+         } else {
+             $teacher_cats[] = (int) $ra['instanceid'];
+         }
      }
  }
  
  // 回傳新增 teacher_category_ids
+ $result['teacher_category_ids'] = $teacher_cats;
```

#### [MODIFY] [auth.php](file:///c:/Apache24/htdocs/includes/auth.php) L104, L385

```diff
  $_SESSION['management_category_id'] = $sync_result['category_id'];
+ $_SESSION['coursecreator_category_ids'] = $sync_result['teacher_category_ids'] ?? [];
```

---

### Phase 2：後端 — 課程管理 API

#### [MODIFY] [CourseController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/CourseController.php)

新增方法：

| 方法 | 路由 | 說明 |
|------|------|------|
| `myList()` | `courses/my_list` | 取教師被指派類別下的所有課程 + 學生人數 |
| `mySetMandatory()` | `courses/my_set_mandatory` | 教師標記/取消必修（限在已設為必修的類別內） |

**`myList()` 邏輯：**
```
1. requireCourseCreator()  // 新增權限檢查方法
2. 取 $_SESSION['coursecreator_category_ids']
3. 如果包含 0 → 不限類別
4. 否則 → 展開每個 category_id 的子類別樹
5. 查 Moodle: SELECT * FROM mdl_course WHERE category IN (...)
6. 加入每門課的 enrolledcount (JOIN mdl_enrol + mdl_user_enrolments)
7. 加入必修狀態 (LEFT JOIN portal_mandatory_courses)
8. 回傳
```

**`mySetMandatory()` 邏輯：**
```
1. requireCourseCreator()
2. 檢查 course 所在 category 是否在教師被指派的類別範圍內
3. 檢查 category 是否已被管理員設為必修類別 (portal_category_settings.is_mandatory_category = 1)
4. 執行 INSERT/UPDATE portal_mandatory_courses
```

#### [MODIFY] [Controller.php](file:///c:/Apache24/htdocs/core/Controller.php)

新增權限檢查方法：
```php
protected function requireCourseCreator(): void {
    if (empty($_SESSION['is_coursecreator'])) {
        ApiResponse::forbidden('需要開課教師權限');
        exit;
    }
}
```

#### [MODIFY] [api/v2/index.php](file:///c:/Apache24/htdocs/api/v2/index.php)

新增路由：
```php
'courses/my_list'             => ['CourseController', 'myList'],
'courses/my_set_mandatory'    => ['CourseController', 'mySetMandatory'],
'courses/my_get_mandatory_categories' => ['CategoryController', 'getTeacherMandatoryCategories'],
```

---

### Phase 3：前端 — 首頁儀表板

#### [MODIFY] [teacher_home.php](file:///c:/Apache24/htdocs/templates/tabs/teacher_home.php)

改造為：
```
┌──────────────────────────────────────────────────┐
│  👨‍🏫 教學管理控制台                                   │
│  歡迎回來，OOO 老師                                  │
│  [📚 課程管理]  [👥 招生管理]  [📊 報表]              │
├───────────┬───────────┬───────────┬───────────────┤
│  📚 我的課程 │  👥 總學生  │  👁 公開   │  ⭐ 必修課程  │
│     --     │     --    │    --    │     --       │
└───────────┴───────────┴───────────┴───────────────┘
│  📂 管理類別：台北 > 護理部、台北 > 醫學部            │
└──────────────────────────────────────────────────┘
```

- 統計從 `courses/my_list` API 計算
- 顯示被指派的類別名稱（讓教師知道自己的管轄範圍）

---

### Phase 4：前端 — 課程管理

#### [MODIFY] [teacher_management.php](file:///c:/Apache24/htdocs/templates/tabs/teacher_management.php)

從 29 行改造為完整 CRUD（~250L），參考 [hospital_admin_courses.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_courses.php) 簡化版：

```
┌──────────────────────────────────────────────────┐
│  📚 課程管理                                        │
│  [🔍 搜尋...]  [➕ 新增課程]  [🔄 重新整理]          │
├──────────────────────────────────────────────────┤
│  📘 課程A   📂護理部  👥25人  👁公開  ⭐必修         │
│      [✏️ 編輯] [👁 切換] [⭐ 設必修] [🗑️ 刪除]     │
│  📘 課程B   📂醫學部  👥12人  🔒隱藏                │
│      [✏️ 編輯] [👁 切換] [🗑️ 刪除]                 │
└──────────────────────────────────────────────────┘
```

- **新增課程**：跳到 `index.php?page=course_create`（復用院區管理員建課頁，加教師類別限制）
- **切換可見性**：呼叫 `courses/toggle_visible`
- **設/取消必修**：呼叫 `courses/my_set_mandatory`（僅在管理員已設為必修的類別下才顯示此按鈕）
- **刪除**：確認對話框 → 呼叫 `courses/delete`

---

### Phase 5：前端 — 招生管理

#### [NEW] [teacher_enrollment.php](file:///c:/Apache24/htdocs/templates/tabs/teacher_enrollment.php)

~200L，參考 [hospital_admin_enrollment.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_enrollment.php)：

```
┌──────────────────────────────────────────────────┐
│  👥 招生管理                                        │
│  [🔍 搜尋課程...]  [🔄]                             │
├──────────────────────────────────────────────────┤
│  📘 課程A  👥25人   [➕ 招生]                        │
│  📘 課程B  👥12人   [➕ 招生]                        │
└──────────────────────────────────────────────────┘

   點 [招生] → 跳到 /index.php?page=course_enrol&course_id=X
```

> [!NOTE]
> 招生管理**不受類別限制** — 教師可以對自己有教師角色的所有課程做招生。
> 這裡的課程列表用 Moodle API 查「該教師有 editingteacher 角色的課程」。

招生頁面（[course_enrol_page.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_enrol_page.php)）**已有完整功能**：
- 群組（cohort）篩選
- 屬性篩選
- 標籤篩選
- 批次加入 / 開放自我選修

---

### Phase 6：前端 — 報表（空殼）

#### [NEW] [teacher_reports.php](file:///c:/Apache24/htdocs/templates/tabs/teacher_reports.php)

~30L，空介面預留：
```
┌──────────────────────────────────────────────────┐
│  📊 報表                                           │
│  🚧 功能開發中，敬請期待                              │
└──────────────────────────────────────────────────┘
```

---

### Phase 7：導覽列 & 路由

#### [MODIFY] [header.php](file:///c:/Apache24/htdocs/templates/header.php) L120-129

```diff
 <!-- 開課教師導覽列 -->
 <a onclick="showHome()" class="pg-link">
     <i class="fas fa-home"></i> 個人主頁
 </a>
 <a onclick="showTab('course-management')" class="pg-link">
     <i class="fas fa-chalkboard"></i> 課程管理
 </a>
+<a onclick="showTab('teacher-enrollment')" class="pg-link">
+    <i class="fas fa-user-plus"></i> 招生管理
+</a>
+<a onclick="showTab('teacher-reports')" class="pg-link">
+    <i class="fas fa-chart-line"></i> 報表
+</a>
-<a href="#" onclick="goToMoodle('<?php echo $add_course_url; ?>')" class="pg-link">
-    <i class="fas fa-plus-circle"></i> 新增課程
-</a>
```

#### [MODIFY] [dashboard.php](file:///c:/Apache24/htdocs/templates/dashboard.php) L22-25

```diff
 <?php elseif ($is_coursecreator): ?>
     <?php include 'tabs/teacher_home.php'; ?>
     <?php include 'tabs/teacher_management.php'; ?>
+    <?php include 'tabs/teacher_enrollment.php'; ?>
+    <?php include 'tabs/teacher_reports.php'; ?>
 <?php else: ?>
```

---

## 關於必修類別設定是否下放

### 分析

| 方案 | 優點 | 風險 |
|------|------|------|
| ❌ 不下放 | 政策統一、管理員全權控制 | 教師每次要設必修都要找管理員 |
| ✅ 完全下放 | 教師方便 | 教師可能亂設，多位教師互相衝突 |
| ⚡ **折中：只下放「課程標記」，不下放「類別設定」** | 最佳平衡 | — |

### 建議方案：折中

```
管理員 → 設定「哪些類別是必修類別」 (portal_category_settings)
教師   → 在「已是必修的類別」內，標記「哪門課是必修課」 (portal_mandatory_courses)
```

具體來說：
- 管理員把「護理部」設為必修類別 ✅
- 教師在「護理部」下建了課程 A、B、C
- 教師可以把 A 標為必修 ⭐，B、C 保持選修
- 教師**不能**把「護理部」設為非必修（這是管理員的事）
- 教師**不能**在非必修類別裡標記必修課程

> [!IMPORTANT]
> 此方案已反映在上方 Phase 2 的 `mySetMandatory()` 邏輯中。

---

## 執行順序

| 步驟 | 工作 | 預估 | 依賴 |
|:----:|------|:----:|:----:|
| 1 | 後端：改造角色同步（多類別） | ~30L | — |
| 2 | 後端：[Controller.php](file:///c:/Apache24/htdocs/core/Controller.php) 加 `requireCourseCreator()` | ~10L | — |
| 3 | 後端：`CourseController.myList()` + 路由 | ~80L | 1 |
| 4 | 後端：`CourseController.mySetMandatory()` + 路由 | ~40L | 1 |
| 5 | 前端：[teacher_home.php](file:///c:/Apache24/htdocs/templates/tabs/teacher_home.php) 儀表板 | ~80L | 3 |
| 6 | 前端：[teacher_management.php](file:///c:/Apache24/htdocs/templates/tabs/teacher_management.php) 課程 CRUD | ~250L | 3,4 |
| 7 | 前端：`teacher_enrollment.php` 招生管理 | ~200L | 3 |
| 8 | 前端：`teacher_reports.php` 空殼 | ~30L | — |
| 9 | 前端：[header.php](file:///c:/Apache24/htdocs/templates/header.php) + [dashboard.php](file:///c:/Apache24/htdocs/templates/dashboard.php) 導覽 | ~15L | 5-8 |
| 10 | 測試驗證 | — | 全部 |

---

## 驗證計畫

1. 教師登入 → Session 包含 `coursecreator_category_ids` 陣列
2. 首頁 → 正確顯示統計 + 管轄類別名稱
3. 課程管理 → 只顯示被指派類別(及子類別)下的課程
4. 在必修類別下 → 可以標記課程為必修；**非必修類別下 → 按鈕不顯示**
5. 招生管理 → 列出所有有教師角色的課程（不受類別限制）
6. 點招生 → 跳到招生頁面，群組/屬性/標籤篩選正常
7. 非教師帳號 → API 回 403 禁止
