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
        
        <!-- 搜索框 -->
        <div class="nav-search">
            <form action="index.php" method="GET">
                <input type="text" name="search" placeholder="搜索文章标题、内容或作者..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">搜索</button>
            </form>
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

        // 获取搜索关键词
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;

        // 获取当前页码
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;

        // 每页显示的文章数量
        $postsPerPage = 5;

        // 计算偏移量
        $offset = ($currentPage - 1) * $postsPerPage;

        // 修改查询语句，支持分页
        $query = "
            SELECT b.id, b.topic_title, b.topic_date, b.image_filename, b.topic_para, b.user_id, 
                   u.display_name as author_name 
            FROM blog_table b 
            LEFT JOIN users u ON b.user_id = u.id 
        ";

        // 如果有搜索关键词，添加 WHERE 条件
        if ($search) {
            $query .= "WHERE b.topic_title LIKE ? OR b.topic_para LIKE ? OR u.display_name LIKE ? ";
        }

        $query .= "ORDER BY b.id DESC LIMIT ? OFFSET ?";

        // 准备查询语句
        $stmt = $conn->prepare($query);

        // 如果有搜索关键词，绑定参数
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $stmt->bind_param('sssii', $searchTerm, $searchTerm, $searchTerm, $postsPerPage, $offset);
        } else {
            $stmt->bind_param('ii', $postsPerPage, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        // 获取文章总数，用于计算总页数
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM blog_table b 
            LEFT JOIN users u ON b.user_id = u.id 
        ";
        if ($search) {
            $countQuery .= "WHERE b.topic_title LIKE ? OR b.topic_para LIKE ? OR u.display_name LIKE ?";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
        } else {
            $countStmt = $conn->prepare($countQuery);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalPosts = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalPosts / $postsPerPage);

        if ($result->num_rows > 0) {
            if ($search) {
                echo '<div style="margin-bottom: 20px; color: #666;">';
                echo '搜索 "' . htmlspecialchars($search) . '" 的结果：共找到 ' . $result->num_rows . ' 篇文章';
                echo '</div>';
            }

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
            echo "<center><span>没有找到与 '" . htmlspecialchars($search) . "' 相关的文章</span></center>";
        }
        
        $stmt->close();
        $countStmt->close();
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

    <center>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?php echo $currentPage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">&laquo; 上一页</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $currentPage): ?>
                    <span class="current-page"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?php echo $currentPage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">下一页 &raquo;</a>
            <?php endif; ?>
        </div>
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