<?php
include 'includes/auth.php';
include 'includes/database.php';

$config = include 'config.php';
$conn = getDbConnection($config);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 自动生成用户
function generateUsers($conn, $numUsers = 10) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, display_name, bio, role, created_at, last_login, is_active) VALUES (?, ?, ?, ?, ?, 'user', NOW(), NOW(), 1)");
    for ($i = 1; $i <= $numUsers; $i++) {
        $username = "unique_user_" . uniqid();
        $email = "user" . uniqid() . "@example.com";
        $passwordHash = password_hash("password" . $i, PASSWORD_BCRYPT);
        $displayName = "用户_" . uniqid();
        $bio = "这是用户 " . $displayName . " 的简介，生成时间：" . date('Y-m-d H:i:s');
        $stmt->bind_param("sssss", $username, $email, $passwordHash, $displayName, $bio);
        $stmt->execute();
    }
    $stmt->close();
    echo "成功生成 $numUsers 个用户。\n";
}

// 自动生成文章
function generatePosts($conn, $numPosts = 50) {
    $stmt = $conn->prepare("INSERT INTO blog_table (topic_title, topic_date, topic_para, image_filename, user_id) VALUES (?, NOW(), ?, ?, ?)");
    $userIds = [];
    
    // 获取所有用户ID
    $result = $conn->query("SELECT id FROM users");
    while ($row = $result->fetch_assoc()) {
        $userIds[] = $row['id'];
    }
    $result->close();

    for ($i = 1; $i <= $numPosts; $i++) {
        $title = "文章标题_" . uniqid();
        $content = "这是文章 " . $title . " 的内容。这是一段自动生成的文章内容，生成时间：" . date('Y-m-d H:i:s');
        $image = $i % 2 === 0 ? "image_" . uniqid() . ".jpg" : "NONE"; // 偶数文章有图片
        $userId = $userIds[array_rand($userIds)]; // 随机分配用户
        $stmt->bind_param("sssi", $title, $content, $image, $userId);
        $stmt->execute();
    }
    $stmt->close();
    echo "成功生成 $numPosts 篇文章。\n";
}

// 调用生成函数
generateUsers($conn, 10); // 生成 10 个用户
generatePosts($conn, 50); // 生成 50 篇文章

$conn->close();
echo "数据生成完成。\n";
?>