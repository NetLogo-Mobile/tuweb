<?php 
session_start();
$dt = null;
if (isset($_SESSION['responseBody'])) {
    $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
}

// 检查登录状态
$isLoggedIn = isset($dt['Data']['User']['Nickname']) && $dt['Data']['User']['Nickname'] !== '点击登录';
if(!$isLoggedIn){ 
    header('Location: getv.php');
    exit;
}
// 获取API数据
function getCommunityData() {
    $apiUrl = 'https://nlm-api-cn.turtlesim.com/Users';
    $context = stream_context_create([
        'http' => ['timeout' => 10, 'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
    ]);
    $data = @file_get_contents($apiUrl, false, $context);
    return $data ? json_decode($data, true) : null;
}

// 处理日期格式
function formatDate($id) {
    return (strlen($id) >= 8) ? date('m/d', hexdec(substr($id, 0, 8)) * 1000 / 1000) : '未知日期';
}

// 获取数据
$communityData = getCommunityData();

// 模块配置
$modules = [
    ['title' => '探索指南', 'count' => 3, 'featured' => true, 'type' => 'explore', 'category' => 'Model'],
    ['title' => '精选实验', 'count' => 3, 'featured' => true, 'type' => 'featured', 'category' => 'Experiment'],
    ['title' => '每日模型', 'count' => 4, 'featured' => false, 'type' => 'daily', 'category' => 'Model'],
    ['title' => '热门实验', 'count' => 4, 'featured' => false, 'type' => 'hot', 'category' => 'Experiment'],
    ['title' => '最新实验', 'count' => 4, 'featured' => false, 'type' => 'new', 'category' => 'Experiment'],
    ['title' => '可视化编程', 'count' => 4, 'featured' => false, 'type' => 'visual', 'category' => 'Model'],
    ['title' => '实验知识库', 'count' => 4, 'featured' => false, 'type' => 'knowledge', 'category' => 'Experiment']
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Arial','Microsoft YaHei',sans-serif;background-color:#f5f7fa;color:#333;line-height:1.6;padding:0;margin:0}.header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,0.1);position:sticky;top:0;z-index:1000}.user-info{display:flex;align-items:center;gap:12px;cursor:pointer;transition:opacity 0.3s ease}.user-info:hover{opacity:0.8}.user-info.login-prompt{cursor:pointer}.avatar{width:45px;height:45px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px}.user-details{display:flex;flex-direction:column}.nickname{font-weight:bold;font-size:16px}.level{font-size:12px;opacity:0.8}.resources{display:flex;gap:15px}.coin,.diamond{display:flex;align-items:center;gap:5px;font-size:14px}.coin i{color:#ffd700}.diamond i{color:#b9f2ff}.content{padding:20px 15px 70px;max-width:1200px;margin:0 auto}.page-title{text-align:center;margin-bottom:25px;color:#333}.page-title h1{font-size:24px;margin-bottom:8px;color:#667eea}.page-title p{color:#666;font-size:14px}.community-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px}.block{background:white;border-radius:15px;padding:20px;box-shadow:0 4px 15px rgba(0,0,0,0.1);transition:transform 0.3s ease,box-shadow 0.3s ease;position:relative;overflow:hidden}.block:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(0,0,0,0.15)}.block-header{color:#667eea;font-size:18px;font-weight:bold;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #f0f0f0}.block-content{max-height:400px;overflow-y:auto}.item{display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f5;transition:background-color 0.3s ease;cursor:pointer}.item:hover{background-color:#f8f9ff;border-radius:8px;padding:12px 8px}.item:last-child{border-bottom:none}.item-img{width:60px;height:60px;border-radius:10px;object-fit:cover;margin-right:15px;flex-shrink:0;border:2px solid #e0e0e0}.item-details{flex:1}.item-title{font-weight:bold;font-size:14px;margin-bottom:5px;color:#333;line-height:1.3}.item-meta{font-size:12px;color:#666;margin-bottom:5px}.item-tags{display:flex;flex-wrap:wrap;gap:5px}.tag{background:#e3f2fd;color:#1976d2;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:500}.featured-block{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}.featured-block .block-header{color:white;border-bottom-color:rgba(255,255,255,0.3)}.featured-block .item-title{color:white}.featured-block .item-meta{color:rgba(255,255,255,0.8)}.footer{position:fixed;bottom:0;left:0;right:0;background:white;display:flex;justify-content:space-around;padding:10px 0;box-shadow:0 -2px 10px rgba(0,0,0,0.1);z-index:1000}.footer div{display:flex;flex-direction:column;align-items:center;gap:5px;font-size:12px;color:#666;transition:color 0.3s ease;cursor:pointer}.footer div.active{color:#667eea}.footer i{font-size:20px}.error{text-align:center;padding:40px 20px;color:#e74c3c}.retry-btn{background:#667eea;color:white;border:none;padding:10px 20px;border-radius:20px;margin-top:15px;cursor:pointer;transition:background 0.3s ease}.retry-btn:hover{background:#5a6fd8}.more-button-container{text-align:center;margin-top:15px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.3)}.featured-block .more-button-container{border-top-color:rgba(255,255,255,0.3)}.more-button{background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.5);padding:8px 20px;border-radius:20px;cursor:pointer;font-size:14px;transition:all 0.3s ease}.more-button:hover{background:rgba(255,255,255,0.3);transform:translateY(-2px)}.block:not(.featured-block) .more-button-container{border-top-color:#f0f0f0}.block:not(.featured-block) .more-button{background:#667eea;color:white;border:none}.block:not(.featured-block) .more-button:hover{background:#5a6fd8}@media (max-width:768px){.community-grid{grid-template-columns:1fr}.header{padding:12px 15px}.content{padding:15px 10px 70px}.avatar{width:40px;height:40px;font-size:18px}.nickname{font-size:14px}.resources{gap:10px}}@media (max-width:480px){.block{padding:15px}.item-img{width:50px;height:50px;margin-right:12px}.item-title{font-size:13px}}.block-content::-webkit-scrollbar{width:4px}.block-content::-webkit-scrollbar-track{background:#f1f1f1;border-radius:2px}.block-content::-webkit-scrollbar-thumb{background:#c1c1c1;border-radius:2px}.block-content::-webkit-scrollbar-thumb:hover{background:#a8a8a8}
    </style>
</head>
<body>
    <header class="header">
        <div class="user-info <?= !$isLoggedIn ? 'login-prompt' : '' ?>" <?= !$isLoggedIn ? 'onclick="window.location=\'login.php\'"' : '' ?>>
            <div class="avatar"><i class="fas fa-user"></i></div>
            <div class="user-details">
                <span class="nickname"><?= htmlspecialchars($dt['Data']['User']['Nickname'] ?? '点击登录') ?></span>
                <span class="level">Level <?= htmlspecialchars($dt['Data']['User']['Level'] ?? '1') ?></span>
            </div>
        </div>
        <div class="resources">
            <span class="coin"><i class="fas fa-coins"></i> <span><?= htmlspecialchars($dt['Data']['User']['Gold'] ?? '0') ?></span></span>
            <span class="diamond"><i class="fas fa-gem"></i> <span><?= htmlspecialchars($dt['Data']['User']['Diamond'] ?? '0') ?></span></span>
        </div>
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
                                    $imgSrc = 'http://netlogo-static-cn.turtlesim.com/experiments/images/' . 
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
                                <button class="more-button" onclick="window.location='med.php?category=<?= $module['category'] ?>&type=<?= $module['type'] ?>'">查看更多</button>
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
        <div class="active"><i class="fas fa-home"></i><span>首页</span></div>
        <div><i class="fas fa-user"></i><span>我的</span></div>
        <div><i class="fas fa-water"></i><span>海水</span></div>
        <div><i class="fas fa-cube"></i><span>模型库</span></div>
        <div><i class="fas fa-bell"></i><span>通知</span></div>
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
