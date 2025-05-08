<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载配置和认证
$config = include 'config.php';
include 'includes/auth.php';

// 要求用户登录才能发布文章
requireLogin();

// 获取表单数据
$blogTitle = trim($_POST["blogtitle"] ?? '');
$blogDate = trim($_POST["blogdate"] ?? '');
$blogPara = trim($_POST["blogpara"] ?? '');

// 验证必填字段
if (empty($blogTitle) || empty($blogDate) || empty($blogPara)) {
    die("错误：标题、日期和内容不能为空");
}

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

// 处理文件上传
$filename = "NONE";
if (isset($_FILES['uploadimage']) && $_FILES['uploadimage']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['uploadimage']['tmp_name'];
    $fileType = $_FILES['uploadimage']['type'];
    $fileSize = $_FILES['uploadimage']['size'];
    
    // 验证文件类型和大小
    if (!in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
        die("错误：只允许上传JPG、PNG和GIF图片");
    }
    
    if ($fileSize > 5000000) { // 5MB
        die("错误：文件大小超过限制");
    }
    
    // 生成唯一文件名避免冲突
    $extension = pathinfo($_FILES['uploadimage']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $uploadDir = "images/";
    
    // 确保目标目录存在
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            die("创建上传目录失败");
        }
    }
    
    // 移动上传的文件
    if (!move_uploaded_file($tmpName, $uploadDir . $filename)) {
        die("文件上传失败");
    }
}

// 获取当前用户ID
$userId = getCurrentUserId();

// 使用参数化查询插入数据，包括用户ID
$stmt = $conn->prepare("INSERT INTO blog_table (topic_title, topic_date, image_filename, topic_para, user_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssi", $blogTitle, $blogDate, $filename, $blogPara, $userId);

if (!$stmt->execute()) {
    die("保存博客失败: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文章已保存</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .top-bar {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            text-align: center;
        }
        
        .saved-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        
        .saved-container h2 {
            font-size: 24px;
            color: #333;
        }

        .saved-container p {
            font-size: 16px;
            color: #555;
            word-break: break-all;
            overflow-wrap: break-word;
        }

        .saved-container img {
            max-width: 100%;
            height: auto;
            margin: 20px 0;
            border-radius: 8px;
        }

        .saved-container a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }

        .saved-container a:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">文章已保存</span>
    </div>
    <div class="saved-container">
        <h2><?php echo htmlspecialchars($blogTitle); ?></h2>
        <p><small><?php echo htmlspecialchars($blogDate); ?></small></p>
        <?php if (!empty($filename) && $filename !== "NONE"): ?>
            <img src="images/<?php echo htmlspecialchars($filename); ?>" alt="文章图片">
        <?php endif; ?>
        <p><?php echo nl2br(htmlspecialchars($blogPara)); ?></p>
        <a href="index.php">返回首页</a>
    </div>
</body>
</html>