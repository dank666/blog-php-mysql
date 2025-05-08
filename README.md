# PHP 和 MySQL 简易博客系统

这是一个简易的博客系统，提供了一个用户友好的平台，供博主在线发布文章。该系统使用 PHP 作为后端语言，并通过 MySQL 数据库存储博客文章。用户可以撰写博客文章、添加图片并将其发布到博客中。

本项目适合作为开发者学习和构建基于 PHP 和 MySQL 的博客系统的起点。

## 项目设置

1. **安装必要的软件**  
   在 Linux 系统中，您需要安装以下软件：
   - Apache 服务器
   - MySQL 数据库
   - PHP

   可以通过以下命令安装这些软件（以基于 Debian 的系统为例，如 Ubuntu）：
   ```bash
   sudo apt update
   sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql
   ```

2. **项目文件结构**  
   将下载的项目文件夹解压到 Apache 的默认 Web 根目录 `/var/www/html/` 下。文件结构可能如下所示：

   ```
   /var/www/html/
   |----blog-php-mysql
        |----styles
             |----style.css
        |----images
        |----scripts
             |----script.js
        |----blog_post_process.php
        |----index.html
        |----index.php
        |----delete_post.php
        |----edit_post.php
        |----README.md
   ```

   设置文件夹权限以确保 Apache 能够访问：
   ```bash
   sudo chown -R www-data:www-data /var/www/html/blog-php-mysql
   sudo chmod -R 755 /var/www/html/blog-php-mysql
   ```

3. **创建数据库**  
   - 启动 MySQL 服务：
     ```bash
     sudo service mysql start
     ```
   - 登录 MySQL：
     ```bash
     sudo mysql -u root -p
     ```
   - 创建数据库和表：
     ```sql
     CREATE DATABASE blog_db;
     USE blog_db;
     CREATE TABLE blog_table (
         id INT AUTO_INCREMENT PRIMARY KEY,
         topic_title TEXT NOT NULL,
         topic_date TEXT NOT NULL,
         topic_para TEXT NOT NULL
     );
     ```
   - 退出 MySQL：
     ```bash
     exit
     ```

4. **测试项目**  
   - 启动 Apache 服务：
     ```bash
     sudo service apache2 start
     ```
   - 在浏览器中访问 <http://localhost/blog-php-mysql>。
   - 在 `index.php` 页面中可以查看所有已创建的文章，并通过页面底部的“撰写新文章”按钮跳转到 `index.html` 页面进行文章创建。

## 功能特性

- **文章展示**  
  在首页 `index.php` 中展示所有已创建的文章。

- **自动插入日期**  
  在创建文章时，系统会自动插入当前日期和时间。

- **未来功能计划**  
  本项目计划在未来增加以下功能：
  - **多用户支持**：允许多个用户注册和登录，管理各自的文章。
  - **用户评论**：为每篇文章添加评论功能，提升互动性。
  - **文章搜索**：支持通过关键词搜索文章。
  - **热点文章排名**：根据文章的浏览量或互动量，展示热门文章排行榜。

欢迎对本项目提出建议或贡献代码！