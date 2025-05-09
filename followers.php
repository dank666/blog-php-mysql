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
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

// 如果用户不存在，则显示错误信息
if ($user_result->num_rows === 0) {
    $error = "用户不存在";
} else {
    $user = $user_result->fetch_assoc();
    
    // 查询关注该用户的粉丝
    $followers_stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, u.bio
        FROM followers f
        JOIN users u ON f.follower_id = u.id
        WHERE f.following_id = ?
        ORDER BY f.created_at DESC
    ");
    $followers_stmt->bind_param("i", $user_id);
    $followers_stmt->execute();
    $followers_result = $followers_stmt->get_result();
    $followers_count = $followers_result->num_rows;
    $followers_list = [];
    
    while($row = $followers_result->fetch_assoc()) {
        // 检查当前登录用户是否已关注此用户
        $is_following = false;
        if ($currentUserId > 0) {
            $check_follow_stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
            $check_follow_stmt->bind_param("ii", $currentUserId, $row['id']);
            $check_follow_stmt->execute();
            $is_following = $check_follow_stmt->get_result()->num_rows > 0;
            $check_follow_stmt->close();
        }
        
        $row['is_following'] = $is_following;
        $followers_list[] = $row;
    }
    $followers_stmt->close();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($error) ? "错误" : htmlspecialchars($user['username']) . " 的粉丝"; ?> - 博客系统</title>
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
        
        .users-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 1px solid #eee;
        }
        
        .user-info {
            flex-grow: 1;
        }
        
        .user-name {
            font-weight: bold;
            color: #333;
            margin: 0;
            text-decoration: none;
        }
        
        .user-bio {
            color: #777;
            font-size: 14px;
            margin: 5px 0 0;
        }
        
        .follow-btn {
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid #007bff;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            outline: none;
            margin-left: 10px;
        }
        
        .follow-btn.following {
            background-color: #007bff;
            color: white;
        }
        
        .follow-btn.not-following {
            background-color: white;
            color: #007bff;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .empty-message {
            text-align: center;
            color: #888;
            margin: 30px 0;
        }
        
        .error-message {
            color: #d9534f;
            text-align: center;
            margin: 40px 0;
            padding: 20px;
            background-color: #f9f2f2;
            border-radius: 5px;
        }
        
        /* 统一顶部导航栏样式 */
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
        <?php if (isset($error)): ?>
            <div class="error-message">
                <h2>发生错误</h2>
                <p><?php echo $error; ?></p>
                <p><a href="index.php">返回首页</a></p>
            </div>
        <?php else: ?>
            <div class="users-container">
                <h1 class="page-title"><?php echo htmlspecialchars($user['username']); ?> 的粉丝 (<?php echo $followers_count; ?>)</h1>
                
                <?php if ($followers_count > 0): ?>
                    <ul class="user-list">
                        <?php foreach ($followers_list as $follower): ?>
                            <li class="user-item">
                                <img src="avatars/<?php echo htmlspecialchars($follower['avatar'] ?? 'default.jpg'); ?>" alt="用户头像" class="user-avatar">
                                <div class="user-info">
                                    <a href="profile.php?id=<?php echo $follower['id']; ?>" class="user-name"><?php echo htmlspecialchars($follower['username']); ?></a>
                                    <?php if (!empty($follower['bio'])): ?>
                                        <p class="user-bio"><?php echo htmlspecialchars($follower['bio']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($currentUserId > 0 && $currentUserId != $follower['id']): ?>
                                    <button class="follow-btn <?php echo $follower['is_following'] ? 'following' : 'not-following'; ?>" 
                                            data-userid="<?php echo $follower['id']; ?>">
                                        <?php echo $follower['is_following'] ? '已关注' : '关注'; ?>
                                    </button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="empty-message">暂无粉丝</p>
                <?php endif; ?>
                
                <a href="profile.php?id=<?php echo $user_id; ?>" class="back-link">返回个人资料</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    // 关注/取消关注功能
    document.querySelectorAll('.follow-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid');
            const button = this;
            
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
            });
        });
    });
    </script>
</body>
</html>