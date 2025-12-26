<?php
/**
 * Shared Footer Partial
 * 
 * Variables available:
 * - $show_login_modal (bool) - Show login modal
 * - $page_js (string) - Page-specific JavaScript
 */
?>

<?php if (!empty($show_login_modal)): ?>
    <!-- Login Prompt Modal -->
    <div id="loginModal" class="modal-overlay">
        <div class="modal">
            <i class="fa-solid fa-lock" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 20px;"></i>
            <h2>需要登入</h2>
            <p>請先登入後再觀看影片內容。</p>
            <a href="login.php" class="btn-login">前往登入</a>
            <p style="margin-top: 15px; cursor: pointer; color: var(--text-secondary);" onclick="closeModal()">關閉</p>
        </div>
    </div>
<?php endif; ?>

</div>

<script src="assets/js/main.js"></script>
<?php if (!empty($page_js)): ?>
    <script><?= $page_js ?></script>
<?php endif; ?>
</body>

</html>