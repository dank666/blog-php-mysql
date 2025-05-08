<?php
$servername = "localhost";
$username = "root";
$password = "www.041217.wtj";
$database = "blog_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);

    $sql = "DELETE FROM blog_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => '帖子已删除']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '删除失败']);
    }

    $stmt->close();
}

$conn->close();
?>