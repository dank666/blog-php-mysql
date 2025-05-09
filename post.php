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
$likes_count = 0;
$user_liked = false;
$current_user_id = isLoggedIn() ? getCurrentUserId() : 0;

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

        /* 点赞按钮样式 */
        .like-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .like-button.liked {
            background-color: #ffebee;
            color: #e53935;
            border-color: #ffcdd2;
        }
        
        .like-button svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* 修改按钮和操作区域的样式 */
        .post-actions {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* 统一按钮样式 */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }

        /* 评论区样式 */
        .comments-section {
            margin-top: 40px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .comment-form {
            margin-bottom: 30px;
        }

        .comment-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 80px;
            margin-bottom: 10px;
            font-family: inherit;
        }

        .comments-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .comment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .comment-author {
            font-weight: bold;
            color: #333;
        }

        .comment-date {
            color: #888;
            font-size: 0.9em;
        }

        .comment-content {
            margin: 10px 0;
            line-height: 1.4;
        }

        .comment-actions {
            margin-top: 10px;
            text-align: right;
        }

        .comment-delete {
            color: #e53935;
            background: none;
            border: none;
            padding: 0;
            font: inherit;
            cursor: pointer;
            text-decoration: underline;
        }

        .loading-comments {
            color: #888;
            text-align: center;
            padding: 10px;
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
                
                <!-- 点赞和操作按钮 -->
                <div class="post-actions">
                    <?php if (isLoggedIn()): ?>
                        <button id="likeButton" class="like-button <?php echo $user_liked ? 'liked' : ''; ?>" data-post-id="<?php echo $post_id; ?>">
                            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            点赞 (<span id="likesCount"><?php echo $likes_count; ?></span>)
                        </button>
                    <?php else: ?>
                        <span class="like-button">
                            <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            点赞 (<?php echo $likes_count; ?>)
                        </span>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn() && ($post['user_id'] == getCurrentUserId() || isAdmin())): ?>
                        <a href="edit_post.php?id=<?php echo $post_id; ?>" class="btn">编辑文章</a>
                        <a href="delete_post.php?id=<?php echo $post_id; ?>" class="btn btn-danger" onclick="return confirm('确定要删除这篇文章吗?');">删除文章</a>
                    <?php endif; ?>
                </div>
                
                <!-- 在 action-links div 前添加评论区 -->
                <div class="comments-section">
                    <h3>评论 (<span id="commentsCount">0</span>)</h3>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="comment-form">
                            <form id="commentForm">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <textarea name="content" placeholder="写下您的评论..." required></textarea>
                                <button type="submit" class="btn">发表评论</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p><a href="login.php">登录</a> 后才能发表评论</p>
                    <?php endif; ?>
                    
                    <ul class="comments-list" id="commentsList">
                        <!-- 评论将通过JavaScript加载 -->
                        <li class="loading-comments">加载评论中...</li>
                    </ul>
                </div>

                <div class="action-links">
                    <a href="index.php">返回首页</a>
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
                    commentsList.innerHTML = '<li class="no-comments">暂无评论，快来发表第一条评论吧！</li>';
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
                                    <button class="comment-delete" data-id="${comment.id}">删除</button>
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
                commentsList.innerHTML = '<li class="error-message">加载评论失败</li>';
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
                            '<li class="no-comments">暂无评论，快来发表第一条评论吧！</li>';
                    }
                }
            } else {
                alert('删除评论失败: ' + data.message);
            }
        })
        .catch(error => console.error('删除评论失败:', error));
    }

    // 提交评论
    document.getElementById('commentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = '发表中...';
        
        fetch('add_comment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitButton.disabled = false;
            submitButton.textContent = '发表评论';
            
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
            submitButton.textContent = '发表评论';
            alert('发表评论失败，请稍后再试');
        });
    });

    // 页面加载时获取评论
    document.addEventListener('DOMContentLoaded', function() {
        loadComments();
    });
    </script>
</body>
</html>