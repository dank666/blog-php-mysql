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
    <title>登录 - 智域博客</title>
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

        /* 登录卡片美化 - 与index.php的post-container风格一致 */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease-out;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
            opacity: 1;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        /* 标题美化 */
        .login-title {
            text-align: center;
            margin: 0 0 40px 0;
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

        .login-title::before {
            content: '🔐';
            font-size: 36px;
            background: none;
            -webkit-background-clip: initial;
            -webkit-text-fill-color: initial;
            background-clip: initial;
        }

        /* 副标题美化 */
        .login-subtitle {
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
        .form-group label[for="password"]::before { content: '🔒'; }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 15px;
            font-size: 16px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
            transform: translateY(-1px);
        }

        .form-group input:hover {
            border-color: rgba(102, 126, 234, 0.4);
        }

        /* 登录按钮美化 - 与index.php的btn样式一致 */
        .form-submit {
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.2), transparent);
        }

        .divider span {
            background: rgba(255, 255, 255, 0.9);
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        /* 注册链接美化 */
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding: 20px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .register-link p {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 25px;
            border: 2px solid #667eea;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .register-link a::before {
            content: '✨';
            font-size: 16px;
        }

        .register-link a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* 页脚美化 */
        .auth-footer {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 13px;
        }

        .auth-footer a {
            color: #667eea;
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

            .login-card {
                padding: 40px 25px;
                margin: 20px 0;
            }

            .login-title {
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
            .login-card {
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

        /* 记住我复选框美化 */
        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            font-size: 14px;
            color: #666;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .remember-me label {
            cursor: pointer;
            font-weight: 500;
        }

        /* 忘记密码链接美化 */
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- 使用与index.php一致的导航栏 -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="auth-container">
        <div class="login-card">
            <h1 class="login-title">登录账户</h1>
            <p class="login-subtitle">欢迎回到智域博客，开始您的创作之旅</p>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($_SESSION['error_message'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username">用户名或邮箱</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                        required
                        autocomplete="username"
                        placeholder="请输入用户名或邮箱"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                        placeholder="请输入您的密码"
                    >
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">记住我</label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="form-submit" id="loginBtn">
                        登录
                    </button>
                </div>
            </form>
            
            <div class="forgot-password">
                <a href="#" onclick="alert('请联系管理员重置密码')">忘记密码？</a>
            </div>
            
            <div class="divider">
                <span>还没有账户？</span>
            </div>
            
            <div class="register-link">
                <p>加入我们的创作者社区</p>
                <a href="register.php">立即注册</a>
            </div>
        </div>
    </div>
    
    <div class="auth-footer">
        <p>&copy; 2024 智域博客. 保留所有权利.</p>
    </div>

    <script>
    // 表单验证和交互效果
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username');
        const password = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        
        // 重置样式
        username.classList.remove('error', 'success');
        password.classList.remove('error', 'success');
        
        let isValid = true;
        
        // 验证用户名
        if (username.value.trim().length === 0) {
            username.classList.add('error');
            isValid = false;
        } else {
            username.classList.add('success');
        }
        
        // 验证密码
        if (password.value.length === 0) {
            password.classList.add('error');
            isValid = false;
        } else {
            password.classList.add('success');
        }
        
        if (!isValid) {
            e.preventDefault();
            return;
        }
        
        // 显示加载状态
        loginBtn.classList.add('loading');
        loginBtn.textContent = '登录中...';
        loginBtn.disabled = true;
    });

    // 实时验证
    document.getElementById('username').addEventListener('blur', function() {
        if (this.value.trim().length === 0) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    document.getElementById('password').addEventListener('blur', function() {
        if (this.value.length === 0) {
            this.classList.add('error');
            this.classList.remove('success');
        } else {
            this.classList.remove('error');
            this.classList.add('success');
        }
    });

    // 页面加载动画
    document.addEventListener('DOMContentLoaded', function() {
        const formGroups = document.querySelectorAll('.form-group, .remember-me, .register-link');
        formGroups.forEach((group, index) => {
            group.style.opacity = '0';
            group.style.transform = 'translateY(20px)';
            group.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1 + 0.3}s forwards`;
        });
    });
    </script>
</body>
</html>