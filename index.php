<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

// 连接数据库
function getDbConnection($config) {
    $conn = new mysqli(
        $config['db']['host'],
        $config['db']['username'],
        $config['db']['password'],
        $config['db']['database']
    );
    
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    
    return $conn;
}

// 获取当前用户
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>多用户博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .all-posts-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .post-container {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .post-container p {
            word-break: break-all;
            overflow-wrap: break-word;
        }
        
        .btn {
            display: inline-block;
            margin: 10px 0;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .post-actions {
            margin-top: 15px;
        }
        
        .edit-post-btn, .delete-post-btn {
            padding: 5px 10px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        /* 新增导航栏样式 */
        .nav-bar {
            background-color: #f8f9fa;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-link {
            color: #007bff;
            text-decoration: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .post-author {
            font-style: italic;
            color: #666;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">多用户博客系统</span>
    </div>
    
    <div class="nav-bar">
        <div class="nav-links">
            <a href="index.php" class="nav-link">首页</a>
            <?php if (isLoggedIn()): ?>
                <a href="new_post.php" class="nav-link">发布文章</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="nav-link">管理面板</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <?php if (isLoggedIn()): ?>
                欢迎，<a href="profile.php" class="nav-link"><?php echo htmlspecialchars($currentUser['display_name']); ?></a>
                <a href="settings.php" class="nav-link">设置</a>
                <a href="logout.php" class="nav-link">退出</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">登录</a>
                <a href="register.php" class="nav-link">注册</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="all-posts-container">
        <?php
        $conn = getDbConnection($config);
        
        // 准备查询语句，连接用户表显示作者信息
        $stmt = $conn->prepare("
            SELECT b.id, b.topic_title, b.topic_date, b.image_filename, b.topic_para, b.user_id, 
                   u.display_name as author_name 
            FROM blog_table b 
            LEFT JOIN users u ON b.user_id = u.id 
            ORDER BY b.id DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<div class="post-container">';
                echo '<h2>' . htmlspecialchars($row["topic_title"]) . '</h2>';
                
                // 显示作者和日期
                echo '<div class="post-author">';
                echo '作者: ' . (empty($row["author_name"]) ? '未知' : htmlspecialchars($row["author_name"])) . ' · ';
                echo htmlspecialchars($row["topic_date"]);
                echo '</div>';
                
                if (!empty($row["image_filename"]) && $row["image_filename"] !== "NONE") {
                    echo '<img src="images/' . htmlspecialchars($row["image_filename"]) . '" alt="Post Image" style="max-width:100%;height:auto;">';
                }
                
                echo '<p>' . nl2br(htmlspecialchars($row["topic_para"])) . '</p>';
                
                // 仅显示给作者或管理员的编辑和删除按钮
                if (isLoggedIn() && (isAdmin() || $row["user_id"] == getCurrentUserId())) {
                    echo '<div class="post-actions">';
                    echo '<button class="edit-post-btn btn" data-id="' . $row["id"] . '">编辑</button>';
                    echo '<button class="delete-post-btn btn" data-id="' . $row["id"] . '" style="background-color:#dc3545;">删除</button>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
        } else {
            echo "<center><span>暂无博客文章</span></center>";
        }
        
        $stmt->close();
        $conn->close();
        ?>
    </div>

    <center>
        <?php if (isLoggedIn()): ?>
            <a href="new_post.php" class="btn">撰写新文章</a>
        <?php else: ?>
            <a href="login.php" class="btn">登录发布文章</a>
        <?php endif; ?>
    </center>

    <script>
    // 删除帖子功能
    document.querySelectorAll('.delete-post-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-id');
            if (confirm('确定要删除这篇文章吗？')) {
                window.location.href = `delete_post.php?id=${postId}`;
            }
        });
    });

    // 编辑帖子功能
    document.querySelectorAll('.edit-post-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-id');
            window.location.href = `edit_post.php?id=${postId}`;
        });
    });
    </script>
</body>
</html>