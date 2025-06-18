<?php
// 加载配置
$config = include 'config.php';
include 'includes/auth.php';
include 'includes/database.php';

// 需要登录才能访问此页面
requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布文章 - 智域博客</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/navbar.css">
    <style>
        /* 美化整体页面 */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Microsoft YaHei', 'PingFang SC', sans-serif;
        }

        /* 隐藏旧的顶部栏 */
        .top-bar {
            display: none;
        }

        /* 主容器样式 */
        .writing-section {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* 表单标题 */
        .form-title {
            text-align: center;
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
        }

        /* 输入框美化 */
        #blogTitle {
            width: 100%;
            padding: 15px 20px;
            font-size: 18px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            outline: none;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box;
            margin-bottom: 20px;
        }

        #blogTitle:focus {
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        #blogTitle::placeholder {
            color: #999;
            font-style: italic;
        }

        /* 日期标签美化 */
        .date-container {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        #dateLabel {
            font-weight: 600;
            font-size: 16px;
            margin-right: 10px;
        }

        #blogDate {
            background: transparent;
            border: none;
            color: white;
            font-size: 16px;
            font-weight: 500;
        }

        /* 修复文件上传功能 */
        .file-upload-container {
            margin-bottom: 25px;
            position: relative;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            overflow: hidden;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .file-upload-wrapper:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }

        /* 修复文件输入框位置 - 让它完全覆盖按钮区域 */
        .file-upload-wrapper input[type=file] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-icon {
            margin-right: 8px;
        }

        /* 添加文件名显示区域 */
        .file-upload-status {
            margin-top: 10px;
            padding: 10px 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            color: #667eea;
            font-size: 14px;
            display: none;
        }

        .file-upload-status.show {
            display: block;
        }

        /* 位置选项美化 */
        .location-option {
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .location-option label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: #333;
        }

        .location-option input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
            accent-color: #667eea;
        }

        #locationStatus {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            font-size: 14px;
            color: #666;
        }

        /* 文本域美化 */
        #blogPara {
            width: 100%;
            padding: 20px;
            font-size: 16px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            outline: none;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box;
            margin-bottom: 25px;
            resize: vertical;
            min-height: 200px;
            font-family: inherit;
            line-height: 1.6;
        }

        #blogPara:focus {
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        #blogPara::placeholder {
            color: #999;
            font-style: italic;
        }

        /* 按钮美化 */
        .btn-modern {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            margin: 10px;
            text-align: center;
        }

        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-modern:active {
            transform: translateY(-1px);
        }

        /* 特殊按钮样式 */
        .btn-tag {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }

        .btn-tag:hover {
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
        }

        .btn-secondary:hover {
            box-shadow: 0 10px 25px rgba(149, 165, 166, 0.4);
        }

        /* 按钮容器 */
        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        /* 标签容器美化 */
        #selectedTagsContainer {
            margin: 25px 0;
            padding: 20px;
            background: rgba(78, 205, 196, 0.1);
            border-radius: 15px;
            border: 1px solid rgba(78, 205, 196, 0.2);
            text-align: center;
        }

        .selected-tags-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .tag-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .tag-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .tag-badge .remove-tag {
            margin-left: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .tag-badge .remove-tag:hover {
            color: #ff6b6b;
        }

        /* 模态框美化 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border: none;
            width: 80%;
            max-width: 600px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 20px;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #ff6b6b;
        }

        .modal h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* 标签列表美化 */
        .tag-list-container {
            margin-bottom: 25px;
        }

        .preview-tags, .all-tags {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .all-tags {
            max-height: 200px;
            overflow-y: auto;
        }

        .tag-item {
            margin: 8px;
            display: inline-block;
        }

        .tag-item input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
            accent-color: #667eea;
        }

        .tag-item label {
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 15px;
            background: white;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .tag-item label:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        #showAllTagsBtn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 15px;
            transition: all 0.3s ease;
        }

        #showAllTagsBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* 自定义标签输入 */
        #customTags {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        #customTags:focus {
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
            outline: none;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .writing-section {
                margin: 20px;
                padding: 25px;
            }

            .form-title {
                font-size: 24px;
            }

            #blogTitle {
                font-size: 16px;
                padding: 12px 15px;
            }

            #blogPara {
                padding: 15px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }

            .btn-modern {
                padding: 12px 25px;
                font-size: 14px;
                margin: 5px;
            }
        }

        /* 加载动画 */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #667eea;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="writing-section">
        <h1 class="form-title">✍️ 发布新文章</h1>
        
        <form action="blog_post_process.php" method="POST" enctype="multipart/form-data">
            <input id="blogTitle" name="blogtitle" type="text" placeholder="请输入文章标题..." autocomplete="off">
            
            <div class="date-container">
                <span id="dateLabel">📅 发布日期:</span>
                <input id="blogDate" name="blogdate" readonly>
            </div>
            
            <div class="file-upload-container">
                <div class="file-upload-wrapper">
                    <span class="file-upload-icon">📸</span>
                    <span id="file-upload-text">选择封面图片</span>
                    <input type="file" name="uploadimage" accept="image/*" id="upload-image">
                </div>
                <div class="file-upload-status" id="file-upload-status"></div>
            </div>

            <!-- 位置选项 -->
            <div class="location-option">
                <label for="enableLocation">
                    <input type="checkbox" id="enableLocation" name="enable_location">
                    📍 显示我的当前位置
                </label>
                <div id="locationStatus" style="display:none;"></div>
                <input type="hidden" id="locationData" name="location_data">
            </div>

            <textarea id="blogPara" name="blogpara" cols="75" rows="10" placeholder="在这里写下你的精彩内容..." autocomplete="off"></textarea>

            <!-- 标签选择 -->
            <div class="button-container">
                <a href="#" class="btn-modern btn-tag" onclick="openTagModal(); return false;">
                    🏷️ 添加标签
                </a>
            </div>

            <!-- 显示已选标签的区域 -->
            <div id="selectedTagsContainer" style="display: none;">
                <h4 style="margin: 0 0 10px 0; color: #333;">📌 已选标签</h4>
                <div id="selectedTags" class="selected-tags-area"></div>
            </div>

            <div class="button-container">
                <button type="submit" class="btn-modern">🚀 发布文章</button>
            </div>

            <!-- 标签模态框 -->
            <div id="tagModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeTagModal()">&times;</span>
                    <h3>🏷️ 选择标签 
                        <button type="button" id="showAllTagsBtn" onclick="toggleAllTags()">显示全部</button>
                    </h3>
                    <div id="tagListContainer" class="tag-list-container">
                        <div class="tag-list" id="tagList">
                            <?php
                            $conn = getDbConnection($config);
                            $result = $conn->query("SELECT * FROM tags ORDER BY name");
                            $count = 0;
                            $totalTags = $result ? $result->num_rows : 0;
                            
                            if ($totalTags > 0) {
                                echo '<div id="previewTags" class="preview-tags">';
                                // 只显示前5个标签作为预览
                                while ($row = $result->fetch_assoc()) {
                                    $count++;
                                    echo '<div class="tag-item">';
                                    echo '<input type="checkbox" name="tags[]" value="'.$row['id'].'" id="tag'.$row['id'].'" onchange="updateSelectedTags()">';
                                    echo '<label for="tag'.$row['id'].'">'.$row['name'].'</label>';
                                    echo '</div>';
                                    
                                    // 只显示前5个作为预览
                                    if ($count >= 5) {
                                        break;
                                    }
                                }
                                echo '</div>';
                                
                                // 重置结果指针
                                $result->data_seek(0);
                                
                                // 所有标签（初始隐藏）
                                echo '<div id="allTags" class="all-tags" style="display:none;">';
                                while ($row = $result->fetch_assoc()) {
                                    echo '<div class="tag-item">';
                                    echo '<input type="checkbox" name="tags[]" value="'.$row['id'].'" id="tag'.$row['id'].'" onchange="updateSelectedTags()">';
                                    echo '<label for="tag'.$row['id'].'">'.$row['name'].'</label>';
                                    echo '</div>';
                                }
                                echo '</div>';
                                
                                // 如果标签太少，隐藏显示全部按钮
                                if ($totalTags <= 5) {
                                    echo '<script>document.getElementById("showAllTagsBtn").style.display = "none";</script>';
                                }
                            } else {
                                echo '<p style="text-align: center; color: #999;">暂无标签</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <h3>✨ 添加自定义标签</h3>
                    <p style="color: #666; font-size: 14px;">用逗号分隔多个标签</p>
                    <input type="text" name="custom_tags" id="customTags" placeholder="例如: 篮球, 咖啡, 阅读">
                    <div style="text-align: center;">
                        <button type="button" onclick="saveTagsAndClose()" class="btn-modern">✅ 确定</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="button-container">
            <a href="index.php" class="btn-modern btn-secondary">⬅️ 返回主页</a>
        </div>
    </div>

    <script src="scripts/script.js"></script>
    <script>
        // 文件上传功能
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('upload-image');
            const fileStatus = document.getElementById('file-upload-status');
            const fileText = document.getElementById('file-upload-text');
            
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // 验证文件类型
                    if (!file.type.startsWith('image/')) {
                        alert('请选择图片文件！');
                        this.value = '';
                        return;
                    }
                    
                    // 验证文件大小（5MB）
                    if (file.size > 5 * 1024 * 1024) {
                        alert('图片大小不能超过5MB！');
                        this.value = '';
                        return;
                    }
                    
                    // 显示文件信息
                    fileText.textContent = `已选择: ${file.name}`;
                    fileStatus.textContent = `📁 文件: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    fileStatus.classList.add('show');
                } else {
                    fileText.textContent = '选择封面图片';
                    fileStatus.classList.remove('show');
                }
            });
        });
        
        // 恢复用户输入
        document.addEventListener('DOMContentLoaded', function() {
            var savedTitle = localStorage.getItem('blogtitle');
            var savedContent = localStorage.getItem('blogpara');
            
            if (savedTitle) {
                document.getElementById('blogTitle').value = savedTitle;
            }
            
            if (savedContent) {
                document.getElementById('blogPara').value = savedContent;
            }
            
            // 初始化标签列表
            const tagList = document.getElementById('tagList');
            if (tagList) {
                tagList.classList.remove('expanded');
            }
        });
        
        // 存储已选择的标签
        let selectedTagsMap = new Map();
        let customTagsArray = [];
        
        // 标签模态框功能
        function openTagModal() {
            document.getElementById('tagModal').style.display = 'block';
        }
        
        function closeTagModal() {
            document.getElementById('tagModal').style.display = 'none';
        }
        
        // 更新已选标签（用于复选框改变时）
        function updateSelectedTags() {
            const checkboxes = document.querySelectorAll('input[name="tags[]"]:checked');
            
            selectedTagsMap.clear();
            
            checkboxes.forEach(checkbox => {
                const tagId = checkbox.value;
                const tagName = document.querySelector(`label[for="tag${tagId}"]`).textContent;
                selectedTagsMap.set(tagId, tagName);
            });
            
            renderSelectedTags();
        }
        
        // 保存标签并关闭模态框
        function saveTagsAndClose() {
            const customTagsInput = document.getElementById('customTags').value.trim();
            
            if (customTagsInput) {
                customTagsArray = customTagsInput.split(',')
                    .map(tag => tag.trim())
                    .filter(tag => tag.length > 0);
            }
            
            renderSelectedTags();
            closeTagModal();
        }
        
        // 渲染已选标签到页面
        function renderSelectedTags() {
            const container = document.getElementById('selectedTags');
            container.innerHTML = '';
            
            // 添加已有标签
            selectedTagsMap.forEach((tagName, tagId) => {
                const tagElement = createTagElement(tagName, 'existing', tagId);
                container.appendChild(tagElement);
                
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'tags[]';
                hiddenInput.value = tagId;
                container.appendChild(hiddenInput);
            });
            
            // 添加自定义标签
            customTagsArray.forEach(tagName => {
                const tagElement = createTagElement(tagName, 'custom');
                container.appendChild(tagElement);
                
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'custom_tags_array[]';
                hiddenInput.value = tagName;
                container.appendChild(hiddenInput);
            });
            
            // 显示或隐藏容器
            document.getElementById('selectedTagsContainer').style.display = 
                (selectedTagsMap.size > 0 || customTagsArray.length > 0) ? 'block' : 'none';
        }
        
        // 创建标签元素
        function createTagElement(tagName, type, tagId = null) {
            const tagElement = document.createElement('span');
            tagElement.className = 'tag-badge';
            tagElement.textContent = tagName;
            
            const removeButton = document.createElement('span');
            removeButton.className = 'remove-tag';
            removeButton.textContent = ' ×';
            removeButton.onclick = function() {
                if (type === 'existing' && tagId) {
                    const checkbox = document.getElementById(`tag${tagId}`);
                    if (checkbox) checkbox.checked = false;
                    selectedTagsMap.delete(tagId);
                } else if (type === 'custom') {
                    customTagsArray = customTagsArray.filter(t => t !== tagName);
                }
                renderSelectedTags();
            };
            
            tagElement.appendChild(removeButton);
            return tagElement;
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            var modal = document.getElementById('tagModal');
            if (event.target == modal) {
                closeTagModal();
            }
        }

        // 切换显示所有标签
        function toggleAllTags() {
            const previewTags = document.getElementById('previewTags');
            const allTags = document.getElementById('allTags');
            const button = document.getElementById('showAllTagsBtn');
            
            if (allTags.style.display === 'none') {
                previewTags.style.display = 'none';
                allTags.style.display = 'block';
                button.textContent = '收起';
            } else {
                previewTags.style.display = 'block';
                allTags.style.display = 'none';
                button.textContent = '显示全部';
            }
        }
        
        // 位置功能
        document.addEventListener('DOMContentLoaded', function() {
            const enableLocationCheckbox = document.getElementById('enableLocation');
            const locationStatusDiv = document.getElementById('locationStatus');
            const locationDataInput = document.getElementById('locationData');
            
            enableLocationCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    locationStatusDiv.style.display = 'block';
                    locationStatusDiv.textContent = '🔍 正在获取位置信息...';
                    
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function(position) {
                                const latitude = position.coords.latitude;
                                const longitude = position.coords.longitude;
                                
                                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`)
                                    .then(response => response.json())
                                    .then(data => {
                                        const locationInfo = {
                                            latitude: latitude,
                                            longitude: longitude,
                                            address: data.display_name || '未知地点'
                                        };
                                        
                                        locationDataInput.value = JSON.stringify(locationInfo);
                                        locationStatusDiv.textContent = `📍 ${locationInfo.address}`;
                                    })
                                    .catch(error => {
                                        console.error('获取地址信息失败:', error);
                                        const locationInfo = {
                                            latitude: latitude,
                                            longitude: longitude,
                                            address: '位置已获取，但无法解析详细地址'
                                        };
                                        locationDataInput.value = JSON.stringify(locationInfo);
                                        locationStatusDiv.textContent = `📍 位置已获取 (${latitude.toFixed(6)}, ${longitude.toFixed(6)})`;
                                    });
                            },
                            function(error) {
                                let errorMessage = '❌ 无法获取位置信息: ';
                                switch(error.code) {
                                    case error.PERMISSION_DENIED:
                                        errorMessage += '用户拒绝了位置请求。';
                                        break;
                                    case error.POSITION_UNAVAILABLE:
                                        errorMessage += '位置信息不可用。';
                                        break;
                                    case error.TIMEOUT:
                                        errorMessage += '获取位置请求超时。';
                                        break;
                                    default:
                                        errorMessage += '发生未知错误。';
                                        break;
                                }
                                locationStatusDiv.textContent = errorMessage;
                                enableLocationCheckbox.checked = false;
                            }
                        );
                    } else {
                        locationStatusDiv.textContent = '❌ 您的浏览器不支持地理位置功能。';
                        enableLocationCheckbox.checked = false;
                    }
                } else {
                    locationStatusDiv.style.display = 'none';
                    locationDataInput.value = '';
                }
            });
        });

        // 表单提交时的加载效果
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.textContent = '🚀 发布中...';
            submitBtn.disabled = true;
            document.querySelector('.writing-section').classList.add('loading');
        });
    </script>
</body>
</html>