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

  </head>

  <body>

    <div class="container" style="width: 50%; margin: auto; text-align: justify; font-family: Roboto, sans-serif;">

      <h1 style="margin-bottom: 10px; text-align: center;">Post Saved</h1>

      <center><a style="color: dodgerblue;" href="index.php">Go to Home Page</a></center>
      
      <br><br>

      <?php echo "<span style='font-weight: bold;' id='showTitle'>" . $blogTitle . "</span>" ?>
      <br>

      <span id="showDate"><?php echo $blogDate ?></span><br><br>

      <center><img src="images/<?php echo $filename; ?>" id="showImage" style="width: 50%; height: auto;"></center>

      <br>

      <?php echo "<span id='showPara'>" . $blogPara . "</span>" ?>

      <br><br>
      
    </div>

  </body>
  
</html>