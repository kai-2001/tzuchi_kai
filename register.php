<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 產生 CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證 CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "安全驗證失敗，請重新整理頁面後再試。";
        $msg_type = "danger";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        
        // 取得動態選單的值
        // institution 現在傳回來的是 Category ID (例如 4, 6)
        $institution_val = $_POST['institution']; 
        $role_parts = explode('|', $institution_val);
        $cat_id = $role_parts[0] ?? '';
        $cat_name = $role_parts[1] ?? $institution_val;

        // department 是 Cohort ID (例如 12, 15)，可能為空
        $cohort_id = isset($_POST['department']) ? trim($_POST['department']) : '';
        
        // 1. 基本檢查
        if (empty($username) || empty($password) || empty($fullname) || empty($email) || empty($cat_id)) {
            $msg = "所有欄位都是必填的！";
            $msg_type = "danger";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $msg = "帳號只能包含英文字母、數字和底線！";
            $msg_type = "danger";
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $msg = "帳號長度需在 3-20 個字元之間！";
            $msg_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "請輸入有效的電子信箱格式！";
            $msg_type = "danger";
        } elseif (strlen($password) < 8) {
            $msg = "密碼長度至少需要 8 個字元！";
            $msg_type = "danger";
        } else {
            require_once 'includes/db_connect.php';

            // 2. 檢查帳號是否存在
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $msg = "這個帳號 ($username) 已經有人使用了，請換一個。";
                $msg_type = "warning";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 3. 寫入外層資料庫 (存中文院區名)
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, institution) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $hashed_password, $fullname, $email, $cat_name);

                if ($stmt->execute()) {
                    // 4. Moodle API 建立使用者
                    $last_name = mb_substr($fullname, 0, 1, "utf-8");
                    $first_name = mb_substr($fullname, 1, null, "utf-8");

                    $moodle_user_data = [
                        'users' => [
                            [
                                'username' => $username,
                                'password' => $password,
                                'firstname' => $first_name,
                                'lastname' => $last_name,
                                'email' => $email,
                                'institution' => $cat_name, 
                                'auth' => 'manual',
                            ]
                        ]
                    ];
                    
                    // 呼叫建立使用者 API
                    require_once 'includes/moodle_api.php'; // 確保引用
                    $create_result = call_moodle($moodle_url, $moodle_token, 'core_user_create_users', $moodle_user_data);

                    if (isset($create_result['exception']) || isset($create_result['errorcode'])) {
                         $msg = "外層註冊成功，但 Moodle 同步失敗：" . ($create_result['message'] ?? '未知錯誤');
                         $msg_type = "warning";
                    } else {
                        // Moodle 建立成功，取得 User ID & Username
                        $new_users = $create_result; 
                        // create_users 回傳建立的使用者列表 [{id:1, username: '...'}, ...]
                        if (!empty($new_users) && isset($new_users[0]['id'])) {
                            $moodle_user_id = $new_users[0]['id'];
                            
                            // ========================================
                            // 5. 雙重加入群組 (Cohort)
                            // A) 加入院區群組 (必要)
                            // B) 加入部門群組 (選填)
                            // ========================================
                            
                            // A) 找或建立院區群組
                            $inst_cohort_id = null;
                            
                            // 搜尋與院區同名的 Cohort
                            $search_params = [
                                'query' => $cat_name,
                                'context' => ['contextlevel' => 'coursecat', 'instanceid' => $cat_id],
                                'includes' => 'all'
                            ];
                            $cohort_search = call_moodle($moodle_url, $moodle_token, 'core_cohort_search_cohorts', $search_params);
                            
                            if (isset($cohort_search['cohorts'])) {
                                foreach ($cohort_search['cohorts'] as $c) {
                                    if ($c['name'] === $cat_name) {
                                        $inst_cohort_id = $c['id'];
                                        break;
                                    }
                                }
                            }
                            
                            // 如果沒找到，自動建立
                            if (!$inst_cohort_id) {
                                $create_cohort = call_moodle($moodle_url, $moodle_token, 'core_cohort_create_cohorts', [
                                    'cohorts' => [[
                                        'categorytype' => ['type' => 'id', 'value' => $cat_id],
                                        'name' => $cat_name,
                                        'idnumber' => 'inst_' . $cat_id,
                                        'description' => '院區頂層群組 (自動建立)'
                                    ]]
                                ]);
                                if (isset($create_cohort[0]['id'])) {
                                    $inst_cohort_id = $create_cohort[0]['id'];
                                }
                            }
                            
                            // 加入院區群組
                            if ($inst_cohort_id) {
                                call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', [
                                    'members' => [[
                                        'cohorttype' => ['type' => 'id', 'value' => $inst_cohort_id],
                                        'usertype' => ['type' => 'id', 'value' => $moodle_user_id]
                                    ]]
                                ]);
                            }
                            
                            // B) 加入部門群組 (如果有選)
                            if (!empty($cohort_id)) {
                                error_log("Register: Adding user $moodle_user_id to department cohort $cohort_id");
                                $dept_result = call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', [
                                    'members' => [[
                                        'cohorttype' => ['type' => 'id', 'value' => $cohort_id],
                                        'usertype' => ['type' => 'id', 'value' => $moodle_user_id]
                                    ]]
                                ]);
                                if (isset($dept_result['exception']) || isset($dept_result['warnings'])) {
                                    error_log("Register: Failed to add to cohort - " . json_encode($dept_result));
                                } else {
                                    error_log("Register: Successfully added to cohort $cohort_id");
                                }
                            } else {
                                error_log("Register: No department cohort selected (cohort_id is empty)");
                            }
                        }

                        $msg = "註冊成功！";
                        $msg_type = "success";
                        header("refresh:2;url=index.php");
                    }

                } else {
                    $msg = "資料庫錯誤：" . $conn->error;
                    $msg_type = "danger";
                }
            }
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊新帳號 | 雲嘉學習網</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
         /* 沿用原本漂亮的 CSS */
         :root { --primary: #2563eb; --accent: #06b6d4; }
         body { background: linear-gradient(135deg, #e0f2fe 0%, #f0f4f8 50%, #ede9fe 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; padding: 20px; }
         body::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; z-index: 0; background: radial-gradient(ellipse 600px 400px at 15% 20%, rgba(99, 179, 237, 0.25) 0%, transparent 70%), radial-gradient(ellipse 500px 350px at 85% 25%, rgba(167, 139, 250, 0.2) 0%, transparent 70%); }
         .register-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 24px; box-shadow: 0 4px 24px rgba(99, 179, 237, 0.12), 0 12px 48px rgba(167, 139, 250, 0.08); border: 1px solid rgba(255, 255, 255, 0.8); max-width: 480px; width: 100%; padding: 50px 40px; position: relative; z-index: 1; }
         .register-card h3 { color: #1e293b; font-weight: 700; margin-bottom: 8px; }
         .register-card .subtitle { color: #64748b; margin-bottom: 30px; }
         .form-label { font-weight: 500; color: #475569; }
         .form-control, .form-select { border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; transition: all 0.2s; }
         .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
         .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); border: none; border-radius: 30px; padding: 14px 28px; font-weight: 600; box-shadow: 0 4px 20px rgba(37, 99, 235, 0.35); transition: all 0.3s; }
         .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(37, 99, 235, 0.45); }
         .back-link { color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: color 0.2s; }
         .back-link:hover { color: var(--primary); }
         .icon-header { width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
         .icon-header i { font-size: 24px; color: white; }
         .password-rules { background: #f1f5f9; border-radius: 10px; padding: 12px 16px; margin-top: 10px; font-size: 13px; }
         .password-rules .rules-title { font-weight: 600; color: #475569; margin-bottom: 8px; }
         .password-rules ul { margin: 0; padding-left: 20px; color: #64748b; }
         .password-rules li { margin-bottom: 4px; }
         #department-container { display: none; } /* 預設隱藏部門選單 */
    </style>
</head>
<body>
    <div class="register-card">
        <div class="icon-header">
            <i class="fas fa-user-plus"></i>
        </div>
        <h3>註冊學員帳號</h3>
        <p class="subtitle">建立帳號開始您的學習之旅</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?> mb-4">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="mb-3">
                <label class="form-label">帳號</label>
                <input type="text" name="username" class="form-control" required placeholder="英文、數字或底線，3-20 字元">
            </div>

            <div class="mb-3">
                <label class="form-label">密碼</label>
                <input type="password" name="password" class="form-control" required placeholder="請輸入符合規則的密碼">
                <div class="password-rules">
                    <div class="rules-title"><i class="fas fa-shield-alt me-1"></i> 密碼必須符合以下規則：</div>
                    <ul>
                        <li>至少 8 個字元</li>
                        <li>至少 1 個數字</li>
                    </ul>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">真實姓名</label>
                <input type="text" name="fullname" class="form-control" required placeholder="例如：王小明">
            </div>

            <!-- 動態院區選單 -->
            <div class="mb-3">
                <label class="form-label">所屬院區</label>
                <select name="institution" id="institution-select" class="form-select" required>
                    <option value="" disabled selected>載入中...</option>
                </select>
            </div>

            <!-- 動態部門選單 (預設隱藏) -->
            <div class="mb-3" id="department-container">
                <label class="form-label">所屬部門 / 單位 (選填)</label>
                <select name="department" id="department-select" class="form-select">
                    <option value="" selected>不指定</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">電子信箱</label>
                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-4">
                <i class="fas fa-check me-2"></i>立即註冊
            </button>
        </form>

        <div class="text-center">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> 已有帳號？返回登入
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const instSelect = document.getElementById('institution-select');
            const deptSelect = document.getElementById('department-select');
            const deptContainer = document.getElementById('department-container');
            let structureData = [];

            // 1. 載入結構資料
            fetch('api/public/get_structure.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        structureData = result.data;
                        renderInstitutions();
                    } else {
                        instSelect.innerHTML = '<option disabled>載入失敗</option>';
                        console.error('API Error:', result.error);
                    }
                })
                .catch(err => {
                    instSelect.innerHTML = '<option disabled>連線錯誤</option>';
                    console.error('Fetch Error:', err);
                });

            // 2. 渲染此院區清單
            function renderInstitutions() {
                instSelect.innerHTML = '<option value="" disabled selected>請選擇院區</option>';
                structureData.forEach(cat => {
                    // value 存 "ID|Name" 以便後端同時取得這兩個資訊
                    const option = document.createElement('option');
                    option.value = cat.id + '|' + cat.name; 
                    option.textContent = cat.name;
                    instSelect.appendChild(option);
                });
            }

            // 3. 監聽院區變更
            instSelect.addEventListener('change', function() {
                const selectedVal = this.value;
                if (!selectedVal) return;
                
                const catId = selectedVal.split('|')[0];
                const selectedCat = structureData.find(c => c.id == catId);

                // 清空並重新渲染部門
                deptSelect.innerHTML = '<option value="" selected>不指定</option>';
                
                if (selectedCat && selectedCat.departments && selectedCat.departments.length > 0) {
                    selectedCat.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id; // Cohort ID
                        option.textContent = dept.name;
                        deptSelect.appendChild(option);
                    });
                    // 顯示部門選單
                    deptContainer.style.display = 'block';
                } else {
                    // 如果該院區沒有部門資料，隱藏選單
                    deptContainer.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>