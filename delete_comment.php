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
$comment_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($comment_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的评论ID']);
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

$user_id = getCurrentUserId();
$is_admin = isAdmin();

// 获取评论信息
$stmt = $conn->prepare("SELECT user_id, post_id FROM comments WHERE id = ?");
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => '评论不存在']);
    exit;
}

$comment = $result->fetch_assoc();
$stmt->close();

// 检查权限（评论作者、管理员或文章作者可以删除评论）
if (!$is_admin && $comment['user_id'] != $user_id) {
    // 检查当前用户是否是文章作者
    $post_id = $comment['post_id'];
    $check_stmt = $conn->prepare("SELECT user_id FROM blog_table WHERE id = ?");
    $check_stmt->bind_param("i", $post_id);
    $check_stmt->execute();
    $post_result = $check_stmt->get_result();
    $post = $post_result->fetch_assoc();
    $check_stmt->close();
    
    if ($post['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => '您没有权限删除此评论']);
        exit;
    }
}

// 删除评论
$delete_stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
$delete_stmt->bind_param("i", $comment_id);

if ($delete_stmt->execute()) {
    $delete_stmt->close();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => '删除评论失败: ' . $delete_stmt->error]);
}

$conn->close();