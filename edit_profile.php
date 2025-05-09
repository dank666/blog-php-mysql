<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

// 需要登录才能访问此页面
requireLogin();

// 获取当前用户信息
$currentUserId = getCurrentUserId();

// 连接数据库
$conn = new mysqli(
    $config['db']['host'],
    $config['db']['username'],
    $config['db']['password'],
    $config['db']['database']
);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 表单提交处理
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取并验证提交的数据
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // 验证用户名不为空
    if (empty($username)) {
        $message = '用户名不能为空';
        $messageType = 'error';
    } else {
        // 检查用户名是否已被使用（排除当前用户）
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $currentUserId);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows > 0) {
            $message = '该用户名已被使用';
            $messageType = 'error';
        }
        $check_stmt->close();
    }
    
    // 如果要更改密码
    if (!empty($newPassword)) {
        // 验证当前密码
        $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $pass_stmt->bind_param("i", $currentUserId);
        $pass_stmt->execute();
        $pass_result = $pass_stmt->get_result();
        $user = $pass_result->fetch_assoc();
        $pass_stmt->close();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $message = '当前密码不正确';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '两次输入的新密码不匹配';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = '密码长度不能少于6个字符';
            $messageType = 'error';
        }
    }
    
    // 处理头像上传
    $avatar = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            $message = '只允许上传JPG、PNG或GIF格式的图片';
            $messageType = 'error';
        } elseif ($_FILES['avatar']['size'] > $maxFileSize) {
            $message = '图片大小不能超过2MB';
            $messageType = 'error';
        } else {
            // 确保目录存在
            if (!file_exists('avatars')) {
                mkdir('avatars', 0777, true);
            }
            
            // 生成唯一的文件名
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatar = 'avatar_' . $currentUserId . '_' . uniqid() . '.' . $extension;
            $targetPath = 'avatars/' . $avatar;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                // 获取并删除旧头像
                $old_stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                $old_stmt->bind_param("i", $currentUserId);
                $old_stmt->execute();
                $old_result = $old_stmt->get_result();
                $old_user = $old_result->fetch_assoc();
                $old_stmt->close();
                
                if (!empty($old_user['avatar']) && $old_user['avatar'] !== 'default.jpg' && file_exists('avatars/' . $old_user['avatar'])) {
                    unlink('avatars/' . $old_user['avatar']);
                }
            } else {
                $message = '头像上传失败';
                $messageType = 'error';
                $avatar = null;
            }
        }
    }
    
    // 如果没有错误，更新用户信息
    if (empty($message)) {
        // 准备更新语句
        if (!empty($newPassword) && $avatar !== null) {
            // 更新用户名、密码和头像
            $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, bio = ?, avatar = ? WHERE id = ?");
            $update_stmt->bind_param("ssssi", $username, $hashed_password, $bio, $avatar, $currentUserId);
        } elseif (!empty($newPassword)) {
            // 只更新用户名和密码
            $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, bio = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $username, $hashed_password, $bio, $currentUserId);
        } elseif ($avatar !== null) {
            // 只更新用户名和头像
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, bio = ?, avatar = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $username, $bio, $avatar, $currentUserId);
        } else {
            // 只更新用户名和个人简介
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $username, $bio, $currentUserId);
        }
        
        if ($update_stmt->execute()) {
            $message = '个人资料更新成功';
            $messageType = 'success';
            
            // 如果用户名改变了，更新会话中的用户名
            $_SESSION['username'] = $username;
        } else {
            $message = '更新失败: ' . $conn->error;
            $messageType = 'error';
        }
        $update_stmt->close();
    }
}

// 获取用户当前信息
$stmt = $conn->prepare("SELECT username, bio, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑个人资料 - 博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .edit-profile-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #444;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-hint {
            font-size: 0.85em;
            color: #777;
            margin-top: 5px;
        }
        
        .current-avatar {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f0f0f0;
        }
        
        .avatar-label {
            display: block;
            margin-top: 8px;
            color: #666;
        }
        
        .section-title {
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #444;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .message {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .top-bar {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        #topBarTitle {
            font-size: 20px;
            font-weight: bold;
        }
        
        .top-bar-right a {
            color: #007bff;
            text-decoration: none;
            margin-left: 15px;
            transition: color 0.2s;
        }
        
        .top-bar-right a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">多用户博客系统</span>
        <div class="top-bar-right">
            <a href="index.php">首页</a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php">我的资料</a>
                <a href="following.php">我的关注</a>
                <a href="followers.php">我的粉丝</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php">管理面板</a>
                <?php endif; ?>
                <a href="settings.php">设置</a>
                <a href="logout.php">退出</a>
            <?php else: ?>
                <a href="login.php">登录</a>
                <a href="register.php">注册</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="main-content">
        <div class="edit-profile-container">
            <h1 class="page-title">编辑个人资料</h1>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType === 'success' ? 'message-success' : 'message-error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form action="edit_profile.php" method="post" enctype="multipart/form-data">
                <div class="current-avatar">
                    <img src="avatars/<?php echo htmlspecialchars($user['avatar'] ?? 'default.jpg'); ?>" alt="当前头像" class="avatar-preview">
                    <span class="avatar-label">当前头像</span>
                </div>
                
                <div class="form-group">
                    <label for="avatar" class="form-label">更换头像</label>
                    <input type="file" id="avatar" name="avatar" class="form-control">
                    <div class="form-hint">支持JPG、PNG、GIF格式，文件大小不超过2MB</div>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">用户名</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bio" class="form-label">个人简介</label>
                    <textarea id="bio" name="bio" class="form-control"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <div class="form-hint">简单介绍一下自己吧</div>
                </div>
                
                <h3 class="section-title">修改密码</h3>
                <div class="form-hint">如果不需要修改密码，请保留以下字段为空</div>
                
                <div class="form-group">
                    <label for="current_password" class="form-label">当前密码</label>
                    <input type="password" id="current_password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">新密码</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                    <div class="form-hint">密码长度不少于6个字符</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">保存更改</button>
                    <a href="profile.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>