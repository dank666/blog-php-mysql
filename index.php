<?php
// 加载配置
$config = include 'config.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Using PHP And MySQL</title>
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
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">Blog | All Posts</span>
    </div>
    
    <div class="all-posts-container">
        <?php
        $conn = getDbConnection($config);
        
        // 准备查询语句
        $stmt = $conn->prepare("SELECT id, topic_title, topic_date, image_filename, topic_para FROM blog_table ORDER BY id DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<div class="post-container">';
                echo '<h2>' . htmlspecialchars($row["topic_title"]) . '</h2>';
                echo '<p><small>' . htmlspecialchars($row["topic_date"]) . '</small></p>';
                
                if (!empty($row["image_filename"]) && $row["image_filename"] !== "NONE") {
                    echo '<img src="images/' . htmlspecialchars($row["image_filename"]) . '" alt="Post Image" style="max-width:100%;height:auto;">';
                }
                
                echo '<p>' . nl2br(htmlspecialchars($row["topic_para"])) . '</p>';
                
                // 添加编辑和删除按钮
                echo '<div class="post-actions">';
                echo '<button class="edit-post-btn btn" data-id="' . $row["id"] . '">编辑</button>';
                echo '<button class="delete-post-btn btn" data-id="' . $row["id"] . '" style="background-color:#dc3545;">删除</button>';
                echo '</div>';
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
        <a href="index.html" class="btn">撰写新文章</a>
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