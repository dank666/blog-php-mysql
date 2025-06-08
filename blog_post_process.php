<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载配置和认证
$config = include 'config.php';
include 'includes/auth.php';
include 'includes/database.php';

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
    $tags = $_POST['tags'] ?? []; // 获取选中的已有标签
    $customTags = $_POST['custom_tags'] ?? ''; // 获取自定义标签
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
            // 处理图片上传
            $target_dir = "images/";
            
            // 确保目录存在
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // 生成唯一文件名
            $extension = pathinfo($_FILES['uploadimage']['name'], PATHINFO_EXTENSION);
            $imageFilename = uniqid('post_') . '.' . $extension;
            $target_file = $target_dir . $imageFilename;
            
            // 检查图片类型
            $check = getimagesize($_FILES['uploadimage']['tmp_name']);
            if ($check === false) {
                // 不是有效图片
                $error = "文件不是有效的图片。";
                $imageFilename = 'NONE';
            } elseif (!move_uploaded_file($_FILES['uploadimage']['tmp_name'], $target_file)) {
                // 处理上传失败
                $error = "图片上传失败，请重试。";
                $imageFilename = 'NONE';
            }
        }
    }

    // 如果有错误，返回错误提示，保留用户输入
    if (!empty($error)) {
        echo "<script>
            localStorage.setItem('blogtitle', " . json_encode($title) . ");
            localStorage.setItem('blogpara', " . json_encode($content) . ");
            alert('" . $error . "'); 
            window.location.href = 'new_post.php';
        </script>";
        exit;
    }

    // 获取当前用户ID
    $currentUser = getCurrentUserId();
    
    // 使用事务确保数据一致性
    $conn->begin_transaction();
    
    try {
        // 插入帖子到数据库，包含图片信息
        $stmt = $conn->prepare("INSERT INTO blog_table (topic_title, topic_date, topic_para, image_filename, user_id) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $content, $imageFilename, $_SESSION['user_id']);
        $stmt->execute();
        $postId = $stmt->insert_id;
        $stmt->close();

        // 在插入文章标签关系前，去除重复的标签
        $tags = array_unique($tags); // 确保标签不重复

        // 处理自定义标签
        if (!empty($customTags)) {
            // 解析自定义标签
            $customTagsArray = array_map('trim', explode(',', $customTags));
            $customTagsArray = array_filter($customTagsArray); // 移除空标签
            $processedCustomTags = []; // 跟踪已处理的自定义标签名称
            
            foreach ($customTagsArray as $tagName) {
                // 避免空标签和重复处理相同的自定义标签
                if (!empty($tagName) && !in_array(strtolower($tagName), $processedCustomTags)) {
                    $processedCustomTags[] = strtolower($tagName); // 记录已处理的标签
                    
                    // 检查标签是否已存在
                    $stmt = $conn->prepare("SELECT id FROM tags WHERE LOWER(name) = LOWER(?)");
                    $stmt->bind_param("s", $tagName);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $tagId = $result->fetch_assoc()['id'];
                        // 只有当这个ID不在标签数组中时才添加
                        if (!in_array($tagId, $tags)) {
                            $tags[] = $tagId;
                        }
                    } else {
                        // 插入新标签
                        $stmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
                        $stmt->bind_param("s", $tagName);
                        $stmt->execute();
                        $tagId = $stmt->insert_id;
                        $tags[] = $tagId;
                    }
                    $stmt->close();
                }
            }
        }

        // 再次确保所有标签都是唯一的
        $tags = array_unique($tags);

        // 插入文章标签关系
        if (!empty($tags)) {
            $stmt = $conn->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
            foreach ($tags as $tagId) {
                $stmt->bind_param("ii", $postId, $tagId);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        // 提交事务
        $conn->commit();
        
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        echo "<script>alert('发布文章失败: " . $e->getMessage() . "'); window.location.href = 'new_post.php';</script>";
        exit;
    }
}
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