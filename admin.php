<?php
include 'includes/auth.php';
include 'includes/database.php';
$config = include 'config.php';

// CSRF Token函数
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}

// 要求管理员权限
requireAdmin();

// 连接数据库
$conn = getDbConnection();

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

$error = '';
$success = '';

// 获取统计数据
// 1. 用户注册趋势（最近12个月）
$months = [];
$userCounts = [];

// 获取最近12个月的月份
for ($i = 11; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}

// 查询用户注册数据
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute();
$result = $stmt->get_result();
$monthUserCounts = [];
while ($row = $result->fetch_assoc()) {
    $monthUserCounts[$row['month']] = $row['count'];
}
$stmt->close();

// 填充每个月的数据
foreach ($months as $month) {
    $userCounts[] = isset($monthUserCounts[$month]) ? $monthUserCounts[$month] : 0;
}

// 2. 文章发布趋势（最近12个月）
$postCounts = [];

// 查询文章发布数据
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(DATE(topic_date), '%Y-%m') AS month,
        COUNT(*) as count
    FROM blog_table
    WHERE DATE(topic_date) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(DATE(topic_date), '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute();
$result = $stmt->get_result();
$monthPostCounts = [];
while ($row = $result->fetch_assoc()) {
    $monthPostCounts[$row['month']] = $row['count'];
}
$stmt->close();

// 填充每个月的数据
foreach ($months as $month) {
    $postCounts[] = isset($monthPostCounts[$month]) ? $monthPostCounts[$month] : 0;
}

// 3. 热门文章（唯一浏览量前10）
$hotPosts = [];
$stmt = $conn->prepare("
    SELECT 
        b.id, 
        b.topic_title, 
        b.topic_date,
        u.username,
        u.id as user_id,
        COUNT(v.id) as view_count
    FROM blog_table b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN blog_views v ON b.id = v.post_id
    GROUP BY b.id, b.topic_title, b.topic_date, u.username, u.id
    ORDER BY view_count DESC
    LIMIT 10
");
$stmt->execute();
$hotPosts = $stmt->get_result();
$stmt->close();

// 4. 活跃用户（发文数量前10）
$activeUsers = [];
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.username,
        COUNT(b.id) as post_count
    FROM users u
    LEFT JOIN blog_table b ON u.id = b.user_id
    GROUP BY u.id
    ORDER BY post_count DESC
    LIMIT 10
");
$stmt->execute();
$activeUsers = $stmt->get_result();
$stmt->close();

// 5. 总体统计数字
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$totalUsersCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM blog_table");
$stmt->execute();
$totalPostsCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 正确查询总浏览量
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM blog_views");
$stmt->execute();
$totalViewsCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 获取最近注册用户数（过去7天）
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$newUsersCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 获取最近发表文章数（过去7天）
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM blog_table WHERE DATE(topic_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$newPostsCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 分页设置
$usersPerPage = 30; // 每页显示的用户数
$postsPerPage = 30; // 每页显示的文章数
$userPage = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
$postPage = isset($_GET['post_page']) ? (int)$_GET['post_page'] : 1;

// 确保页码至少为1
if ($userPage < 1) $userPage = 1;
if ($postPage < 1) $postPage = 1;

// 处理用户角色更改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role'], $_POST['csrf_token'])) {
    // 验证CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = "安全验证失败，请重试";
    } else {
        $userId = (int)$_POST['user_id'];
        $newRole = $_POST['role'];

        // 确保第一个管理员的权限不能被修改
        $firstAdminId = 1; // 假设第一个管理员的ID是1
        if ($userId === $firstAdminId) {
            $error = "无法修改第一个管理员的权限。";
        } else {
            // 更新用户角色
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $newRole, $userId);
            if ($stmt->execute()) {
                $success = "用户角色已更新。";
            } else {
                $error = "更新用户角色失败：" . $conn->error;
            }
            $stmt->close();
        }
    }
}

// 处理用户删除
if (isset($_GET['delete_user'])) {
    $userId = (int)$_GET['delete_user'];
    
    // 阻止删除自己的账号
    if ($userId == getCurrentUserId()) {
        $error = "不能删除当前登录的管理员账号";
    } else {
        // 开始事务，确保删除用户及其文章的原子性
        $conn->begin_transaction();
        
        try {
            // 先查询用户所有文章的图片
            $stmt = $conn->prepare("SELECT image_filename FROM blog_table WHERE user_id = ? AND image_filename != 'NONE'");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 删除所有图片文件
            while ($row = $result->fetch_assoc()) {
                $imagePath = "images/" . $row['image_filename'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            // 删除用户所有文章
            $stmt = $conn->prepare("DELETE FROM blog_table WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // 删除用户头像
            $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['avatar']) && $row['avatar'] != 'default.jpg') {
                    $avatarPath = "avatars/" . $row['avatar'];
                    if (file_exists($avatarPath)) {
                        unlink($avatarPath);
                    }
                }
            }
            
            // 删除用户账号
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // 提交事务
            $conn->commit();
            $success = "用户及其所有内容已删除";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "删除用户失败: " . $e->getMessage();
        }
    }
}

// 处理文章删除
if (isset($_GET['delete_post'])) {
    $postId = (int)$_GET['delete_post'];
    
    // 查询文章图片
    $stmt = $conn->prepare("SELECT image_filename FROM blog_table WHERE id = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // 删除关联图片
        if (!empty($row['image_filename']) && $row['image_filename'] != 'NONE') {
            $imagePath = "images/" . $row['image_filename'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // 删除文章记录
        $stmt = $conn->prepare("DELETE FROM blog_table WHERE id = ?");
        $stmt->bind_param("i", $postId);
        
        if ($stmt->execute()) {
            $success = "文章已删除";
        } else {
            $error = "删除文章失败: " . $conn->error;
        }
    }
    $stmt->close();
}

// 获取用户总数
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// 计算总页数和偏移量
$totalUserPages = ceil($totalUsers / $usersPerPage);
$userOffset = ($userPage - 1) * $usersPerPage;

// 获取当前页的用户
$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $usersPerPage, $userOffset);
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

// 获取文章总数
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM blog_table");
$countStmt->execute();
$totalPosts = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// 计算总页数和偏移量
$totalPostPages = ceil($totalPosts / $postsPerPage);
$postOffset = ($postPage - 1) * $postsPerPage;

// 获取当前页的文章
$stmt = $conn->prepare("
    SELECT b.id, b.topic_title, b.topic_date, u.username, u.id as user_id 
    FROM blog_table b 
    JOIN users u ON b.user_id = u.id 
    ORDER BY b.id DESC LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $postsPerPage, $postOffset);
$stmt->execute();
$posts = $stmt->get_result();
$stmt->close();

$conn->close();

// 获取当前活动的标签
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理面板 - 智域博客</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
    <style>
        /* 整体页面美化 - 与主界面保持一致 */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Microsoft YaHei', 'PingFang SC', sans-serif;
            color: #333;
        }

        /* 隐藏旧的top-bar */
        .top-bar {
            display: none;
        }

        /* 管理面板容器美化 */
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* 页面标题美化 */
        .admin-container h2 {
            margin: 0 0 30px 0;
            color: #667eea;
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }

        /* 消息提示美化 */
        .error {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error::before {
            content: '⚠️';
            font-size: 18px;
        }

        .success {
            color: #4ecdc4;
            background: rgba(78, 205, 196, 0.1);
            border: 1px solid rgba(78, 205, 196, 0.3);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success::before {
            content: '✅';
            font-size: 18px;
        }

        /* 标签页美化 */
        .tabs {
            display: flex;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .tab {
            flex: 1;
            padding: 15px 20px;
            cursor: pointer;
            border-radius: 15px;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            color: #666;
            position: relative;
        }

        .tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* 标签页内容美化 */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* 管理区域美化 */
        .admin-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .admin-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
        }

        .admin-section h3 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #667eea;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-section h3::before {
            font-size: 28px;
        }

        .admin-section h3:nth-of-type(1)::before { content: '👥'; }
        .admin-section h3:nth-of-type(2)::before { content: '📝'; }
        .admin-section h3:nth-of-type(3)::before { content: '📊'; }

        /* 表格美化 */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .admin-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: none;
        }

        .admin-table th:first-child {
            border-top-left-radius: 15px;
        }

        .admin-table th:last-child {
            border-top-right-radius: 15px;
        }

        .admin-table td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            font-size: 14px;
            vertical-align: middle;
        }

        .admin-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:last-child td:first-child {
            border-bottom-left-radius: 15px;
        }

        .admin-table tr:last-child td:last-child {
            border-bottom-right-radius: 15px;
        }

        /* 操作按钮美化 */
        .action-btn {
            display: inline-block;
            padding: 8px 15px;
            margin: 0 3px;
            border-radius: 20px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .edit-btn {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            box-shadow: 0 3px 10px rgba(78, 205, 196, 0.3);
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
        }

        .delete-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            box-shadow: 0 3px 10px rgba(255, 107, 107, 0.3);
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        /* 角色选择下拉框美化 */
        select[name="role"] {
            padding: 6px 12px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 15px;
            background: white;
            color: #333;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        select[name="role"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
        }

        select[name="role"]:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        /* 分页美化 */
        .pagination {
            margin: 30px 0;
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }

        .page-link:not(.active) {
            color: #667eea;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .page-link:not(.active):hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .page-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* 统计卡片美化 */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card-value {
            font-size: 32px;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .stat-card-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        /* 图表容器美化 */
        .stats-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: 400px;
            position: relative;
            overflow: hidden;
        }

        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
        }

        .chart-container h4 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #667eea;
            font-size: 18px;
            font-weight: 700;
        }

        .chart-container canvas {
            max-height: 320px;
        }

        /* 统计表格容器美化 */
        .stats-tables {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
        }

        .stats-table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .stats-table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4ecdc4);
        }

        .stats-table-container h4 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #667eea;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-table-container h4::before {
            font-size: 20px;
        }

        .stats-table-container h4:nth-of-type(1)::before { content: '🔥'; }
        .stats-table-container h4:nth-of-type(2)::before { content: '⭐'; }

        /* 链接美化 */
        .admin-table a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .admin-table a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .admin-container {
                padding: 20px 15px;
            }

            .tabs {
                flex-direction: column;
                gap: 5px;
            }

            .tab {
                padding: 12px 15px;
            }

            .admin-section {
                padding: 20px;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }

            .stats-charts {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 300px;
            }

            .stats-tables {
                grid-template-columns: 1fr;
            }

            .admin-table {
                font-size: 13px;
            }

            .admin-table th,
            .admin-table td {
                padding: 10px 8px;
            }

            .action-btn {
                display: block;
                margin: 2px 0;
                text-align: center;
            }
        }

        /* 毛玻璃效果增强 */
        @supports (backdrop-filter: blur(10px)) {
            .admin-section,
            .stat-card,
            .chart-container,
            .stats-table-container,
            .tabs {
                backdrop-filter: blur(15px);
                background: rgba(255, 255, 255, 0.8);
            }
        }

        /* 加载动画 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-section,
        .stat-card {
            animation: fadeInUp 0.6s ease-out;
        }

        /* 为不同的卡片添加延迟动画 */
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="admin-container">
        <h2>🛠️ 管理面板</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab <?php echo $activeTab === 'users' ? 'active' : ''; ?>" data-tab="users">👥 用户管理</div>
            <div class="tab <?php echo $activeTab === 'posts' ? 'active' : ''; ?>" data-tab="posts">📝 文章管理</div>
            <div class="tab <?php echo $activeTab === 'stats' ? 'active' : ''; ?>" data-tab="stats">📊 统计分析</div>
        </div>
        
        <div class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" id="users-tab">
            <div class="admin-section">
                <h3>用户管理</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>电子邮件</th>
                            <th>角色</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <select name="role" onchange="this.form.submit()" <?php echo $user['id'] === 1 ? 'disabled' : ''; ?>>
                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>用户</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>管理员</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="action-btn edit-btn">👀 查看</a>
                                    <?php if ($user['id'] != getCurrentUserId()): ?>
                                        <a href="admin.php?delete_user=<?php echo $user['id']; ?>&tab=users&user_page=<?php echo $userPage; ?>" class="action-btn delete-btn" onclick="return confirm('确定要删除此用户及其所有内容吗？此操作不可撤销!');">🗑️ 删除</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- 用户分页 -->
                <div class="pagination">
                    <?php if ($totalUserPages > 1): ?>
                        <?php if ($userPage > 1): ?>
                            <a href="?user_page=1&tab=users" class="page-link">⏮️</a>
                            <a href="?user_page=<?php echo $userPage - 1; ?>&tab=users" class="page-link">◀️</a>
                        <?php endif; ?>
                        
                        <?php
                        // 显示附近的几个页码
                        $startPage = max(1, $userPage - 2);
                        $endPage = min($totalUserPages, $userPage + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $userPage): ?>
                                <span class="page-link active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?user_page=<?php echo $i; ?>&tab=users" class="page-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($userPage < $totalUserPages): ?>
                            <a href="?user_page=<?php echo $userPage + 1; ?>&tab=users" class="page-link">▶️</a>
                            <a href="?user_page=<?php echo $totalUserPages; ?>&tab=users" class="page-link">⏭️</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="tab-content <?php echo $activeTab === 'posts' ? 'active' : ''; ?>" id="posts-tab">
            <div class="admin-section">
                <h3>文章管理</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>标题</th>
                            <th>作者</th>
                            <th>发布日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($post = $posts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $post['id']; ?></td>
                                <td><?php echo htmlspecialchars($post['topic_title']); ?></td>
                                <td>
                                    <a href="profile.php?id=<?php echo $post['user_id']; ?>">
                                        <?php echo htmlspecialchars($post['username']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($post['topic_date']); ?></td>
                                <td>
                                    <a href="post.php?id=<?php echo $post['id']; ?>" class="action-btn edit-btn">👀 查看</a>
                                    <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="action-btn edit-btn">✏️ 编辑</a>
                                    <a href="admin.php?delete_post=<?php echo $post['id']; ?>&tab=posts&post_page=<?php echo $postPage; ?>" class="action-btn delete-btn" onclick="return confirm('确定要删除此文章吗？');">🗑️ 删除</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- 文章分页 -->
                <div class="pagination">
                    <?php if ($totalPostPages > 1): ?>
                        <?php if ($postPage > 1): ?>
                            <a href="?post_page=1&tab=posts" class="page-link">⏮️</a>
                            <a href="?post_page=<?php echo $postPage - 1; ?>&tab=posts" class="page-link">◀️</a>
                        <?php endif; ?>
                        
                        <?php
                        // 显示附近的几个页码
                        $startPage = max(1, $postPage - 2);
                        $endPage = min($totalPostPages, $postPage + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $postPage): ?>
                                <span class="page-link active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?post_page=<?php echo $i; ?>&tab=posts" class="page-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($postPage < $totalPostPages): ?>
                            <a href="?post_page=<?php echo $postPage + 1; ?>&tab=posts" class="page-link">▶️</a>
                            <a href="?post_page=<?php echo $totalPostPages; ?>&tab=posts" class="page-link">⏭️</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-content <?php echo $activeTab === 'stats' ? 'active' : ''; ?>" id="stats-tab">
            <div class="admin-section">
                <h3>统计与分析</h3>
                
                <!-- 数据概览卡片 -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $totalUsersCount; ?></div>
                        <div class="stat-card-label">👥 总用户数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $totalPostsCount; ?></div>
                        <div class="stat-card-label">📝 总文章数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $totalViewsCount; ?></div>
                        <div class="stat-card-label">👁️ 总浏览量</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $newUsersCount; ?></div>
                        <div class="stat-card-label">🆕 7天新增用户</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-value"><?php echo $newPostsCount; ?></div>
                        <div class="stat-card-label">📄 7天新增文章</div>
                    </div>
                </div>
                
                <!-- 趋势图表 -->
                <div class="stats-charts">
                    <div class="chart-container">
                        <h4>📈 用户增长趋势</h4>
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h4>📊 文章发布趋势</h4>
                        <canvas id="postGrowthChart"></canvas>
                    </div>
                </div>
                
                <!-- 热门文章和活跃用户 -->
                <div class="stats-tables">
                    <div class="stats-table-container">
                        <h4>热门文章（阅读量排名）</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>标题</th>
                                    <th>作者</th>
                                    <th>发布日期</th>
                                    <th>阅读量</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                while ($post = $hotPosts->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($rank <= 3): ?>
                                                <span style="font-size: 18px;">
                                                    <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo $rank; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="post.php?id=<?php echo $post['id']; ?>">
                                                <?php echo htmlspecialchars($post['topic_title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="profile.php?id=<?php echo $post['user_id']; ?>">
                                                <?php echo htmlspecialchars($post['username']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($post['topic_date']); ?></td>
                                        <td><?php echo $post['view_count']; ?></td>
                                    </tr>
                                <?php $rank++; endwhile; ?>
                                <?php if ($rank === 1): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #999; padding: 30px;">📭 暂无数据</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- 活跃用户 -->
                    <div class="stats-table-container">
                        <h4>活跃用户（发文数排名）</h4>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>用户名</th>
                                    <th>文章数量</th>
                                    <th>查看</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                while ($user = $activeUsers->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($rank <= 3): ?>
                                                <span style="font-size: 18px;">
                                                    <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo $rank; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo $user['post_count']; ?></td>
                                        <td>
                                            <a href="profile.php?id=<?php echo $user['id']; ?>" class="action-btn edit-btn">👀 查看资料</a>
                                        </td>
                                    </tr>
                                <?php $rank++; endwhile; ?>
                                <?php if ($rank === 1): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #999; padding: 30px;">📭 暂无数据</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 添加Chart.js库 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <script>
        // 保存图表实例的全局变量
        let userChart = null;
        let postChart = null;
        
        // 统计图表初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 获取所有标签页按钮
            const tabs = document.querySelectorAll('.tab');
            
            // 为每个标签页添加点击事件
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // 获取标签页ID
                    const tabId = this.getAttribute('data-tab');
                    
                    // 更新URL参数但不刷新页面
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);
                    
                    // 隐藏所有标签页内容
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // 移除所有标签页按钮的active类
                    document.querySelectorAll('.tab').forEach(t => {
                        t.classList.remove('active');
                    });
                    
                    // 显示当前标签页
                    document.getElementById(tabId + '-tab').classList.add('active');
                    this.classList.add('active');
                    
                    // 如果是统计标签页，初始化图表
                    if (tabId === 'stats') {
                        // 使用setTimeout确保DOM已更新
                        setTimeout(initCharts, 50);
                    }
                });
            });
            
            // 如果当前是统计标签页，初始化图表
            if (document.getElementById('stats-tab').classList.contains('active')) {
                // 使用setTimeout确保DOM已完全加载
                setTimeout(initCharts, 50);
            }
        });
        
        function initCharts() {
            // 确保相关的canvas元素存在
            const userCtx = document.getElementById('userGrowthChart');
            const postCtx = document.getElementById('postGrowthChart');
            
            if (!userCtx || !postCtx) {
                console.error('Canvas元素不存在');
                return;
            }
            
            // 用户增长趋势图 - 销毁旧实例
            if (userChart) {
                userChart.destroy();
            }
            
            // 文章发布趋势图 - 销毁旧实例
            if (postChart) {
                postChart.destroy();
            }
            
            // 获取月份和数据
            const months = <?php echo json_encode($months); ?>;
            const userCounts = <?php echo json_encode($userCounts); ?>;
            const postCounts = <?php echo json_encode($postCounts); ?>;

            // 如果数据为空，添加当前月份的默认数据
            if (months.length === 0) {
                const currentDate = new Date();
                const currentMonth = currentDate.getFullYear() + '-' + 
                                    String(currentDate.getMonth() + 1).padStart(2, '0');
                months.push(currentMonth);
                userCounts.push(0);
                postCounts.push(0);
            }
            
            // Chart.js 配置
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                animation: {
                    duration: 0 // 禁用动画
                }
            };
            
            // 重新创建用户增长趋势图
            userChart = new Chart(userCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: '新注册用户数',
                        data: userCounts,
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: chartOptions
            });
            
            // 重新创建文章发布趋势图
            postChart = new Chart(postCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: '发布文章数',
                        data: postCounts,
                        backgroundColor: 'rgba(78, 205, 196, 0.8)',
                        borderColor: 'rgba(78, 205, 196, 1)',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: chartOptions
            });
        }
        
        // 页面大小变化时重新渲染图表的优化处理
        let resizeTimeout;
        window.addEventListener('resize', function() {
            // 如果当前在统计标签页，且图表已初始化
            if (document.getElementById('stats-tab').classList.contains('active') && (userChart || postChart)) {
                // 使用防抖动优化，避免频繁重绘
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    // 不是完全重绘，只调整尺寸
                    if (userChart) userChart.resize();
                    if (postChart) postChart.resize();
                }, 300);
            }
        });
    </script>
</body>
</html>