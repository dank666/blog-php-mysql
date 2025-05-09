<?php
include 'includes/auth.php';
$config = include 'config.php';

header('Content-Type: application/json');

// 验证用户是否登录
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 验证请求参数
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的文章ID']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => '评论内容不能为空']);
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
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 检查文章是否存在
$check_stmt = $conn->prepare("SELECT id FROM blog_table WHERE id = ?");
$check_stmt->bind_param("i", $post_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0) {
    $check_stmt->close();
    echo json_encode(['success' => false, 'message' => '文章不存在']);
    exit;
}
$check_stmt->close();

// 插入评论
$user_id = getCurrentUserId();
$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $post_id, $user_id, $content);

if ($stmt->execute()) {
    $comment_id = $conn->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'id' => $comment_id]);
} else {
    echo json_encode(['success' => false, 'message' => '添加评论失败: ' . $stmt->error]);
}

$conn->close();