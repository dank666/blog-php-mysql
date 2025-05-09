<?php
include 'includes/auth.php';
$config = include 'config.php';

header('Content-Type: application/json');

// 获取参数
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if ($post_id <= 0) {
    echo json_encode([]);
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
    echo json_encode([]);
    exit;
}

// 获取评论
$stmt = $conn->prepare("
    SELECT c.*, u.username 
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at DESC
");

$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    // 转换HTML特殊字符
    $row['content'] = htmlspecialchars($row['content']);
    $row['username'] = htmlspecialchars($row['username']);
    
    // 格式化日期
    $date = new DateTime($row['created_at']);
    $row['created_at'] = $date->format('Y-m-d H:i');
    
    $comments[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($comments);