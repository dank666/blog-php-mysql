<?php
session_start();
include 'includes/auth.php';
include 'includes/database.php';

$config = include 'config.php';
$conn = getDbConnection($config);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 获取用户ID
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : (isLoggedIn() ? getCurrentUserId() : 0);

if ($user_id <= 0) {
    header("Location: login.php");
    exit();
}

// 获取用户信息
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("用户不存在");
}

// 检查是否是当前用户的个人资料
$is_own_profile = isLoggedIn() && getCurrentUserId() == $user_id;

// 获取用户文章数量
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM blog_table WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$post_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 获取用户文章的总浏览量
$stmt = $conn->prepare("
    SELECT COUNT(v.id) as total_views 
    FROM blog_views v 
    JOIN blog_table b ON v.post_id = b.id 
    WHERE b.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_views = $stmt->get_result()->fetch_assoc()['total_views'];
$stmt->close();

// 获取用户文章的总点赞数
$stmt = $conn->prepare("
    SELECT COUNT(l.id) as total_likes 
    FROM likes l 
    JOIN blog_table b ON l.post_id = b.id 
    WHERE b.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_likes = $stmt->get_result()->fetch_assoc()['total_likes'];
$stmt->close();

// 获取粉丝数量
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE following_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$followers_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 获取关注数量
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE follower_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$following_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 检查当前用户是否关注了这个用户
$is_following = false;
if (isLoggedIn() && !$is_own_profile) {
    $current_user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
    $stmt->bind_param("ii", $current_user_id, $user_id);
    $stmt->execute();
    $is_following = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

// 分页设置
$posts_per_page = 12;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $posts_per_page;

// 获取用户的文章（分页）
$stmt = $conn->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = b.id) as likes_count,
           (SELECT COUNT(*) FROM blog_views v WHERE v.post_id = b.id) as views_count
    FROM blog_table b 
    WHERE b.user_id = ? 
    ORDER BY b.id DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $posts_per_page, $offset);
$stmt->execute();
$posts = $stmt->get_result();
$stmt->close();

// 计算总页数
$total_pages = ceil($post_count / $posts_per_page);

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> 的个人资料 - 智域博客</title>
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

        /* 个人资料容器美化 */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* 个人资料头部美化 */
        .profile-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4, #f093fb);
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* 头像美化 */
        .profile-avatar {
            position: relative;
        }

        .profile-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .profile-avatar:hover img {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
        }

        /* 用户信息美化 */
        .profile-details {
            flex: 1;
        }

        .profile-username {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap; /* 允许换行 */
        }

        .profile-role {
            display: inline-flex; /* 改为 inline-flex */
            align-items: center; /* 垂直居中对齐 */
            padding: 8px 16px; /* 增加内边距 */
            border-radius: 20px;
            font-size: 13px; /* 稍微增大字体 */
            font-weight: 600;
            margin-left: 0; /* 移除左边距，因为父容器已有gap */
            /* 重置渐变文字效果 */
            background-clip: initial !important;
            -webkit-background-clip: initial !important;
            -webkit-text-fill-color: initial !important;
            white-space: nowrap; /* 防止文字换行 */
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .profile-role:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-role.admin {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white !important;
        }

        .profile-role.user {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white !important;
        }

        .profile-email {
            color: #666;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-join-date {
            color: #999;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 统计卡片美化 */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
        }

        /* 让粉丝数和关注数可点击 */
        .stat-card.clickable {
            cursor: pointer;
        }

        .stat-card.clickable:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        /* 点击提示样式 */
        .stat-card.clickable .stat-label::after {
            content: '👆';
            font-size: 12px;
            margin-left: 5px;
            opacity: 0.7;
        }

        /* 操作按钮区域美化 */
        .profile-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .follow-btn {
            padding: 12px 25px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .follow-btn.following {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .follow-btn.not-following {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .follow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .edit-profile-btn {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }

        .edit-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 205, 196, 0.4);
            color: white;
        }

        /* 文章列表区域美化 */
        .posts-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .posts-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
        }

        .posts-section h3 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #667eea;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .posts-section h3::before {
            content: '📝';
            font-size: 28px;
        }

        /* 文章网格布局 */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* 文章卡片美化 */
        .post-card {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .post-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .post-card:hover::before {
            opacity: 1;
        }

        .post-card-title {
            margin: 0 0 15px 0;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.4;
        }

        .post-card-title a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .post-card-title a:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .post-card-excerpt {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }

        .post-card-stats {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #666;
        }

        .post-card-stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .post-card-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }

        .post-card:hover .post-card-image {
            transform: scale(1.03);
        }

        /* 分页美化 */
        .pagination {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination .current-page {
            padding: 12px 18px;
            border-radius: 25px;
            text-decoration: none;
            border: none;
            transition: all 0.3s ease;
            font-weight: 600;
            min-width: 45px;
            text-align: center;
        }

        .pagination a {
            color: #667eea;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .pagination a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .pagination .current-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        /* 空状态美化 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 18px;
            margin-bottom: 20px;
        }

        /* 关注者/关注中列表美化 */
        .follow-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .follow-item {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }

        .follow-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .follow-item img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }

        .follow-item-name {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
        }

        .follow-item-stats {
            font-size: 12px;
            color: #999;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px 15px;
            }

            .profile-header {
                padding: 25px;
            }

            .profile-info {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .profile-avatar img {
                width: 100px;
                height: 100px;
            }

            .profile-username {
                font-size: 24px;
                justify-content: center;
            }

            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 20px 15px;
            }

            .posts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .post-card {
                padding: 20px;
            }

            .pagination {
                gap: 5px;
            }

            .pagination a, .pagination .current-page {
                padding: 10px 14px;
                font-size: 14px;
            }

            .profile-actions {
                justify-content: center;
                width: 100%;
            }
        }

        /* 毛玻璃效果增强 */
        @supports (backdrop-filter: blur(10px)) {
            .profile-header,
            .posts-section,
            .stat-card {
                backdrop-filter: blur(15px);
                background: rgba(255, 255, 255, 0.8);
            }
        }

        /* 加载动画 */
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

        .profile-header,
        .posts-section,
        .stat-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }

        /* 成功消息样式 */
        .success-message {
            background: rgba(78, 205, 196, 0.1);
            border: 1px solid rgba(78, 205, 196, 0.3);
            color: #4ecdc4;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message::before {
            content: '✅';
            font-size: 18px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="profile-container">
        <!-- 个人资料头部 -->
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <img src="avatars/<?php echo htmlspecialchars($user['avatar'] ?? 'default.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($user['username']); ?>的头像">
                </div>
                
                <div class="profile-details">
                    <h1 class="profile-username">
                        <?php echo htmlspecialchars($user['username']); ?>
                        <span class="profile-role <?php echo $user['role']; ?>">
                            <?php echo $user['role'] === 'admin' ? '👑 管理员' : '👤 用户'; ?>
                        </span>
                    </h1>
                    
                    <div class="profile-email">
                        📧 <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    
                    <div class="profile-join-date">
                        📅 加入于 <?php echo date('Y年m月d日', strtotime($user['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- 统计信息 -->
            <div class="profile-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $post_count; ?></div>
                    <div class="stat-label">📝 发布文章</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_views; ?></div>
                    <div class="stat-label">👁️ 总浏览量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_likes; ?></div>
                    <div class="stat-label">❤️ 获得点赞</div>
                </div>
                <!-- 让粉丝数可点击 -->
                <a href="followers.php?id=<?php echo $user_id; ?>" class="stat-card clickable">
                    <div class="stat-value"><?php echo $followers_count; ?></div>
                    <div class="stat-label">👥 粉丝数</div>
                </a>
                <!-- 让关注数可点击 -->
                <a href="following.php?id=<?php echo $user_id; ?>" class="stat-card clickable">
                    <div class="stat-value"><?php echo $following_count; ?></div>
                    <div class="stat-label">➕ 关注数</div>
                </a>
            </div>

            <!-- 操作按钮 -->
            <div class="profile-actions">
                <?php if ($is_own_profile): ?>
                    <a href="edit_profile.php" class="edit-profile-btn">
                        ✏️ 编辑资料
                    </a>
                <?php elseif (isLoggedIn()): ?>
                    <button id="followBtn" class="follow-btn <?php echo $is_following ? 'following' : 'not-following'; ?>" 
                            data-userid="<?php echo $user_id; ?>">
                        <?php if ($is_following): ?>
                            ✅ 已关注
                        <?php else: ?>
                            ➕ 关注
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- 文章列表 -->
        <div class="posts-section">
            <h3>Ta 的文章</h3>
            
            <?php if ($posts->num_rows > 0): ?>
                <div class="posts-grid">
                    <?php while ($post = $posts->fetch_assoc()): ?>
                        <div class="post-card">
                            <?php if (!empty($post['image_filename']) && $post['image_filename'] !== 'NONE'): ?>
                                <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" 
                                     alt="文章图片" class="post-card-image">
                            <?php endif; ?>
                            
                            <h4 class="post-card-title">
                                <a href="post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo htmlspecialchars($post['topic_title']); ?>
                                </a>
                            </h4>
                            
                            <div class="post-card-meta">
                                <span>📅 <?php echo date('Y-m-d', strtotime($post['topic_date'])); ?></span>
                                <?php if (!empty($post['location'])): ?>
                                    <span>📍 <?php echo htmlspecialchars($post['location']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-card-excerpt">
                                <?php 
                                $excerpt = mb_substr($post['topic_para'], 0, 120, 'UTF-8');
                                if (mb_strlen($post['topic_para'], 'UTF-8') > 120) {
                                    $excerpt .= '...';
                                }
                                echo nl2br(htmlspecialchars($excerpt)); 
                                ?>
                            </div>
                            
                            <div class="post-card-stats">
                                <div class="post-card-stat">
                                    👁️ <?php echo $post['views_count']; ?>
                                </div>
                                <div class="post-card-stat">
                                    ❤️ <?php echo $post['likes_count']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?id=<?php echo $user_id; ?>&page=<?php echo $current_page - 1; ?>">‹ 上一页</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <span class="current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?id=<?php echo $user_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?id=<?php echo $user_id; ?>&page=<?php echo $current_page + 1; ?>">下一页 ›</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <div class="empty-state-text">
                        <?php echo $is_own_profile ? '您还没有发布任何文章' : 'Ta 还没有发布任何文章'; ?>
                    </div>
                    <?php if ($is_own_profile): ?>
                        <a href="new_post.php" class="btn">✍️ 撰写第一篇文章</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // 关注/取消关注功能
    const followBtn = document.getElementById('followBtn');
    if (followBtn) {
        followBtn.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid');
            const isCurrentlyFollowing = this.classList.contains('following');
            
            // 禁用按钮防止重复点击
            this.disabled = true;
            this.textContent = '处理中...';
            
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
                    // 更新按钮状态
                    if (data.is_following) {
                        this.className = 'follow-btn following';
                        this.innerHTML = '✅ 已关注';
                    } else {
                        this.className = 'follow-btn not-following';
                        this.innerHTML = '➕ 关注';
                    }
                    
                    // 更新粉丝数量
                    const followersCountElement = document.querySelector('.stat-card.clickable .stat-value');
                    if (followersCountElement) {
                        const currentCount = parseInt(followersCountElement.textContent);
                        followersCountElement.textContent = data.is_following ? currentCount + 1 : currentCount - 1;
                    }
                } else {
                    alert('操作失败: ' + data.message);
                    // 恢复原始状态
                    if (isCurrentlyFollowing) {
                        this.className = 'follow-btn following';
                        this.innerHTML = '✅ 已关注';
                    } else {
                        this.className = 'follow-btn not-following';
                        this.innerHTML = '➕ 关注';
                    }
                }
            })
            .catch(error => {
                console.error('关注操作失败:', error);
                alert('网络错误，请稍后再试');
                // 恢复原始状态
                if (isCurrentlyFollowing) {
                    this.className = 'follow-btn following';
                    this.innerHTML = '✅ 已关注';
                } else {
                    this.className = 'follow-btn not-following';
                    this.innerHTML = '➕ 关注';
                }
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    }

    // 文章卡片点击效果
    document.querySelectorAll('.post-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // 如果点击的不是链接，则跳转到文章页面
            if (e.target.tagName !== 'A') {
                const link = this.querySelector('.post-card-title a');
                if (link) {
                    window.location.href = link.href;
                }
            }
        });
    });

    // 添加页面加载动画
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.post-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${0.1 * (index % 4)}s`;
            card.style.animation = 'fadeInUp 0.6s ease-out forwards';
        });
    });

    // 为统计卡片添加点击提示效果
    document.querySelectorAll('.stat-card.clickable').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
            this.style.boxShadow = '0 20px 50px rgba(102, 126, 234, 0.3)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-5px) scale(1)';
            this.style.boxShadow = '0 15px 40px rgba(0, 0, 0, 0.15)';
        });
    });
    </script>
</body>
</html>