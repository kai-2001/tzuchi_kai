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

        let mouseX = 0, mouseY = 0;
        let ticking = false;

        document.addEventListener('mousemove', function (e) {
            mouseX = e.clientX;
            mouseY = e.clientY;

            if (!ticking) {
                window.requestAnimationFrame(function () {
                    glow.style.transform = `translate(calc(${mouseX}px - 50%), calc(${mouseY}px - 50%))`;
                    ticking = false;
                });
                ticking = true;
            }
        });
    })();
</script>

</body>

</html>