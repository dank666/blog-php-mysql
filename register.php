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
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $display_name = trim($_POST['display_name'] ?? $username);
    
    // 基本验证
    if (empty($username) || empty($email) || empty($password)) {
        $error = "所有带星号的字段都必须填写";
    } elseif ($password !== $confirm_password) {
        $error = "两次输入的密码不匹配";
    } elseif (strlen($password) < 6) {
        $error = "密码长度必须至少为6个字符";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "请输入有效的电子邮件地址";
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
            // 检查用户名和邮箱是否已存在
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "用户名或邮箱已被使用";
            } else {
                // 创建新用户
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $email, $password_hash, $display_name);
                
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "注册失败: " . $conn->error;
                }
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
    <title>注册 - 博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .form-container {
            max-width: 500px;
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
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .success-message {
            color: #28a745;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">博客系统 | 注册</span>
    </div>
    
    <div class="form-container">
        <?php if ($success): ?>
            <div class="success-message">
                <p>注册成功！现在您可以 <a href="login.php">登录</a> 了。</p>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">用户名 *</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">电子邮件 *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="display_name">显示名称</label>
                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($display_name ?? ''); ?>">
                    <small>如果不填写，将使用用户名作为显示名称</small>
                </div>
                
                <div class="form-group">
                    <label for="password">密码 *</label>
                    <input type="password" id="password" name="password" required>
                    <small>密码长度至少为6个字符</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密码 *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="form-submit">注册</button>
                </div>
            </form>
            
            <p>已有账号？ <a href="login.php">登录</a></p>
        <?php endif; ?>
    </div>
</body>
</html>