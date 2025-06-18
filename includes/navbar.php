<?php
// 确保包含必要的文件
if (!function_exists('isLoggedIn')) {
    include_once __DIR__ . '/auth.php';
}

// 获取当前用户信息
$currentUser = getCurrentUser();
?>

<div class="nav-bar">
    <!-- 添加网站标题到导航栏左侧 -->
    <div class="nav-brand">
        <a href="index.php" class="brand-link">
            <span style="--i:1">智</span>
            <span style="--i:2">域</span>
            <span style="--i:3">博</span>
            <span style="--i:4">客</span>
        </a>
    </div>
    
    <div class="nav-links">
        <a href="index.php" class="nav-link">首页</a>
        <?php if (isLoggedIn()): ?>
            <a href="new_post.php" class="nav-link">发布文章</a>
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="nav-link">管理面板</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- 搜索框 -->
    <div class="nav-search">
        <form action="index.php" method="GET">
            <input type="text" name="search" placeholder="搜索文章标题、内容或作者..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit">搜索</button>
        </form>
    </div>
    
    <div class="user-info">
        <?php if (isLoggedIn()): ?>
            <!-- 去掉"欢迎，"文字，直接显示用户名 -->
            <a href="profile.php" class="nav-link"><?php echo htmlspecialchars($currentUser['display_name']); ?></a>
            <a href="settings.php" class="nav-link">设置</a>
            <a href="logout.php" class="nav-link">退出</a>
        <?php else: ?>
            <a href="login.php" class="nav-link">登录</a>
            <a href="register.php" class="nav-link">注册</a>
        <?php endif; ?>
    </div>
</div>