<?php
/**
 * Manage Accounts Template
 */
include __DIR__ . '/partials/header.php';
?>

<?php
$navbar_mode = 'simple';
$page_title = '帳號管理';
$nav_actions = [
    ['label' => '返回首頁', 'url' => 'index.php', 'icon' => 'fa-solid fa-house']
];
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="search-bar">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-secondary);">
                <i class="fa-solid fa-users-gear"></i> 管理員帳號列表
            </h3>
            <a href="add_account.php" class="btn-add-video">
                <i class="fa-solid fa-plus"></i> 新增帳號
            </a>
        </div>

        <?php if (empty($accounts)): ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-user-shield" style="font-size: 3rem; opacity: 0.2; margin-bottom: 20px;"></i>
                <p>目前沒有管理員帳號。</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th>帳號</th>
                            <th>顯示名稱</th>
                            <th>角色</th>
                            <th>所屬院區</th>
                            <th style="text-align: center;">密碼狀態</th>
                            <th>最後登入</th>
                            <th style="width: 100px; text-align: center;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($a['username']) ?>
                                    </strong></td>
                                <td>
                                    <?= htmlspecialchars($a['display_name'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php if ($a['role'] === 'manager'): ?>
                                        <span class="badge bg-danger">系統管理員</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">院區管理員</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($a['campus_name'] ?? '-') ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($a['has_password']): ?>
                                        <span style="color: #22c55e;"><i class="fa-solid fa-circle-check"></i> 已設定</span>
                                    <?php else: ?>
                                        <span style="color: #ef4444;"><i class="fa-solid fa-circle-xmark"></i> 未設定</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($a['last_login']): ?>
                                        <?= date('Y-m-d H:i', strtotime($a['last_login'])) ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">從未登入</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions-wrapper" style="justify-content: center;">
                                        <a href="edit_account.php?id=<?= $a['id'] ?>" class="btn-edit" title="編輯">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if ($a['id'] != $_SESSION['user_id']): ?>
                                            <a href="#"
                                                onclick="confirmDeleteAccount(<?= $a['id'] ?>, '<?= htmlspecialchars($a['username'], ENT_QUOTES) ?>')"
                                                class="btn-delete" title="刪除">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .alert-danger {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #dc2626;
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.95rem;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.08);
        border: 1px solid rgba(34, 197, 94, 0.3);
        color: #166534;
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.95rem;
    }
</style>

<script>
    function confirmDeleteAccount(id, username) {
        if (confirm('確定要刪除帳號「' + username + '」嗎？\n此操作無法復原！')) {
            window.location.href = 'manage_accounts.php?action=delete&id=' + id;
        }
    }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>