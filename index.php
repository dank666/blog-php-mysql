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

      <span id="topBarTitle">Blog | All Posts</span>

    </div>

    <br>

    <div class="all-posts-container">

      <?php

      $servername = "localhost";

      $username = "root";

      $password = "www.041217.wtj";

      $database = "blog_db";

      $conn = new mysqli($servername, $username, $password, $database);

      if($conn->connect_error) die("Connection Error" . $conn->connect_error);

      $sql = "SELECT id, topic_title, topic_date, image_filename, topic_para FROM blog_table;";

      $result = $conn->query($sql);

      if($result->num_rows > 0)
      {
        while($row = $result->fetch_assoc())
        {
          echo "<div class='post-container'>";

          echo "<h2>" . htmlspecialchars($row["topic_title"]) . "</h2>";

          echo "<p><small>" . htmlspecialchars($row["topic_date"]) . "</small></p>";

          if (!empty($row["image_filename"]) && $row["image_filename"] !== "NONE") {
            echo "<img src='images/" . htmlspecialchars($row["image_filename"]) . "' alt='Post Image' style='width: 100%; height: auto;'>";
          }

          echo "<p>" . nl2br(htmlspecialchars($row["topic_para"])) . "</p>";

          echo "<button class='delete-post-btn' data-post-id='" . $row["id"] . "'>删除</button>";

          echo "<button class='edit-post-btn' data-post-id='" . $row["id"] . "'>编辑</button>";

          echo "</div>";
        }
      }
      
      else
      {
        echo "<center><span>No Blog Posts Found</span></center>";
      }

      $conn->close();
      
      ?>

    </div>

    <center>
      <a href="index.html" class="btn">撰写新文章</a>
    </center>

    <script>
    // 删除帖子功能
    document.querySelectorAll('.delete-post-btn').forEach(button => {
        button.addEventListener('click', function () {
            const postId = this.getAttribute('data-post-id');
            if (confirm('确定要删除这篇帖子吗？')) {
                fetch('delete_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('帖子已删除');
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });

    // 编辑帖子功能
    document.querySelectorAll('.edit-post-btn').forEach(button => {
        button.addEventListener('click', function () {
            const postId = this.getAttribute('data-post-id');
            window.location.href = `edit_post.php?id=${postId}`;
        });
    });
    </script>

  </body>
  
</html>