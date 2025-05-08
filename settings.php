<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

// 需要登录才能访问设置页面
requireLogin();

$success = false;
$error = "";
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // 获取表单数据
    $displayName = trim($_POST['display_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 验证电子邮件
    if (!empty($email) && $email !== $user['email']) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "请输入有效的电子邮件地址";
        } else {
            // 检查电子邮件是否已被使用
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "该电子邮件已被其他用户使用";
            }
            $stmt->close();
        }
    }
    
    // 如果要更改密码
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        // 验证当前密码
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($currentPassword, $userData['password_hash'])) {
            $error = "当前密码不正确";
        } elseif (empty($newPassword)) {
            $error = "新密码不能为空";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "两次输入的新密码不匹配";
        } elseif (strlen($newPassword) < 6) {
            $error = "新密码长度必须至少为6个字符";
        }
    }
    
    // 处理头像上传
    $avatarFilename = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['avatar']['tmp_name'];
        $fileType = $_FILES['avatar']['type'];
        $fileSize = $_FILES['avatar']['size'];
        
        // 验证文件
        if (!in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
            $error = "头像必须是JPG、PNG或GIF格式";
        } elseif ($fileSize > 2000000) { // 2MB
            $error = "头像文件大小不能超过2MB";
        } else {
            // 创建头像目录
            $avatarDir = "avatars/";
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }
            
            // 生成唯一文件名
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarFilename = uniqid('avatar_') . '.' . $extension;
            
            // 移动上传的文件
            if (!move_uploaded_file($tmpName, $avatarDir . $avatarFilename)) {
                $error = "头像上传失败";
            } else {
                // 删除旧头像
                if (!empty($user['avatar']) && $user['avatar'] !== 'default.jpg') {
                    $oldAvatarPath = $avatarDir . $user['avatar'];
                    if (file_exists($oldAvatarPath)) {
                        unlink($oldAvatarPath);
                    }
                }
            }
        }
    }
    
    // 如果没有错误，更新用户信息
    if (empty($error)) {
        if (!empty($newPassword)) {
            // 更新包括密码在内的所有信息
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET display_name = ?, bio = ?, email = ?, password_hash = ?, avatar = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $displayName, $bio, $email, $passwordHash, $avatarFilename, $_SESSION['user_id']);
        } else {
            // 只更新基本信息
            $stmt = $conn->prepare("UPDATE users SET display_name = ?, bio = ?, email = ?, avatar = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $displayName, $bio, $email, $avatarFilename, $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $success = true;
            // 更新会话中的用户信息
            $user['display_name'] = $displayName;
            $user['email'] = $email;
            $user['bio'] = $bio;
            $user['avatar'] = $avatarFilename;
        } else {
            $error = "更新失败: " . $conn->error;
        }
        
        $stmt->close();
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账户设置 - 博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group textarea {
            height: 100px;
        }
        
        .section-title {
            margin-top: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .save-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .success-message {
            padding: 10px;
            background-color: #d4edda;
            color: #155724;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-message {
            padding: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        
        /* 导航栏 */
        .nav-bar {
            background-color: #f8f9fa;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-link {
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">账户设置</span>
    </div>
    
    <div class="nav-bar">
        <div class="nav-links">
            <a href="index.php" class="nav-link">首页</a>
            <a href="index.html" class="nav-link">发布文章</a>
            <a href="profile.php" class="nav-link">我的资料</a>
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="nav-link">管理面板</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            欢迎，<a href="profile.php" class="nav-link"><?php echo htmlspecialchars($user['display_name']); ?></a>
            <a href="logout.php" class="nav-link">退出</a>
        </div>
    </div>
    
    <div class="settings-container">
        <?php if ($success): ?>
            <div class="success-message">个人资料已更新成功！</div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <h2 class="section-title">个人资料</h2>
            
            <div class="form-group">
                <label for="display_name">显示名称</label>
                <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user['display_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">电子邮件</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="bio">个人简介</label>
                <textarea id="bio" name="bio"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="avatar">头像</label>
                <?php $avatarUrl = !empty($user['avatar']) ? 'avatars/' . htmlspecialchars($user['avatar']) : 'avatars/default.jpg'; ?>
                <img src="<?php echo $avatarUrl; ?>" alt="当前头像" class="avatar-preview">
                <input type="file" id="avatar" name="avatar" accept="image/*">
                <small>支持JPG、PNG、GIF格式，大小不超过2MB</small>
            </div>
            
            <h2 class="section-title">修改密码</h2>
            <small>如果不想修改密码，请将以下字段留空</small>
            
            <div class="form-group">
                <label for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password">
            </div>
            
            <div class="form-group">
                <label for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password">
                <small>密码长度至少为6个字符</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password">
            </div>
            
            <button type="submit" class="save-btn">保存设置</button>
        </form>
    </div>
</body>
</html>