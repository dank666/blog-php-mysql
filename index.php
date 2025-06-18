<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';
include 'includes/database.php';
include 'includes/ensemble_recommendations.php';

// 连接数据库
$conn = getDbConnection($config);

// 获取当前用户
$currentUser = getCurrentUser();

// 获取当前用户ID（如果已登录）
$current_user_id = isLoggedIn() ? getCurrentUserId() : null;

// 获取推荐文章列表
$recommended_posts = getEnsembleRecommendations($conn, $current_user_id, 10);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>智域博客</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
    <style>
        /* 整体页面美化 */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Microsoft YaHei', 'PingFang SC', sans-serif;
            color: #333;
        }

        /* 删除原有的黑色top-bar样式 */
        .top-bar {
            display: none;
        }
        
        /* 主布局美化 */
        .main-layout {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            gap: 30px;
            padding: 30px 20px;
        }
        
        .sidebar-left {
            width: 300px;
            flex-shrink: 0;
        }
        
        .main-content {
            flex: 1;
        }
        
        /* 推荐文章面板美化 - 仅优化这部分 */
        .recommendation-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .recommendation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
            flex-shrink: 0;
        }

        .recommendation-title {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #refreshRecommendations {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #refreshRecommendations:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        #refreshRecommendations svg {
            fill: white;
            width: 18px;
            height: 18px;
        }
        
        .recommendation-list-container {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .recommendation-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            scrollbar-width: thin;
            scrollbar-color: rgba(102, 126, 234, 0.3) transparent;
        }

        .recommendation-list::-webkit-scrollbar {
            width: 6px;
        }

        .recommendation-list::-webkit-scrollbar-track {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
        }

        .recommendation-list::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 10px;
        }

        .recommendation-list::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
        
        .recommendation-item {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            position: relative;
            transition: all 0.3s ease;
            margin: 0 -12px;
            border-radius: 12px;
        }

        .recommendation-item:last-child {
            border-bottom: none;
        }

        .recommendation-item:hover {
            background: rgba(102, 126, 234, 0.08);
            transform: translateX(8px);
            box-shadow: 0 3px 15px rgba(102, 126, 234, 0.1);
        }
        
        .recommendation-item a {
            color: #444;
            text-decoration: none;
            font-size: 14px;
            display: block;
            margin-right: 40px;
            transition: all 0.3s ease;
            font-weight: 500;
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .recommendation-item a:hover {
            color: #667eea;
            text-shadow: 0 1px 3px rgba(102, 126, 234, 0.2);
        }
        
        /* 推荐排名样式美化 */
        .recommend-rank {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: white;
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .recommend-rank.rank-1 {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
            font-size: 13px;
            width: 30px;
            height: 30px;
            animation: goldGlow 2s ease-in-out infinite alternate;
        }

        .recommend-rank.rank-2 {
            background: linear-gradient(45deg, #C0C0C0, #A9A9A9);
            box-shadow: 0 3px 12px rgba(192, 192, 192, 0.4);
            font-size: 12px;
            width: 28px;
            height: 28px;
        }

        .recommend-rank.rank-3 {
            background: linear-gradient(45deg, #CD7F32, #B8860B);
            box-shadow: 0 3px 10px rgba(205, 127, 50, 0.4);
            font-size: 12px;
            width: 28px;
            height: 28px;
        }

        .recommend-rank.rank-4 { background: linear-gradient(45deg, #9b59b6, #8e44ad); }
        .recommend-rank.rank-5 { background: linear-gradient(45deg, #3498db, #2980b9); }
        .recommend-rank.rank-6 { background: linear-gradient(45deg, #1abc9c, #16a085); }
        .recommend-rank.rank-7 { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .recommend-rank.rank-8 { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .recommend-rank.rank-9 { background: linear-gradient(45deg, #f1c40f, #f39c12); }
        .recommend-rank.rank-10 { background: linear-gradient(45deg, #e67e22, #d35400); }

        @keyframes goldGlow {
            from { 
                box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
                transform: translateY(-50%) scale(1);
            }
            to { 
                box-shadow: 0 6px 20px rgba(255, 215, 0, 0.6);
                transform: translateY(-50%) scale(1.05);
            }
        }

        .recommendation-item:hover .recommend-rank {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .no-recommendations,
        .loading-recommendations,
        .error-recommendations {
            text-align: center;
            padding: 30px 20px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
            margin: 10px 0;
        }

        .no-recommendations {
            color: #999;
            background: rgba(102, 126, 234, 0.05);
            border: 2px dashed rgba(102, 126, 234, 0.2);
        }
        
        .loading-recommendations {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .error-recommendations {
            color: #e53935;
            background: rgba(229, 57, 53, 0.1);
            border: 1px solid rgba(229, 57, 53, 0.2);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .recommendation-stats {
            margin-top: 15px;
            padding: 10px 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            font-size: 12px;
            color: #666;
            text-align: center;
            border-top: 1px solid rgba(102, 126, 234, 0.1);
            flex-shrink: 0;
        }

        /* 文章容器美化 */
        .post-container {
            margin-bottom: 30px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
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
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .post-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .post-container:hover::before {
            opacity: 1;
        }
        
        .post-container h2 {
            margin-top: 0;
            margin-bottom: 15px;
        }

        /* 修复文章标题颜色问题 - 默认显示蓝色 */
        .post-container h2 a {
            text-decoration: none;
            color: #667eea !important; /* 默认显示蓝色而不是黑色 */
            font-size: 24px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .post-container h2 a:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }
        
        .post-container p {
            word-break: break-all;
            overflow-wrap: break-word;
            line-height: 1.6;
            color: #555;
            font-size: 16px;
        }
        
        /* 按钮美化 */
        .btn {
            display: inline-block;
            margin: 10px 5px;
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
        
        .post-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .edit-post-btn {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }

        .edit-post-btn:hover {
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.4);
        }
        
        .delete-post-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24) !important;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .delete-post-btn:hover {
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4) !important;
        }
        
        /* 作者信息美化 */
        .post-author {
            font-style: normal;
            color: #667eea;
            margin-bottom: 15px;
            font-weight: 500;
            padding: 8px 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            display: inline-block;
            font-size: 14px;
        }

        .post-date {
            font-size: 0.9em;
            color: #999;
        }

        .post-location {
            display: inline-flex;
            align-items: center;
            margin: 10px 0;
            font-size: 14px;
            color: #555;
            background: rgba(78, 205, 196, 0.1);
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 500;
        }

        .location-icon {
            margin-right: 5px;
            font-style: normal;
        }

        /* 文章图片美化 */
        .post-container img {
            border-radius: 15px;
            margin: 15px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .post-container img:hover {
            transform: scale(1.02);
        }

        /* 点赞信息美化 */
        .post-meta {
            margin-top: 15px;
            padding: 10px 15px;
            background: rgba(233, 30, 99, 0.1);
            border-radius: 15px;
            display: inline-block;
        }

        .like-count {
            color: #e91e63;
            font-weight: 500;
            font-size: 14px;
        }

        /* 分页美化 */
        .pagination {
            margin: 40px 0;
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

        /* 搜索结果信息美化 */
        .search-info {
            margin-bottom: 30px;
            padding: 15px 25px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            border-left: 4px solid #667eea;
            color: #667eea;
            font-weight: 500;
        }

        /* 空状态美化 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            margin: 30px 0;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state-text {
            color: #999;
            font-size: 18px;
            margin-bottom: 20px;
        }

        /* 加载状态样式美化 */
        .loading-recommendations {
            text-align: center;
            color: #667eea;
            font-size: 14px;
            padding: 15px 0;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
        }
        
        .error-recommendations {
            text-align: center;
            color: #e53935;
            font-size: 14px;
            padding: 15px 0;
            background: rgba(229, 57, 53, 0.1);
            border-radius: 10px;
        }

        /* 按钮旋转动画 */
        .spinning {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 中心按钮容器美化 */
        .center-button-container {
            text-align: center;
            margin: 40px 0;
            padding: 30px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .center-button-container .btn {
            font-size: 16px;
            padding: 15px 35px;
        }
        
        /* 响应式布局美化 */
        @media (max-width: 768px) {
            .main-layout {
                flex-direction: column;
                padding: 20px 15px;
                gap: 20px;
            }
            
            .sidebar-left {
                width: 100%;
                order: 2;
            }
            
            .main-content {
                order: 1;
            }

            .recommendation-panel {
                position: static;
                margin-bottom: 20px;
                padding: 20px;
            }

            .post-container {
                padding: 20px;
                margin-bottom: 20px;
            }

            .post-container h2 a {
                font-size: 20px;
            }

            .pagination {
                gap: 5px;
            }

            .pagination a, .pagination .current-page {
                padding: 10px 14px;
                font-size: 14px;
            }
        }

        /* 毛玻璃效果增强 */
        @supports (backdrop-filter: blur(10px)) {
            .post-container,
            .recommendation-panel,
            .center-button-container {
                backdrop-filter: blur(15px);
                background: rgba(255, 255, 255, 0.8);
            }
        }

        /* 深色模式准备 */
        @media (prefers-color-scheme: dark) {
            /* 可以在这里添加深色模式样式 */
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- 保持原有的内容结构 -->
    <div class="main-layout">
        <!-- 左侧边栏 - 推荐文章 -->
        <div class="sidebar-left">
            <div class="recommendation-panel">
                <div class="recommendation-header">
                    <h3 class="recommendation-title">🌟 推荐阅读</h3>
                    <button id="refreshRecommendations" title="刷新推荐">
                        <svg viewBox="0 0 24 24">
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
                        <p class="no-recommendations">✨ 暂无推荐内容</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 右侧主内容区 -->
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
                        echo '<div class="search-info">';
                        echo '🔍 搜索 "' . htmlspecialchars($search) . '" 的结果：共找到 ' . $result->num_rows . ' 篇文章';
                        echo '</div>';
                    }

                    while ($row = $result->fetch_assoc()) {
                        echo '<div class="post-container">';
                        
                        // 将标题修改为链接
                        echo '<h2><a href="post.php?id=' . $row["id"] . '">' 
                             . htmlspecialchars($row["topic_title"]) . '</a></h2>';
                        
                        // 显示作者和日期
                        echo '<div class="post-author">';
                        echo '👤 ' . (empty($row["author_name"]) ? '未知' : htmlspecialchars($row["author_name"])) . ' • ' . htmlspecialchars($row["topic_date"]);
                        echo '</div>';
                        
                        // 显示位置信息（如果有）
                        if (!empty($row['location'])):
                        echo '<div class="post-location">
                              <span class="location-icon">📍</span> 
                              ' . htmlspecialchars($row['location']) . '
                              </div>';
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
                        echo '<span class="like-count"><svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:middle;margin-right:3px;fill:#e91e63;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg> ';
                        echo $row["likes_count"] . ' 人点赞</span>';
                        echo '</div>';
                        
                        // 添加"阅读全文"链接
                        echo '<div style="margin-top: 20px;">';
                        echo '<a href="post.php?id=' . $row["id"] . '" class="btn">📖 阅读全文</a>';
                        echo '</div>';
                        
                        // 仅显示给作者或管理员的编辑和删除按钮
                        if (isLoggedIn() && (isAdmin() || $row["user_id"] == getCurrentUserId())) {
                            echo '<div class="post-actions">';
                            echo '<button class="edit-post-btn btn" data-id="' . $row["id"] . '">✏️ 编辑</button>';
                            echo '<button class="delete-post-btn btn" data-id="' . $row["id"] . '">🗑️ 删除</button>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                } else {
                    if ($search) {
                        echo '<div class="empty-state">';
                        echo '<div class="empty-state-icon">🔍</div>';
                        echo '<div class="empty-state-text">没有找到与 "' . htmlspecialchars($search) . '" 相关的文章</div>';
                        echo '<a href="index.php" class="btn">🏠 返回首页</a>';
                        echo '</div>';
                    } else {
                        echo '<div class="empty-state">';
                        echo '<div class="empty-state-icon">📝</div>';
                        echo '<div class="empty-state-text">目前还没有文章，快来发布第一篇吧！</div>';
                        if (isLoggedIn()) {
                            echo '<a href="new_post.php" class="btn">✍️ 撰写新文章</a>';
                        } else {
                            echo '<a href="login.php" class="btn">🔑 登录发布文章</a>';
                        }
                        echo '</div>';
                    }
                }
                
                $stmt->close();
                $countStmt->close();
                ?>
            </div>

            <div class="center-button-container">
                <?php if (isLoggedIn()): ?>
                    <a href="new_post.php" class="btn">✍️ 撰写新文章</a>
                <?php else: ?>
                    <a href="login.php" class="btn">🔑 登录发布文章</a>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">‹ 上一页</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $currentPage): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">下一页 ›</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
        recommendationsList.innerHTML = '<p class="loading-recommendations">🔄 正在更新推荐...</p>';
        
        // 请求新的推荐
        fetch('get_recommendations.php')
            .then(response => response.json())
            .then(data => {
                // 更新推荐列表
                if (data.length === 0) {
                    recommendationsList.innerHTML = '<p class="no-recommendations">✨ 暂无推荐内容</p>';
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
                recommendationsList.innerHTML = '<p class="error-recommendations">❌ 刷新推荐失败，请稍后再试</p>';
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