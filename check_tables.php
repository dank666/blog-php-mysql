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

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 检查users表结构
echo "<h2>用户表结构:</h2>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table border='1'><tr><th>字段</th><th>类型</th><th>NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["Field"] . "</td>";
        echo "<td>" . $row["Type"] . "</td>";
        echo "<td>" . $row["Null"] . "</td>";
        echo "<td>" . $row["Key"] . "</td>";
        echo "<td>" . $row["Default"] . "</td>";
        echo "<td>" . $row["Extra"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "表不存在或无法查询";
}

$conn->close();
?>