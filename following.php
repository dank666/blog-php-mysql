<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

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

// 获取当前登录用户
$currentUserId = isLoggedIn() ? getCurrentUserId() : 0;

// 获取要查看的用户ID，如果没有指定则显示当前登录用户
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;

// 如果未登录且未指定用户ID，则重定向到登录页面
if ($user_id === 0) {
    header('Location: login.php');
    exit;
}

// 查询用户信息
$stmt = $conn->prepare("SELECT username, display_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

// 如果用户不存在，则显示错误信息
if ($user_result->num_rows === 0) {
    $error = "用户不存在";
} else {
    $user = $user_result->fetch_assoc();
    
    // 查询该用户关注的用户
    $following_stmt = $conn->prepare("
        SELECT u.id, u.username, u.display_name, u.avatar, u.bio
        FROM followers f
        JOIN users u ON f.following_id = u.id
        WHERE f.follower_id = ?
        ORDER BY f.created_at DESC
    ");
    $following_stmt->bind_param("i", $user_id);
    $following_stmt->execute();
    $following_result = $following_stmt->get_result();
    $following_count = $following_result->num_rows;
    $following_list = [];
    
    while($row = $following_result->fetch_assoc()) {
        // 检查当前登录用户是否已关注此用户
        $is_following = false;
        if ($currentUserId > 0 && $currentUserId != $row['id']) {
            $check_follow_stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
            $check_follow_stmt->bind_param("ii", $currentUserId, $row['id']);
            $check_follow_stmt->execute();
            $is_following = $check_follow_stmt->get_result()->num_rows > 0;
            $check_follow_stmt->close();
        }
        
        $row['is_following'] = $is_following;
        $following_list[] = $row;
    }
    $following_stmt->close();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($error) ? "错误" : htmlspecialchars($user['display_name'] ?? $user['username']) . " 的关注"; ?> - 智域博客</title>
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
        }

        /* 隐藏原有的top-bar */
        .top-bar {
            display: none;
        }

        /* 主容器美化 */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - 80px);
        }

        /* 用户列表容器美化 - 与index.php的post-container风格一致 */
        .users-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            margin: 20px auto;
            max-width: 800px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease-out;
        }

        .users-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ecdc4, #f093fb, #667eea);
            opacity: 1;
        }

        .users-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        /* 页面标题美化 */
        .page-title {
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

        .page-title::before {
            content: '🌟';
            font-size: 36px;
            background: none;
            -webkit-background-clip: initial;
            -webkit-text-fill-color: initial;
            background-clip: initial;
        }

        /* 用户列表美化 */
        .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .user-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }

        .user-item:last-child {
            margin-bottom: 0;
        }

        /* 用户头像美化 */
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid rgba(78, 205, 196, 0.2);
            transition: all 0.3s ease;
        }

        .user-item:hover .user-avatar {
            border-color: #4ecdc4;
            transform: scale(1.05);
        }

        /* 用户信息美化 */
        .user-info {
            flex-grow: 1;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            margin: 0 0 5px 0;
            text-decoration: none;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .user-name:hover {
            color: #4ecdc4;
        }

        .user-bio {
            color: #666;
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
        }

        /* 关注按钮美化 */
        .follow-btn {
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid #4ecdc4;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            margin-left: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .follow-btn.following {
            background: #4ecdc4;
            color: white;
        }

        .follow-btn.following::before {
            content: '✓';
            font-size: 16px;
        }

        .follow-btn.not-following {
            background: white;
            color: #4ecdc4;
        }

        .follow-btn.not-following::before {
            content: '+';
            font-size: 16px;
        }

        .follow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }

        /* 返回链接美化 */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding: 12px 25px;
            background: linear-gradient(135deg, #4ecdc4 0%, #f093fb 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }

        .back-link::before {
            content: '←';
            font-size: 16px;
        }

        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 205, 196, 0.4);
        }

        /* 空状态消息美化 */
        .empty-message {
            text-align: center;
            color: #666;
            margin: 40px 0;
            padding: 40px 20px;
            background: rgba(78, 205, 196, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(78, 205, 196, 0.1);
            font-size: 16px;
        }

        .empty-message::before {
            content: '🌸';
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }

        /* 错误消息美化 */
        .error-message {
            color: #ff6b6b;
            text-align: center;
            margin: 40px 0;
            padding: 30px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 15px;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        .error-message h2 {
            margin: 0 0 15px 0;
            font-size: 24px;
        }

        .error-message::before {
            content: '⚠️';
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .users-container {
                padding: 25px;
                margin: 15px auto;
            }

            .page-title {
                font-size: 24px;
                flex-direction: column;
                gap: 8px;
            }

            .user-item {
                padding: 15px;
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .user-avatar {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .follow-btn {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
        }

        /* 毛玻璃效果增强 */
        @supports (backdrop-filter: blur(10px)) {
            .users-container {
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

        /* 加载状态按钮 */
        .follow-btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .follow-btn.loading::before {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- 使用与index.php一致的导航栏 -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="users-container">
                <div class="error-message">
                    <h2>发生错误</h2>
                    <p><?php echo htmlspecialchars($error); ?></p>
                    <p><a href="index.php" class="back-link">返回首页</a></p>
                </div>
            </div>
        <?php else: ?>
            <div class="users-container">
                <h1 class="page-title"><?php echo htmlspecialchars($user['display_name'] ?? $user['username']); ?> 的关注 (<?php echo $following_count; ?>)</h1>
                
                <?php if ($following_count > 0): ?>
                    <ul class="user-list">
                        <?php foreach ($following_list as $following): ?>
                            <li class="user-item">
                                <img src="avatars/<?php echo htmlspecialchars($following['avatar'] ?? 'default.jpg'); ?>" alt="用户头像" class="user-avatar">
                                <div class="user-info">
                                    <a href="profile.php?id=<?php echo $following['id']; ?>" class="user-name">
                                        <?php echo htmlspecialchars($following['display_name'] ?? $following['username']); ?>
                                    </a>
                                    <?php if (!empty($following['bio'])): ?>
                                        <p class="user-bio"><?php echo htmlspecialchars($following['bio']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($currentUserId > 0 && $currentUserId != $following['id']): ?>
                                    <button class="follow-btn <?php echo $following['is_following'] ? 'following' : 'not-following'; ?>" 
                                            data-userid="<?php echo $following['id']; ?>">
                                        <?php echo $following['is_following'] ? '已关注' : '关注'; ?>
                                    </button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-message">
                        <p>暂无关注的用户</p>
                    </div>
                <?php endif; ?>
                
                <div style="text-align: center;">
                    <a href="profile.php?id=<?php echo $user_id; ?>" class="back-link">返回个人资料</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    // 关注/取消关注功能
    document.querySelectorAll('.follow-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid');
            const button = this;
            
            // 添加加载状态
            button.classList.add('loading');
            button.disabled = true;
            
            fetch('toggle_follow.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新按钮状态和文本
                    if (data.is_following) {
                        button.className = 'follow-btn following';
                        button.textContent = '已关注';
                    } else {
                        button.className = 'follow-btn not-following';
                        button.textContent = '关注';
                    }
                } else {
                    alert('操作失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('关注操作失败:', error);
                alert('操作失败，请稍后再试');
            })
            .finally(() => {
                // 移除加载状态
                button.classList.remove('loading');
                button.disabled = false;
            });
        });
    });

    // 页面加载动画
    document.addEventListener('DOMContentLoaded', function() {
        const userItems = document.querySelectorAll('.user-item');
        userItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            item.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1 + 0.3}s forwards`;
        });
    });
    </script>
</body>
</html>