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
$following_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$follower_id = getCurrentUserId();

if ($following_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的用户ID']);
    exit;
}

if ($follower_id == $following_id) {
    echo json_encode(['success' => false, 'message' => '不能关注自己']);
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

// 检查要关注的用户是否存在
$check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_stmt->bind_param("i", $following_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0) {
    $check_stmt->close();
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}
$check_stmt->close();

// 检查是否已经关注
$check_follow_stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
$check_follow_stmt->bind_param("ii", $follower_id, $following_id);
$check_follow_stmt->execute();
$follow_result = $check_follow_stmt->get_result();
$already_following = $follow_result->num_rows > 0;
$check_follow_stmt->close();

// 关注或取消关注
if ($already_following) {
    // 取消关注
    $delete_stmt = $conn->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
    $delete_stmt->bind_param("ii", $follower_id, $following_id);
    $success = $delete_stmt->execute();
    $delete_stmt->close();
    $is_following = false;
} else {
    // 添加关注
    $insert_stmt = $conn->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $follower_id, $following_id);
    $success = $insert_stmt->execute();
    $insert_stmt->close();
    $is_following = true;
}

// 获取更新后的粉丝数
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE following_id = ?");
$count_stmt->bind_param("i", $following_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$followers_count = $count_result->fetch_assoc()['count'];
$count_stmt->close();

$conn->close();

echo json_encode([
    'success' => $success,
    'is_following' => $is_following,
    'followers_count' => $followers_count
]);