<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

// 确定查看的是哪个用户的资料
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : getCurrentUserId();

if (!$profileId) {
    // 如果没有指定用户且未登录，重定向到登录页
    header("Location: login.php");
    exit;
}

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

// 获取用户信息
$stmt = $conn->prepare("
    SELECT username, email, display_name, bio, avatar, role, created_at
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $profileId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    die("未找到该用户");
}

$user = $result->fetch_assoc();
$stmt->close();

// 获取用户的文章
$stmt = $conn->prepare("
    SELECT id, topic_title, topic_date, image_filename
    FROM blog_table 
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $profileId);
$stmt->execute();
$posts = $stmt->get_result();
$stmt->close();
$conn->close();

// 检查是否是当前用户查看自己的资料
$isOwnProfile = isLoggedIn() && getCurrentUserId() == $profileId;

// 获取当前登录用户
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';
    $success = '';

    // 检查是否上传了文件
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        // 检查文件大小
        if ($file['size'] > $maxFileSize) {
            $error = "图片太大，请选择小于2MB的图片。";
        } else {
            // 处理文件上传
            $uploadDir = 'avatars/';
            $fileName = uniqid() . '_' . basename($file['name']);
            $uploadPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // 更新数据库中的头像路径
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $fileName, $currentUser['id']);
                if ($stmt->execute()) {
                    $success = "头像更新成功！";
                } else {
                    $error = "更新头像失败，请稍后再试。";
                }
                $stmt->close();
            } else {
                $error = "上传头像失败，请稍后再试。";
            }
        }
    } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "上传头像失败，请检查文件并重试。";
    }

    // 其他表单处理逻辑...
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['display_name']); ?> 的个人资料 - 博客系统</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-info {
            flex-grow: 1;
        }
        
        .profile-name {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .profile-username {
            color: #666;
            margin-bottom: 10px;
        }
        
        .profile-bio {
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .profile-meta {
            color: #666;
            font-size: 14px;
        }
        
        .profile-posts {
            margin-top: 30px;
        }
        
        .post-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .post-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.2s;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .post-card-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .post-card-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .post-card-date {
            font-size: 12px;
            color: #666;
        }
        
        .edit-profile-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        /* 导航栏 */
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
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">个人资料 | <?php echo htmlspecialchars($user['display_name']); ?></span>
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
    
    <div class="profile-container">
        <div class="profile-header">
            <img src="<?php echo !empty($user['avatar']) ? 'avatars/' . htmlspecialchars($user['avatar']) : 'avatars/default.jpg'; ?>" alt="头像" class="profile-avatar">
            
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($user['display_name']); ?></h1>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                
                <?php if (!empty($user['bio'])): ?>
                    <div class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
                <?php endif; ?>
                
                <div class="profile-meta">
                    注册于: <?php echo date('Y年m月d日', strtotime($user['created_at'])); ?>
                </div>
                
                <?php if ($isOwnProfile): ?>
                    <a href="settings.php" class="edit-profile-btn">编辑个人资料</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-posts">
            <h2><?php echo htmlspecialchars($user['display_name']); ?> 的文章</h2>
            
            <?php if ($posts->num_rows > 0): ?>
                <div class="post-grid">
                    <?php while ($post = $posts->fetch_assoc()): ?>
                        <a href="post.php?id=<?php echo $post['id']; ?>" style="text-decoration: none; color: inherit;">
                            <div class="post-card">
                                <?php if (!empty($post['image_filename']) && $post['image_filename'] !== "NONE"): ?>
                                    <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" alt="" class="post-card-image">
                                <?php else: ?>
                                    <div style="height: 150px; background-color: #f0f0f0; border-radius: 5px; margin-bottom: 10px;"></div>
                                <?php endif; ?>
                                
                                <div class="post-card-title"><?php echo htmlspecialchars($post['topic_title']); ?></div>
                                <div class="post-card-date"><?php echo htmlspecialchars($post['topic_date']); ?></div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>暂无文章</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>