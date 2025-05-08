<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载配置和认证
$config = include 'config.php';
include 'includes/auth.php';

// 要求用户登录才能发布文章
requireLogin();

// 开启 session，用于保存用户输入
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 确保字段名称与表单一致
    $title = trim($_POST['blogtitle'] ?? '');
    $date = trim($_POST['blogdate'] ?? '');
    $content = trim($_POST['blogpara'] ?? '');
    $error = '';

    // 保存用户输入到 session
    $_SESSION['blogtitle'] = $title;
    $_SESSION['blogdate'] = $date;
    $_SESSION['blogpara'] = $content;

    // 检查必填字段
    if (empty($title) || empty($date) || empty($content)) {
        $error = "标题、日期和内容不能为空。";
    }

    // 检查图片上传
    $imageFilename = 'NONE';
    if (isset($_FILES['uploadimage']) && $_FILES['uploadimage']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['uploadimage']['error'] === UPLOAD_ERR_INI_SIZE) {
            $error = "图片太大，请选择小于 " . ini_get('upload_max_filesize') . " 的图片。";
        } elseif ($_FILES['uploadimage']['error'] !== UPLOAD_ERR_OK) {
            $error = "图片上传失败，请重试。";
        } else {
            // 验证图片大小（2MB限制）
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            if ($_FILES['uploadimage']['size'] > $maxFileSize) {
                $error = "图片太大，请选择小于 2MB 的图片。";
            } else {
                // 处理图片上传
                $uploadDir = 'images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = pathinfo($_FILES['uploadimage']['name'], PATHINFO_EXTENSION);
                $imageFilename = uniqid('post_') . '.' . $extension;

                if (!move_uploaded_file($_FILES['uploadimage']['tmp_name'], $uploadDir . $imageFilename)) {
                    $error = "图片上传失败，请重试。";
                }
            }
        }
    }

    // 如果有错误，返回错误提示，保留用户输入
    if (!empty($error)) {
        echo "<script>
            localStorage.setItem('blogtitle', " . json_encode($title) . ");
            localStorage.setItem('blogpara', " . json_encode($content) . ");
            alert('" . $error . "'); 
            window.location.href = 'index.html';
        </script>";
        exit;
    }

    // 获取当前用户ID
    $currentUser = getCurrentUserId();

    // 插入帖子到数据库
    $stmt = $conn->prepare("INSERT INTO blog_table (topic_title, topic_date, topic_para, image_filename, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $date, $content, $imageFilename, $currentUser);
    if ($stmt->execute()) {
        // 清除 session 数据和 localStorage
        unset($_SESSION['blogtitle']);
        unset($_SESSION['blogdate']);
        unset($_SESSION['blogpara']);
        echo "<script>
            localStorage.removeItem('blogtitle');
            localStorage.removeItem('blogpara');
            alert('帖子发布成功！'); 
            window.location.href = 'index.php';
        </script>";
    } else {
        echo "<script>
            localStorage.setItem('blogtitle', " . json_encode($title) . ");
            localStorage.setItem('blogpara', " . json_encode($content) . ");
            alert('发布失败，请重试。'); 
            window.location.href = 'index.html';
        </script>";
    }
    $stmt->close();
}

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
        <h2><?php echo htmlspecialchars($title); ?></h2>
        <p><small><?php echo htmlspecialchars($date); ?></small></p>
        <?php if (!empty($imageFilename) && $imageFilename !== "NONE"): ?>
            <img src="images/<?php echo htmlspecialchars($imageFilename); ?>" alt="文章图片">
        <?php endif; ?>
        <p><?php echo nl2br(htmlspecialchars($content)); ?></p>
        <a href="index.php">返回首页</a>
    </div>
</body>
</html>