<!-- filepath: /home/wtejing/blog_github/blog-php-mysql/user_posts.php -->
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

// 获取用户ID
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 查询用户信息
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("用户不存在");
}

$user = $result->fetch_assoc();
$stmt->close();

// 查询用户的所有文章
$posts_stmt = $conn->prepare("
    SELECT id, topic_title, topic_date, image_filename 
    FROM blog_table 
    WHERE user_id = ? 
    ORDER BY id DESC
");
$posts_stmt->bind_param("i", $user_id);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> 的文章</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }

        .top-bar {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            text-align: center;
        }

        .main-content {
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .post-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }

        .post-card:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .post-card-link {
            text-decoration: none;
            color: inherit;
        }

        .post-card-image {
            height: 150px;
            overflow: hidden;
        }

        .post-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .post-card-content {
            padding: 15px;
        }

        .post-card-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 10px;
            color: #333;
        }

        .post-card-date {
            font-size: 14px;
            color: #666;
        }

        .no-posts {
            text-align: center;
            font-size: 16px;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="profile.php?id=<?php echo $user_id; ?>" style="color: #fff; text-decoration: none;">返回个人资料</a>
    </div>
    <div class="main-content">
        <h1><?php echo htmlspecialchars($user['username']); ?> 的文章</h1>
        <div class="posts-grid">
            <?php if ($posts_result->num_rows > 0): ?>
                <?php while ($post = $posts_result->fetch_assoc()): ?>
                    <div class="post-card">
                        <a href="post.php?id=<?php echo $post['id']; ?>" class="post-card-link">
                            <?php if (!empty($post['image_filename']) && $post['image_filename'] != 'NONE'): ?>
                                <div class="post-card-image">
                                    <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" alt="文章配图">
                                </div>
                            <?php else: ?>
                                <div class="post-card-image post-card-no-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                                    无图片
                                </div>
                            <?php endif; ?>
                            <div class="post-card-content">
                                <h3 class="post-card-title"><?php echo htmlspecialchars($post['topic_title']); ?></h3>
                                <div class="post-card-date"><?php echo htmlspecialchars($post['topic_date']); ?></div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-posts">暂无文章</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
$posts_stmt->close();
$conn->close();
?>