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
    $title = trim($_POST['blogtitle'] ?? '');
    $date = trim($_POST['blogdate'] ?? '');
    $para = trim($_POST['blogpara'] ?? '');
    
    // 验证必填字段
    if (empty($title) || empty($date) || empty($para)) {
        $error = "标题、日期和内容不能为空";
    } else {
        // 处理图片上传
        $filename = $_POST['current_image'] ?? "NONE";
        
        if (isset($_FILES['uploadimage']) && $_FILES['uploadimage']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['uploadimage']['tmp_name'];
            $fileType = $_FILES['uploadimage']['type'];
            $fileSize = $_FILES['uploadimage']['size'];
            
            // 验证文件类型和大小
            if (!in_array($fileType, $config['upload']['allowed_types'])) {
                $error = "错误：只允许上传JPG、PNG和GIF图片";
            } elseif ($fileSize > $config['upload']['max_size']) {
                $error = "错误：文件大小超过限制";
            } else {
                // 删除旧图片
                if (!empty($filename) && $filename !== "NONE") {
                    $oldImagePath = "images/" . $filename;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                // 生成唯一文件名
                $extension = pathinfo($_FILES['uploadimage']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $extension;
                $uploadDir = "images/";
                
                // 确保目标目录存在
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // 移动上传的文件
                if (!move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $error = "文件上传失败";
                }
            }
        }
        
        if (!isset($error)) {
            // 更新数据库
            $stmt = $conn->prepare("UPDATE blog_table SET topic_title = ?, topic_date = ?, image_filename = ?, topic_para = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $title, $date, $filename, $para, $id);
            
            if ($stmt->execute()) {
                header("Location: index.php");
                exit;
            } else {
                $error = "更新博客失败: " . $stmt->error;
            }
            $stmt->close();
        }
    }
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
            <input id="blogTitle" name="blogtitle" type="text" placeholder="Blog Title..." autocomplete="off" 
                   value="<?php echo htmlspecialchars($post['topic_title']); ?>">
            
            <br>
            
            <span id="dateLabel">Date: </span>
            <input id="blogDate" name="blogdate" 
                   value="<?php echo htmlspecialchars($post['topic_date']); ?>">
            
            <br><br>
            
            <?php if (!empty($post['image_filename']) && $post['image_filename'] !== "NONE"): ?>
                <div style="margin-bottom: 15px;">
                    <p>当前图片：</p>
                    <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" 
                         alt="Current Image" style="max-width:300px;">
                    <input type="hidden" name="current_image" 
                           value="<?php echo htmlspecialchars($post['image_filename']); ?>">
                </div>
            <?php endif; ?>
            
            <input type="file" name="uploadimage">
            <p><small>选择新图片以替换当前图片，或留空保持当前图片不变</small></p>
            
            <br>
          
            <textarea id="blogPara" name="blogpara" cols="75" rows="10" placeholder="博客内容..."><?php echo htmlspecialchars($post['topic_para']); ?></textarea>

            <br><br>
            
            <button id="saveBtn" type="submit">更新文章</button>
        </form>

        <br>

        <center><a style="text-decoration: none;" href="index.php" id="saveBtn">返回首页</a></center>
    </div>

    <script src="scripts/script.js"></script>
</body>
</html>