# 院區管理員功能介紹

院區管理員（Hospital Admin）是 Portal 中權限最高的院區級角色。登入後看到 7 個功能 tab + 1 個外連報表。

---

## 1. 🏠 首頁

**做什麼：** 登入後的第一個畫面。顯示歡迎語、快捷操作按鈕（跳到課程管理、招生等）、以及統計卡片（總成員數、教師數、學生數）。

**為什麼用這些檔案：**

- [hospital_admin_home.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_home.php) — 畫面 HTML + 內嵌 JS，用 `fetch` 去打 API 拿統計數據，然後填到卡片上
- [StatsController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/StatsController.php) `dashboard()` — 後端算統計數據（查 Portal DB 的 users 表 + Moodle DB 的課程數）回傳 JSON
- 路由 `stats/dashboard`

---

## 2. 👥 成員管理

**做什麼：** 管理這個院區底下的所有使用者帳號。可以：
- 查看使用者清單（依角色篩選：學生/教師/管理員）
- 新建帳號（輸入帳號、姓名、密碼，同步建立 Moodle 帳號）
- 編輯帳號資料
- 重設密碼
- 變更角色（把學生升為開課教師、或降級）
- 批次匯入／刪除成員

**為什麼用這些檔案：**

- [hospital_admin_users.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_users.php) — 使用者列表 UI + 搜尋 + 角色篩選 + 新增/編輯 Modal
- [hospital_admin_members.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_members.php) — 成員管理子頁面（更細緻的批次操作 UI）
- [HospitalAdminController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/HospitalAdminController.php) — 處理帳號 CRUD：建帳號時同時呼叫 [moodle_api.php](file:///c:/Apache24/htdocs/includes/moodle_api.php) 的 `moodle_create_user()` 在 Moodle 同步建帳，變更角色時也要同步到 Moodle 的 role_assignments
- [MemberController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/MemberController.php) — 批次操作成員（批次更新角色、批次刪除等）
- Legacy proxy：`api/hospital_admin/manage_users.php` 等 — 舊版 API 入口，內部轉發到 V2

---

## 3. 📂 類別管理

**做什麼：** 管理 Moodle 的課程類別（category）樹狀結構。可以：
- 瀏覽類別樹（父子階層，例如「台北 > 護理部 > 基礎護理」）
- 新建子類別
- 編輯類別名稱
- 刪除空類別
- 設定「必修類別」（標記某個類別為必修 → 該類別下的課程可被標為必修課）
- 設定必修條件（指定該類別的必修課幾門需完成）
- 批次建立類別

**為什麼用這些檔案：**

- [hospital_admin_categories.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_categories.php) — 類別樹 UI + 麵包屑導航 + 新增/編輯 Modal + 必修設定面板
- [CategoryController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/CategoryController.php) — 核心邏輯。`listChildren()` 用 Moodle API 拉類別樹；`create()` 呼叫 Moodle API 建類別；`getMandatoryCategories()` 查 Portal DB 的 `portal_category_settings` 表；`saveMandatoryRequirements()` 存必修條件
- 牽涉的 DB 表：Moodle 的 `mdl_course_categories`（類別本體），Portal 的 `portal_category_settings`（必修標記）

---

## 4. 📚 課程管理

**做什麼：** 管理院區管轄類別下的所有課程。功能最多、最重的一塊：
- 瀏覽課程列表（依類別篩選，點類別進入下層看該類別的課程）
- 新建課程（完整表單：課程名稱、簡介、類別、Quill 富文本編輯器、批次建課 CSV 匯入）
- 編輯課程
- 刪除課程（二次確認，要打課程名稱才能刪）
- 批次刪除
- 切換可見性（公開/隱藏）
- 設定/取消必修（在管理員已標記為必修的類別下標記某課程為必修）
- 課程標籤管理（給課程貼標籤，方便分類與搜尋）
- 課程可見性控制（指定課程對特定群組可見）

**為什麼用這些檔案：**

- [hospital_admin_courses.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_courses.php)（1193 行）— 課程列表 UI + 所有 Modal（新增、刪除確認、批次刪除、設必修等）+ 內嵌的所有 JS 互動邏輯
- [hospital_admin_course_create_page.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_create_page.php) — 獨立的建課頁面（從課程管理點「新增課程」會跳到這裡），包含 Quill 編輯器、CSV 批次上傳、類別選擇器
- [hospital_admin_course_create.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_create.php) — 路由中介，`?page=course_create` 時 include 建課頁面
- [course_create.css](file:///c:/Apache24/htdocs/assets/css/pages/course_create.css) — 建課頁面的 CSS（已從 PHP 抽離）
- [CourseManager.js](file:///c:/Apache24/htdocs/assets/js/modules/CourseManager.js)（694 行）— 課程卡片渲染、教師課程列表（這個是學生/教師共用的模組，院區管理員的課程 JS 反而還是內嵌在 PHP 裡）
- [CourseController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/CourseController.php) — 所有課程 CRUD 邏輯。`list()` 查 Moodle 指定類別下的課程；`create()` 呼叫 Moodle API 建課；`delete()` 呼叫 Moodle API 刪課；`toggleVisible()` 改課程可見性；`setMandatory()` 存 `portal_mandatory_courses` 表
- [CourseTagController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/CourseTagController.php) — 課程標籤 CRUD（Portal DB 的 `portal_course_tags` 表）
- 牽涉的 DB 表：Moodle 的 `mdl_course`、`mdl_enrol`、`mdl_user_enrolments`；Portal 的 `portal_mandatory_courses`、`portal_course_tags`、`portal_course_visibility`

---

## 5. 👥 招生管理

**做什麼：** 把學生加入課程。流程是：
1. 先看到院區所有課程的列表（含學生人數）
2. 選一門課，點「招生」→ 跳到招生操作頁面
3. 在招生頁面可以：搜尋學生（依姓名/帳號/群組/標籤篩選）、勾選多人、一鍵批次加入
4. 也可以開啟「自我選修」→ 學生自己在 Moodle 裡選這門課

**為什麼用這些檔案：**

- [hospital_admin_enrollment.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_enrollment.php)（426 行）— 課程選擇列表 UI，每門課旁邊有「招生」按鈕
- [hospital_admin_course_enrol_page.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_enrol_page.php) — 招生操作頁面（搜尋學生、勾選、批次加入等完整 UI）
- [hospital_admin_course_enrol.php](file:///c:/Apache24/htdocs/templates/hospital_admin_course_enrol.php) — 路由中介，`?page=course_enrol&course_id=X`
- [CourseController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/CourseController.php) — `enrolUsers()` 呼叫 Moodle API 的 `enrol_manual_enrol_users` 把學生加進課程；`batchEnrol()` 批次操作；`enableSelfEnrol()` 開啟/關閉自我選修（操作 Moodle 的 enrol plugin）

---

## 6. 🧩 群組管理

**做什麼：** 管理 Moodle 的群組（cohort）。群組是用來分類學生的，例如「護理部」「醫師」「2024 新進」。可以：
- 瀏覽群組列表（依維度分類：職類、所屬、屬性等）
- 階層導航（群組可以有子群組）
- 新建群組（選擇所屬維度）
- 刪除群組
- 查看/管理成員（加入/移除成員）
- 搜尋使用者加入群組
- 篩選器（依類別、位置、屬性、標籤交叉篩選成員）

**為什麼用這些檔案：**

- [hospital_admin_cohorts.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_cohorts.php) — 群組管理的 HTML 骨架 + Modal（新增/編輯/刪除群組、成員管理面板）
- [cohorts.js](file:///c:/Apache24/htdocs/assets/js/pages/cohorts.js)（2094 行）— JS 已抽離到獨立檔案。超大檔案，處理：群組列表渲染、階層導航、成員面板、新增/刪除群組、維度篩選、搜尋使用者、批次操作
- [CohortController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/CohortController.php) — 群組 CRUD。`list()` 呼叫 Moodle API 拉群組；`getMembers()` 查群組成員；`addMember()` / `removeMember()` 呼叫 Moodle API 加減人；`searchUsers()` 搜尋可被加入的使用者
- [DimensionController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/DimensionController.php) — 維度管理（Portal DB 的 `portal_dimensions`、`portal_dimension_cohorts` 表）。維度是群組的上層分類概念（職類、所屬、屬性是三個維度）
- 牽涉的 DB 表：Moodle 的 `mdl_cohort`、`mdl_cohort_members`；Portal 的 `portal_dimensions`、`portal_dimension_cohorts`

---

## 7. 🏷️ 標籤管理

**做什麼：** 管理自訂標籤（tag）。標籤可以貼在使用者或課程上，用來做更靈活的分類。可以：
- 查看所有標籤（分模板標籤 vs 自訂標籤）
- 新建標籤（設定名稱、顏色、描述）
- 編輯/刪除標籤
- 管理模板標籤（預設標籤集）

**為什麼用這些檔案：**

- [hospital_admin_tags.php](file:///c:/Apache24/htdocs/templates/tabs/hospital_admin_tags.php) — 標籤管理 UI 骨架 + Modal
- [tags.js](file:///c:/Apache24/htdocs/assets/js/modules/tags.js)（326 行）— JS 已抽離。處理標籤 CRUD、管理 Modal、標籤選擇器元件
- [TagController.php](file:///c:/Apache24/htdocs/app/Controllers/Api/TagController.php) — 標籤 CRUD。`list()` 查 Portal DB 的 `portal_tags`；`create()` 新增標籤；`templates()` 查模板標籤
- 牽涉的 DB 表：Portal 的 `portal_tags`、`portal_tag_templates`、`portal_user_tags`

---

## 8. 📊 報表

**做什麼：** 目前是直接跳到 Moodle 的內建報表系統（`/report/log/index.php`），沒有 Portal 自己的報表頁面。dashboard.php 裡有 include 一個空的 `hospital_admin_reports.php`，預留未來開發。

---

## 共用基礎設施（每個功能都用到）

| 檔案 | 為什麼需要 |
|------|-----------|
| [api/v2/index.php](file:///c:/Apache24/htdocs/api/v2/index.php) | 所有 API 的統一入口，用 `?route=xxx` 分發到對應 Controller |
| [api/hospital_admin/](file:///c:/Apache24/htdocs/api/hospital_admin/)（16 檔） | 舊版 API proxy，把舊的 action 參數轉成 V2 route 後 include V2 入口 |
| [ApiClient.js](file:///c:/Apache24/htdocs/assets/js/modules/ApiClient.js) | 前端統一的 API 呼叫工具（封裝 fetch + 錯誤處理） |
| [Controller.php](file:///c:/Apache24/htdocs/core/Controller.php) | 所有 Controller 的父類，提供 `requireHospitalAdmin()` 權限檢查 |
| [moodle_api.php](file:///c:/Apache24/htdocs/includes/moodle_api.php)（1297 行） | 所有跟 Moodle 互動的底層函式（用 cURL 打 Moodle Web Service + 直接查 Moodle DB） |
| [header.php](file:///c:/Apache24/htdocs/templates/header.php) L31-52 | 注入 `window.PortalConfig`（API base URL、使用者資訊、角色），JS 模組都靠它知道打哪個 API |
