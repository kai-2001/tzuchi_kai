<?php include __DIR__ . '/partials/header.php'; ?>

<?php
$navbar_mode = 'simple';
$page_title = '批次上傳公告';
$custom_breadcrumbs = [
    ['label' => '公告管理', 'url' => 'manage_announcements.php']
];
$nav_actions = [
    ['label' => '返回列表', 'url' => 'manage_announcements.php', 'icon' => 'fa-solid fa-arrow-left']
];
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($results['fails'])): ?>
            <div class="alert alert-danger" style="display: block;">
                <h3 style="color: #be123c; margin-top: 0; font-size: 1.1rem;">以下資料上傳失敗：</h3>
                <ul style="margin: 10px 0 0 20px; color: #be123c;">
                    <?php foreach ($results['fails'] as $fail): ?>
                        <li><?= htmlspecialchars($fail) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="instruction-box">
            <h3><i class="fa-solid fa-circle-info"></i> 使用說明</h3>
            <div class="content">
                請準備 Excel (.xlsx) 或 CSV (.csv) 檔案，欄位順序如下（第一列標題列會被略過）：
                <a href="test_upload2.xlsx" download="範例檔案.xlsx"
                    style="display: inline-flex; align-items: center; background: #f0f9ff; color: var(--primary-color); padding: 2px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; border: 1px solid #bae6fd; margin-left: 8px; vertical-align: middle;">
                    <i class="fa-solid fa-file-excel" style="margin-right: 5px;"></i> 下載 Excel 範例
                </a>
            </div>
            <ol>
                <li><strong>標題 (必填)</strong></li>
                <li>講者姓名</li>
                <li>服務單位</li>
                <li>職務</li>
                <li>活動日期 (支援 YYYY-MM-DD 或 Excel 日期格式)</li>
                <li>地點</li>
                <li>詳細內容</li>
                <li>院區 ID (<?php
                if (is_campus_admin()) {
                    echo "<span style='color: #0891b2; font-weight: 600;'>💡 此欄位可填空或省略，系統會自動設為您的所屬院區</span>";
                } else {
                    global $conn;
                    $c_list = ['0=全部'];
                    $c_res = $conn->query("SELECT * FROM campuses ORDER BY id");
                    if ($c_res) {
                        while ($c = $c_res->fetch_assoc()) {
                            $c_list[] = $c['id'] . '=' . htmlspecialchars($c['name']);
                        }
                    }
                    echo implode(', ', $c_list);
                }
                ?>)</li>
            </ol>
            <div style="font-size: 0.9rem; color: #94a3b8; margin-top: 20px;">
                * 注意：批次上傳的公告預設<strong>不會顯示在首頁橫幅</strong>。如需顯示，請在上傳後手動編輯。
            </div>
        </div>

        <form action="batch_upload_announcements.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>選擇檔案</label>
                <div class="file-dropzone" onclick="document.getElementById('fileInput').click()"
                    ondragover="this.style.borderColor='#3b82f6'; this.style.background='#eff6ff'; event.preventDefault();"
                    ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='transparent';"
                    ondrop="event.preventDefault(); document.getElementById('fileInput').files = event.dataTransfer.files; this.style.borderColor='#cbd5e1'; this.style.background='transparent'; this.querySelector('p').innerText = event.dataTransfer.files[0].name; this.querySelector('i').className = 'fa-solid fa-file-circle-check'; this.querySelector('i').style.color = '#22c55e';">

                    <i class="fa-solid fa-file-excel"></i>
                    <p>點擊選擇或拖拉檔案至此</p>
                    <p class="subtitle">支援 .xlsx, .csv</p>

                    <input type="file" name="batch_file" id="fileInput" accept=".csv, .xlsx" style="display: none;"
                        onchange="this.parentNode.querySelector('p').innerText = this.files[0].name; this.parentNode.querySelector('i').className = 'fa-solid fa-file-circle-check'; this.parentNode.querySelector('i').style.color = '#22c55e';">
                </div>
            </div>

            <div class="form-actions" style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-submit btn-pill" style="flex:1;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> 開始上傳
                </button>
            </div>
        </form>
    </div>
</div>


<?php include __DIR__ . '/partials/footer.php'; ?>