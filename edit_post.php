<?php
// 加载配置
$config = include 'config.php';

// 验证ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("错误：无效的文章ID");
}

$id = (int)$_GET['id'];
$post = null;

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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = (int)$_POST['post_id'];
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $error = '';

    // 检查必填字段
    if (empty($title) || empty($date) || empty($content)) {
        $error = "标题、日期和内容不能为空。";
    }

    // 检查图片上传
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_INI_SIZE) {
            $error = "图片太大，请选择小于 " . ini_get('upload_max_filesize') . " 的图片。";
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = "图片上传失败，请重试。";
        } else {
            // 验证图片大小（2MB限制）
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            if ($_FILES['image']['size'] > $maxFileSize) {
                $error = "图片太大，请选择小于 2MB 的图片。";
            }

            // 验证图片格式
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                $error = "图片格式不支持，仅支持 JPG、PNG 和 GIF 格式。";
            }
        }
    }

    // 如果有错误，返回错误提示
    if (!empty($error)) {
        echo "<script>alert('$error'); window.history.back();</script>";
        exit;
    }

    // 如果没有错误，处理图片上传
    $imageFilename = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageFilename = uniqid('post_') . '.' . $extension;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageFilename)) {
            echo "<script>alert('图片上传失败，请重试。'); window.history.back();</script>";
            exit;
        }
    }

    // 更新帖子到数据库
    $stmt = $conn->prepare("UPDATE blog_table SET topic_title = ?, topic_date = ?, topic_para = ?, image_filename = IFNULL(?, image_filename) WHERE id = ?");
    $stmt->bind_param("ssssi", $title, $date, $content, $imageFilename, $postId);
    if ($stmt->execute()) {
        echo "<script>alert('帖子更新成功！'); window.location.href = 'index.php';</script>";
    } else {
        echo "<script>alert('帖子更新失败，请重试。'); window.history.back();</script>";
    }
    $stmt->close();
}

// 获取文章数据
$stmt = $conn->prepare("SELECT topic_title, topic_date, image_filename, topic_para FROM blog_table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $post = $result->fetch_assoc();
} else {
    die("文章不存在");
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑博客文章</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">Blog | Edit Post</span>
    </div>

    <div class="writing-section">
        <?php if (isset($error)): ?>
            <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="edit_post.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            <input id="blogTitle" name="title" type="text" placeholder="Blog Title..." autocomplete="off" 
                   value="<?php echo htmlspecialchars($post['topic_title']); ?>">
            
            <br>
            
            <span id="dateLabel">Date: </span>
            <input id="blogDate" name="date" 
                   value="<?php echo htmlspecialchars($post['topic_date']); ?>">
            
            <br><br>
            
            <?php if (!empty($post['image_filename']) && $post['image_filename'] !== "NONE"): ?>
                <div style="margin-bottom: 15px;">
                    <p>当前图片：</p>
                    <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" 
                         alt="Current Image" style="max-width:300px;">
                </div>
            <?php endif; ?>
            
            <input type="file" name="image">
            <p><small>选择新图片以替换当前图片，或留空保持当前图片不变</small></p>
            
            <br>
          
            <textarea id="blogPara" name="content" cols="75" rows="10" placeholder="博客内容..."><?php echo htmlspecialchars($post['topic_para']); ?></textarea>

            <br><br>
            
            <button id="saveBtn" type="submit">更新文章</button>
        </form>

        <br>

        <center><a style="text-decoration: none;" href="index.php" id="saveBtn">返回首页</a></center>
    </div>

    <script src="scripts/script.js"></script>
</body>
</html>