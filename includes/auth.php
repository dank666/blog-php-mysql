<?php
session_start();

// 检查用户是否已登录
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// 检查是否是管理员
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// 获取当前登录用户ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// 获取当前登录用户信息
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $config;
    $conn = new mysqli(
        $config['db']['host'],
        $config['db']['username'],
        $config['db']['password'],
        $config['db']['database']
    );
    
    if ($conn->connect_error) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT id, username, email, display_name, bio, avatar, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $user;
    }
    
    $stmt->close();
    $conn->close();
    return null;
}

// 检查用户是否可以编辑特定文章
function canEditPost($postId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // 管理员可以编辑任何文章
    if (isAdmin()) {
        return true;
    }
    
    global $config;
    $conn = new mysqli(
        $config['db']['host'],
        $config['db']['username'],
        $config['db']['password'],
        $config['db']['database']
    );
    
    if ($conn->connect_error) {
        return false;
    }
    
    // 检查文章是否属于当前用户
    $stmt = $conn->prepare("SELECT 1 FROM blog_table WHERE id = ? AND user_id = ?");
    $userId = getCurrentUserId();
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $canEdit = $result->num_rows > 0;
    
    $stmt->close();
    $conn->close();
    
    return $canEdit;
}

// 要求登录，否则重定向到登录页面
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error_message'] = "请先登录";
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
}

// 要求管理员权限
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error_message'] = "需要管理员权限";
        header("Location: index.php");
        exit;
    }
}
?>