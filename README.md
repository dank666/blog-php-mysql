# PHP + MySQL 多用户博客系统

这是一个基于 PHP 和 MySQL 的完整博客系统，支持多用户注册、文章管理、图片上传、用户权限控制等功能。该系统适合初学者学习 PHP 与 MySQL 的全栈开发，也可作为小型团队或个人的实用博客平台。

---

## 功能特性

### 用户管理
- **用户注册与登录**：支持用户注册、登录和退出功能  
- **权限管理**：分为管理员和普通用户两种角色  
- **个人资料**：用户可上传头像并编辑个人信息  
- **安全认证**：密码加密存储，CSRF 令牌保护  

### 文章管理
- **文章发布**：支持输入标题、正文、日期和上传图片  
- **文章展示**：首页展示所有已发布的文章，包括作者信息  
- **文章编辑与删除**：用户可编辑和删除自己的文章，管理员可管理所有文章  
- **图片上传**：支持 JPG、PNG、GIF 格式，大小限制为 2MB  

### 管理功能
- **管理面板**：管理员专用界面，可管理所有用户和文章  
- **用户管理**：管理员可设置或取消其他用户的管理权限  
- **首管理员保护**：系统确保首个管理员权限不能被其他管理员修改  

### 用户体验
- **输入保留**：上传图片过大等错误情况下，用户输入的内容会被保留  
- **错误提示**：针对各种操作提供友好的错误和成功提示  
- **响应式设计**：适配不同屏幕大小的设备  
- **自动换行优化**：支持中英文内容自动换行，防止长文本溢出页面  

---

## 项目结构

### 数据库结构
- `users` 表  
- `blog_table` 表  

---

## 环境准备

### 安装依赖
确保已安装 PHP、MySQL 和 Apache/Nginx 服务器。

---

## 部署项目

1. 将本项目文件夹复制到 `html` 目录下（如：`/var/www/html/`）
2. 设置权限：

   ```bash
   chmod -R 755 blog-php-mysql
   chown -R www-data:www-data blog-php-mysql
   ```

3. 创建并配置数据库
4. 创建配置文件：

   在项目根目录创建 `config.php` 文件，设置数据库连接信息：

   ```php
   <?php
   $host = "localhost";
   $user = "your_db_user";
   $pass = "your_db_password";
   $dbname = "your_db_name";
   ?>
   ```

---

## 启动服务

### 创建目录结构
确保以下目录存在并具备可写权限：

- `images/`
- `avatars/`

### 访问系统
浏览器访问：

```
http://localhost/blog-php-mysql
```

---

## 使用指南

### 注册与登录
1. 访问登录页面：http://localhost/blog-php-mysql/login.php  
2. 首次使用请点击“注册”，填写用户名、邮箱和密码  
3. 注册后自动登录，或使用已有账户登录系统  

### 发布文章
1. 点击“撰写新文章”按钮  
2. 填写标题、内容，可选择上传图片（支持 JPG、PNG、GIF 格式，大小不超过 2MB）  
3. 点击“保存”按钮发布文章  

### 管理文章
- 在首页可以查看所有已发布的文章  
- 对于自己发布的文章，可以点击“编辑”或“删除”进行管理  
- 管理员可以管理所有用户的文章  

### 个人资料设置
- 点击顶部导航栏中的用户名，进入个人资料页面  
- 可以上传头像、修改显示名称等信息  
- 点击“设置”可以修改密码和其他账户信息  

### 管理员功能
- 管理员登录后，可以看到“管理面板”选项  
- 在管理面板中可以管理所有用户和文章  
- 可以设置或取消其他用户的管理员权限  

---

## 文件上传限制

| 类型         | 格式               | 最大大小 |
|--------------|--------------------|-----------|
| 头像上传     | JPG、PNG、GIF      | 2MB       |
| 文章图片上传 | JPG、PNG、GIF      | 2MB       |

*上传失败时系统会提示错误并保留输入内容。*

---

## 安全特性

- 密码使用安全哈希算法存储（如 `password_hash()`）
- 表单提交使用 CSRF 令牌保护
- 输入数据经过过滤和验证，防止 SQL 注入
- 首个管理员权限不能被其他管理员修改

---

## 扩展与自定义

- 可修改 `config.php` 调整数据库连接设置
- 可修改 `style.css` 自定义界面样式
- 可调整 `php.ini` 中的 `upload_max_filesize` 和 `post_max_size` 改变上传限制

---

## 部署脚本

项目包含 `deploy.sh` 脚本，支持自动部署和更新：

- 自动备份 `images` 和 `avatars` 文件夹，升级时不会丢失已上传图片。

---

## 故障排除

| 问题             | 解决方案                                                                 |
|------------------|--------------------------------------------------------------------------|
| 上传文件失败     | 检查目录权限、PHP 配置（如 `upload_max_filesize`）                       |
| 数据库连接错误   | 确认 `config.php` 中的数据库连接信息正确                                 |
| 页面样式异常     | 检查 CSS 和 JavaScript 是否正确引入                                      |
| 日期显示问题     | 检查 JavaScript 日期设置代码                                              |
