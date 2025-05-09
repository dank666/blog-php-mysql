<?php
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
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// 如果用户不存在，则显示错误信息
if ($result->num_rows === 0) {
    $error = "用户不存在";
} else {
    $user = $result->fetch_assoc();
    
    // 统计该用户发布的文章数
    $posts_stmt = $conn->prepare("SELECT COUNT(*) as post_count FROM blog_table WHERE user_id = ?");
    $posts_stmt->bind_param("i", $user_id);
    $posts_stmt->execute();
    $posts_result = $posts_stmt->get_result();
    $post_count = $posts_result->fetch_assoc()['post_count'];
    $posts_stmt->close();
    
    // 统计关注数（该用户关注了多少人）
    $following_stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE follower_id = ?");
    $following_stmt->bind_param("i", $user_id);
    $following_stmt->execute();
    $following_result = $following_stmt->get_result();
    $following_count = $following_result->fetch_assoc()['count'];
    $following_stmt->close();
    
    // 统计粉丝数（有多少人关注该用户）
    $followers_stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE following_id = ?");
    $followers_stmt->bind_param("i", $user_id);
    $followers_stmt->execute();
    $followers_result = $followers_stmt->get_result();
    $followers_count = $followers_result->fetch_assoc()['count'];
    $followers_stmt->close();
    
    // 检查当前用户是否已关注该用户
    $is_following = false;
    if ($currentUserId > 0 && $currentUserId != $user_id) {
        $check_follow_stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
        $check_follow_stmt->bind_param("ii", $currentUserId, $user_id);
        $check_follow_stmt->execute();
        $is_following = $check_follow_stmt->get_result()->num_rows > 0;
        $check_follow_stmt->close();
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($error) ? "错误" : htmlspecialchars($user['username']) . " 的个人资料"; ?> - 博客系统</title>
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
        
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            text-align: center;
        }
        
        .profile-header {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 15px;
            border: 3px solid #f0f0f0;
            object-fit: cover;
        }
        
        .profile-username {
            font-size: 24px;
            margin: 10px 0;
            color: #333;
        }
        
        .profile-bio {
            color: #666;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .profile-stats {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
            min-width: 100px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .profile-actions {
            margin-top: 25px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            margin: 5px;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #007bff;
            color: #007bff;
        }
        
        .btn-outline:hover {
            background-color: #f0f8ff;
        }
        
        .error-message {
            color: #d9534f;
            text-align: center;
            margin: 40px 0;
            padding: 20px;
            background-color: #f9f2f2;
            border-radius: 5px;
        }
        
        .profile-posts {
            margin-top: 30px;
            text-align: center;
        }

        .profile-posts h2 {
            margin-bottom: 20px;
            font-size: 22px;
            color: #333;
            text-align: center;
        }

        .posts-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .post-card {
            width: calc(33.333% - 14px);
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .post-card {
                width: calc(50% - 10px);
            }
        }

        @media (max-width: 480px) {
            .post-card {
                width: 100%;
            }
        }

        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .post-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
            height: 100%;
        }

        .post-card-image {
            height: 160px;
            overflow: hidden;
            background-color: #f0f0f0;
        }

        .post-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .post-card:hover .post-card-image img {
            transform: scale(1.05);
        }

        .post-card-no-image {
            background-color: #f0f0f0;
            position: relative;
        }

        .post-card-no-image::after {
            content: "无图片";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #999;
            font-size: 14px;
        }

        .post-card-content {
            padding: 15px;
            text-align: left;
        }

        .post-card-title {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
            /* 文本溢出显示省略号 */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 42px;
        }

        .post-card-date {
            color: #888;
            font-size: 12px;
        }

        .view-all-posts {
            margin-top: 20px;
            text-align: center;
        }

        .view-all-posts a {
            display: inline-block;
            padding: 8px 20px;
            background-color: #f8f9fa;
            color: #007bff;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 14px;
            transition: all 0.2s;
        }

        .view-all-posts a:hover {
            background-color: #e9ecef;
            color: #0056b3;
        }

        .no-posts {
            grid-column: 1 / -1;
            text-align: center;
            color: #888;
            padding: 30px 0;
            font-style: italic;
            background-color: #f9f9f9;
            border-radius: 5px;
            width: 100%;
        }

        .follow-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #007bff;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            outline: none;
        }
        
        .follow-btn.following {
            background-color: #007bff;
            color: white;
        }
        
        .follow-btn.not-following {
            background-color: white;
            color: #007bff;
        }
        
        .stat-item a {
            text-decoration: none;
            color: inherit;
            display: block;
            width: 100%;
            height: 100%;
        }
        
        .stat-item:hover {
            background-color: #f0f0f0;
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
        <?php if (isset($error)): ?>
            <div class="error-message">
                <h2>发生错误</h2>
                <p><?php echo $error; ?></p>
                <p><a href="index.php">返回首页</a></p>
            </div>
        <?php else: ?>
            <div class="profile-container">
                <div class="profile-header">
                    <img src="avatars/<?php echo htmlspecialchars($user['avatar'] ?? 'default.jpg'); ?>" alt="用户头像" class="profile-avatar">
                    <h1 class="profile-username"><?php echo htmlspecialchars($user['username']); ?></h1>
                    <?php if (!empty($user['bio'])): ?>
                        <div class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $post_count; ?></div>
                        <div class="stat-label">文章</div>
                    </div>
                    <div class="stat-item">
                        <a href="following.php?id=<?php echo $user_id; ?>">
                            <div class="stat-value"><?php echo $following_count; ?></div>
                            <div class="stat-label">关注</div>
                        </a>
                    </div>
                    <div class="stat-item">
                        <a href="followers.php?id=<?php echo $user_id; ?>">
                            <div class="stat-value"><?php echo $followers_count; ?></div>
                            <div class="stat-label">粉丝</div>
                        </a>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <?php if ($currentUserId > 0 && $currentUserId != $user_id): ?>
                        <button id="followBtn" class="follow-btn <?php echo $is_following ? 'following' : 'not-following'; ?>" data-userid="<?php echo $user_id; ?>">
                            <?php echo $is_following ? '已关注' : '关注'; ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($currentUserId == $user_id): ?>
                        <a href="edit_profile.php" class="btn btn-outline">编辑资料</a>
                    <?php endif; ?>
                </div>
                
                <div class="profile-posts">
                    <h2>最近发布的文章</h2>
                    
                    <div class="posts-grid">
                        <?php 
                        // 重新连接数据库获取最近的文章
                        $conn = new mysqli(
                            $config['db']['host'],
                            $config['db']['username'],
                            $config['db']['password'],
                            $config['db']['database']
                        );
                        
                        $posts_stmt = $conn->prepare("
                            SELECT id, topic_title, topic_date, topic_para, image_filename
                            FROM blog_table 
                            WHERE user_id = ? 
                            ORDER BY id DESC
                            LIMIT 6
                        ");
                        $posts_stmt->bind_param("i", $user_id);
                        $posts_stmt->execute();
                        $posts_result = $posts_stmt->get_result();
                        
                        if ($posts_result->num_rows > 0) {
                            while($post = $posts_result->fetch_assoc()) {
                                // 创建摘要
                                $title = htmlspecialchars($post['topic_title']);
                                $date = htmlspecialchars($post['topic_date']);
                                $post_id = $post['id'];
                                
                                echo '<div class="post-card">';
                                echo '<a href="post.php?id=' . $post_id . '" class="post-card-link">';
                                
                                // 显示图片（如果有）
                                if (!empty($post['image_filename']) && $post['image_filename'] != 'NONE') {
                                    echo '<div class="post-card-image">';
                                    echo '<img src="images/' . htmlspecialchars($post['image_filename']) . '" alt="文章配图">';
                                    echo '</div>';
                                } else {
                                    // 如果没有图片，显示默认图片或占位符
                                    echo '<div class="post-card-image post-card-no-image">';
                                    echo '</div>';
                                }
                                
                                echo '<div class="post-card-content">';
                                echo '<h3 class="post-card-title">' . $title . '</h3>';
                                echo '<div class="post-card-date">' . $date . '</div>';
                                echo '</div>';
                                
                                echo '</a>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="no-posts">暂无文章</div>';
                        }
                        
                        $posts_stmt->close();
                        $conn->close();
                        ?>
                    </div>
                    
                    <?php if ($post_count > 6): ?>
                        <div class="view-all-posts">
                            <a href="user_posts.php?id=<?php echo $user_id; ?>">查看全部 <?php echo $post_count; ?> 篇文章</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    const followBtn = document.getElementById('followBtn');
    if (followBtn) {
        followBtn.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid');
            
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
                    if (data.is_following) {
                        followBtn.className = 'follow-btn following';
                        followBtn.textContent = '已关注';
                    } else {
                        followBtn.className = 'follow-btn not-following';
                        followBtn.textContent = '关注';
                    }
                    
                    const followersCountElem = document.querySelector('.stat-item:nth-child(3) .stat-value');
                    if (followersCountElem) {
                        followersCountElem.textContent = data.followers_count;
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
    }
    </script>
</body>
</html>