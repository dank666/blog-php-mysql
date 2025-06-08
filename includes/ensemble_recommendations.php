<?php
/**
 * 简化的热门度推荐系统
 * 基于文章的浏览量、点赞数和评论数进行推荐
 */

/**
 * 获取热门文章推荐
 * @param mysqli $conn 数据库连接
 * @param int|null $userId 用户ID，未登录用户为null
 * @param int $limit 推荐数量
 * @return array 推荐文章列表
 */
function getEnsembleRecommendations($conn, $userId = null, $limit = 10) {
    // 存储候选文章及其热门度得分
    $candidates = [];
    $scores = [];
    $finalRecommendations = [];
    
    try {
        // 1. 获取所有文章作为候选
        $query = "SELECT id, topic_title, topic_para, user_id, topic_date 
                 FROM blog_table 
                 ORDER BY id DESC 
                 LIMIT 100"; // 限制候选池大小
        
        $result = $conn->query($query);
        if (!$result) {
            error_log("推荐系统错误: 查询候选文章失败 - " . $conn->error);
            return []; // 查询失败时返回空数组
        }
        
        while ($row = $result->fetch_assoc()) {
            $candidates[$row['id']] = $row;
            $scores[$row['id']] = 0; // 初始化得分
        }
        
        // 2. 计算浏览量得分 (30%)
        $query = "SELECT post_id, COUNT(*) as views_count 
                 FROM blog_views 
                 GROUP BY post_id 
                 ORDER BY views_count DESC";
        
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            // 找出最大浏览量用于归一化
            $result->data_seek(0);
            $firstRow = $result->fetch_assoc();
            $maxViews = max($firstRow['views_count'], 1);
            $result->data_seek(0);
            
            while ($row = $result->fetch_assoc()) {
                if (isset($scores[$row['post_id']])) {
                    // 归一化浏览量得分 (0-30分)
                    $viewScore = ($row['views_count'] / $maxViews) * 30;
                    $scores[$row['post_id']] += $viewScore;
                }
            }
        }
        
        // 3. 计算点赞得分 (40%)
        $query = "SELECT post_id, COUNT(*) as likes_count 
                 FROM likes 
                 GROUP BY post_id 
                 ORDER BY likes_count DESC";
        
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            // 找出最大点赞数用于归一化
            $result->data_seek(0);
            $firstRow = $result->fetch_assoc();
            $maxLikes = max($firstRow['likes_count'], 1);
            $result->data_seek(0);
            
            while ($row = $result->fetch_assoc()) {
                if (isset($scores[$row['post_id']])) {
                    // 归一化点赞得分 (0-40分)
                    $likeScore = ($row['likes_count'] / $maxLikes) * 40;
                    $scores[$row['post_id']] += $likeScore;
                }
            }
        }
        
        // 4. 计算评论得分 (20%)
        $query = "SELECT post_id, COUNT(*) as comments_count 
                 FROM comments 
                 GROUP BY post_id 
                 ORDER BY comments_count DESC";
        
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            // 找出最大评论数用于归一化
            $result->data_seek(0);
            $firstRow = $result->fetch_assoc();
            $maxComments = max($firstRow['comments_count'], 1);
            $result->data_seek(0);
            
            while ($row = $result->fetch_assoc()) {
                if (isset($scores[$row['post_id']])) {
                    // 归一化评论得分 (0-20分)
                    $commentScore = ($row['comments_count'] / $maxComments) * 20;
                    $scores[$row['post_id']] += $commentScore;
                }
            }
        }
        
        // 5. 考虑时间因素 (最新文章得额外加分)
        $query = "SELECT id, 
                 DATEDIFF(NOW(), STR_TO_DATE(topic_date, '%Y-%m-%d')) as days_old 
                 FROM blog_table 
                 ORDER BY days_old ASC 
                 LIMIT 20";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (isset($scores[$row['id']])) {
                    // 文章越新加分越多，最多10分
                    $days = intval($row['days_old']);
                    $timeBonus = max(0, 10 - ($days / 3));
                    $scores[$row['id']] += $timeBonus;
                }
            }
        }
        
        // 6. 添加少量随机性，确保推荐结果有变化
        foreach ($scores as $postId => $score) {
            // 随机波动范围 ±5%
            $randomFactor = mt_rand(95, 105) / 100;
            $scores[$postId] *= $randomFactor;
        }
        
        // 7. 根据总得分排序并选择前N个作为推荐结果
        arsort($scores);
        
        // 收集最终推荐
        $count = 0;
        foreach ($scores as $postId => $score) {
            if ($count >= $limit) break;
            
            if (isset($candidates[$postId])) {
                $finalRecommendations[] = $candidates[$postId];
                $count++;
            }
        }
        
    } catch (Exception $e) {
        error_log("推荐系统错误: " . $e->getMessage());
    }
    
    // 如果没有找到推荐内容，返回最新的几篇文章
    if (empty($finalRecommendations)) {
        $backupQuery = "SELECT id, topic_title, topic_para, user_id, topic_date 
                       FROM blog_table 
                       ORDER BY id DESC 
                       LIMIT ?";
        $stmt = $conn->prepare($backupQuery);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $finalRecommendations[] = $row;
        }
        
        error_log("推荐系统: 使用了备用推荐方案，返回最新的 " . count($finalRecommendations) . " 篇文章");
    }

    return $finalRecommendations;
}
?>