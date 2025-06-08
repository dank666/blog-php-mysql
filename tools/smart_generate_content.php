<?php
// 包含配置和数据库连接 - 使用绝对路径
$config = include __DIR__ . '/../config.php';
include __DIR__ . '/../includes/database.php';

// 连接数据库
$conn = getDbConnection($config);

// ======== 智能表结构检测 ========
$tableStructures = [];
$tableNames = ['users', 'blog_table', 'comments', 'likes', 'followers', 'blog_views', 'tags', 'post_tags'];

echo "====== 开始智能检测数据库结构 ======\n";
foreach ($tableNames as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM $table");
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        $tableStructures[$table] = $columns;
        echo "✓ 找到表 $table，包含 " . count($columns) . " 个列: " . implode(", ", array_keys($columns)) . "\n";
    } else {
        echo "! 表 $table 不存在，将在需要时创建\n";
    }
}

// 创建缺失的表
if (!isset($tableStructures['tags'])) {
    echo "正在创建tags表...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tag_name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // 重新获取结构
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM tags");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = [
            'type' => $row['Type'],
            'null' => $row['Null'],
            'key' => $row['Key'],
            'default' => $row['Default'],
            'extra' => $row['Extra']
        ];
    }
    $tableStructures['tags'] = $columns;
    echo "✓ 创建并添加了表 tags\n";
}

if (!isset($tableStructures['post_tags'])) {
    echo "正在创建post_tags表...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS post_tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            tag_id INT NOT NULL,
            UNIQUE KEY (post_id, tag_id)
        )
    ");
    
    // 重新获取结构
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM post_tags");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = [
            'type' => $row['Type'],
            'null' => $row['Null'],
            'key' => $row['Key'],
            'default' => $row['Default'],
            'extra' => $row['Extra']
        ];
    }
    $tableStructures['post_tags'] = $columns;
    echo "✓ 创建并添加了表 post_tags\n";
}

// 确定标签列名
$tagNameColumn = 'tag_name';
if (isset($tableStructures['tags'])) {
    if (isset($tableStructures['tags']['tag_name'])) {
        $tagNameColumn = 'tag_name';
    } elseif (isset($tableStructures['tags']['name'])) {
        $tagNameColumn = 'name';
    } elseif (isset($tableStructures['tags']['tag'])) {
        $tagNameColumn = 'tag';
    }
}
echo "将使用 $tagNameColumn 作为标签名称列\n";

// 确定评论内容列名
$commentContentColumn = 'content';
if (isset($tableStructures['comments'])) {
    if (isset($tableStructures['comments']['content'])) {
        $commentContentColumn = 'content';
    } elseif (isset($tableStructures['comments']['comment_text'])) {
        $commentContentColumn = 'comment_text';
    } elseif (isset($tableStructures['comments']['comment'])) {
        $commentContentColumn = 'comment';
    }
}
echo "将使用 $commentContentColumn 作为评论内容列\n";

// ======== 数据生成部分 ========
// 常见中文姓氏
$surnames = ["王", "李", "张", "刘", "陈", "杨", "黄", "赵", "吴", "周", "徐", "孙", "马", "朱", "胡", "林", "郭", "何", "高", "罗"];

// 常见中文名字
$names = ["伟", "芳", "娜", "秀英", "敏", "静", "丽", "强", "磊", "军", "洋", "勇", "艳", "杰", "娟", "涛", "明", "超", "秀兰", "霞", "平", "刚", "桂英"];

// 常见网名风格
$nicknames = ["小", "大", "老", "爱", "懒", "码农", "程序猿", "文艺", "科技", "数码", "旅行", "美食", "生活", "时尚", "学习", "健身", "读书", "摄影", "音乐", "电影"];

// 常见位置信息
$locations = [
    "北京市海淀区", "上海市浦东新区", "广州市天河区", "深圳市南山区", "杭州市西湖区",
    "成都市武侯区", "重庆市渝中区", "南京市鼓楼区", "武汉市武昌区", "西安市雁塔区",
    "苏州市姑苏区", "天津市和平区", "长沙市岳麓区", "郑州市金水区", "青岛市市南区"
];

// 兴趣和专业领域
$interests = [
    "编程", "人工智能", "数据科学", "Web开发", "移动开发", 
    "云计算", "区块链", "网络安全", "物联网", "游戏开发",
    "摄影", "旅行", "美食", "阅读", "音乐",
    "电影", "健身", "瑜伽", "跑步", "户外运动"
];

// 简易拼音转换函数
function pinyin($chinese) {
    // 这里使用简化版，实际应用中可以使用专业的拼音转换库
    $dict = [
        '王' => 'wang', '李' => 'li', '张' => 'zhang', '刘' => 'liu', '陈' => 'chen',
        '杨' => 'yang', '黄' => 'huang', '赵' => 'zhao', '吴' => 'wu', '周' => 'zhou',
        '徐' => 'xu', '孙' => 'sun', '马' => 'ma', '朱' => 'zhu', '胡' => 'hu',
        '林' => 'lin', '郭' => 'guo', '何' => 'he', '高' => 'gao', '罗' => 'luo',
        '伟' => 'wei', '芳' => 'fang', '娜' => 'na', '秀英' => 'xiuying', '敏' => 'min',
        '静' => 'jing', '丽' => 'li', '强' => 'qiang', '磊' => 'lei', '军' => 'jun',
        '洋' => 'yang', '勇' => 'yong', '艳' => 'yan', '杰' => 'jie', '娟' => 'juan',
        '涛' => 'tao', '明' => 'ming', '超' => 'chao', '秀兰' => 'xiulan', '霞' => 'xia',
        '平' => 'ping', '刚' => 'gang', '桂英' => 'guiying'
    ];
    
    return isset($dict[$chinese]) ? $dict[$chinese] : 'user';
}

// 用户生成函数 - 根据实际表结构生成
function generateUser($conn, $tableStructure, $surnames, $names, $nicknames, $locations, $interests) {
    // 生成姓名
    $surname = $surnames[array_rand($surnames)];
    $name = $names[array_rand($names)];
    $fullName = $surname . $name;
    
    // 生成用户名
    $usernameStyle = rand(1, 3);
    switch($usernameStyle) {
        case 1:
            $username = $fullName . rand(1, 999);
            break;
        case 2:
            $username = $nicknames[array_rand($nicknames)] . $surname . rand(0, 99);
            break;
        case 3:
            $username = strtolower(pinyin($surname)) . strtolower(pinyin($name)) . rand(0, 99);
            break;
    }
    
    // 生成邮箱
    $domains = ["gmail.com", "qq.com", "163.com", "126.com", "outlook.com", "hotmail.com", "foxmail.com"];
    $email = strtolower($username) . "@" . $domains[array_rand($domains)];
    
    // 根据表结构动态构建SQL
    $fields = [];
    $placeholders = [];
    $params = [];
    $types = '';
    
    // 用户名
    if (isset($tableStructure['username'])) {
        $fields[] = 'username';
        $placeholders[] = '?';
        $params[] = $username;
        $types .= 's';
    }
    
    // 显示名称
    if (isset($tableStructure['display_name'])) {
        $fields[] = 'display_name';
        $placeholders[] = '?';
        $params[] = $fullName;
        $types .= 's';
    }
    
    // 邮箱
    if (isset($tableStructure['email'])) {
        $fields[] = 'email';
        $placeholders[] = '?';
        $params[] = $email;
        $types .= 's';
    }
    
    // 密码字段 - 检查多种可能的字段名
    if (isset($tableStructure['password_hash'])) {
        $fields[] = 'password_hash';
        $placeholders[] = '?';
        $params[] = password_hash("password123", PASSWORD_DEFAULT);
        $types .= 's';
    } elseif (isset($tableStructure['password'])) {
        $fields[] = 'password';
        $placeholders[] = '?';
        $params[] = password_hash("password123", PASSWORD_DEFAULT);
        $types .= 's';
    } elseif (isset($tableStructure['user_password'])) {
        $fields[] = 'user_password';
        $placeholders[] = '?';
        $params[] = password_hash("password123", PASSWORD_DEFAULT);
        $types .= 's';
    }
    
    // 简介
    if (isset($tableStructure['bio'])) {
        $userInterests = [];
        $interestCount = rand(2, 5);
        for($i = 0; $i < $interestCount; $i++) {
            $userInterests[] = $interests[array_rand($interests)];
        }
        $userInterests = array_unique($userInterests);
        $bio = "我是" . $fullName . "，喜欢" . implode("、", $userInterests) . "。";
        
        $fields[] = 'bio';
        $placeholders[] = '?';
        $params[] = $bio;
        $types .= 's';
    }
    
    // 头像
    if (isset($tableStructure['avatar'])) {
        $fields[] = 'avatar';
        $placeholders[] = '?';
        $params[] = "default.jpg"; // 默认头像
        $types .= 's';
    }
    
    // 角色
    if (isset($tableStructure['role'])) {
        $fields[] = 'role';
        $placeholders[] = '?';
        $params[] = "user"; // 默认角色
        $types .= 's';
    }
    
    // 创建时间
    $registrationDays = rand(1, 365);
    $registrationDate = date("Y-m-d H:i:s", strtotime("-$registrationDays days"));
    
    if (isset($tableStructure['created_at'])) {
        $fields[] = 'created_at';
        $placeholders[] = '?';
        $params[] = $registrationDate;
        $types .= 's';
    } elseif (isset($tableStructure['date_created'])) {
        $fields[] = 'date_created';
        $placeholders[] = '?';
        $params[] = $registrationDate;
        $types .= 's';
    } elseif (isset($tableStructure['reg_date'])) {
        $fields[] = 'reg_date';
        $placeholders[] = '?';
        $params[] = $registrationDate;
        $types .= 's';
    }
    
    // 最后登录时间
    if (isset($tableStructure['last_login'])) {
        $fields[] = 'last_login';
        $placeholders[] = '?';
        $params[] = date("Y-m-d H:i:s", strtotime("-" . rand(0, 30) . " days"));
        $types .= 's';
    }
    
    // 是否激活
    if (isset($tableStructure['is_active'])) {
        $fields[] = 'is_active';
        $placeholders[] = '?';
        $params[] = 1; // 默认激活
        $types .= 'i';
    }
    
    // 如果没有找到任何字段，返回错误
    if (empty($fields)) {
        echo "错误: 无法找到用户表中的任何有效字段\n";
        return 0;
    }
    
    // 构建并执行SQL
    $sql = "INSERT INTO users (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // 动态绑定参数
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $conn->insert_id;
    }
    
    echo "错误: 无法插入用户数据\n";
    return 0;
}

// 文章类别和标签
$blogCategories = [
    "科技" => ["人工智能", "大数据", "云计算", "编程语言", "区块链", "5G技术", "智能家居"],
    "生活" => ["旅行攻略", "美食推荐", "健康生活", "家居装修", "育儿经验", "时尚穿搭"],
    "职场" => ["职业规划", "面试技巧", "职场人际关系", "高效工作", "职场心态", "创业经验"],
    "教育" => ["学习方法", "考试技巧", "教育资源", "在线学习", "教育理念", "学科知识"],
    "文化" => ["历史探索", "文学赏析", "艺术鉴赏", "电影评论", "音乐分享", "传统文化"]
];

$blogTags = [
    "科技" => ["AI", "大数据", "云计算", "Python", "Java", "区块链", "5G", "物联网", "算法", "编程"],
    "生活" => ["旅行", "美食", "健康", "家居", "育儿", "时尚", "摄影", "健身", "阅读", "烹饪"],
    "职场" => ["求职", "面试", "职场关系", "效率", "职场心态", "创业", "领导力", "团队管理", "沟通", "职业规划"],
    "教育" => ["学习方法", "考试", "教育资源", "在线课程", "教育理念", "学科知识", "记忆力", "思维导图", "语言学习"],
    "文化" => ["历史", "文学", "艺术", "电影", "音乐", "传统文化", "戏剧", "博物馆", "文化遗产", "哲学"]
];

// 文章标题模板
$titleTemplates = [
    "科技" => [
        "%s的未来发展趋势",
        "%s入门指南：从零开始学习",
        "如何在2023年掌握%s技术",
        "%s技术在行业中的应用案例",
        "深入理解%s：核心概念与实践"
    ],
    "生活" => [
        "%s攻略：不可错过的体验",
        "如何打造完美的%s生活",
        "%s指南：新手必读",
        "我的%s日记：心得与分享",
        "%s技巧：让生活更美好"
    ],
    "职场" => [
        "如何在%s中脱颖而出",
        "%s技巧：提升职场竞争力",
        "我在%s中的经验教训",
        "从菜鸟到高手：%s成长之路",
        "%s指南：职场新人必读"
    ],
    "教育" => [
        "高效%s学习方法",
        "%s入门：零基础学习指南",
        "如何培养孩子的%s能力",
        "%s学习资源推荐",
        "我是如何掌握%s的"
    ],
    "文化" => [
        "%s赏析：经典与现代的碰撞",
        "探索%s的文化底蕴",
        "%s与当代社会的关系",
        "我眼中的%s：个人感悟",
        "%s发展史：从起源到现在"
    ]
];

// 文章内容生成函数
function generateBlogContent($category, $topic) {
    $paragraphs = [];
    
    // 生成引言
    $intros = [
        "在当今快速发展的{$category}领域，{$topic}已经成为一个不可忽视的重要话题。",
        "随着社会的不断进步，{$topic}越来越受到人们的关注。",
        "作为{$category}领域的爱好者，我一直对{$topic}充满好奇。",
        "近年来，{$topic}在{$category}领域掀起了一股热潮。",
        "谈到{$category}，就不得不提{$topic}这个核心概念。"
    ];
    $paragraphs[] = $intros[array_rand($intros)];
    
    // 生成主体 (简化版，3-4段)
    for($i = 0; $i < rand(3, 4); $i++) {
        $paragraphs[] = "关于{$topic}的第" . ($i+1) . "个要点：" . 
                      "在{$category}领域中，{$topic}扮演着至关重要的角色。" . 
                      "通过深入研究和实践，我们可以发现{$topic}有很多值得探索的方面。" . 
                      "例如，[此处是关于{$topic}的详细内容...]，这些都是我们需要认真思考的问题。";
    }
    
    // 生成结论
    $conclusions = [
        "总的来说，{$topic}是{$category}领域中一个既有深度又有广度的话题。通过深入学习和实践，我们可以更好地理解和应用它。",
        "回顾{$topic}的发展历程和核心价值，我们可以看到它在{$category}领域的重要性。希望这篇文章能帮助您更好地了解{$topic}。",
        "通过本文的介绍，希望能为您提供关于{$topic}的基本认识。{$topic}作为{$category}领域的重要组成部分，值得我们持续关注和探索。",
        "{$topic}的学习是一个持续的过程，需要不断实践和反思。希望本文的分享能为您的学习之旅提供一些帮助和启发。"
    ];
    $paragraphs[] = $conclusions[array_rand($conclusions)];
    
    return implode("\n\n", $paragraphs);
}

// 文章生成函数 - 根据实际表结构生成
function generateBlogPost($conn, $tableStructure, $userIds, $blogCategories, $blogTags, $titleTemplates, $locations, $tagNameColumn) {
    // 选择随机用户
    $userId = $userIds[array_rand($userIds)];
    
    // 选择随机类别
    $category = array_rand($blogCategories);
    $topics = $blogCategories[$category];
    $topic = $topics[array_rand($topics)];
    
    // 生成标题
    $titleTemplate = $titleTemplates[$category][array_rand($titleTemplates[$category])];
    $title = sprintf($titleTemplate, $topic);
    
    // 生成内容
    $content = generateBlogContent($category, $topic);
    
    // 根据表结构动态构建SQL
    $fields = [];
    $placeholders = [];
    $params = [];
    $types = '';
    
    // 用户ID
    if (isset($tableStructure['user_id'])) {
        $fields[] = 'user_id';
        $placeholders[] = '?';
        $params[] = $userId;
        $types .= 'i';
    }
    
    // 标题
    if (isset($tableStructure['topic_title'])) {
        $fields[] = 'topic_title';
        $placeholders[] = '?';
        $params[] = $title;
        $types .= 's';
    }
    
    // 内容
    if (isset($tableStructure['topic_para'])) {
        $fields[] = 'topic_para';
        $placeholders[] = '?';
        $params[] = $content;
        $types .= 's';
    }
    
    // 日期
    $days = rand(1, 100);
    $date = date("Y-m-d H:i:s", strtotime("-$days days"));
    
    if (isset($tableStructure['topic_date'])) {
        $fields[] = 'topic_date';
        $placeholders[] = '?';
        $params[] = $date;
        $types .= 's';
    }
    
    // 图片文件名
    if (isset($tableStructure['image_filename'])) {
        $fields[] = 'image_filename';
        $placeholders[] = '?';
        $params[] = "NONE"; // 默认无图片
        $types .= 's';
    }
    
    // 位置
    if (isset($tableStructure['location'])) {
        $hasLocation = (rand(0, 10) > 6); // 40%概率有位置
        $location = $hasLocation ? $locations[array_rand($locations)] : "";
        $fields[] = 'location';
        $placeholders[] = '?';
        $params[] = $location;
        $types .= 's';
    }
    
    // 如果没有找到任何字段，返回错误
    if (empty($fields)) {
        echo "错误: 无法找到博客表中的任何有效字段\n";
        return 0;
    }
    
    // 构建并执行SQL
    $sql = "INSERT INTO blog_table (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // 动态绑定参数
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $postId = $conn->insert_id;
        
        // 添加标签
        // 选择标签 (3-5个)
        $availableTags = $blogTags[$category];
        $tagCount = min(rand(3, 5), count($availableTags));
        $selectedTagIndices = array_rand($availableTags, $tagCount);
        if(!is_array($selectedTagIndices)) {
            $selectedTagIndices = [$selectedTagIndices];
        }
        $selectedTags = [];
        foreach($selectedTagIndices as $index) {
            $selectedTags[] = $availableTags[$index];
        }
        // 添加主题作为标签
        if(!in_array($topic, $selectedTags)) {
            $selectedTags[] = $topic;
        }
        
        foreach($selectedTags as $tag) {
            // 检查标签是否存在
            $sql = "SELECT id FROM tags WHERE $tagNameColumn = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $tag);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0) {
                $tagId = $result->fetch_assoc()['id'];
            } else {
                // 创建新标签
                $sql = "INSERT INTO tags ($tagNameColumn) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $tag);
                $stmt->execute();
                $tagId = $conn->insert_id;
            }
            
            // 关联文章和标签
            if (isset($tableStructures['post_tags'])) {
                $sql = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $postId, $tagId);
                $stmt->execute();
            }
        }
        
        // 生成随机浏览量 (5-20个)
        $viewsCount = rand(5, 20);
        if (isset($tableStructures['blog_views'])) {
            for($i = 0; $i < $viewsCount; $i++) {
                $viewStruct = $tableStructures['blog_views'];
                $viewFields = [];
                $viewPlaceholders = [];
                $viewParams = [];
                $viewTypes = '';
                
                // 文章ID
                if (isset($viewStruct['post_id'])) {
                    $viewFields[] = 'post_id';
                    $viewPlaceholders[] = '?';
                    $viewParams[] = $postId;
                    $viewTypes .= 'i';
                }
                
                // 浏览者IP
                if (isset($viewStruct['viewer_ip'])) {
                    $ip = rand(1, 255) . "." . rand(0, 255) . "." . rand(0, 255) . "." . rand(0, 255);
                    $viewFields[] = 'viewer_ip';
                    $viewPlaceholders[] = '?';
                    $viewParams[] = $ip;
                    $viewTypes .= 's';
                }
                
                // 浏览日期
                $viewDate = date("Y-m-d H:i:s", strtotime("-" . rand(1, $days) . " days"));
                
                if (isset($viewStruct['view_date'])) {
                    $viewFields[] = 'view_date';
                    $viewPlaceholders[] = '?';
                    $viewParams[] = $viewDate;
                    $viewTypes .= 's';
                }
                
                // 浏览日期(仅日期)
                if (isset($viewStruct['view_date_only'])) {
                    $viewDateOnly = date("Y-m-d", strtotime($viewDate));
                    $viewFields[] = 'view_date_only';
                    $viewPlaceholders[] = '?';
                    $viewParams[] = $viewDateOnly;
                    $viewTypes .= 's';
                }
                
                // 如果找到有效字段，执行插入
                if (!empty($viewFields)) {
                    $viewSql = "INSERT INTO blog_views (" . implode(", ", $viewFields) . ") VALUES (" . implode(", ", $viewPlaceholders) . ")";
                    $viewStmt = $conn->prepare($viewSql);
                    $viewStmt->bind_param($viewTypes, ...$viewParams);
                    $viewStmt->execute();
                }
            }
        }
        
        return $postId;
    }
    
    echo "错误: 无法插入博客数据\n";
    return 0;
}

// 评论生成函数 - 根据实际表结构生成
function generateComments($conn, $tableStructure, $postId, $userIds, $contentColumn) {
    $commentTemplates = [
        "感谢分享，文章很有启发性！",
        "写得非常好，学到了很多东西。",
        "我也有类似的经历，非常认同你的观点。",
        "这篇文章解答了我很多疑惑，谢谢！",
        "有一点我不太理解，能不能详细解释一下？",
        "文章内容很充实，但是我觉得还可以补充一点...",
        "这个观点很有创新性，从来没有这么想过。",
        "写得太好了，已经收藏了！",
        "图文并茂，讲解清晰，赞一个！",
        "期待更多类似主题的文章！"
    ];
    
    $commentCount = rand(2, 5); // 每篇文章2-5条评论
    $insertedCount = 0;
    
    for($i = 0; $i < $commentCount; $i++) {
        $commenterId = $userIds[array_rand($userIds)];
        $comment = $commentTemplates[array_rand($commentTemplates)];
        $commentDate = date("Y-m-d H:i:s", strtotime("-" . rand(1, 30) . " days"));
        
        // 根据表结构动态构建SQL
        $fields = [];
        $placeholders = [];
        $params = [];
        $types = '';
        
        // 文章ID
        if (isset($tableStructure['post_id'])) {
            $fields[] = 'post_id';
            $placeholders[] = '?';
            $params[] = $postId;
            $types .= 'i';
        }
        
        // 用户ID
        if (isset($tableStructure['user_id'])) {
            $fields[] = 'user_id';
            $placeholders[] = '?';
            $params[] = $commenterId;
            $types .= 'i';
        }
        
        // 评论内容
        if (isset($tableStructure[$contentColumn])) {
            $fields[] = $contentColumn;
            $placeholders[] = '?';
            $params[] = $comment;
            $types .= 's';
        }
        
        // 创建时间
        if (isset($tableStructure['created_at'])) {
            $fields[] = 'created_at';
            $placeholders[] = '?';
            $params[] = $commentDate;
            $types .= 's';
        }
        
        // 如果没有找到任何字段，跳过
        if (empty($fields)) {
            continue;
        }
        
        // 构建并执行SQL
        $sql = "INSERT INTO comments (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // 动态绑定参数
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $insertedCount++;
        }
    }
    
    return $insertedCount;
}

// ======== 执行数据生成 ========
echo "此操作将生成模拟数据，如果您已有重要数据，请先备份。\n";
echo "确认继续? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
if(strtolower($line) != 'y') {
    echo "操作已取消\n";
    exit;
}

// 生成用户
$userCount = 20; // 生成20个用户
$userIds = [];

echo "\n开始生成 $userCount 个用户...\n";
for($i = 0; $i < $userCount; $i++) {
    $userId = generateUser($conn, $tableStructures['users'], $surnames, $names, $nicknames, $locations, $interests);
    if ($userId > 0) {
        $userIds[] = $userId;
        echo "已生成用户 #$userId\n";
    }
}

// 生成文章
$postCount = 50; // 生成50篇文章
$postIds = [];

echo "\n开始生成 $postCount 篇文章...\n";
for($i = 0; $i < $postCount; $i++) {
    $postId = generateBlogPost($conn, $tableStructures['blog_table'], $userIds, $blogCategories, $blogTags, $titleTemplates, $locations, $tagNameColumn);
    if ($postId > 0) {
        $postIds[] = $postId;
        echo "已生成文章 #$postId\n";
    }
}

// 生成评论
echo "\n开始为文章生成评论...\n";
foreach($postIds as $postId) {
    $commentCount = generateComments($conn, $tableStructures['comments'], $postId, $userIds, $commentContentColumn);
    echo "已为文章 #$postId 生成 $commentCount 条评论\n";
}

// 生成点赞
if (isset($tableStructures['likes'])) {
    echo "\n开始生成点赞数据...\n";
    $likeCount = 0;
    
    foreach($postIds as $postId) {
        // 每篇文章3-15个赞
        $likesForPost = rand(3, 15);
        // 随机选择用户
        $likerIds = array_rand(array_flip($userIds), min($likesForPost, count($userIds)));
        if (!is_array($likerIds)) {
            $likerIds = [$likerIds];
        }
        
        foreach($likerIds as $likerId) {
            $likeDate = date("Y-m-d H:i:s", strtotime("-" . rand(1, 30) . " days"));
            
            $likeFields = [];
            $likePlaceholders = [];
            $likeParams = [];
            $likeTypes = '';
            
            if (isset($tableStructures['likes']['post_id'])) {
                $likeFields[] = 'post_id';
                $likePlaceholders[] = '?';
                $likeParams[] = $postId;
                $likeTypes .= 'i';
            }
            
            if (isset($tableStructures['likes']['user_id'])) {
                $likeFields[] = 'user_id';
                $likePlaceholders[] = '?';
                $likeParams[] = $likerId;
                $likeTypes .= 'i';
            }
            
            if (isset($tableStructures['likes']['created_at'])) {
                $likeFields[] = 'created_at';
                $likePlaceholders[] = '?';
                $likeParams[] = $likeDate;
                $likeTypes .= 's';
            }
            
            if (!empty($likeFields)) {
                $likeSql = "INSERT INTO likes (" . implode(", ", $likeFields) . ") VALUES (" . implode(", ", $likePlaceholders) . ")";
                $likeStmt = $conn->prepare($likeSql);
                $likeStmt->bind_param($likeTypes, ...$likeParams);
                $likeStmt->execute();
                $likeCount++;
            }
        }
    }
    
    echo "已生成 $likeCount 条点赞数据\n";
}

// 生成关注关系
if (isset($tableStructures['followers'])) {
    echo "\n开始生成用户关注关系...\n";
    $followCount = 0;
    
    // 每个用户关注3-10个其他用户
    foreach($userIds as $userId) {
        $followingCount = rand(3, 10);
        $potentialFollowings = array_diff($userIds, [$userId]); // 排除自己
        if (count($potentialFollowings) > 0) {
            $followingIds = array_rand(array_flip($potentialFollowings), min($followingCount, count($potentialFollowings)));
            if (!is_array($followingIds)) {
                $followingIds = [$followingIds];
            }
            
            foreach($followingIds as $followingId) {
                $followDate = date("Y-m-d H:i:s", strtotime("-" . rand(1, 90) . " days"));
                
                $followFields = [];
                $followPlaceholders = [];
                $followParams = [];
                $followTypes = '';
                
                if (isset($tableStructures['followers']['follower_id'])) {
                    $followFields[] = 'follower_id';
                    $followPlaceholders[] = '?';
                    $followParams[] = $userId;
                    $followTypes .= 'i';
                }
                
                if (isset($tableStructures['followers']['following_id'])) {
                    $followFields[] = 'following_id';
                    $followPlaceholders[] = '?';
                    $followParams[] = $followingId;
                    $followTypes .= 'i';
                }
                
                if (isset($tableStructures['followers']['created_at'])) {
                    $followFields[] = 'created_at';
                    $followPlaceholders[] = '?';
                    $followParams[] = $followDate;
                    $followTypes .= 's';
                }
                
                if (!empty($followFields)) {
                    $followSql = "INSERT INTO followers (" . implode(", ", $followFields) . ") VALUES (" . implode(", ", $followPlaceholders) . ")";
                    $followStmt = $conn->prepare($followSql);
                    $followStmt->bind_param($followTypes, ...$followParams);
                    $followStmt->execute();
                    $followCount++;
                }
            }
        }
    }
    
    echo "已生成 $followCount 条关注关系\n";
}

echo "\n====== 数据生成完成 ======\n";
echo "共生成：\n";
echo "- $userCount 个用户\n";
echo "- $postCount 篇文章\n";
echo "- " . count($postIds) * rand(2, 5) . " 条评论\n";
if (isset($tableStructures['likes'])) {
    echo "- " . count($postIds) * rand(3, 15) . " 条点赞\n";
}
if (isset($tableStructures['followers'])) {
    echo "- " . count($userIds) * rand(3, 10) . " 条关注关系\n";
}
echo "数据库填充成功！\n";
?>