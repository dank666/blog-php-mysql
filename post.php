<?php
include 'includes/auth.php';
$config = include 'config.php';

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

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = null;
$author = null;
$error = null;

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
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error ? "错误" : htmlspecialchars($post['topic_title']); ?> - 博客系统</title>
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
            text-align: center; /* 居中对齐 */
        }
        
        .post-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            text-align: left; /* 文章内容左对齐 */
        }
        
        .post-title {
            margin-bottom: 15px;
            color: #333;
            font-size: 28px;
        }
        
        .post-meta {
            color: #666;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .post-meta img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            border: 2px solid #f0f0f0;
        }
        
        .post-content {
            line-height: 1.8;
            font-size: 16px;
            color: #444;
        }
        
        .post-image {
            max-width: 100%;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .error-message {
            color: #d9534f;
            text-align: center;
            margin: 40px 0;
            padding: 20px;
            background-color: #f9f2f2;
            border-radius: 5px;
        }
        
        .action-links {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            text-align: center;
        }
        
        .action-links a {
            color: #007bff;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .action-links a:hover {
            text-decoration: underline;
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
        
        /* 添加蓝色导航链接样式，与admin.php相同 */
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
        <div class="post-container">
            <?php if ($error): ?>
                <div class="error-message">
                    <h2>发生错误</h2>
                    <p><?php echo $error; ?></p>
                    <p><a href="index.php">返回首页</a></p>
                </div>
            <?php else: ?>
                <h1 class="post-title"><?php echo htmlspecialchars($post['topic_title']); ?></h1>
                
                <div class="post-meta">
                    <?php if ($author): ?>
                        <img src="avatars/<?php echo htmlspecialchars($author['avatar'] ?? 'default.jpg'); ?>" alt="头像">
                        <div>
                            <p>作者: <a href="profile.php?id=<?php echo $author['id']; ?>"><?php echo htmlspecialchars($author['username']); ?></a></p>
                            <p>发布日期: <?php echo htmlspecialchars($post['topic_date']); ?></p>
                        </div>
                    <?php else: ?>
                        <div>
                            <p>作者: [已删除]</p>
                            <p>发布日期: <?php echo htmlspecialchars($post['topic_date']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($post['image_filename'] && $post['image_filename'] != 'NONE'): ?>
                    <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" alt="文章图片" class="post-image">
                <?php endif; ?>
                
                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['topic_para'])); ?>
                </div>
                
                <div class="action-links">
                    <a href="index.php">返回首页</a>
                    
                    <?php if (isLoggedIn() && ($post['user_id'] == getCurrentUserId() || isAdmin())): ?>
                        <a href="edit_post.php?id=<?php echo $post_id; ?>">编辑文章</a>
                        <a href="delete_post.php?id=<?php echo $post_id; ?>" onclick="return confirm('确定要删除这篇文章吗?');">删除文章</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>