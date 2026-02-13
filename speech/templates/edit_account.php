<?php include __DIR__ . '/partials/header.php'; ?>

<?php
$navbar_mode = 'simple';
$page_title = '編輯帳號';
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

        <form action="edit_account.php?id=<?= $account['id'] ?>" method="POST">

            <div class="form-group full-width">
                <label>帳號名稱</label>
                <input type="text" value="<?= htmlspecialchars($account['username']) ?>" disabled
                    style="background: #f1f5f9; cursor: not-allowed; color: #64748b;">
                <small style="color: #94a3b8; margin-top: 4px; display: block;">帳號名稱建立後不可修改</small>
            </div>

            <div class="form-group full-width">
                <label>顯示名稱</label>
                <input type="text" name="display_name" placeholder="如：嘉義院區管理員"
                    value="<?= htmlspecialchars($account['display_name'] ?? '') ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>角色</label>
                    <input type="text" value="<?= $account['role'] === 'manager' ? '系統管理員' : '院區管理員' ?>" disabled
                        style="background: #f1f5f9; cursor: not-allowed; color: #64748b;">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($account['role']) ?>">
                </div>
                <?php if ($account['role'] === 'campus_admin'): ?>
                    <div class="form-group">
                        <label>所屬院區</label>
                        <select name="campus_id" id="campus_id">
                            <option value="">-- 請選擇 --</option>
                            <?php foreach ($campuses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($account['campus_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div
                style="background: rgba(248, 250, 252, 0.5); padding: 25px; border-radius: 15px; margin-top: 10px; border: 1px solid #e2e8f0;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: 600; color: var(--primary-color); display: block; margin-bottom: 10px;">
                        <i class="fa-solid fa-key"></i> 重設密碼
                    </label>
                    <input type="password" name="new_password" minlength="6" placeholder="輸入新密碼（至少 6 字元）">
                    <small style="color: #94a3b8; margin-top: 4px; display: block;">留空則不變更密碼</small>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 40px; display: flex; justify-content: center;">
                <button type="submit" class="btn-submit"
                    style="padding: 12px 40px; font-size: 1rem; border-radius: 50px; min-width: 160px;">
                    儲存變更
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>