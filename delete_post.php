<?php
// 加载配置
$config = include 'config.php';

// 验证ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("错误：无效的文章ID");
}

$id = (int)$_GET['id'];

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

// 先获取图片文件名
$stmt = $conn->prepare("SELECT image_filename FROM blog_table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $filename = $row['image_filename'];
    
    // 如果有图片且不是默认的NONE，则删除该图片
    if (!empty($filename) && $filename !== "NONE") {
        $imagePath = "images/" . $filename;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    // 删除文章记录
    $deleteStmt = $conn->prepare("DELETE FROM blog_table WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    
    if ($deleteStmt->execute()) {
        // 删除成功
        header("Location: index.php");
        exit;
    } else {
        echo "删除文章失败: " . $conn->error;
    }
    
    $deleteStmt->close();
} else {
    echo "文章不存在";
}

$stmt->close();
$conn->close();
?>