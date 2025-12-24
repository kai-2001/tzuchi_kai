# 如何測試非同步載入 (Async Loading)

## 📋 測試步驟

### 方法 1: 使用普通使用者帳號測試

1. **登出管理員帳號**
   - 訪問: `http://localhost/index.php?logout=1`

2. **使用普通使用者登入**
   - 帳號: `user1` (或其他非管理員帳號)
   - 密碼: (你設定的密碼)

3. **觀察載入過程**
   - 頁面應該**立即顯示** (< 500ms)
   - 會看到 **loading skeleton** (灰色閃爍動畫)
   - 幾秒後資料會**淡入**填充

4. **檢查 Console**
   - 打開瀏覽器 DevTools (F12)
   - Console 應顯示: `✅ Moodle 資料載入完成`

5. **檢查 Network**
   - Network 標籤頁應顯示:
     - `index.php` - 很快 (< 500ms)
     - `get_moodle_data.php` - 較慢 (2-5s,但在背景執行)

---

### 方法 2: 清除快取測試

如果你已經以普通使用者登入:

```
訪問: http://localhost/index.php?clear_cache=1
然後訪問: http://localhost/index.php
```

觀察:
- 頁面立即顯示
- Loading skeleton 出現
- 資料載入完成後淡入

---

## 🎯 預期結果

### ✅ 成功指標

1. **頁面載入時間**
   - `index.php` 載入: < 500ms
   - 可立即看到頁面結構

2. **Loading 狀態**
   - 公告區: 顯示 loading skeleton
   - 課程列表: 顯示 loading skeleton  
   - 必修進度: 顯示 loading skeleton

3. **資料填充**
   - 2-5 秒後資料出現
   - 有淡入動畫效果
   - 所有區塊正常顯示

4. **Console 訊息**
   ```
   ✅ Moodle 資料載入完成
   ```

### ❌ 如果看到錯誤

如果看到紅色錯誤訊息:
- 檢查 `api/get_moodle_data.php` 是否存在
- 檢查 Console 是否有 JavaScript 錯誤
- 檢查 Network tab 看 API 請求狀態

---

## 📊 性能對比

### 之前 (Server-side 平行請求)
- 頁面載入: **5-10 秒**
- 使用者看到空白畫面等待

### 現在 (Async 非同步載入)
- 頁面載入: **< 0.5 秒** ⚡
- 立即看到頁面結構
- 資料背景載入 (2-5 秒)

### 總改進
- **感知速度提升: 10-20 倍**
- **實際資料載入時間: 不變** (但不會阻塞頁面顯示)

---

## 🔍 除錯技巧

### 檢查 async_loader.php 是否載入

打開 DevTools > Console,輸入:
```javascript
typeof loadMoodleData
```

應該顯示: `"undefined"` (因為在 IIFE 內部)

但可以看到 loading skeleton CSS 已套用

### 手動觸發載入

在 Console 輸入:
```javascript
fetch('/api/get_moodle_data.php')
  .then(r => r.json())
  .then(console.log)
```

應該會顯示JSON資料

---

## ℹ️ 注意事項

1. **管理員帳號不會觸發非同步載入**
   - 因為管理員不需要 Moodle 資料
   - 直接顯示管理面板

2. **首次載入會較慢**
   - 因為快取是空的
   - 後續 5 分鐘內會使用快取,非常快

3. **離線狀態**
   - 如果 Moodle API 無法連線
   - 會顯示錯誤訊息和重新整理按鈕
