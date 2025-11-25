<?php
session_start();

// 获取参数
$category = $_GET['category'] ?? 'Model';
$contentId = $_GET['id'] ?? '';
$type = $_GET['type'] ?? '';

// 获取用户数据
$dt = null;
if (isset($_SESSION['responseBody'])) {
    $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
}

// 获取作品详情的函数
function getContentSummary($contentId, $category, $token, $authCode) {
    $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    $url = $baseUrl . 'Contents/GetSummary';
    
    $requestData = [
        'ContentID' => $contentId,
        'Category' => $category,
    ];
    
    $headers = [
        "Content-Type: application/json",
        "Accept: application/json",
        "Accept-Language: zh-CN",
        "x-API-Token: " . $token,
        "x-API-AuthCode: " . $authCode,
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    $responseData = json_decode($body, true);
    return json_last_error() === JSON_ERROR_NONE ? $responseData : null;
}

// 获取作品详情
$contentData = null;
if (!empty($contentId) && !empty($category)) {
    $token = $_SESSION['token'] ?? '';
    $authCode = $_SESSION['authCode'] ?? '';
    
    if (!empty($token) && !empty($authCode)) {
        $contentData = getContentSummary($contentId, $category, $token, $authCode);
    }
}

$content = $contentData['Data'] ?? null;

// 处理日期格式
function formatDate($timestamp) {
    return date('Y年m月d日', $timestamp / 1000);
}

// 页面标题映射
$pageTitles = [
    'explore' => '探索指南',
    'featured' => '精选实验', 
    'daily' => '每日模型',
    'hot' => '热门实验',
    'new' => '最新实验',
    'visual' => '可视化编程',
    'knowledge' => '实验知识库'
];

$pageTitle = $pageTitles[$type] ?? '作品详情';
?>
<!DOCTYPE html>
<html lang="zh-CN" translate="no" style="--vh: 7.05px;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="google" content="notranslate">
    <title><?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $pageTitle) ?> - Turtle Universe Web</title>
    <link rel="shortcut icon" href="./assets/icons/logo.png" type="image/png">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:v-sans,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol";font-size:14px;line-height:1.6;margin:0;background:#f5f7fa;color:#333}.RUser{color:cornflowerblue}a[internal]{color:cornflowerblue;text-decoration:none}.basic-layout{display:flex;height:100vh}.layout-left{flex:1;position:relative}.layout-right{flex:1;overflow:hidden}.cover{height:100%;background-size:cover;background-position:center;position:relative;display:flex;flex-direction:column;padding:20px}.return{width:2.7em;cursor:pointer}.title{font-size:24px;font-weight:bold;color:white;margin:10px 0;text-align:left}.tag{display:inline-block;padding:4px 12px;margin:2px;border-radius:16px;background:rgba(255,255,255,0.2);color:white;font-size:12px}.coverBottom{margin-top:auto}.btns{display:flex;justify-content:space-around}.enter{padding:8px 24px;border-radius:25px;background:#2080f0;color:white;border:none;cursor:pointer;font-size:14px}.scroll-container{height:100%;overflow-y:auto}.context{padding:20px}.n-tabs{width:100%}.n-tabs-wrapper{display:flex;justify-content:space-evenly}.n-tabs-tab{padding:10px 0;cursor:pointer;color:#666}.n-tabs-tab--active{color:#18a058;font-weight:500}.n-tab-pane{margin-top:20px}.gray{background:#f8f9fa;border-radius:12px;padding:15px}.intro{text-align:left;line-height:1.8}.intro p{margin-bottom:15px}.intro h1,.intro h2,.intro h3{color:#2080f0;margin:20px 0 10px}.intro ul,.intro ol{margin:10px 0;padding-left:20px}.intro li{margin:5px 0}.user-info{display:flex;align-items:center;padding:15px;background:white;border-radius:10px;margin:5px 0}.user-avatar{width:50px;height:50px;border-radius:50%;margin-right:15px}.user-details{text-align:left}.user-name{color:#007bff;margin:0;font-size:16px}.user-bio{color:gray;margin:5px 0 0}.action-buttons{display:flex;gap:10px;margin:20px 0}.btn{padding:10px 20px;border-radius:20px;border:none;cursor:pointer;font-size:14px}.btn-primary{background:#2080f0;color:white}.btn-secondary{background:#f8f9fa;color:#333;border:1px solid #ddd}.error{text-align:center;padding:60px 20px;color:#e74c3c}.empty{text-align:center;padding:60px 20px;color:#666}.back-button{position:absolute;top:20px;left:20px;background:rgba(255,255,255,0.2);color:white;border:none;padding:8px 16px;border-radius:20px;cursor:pointer;z-index:10}.footer{position:fixed;bottom:0;left:0;right:0;background:white;display:flex;justify-content:space-around;padding:10px 0;box-shadow:0 -2px 10px rgba(0,0,0,0.1);z-index:1000}.footer div{display:flex;flex-direction:column;align-items:center;gap:5px;font-size:12px;color:#666}.footer div.active{color:#667eea}.footer i{font-size:20px}.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:15px 0}.stat-item{text-align:center;padding:10px;background:white;border-radius:8px}.stat-number{font-size:18px;font-weight:bold;color:#2080f0}.stat-label{font-size:12px;color:#666}.markdown-content h1{font-size:24px;margin:20px 0 10px}.markdown-content h2{font-size:20px;margin:15px 0 8px}.markdown-content h3{font-size:16px;margin:12px 0 6px}.markdown-content ul,.markdown-content ol{padding-left:20px}.markdown-content li{margin:5px 0}@media (max-width:768px){.basic-layout{flex-direction:column}.layout-left,.layout-right{flex:none}.layout-left{height:50vh}.layout-right{height:50vh}.stats-grid{grid-template-columns:repeat(2,1fr)}}
    </style>
</head>
<body>
    <div id="app">
        <div class="basic-layout">
            <!-- 左侧布局 -->
            <div class="layout-left">
                <button class="back-button" onclick="window.history.back()">
                    ← 返回
                </button>
                
                <div class="cover" style="background-image: url('<?php 
                    if ($content && isset($content['Image'])) {
                        echo 'http://netlogo-static-cn.turtlesim.com/experiments/images/' . 
                             substr($content['ID'], 0, 4) . '/' .
                             substr($content['ID'], 4, 2) . '/' .
                             substr($content['ID'], 6, 2) . '/' .
                             substr($content['ID'], 8, 16) . '/' .
                             $content['Image'] . '.jpg!full';
                    } else {
                        echo 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCAwIDgwMCA0MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSI4MDAiIGhlaWdodD0iNDAwIiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik00MDAgMjQwQzQ0NC4xODMgMjQwIDQ4MCAyMDQuMTgzIDQ4MCAxNjBDNDgwIDExNS44MTcgNDQ0LjE4MyA4MCA0MDAgODBDMzU1LjgxNyA4MCAzMjAgMTE1LjgxNyAzMjAgMTYwQzMyMCAyMDQuMTgzIDM1NS44MTcgMjQwIDQwMCAyNDBaIiBmaWxsPSIjQ0VDRUNFIi8+CjxwYXRoIGQ9Ik00ODAgMjgwSDMyMEMzMTcuOTExIDI4MCAzMTYgMjgxLjkxMSAzMTYgMjg0VjIyNEMzMTYgMjIyLjA4OSAzMTcuOTExIDIyMCAzMjAgMjIwSDQ4MEM0ODIuMDg5IDIyMCA0ODQgMjIyLjA4OSA0ODQgMjI0VjI4NEM0ODQgMjgxLjkxMSA0ODIuMDg5IDI4MCA0ODAgMjgwWk0zMjAgMjI0VjI4MEg0ODBWMjI0SDMyMFoiIGZpbGw9IiNDRUNFQ0UiLz4KPC9zdmc+';
                    }
                ?>');">
                    <div style="text-align: left;">
                        <div class="title"><?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? '未知标题') ?></div>
                        <div style="position: absolute; z-index: 100;">
                            <div class="tag" style="color: aquamarine; font-weight: bold;">
                                <?= htmlspecialchars($content['Category'] === 'Model' ? 'Model' : 'Experiment') ?>
                            </div>
                            <?php if ($content && isset($content['Tags'])): ?>
                                <?php foreach (array_slice($content['Tags'], 0, 5) as $tag): ?>
                                    <div class="tag"><?= htmlspecialchars($tag) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="coverBottom">
                        <div class="btns">
                            <button class="enter" onclick="runExperiment()">进入实验</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧布局 -->
            <div class="layout-right">
                <div class="scroll-container">
                    <div class="context">
                        <!-- 用户信息卡片 -->
                        <div class="user-info">
                            <img class="user-avatar" src="<?php
                                if ($content && isset($content['User']['Avatar'])) {
                                    echo 'http://netlogo-static-cn.turtlesim.com/users/avatars/' . 
                                         substr($content['User']['ID'], 0, 4) . '/' .
                                         substr($content['User']['ID'], 4, 2) . '/' .
                                         substr($content['User']['ID'], 6, 2) . '/' .
                                         substr($content['User']['ID'], 8, 16) . '/' .
                                         $content['User']['Avatar'] . '.jpg!full';
                                } else {
                                    echo 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjUiIGN5PSIyNSIgcj0iMjUiIGZpbGw9IiNGMEYwRjAiLz4KPHBhdGggZD0iTTI1IDMzQzI5LjQxODMgMzMgMzMgMjkuNDE4MyAzMyAyNUMzMyAyMC41ODE3IDI5LjQxODMgMTcgMjUgMTdDMjAuNTgxNyAxNyAxNyAyMC41ODE3IDE3IDI1QzE3IDI5LjQxODMgMjAuNTgxNyAzMyAyNSAzM1oiIGZpbGw9IiNDRUNFQ0UiLz4KPHBhdGggZD0iTTMzIDM3SDE3QzE2LjQ0NzcgMzcgMTYgMzYuNTUyMyAxNiAzNlYyNkMxNiAyNS40NDc3IDE2LjQ0NzcgMjUgMTcgMjVIMzNDMzMuNTUyMyAyNSAzNCAyNS40NDc3IDM0IDI2VjM2QzM0IDM2LjU1MjMgMzMuNTUyMyAzNyAzMyAzN1pNMTcgMjZWMzZIMzNWMjZIMTdaIiBmaWxsPSIjQ0VDRUNFIi8+Cjwvc3ZnPgo=';
                                }
                            ?>" alt="用户头像">
                            <div class="user-details">
                                <p class="user-name"><?= htmlspecialchars($content['User']['Nickname'] ?? '未知用户') ?></p>
                                <p class="user-bio"><?= htmlspecialchars($content['User']['Signature'] ?? '暂无简介') ?></p>
                            </div>
                        </div>

                        <!-- 统计数据 -->
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= $content['Visits'] ?? 0 ?></div>
                                <div class="stat-label">访问量</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $content['Stars'] ?? 0 ?></div>
                                <div class="stat-label">收藏</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $content['Supports'] ?? 0 ?></div>
                                <div class="stat-label">支持</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $content['Comments'] ?? 0 ?></div>
                                <div class="stat-label">评论</div>
                            </div>
                        </div>

                        <!-- 标签页 -->
                        <div class="n-tabs">
                            <div class="n-tabs-wrapper">
                                <div class="n-tabs-tab n-tabs-tab--active" onclick="switchTab('intro')">简介</div>
                                <div class="n-tabs-tab" onclick="switchTab('info')">详细信息</div>
                            </div>
                            
                            <div class="n-tab-pane">
                                <div class="gray">
                                    <!-- 简介标签页 -->
                                    <div id="intro-tab" class="tab-content">
                                        <div style="margin: 5px; background-color: white; border-radius: 10px; padding: 15px;">
                                            <h3 style="color: #2080f0; text-align: left; margin-top: 2px; margin-bottom: 10px;">作品介绍</h3>
                                            <div class="markdown-content intro">
                                                <?php if ($content && isset($content['LocalizedDescription']['Chinese'])): ?>
                                                    <?= nl2br(htmlspecialchars($content['LocalizedDescription']['Chinese'])) ?>
                                                <?php else: ?>
                                                    <p>暂无作品介绍</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 详细信息标签页 -->
                                    <div id="info-tab" class="tab-content" style="display: none;">
                                        <div style="margin: 5px; background-color: white; border-radius: 10px; padding: 15px;">
                                            <h3 style="color: #2080f0; text-align: left; margin-top: 2px; margin-bottom: 10px;">详细信息</h3>
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                                <div>
                                                    <div style="font-size: 12px; color: #666;">创建时间</div>
                                                    <div style="font-weight: bold;"><?= formatDate($content['CreationDate'] ?? 0) ?></div>
                                                </div>
                                                <div>
                                                    <div style="font-size: 12px; color: #666;">更新时间</div>
                                                    <div style="font-weight: bold;"><?= formatDate($content['UpdateDate'] ?? 0) ?></div>
                                                </div>
                                                <div>
                                                    <div style="font-size: 12px; color: #666;">版本</div>
                                                    <div style="font-weight: bold;"><?= $content['Version'] ?? '未知' ?></div>
                                                </div>
                                                <div>
                                                    <div style="font-size: 12px; color: #666;">语言</div>
                                                    <div style="font-weight: bold;"><?= htmlspecialchars($content['Language'] ?? '未知') ?></div>
                                                </div>
                                                <div>
                                                    <div style="font-size: 12px; color: #666;">改编次数</div>
                                                    <div style="font-weight: bold;"><?= $content['Remixes'] ?? 0 ?></div>
                                                </div>
                                                <div>
                                                    <div style="font-size: 12px; color: #666;">模型ID</div>
                                                    <div style="font-weight: bold; font-family: monospace;"><?= htmlspecialchars($content['ModelID'] ?? '未知') ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 操作按钮 -->
                                    <div class="action-buttons">
                                        <button class="btn btn-primary" onclick="favoriteExperiment()">
                                            ❤ 收藏 (<?= $content['Stars'] ?? 0 ?>)
                                        </button>
                                        <button class="btn btn-secondary" onclick="shareExperiment()">
                                            ↗ 分享
                                        </button>
                                        <button class="btn btn-secondary" onclick="remixExperiment()">
                                            🔄 改编 (<?= $content['Remixes'] ?? 0 ?>)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 标签页切换
        function switchTab(tabName) {
            // 隐藏所有标签内容
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // 移除所有标签的激活状态
            document.querySelectorAll('.n-tabs-tab').forEach(tab => {
                tab.classList.remove('n-tabs-tab--active');
            });
            
            // 显示选中的标签内容
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // 激活选中的标签
            event.target.classList.add('n-tabs-tab--active');
        }

        // 导航功能
        function navigate(page) {
            window.location.href = page;
        }

        // 实验操作函数
        function runExperiment() {
            alert('进入实验功能即将开放');
        }

        function favoriteExperiment() {
            alert('收藏功能即将开放');
        }

        function shareExperiment() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? "海龟实验室作品") ?>',
                    text: '来看看这个有趣的Netlogo实验作品！',
                    url: window.location.href
                }).catch(console.error);
            } else {
                alert('分享功能即将开放');
            }
        }

        function remixExperiment() {
            alert('改编功能即将开放');
        }
    </script>
</body>
</html>
