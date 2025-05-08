<?php
include 'includes/auth.php';
include 'includes/database.php';
$config = include 'config.php';

// CSRF Token函数
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}

// 要求管理员权限
requireAdmin();

// 连接数据库
$conn = getDbConnection();

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

$error = '';
$success = '';

// 分页设置
$usersPerPage = 10; // 每页显示的用户数
$postsPerPage = 10; // 每页显示的文章数
$userPage = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
$postPage = isset($_GET['post_page']) ? (int)$_GET['post_page'] : 1;

// 确保页码至少为1
if ($userPage < 1) $userPage = 1;
if ($postPage < 1) $postPage = 1;

// 处理用户角色更改
if (isset($_POST['update_role'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "安全验证失败，请重试";
    } else {
        $userId = (int)$_POST['user_id'];
        $newRole = $_POST['role'];
        
        if ($newRole === 'admin' || $newRole === 'user') {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $newRole, $userId);
            
            if ($stmt->execute()) {
                $success = "用户角色已更新";
            } else {
                $error = "更新角色失败: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// 处理用户删除
if (isset($_GET['delete_user'])) {
    $userId = (int)$_GET['delete_user'];
    
    // 阻止删除自己的账号
    if ($userId == getCurrentUserId()) {
        $error = "不能删除当前登录的管理员账号";
    } else {
        // 开始事务，确保删除用户及其文章的原子性
        $conn->begin_transaction();
        
        try {
            // 先查询用户所有文章的图片
            $stmt = $conn->prepare("SELECT image_filename FROM blog_table WHERE user_id = ? AND image_filename != 'NONE'");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 删除所有图片文件
            while ($row = $result->fetch_assoc()) {
                $imagePath = "images/" . $row['image_filename'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            // 删除用户所有文章
            $stmt = $conn->prepare("DELETE FROM blog_table WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // 删除用户头像
            $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['avatar']) && $row['avatar'] != 'default.jpg') {
                    $avatarPath = "avatars/" . $row['avatar'];
                    if (file_exists($avatarPath)) {
                        unlink($avatarPath);
                    }
                }
            }
            
            // 删除用户账号
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // 提交事务
            $conn->commit();
            $success = "用户及其所有内容已删除";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "删除用户失败: " . $e->getMessage();
        }
    }
}

// 处理文章删除
if (isset($_GET['delete_post'])) {
    $postId = (int)$_GET['delete_post'];
    
    // 查询文章图片
    $stmt = $conn->prepare("SELECT image_filename FROM blog_table WHERE id = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // 删除关联图片
        if (!empty($row['image_filename']) && $row['image_filename'] != 'NONE') {
            $imagePath = "images/" . $row['image_filename'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // 删除文章记录
        $stmt = $conn->prepare("DELETE FROM blog_table WHERE id = ?");
        $stmt->bind_param("i", $postId);
        
        if ($stmt->execute()) {
            $success = "文章已删除";
        } else {
            $error = "删除文章失败: " . $conn->error;
        }
    }
    $stmt->close();
}

// 获取用户总数
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// 计算总页数和偏移量
$totalUserPages = ceil($totalUsers / $usersPerPage);
$userOffset = ($userPage - 1) * $usersPerPage;

// 获取当前页的用户
$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $usersPerPage, $userOffset);
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

// 获取文章总数
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM blog_table");
$countStmt->execute();
$totalPosts = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// 计算总页数和偏移量
$totalPostPages = ceil($totalPosts / $postsPerPage);
$postOffset = ($postPage - 1) * $postsPerPage;

// 获取当前页的文章
$stmt = $conn->prepare("
    SELECT b.id, b.topic_title, b.topic_date, u.username, u.id as user_id 
    FROM blog_table b 
    JOIN users u ON b.user_id = u.id 
    ORDER BY b.id DESC LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $postsPerPage, $postOffset);
$stmt->execute();
$posts = $stmt->get_result();
$stmt->close();

$conn->close();

// 获取当前活动的标签
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理面板 - 多用户博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .admin-section {
            margin-bottom: 40px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .admin-table th, .admin-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .admin-table th {
            background-color: #f5f5f5;
        }
        .admin-table tr:hover {
            background-color: #f9f9f9;
        }
        .action-btn {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 5px;
            border-radius: 3px;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .edit-btn {
            background-color: #28a745;
        }
        .delete-btn {
            background-color: #dc3545;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
        }
        .tab.active {
            border-bottom: 2px solid #007bff;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        
        /* 分页样式 */
        .pagination {
            margin: 20px 0;
            text-align: center;
        }
        .page-link {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 3px;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-decoration: none;
            color: #007bff;
        }
        .page-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .page-link:hover:not(.active) {
            background-color: #f5f5f5;
        }

        /* 添加这段代码修改顶部导航链接颜色 */
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
            <a href="profile.php">我的资料</a>
            <a href="settings.php">设置</a>
            <a href="logout.php">退出</a>
        </div>
    </div>
    
    <div class="admin-container">
        <h2>管理面板</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>" data-tab="users">用户管理</div>
            <div class="tab <?php echo $activeTab === 'posts' ? 'active' : ''; ?>" data-tab="posts">文章管理</div>
        </div>
        
        <div class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" id="users-tab">
            <div class="admin-section">
                <h3>用户管理</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>电子邮件</th>
                            <th>角色</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <select name="role" onchange="this.form.submit()">
                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>用户</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>管理员</option>
                                        </select>
                                        <input type="hidden" name="update_role" value="1">
                                    </form>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="action-btn edit-btn">查看</a>
                                    <?php if ($user['id'] != getCurrentUserId()): ?>
                                        <a href="admin.php?delete_user=<?php echo $user['id']; ?>&tab=users&user_page=<?php echo $userPage; ?>" class="action-btn delete-btn" onclick="return confirm('确定要删除此用户及其所有内容吗？此操作不可撤销!');">删除</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- 用户分页 -->
                <div class="pagination">
                    <?php if ($totalUserPages > 1): ?>
                        <?php if ($userPage > 1): ?>
                            <a href="?user_page=1&tab=users" class="page-link">&lt;&lt;</a>
                            <a href="?user_page=<?php echo $userPage - 1; ?>&tab=users" class="page-link">&lt;</a>
                        <?php endif; ?>
                        
                        <?php
                        // 显示附近的几个页码
                        $startPage = max(1, $userPage - 2);
                        $endPage = min($totalUserPages, $userPage + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $userPage): ?>
                                <span class="page-link active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?user_page=<?php echo $i; ?>&tab=users" class="page-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($userPage < $totalUserPages): ?>
                            <a href="?user_page=<?php echo $userPage + 1; ?>&tab=users" class="page-link">&gt;</a>
                            <a href="?user_page=<?php echo $totalUserPages; ?>&tab=users" class="page-link">&gt;&gt;</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="tab-content <?php echo $activeTab === 'posts' ? 'active' : ''; ?>" id="posts-tab">
            <div class="admin-section">
                <h3>文章管理</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>标题</th>
                            <th>作者</th>
                            <th>发布日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($post = $posts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $post['id']; ?></td>
                                <td><?php echo htmlspecialchars($post['topic_title']); ?></td>
                                <td>
                                    <a href="profile.php?id=<?php echo $post['user_id']; ?>">
                                        <?php echo htmlspecialchars($post['username']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($post['topic_date']); ?></td>
                                <td>
                                    <a href="post.php?id=<?php echo $post['id']; ?>" class="action-btn edit-btn">查看</a>
                                    <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="action-btn edit-btn">编辑</a>
                                    <a href="admin.php?delete_post=<?php echo $post['id']; ?>&tab=posts&post_page=<?php echo $postPage; ?>" class="action-btn delete-btn" onclick="return confirm('确定要删除此文章吗？');">删除</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- 文章分页 -->
                <div class="pagination">
                    <?php if ($totalPostPages > 1): ?>
                        <?php if ($postPage > 1): ?>
                            <a href="?post_page=1&tab=posts" class="page-link">&lt;&lt;</a>
                            <a href="?post_page=<?php echo $postPage - 1; ?>&tab=posts" class="page-link">&lt;</a>
                        <?php endif; ?>
                        
                        <?php
                        // 显示附近的几个页码
                        $startPage = max(1, $postPage - 2);
                        $endPage = min($totalPostPages, $postPage + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $postPage): ?>
                                <span class="page-link active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?post_page=<?php echo $i; ?>&tab=posts" class="page-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($postPage < $totalPostPages): ?>
                            <a href="?post_page=<?php echo $postPage + 1; ?>&tab=posts" class="page-link">&gt;</a>
                            <a href="?post_page=<?php echo $totalPostPages; ?>&tab=posts" class="page-link">&gt;&gt;</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 选项卡切换功能
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // 激活当前选项卡
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // 显示相应内容
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabId + '-tab').classList.add('active');
                
                // 更新URL参数，不刷新页面
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);
            });
        });
    </script>
</body>
</html>