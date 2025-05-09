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

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的文章ID']);
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

$user_id = getCurrentUserId();

// 检查用户是否已点赞
$check_like_stmt = $conn->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
$check_like_stmt->bind_param("ii", $post_id, $user_id);
$check_like_stmt->execute();
$like_result = $check_like_stmt->get_result();
$exists = $like_result->num_rows > 0;
$check_like_stmt->close();

// 点赞或取消点赞
if ($exists) {
    // 取消点赞
    $delete_stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $post_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    $user_liked = false;
} else {
    // 添加点赞
    $insert_stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $post_id, $user_id);
    $insert_stmt->execute();
    $insert_stmt->close();
    $user_liked = true;
}

// 获取更新后的点赞数
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
$count_stmt->bind_param("i", $post_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$likes_count = $count_result->fetch_assoc()['count'];
$count_stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'likes_count' => $likes_count,
    'user_liked' => $user_liked
]);