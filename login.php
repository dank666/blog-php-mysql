<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

// 如果用户已登录，重定向到首页
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "请输入用户名和密码";
    } else {
        // 连接数据库
        $conn = new mysqli(
            $config['db']['host'],
            $config['db']['username'],
            $config['db']['password'],
            $config['db']['database']
        );
        
        if ($conn->connect_error) {
            $error = "数据库连接失败: " . $conn->connect_error;
        } else {
            // 查找用户
            $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password_hash'])) {
                    // 登录成功，设置会话变量
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username']; 
                    $_SESSION['user_role'] = $user['role']; // 确保使用这个变量名
                    
                    // 重定向到首页或其他页面
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "密码不正确";
                }
            } else {
                $error = "用户不存在或已被禁用";
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .form-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-submit {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">博客系统 | 登录</span>
    </div>
    
    <div class="form-container">
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php elseif (isset($_SESSION['error_message'])): ?>
            <div class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">用户名或电子邮件</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="form-submit">登录</button>
            </div>
        </form>
        
        <p>还没有账号？ <a href="register.php">注册</a></p>
    </div>
</body>
</html>