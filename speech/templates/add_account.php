<?php include __DIR__ . '/partials/header.php'; ?>

<?php
$navbar_mode = 'simple';
$page_title = '新增帳號';
$custom_breadcrumbs = [
    ['label' => '帳號管理', 'url' => 'manage_accounts.php']
];
$nav_actions = [
    ['label' => '返回列表', 'url' => 'manage_accounts.php', 'icon' => 'fa-solid fa-arrow-left']
];
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if ($error): ?>
            <div class="alert alert-danger" style="color: #f87171; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="add_account.php" method="POST">

            <div class="form-group full-width">
                <label>帳號名稱 <span style="color:red;">*</span></label>
                <input type="text" name="username" required minlength="3" pattern="[a-zA-Z0-9_]+"
                    placeholder="英文/數字/底線，如 admin_kl" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                <small style="color: #94a3b8; margin-top: 4px; display: block;">僅限英文字母、數字與底線，至少 3 個字元</small>
            </div>

            <div class="form-group full-width">
                <label>顯示名稱</label>
                <input type="text" name="display_name" placeholder="如：嘉義院區管理員"
                    value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>角色</label>
                    <input type="text" value="院區管理員" disabled
                        style="background: #f1f5f9; cursor: not-allowed; color: #64748b;">
                    <input type="hidden" name="role" value="campus_admin">
                </div>
                <div class="form-group">
                    <label>所屬院區 <span style="color:red;">*</span></label>
                    <select name="campus_id" id="campus_id" required>
                        <option value="">-- 請選擇 --</option>
                        <?php foreach ($campuses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($_POST['campus_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group full-width">
                <label>密碼 <span style="color:red;">*</span></label>
                <input type="password" name="password" required minlength="6" placeholder="至少 6 個字元">
            </div>

            <div class="form-actions" style="margin-top: 40px; display: flex; justify-content: center;">
                <button type="submit" class="btn-submit"
                    style="padding: 12px 40px; font-size: 1rem; border-radius: 50px; min-width: 160px;">
                    建立帳號
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>