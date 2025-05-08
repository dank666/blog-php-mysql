<?php

// error_reporting(0);

$servername = "localhost";

$username = "root";

$password = "www.041217.wtj";

$database = "blog_db"; 

$blogTitle = $_POST["blogtitle"];

$blogDate = $_POST["blogdate"];

$blogPara = $_POST["blogpara"];

// $sql = "insert into blog_table (topic_title, topic_date, image_filename, topic_para) values ('" . $blogTitle . "', '" . $blogDate . "', '" . $filename . "', '" . $blogPara . "');";

$conn = new mysqli($servername, $username, $password, $database);

if($conn->connect_error) die("Connection to database failed") . $conn->connect->error;

$filename = "NONE";

if (isset($_FILES['uploadimage']) && $_FILES['uploadimage']['error'] === UPLOAD_ERR_OK) {
    $filename = $_FILES['uploadimage']['name'];
    $tempname = $_FILES['uploadimage']['tmp_name'];
    $uploadDir = "images/";

    // 确保目标目录存在
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 移动上传的文件
    if (move_uploaded_file($tempname, $uploadDir . $filename)) {
        $filename = $conn->real_escape_string($filename); // 防止 SQL 注入
    } else {
        $filename = "NONE"; // 如果上传失败，设置为默认值
    }
}

$sql = "insert into blog_table (topic_title, topic_date, image_filename, topic_para) values ('" . $blogTitle . "', '" . $blogDate . "', '" . $filename . "', '" . $blogPara . "');";

if($conn->query($sql) === TRUE)
{
  echo "";
}

else
{
  echo "Error Saving Post";
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Saved</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .saved-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            font-family: "Roboto", sans-serif;
        }

        .saved-container h2 {
            font-size: 24px;
            color: #333;
        }

        .saved-container p {
            font-size: 16px;
            color: #555;
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
        <span id="topBarTitle">Post Saved</span>
    </div>
    <div class="saved-container">
        <h2><?php echo htmlspecialchars($blogTitle); ?></h2>
        <p><small><?php echo htmlspecialchars($blogDate); ?></small></p>
        <?php if (!empty($filename) && $filename !== "NONE"): ?>
            <img src="images/<?php echo htmlspecialchars($filename); ?>" alt="Post Image">
        <?php endif; ?>
        <p><?php echo nl2br(htmlspecialchars($blogPara)); ?></p>
        <a href="index.php">Go to Home Page</a>
    </div>
</body>
</html>