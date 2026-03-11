# Portal MVC 重構計畫

## 📊 目標

| 項目 | 目前 | 目標 |
|------|------|------|
| 前後端分離 | 60% | 90% |
| MVC 完整度 | 50% | 85% |
| 可維護性 | 55% | 80% |

---

## 🔴 Phase 1: 建立 Model 層（優先）

### 目標
將資料庫操作從 Controller 抽離到 Model

### 建立檔案
```
app/Models/
├── Institution.php      # 機構 CRUD
├── User.php            # 使用者 CRUD
├── CohortDimension.php # 維度管理
└── BaseModel.php       # 基礎類別
```

---

## 🟡 Phase 2: 合併舊 API

### 遷移對照表
| 舊 API | 新 Controller | 狀態 |
|--------|---------------|------|
| manage_users.php (687行) | UserController | ⚠️ 部分 |
| manage_cohort.php | CohortController | ✅ 已遷移 |
| manage_category.php (815行) | CategoryController | ⚠️ 部分 |
| manage_course.php (610行) | CourseController | ❌ 待建立 |
| manage_dimensions.php | DimensionController | ❌ 待建立 |

---

## 🟢 Phase 3: 模板重構

### 問題
- `hospital_admin_course_create_page.php` (89KB) 混雜大量 JS

### 解決方案
- 將 JS 抽離到 `assets/js/pages/`
- 模板只保留 HTML 結構

---

## 🔵 Phase 4: 前端模組化

### 目標結構
```
assets/js/
├── core/           # API, utils, UI
├── modules/        # cohort, course, user
└── app.js          # 主入口
```

---

## 📋 執行順序

| 優先 | Phase | 預估時間 |
|------|-------|----------|
| 1 | Model 層 | 2-3 天 |
| 2 | 合併 API | 3-5 天 |
| 3 | 模板重構 | 2-3 天 |
| 4 | JS 模組化 | 2-3 天 |

---

## ✅ 已完成

- [x] 建立 `MoodleDatabase` Service
- [x] 重構 `CohortController.listWithDimensions()`
- [x] 移除 debug 日誌
- [x] 刪除垃圾檔案 (25+)
