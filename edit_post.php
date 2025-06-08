<?php
// 加载配置
$config = include 'config.php';

// 验证ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("错误：无效的文章ID");
}

$id = (int)$_GET['id'];
$post = null;

// 连接数据库
$conn = new mysqli(
    $config['db']['host'],
    $config['db']['username'],
    $config['db']['password'],
    $config['db']['database']
);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 获取当前文章的标签
$existingTags = [];
$stmt = $conn->prepare("
    SELECT t.id, t.name 
    FROM tags t
    JOIN post_tags pt ON t.id = pt.tag_id
    WHERE pt.post_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$tagResult = $stmt->get_result();
while ($tag = $tagResult->fetch_assoc()) {
    $existingTags[$tag['id']] = $tag['name'];
}
$stmt->close();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = (int)$_POST['post_id'];
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tags = $_POST['tags'] ?? []; // 获取选中的已有标签
    $customTags = $_POST['custom_tags_array'] ?? []; // 获取自定义标签
    $error = '';

    // 检查必填字段
    if (empty($title) || empty($date) || empty($content)) {
        $error = "标题、日期和内容不能为空。";
    }

    // 如果有错误，返回错误提示
    if (!empty($error)) {
        echo "<script>alert('$error'); window.history.back();</script>";
        exit;
    }

    // 处理图片上传
    $imageFilename = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "images/";
        
        // 确保目录存在
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        // 生成唯一文件名
        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageFilename = uniqid('post_') . '.' . $extension;
        $target_file = $target_dir . $imageFilename;
        
        // 移动上传的文件
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            echo "<script>alert('图片上传失败，请重试。'); window.history.back();</script>";
            exit;
        }
    }

    // 处理位置数据
    $location = null;
    if (!empty($_POST['location_data'])) {
        $locationInfo = json_decode($_POST['location_data'], true);
        if ($locationInfo && isset($locationInfo['address'])) {
            $location = $locationInfo['address'];
            if (strlen($location) > 255) {
                $location = substr($location, 0, 252) . '...';
            }
        }
    }

    // 使用事务确保数据一致性
    $conn->begin_transaction();
    
    try {
        // 更新文章内容
        if ($imageFilename) {
            // 如果有新图片
            $stmt = $conn->prepare("UPDATE blog_table SET topic_title = ?, topic_date = ?, topic_para = ?, image_filename = ?, location = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $title, $date, $content, $imageFilename, $location, $postId);
        } else {
            // 如果没有新图片
            $stmt = $conn->prepare("UPDATE blog_table SET topic_title = ?, topic_date = ?, topic_para = ?, location = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $title, $date, $content, $location, $postId);
        }
        $stmt->execute();
        $stmt->close();
        
        // 删除旧的标签关联 - 确保此语句执行成功
        $stmt = $conn->prepare("DELETE FROM post_tags WHERE post_id = ?");
        $stmt->bind_param("i", $postId);
        $result = $stmt->execute();
        if (!$result) {
            throw new Exception("删除旧标签失败: " . $conn->error);
        }
        $stmt->close();
        
        // 对标签数组进行去重
        $tags = isset($tags) ? array_map('intval', $tags) : [];
        $tags = array_unique($tags);
        
        // 处理自定义标签
        $processedCustomTags = []; // 跟踪已处理的自定义标签
        
        foreach ($customTags as $tagName) {
            if (!empty($tagName) && !in_array(strtolower($tagName), $processedCustomTags)) {
                // 记录已处理的标签（不区分大小写）
                $processedCustomTags[] = strtolower($tagName);
                
                // 检查标签是否已存在
                $stmt = $conn->prepare("SELECT id FROM tags WHERE LOWER(name) = LOWER(?)");
                $stmt->bind_param("s", $tagName);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $tagId = $result->fetch_assoc()['id'];
                    // 只有当这个ID不在标签数组中时才添加
                    if (!in_array($tagId, $tags)) {
                        $tags[] = $tagId;
                    }
                } else {
                    // 插入新标签
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
                    $stmt->bind_param("s", $tagName);
                    $stmt->execute();
                    $tagId = $conn->insert_id;
                    $tags[] = $tagId;
                }
                $stmt->close();
            }
        }
        
        // 再次确保所有标签都是唯一的
        $tags = array_unique($tags);
        
        // 插入文章标签关系
        if (!empty($tags)) {
            // 使用INSERT IGNORE来忽略重复键错误
            $stmt = $conn->prepare("INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)");
            
            foreach ($tags as $tagId) {
                $stmt->bind_param("ii", $postId, $tagId);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        // 提交事务
        $conn->commit();
        
        // 重定向到文章页面
        header("Location: post.php?id=$postId");
        exit;
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        
        // 显示错误信息
        echo "<script>alert('更新文章失败: " . $e->getMessage() . "'); window.history.back();</script>";
        exit;
    }
}

// 获取文章数据
$stmt = $conn->prepare("SELECT topic_title, topic_date, image_filename, topic_para, location FROM blog_table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $post = $result->fetch_assoc();
} else {
    die("文章不存在");
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑博客文章</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 70%;
            border-radius: 8px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }
        
        .tag-list-container {
            position: relative;
            overflow: hidden;
            transition: height 0.3s ease;
        }
        
        .tag-list {
            margin-bottom: 15px;
        }
        
        /* 预览标签样式 */
        .preview-tags {
            margin-bottom: 10px;
        }
        
        /* 所有标签样式 */
        .all-tags {
            max-height: 200px; /* 固定高度，超出时显示滚动条 */
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        #showAllTagsBtn {
            background-color: #f1f1f1;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        
        #showAllTagsBtn:hover {
            background-color: #ddd;
        }
        
        .tag-item {
            margin: 5px;
            display: inline-block;
        }

        .selected-tags-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .tag-badge {
            background-color: #007BFF;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            font-size: 14px;
        }
        
        .tag-badge .remove-tag {
            margin-left: 5px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span id="topBarTitle">Blog | Edit Post</span>
    </div>

    <div class="writing-section">
        <?php if (isset($error)): ?>
            <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="edit_post.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="post_id" value="<?php echo $id; ?>">
            <input id="blogTitle" name="title" type="text" placeholder="Blog Title..." autocomplete="off" 
                   value="<?php echo htmlspecialchars($post['topic_title']); ?>">
            
            <br>
            
            <span id="dateLabel">Date: </span>
            <input id="blogDate" name="date" 
                   value="<?php echo htmlspecialchars($post['topic_date']); ?>">
            
            <br><br>
            
            <?php if (!empty($post['image_filename']) && $post['image_filename'] !== "NONE"): ?>
                <div style="margin-bottom: 15px;">
                    <p>当前图片：</p>
                    <img src="images/<?php echo htmlspecialchars($post['image_filename']); ?>" 
                         alt="Current Image" style="max-width:300px;">
                </div>
            <?php endif; ?>
            
            <input type="file" name="image">
            <p><small>选择新图片以替换当前图片，或留空保持当前图片不变</small></p>

            <br>
          
            <!-- 添加位置选项 -->
            <div class="location-option">
              <label for="enableLocation">
                <input type="checkbox" id="enableLocation" name="enable_location" <?php echo !empty($post['location']) ? 'checked' : ''; ?>> 
                显示我的当前位置
              </label>
              <div id="locationStatus" style="display:<?php echo !empty($post['location']) ? 'block' : 'none'; ?>; margin-top: 5px; font-size: 14px; color: #666;">
                <?php if (!empty($post['location'])): ?>
                  当前位置: <?php echo htmlspecialchars($post['location']); ?>
                <?php endif; ?>
              </div>
              <input type="hidden" id="locationData" name="location_data" value="<?php echo !empty($post['location']) ? htmlspecialchars(json_encode(['address' => $post['location']])) : ''; ?>">
              <button type="button" id="updateLocationBtn" style="display:<?php echo !empty($post['location']) ? 'inline-block' : 'none'; ?>; margin-top: 5px;">更新位置</button>
            </div>

            <br>

            <textarea id="blogPara" name="content" cols="75" rows="10" placeholder="博客内容..."><?php echo htmlspecialchars($post['topic_para']); ?></textarea>

            <br><br>
            
            <!-- 添加标签按钮 -->
            <center><a style="text-decoration: none;" href="#" id="saveBtn" onclick="openTagModal(); return false;">修改标签</a></center>

            <!-- 显示已选标签的区域 -->
            <div id="selectedTagsContainer" style="margin: 10px 0; text-align: center;">
                <div id="selectedTags" class="selected-tags-area"></div>
            </div>

            <br>
            
            <button id="saveBtn" type="submit">更新文章</button>
            
            <!-- 标签模态框 -->
            <div id="tagModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeTagModal()">&times;</span>
                    <h3>选择标签 <button type="button" id="showAllTagsBtn" onclick="toggleAllTags()">显示全部</button></h3>
                    <div id="tagListContainer" class="tag-list-container">
                        <div class="tag-list" id="tagList">
                            <?php
                            $result = $conn->query("SELECT * FROM tags ORDER BY name");
                            $count = 0;
                            $totalTags = $result ? $result->num_rows : 0;
                            
                            if ($totalTags > 0) {
                                echo '<div id="previewTags" class="preview-tags">';
                                // 只显示前5个标签作为预览
                                while ($row = $result->fetch_assoc()) {
                                    $count++;
                                    $isChecked = isset($existingTags[$row['id']]) ? 'checked' : '';
                                    echo '<div class="tag-item">';
                                    echo '<input type="checkbox" name="tags[]" value="'.$row['id'].'" id="tag'.$row['id'].'" '.$isChecked.' onchange="updateSelectedTags()">';
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
                                    $isChecked = isset($existingTags[$row['id']]) ? 'checked' : '';
                                    echo '<div class="tag-item">';
                                    echo '<input type="checkbox" name="tags[]" value="'.$row['id'].'" id="tag'.$row['id'].'" '.$isChecked.' onchange="updateSelectedTags()">';
                                    echo '<label for="tag'.$row['id'].'">'.$row['name'].'</label>';
                                    echo '</div>';
                                }
                                echo '</div>';
                                
                                // 如果标签太少，隐藏显示全部按钮
                                if ($totalTags <= 5) {
                                    echo '<script>document.getElementById("showAllTagsBtn").style.display = "none";</script>';
                                }
                            } else {
                                echo '<p>暂无标签</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <h3>添加自定义标签</h3>
                    <p>用逗号分隔多个标签</p>
                    <input type="text" name="custom_tags" id="customTags" style="width: 100%" placeholder="例如: 篮球, 咖啡, 阅读">
                    <br><br>
                    <center><button type="button" onclick="saveTagsAndClose()" style="padding: 5px 15px;">确定</button></center>
                </div>
            </div>
        </form>

        <br>

        <center><a style="text-decoration: none;" href="index.php" id="saveBtn">返回首页</a></center>
    </div>

    <script src="scripts/script.js"></script>
    <script>
        // 存储已选择的标签
        let selectedTagsMap = new Map(); // 使用Map存储标签ID/名称对
        let customTagsArray = []; // 存储自定义标签
        
        // 初始化已选标签
        selectedTagsMap.clear(); // 确保Map为空
        <?php foreach ($existingTags as $id => $name): ?>
            if (!selectedTagsMap.has('<?php echo $id; ?>')) {
                selectedTagsMap.set('<?php echo $id; ?>', '<?php echo addslashes($name); ?>');
            }
        <?php endforeach; ?>
        
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
            
            // 清除之前的选择
            selectedTagsMap.clear();
            
            // 添加新选择的标签，确保不重复
            const processedIds = new Set();
            
            checkboxes.forEach(checkbox => {
                const tagId = checkbox.value;
                
                // 确保该ID还没有被添加
                if (!processedIds.has(tagId)) {
                    processedIds.add(tagId);
                    const tagName = document.querySelector(`label[for="tag${tagId}"]`).textContent;
                    selectedTagsMap.set(tagId, tagName);
                    
                    // 同步复选框状态
                    syncCheckboxState(tagId, true);
                }
            });
            
            // 更新显示
            renderSelectedTags();
        }
        
        // 保存标签并关闭模态框
        function saveTagsAndClose() {
            // 处理自定义标签
            const customTagsInput = document.getElementById('customTags').value.trim();
            
            if (customTagsInput) {
                // 分割并清理自定义标签
                const tagArray = customTagsInput.split(',')
                    .map(tag => tag.trim())
                    .filter(tag => tag.length > 0);
                
                // 检查重复标签（包括已有标签和自定义标签）
                const existingTagNames = Array.from(selectedTagsMap.values()).map(name => name.toLowerCase());
                const uniqueCustomTags = [];
                
                // 只添加不重复的标签
                for (const tag of tagArray) {
                    const lowerTag = tag.toLowerCase();
                    
                    // 检查是否与已有标签重复
                    if (!existingTagNames.includes(lowerTag)) {
                        // 检查是否与已添加的自定义标签重复
                        if (!uniqueCustomTags.some(t => t.toLowerCase() === lowerTag)) {
                            uniqueCustomTags.push(tag);
                        }
                    }
                }
                
                // 替换自定义标签数组
                customTagsArray = uniqueCustomTags;
            }
            
            // 渲染所有选中的标签
            renderSelectedTags();
            
            // 关闭模态框
            closeTagModal();
        }
        
        // 渲染已选标签到页面
        function renderSelectedTags() {
            const container = document.getElementById('selectedTags');
            container.innerHTML = ''; // 清空容器
            
            // 使用Set跟踪已添加的标签ID和自定义标签，避免重复
            const addedTagIds = new Set();
            const addedCustomTags = new Set();
            
            // 添加已有标签
            selectedTagsMap.forEach((tagName, tagId) => {
                // 确保这个ID还没有被添加过
                if (!addedTagIds.has(tagId)) {
                    addedTagIds.add(tagId);
                    
                    const tagElement = createTagElement(tagName, 'existing', tagId);
                    container.appendChild(tagElement);
                    
                    // 确保有一个隐藏的input用于表单提交
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'tags[]';
                    hiddenInput.value = tagId;
                    container.appendChild(hiddenInput);
                }
            });
            
            // 添加自定义标签
            customTagsArray.forEach(tagName => {
                // 确保这个标签名还没有被添加过（不区分大小写）
                const lowerTagName = tagName.toLowerCase();
                if (!addedCustomTags.has(lowerTagName)) {
                    addedCustomTags.add(lowerTagName);
                    
                    const tagElement = createTagElement(tagName, 'custom');
                    container.appendChild(tagElement);
                    
                    // 确保有一个隐藏的input用于表单提交
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'custom_tags_array[]';
                    hiddenInput.value = tagName;
                    container.appendChild(hiddenInput);
                }
            });
            
            // 显示或隐藏容器
            document.getElementById('selectedTagsContainer').style.display = 
                (addedTagIds.size > 0 || addedCustomTags.size > 0) ? 'block' : 'none';
        }
        
        // 创建标签元素
        function createTagElement(tagName, type, tagId = null) {
            const tagElement = document.createElement('span');
            tagElement.className = 'tag-badge';
            tagElement.textContent = tagName;
            
            // 添加删除按钮
            const removeButton = document.createElement('span');
            removeButton.className = 'remove-tag';
            removeButton.textContent = ' ×';
            removeButton.onclick = function() {
                if (type === 'existing' && tagId) {
                    // 取消选中复选框
                    const checkbox = document.getElementById(`tag${tagId}`);
                    if (checkbox) checkbox.checked = false;
                    selectedTagsMap.delete(tagId);
                } else if (type === 'custom') {
                    // 从自定义标签数组中删除
                    customTagsArray = customTagsArray.filter(t => t !== tagName);
                }
                renderSelectedTags();
            };
            
            tagElement.appendChild(removeButton);
            return tagElement;
        }
        
        // 切换显示所有标签
        function toggleAllTags() {
            const previewTags = document.getElementById('previewTags');
            const allTags = document.getElementById('allTags');
            const button = document.getElementById('showAllTagsBtn');
            
            if (allTags.style.display === 'none') {
                // 显示所有标签
                previewTags.style.display = 'none';
                allTags.style.display = 'block';
                button.textContent = '收起';
            } else {
                // 只显示预览标签
                previewTags.style.display = 'block';
                allTags.style.display = 'none';
                button.textContent = '显示全部';
            }
        }
        
        // 确保标签选择状态在预览和全部视图之间保持同步
        function syncCheckboxState(tagId, checked) {
            const previewCheckbox = document.querySelector(`#previewTags input[id="tag${tagId}"]`);
            const allTagsCheckbox = document.querySelector(`#allTags input[id="tag${tagId}"]`);
            
            if (previewCheckbox) previewCheckbox.checked = checked;
            if (allTagsCheckbox) allTagsCheckbox.checked = checked;
        }
        
        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 渲染已有标签
            renderSelectedTags();
        });
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            var modal = document.getElementById('tagModal');
            if (event.target == modal) {
                closeTagModal();
            }
        }
        
        // 现有JavaScript代码...
  
        // 添加位置功能
        document.addEventListener('DOMContentLoaded', function() {
          const enableLocationCheckbox = document.getElementById('enableLocation');
          const locationStatusDiv = document.getElementById('locationStatus');
          const locationDataInput = document.getElementById('locationData');
          const updateLocationBtn = document.getElementById('updateLocationBtn');
          
          function updateLocation() {
            locationStatusDiv.style.display = 'block';
            locationStatusDiv.textContent = '正在获取位置信息...';
            
            // 检查浏览器是否支持地理位置API
            if (navigator.geolocation) {
              navigator.geolocation.getCurrentPosition(
                // 成功获取位置
                function(position) {
                  const latitude = position.coords.latitude;
                  const longitude = position.coords.longitude;
                  
                  // 调用反向地理编码API获取地址信息
                  fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`)
                    .then(response => response.json())
                    .then(data => {
                      // 存储位置信息
                      const locationInfo = {
                        latitude: latitude,
                        longitude: longitude,
                        address: data.display_name || '未知地点'
                      };
                      
                      locationDataInput.value = JSON.stringify(locationInfo);
                      locationStatusDiv.textContent = `位置: ${locationInfo.address}`;
                      if (updateLocationBtn) updateLocationBtn.style.display = 'inline-block';
                    })
                    .catch(error => {
                      console.error('获取地址信息失败:', error);
                      const locationInfo = {
                        latitude: latitude,
                        longitude: longitude,
                        address: '位置已获取，但无法解析详细地址'
                      };
                      locationDataInput.value = JSON.stringify(locationInfo);
                      locationStatusDiv.textContent = `位置已获取 (${latitude.toFixed(6)}, ${longitude.toFixed(6)})`;
                      if (updateLocationBtn) updateLocationBtn.style.display = 'inline-block';
                    });
                },
                // 获取位置失败
                function(error) {
                  let errorMessage = '无法获取位置信息: ';
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
                    case error.UNKNOWN_ERROR:
                      errorMessage += '发生未知错误。';
                      break;
                  }
                  locationStatusDiv.textContent = errorMessage;
                }
              );
            } else {
              locationStatusDiv.textContent = '您的浏览器不支持地理位置功能。';
            }
          }
          
          enableLocationCheckbox.addEventListener('change', function() {
            if (this.checked) {
              if (!locationDataInput.value) {
                updateLocation();
              }
            } else {
              locationStatusDiv.style.display = 'none';
              locationDataInput.value = '';
              if (updateLocationBtn) updateLocationBtn.style.display = 'none';
            }
          });
          
          if (updateLocationBtn) {
            updateLocationBtn.addEventListener('click', function(e) {
              e.preventDefault();
              updateLocation();
            });
          }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>