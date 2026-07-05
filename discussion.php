<?php 
session_start();
include 'getv.php';

function getCommunityData() {
    $baseUrl = "http://nlm-api-cn.turtlesim.com/";
    
    // 准备请求数据
    $requestData = [
        'Identifier' => 'Discussions',
        'Language' => "Chinese"
    ];
    
    // 准备请求头
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Language: zh-CN',
    ];
    
    // 添加认证头（修复了多余的大括号）
    $headers[] = 'x-API-Token: ' . $_SESSION['token'];
    $headers[] = 'x-API-AuthCode: ' . $_SESSION['authCode'];
    
    // 发送POST请求
    $url = $baseUrl . 'Contents/GetLibrary';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data;
}

// 处理日期格式
function formatDate($id) {
    return (strlen($id) >= 8) ? date('m/d', hexdec(substr($id, 0, 8)) * 1000 / 1000) : '未知日期';
}

// 获取数据
$communityData = getCommunityData();

// 模块配置
$modules = [
    ['title' => '海水公告', 'count' => 3, 'featured' => true, 'type' => '公告', 'category' => 'Discussion', 'sort' => 0],
    ['title' => '海水精选', 'count' => 3, 'featured' => true, 'type' => '精选', 'category' => 'Discussion', 'sort' => 0],
    ['title' => '热门交流', 'count' => 4, 'featured' => false, 'type' => '', 'category' => 'Discussion', 'sort' => 1],
    ['title' => '最新交流', 'count' => 4, 'featured' => false, 'type' => '', 'category' => 'Discussion', 'sort' => 0],
    ['title' => '社区知识库', 'count' => 4, 'featured' => false, 'type' => '知识库', 'category' => 'Discussion', 'sort' => 0],
    ['title' => '海水模版', 'count' => 4, 'featured' => false, 'type' => '海水', 'category' => 'Model', 'sort' => 0]
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Turtle Universe Web - Discussion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link rel="stylesheet" href="styles/library.css">
</head>
<body>
    <header class="header">
        <h2>海水</h2>
    </header>

    <main class="content">
        <?php if ($communityData && isset($communityData['Data']['Blocks'])): ?>
            <?php $blocks = $communityData['Data']['Blocks']; ?>
            <div class="community-grid">
                <?php foreach ($modules as $index => $module): ?>
                    <?php if (isset($blocks[$index])): ?>
                        <?php 
                        $block = $blocks[$index];
                        $summaries = $block['Summaries'] ?? [];
                        ?>
                        <div class="block <?= $module['featured'] ? 'featured-block' : '' ?>">
                            <div class="block-header"><?= $module['title'] ?></div>
                            <div class="block-content">
                                <?php foreach (array_slice($summaries, 0, $module['count']) as $exp): ?>
                                    <?php 
                                    $subject = $exp['Subject'] ?? ($exp['LocalizedSubject']['Chinese'] ?? '未知主题');
                                    $author = $exp['User']['Nickname'] ?? '未知作者';
                                    $date = formatDate($exp['ID'] ?? '');
                                    $tags = $exp['Tags'] ?? [];
                                    $imgSrc = 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/' . 
                                             substr($exp['ID'] ?? '0000000000000000', 0, 4) . '/' .
                                             substr($exp['ID'] ?? '0000000000000000', 4, 2) . '/' .
                                             substr($exp['ID'] ?? '0000000000000000', 6, 2) . '/' .
                                             substr($exp['ID'] ?? '0000000000000000', 8, 16) . '/' .
                                             ($exp['Image'] ?? 'default') . '.jpg!full';
                                    ?>
                                    <div class="item" onclick="window.location='med.php?category=<?= $module['category'] ?>&id=<?= $exp['ID'] ?>'">
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($subject) ?>" class="item-img" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0zMCAzNEMzMy4zMTM3IDM0IDM2IDMxLjMxMzcgMzYgMjhDMzYgMjQuNjg2MyAzMy4zMTM3IDIyIDMwIDIyQzI2LjY4NjMgMjIgMjQgMjQuNjg2MyAyNCAyOEMyNCAzMS4zMTM3IDI2LjY4NjMgMzQgMzAgMzRaIiBmaWxsPSIjQ0VDRUNFIi8+CjxwYXRoIGQ9Ik0zNiAzOEgyNEMyMi44OTU0IDM4IDIyIDM3LjEwNDYgMjIgMzZWMjRDMjIgMjIuODk1NCAyMi44OTU0IDIyIDI0IDIySDM2QzM3LjEwNDYgMjIgMzggMjIuODk1NCAzOCAyNFYzNkMzOCAzNy4xMDQ2IDM3LjEwNDYgMzggMzYgMzhaTTI0IDI0VjM2SDM2VjI0SDI0WiIgZmlsbD0iI0NFQ0VDRSIvPgo8L3N2Zz4K'">
                                        <div class="item-details">
                                            <div class="item-title"><?= htmlspecialchars($subject) ?></div>
                                            <div class="item-meta"><?= htmlspecialchars($author) ?> - <?= $date ?></div>
                                            <?php if (!empty($tags)): ?>
                                                <div class="item-tags">
                                                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                                        <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="more-button-container">
                                <button class="more-button" onclick="window.location='query.php?category=<?= $module['category'] ?>&tags=<?= $module['type'] ?>&sort=<?= $module['sort'] ?>'">查看更多</button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="error">
                <p>无法加载社区数据，请检查网络连接或稍后重试</p>
                <button class="retry-btn" onclick="window.location.reload()">重新加载</button>
            </div>
        <?php endif; ?>
    </main>

    <div class="footer">
        <div onclick="location.href='/'"><i class="fas fa-home"></i><span>首页</span></div>
        <div onclick="location.href='me.php'"><i class="fas fa-user"></i><span>我的</span></div>
        <div class="active"><i class="fas fa-water"></i><span>海水</span></div>
        <div><i class="fas fa-cube" onclick="location.href='model.php'"></i><span>模型库</span></div>
        <div onclick="location.href='nof.php'"><i class="fas fa-bell"></i><span>通知</span></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const footerItems = document.querySelectorAll('.footer div');
            footerItems.forEach(item => {
                item.addEventListener('click', function() {
                    footerItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
