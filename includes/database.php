<?php
/**
 * 数据库连接函数
 * 返回一个数据库连接对象
 */
function getDbConnection() {
    global $config;
    
    if (!isset($config)) {
        $config = include __DIR__ . '/../config.php';
    }
    
    $conn = new mysqli(
        $config['db']['host'],
        $config['db']['username'],
        $config['db']['password'],
        $config['db']['database']
    );
    
    // 设置字符集
    $conn->set_charset("utf8");
    
    return $conn;
}
?>