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
    $display_name = trim($_POST['display_name'] ?? '');
    
    // 如果未填写显示名称，则使用用户名作为显示名称
    if (empty($display_name)) {
        $display_name = $username;
    }
    
    // 基本验证
    if (empty($username) || empty($email) || empty($password)) {
        $error = "所有带星号的字段都必须填写";
    } elseif ($password !== $confirm_password) {
        $error = "两次输入的密码不匹配";
    } elseif (strlen($password) < 6) {
        $error = "密码长度必须至少为6个字符";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "请输入有效的邮箱地址";
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
            // 检查用户名是否已存在
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "用户名已被使用";
            } else {
                // 检查邮箱是否已存在
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "邮箱地址已被使用";
                } else {
                    // 创建新用户
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, display_name, created_at, is_active) VALUES (?, ?, ?, ?, NOW(), TRUE)");
                    $stmt->bind_param("ssss", $username, $email, $password_hash, $display_name);
                    
                    if ($stmt->execute()) {
                        $success = true;
                        // 清空表单数据
                        $username = $email = $display_name = '';
                    } else {
                        $error = "注册失败: " . $stmt->error;
                    }
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
    <title>注册 - 智域博客</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
    <style>
        /* 整体页面美化 - 与index.php保持一致 */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Microsoft YaHei', 'PingFang SC', sans-serif;
            color: #333;
            display: flex;
            flex-direction: column;
        }

        /* 隐藏原有的top-bar */
        .top-bar {
            display: none;
        }

        /* 主容器美化 */
        .auth-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            min-height: calc(100vh - 80px);
        }

        /* 注册卡片美化 - 与index.php的post-container风格一致 */
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease-out;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
            opacity: 1;
        }

        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        /* 标题美化 */
        .register-title {
            text-align: center;
            margin: 0 0 40px 0;
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(45deg, #4ecdc4, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .register-title::before {
            content: '✨';
            font-size: 36px;
            background: none;
            -webkit-background-clip: initial;
            -webkit-text-fill-color: initial;
            background-clip: initial;
        }

        /* 副标题美化 */
        .register-subtitle {
            text-align: center;
            color: #666;
            margin: -20px 0 40px 0;
            font-size: 16px;
            font-weight: 500;
        }

        /* 错误消息美化 */
        .error-message {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease-in-out;
        }

        .error-message::before {
            content: '⚠️';
            font-size: 18px;
        }

        /* 成功消息美化 */
        .success-message {
            background: rgba(78, 205, 196, 0.1);
            color: #4ecdc4;
            border: 1px solid rgba(78, 205, 196, 0.3);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeInDown 0.5s ease-out;
        }

        .success-message::before {
            content: '🎉';
            font-size: 18px;
        }

        /* 表单组美化 */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label::before {
            font-size: 16px;
        }

        .form-group label[for="username"]::before { content: '👤'; }
        .form-group label[for="email"]::before { content: '📧'; }
        .form-group label[for="display_name"]::before { content: '🏷️'; }
        .form-group label[for="password"]::before { content: '🔒'; }
        .form-group label[for="confirm_password"]::before { content: '✅'; }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(78, 205, 196, 0.2);
            border-radius: 15px;
            font-size: 16px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4ecdc4;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
            background: white;
            transform: translateY(-1px);
        }

        .form-group input:hover {
            border-color: rgba(78, 205, 196, 0.4);
        }

        /* 表单提示文字 */
        .form-hint {
            font-size: 13px;
            color: #666;
            margin-top: 6px;
            padding-left: 5px;
        }

        /* 注册按钮美化 - 与index.php的btn样式一致 */
        .form-submit {
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, #4ecdc4 0%, #f093fb 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-submit::before {
            content: '🚀';
            font-size: 18px;
        }

        .form-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.4);
        }

        .form-submit:active {
            transform: translateY(0);
        }

        /* 分割线美化 */
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
            color: #999;
            font-size: 14px;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(78, 205, 196, 0.2), transparent);
        }

        .divider span {
            background: rgba(255, 255, 255, 0.9);
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        /* 登录链接美化 */
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding: 20px;
            background: rgba(78, 205, 196, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(78, 205, 196, 0.1);
        }

        .login-link p {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4ecdc4;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 25px;
            border: 2px solid #4ecdc4;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .login-link a::before {
            content: '🔐';
            font-size: 16px;
        }

        .login-link a:hover {
            background: #4ecdc4;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }

        /* 页脚美化 */
        .auth-footer {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 13px;
        }

        .auth-footer a {
            color: #4ecdc4;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .auth-container {
                padding: 20px 15px;
            }

            .register-card {
                padding: 40px 25px;
                margin: 20px 0;
            }

            .register-title {
                font-size: 28px;
                flex-direction: column;
                gap: 8px;
            }

            .form-group input {
                padding: 14px 18px;
                font-size: 16px; /* 防止iOS缩放 */
            }

            .form-submit {
                padding: 15px 20px;
            }
        }

        /* 毛玻璃效果增强 - 与index.php一致 */
        @supports (backdrop-filter: blur(10px)) {
            .register-card {
                backdrop-filter: blur(15px);
                background: rgba(255, 255, 255, 0.8);
            }
        }

        /* 动画效果 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* 表单验证样式 */
        .form-group input.error {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .form-group input.success {
            border-color: #4ecdc4;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        /* 加载状态按钮 */
        .form-submit.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .form-submit.loading::before {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 密码强度指示器 */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .password-strength.show {
            opacity: 1;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #ff6b6b; width: 25%; }
        .strength-fair { background: #ffa726; width: 50%; }
        .strength-good { background: #42a5f5; width: 75%; }
        .strength-strong { background: #4ecdc4; width: 100%; }

        .password-strength-text {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 500;
        }

        /* 密码显示/隐藏按钮 */
        .password-field {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 18px;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: #4ecdc4;
        }

        /* 必填字段标记 */
        .required {
            color: #ff6b6b;
            margin-left: 3px;
        }
    </style>
</head>
<body>
    <!-- 使用与index.php一致的导航栏 -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="auth-container">
        <div class="register-card">
            <h1 class="register-title">创建账户</h1>
            <p class="register-subtitle">加入智域博客，开启您的创作之旅</p>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    注册成功！欢迎加入智域博客！请前往登录页面。
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label for="username">用户名 <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                        required
                        autocomplete="username"
                        placeholder="请输入用户名"
                        minlength="3"
                        maxlength="50"
                    >
                    <div class="form-hint">用户名长度为3-50个字符，用于登录</div>
                </div>
                
                <div class="form-group">
                    <label for="email">邮箱地址 <span class="required">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                        required
                        autocomplete="email"
                        placeholder="请输入邮箱地址"
                    >
                    <div class="form-hint">用于接收重要通知和密码重置</div>
                </div>
                
                <div class="form-group">
                    <label for="display_name">显示名称</label>
                    <input 
                        type="text" 
                        id="display_name" 
                        name="display_name" 
                        value="<?php echo htmlspecialchars($display_name ?? ''); ?>" 
                        autocomplete="name"
                        placeholder="请输入显示名称（可选）"
                        maxlength="100"
                    >
                    <div class="form-hint">留空则使用用户名作为显示名称</div>
                </div>
                
                <div class="form-group">
                    <label for="password">密码 <span class="required">*</span></label>
                    <div class="password-field">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            autocomplete="new-password"
                            placeholder="请输入密码"
                            minlength="6"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            👁️
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="password-strength-text" id="strengthText"></div>
                    <div class="form-hint">密码至少6个字符，建议包含字母、数字和特殊字符</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密码 <span class="required">*</span></label>
                    <div class="password-field">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required
                            autocomplete="new-password"
                            placeholder="请再次输入密码"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            👁️
                        </button>
                    </div>
                    <div class="form-hint">请确保两次输入的密码一致</div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="form-submit" id="registerBtn">
                        创建账户
                    </button>
                </div>
            </form>
            <?php endif; ?>
            
            <div class="divider">
                <span>已有账户？</span>
            </div>
            
            <div class="login-link">
                <p>立即登录开始创作</p>
                <a href="login.php">立即登录</a>
            </div>
        </div>
    </div>
    
    <div class="auth-footer">
        <p>&copy; 2024 智域博客. 保留所有权利.</p>
    </div>

    <script>
    // 密码显示/隐藏功能
    function togglePassword(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const toggleBtn = passwordField.parentNode.querySelector('.toggle-password');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleBtn.textContent = '🙈';
        } else {
            passwordField.type = 'password';
            toggleBtn.textContent = '👁️';
        }
    }

    // 密码强度检测
    function checkPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 6) strength += 1;
        if (password.length >= 8) strength += 1;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
        if (/\d/.test(password)) strength += 1;
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;
        
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const strengthContainer = document.getElementById('passwordStrength');
        
        strengthContainer.classList.add('show');
        
        if (strength <= 1) {
            strengthBar.className = 'password-strength-bar strength-weak';
            strengthText.textContent = '密码强度：弱';
            strengthText.style.color = '#ff6b6b';
        } else if (strength <= 2) {
            strengthBar.className = 'password-strength-bar strength-fair';
            strengthText.textContent = '密码强度：一般';
            strengthText.style.color = '#ffa726';
        } else if (strength <= 3) {
            strengthBar.className = 'password-strength-bar strength-good';
            strengthText.textContent = '密码强度：良好';
            strengthText.style.color = '#42a5f5';
        } else {
            strengthBar.className = 'password-strength-bar strength-strong';
            strengthText.textContent = '密码强度：强';
            strengthText.style.color = '#4ecdc4';
        }
        
        return strength;
    }

    // 监听密码输入
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        if (password.length > 0) {
            checkPasswordStrength(password);
        } else {
            document.getElementById('passwordStrength').classList.remove('show');
        }
        
        const confirmPassword = document.getElementById('confirm_password').value;
        if (confirmPassword.length > 0) {
            checkPasswordMatch();
        }
    });

    // 检查密码匹配
    function checkPasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const confirmField = document.getElementById('confirm_password');
        
        if (confirmPassword.length === 0) {
            confirmField.classList.remove('error', 'success');
            return;
        }
        
        if (password !== confirmPassword) {
            confirmField.classList.add('error');
            confirmField.classList.remove('success');
        } else {
            confirmField.classList.remove('error');
            confirmField.classList.add('success');
        }
    }

    document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

    // 表单验证和交互效果
    document.getElementById('registerForm')?.addEventListener('submit', function(e) {
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const registerBtn = document.getElementById('registerBtn');
        
        // 重置样式
        [username, email, password, confirmPassword].forEach(field => {
            field.classList.remove('error', 'success');
        });
        
        let isValid = true;
        
        // 验证用户名
        if (username.value.trim().length < 3) {
            username.classList.add('error');
            isValid = false;
        } else {
            username.classList.add('success');
        }
        
        // 验证邮箱
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value)) {
            email.classList.add('error');
            isValid = false;
        } else {
            email.classList.add('success');
        }
        
        // 验证密码
        if (password.value.length < 6) {
            password.classList.add('error');
            isValid = false;
        } else {
            password.classList.add('success');
        }
        
        // 验证确认密码
        if (password.value !== confirmPassword.value) {
            confirmPassword.classList.add('error');
            isValid = false;
        } else {
            confirmPassword.classList.add('success');
        }
        
        if (!isValid) {
            e.preventDefault();
            return;
        }
        
        // 显示加载状态
        registerBtn.classList.add('loading');
        registerBtn.textContent = '注册中...';
        registerBtn.disabled = true;
    });

    // 实时验证
    document.getElementById('username')?.addEventListener('blur', function() {
        if (this.value.trim().length < 3) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    document.getElementById('email')?.addEventListener('blur', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(this.value)) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    // 页面加载动画
    document.addEventListener('DOMContentLoaded', function() {
        const formGroups = document.querySelectorAll('.form-group, .login-link');
        formGroups.forEach((group, index) => {
            group.style.opacity = '0';
            group.style.transform = 'translateY(20px)';
            group.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1 + 0.3}s forwards`;
        });
    });
    </script>
</body>
</html>