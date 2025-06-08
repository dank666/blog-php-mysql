<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';
include 'includes/database.php'; // 确保包含数据库连接

// 添加推荐系统
include 'includes/ensemble_recommendations.php';

// 连接数据库
$conn = getDbConnection($config);

// 获取当前用户
$currentUser = getCurrentUser();

// 获取当前用户ID（如果已登录）
$current_user_id = isLoggedIn() ? getCurrentUserId() : null;

// 获取推荐文章列表
$recommended_posts = getEnsembleRecommendations($conn, $current_user_id, 10);

// 添加调试信息
echo "<!-- 调试信息: 找到 " . count($recommended_posts) . " 篇推荐文章 -->";
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>智域博客</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        /* 保留原有样式 */
        .all-posts-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* 添加布局样式 */
        .main-layout {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            gap: 20px;
        }
        
        .sidebar-left {
            width: 280px;
            flex-shrink: 0;
        }
        
        .main-content {
            flex: 1;
        }
        
        /* 推荐文章列表样式 */
        .recommendation-panel {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .recommendation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .recommendation-title {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .recommendation-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .recommendation-item {
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
            position: relative;
        }
        
        .recommendation-item.featured {
            padding: 10px 0;
        }
        
        .recommendation-item a {
            color: #333;
            text-decoration: none;
            font-size: 14px;
            display: block;
            margin-right: 60px;
            transition: color 0.2s;
        }
        
        .recommendation-item a:hover {
            color: #4CAF50;
        }
        
        .recommend-level {
            position: absolute;
            right: 0;
            top: 8px;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
        }
        
        .recommend-level.high {
            background-color: #e53935;
        }
        
        .recommend-level.medium {
            background-color: #ff9800;
        }
        
        .no-recommendations {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 10px 0;
        }
        
        /* 推荐排名样式 */
        .recommend-rank {
            position: absolute;
            right: 0;
            top: 8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: white;
            background-color: #999;
        }

        /* 前三名特殊样式 */
        .recommend-rank.rank-1 {
            background-color: #FFD700; /* 金色 */
            box-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
            font-size: 14px;
            width: 28px;
            height: 28px;
        }

        .recommend-rank.rank-2 {
            background-color: #C0C0C0; /* 银色 */
            box-shadow: 0 0 4px rgba(192, 192, 192, 0.5);
            font-size: 13px;
            width: 26px;
            height: 26px;
        }

        .recommend-rank.rank-3 {
            background-color: #CD7F32; /* 铜色 */
            box-shadow: 0 0 3px rgba(205, 127, 50, 0.5);
            font-size: 13px;
            width: 26px;
            height: 26px;
        }

        /* 确保有足够的右边距容纳排名标志 */
        .recommendation-item a {
            margin-right: 35px;
        }
        
        /* 响应式布局 */
        @media (max-width: 768px) {
            .main-layout {
                flex-direction: column;
            }
            
            .sidebar-left {
                width: 100%;
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
        }
        
        /* 保留原有样式 */
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

        .post-date {
            font-size: 0.9em;
            color: #999;
        }

        .post-location {
            display: flex;
            align-items: center;
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }

        .location-icon {
            margin-right: 5px;
            font-style: normal;
        }

        .location-option {
            margin: 10px 0;
        }

        #updateLocationBtn {
            background-color: #f1f1f1;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        #updateLocationBtn:hover {
            background-color: #ddd;
        }

        .post-preview {
            margin-top: 10px;
            font-size: 1.1em;
        }

        /* 加载状态样式 */
        .loading-recommendations {
            text-align: center;
            color: #007bff;
            font-size: 14px;
            padding: 10px 0;
        }
        
        .error-recommendations {
            text-align: center;
            color: #e53935;
            font-size: 14px;
            padding: 10px 0;
        }

        /* 按钮旋转动画 */
        .spinning {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">智域博客</span>
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
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
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
    
    <!-- 新增的两栏布局容器 -->
    <div class="main-layout">
        <!-- 左侧边栏 - 推荐文章 -->
        <div class="sidebar-left">
            <div class="recommendation-panel">
                <div class="recommendation-header">
                    <h3 class="recommendation-title">推荐阅读</h3>
                    <button id="refreshRecommendations" title="刷新推荐">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                        </svg>
                    </button>
                </div>
                
                <div id="recommendationsList">
                    <?php if (!empty($recommended_posts)): ?>
                        <ul class="recommendation-list">
                            <?php foreach($recommended_posts as $index => $post): ?>
                                <li class="recommendation-item<?php echo $index < 3 ? ' featured' : ''; ?>">
                                    <a href="post.php?id=<?php echo $post['id']; ?>">
                                        <?php echo htmlspecialchars($post['topic_title']); ?>
                                    </a>
                                    <span class="recommend-rank rank-<?php echo $index + 1; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-recommendations">暂无推荐内容</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 右侧主内容区 - 保持原有功能不变 -->
        <div class="main-content">
            <div class="all-posts-container">
                <?php
                // 获取搜索关键词
                $search = isset($_GET['search']) ? trim($_GET['search']) : null;

                // 获取当前页码
                $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if ($currentPage < 1) $currentPage = 1;

                // 每页显示的文章数量
                $postsPerPage = 24;

                // 计算偏移量
                $offset = ($currentPage - 1) * $postsPerPage;

                // 修改查询语句，支持分页
                $query = "
                    SELECT b.id, b.topic_title, b.topic_date, b.image_filename, b.topic_para, b.user_id, b.location,
                           u.display_name as author_name,
                           (SELECT COUNT(*) FROM likes l WHERE l.post_id = b.id) as likes_count
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
                        
                        // 将标题修改为链接
                        echo '<h2><a href="post.php?id=' . $row["id"] . '" style="text-decoration:none;color:#333;">' 
                             . htmlspecialchars($row["topic_title"]) . '</a></h2>';
                        
                        // 显示作者和日期
                        echo '<div class="post-author">';
                        echo '作者: ' . (empty($row["author_name"]) ? '未知' : htmlspecialchars($row["author_name"])) . ' · ';
                        echo htmlspecialchars($row["topic_date"]);
                        echo '</div>';
                        
                        // 显示位置信息（如果有）
                        if (!empty($row['location'])):
                        echo '<span class="post-location">
                              <i class="location-icon">📍</i> 
                              ' . htmlspecialchars($row['location']) . '
                              </span>';
                        endif;

                        // 显示图片（如果有）
                        if (!empty($row["image_filename"]) && $row["image_filename"] !== "NONE") {
                            echo '<img src="images/' . htmlspecialchars($row["image_filename"]) . '" alt="Post Image" style="max-width:100%;height:auto;">';
                        }
                        
                        // 显示文章摘要（前200个字符）
                        $content = $row["topic_para"];
                        $excerpt = mb_substr($content, 0, 200, 'UTF-8');
                        if (mb_strlen($content, 'UTF-8') > 200) {
                            $excerpt .= '...';
                        }
                        echo '<p>' . nl2br(htmlspecialchars($excerpt)) . '</p>';
                        
                        // 显示点赞数
                        echo '<div class="post-meta">';
                        echo '<span class="like-count"><svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;margin-right:3px;fill:#e53935;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg> ';
                        echo $row["likes_count"] . ' 人点赞</span>';
                        echo '</div>';
                        
                        // 添加"阅读全文"链接
                        echo '<div style="margin-top: 15px;">';
                        echo '<a href="post.php?id=' . $row["id"] . '" class="btn">阅读全文</a>';
                        echo '</div>';
                        
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
                    if ($search) {
                        echo "<center><span>没有找到与 '" . htmlspecialchars($search) . "' 相关的文章</span></center>";
                    } else {
                        echo "<center><span>目前还没有文章</span></center>";
                    }
                }
                
                $stmt->close();
                $countStmt->close();
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
        </div>
    </div>

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

    // 推荐刷新功能
    document.getElementById('refreshRecommendations').addEventListener('click', function() {
        const button = this;
        const recommendationsList = document.getElementById('recommendationsList');
        
        // 显示加载状态
        button.classList.add('spinning');
        recommendationsList.innerHTML = '<p class="loading-recommendations">正在更新推荐...</p>';
        
        // 请求新的推荐
        fetch('get_recommendations.php')
            .then(response => response.json())
            .then(data => {
                // 更新推荐列表
                if (data.length === 0) {
                    recommendationsList.innerHTML = '<p class="no-recommendations">暂无推荐内容</p>';
                } else {
                    let html = '<ul class="recommendation-list">';
                    
                    data.forEach((post, index) => {
                        const featuredClass = index < 3 ? ' featured' : '';
                        html += `
                            <li class="recommendation-item${featuredClass}">
                                <a href="post.php?id=${post.id}">
                                    ${escapeHtml(post.topic_title)}
                                </a>
                                <span class="recommend-rank rank-${index + 1}">
                                    ${index + 1}
                                </span>
                            </li>
                        `;
                    });
                    
                    html += '</ul>';
                    recommendationsList.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('获取推荐失败:', error);
                recommendationsList.innerHTML = '<p class="error-recommendations">刷新推荐失败，请稍后再试</p>';
            })
            .finally(() => {
                button.classList.remove('spinning');
            });
    });

    // HTML转义函数
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    </script>
</body>
</html>