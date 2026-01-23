<?php
require_once 'includes/config.php';

$result_dump = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $client = new SoapClient(null, [
                'location' => SOAP_LOCATION,
                'uri' => SOAP_URI,
                'trace' => 1,
                'exceptions' => true,
                'stream_context' => $context
            ]);

            // Attempt login
            $res = $client->login($username, md5($password));

            // Format result
            $result_dump = print_r($res, true);

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOAP 登入測試</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        button {
            padding: 10px 20px;
            background: #008491;
            color: white;
            border: none;
            cursor: pointer;
        }

        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }

        .error {
            color: red;
            background: #ffe6e6;
            padding: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <h1>SOAP 登入回傳資料檢測</h1>
    <p>請輸入您的帳號密碼進行測試，系統將會顯示 SOAP 伺服器回傳的原始資料結構。</p>

    <?php if ($error): ?>
        <div class="error">錯誤：
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>帳號</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>密碼</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">測試登入並查看資料</button>
    </form>

    <?php if ($result_dump): ?>
        <h2>回傳結果：</h2>
        <pre><?= htmlspecialchars($result_dump) ?></pre>
    <?php endif; ?>
</body>

</html>