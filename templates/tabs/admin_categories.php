<div id="section-admin-categories" class="page-section">
    <div class="hero-section">
        <i class="fas fa-folder-tree hero-icon"></i>
        <h1>類別與群組管理</h1>
        <p>系統管理員專用工具：快速建立部門類別與自動化群組</p>
    </div>

    <!-- 類別管理專區 -->
    <div class="admin-tools-container" style="max-width: 1000px; margin: 0 auto; text-align: left; padding: 20px;">

        <?php
        // 取得所有院區列表
        $all_institutions = [];
        // 注意：這裡假設 $conn 已經在外層建立，或是重新建立
        if (!isset($conn) || $conn->connect_error) {
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            $conn->set_charset("utf8mb4");
        }

        if ($conn && !$conn->connect_error) {
            $res = $conn->query("SELECT * FROM institutions ORDER BY id ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $all_institutions[] = $row;
                }
            }
        }
        ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- 單獨新增 -->
            <div class="widget-card">
                <div class="widget-header">
                    <h3><i class="fas fa-plus"></i> 新增單一部門類別</h3>
                </div>
                <div class="widget-body">
                    <form id="single-cat-form" onsubmit="createCategory(event, 'single')">
                        <div class="form-group">
                            <label>選擇院區</label>
                            <select name="institution_id" class="form-select" required>
                                <option value="">請選擇...</option>
                                <?php foreach ($all_institutions as $inst): ?>
                                    <option value="<?php echo $inst['id']; ?>"><?php echo h($inst['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>部門名稱</label>
                            <input type="text" name="category_name" class="form-input" placeholder="例如：教學部" required>
                        </div>
                        <button type="submit" class="btn-primary" style="width: 100%;">
                            <i class="fas fa-check"></i> 立即建立
                        </button>
                    </form>
                </div>
            </div>

            <!-- 批次新增 -->
            <div class="widget-card">
                <div class="widget-header">
                    <h3><i class="fas fa-layer-group"></i> 批次新增部門類別</h3>
                </div>
                <div class="widget-body">
                    <form id="batch-cat-form" onsubmit="createCategory(event, 'batch')">
                        <div class="form-group">
                            <label>選擇院區</label>
                            <select name="institution_id" class="form-select" required>
                                <option value="">請選擇...</option>
                                <?php foreach ($all_institutions as $inst): ?>
                                    <option value="<?php echo $inst['id']; ?>"><?php echo h($inst['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>部門名稱清單 (一行一個)</label>
                            <textarea name="category_names" class="form-input" rows="5"
                                placeholder="內科部&#10;外科部&#10;婦產部..." required></textarea>
                        </div>
                        <button type="submit" class="btn-primary" style="width: 100%;">
                            <i class="fas fa-play"></i> 批次執行
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 結果輸出 -->
        <div id="create-result-log" style="margin-top: 20px; display: none;">
            <div class="alert alert-info" id="create-result-content" style="white-space: pre-line;"></div>
        </div>

        <div style="margin-top:20px; text-align:center;">
            <button class="btn-secondary" onclick="showTab('admin-console')">
                <i class="fas fa-arrow-left"></i> 返回控制台
            </button>
        </div>
    </div>

    <script>
        function createCategory(e, type) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

            const fd = new FormData(form);
            fd.append('mode', type); // 'single' or 'batch'

            document.getElementById('create-result-log').style.display = 'none';

            fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/batch_create', {
                method: 'POST',
                body: fd
            })
                .then(res => res.json())
                .then(data => {
                    const logDiv = document.getElementById('create-result-log');
                    const contentDiv = document.getElementById('create-result-content');
                    logDiv.style.display = 'block';

                    if (data.success) {
                        contentDiv.className = 'alert alert-success';
                        contentDiv.textContent = data.message + '\n\n' + (data.log || '');
                        if (type === 'single') form.reset();
                    } else {
                        contentDiv.className = 'alert alert-danger';
                        contentDiv.textContent = '錯誤：' + data.message;
                    }
                })
                .catch(err => {
                    alert('系統錯誤: ' + err);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }
    </script>
</div>