<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';

// 需要登录才能访问此页面
requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();
?>

<!DOCTYPE html>

<html lang="en">
  
  <head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Blog Using PHP And MySQL</title>

    <link rel="stylesheet" href="styles/style.css">

  </head>

  <body>

    <div class="top-bar">
  
      <span id="topBarTitle">Blog | New Post</span>

    </div>

    <div class="writing-section">

      <form action="blog_post_process.php" method="POST" enctype="multipart/form-data">

        <input id="blogTitle" name="blogtitle" type="text" placeholder="Blog Title..." autocomplete="off">
        
        <br>
        
        <span id="dateLabel">Date: </span>
        
        <input id="blogDate" name="blogdate" readonly></input>
        
        <br><br>
        
        <input type="file" name="uploadimage">
        
        <br><br>
      
        <textarea id="blogPara" name="blogpara" cols="75" rows="10" type="text" placeholder="Blog Paragraph..." autocomplete="off"></textarea>

        <br><br>
        
        <button id="saveBtn" type="submit">Save Post</button>

      </form>

      <br>

      <center><a style="text-decoration: none;" href="index.php" id="saveBtn">Go to Home Page</a></center>
      
    </div>

    <script src="scripts/script.js"></script>
    <script>
      // 恢复用户输入
      document.addEventListener('DOMContentLoaded', function() {
        var savedTitle = localStorage.getItem('blogtitle');
        var savedContent = localStorage.getItem('blogpara');
        
        if (savedTitle) {
          document.getElementById('blogTitle').value = savedTitle;
        }
        
        if (savedContent) {
          document.getElementById('blogPara').value = savedContent;
        }
      });
    </script>
  </body>
  
</html>