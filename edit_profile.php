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
    <title>编辑个人资料 - 智域博客</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
    <style>
        /* 整体页面美化 - 与主界面保持一致 */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Microsoft YaHei', 'PingFang SC', sans-serif;
            color: #333;
        }

        /* 隐藏旧的top-bar */
        .top-bar {
            display: none;
        }
        
        /* 主布局美化 */
        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* 编辑个人资料容器美化 */
        .edit-profile-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .edit-profile-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4, #f093fb);
        }
        
        /* 页面标题美化 */
        .page-title {
            text-align: center;
            margin: 0 0 40px 0;
            color: #667eea;
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .page-title::before {
            content: '✏️';
            font-size: 36px;
            background: none;
            -webkit-background-clip: initial;
            -webkit-text-fill-color: initial;
            background-clip: initial;
        }

        /* 消息提示美化 */
        .message {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeInDown 0.5s ease-out;
        }

        .message-success {
            background: rgba(78, 205, 196, 0.1);
            color: #4ecdc4;
            border: 1px solid rgba(78, 205, 196, 0.3);
        }

        .message-success::before {
            content: '✅';
            font-size: 18px;
        }

        .message-error {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        .message-error::before {
            content: '⚠️';
            font-size: 18px;
        }

        /* 当前头像区域美化 */
        .current-avatar {
            text-align: center;
            margin-bottom: 30px;
            padding: 25px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 20px;
            border: 2px dashed rgba(102, 126, 234, 0.2);
        }

        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .avatar-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
        }

        .avatar-label {
            display: block;
            color: #667eea;
            font-weight: 600;
            font-size: 16px;
        }

        /* 表单组美化 */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label::before {
            font-size: 16px;
        }

        .form-label[for="avatar"]::before { content: '🖼️'; }
        .form-label[for="username"]::before { content: '👤'; }
        .form-label[for="bio"]::before { content: '📝'; }
        .form-label[for="current_password"]::before { content: '🔒'; }
        .form-label[for="new_password"]::before { content: '🔑'; }
        .form-label[for="confirm_password"]::before { content: '✅'; }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 15px;
            font-size: 15px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        /* 表单提示美化 */
        .form-hint {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            padding: 8px 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            border-left: 3px solid rgba(102, 126, 234, 0.3);
        }

        /* 区域标题美化 */
        .section-title {
            margin: 40px 0 20px;
            padding: 15px 0;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
            color: #667eea;
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '🔐';
            font-size: 24px;
        }

        /* 密码区域提示美化 */
        .password-section-hint {
            margin-bottom: 20px;
            padding: 15px 20px;
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 15px;
            color: #856404;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-section-hint::before {
            content: '💡';
            font-size: 18px;
        }

        /* 按钮美化 */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 15px 30px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: inherit;
            min-width: 120px;
        }

        .btn[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(108, 117, 125, 0.4);
            color: white;
        }

        /* 表单操作区域美化 */
        .form-actions {
            text-align: center;
            margin-top: 40px;
            padding: 25px 0;
            border-top: 2px solid rgba(102, 126, 234, 0.1);
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* 文件上传美化 */
        input[type="file"].form-control {
            padding: 12px 15px;
            background: rgba(102, 126, 234, 0.05);
            border: 2px dashed rgba(102, 126, 234, 0.3);
            cursor: pointer;
        }

        input[type="file"].form-control:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        input[type="file"].form-control:focus {
            background: rgba(102, 126, 234, 0.1);
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .edit-profile-container {
                padding: 25px;
            }

            .page-title {
                font-size: 26px;
                flex-direction: column;
                gap: 8px;
            }

            .avatar-preview {
                width: 100px;
                height: 100px;
            }

            .form-control {
                padding: 12px 15px;
            }

            .form-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
            }

            .section-title {
                font-size: 20px;
            }
        }

        /* 毛玻璃效果增强 */
        @supports (backdrop-filter: blur(10px)) {
            .edit-profile-container {
                backdrop-filter: blur(15px);
                background: rgba(255, 255, 255, 0.8);
            }
        }

        /* 加载动画 */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .edit-profile-container {
            animation: fadeInUp 0.6s ease-out;
        }

        /* 表单验证样式 */
        .form-control.error {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .form-control.success {
            border-color: #4ecdc4;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        /* 加载状态按钮 */
        .btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-content">
        <div class="edit-profile-container">
            <h1 class="page-title">编辑个人资料</h1>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType === 'success' ? 'message-success' : 'message-error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form action="edit_profile.php" method="post" enctype="multipart/form-data" id="editProfileForm">
                <div class="current-avatar">
                    <img src="avatars/<?php echo htmlspecialchars($user['avatar'] ?? 'default.jpg'); ?>" alt="当前头像" class="avatar-preview" id="avatarPreview">
                    <span class="avatar-label">当前头像</span>
                </div>
                
                <div class="form-group">
                    <label for="avatar" class="form-label">更换头像</label>
                    <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*">
                    <div class="form-hint">📸 支持JPG、PNG、GIF格式，文件大小不超过2MB</div>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">用户名</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    <div class="form-hint">🔤 用户名将在您的文章和评论中显示</div>
                </div>
                
                <div class="form-group">
                    <label for="bio" class="form-label">个人简介</label>
                    <textarea id="bio" name="bio" class="form-control" placeholder="简单介绍一下自己吧..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <div class="form-hint">📖 让其他用户更好地了解您</div>
                </div>
                
                <h3 class="section-title">修改密码</h3>
                <div class="password-section-hint">
                    如果不需要修改密码，请保留以下字段为空
                </div>
                
                <div class="form-group">
                    <label for="current_password" class="form-label">当前密码</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" placeholder="请输入当前密码">
                    <div class="form-hint">🔐 为了安全起见，修改密码需要验证当前密码</div>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">新密码</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="请输入新密码">
                    <div class="form-hint">🔑 密码长度不少于6个字符，建议包含字母和数字</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="请再次输入新密码">
                    <div class="form-hint">✅ 请确保两次输入的密码一致</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn" id="submitBtn">💾 保存更改</button>
                    <a href="profile.php" class="btn btn-secondary">❌ 取消</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // 头像预览功能
    document.getElementById('avatar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // 检查文件类型
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('请选择JPG、PNG或GIF格式的图片');
                this.value = '';
                return;
            }
            
            // 检查文件大小 (2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('图片大小不能超过2MB');
                this.value = '';
                return;
            }
            
            // 预览图片
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // 密码强度检测
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        const strengthIndicator = this.nextElementSibling;
        
        if (password.length === 0) {
            this.classList.remove('error', 'success');
            return;
        }
        
        if (password.length < 6) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    // 密码确认验证
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        
        if (confirmPassword.length === 0) {
            this.classList.remove('error', 'success');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    // 表单提交处理
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const currentPassword = document.getElementById('current_password').value;
        
        // 如果要修改密码，验证密码字段
        if (newPassword || confirmPassword || currentPassword) {
            if (!currentPassword) {
                alert('修改密码需要输入当前密码');
                e.preventDefault();
                return;
            }
            
            if (!newPassword) {
                alert('请输入新密码');
                e.preventDefault();
                return;
            }
            
            if (newPassword !== confirmPassword) {
                alert('两次输入的新密码不一致');
                e.preventDefault();
                return;
            }
            
            if (newPassword.length < 6) {
                alert('新密码长度不能少于6个字符');
                e.preventDefault();
                return;
            }
        }
        
        // 显示加载状态
        submitBtn.classList.add('loading');
        submitBtn.textContent = '保存中...';
        submitBtn.disabled = true;
    });

    // 用户名实时验证
    document.getElementById('username').addEventListener('blur', function() {
        const username = this.value.trim();
        if (username.length === 0) {
            this.classList.add('error');
            this.classList.remove('success');
        } else if (username.length < 2) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    // 页面加载完成后的动画效果
    document.addEventListener('DOMContentLoaded', function() {
        // 为表单组添加渐入动画
        const formGroups = document.querySelectorAll('.form-group, .section-title, .password-section-hint');
        formGroups.forEach((group, index) => {
            group.style.opacity = '0';
            group.style.transform = 'translateY(20px)';
            group.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1}s forwards`;
        });
    });

    // 防止表单重复提交
    let isSubmitting = false;
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return;
        }
        isSubmitting = true;
    });
    </script>
</body>
</html>