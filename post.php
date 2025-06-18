<?php
// 添加错误报告以帮助调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'includes/auth.php';
include 'includes/database.php';

$config = include 'config.php';
$conn = getDbConnection($config);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 获取文章ID
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 检查文章是否存在
$stmt = $conn->prepare("SELECT * FROM blog_table WHERE id = ?");
$stmt->bind_param("i", $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    die("文章不存在");
}

// 记录用户访问
if (!isset($_SESSION['viewed_posts'])) {
    $_SESSION['viewed_posts'] = [];
}

if (!in_array($postId, $_SESSION['viewed_posts'])) {
    // 将文章ID添加到已访问列表
    $_SESSION['viewed_posts'][] = $postId;

    // 插入或更新浏览记录
    $stmt = $conn->prepare("INSERT INTO blog_views (post_id) VALUES (?)");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $stmt->close();
}

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = null;
$author = null;
$error = null;
$likes_count = 0;
$user_liked = false;
$current_user_id = isLoggedIn() ? getCurrentUserId() : 0;
$views_count = 0; // 初始化浏览量变量

if ($post_id <= 0) {
    $error = "无效的文章ID";
} else {
    // 安全地查询文章信息
    $stmt = $conn->prepare("
        SELECT b.*, u.username, u.avatar, u.id as author_id
        FROM blog_table b
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    
    if (!$stmt) {
        $error = "数据库查询准备失败: " . $conn->error;
    } else {
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = "文章不存在";
        } else {
            $post = $result->fetch_assoc();
            
            // 获取作者信息(即使作者可能已被删除)
            if ($post['user_id']) {
                $authorStmt = $conn->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
                if ($authorStmt) {
                    $authorStmt->bind_param("i", $post['user_id']);
                    $authorStmt->execute();
                    $authorResult = $authorStmt->get_result();
                    if ($authorResult->num_rows > 0) {
                        $author = $authorResult->fetch_assoc();
                    }
                    $authorStmt->close();
                }
            }

            // 获取点赞数
            $likes_stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
            $likes_stmt->bind_param("i", $post_id);
            $likes_stmt->execute();
            $likes_result = $likes_stmt->get_result();
            $likes_count = $likes_result->fetch_assoc()['count'];
            $likes_stmt->close();
            
            // 检查当前用户是否已点赞
            if ($current_user_id > 0) {
                $user_liked_stmt = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
                $user_liked_stmt->bind_param("ii", $post_id, $current_user_id);
                $user_liked_stmt->execute();
                $user_liked = $user_liked_stmt->get_result()->num_rows > 0;
                $user_liked_stmt->close();
            }
        }
        $stmt->close();
    }
}

// 记录文章访问量
if (!$error && isset($post['id'])) {
    $post_id = $post['id'];
    
    // 未登录的用户仍然用IP记录
    $viewer_id = isLoggedIn() ? getCurrentUserId() : 'ip_' . $_SERVER['REMOTE_ADDR'];
    
    // 创建或验证视图表
    $conn->query("
        CREATE TABLE IF NOT EXISTS blog_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            viewer_id VARCHAR(64) NOT NULL,
            view_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            view_date_only DATE DEFAULT (CURRENT_DATE),
            UNIQUE KEY (post_id, viewer_id, view_date_only),
            FOREIGN KEY (post_id) REFERENCES blog_table(id) ON DELETE CASCADE
        )
    ");
    
    // 尝试插入浏览记录
    try {
        $view_stmt = $conn->prepare("
            INSERT IGNORE INTO blog_views (post_id, viewer_id, view_date_only) 
            VALUES (?, ?, CURRENT_DATE())
        ");
        $view_stmt->bind_param("is", $post_id, $viewer_id);
        $view_stmt->execute();
        $view_stmt->close();
    } catch (Exception $e) {
        error_log("浏览量记录失败: " . $e->getMessage());
    }
    
    // 获取文章浏览量
    try {
        $views_stmt = $conn->prepare("SELECT COUNT(*) as count FROM blog_views WHERE post_id = ?");
        $views_stmt->bind_param("i", $post_id);
        $views_stmt->execute();
        $views_result = $views_stmt->get_result();
        $views_count = $views_result->fetch_assoc()['count'];
        $views_stmt->close();
    } catch (Exception $e) {
        error_log("浏览量获取失败: " . $e->getMessage());
        $views_count = 0;
    }
}

// 获取文章的标签
$stmt = $conn->prepare("
    SELECT t.name 
    FROM tags t
    JOIN post_tags pt ON t.id = pt.tag_id
    WHERE pt.post_id = ?
");
$stmt->bind_param("i", $postId);
$stmt->execute();
$tagsResult = $stmt->get_result();
$tags = [];
while ($row = $tagsResult->fetch_assoc()) {
    $tags[] = $row['name'];
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error ? "错误" : htmlspecialchars($post['topic_title']); ?> - 智域博客</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* 文章容器美化 - 与主界面post-container风格一致 */
        .post-container {
            margin-bottom: 30px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .post-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
            opacity: 1;
        }
        
        /* 文章标题美化 */
        .post-title {
            margin: 0 0 30px 0;
            color: #667eea;
            font-size: 32px;
            font-weight: 700;
            line-height: 1.3;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* 文章元信息美化 */
        .post-meta {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(102, 126, 234, 0.08);
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }
        
        .post-meta img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 20px;
            border: 3px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .post-meta-info {
            flex: 1;
        }

        .post-meta-info p {
            margin: 5px 0;
            font-size: 15px;
            color: #555;
        }

        .post-meta-info a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .post-meta-info a:hover {
            color: #764ba2;
        }
        
        /* 发布日期和位置信息美化 */
        .post-date {
            display: inline-flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 8px 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            font-size: 14px;
            color: #667eea;
            font-weight: 500;
        }

        .post-location {
            display: inline-flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 8px 15px;
            background: rgba(78, 205, 196, 0.1);
            border-radius: 20px;
            font-size: 14px;
            color: #4ecdc4;
            font-weight: 500;
        }

        .location-icon {
            margin-right: 5px;
            font-style: normal;
        }
        
        /* 文章图片美化 */
        .post-image {
            margin: 25px 0;
            text-align: center;
        }

        .post-image img {
            max-width: 100%;
            height: auto;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .post-image img:hover {
            transform: scale(1.02);
        }
        
        /* 文章内容美化 */
        .post-content {
            line-height: 1.8;
            font-size: 17px;
            color: #444;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 15px;
            padding: 30px;
            margin: 25px 0;
            border: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
        }

        .post-content::before {
            content: '📝';
            position: absolute;
            top: -10px;
            left: 20px;
            background: white;
            padding: 0 10px;
            font-size: 20px;
        }
        
        /* 标签区域美化 */
        .post-tags {
            margin: 25px 0;
            padding: 20px;
            background: rgba(78, 205, 196, 0.08);
            border-radius: 15px;
            border: 1px solid rgba(78, 205, 196, 0.2);
        }

        .post-tags strong {
            color: #4ecdc4;
            font-size: 16px;
            margin-right: 10px;
        }

        .tag-item {
            display: inline-block;
            margin: 5px 8px 5px 0;
            padding: 6px 15px;
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(78, 205, 196, 0.3);
        }
        
        /* 操作按钮区域美化 */
        .post-actions {
            margin-top: 30px;
            padding: 25px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        /* 点赞按钮美化 */
        .like-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }
        
        .like-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }
        
        .like-button.liked {
            background: linear-gradient(45deg, #e91e63, #ad1457);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }
        
        .like-button svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* 统一按钮样式 */
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4);
        }

        /* 关注按钮美化 */
        .follow-btn-small {
            padding: 6px 15px;
            border-radius: 20px;
            border: 2px solid #667eea;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            margin-left: 10px;
        }

        .follow-btn-small.following {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        .follow-btn-small.not-following {
            background: white;
            color: #667eea;
            border-color: #667eea;
        }

        .follow-btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* 评论区美化 */
        .comments-section {
            margin-top: 40px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .comments-section h3 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #667eea;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .comments-section h3::before {
            content: '💬';
            font-size: 28px;
        }

        /* 评论表单美化 */
        .comment-form {
            margin-bottom: 30px;
            padding: 25px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .comment-form textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 15px;
            min-height: 100px;
            margin-bottom: 15px;
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .comment-form textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
        }

        /* 评论列表美化 */
        .comments-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .comment-item {
            padding: 20px;
            margin-bottom: 20px;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .comment-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
        }

        .comment-author {
            font-weight: 600;
            color: #667eea;
            font-size: 15px;
        }

        .comment-date {
            color: #999;
            font-size: 13px;
        }

        .comment-content {
            margin: 15px 0;
            line-height: 1.6;
            color: #444;
            font-size: 15px;
        }

        .comment-actions {
            margin-top: 15px;
            text-align: right;
        }

        .comment-delete {
            color: #ff6b6b;
            background: none;
            border: none;
            padding: 5px 10px;
            font: inherit;
            cursor: pointer;
            border-radius: 15px;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .comment-delete:hover {
            background: rgba(255, 107, 107, 0.1);
            color: #ee5a24;
        }

        /* 状态提示美化 */
        .loading-comments,
        .no-comments,
        .error-message {
            text-align: center;
            padding: 30px 20px;
            border-radius: 15px;
            margin: 20px 0;
            font-size: 16px;
        }

        .loading-comments {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .no-comments {
            color: #999;
            background: rgba(102, 126, 234, 0.05);
            border: 2px dashed rgba(102, 126, 234, 0.2);
        }

        .error-message {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.2);
            margin: 40px 0;
        }
        
        /* 返回链接美化 */
        .action-links {
            margin-top: 40px;
            text-align: center;
            padding: 25px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .action-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            padding: 12px 25px;
            border-radius: 25px;
            background: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .action-links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .post-container {
                padding: 25px;
            }

            .post-title {
                font-size: 26px;
            }

            .post-meta {
                flex-direction: column;
                text-align: center;
            }

            .post-meta img {
                margin: 0 0 15px 0;
            }

            .post-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .post-actions > * {
                margin-bottom: 10px;
            }

            .comments-section {
                padding: 20px;
            }

            .comment-form {
                padding: 20px;
            }
        }

        /* 毛玻璃效果增强 */
        @supports (backdrop-filter: blur(10px)) {
            .post-container,
            .comments-section {
                backdrop-filter: blur(15px);
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-content">
        <div class="post-container">
            <?php if ($error): ?>
                <div class="error-message">
                    <h2>🚫 发生错误</h2>
                    <p><?php echo $error; ?></p>
                    <p><a href="index.php" class="btn">🏠 返回首页</a></p>
                </div>
            <?php else: ?>
                <h1 class="post-title"><?php echo htmlspecialchars($post['topic_title']); ?></h1>
                
                <div class="post-meta">
                    <?php if ($author): ?>
                        <img src="avatars/<?php echo htmlspecialchars($author['avatar'] ?? 'default.jpg'); ?>" alt="头像">
                        <div class="post-meta-info">
                            <p>👤 作者: <a href="profile.php?id=<?php echo $author['id']; ?>"><?php echo htmlspecialchars($author['username']); ?></a>
                                <?php 
                                // 如果当前用户已登录且不是作者本人，显示关注按钮
                                if (isLoggedIn() && getCurrentUserId() != $author['id']): 
                                    // 重新连接数据库检查关注状态
                                    $conn_follow = new mysqli(
                                        $config['db']['host'],
                                        $config['db']['username'],
                                        $config['db']['password'],
                                        $config['db']['database']
                                    );
                                    // 检查是否已关注
                                    $current_user_id = getCurrentUserId();
                                    $author_id = $author['id'];
                                    $check_follow_stmt = $conn_follow->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
                                    $check_follow_stmt->bind_param("ii", $current_user_id, $author_id);
                                    $check_follow_stmt->execute();
                                    $is_following = $check_follow_stmt->get_result()->num_rows > 0;
                                    $check_follow_stmt->close();
                                    $conn_follow->close();
                                ?>
                                    <button id="authorFollowBtn" class="follow-btn-small <?php echo $is_following ? 'following' : 'not-following'; ?>" 
                                            data-userid="<?php echo $author['id']; ?>">
                                        <?php echo $is_following ? '已关注' : '关注'; ?>
                                    </button>
                                <?php endif; ?>
                            </p>
                            <p>👁️ 浏览量: <?php echo $views_count; ?></p>
                        </div>
                    <?php else: ?>
                        <div class="post-meta-info">
                            <p>👤 作者: [已删除]</p>
                            <p>👁️ 浏览量: <?php echo $views_count; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="post-date">📅 发布于: <?php echo htmlspecialchars($post['topic_date']); ?></div>

                <?php if (!empty($post['location'])): ?>
                <div class="post-location">
                    <i class="location-icon">📍</i> 
                    <span><?php echo htmlspecialchars($post['location']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($post['image_filename']) && $post['image_filename'] !== "NONE"): ?>
                    <div class="post-image">
                        <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" 
                             alt="<?php echo htmlspecialchars($post['topic_title']); ?>">
                    </div>
                <?php endif; ?>

                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['topic_para'])); ?>
                </div>
                
                <!-- 显示文章标签 -->
                <?php if (!empty($tags)): ?>
                <div class="post-tags">
                    <strong>🏷️ 标签：</strong>
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag-item"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- 点赞和操作按钮 -->
                <div class="post-actions">
                    <?php if (isLoggedIn()): ?>
                        <button id="likeButton" class="like-button <?php echo $user_liked ? 'liked' : ''; ?>" data-post-id="<?php echo $post_id; ?>">
                            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            <span id="likesCount"><?php echo $likes_count; ?></span> 人点赞
                        </button>
                    <?php else: ?>
                        <span class="like-button">
                            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            <?php echo $likes_count; ?> 人点赞
                        </span>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn() && ($post['user_id'] == getCurrentUserId() || isAdmin())): ?>
                        <a href="edit_post.php?id=<?php echo $post_id; ?>" class="btn">✏️ 编辑文章</a>
                        <a href="delete_post.php?id=<?php echo $post_id; ?>" class="btn btn-danger" onclick="return confirm('确定要删除这篇文章吗?');">🗑️ 删除文章</a>
                    <?php endif; ?>
                </div>
                
                <!-- 评论区 -->
                <div class="comments-section">
                    <h3>评论 (<span id="commentsCount">0</span>)</h3>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="comment-form">
                            <form id="commentForm">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <textarea name="content" placeholder="💭 写下您的精彩评论..." required></textarea>
                                <button type="submit" class="btn">💬 发表评论</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="comment-form">
                            <p style="text-align: center; color: #999; margin: 20px 0;">
                                <a href="login.php" class="btn">🔑 登录</a> 后才能发表评论
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <ul class="comments-list" id="commentsList">
                        <li class="loading-comments">🔄 加载评论中...</li>
                    </ul>
                </div>

                <div class="action-links">
                    <a href="index.php">🏠 返回首页</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // 点赞功能
    const likeButton = document.getElementById('likeButton');
    if (likeButton) {
        likeButton.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            
            fetch('toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('likesCount').textContent = data.likes_count;
                    likeButton.classList.toggle('liked', data.user_liked);
                } else {
                    alert('操作失败: ' + data.message);
                }
            })
            .catch(error => console.error('点赞操作失败:', error));
        });
    }

    // 加载评论
    function loadComments() {
        const commentsList = document.getElementById('commentsList');
        
        fetch('get_comments.php?post_id=<?php echo $post_id; ?>')
            .then(response => response.json())
            .then(data => {
                commentsList.innerHTML = '';
                
                if (data.length === 0) {
                    commentsList.innerHTML = '<li class="no-comments">📝 暂无评论，快来发表第一条评论吧！</li>';
                } else {
                    data.forEach(comment => {
                        const li = document.createElement('li');
                        li.className = 'comment-item';
                        li.dataset.id = comment.id;
                        
                        li.innerHTML = `
                            <div class="comment-header">
                                <span class="comment-author">${comment.username}</span>
                                <span class="comment-date">${comment.created_at}</span>
                            </div>
                            <div class="comment-content">${comment.content}</div>
                            ${(<?php echo $current_user_id; ?> === parseInt(comment.user_id) || <?php echo isAdmin() ? 'true' : 'false'; ?>) ? 
                                `<div class="comment-actions">
                                    <button class="comment-delete" data-id="${comment.id}">🗑️ 删除</button>
                                </div>` : ''}
                        `;
                        
                        commentsList.appendChild(li);
                    });
                    
                    // 添加删除评论的事件监听
                    document.querySelectorAll('.comment-delete').forEach(button => {
                        button.addEventListener('click', function() {
                            const commentId = this.getAttribute('data-id');
                            if (confirm('确定要删除这条评论吗？')) {
                                deleteComment(commentId);
                            }
                        });
                    });
                }
                
                document.getElementById('commentsCount').textContent = data.length;
            })
            .catch(error => {
                console.error('加载评论失败:', error);
                commentsList.innerHTML = '<li class="error-message">❌ 加载评论失败</li>';
            });
    }

    // 删除评论
    function deleteComment(commentId) {
        fetch('delete_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${commentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const commentElement = document.querySelector(`.comment-item[data-id="${commentId}"]`);
                if (commentElement) {
                    commentElement.remove();
                    const currentCount = parseInt(document.getElementById('commentsCount').textContent);
                    document.getElementById('commentsCount').textContent = currentCount - 1;
                    
                    // 如果没有评论了，显示"暂无评论"提示
                    if (currentCount - 1 === 0) {
                        document.getElementById('commentsList').innerHTML = 
                            '<li class="no-comments">📝 暂无评论，快来发表第一条评论吧！</li>';
                    }
                }
            } else {
                alert('删除评论失败: ' + data.message);
            }
        })
        .catch(error => console.error('删除评论失败:', error));
    }

    // 提交评论
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = '💬 发表中...';
            
            fetch('add_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitButton.disabled = false;
                submitButton.textContent = '💬 发表评论';
                
                if (data.success) {
                    this.reset();
                    loadComments();
                } else {
                    alert('发表评论失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('发表评论失败:', error);
                submitButton.disabled = false;
                submitButton.textContent = '💬 发表评论';
                alert('发表评论失败，请稍后再试');
            });
        });
    }

    // 作者关注按钮功能
    const authorFollowBtn = document.getElementById('authorFollowBtn');
    if (authorFollowBtn) {
        authorFollowBtn.addEventListener('click', function() {
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
                    // 更新按钮状态和文本
                    if (data.is_following) {
                        authorFollowBtn.className = 'follow-btn-small following';
                        authorFollowBtn.textContent = '已关注';
                    } else {
                        authorFollowBtn.className = 'follow-btn-small not-following';
                        authorFollowBtn.textContent = '关注';
                    }
                } else {
                    alert('操作失败: ' + data.message);
                }
            })
            .catch(error => console.error('关注操作失败:', error));
        });
    }

    // 页面加载时获取评论
    document.addEventListener('DOMContentLoaded', function() {
        loadComments();
    });
    </script>
</body>
</html>