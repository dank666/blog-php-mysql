<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = include 'config.php';
$conn = new mysqli(
    $config['db']['host'],
    $config['db']['username'],
    $config['db']['password'],
    $config['db']['database']
);

// 删除可能存在的同名账号
$conn->query("DELETE FROM users WHERE username = 'admin'");

// 创建新管理员账户
$username = 'admin';
$email = 'wtejing@gmail.com';
$password = password_hash('admin123', PASSWORD_DEFAULT); 
$role = 'admin';

$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, display_name, avatar, created_at, is_active) 
                       VALUES (?, ?, ?, ?, '管理员', 'default.jpg', NOW(), 1)");
$stmt->bind_param("ssss", $username, $email, $password, $role);

if ($stmt->execute()) {
    echo "<h2>管理员账号创建成功!</h2>";
    echo "<p>用户名: admin<br>密码: admin123</p>";
    echo "<p><a href='login.php'>点击此处登录</a></p>";
} else {
    echo "错误: " . $stmt->error;
}
?>