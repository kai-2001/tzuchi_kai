<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['username'])): ?>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <?php include 'templates/async_loader.php'; // 非同步資料載入 ?>
<?php endif; ?>

<!-- 游標跟隨暗色光暈 -->
<div class="mouse-follow-glow" id="mouse-glow"></div>

<script>
    (function () {
        const glow = document.getElementById('mouse-glow');
        if (!glow) return;

        document.addEventListener('mousemove', function (e) {
            glow.style.transform = `translate(calc(${e.clientX}px - 50%), calc(${e.clientY}px - 50%))`;
        });
    })();
</script>

</body>

</html>