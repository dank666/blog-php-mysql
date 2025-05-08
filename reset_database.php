<?php
// 显示所有PHP错误
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = include 'config.php';
$conn = new mysqli(
    $config['db']['host'],
    $config['db']['username'],
    $config['db']['password'],
    $config['db']['database']
);

// 临时禁用外键检查
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// 清空表
$conn->query("TRUNCATE TABLE blog_table");
$conn->query("TRUNCATE TABLE users");

// 重新启用外键检查
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// 创建管理员账户
$username = 'admin';
$email = 'wtejing@gmail.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$role = 'admin';

// 修改这一行，将 password 改为 password_hash
$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("ssss", $username, $email, $password, $role);

if ($stmt->execute()) {
    echo "<h3>数据库已重置!</h3>";
    echo "<p>创建了管理员账号:</p>";
    echo "<ul>";
    echo "<li>用户名: admin</li>";
    echo "<li>密码: admin123</li>";
    echo "<li>邮箱: wtejing@gmail.com</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>点击这里登录</a></p>";
} else {
    echo "创建管理员账户失败: " . $stmt->error; // 注意：使用$stmt->error而非$conn->error可提供更具体的错误
}

$conn->close();
?>