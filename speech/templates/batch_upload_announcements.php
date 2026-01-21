<?php include __DIR__ . '/partials/header.php'; ?>

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <a href="manage_announcements.php"
                style="text-decoration:none; color: var(--text-primary); font-size: 1.2rem; font-weight: 500;">公告管理</a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                批次上傳公告</h2>
        </div>
        <div class="user-nav">
            <a href="manage_announcements.php" class="btn-admin"><i class="fa-solid fa-arrow-left"></i> 返回公告列表</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form"
        style="max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"
                style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"
                style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($results['fails'])): ?>
            <div
                style="background: #fff1f2; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #ffe4e6;">
                <h3 style="color: #be123c; margin-top: 0; font-size: 1.1rem;">以下資料上傳失敗：</h3>
                <ul style="margin: 10px 0 0 20px; color: #be123c;">
                    <?php foreach ($results['fails'] as $fail): ?>
                        <li>
                            <?= htmlspecialchars($fail) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="instruction-box"
            style="background: #f8fafc; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e2e8f0;">
            <h3 style="margin-top: 0; color: #475569; font-size: 1.1rem; margin-bottom: 15px;">
                <i class="fa-solid fa-circle-info"></i> 使用說明
            </h3>
            <div style="color: #64748b; line-height: 2; margin-bottom: 15px;">
                請準備 Excel (.xlsx) 或 CSV (.csv) 檔案，欄位順序如下（第一列標題列會被略過）：
                <a href="test_upload2.xlsx" download="範例檔案.xlsx"
                    style="display: inline-flex; align-items: center; background: #f0f9ff; color: var(--primary-color); padding: 2px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; border: 1px solid #bae6fd; margin-left: 8px; vertical-align: middle;">
                    <i class="fa-solid fa-file-excel" style="margin-right: 5px;"></i> 下載 Excel 範例
                </a>
            </div>
            <ol style="color: #64748b; padding-left: 20px; margin-bottom: 20px;">
                <li><strong>標題 (必填)</strong></li>
                <li>講者姓名</li>
                <li>單位/職稱</li>
                <li>活動日期 (支援 YYYY-MM-DD 或 Excel 日期格式)</li>
                <li>地點</li>
                <li>詳細內容</li>
                <li>院區 ID (<?php
                global $conn;
                $c_list = ['0=全部'];
                $c_res = $conn->query("SELECT * FROM campuses ORDER BY id");
                if ($c_res) {
                    while ($c = $c_res->fetch_assoc()) {
                        $c_list[] = $c['id'] . '=' . htmlspecialchars($c['name']);
                    }
                }
                echo implode(', ', $c_list);
                ?>)</li>
            </ol>
            <div style="font-size: 0.9rem; color: #94a3b8;">
                * 注意：批次上傳的公告預設<strong>不會顯示在首頁橫幅</strong>。如需顯示，請在上傳後手動編輯。
            </div>
        </div>

        <form action="batch_upload_announcements.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label style="display: block; margin-bottom: 10px; font-weight: 500; color: #334155;">選擇檔案</label>
                <div style="border: 2px dashed #cbd5e1; padding: 40px; text-align: center; border-radius: 12px; cursor: pointer; transition: all 0.2s;"
                    onclick="document.getElementById('fileInput').click()"
                    ondragover="this.style.borderColor='#3b82f6'; this.style.background='#eff6ff'; event.preventDefault();"
                    ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='transparent';"
                    ondrop="event.preventDefault(); document.getElementById('fileInput').files = event.dataTransfer.files; this.style.borderColor='#cbd5e1'; this.style.background='transparent'; this.querySelector('p').innerText = event.dataTransfer.files[0].name; this.querySelector('i').className = 'fa-solid fa-file-circle-check'; this.querySelector('i').style.color = '#22c55e';">

                    <i class="fa-solid fa-file-excel" style="font-size: 3rem; color: #10b981; margin-bottom: 15px;"></i>
                    <p style="color: #64748b; margin: 0;">點擊選擇或拖拉檔案至此</p>
                    <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 5px;">支援 .xlsx, .csv</p>

                    <input type="file" name="batch_file" id="fileInput" accept=".csv, .xlsx" style="display: none;"
                        onchange="this.parentNode.querySelector('p').innerText = this.files[0].name; this.parentNode.querySelector('i').className = 'fa-solid fa-file-circle-check'; this.parentNode.querySelector('i').style.color = '#22c55e';">
                </div>
            </div>

            <div class="form-actions" style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-submit"
                    style="background: var(--primary-color); color: white; border: none; padding: 12px 30px; border-radius: 50px; cursor: pointer; font-size: 1rem; font-weight: 600; flex: 1;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> 開始上傳
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>