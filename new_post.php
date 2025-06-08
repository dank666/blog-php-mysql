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

<html lang="en">
  
  <head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Blog Using PHP And MySQL</title>

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
  
      <span id="topBarTitle">智域博客</span>

    </div>

    <div class="writing-section">

      <form action="blog_post_process.php" method="POST" enctype="multipart/form-data">

        <input id="blogTitle" name="blogtitle" type="text" placeholder="Blog Title..." autocomplete="off">
        
        <br>
        
        <span id="dateLabel">日期: </span>
        
        <input id="blogDate" name="blogdate" readonly></input>
        
        <br><br>
        
        <input type="file" name="uploadimage">

        <br><br>
      
        <!-- 添加位置选项 -->
        <div class="location-option">
          <label for="enableLocation">
            <input type="checkbox" id="enableLocation" name="enable_location"> 显示我的当前位置
          </label>
          <div id="locationStatus" style="display:none; margin-top: 5px; font-size: 14px; color: #666;"></div>
          <input type="hidden" id="locationData" name="location_data">
        </div>

        <br>

        <textarea id="blogPara" name="blogpara" cols="75" rows="10" type="text" placeholder="Blog Paragraph..." autocomplete="off"></textarea>

        <br><br>
        
        <!-- 添加标签按钮 -->
        <center><a style="text-decoration: none;" href="#" id="saveBtn" onclick="openTagModal(); return false;">添加标签</a></center>

        <!-- 显示已选标签的区域 -->
        <div id="selectedTagsContainer" style="margin: 10px 0; text-align: center;">
          <div id="selectedTags" class="selected-tags-area"></div>
        </div>

        <br>
        
        <button id="saveBtn" type="submit">发布文章</button>

        <!-- 标签模态框 -->
        <div id="tagModal" class="modal">
          <div class="modal-content">
            <span class="close" onclick="closeTagModal()">&times;</span>
            <h3>选择标签 <button type="button" id="showAllTagsBtn" onclick="toggleAllTags()">显示全部</button></h3>
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

      <center><a style="text-decoration: none;" href="index.php" id="saveBtn">返回主页</a></center>
      
    </div>

    <script src="scripts/script.js"></script>
    <script>
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
      let selectedTagsMap = new Map(); // 使用Map存储标签ID/名称对
      let customTagsArray = []; // 存储自定义标签
      
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
        
        // 添加新选择的标签
        checkboxes.forEach(checkbox => {
          const tagId = checkbox.value;
          const tagName = document.querySelector(`label[for="tag${tagId}"]`).textContent;
          selectedTagsMap.set(tagId, tagName);
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
          customTagsArray = customTagsInput.split(',')
            .map(tag => tag.trim())
            .filter(tag => tag.length > 0);
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
        
        // 添加已有标签
        selectedTagsMap.forEach((tagName, tagId) => {
          const tagElement = createTagElement(tagName, 'existing', tagId);
          container.appendChild(tagElement);
          
          // 确保有一个隐藏的input用于表单提交
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
          
          // 确保有一个隐藏的input用于表单提交
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
      
      // 页面加载时初始化标签显示区域
      document.addEventListener('DOMContentLoaded', function() {
        // 已有的用户输入恢复代码...
        
        // 初始化标签显示区域
        document.getElementById('selectedTagsContainer').style.display = 'none';
      });
      
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
      
      // 修改updateSelectedTags函数，添加同步功能
      function updateSelectedTags() {
        const checkboxes = document.querySelectorAll('input[name="tags[]"]:checked');
        
        // 清除之前的选择
        selectedTagsMap.clear();
        
        // 添加新选择的标签
        checkboxes.forEach(checkbox => {
          const tagId = checkbox.value;
          const tagName = document.querySelector(`label[for="tag${tagId}"]`).textContent;
          selectedTagsMap.set(tagId, tagName);
          
          // 同步复选框状态
          syncCheckboxState(tagId, true);
        });
        
        // 更新显示
        renderSelectedTags();
      }
      
      // 现有JavaScript代码...
      
      // 添加位置功能
      document.addEventListener('DOMContentLoaded', function() {
        const enableLocationCheckbox = document.getElementById('enableLocation');
        const locationStatusDiv = document.getElementById('locationStatus');
        const locationDataInput = document.getElementById('locationData');
        
        enableLocationCheckbox.addEventListener('change', function() {
          if (this.checked) {
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
                  enableLocationCheckbox.checked = false;
                }
              );
            } else {
              locationStatusDiv.textContent = '您的浏览器不支持地理位置功能。';
              enableLocationCheckbox.checked = false;
            }
          } else {
            locationStatusDiv.style.display = 'none';
            locationDataInput.value = '';
          }
        });
      });
    </script>
  </body>
  
</html>