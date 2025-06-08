<?php
session_start();
include 'includes/auth.php';
include 'includes/database.php';
include 'includes/ensemble_recommendations.php';
$config = include 'config.php';

// 设置响应头为JSON
header('Content-Type: application/json');

// 连接数据库
$conn = getDbConnection($config);

// 获取当前用户ID（如果已登录）
$current_user_id = isLoggedIn() ? getCurrentUserId() : null;

// 获取推荐文章列表
$recommended_posts = getEnsembleRecommendations($conn, $current_user_id, 10);

// 返回JSON格式的推荐结果
echo json_encode($recommended_posts);
$conn->close();
?>