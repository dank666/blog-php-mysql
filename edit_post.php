<?php
$servername = "localhost";
$username = "root";
$password = "www.041217.wtj";
$database = "blog_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $sql = "SELECT * FROM blog_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'], $_POST['title'], $_POST['content'])) {
    $post_id = intval($_POST['post_id']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    $sql = "UPDATE blog_table SET topic_title = ?, topic_para = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $title, $content, $post_id);

    if ($stmt->execute()) {
        header("Location: index.php");
        exit;
    } else {
        echo "更新失败";
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blogTitle'], $_POST['blogDate'], $_POST['filename'], $_POST['blogPara'])) {
    $blogTitle = trim($_POST['blogTitle']);
    $blogDate = trim($_POST['blogDate']);
    $blogPara = trim($_POST['blogPara']);

    if (isset($_FILES['uploadimage']) && $_FILES['uploadimage']['name']) {
        $filename = $_FILES['uploadimage']['name'];
        $tempname = $_FILES['uploadimage']['tmp_name'];
        move_uploaded_file($tempname, "images/" . $filename);
    } else {
        $filename = "default.jpg"; // 如果没有上传图片，使用默认图片
    }

    $sql = "INSERT INTO blog_table (topic_title, topic_date, image_filename, topic_para) VALUES ('" . $blogTitle . "', '" . $blogDate . "', '" . $filename . "', '" . $blogPara . "');";
    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑帖子</title>
</head>
<body>
    <h1>编辑帖子</h1>
    <form method="POST" action="edit_post.php">
        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
        <label for="title">标题:</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['topic_title']); ?>" required>
        <br>
        <label for="content">内容:</label>
        <textarea id="content" name="content" required><?php echo htmlspecialchars($post['topic_para']); ?></textarea>
        <br>
        <button type="submit">保存更改</button>
    </form>

    <?php
    if (isset($row["id"])) {
        echo "<button class='delete-post-btn' data-post-id='" . $row["id"] . "'>删除</button>";
        echo "<button class='edit-post-btn' data-post-id='" . $row["id"] . "'>编辑</button>";
    }
    ?>
</body>
</html>