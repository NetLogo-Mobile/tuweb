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
} else {
    $redirectUrl = '/getv.php?r=' . urlencode('med.php?category=' . $category . '&id=' . $contentId . '&type=' . $type);
    header('Location: ' . $redirectUrl);
    exit;
}

// 获取作品详情
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

// 获取改编列表
function getDerivatives($contentId, $category, $token, $authCode) {
    $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    $url = $baseUrl . 'Contents/GetDerivatives';
    
    $requestData = [
        'ContentID' => $contentId,
        'Category' => $category,
        'Language' => 'Chinese',
        'WithSummary' => true
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
    ]);
    
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    $data = json_decode($body, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : null;
}

// 检查是否已收藏（Star）
function isContentStarred($contentId, $category, $token, $authCode) {
    $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    $url = $baseUrl . 'Contents/IsStarred';
    
    $requestData = [
        'ContentID' => $contentId,
        'Category' => $category,
    ];
    
    $headers = [
        "Content-Type: application/json",
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
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    $data = json_decode($body, true);
    if ($data && isset($data['Data']) && is_bool($data['Data'])) {
        return $data['Data'];
    }
    return false;
}

// 获取作品详情
$contentData = null;
if (!empty($contentId) && !empty($category)) {
    $token = $_SESSION['token'] ?? '';
    $authCode = $_SESSION['authCode'] ?? '';
    
    if (isset($token) && isset($authCode)) {
        $contentData = getContentSummary($contentId, $category, $token, $authCode);
    }
}

$content = $contentData['Data'] ?? null;

// 获取改编列表
$remixes = [];
$isStarred = false;
if ($content && $contentId && $category) {
    $derivativesData = getDerivatives($contentId, $category, $token, $authCode);
    if ($derivativesData && isset($derivativesData['Data']['Experiments']['Children-Models'])) {
        $remixes = $derivativesData['Data']['Experiments']['Children-Models'];
    }
    // 获取初始收藏状态
    $isStarred = isContentStarred($contentId, $category, $token, $authCode);
}

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

// 自定义标签解析器类
class CustomTagParser {
    public static function parse(string $text): string {
        if (empty($text)) return '';
        $text = htmlspecialchars($text);
        $text = self::parseMarkdownHeadings($text);
        $text = nl2br($text);
        
        $patterns = [
            '/&lt;user=([a-f0-9]+)&gt;(.*?)&lt;\/user&gt;/i',
            '/&lt;experiment=([a-f0-9]+)&gt;(.*?)&lt;\/experiment&gt;/i',
            '/&lt;discussion=([a-f0-9]+)&gt;(.*?)&lt;\/discussion&gt;/i',
            '/&lt;model=([a-f0-9]+)&gt;(.*?)&lt;\/model&gt;/i',
            '/&lt;external=([^&]+)&gt;(.*?)&lt;\/external&gt;/i',
            '/&lt;size=([^&]+)&gt;(.*?)&lt;\/size&gt;/i',
            '/&lt;color=([^&]+)&gt;(.*?)&lt;\/color&gt;/i',
            '/&lt;b&gt;(.*?)&lt;\/b&gt;/i',
            '/&lt;i&gt;(.*?)&lt;\/i&gt;/i',
            '/&lt;a&gt;(.*?)&lt;\/a&gt;/i'
        ];
        
        $replacements = [
            '<span class="RUser" data-user="$1">$2</span>',
            '<a href="med.php?id=$1&category=Experiment" class="experiment-link">$2</a>',
            '<a href="med.php?id=$1&category=Discussion" class="discussion-link">$2</a>',
            '<a href="med.php?id=$1&category=Model" class="model-link">$2</a>',
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" class="external-link">$2</a>',
            '<span style="font-size: $1;">$2</span>',
            '<span style="color: $1;">$2</span>',
            '<strong>$1</strong>',
            '<em>$1</em>',
            '<span class="blue-tag">$1</span>'
        ];
        
        $text = preg_replace($patterns, $replacements, $text);
        $text = self::parseSimpleMarkdown($text);
        return $text;
    }
    
    private static function parseMarkdownHeadings(string $text): string {
        $text = preg_replace('/^# (.+)$/m', '<strong class="h1">$1</strong>', $text);
        $text = preg_replace('/^## (.+)$/m', '<strong class="h2">$1</strong>', $text);
        $text = preg_replace('/^### (.+)$/m', '<strong class="h3">$1</strong>', $text);
        $text = preg_replace('/^#### (.+)$/m', '<strong class="h4">$1</strong>', $text);
        return $text;
    }
    
    private static function parseSimpleMarkdown(string $text): string {
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        return $text;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="google" content="notranslate">
    <title><?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $pageTitle) ?> - Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel='stylesheet' href='../styles/main.css'/>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:v-sans,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;line-height:1.6;margin:0;background:#f5f7fa;color:#333}.RUser{color:cornflowerblue}a[internal]{color:cornflowerblue;text-decoration:none}.basic-layout{display:flex;height:100vh}.layout-left{flex:1;position:relative}.layout-right{flex:1;overflow:hidden}.cover{height:100%;background-size:cover;background-position:center;position:relative;display:flex;flex-direction:column;padding:20px}.return{width:2.7em;cursor:pointer}.title{font-size:24px;font-weight:bold;color:white;margin:10px 0;text-align:left}.tag{display:inline-block;padding:4px 12px;margin:2px;border-radius:16px;background:rgba(255,255,255,0.2);color:white;font-size:12px}.coverBottom{margin-top:auto}.btns{display:flex;justify-content:space-around;gap:10px}.enter{padding:8px 24px;border-radius:25px;background:#2080f0;color:white;border:none;cursor:pointer;font-size:14px}.scroll-container{height:100%;overflow-y:auto}.context{padding:20px}.n-tabs{width:100%}.n-tabs-wrapper{display:flex;justify-content:space-evenly}.n-tabs-tab{padding:10px 0;cursor:pointer;color:#666}.n-tabs-tab--active{color:#18a058;font-weight:500}.n-tab-pane{margin-top:20px}.gray{background:#f8f9fa;border-radius:12px;padding:15px}.intro{text-align:left;line-height:1.8}.intro p{margin-bottom:15px}.intro h1,.intro h2,.intro h3{color:#2080f0;margin:20px 0 10px}.intro ul,.intro ol{margin:10px 0;padding-left:20px}.intro li{margin:5px 0}.user-info{display:flex;align-items:center;padding:15px;background:white;border-radius:10px;margin:5px 0}.user-avatar{width:50px;height:50px;border-radius:50%;margin-right:15px}.user-details{text-align:left}.user-name{color:#007bff;margin:0;font-size:16px}.user-bio{color:gray;margin:5px 0 0}.action-buttons{display:flex;gap:10px;margin:20px 0}.btn{padding:10px 20px;border-radius:20px;border:none;cursor:pointer;font-size:14px}.btn-primary{background:#2080f0;color:white}.btn-secondary{background:#f8f9fa;color:#333;border:1px solid #ddd}.error{text-align:center;padding:60px 20px;color:#e74c3c}.empty{text-align:center;padding:60px 20px;color:#666}.back-button{position:absolute;top:20px;left:20px;background:rgba(255,255,255,0.2);color:white;border:none;padding:8px 16px;border-radius:20px;cursor:pointer;z-index:10}.footer{position:fixed;bottom:0;left:0;right:0;background:white;display:flex;justify-content:space-around;padding:10px 0;box-shadow:0 -2px 10px rgba(0,0,0,0.1);z-index:1000}.footer div{display:flex;flex-direction:column;align-items:center;gap:5px;font-size:12px;color:#666}.footer div.active{color:#667eea}.footer i{font-size:20px}.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:15px 0}.stat-item{text-align:center;padding:10px;background:white;border-radius:8px}.stat-number{font-size:18px;font-weight:bold;color:#2080f0}.stat-label{font-size:12px;color:#666}.markdown-content h1{font-size:24px;margin:20px 0 10px}.markdown-content h2{font-size:20px;margin:15px 0 8px}.markdown-content h3{font-size:16px;margin:12px 0 6px}.markdown-content ul,.markdown-content ol{padding-left:20px}.markdown-content li{margin:5px 0}
        
        /* 新增顶部按钮 */
        .icon-btn {
            background: rgba(255,255,255,0.25);
            border: none;
            color: white;
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            backdrop-filter: blur(4px);
        }
        /* 互动图标栏 */
        .action-icons {
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
        }
        .action-item {
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        .action-item i {
            font-size: 20px;
        }
        /* 讨论按钮 */
        .btn-secondary-outline {
            padding: 8px 24px;
            border-radius: 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.5);
            cursor: pointer;
            font-size: 14px;
            backdrop-filter: blur(4px);
        }
        
        /* 原有自定义标签样式 */
        .intro .h1, .intro .h2, .intro .h3, .intro .h4 { font-weight: bold; margin: 12px 0 6px 0; line-height: 1.2; }
        .intro .h1 { font-size: 20px; }
        .intro .h2 { font-size: 19px; }
        .intro .h3 { font-size: 18px; }
        .intro .h4 { font-size: 17px; }
        .blue-tag { color: #0000FF; font-size: 1em; text-decoration: none; border: none; padding: 0; margin: 0; background: none; font-weight: normal; }
        .experiment-link, .discussion-link, .model-link, .external-link { color: skyblue; font-size: 1em; text-decoration: none; border: none; padding: 0; margin: 0 2px; background: none; font-weight: normal; }
        .experiment-link:hover, .discussion-link:hover, .model-link:hover, .external-link:hover { text-decoration: underline; }
        .notification_text code { background-color: #f5f5f5; padding: 2px 4px; border-radius: 2px; font-family: monospace; font-size: 0.9em; color: #c7254e; }
        .notification_text del { color: #999; text-decoration: line-through; }
        .notification_text strong { font-weight: 600; }
        .notification_text em { font-style: italic; }
        .RUser { color: #1890ff; font-weight: 500; }
        
        @media (max-width:768px){
            .basic-layout{flex-direction:column}
            .layout-left,.layout-right{flex:none}
            .layout-left{height:50vh}
            .layout-right{height:50vh}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="basic-layout">
            <!-- 左侧预览区 -->
            <div class="layout-left">
                <!-- 顶部操作栏 -->
                <div style="position: absolute; top: 16px; left: 16px; right: 16px; display: flex; justify-content: space-between; z-index: 10;">
                    <button class="icon-btn" onclick="window.history.back()"><i class="fas fa-arrow-left"></i></button>
                    <div style="display: flex; gap: 8px;">
                        <button class="icon-btn" onclick="shareExperiment()"><i class="fas fa-share-alt"></i></button>
                        <button class="icon-btn" onclick="toggleMenu()"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                </div>
                
                <!-- 封面及内容 -->
                <div class="cover" style="background-image: url('<?php 
                    if ($content && isset($content['Image'])) {
                        echo 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/' . 
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
                        <div class="title"><?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $content['Subject']) ?></div>
                        <div>
                            <span class="tag" style="color: aquamarine; font-weight: bold;"><?= htmlspecialchars($content['Category']) ?></span>
                            <span class="tag"><i class="fas fa-eye"></i>&nbsp;<?= $content['Visits'] ?? 0 ?></span>
                            <?php if ($content && isset($content['Tags'])): ?>
                                <?php foreach (array_slice($content['Tags'], 0, 5) as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="coverBottom">
                        <!-- 互动图标栏 -->
                        <div class="action-icons">
                            <div class="action-item" id="btnSupport" onclick="handleSupport()">
                                <i class="far fa-heart"></i><span>赞 <?= $content['Supports'] ?? 0 ?></span>
                            </div>
                            <div class="action-item" id="btnStar" onclick="handleStar()">
                                <i class="<?= $isStarred ? 'fas' : 'far' ?> fa-star"></i><span>收藏</span>
                            </div>
                            <div class="action-item" onclick="switchTab('info')">
                                <i class="far fa-comment"></i><span><?= $content['Comments'] ?? 0 ?></span>
                            </div>
                            <div class="action-item" onclick="shareExperiment()">
                                <i class="fas fa-share"></i><span>分享</span>
                            </div>
                        </div>
                        
                        <!-- 底部双按钮 -->
                        <div class="btns">
                            <button class="enter" onclick="runExperiment()">进入实验</button>
                            <button class="btn-secondary-outline" onclick="switchTab('info')">参与讨论</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧内容区 -->
            <div class="layout-right">
                <div class="scroll-container">
                    <div class="context">
                        <div class="n-tabs">
                            <div class="n-tabs-wrapper">
                                <div class="n-tabs-tab n-tabs-tab--active" onclick="switchTab('intro')">简介</div>
                                <div class="n-tabs-tab" onclick="switchTab('info')">评论(<?= $content['Comments'] ?? 0 ?>)</div>
                                <div class="n-tabs-tab" onclick="switchTab('remix')">改编(<?= count($remixes) ?>)</div>
                            </div>
                            
                            <div class="n-tab-pane">
                                <div class="gray">
                                    <!-- 简介标签页 -->
                                    <div id="intro-tab" class="qh">
                                        <div class="user-info" onclick="getUserCard('<?= $content['User']['ID'] ?>')">
                                            <img class="user-avatar" src="<?php
                                                if ($content && isset($content['User']['Avatar'])) {
                                                    echo 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/' . 
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
                                        <div style="margin: 5px; background-color: white; border-radius: 10px; padding: 15px;" class='tab-content'>
                                            <h3 style="color: #2080f0; text-align: left; margin-top: 2px; margin-bottom: 10px;">作品介绍</h3>
                                            <div class="markdown-content intro">
                                                <?php if ($content && isset($content['LocalizedDescription']['Chinese'])): ?>
                                                    <?= CustomTagParser::parse($content['LocalizedDescription']['Chinese']) ?>
                                                <?php else: ?>
                                                    <?= implode('<br>', array_map(function($a) {return CustomTagParser::parse($a);}, $content['Description'])) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 评论标签页 -->
                                    <div id="info-tab" class="qh" style="display: none;">
                                        <div id="comments">加载评论中...</div>
                                        <?php include 'comment/comment.html' ?>
                                    </div>
                                    
                                    <!-- 改编标签页 -->
                                    <div id="remix-tab" class="qh" style="display: none;">
                                        <div id="remixes-list">
                                            <div style="text-align:center;padding:20px;color:#666;">加载改编列表中...</div>
                                        </div>
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
        // 初始收藏状态
        var starStatus = <?= $isStarred ? 'true' : 'false' ?>;
        var supportStatus = false; // 无初始API，默认未赞

        // 标签切换
        function switchTab(tabName) {
            document.querySelectorAll('.qh').forEach(tab => tab.style.display = 'none');
            document.querySelectorAll('.n-tabs-tab').forEach(tab => tab.classList.remove('n-tabs-tab--active'));
            
            const pane = document.getElementById(tabName + '-tab');
            if (pane) pane.style.display = 'block';
            
            const tabs = document.querySelectorAll('.n-tabs-tab');
            const idx = {intro:0, info:1, remix:2}[tabName];
            if (tabs[idx]) tabs[idx].classList.add('n-tabs-tab--active');
            
            // 切换到改编时加载数据
            if (tabName === 'remix' && document.getElementById('remixes-list').innerText.includes('加载')) {
                loadRemixes();
            }
            // 切换到评论时立即加载一次
            if (tabName === 'info') {
                fetchComments();
            }
        }
        
        // 加载改编列表
        function loadRemixes() {
            const remixes = <?= json_encode($remixes, JSON_UNESCAPED_UNICODE) ?>;
            const container = document.getElementById('remixes-list');
            if (!remixes || remixes.length === 0) {
                container.innerHTML = '<div class="empty">暂无改编作品</div>';
                return;
            }
            container.innerHTML = remixes.map(item => {
                const title = item.LocalizedSubject?.Chinese || item.Subject || '未命名';
                const id = item.ID;
                const user = item.User?.Nickname || '匿名';
                const avatarBase = 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/';
                const avatarUrl = item.User?.Avatar ? 
                    avatarBase + id.substring(0,4) + '/' + id.substring(4,6) + '/' + id.substring(6,8) + '/' + id.substring(8,16) + '/' + item.User.Avatar + '.jpg!full' :
                    'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjUiIGN5PSIyNSIgcj0iMjUiIGZpbGw9IiNGMEYwRjAiLz4KPHBhdGggZD0iTTI1IDMzQzI5LjQxODMgMzMgMzMgMjkuNDE4MyAzMyAyNUMzMyAyMC41ODE3IDI5LjQxODMgMTcgMjUgMTdDMjAuNTgxNyAxNyAxNyAyMC41ODE3IDE3IDI1QzE3IDI5LjQxODMgMjAuNTgxNyAzMyAyNSAzM1oiIGZpbGw9IiNDRUNFQ0UiLz4KPHBhdGggZD0iTTMzIDM3SDE3QzE2LjQ0NzcgMzcgMTYgMzYuNTUyMyAxNiAzNlYyNkMxNiAyNS40NDc3IDE2LjQ0NzcgMjUgMTcgMjVIMzNDMzMuNTUyMyAyNSAzNCAyNS40NDc3IDM0IDI2VjM2QzM0IDM2LjU1MjMgMzMuNTUyMyAzNyAzMyAzN1pNMTcgMjZWMzZIMzNWMjZIMTdaIiBmaWxsPSIjQ0VDRUNFIi8+Cjwvc3ZnPgo=';
                return `<div class="user-info" style="cursor:pointer;" onclick="location.href='med.php?id=${id}&category=Model'">
                            <img class="user-avatar" src="${avatarUrl}" alt="封面">
                            <div class="user-details">
                                <p class="user-name">${title}</p>
                                <p class="user-bio">作者: ${user}</p>
                            </div>
                        </div>`;
            }).join('');
        }
        
        // 动态加载评论（复用原有逻辑）
        function fetchComments() {
            fetch('/comment/?category=<?= $category ?>&id=<?= $contentId ?>')
                .then(response => response.text())
                .then(html => { document.getElementById('comments').innerHTML = html; })
                .catch(e => { document.getElementById('comments').innerHTML = '<div class="error">加载评论失败</div>'; });
        }
        
        // 点赞
        function handleSupport() {
            const newStatus = supportStatus ? 0 : 1;
            fetch('/api/star.php?action=support&id=<?= $contentId ?>&category=<?= $category ?>&status=' + newStatus, { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.Success) {
                        supportStatus = !supportStatus;
                        const btn = document.getElementById('btnSupport');
                        const icon = btn.querySelector('i');
                        icon.className = supportStatus ? 'fas fa-heart' : 'far fa-heart';
                        const span = btn.querySelector('span');
                        const cur = parseInt(span.innerText.match(/\d+/)?.[0] || 0);
                        span.innerText = '赞 ' + (cur + (supportStatus ? 1 : -1));
                    } else {
                        alert('操作失败');
                    }
                });
        }
        
        // 收藏
        function handleStar() {
            const newStatus = starStatus ? 0 : 1;
            fetch('/api/star.php?action=star&id=<?= $contentId ?>&category=<?= $category ?>&status=' + newStatus, { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.Success) {
                        starStatus = !starStatus;
                        document.getElementById('btnStar').querySelector('i').className = starStatus ? 'fas fa-star' : 'far fa-star';
                    } else {
                        alert('操作失败');
                    }
                });
        }
        
        // 分享
        function shareExperiment() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $content['Subject']) ?>',
                    url: window.location.href
                }).catch(()=>{});
            } else {
                prompt('复制链接分享:', window.location.href);
            }
        }
        
        // 菜单
        function toggleMenu() {
            alert('菜单功能（举报/编辑等）即将开放');
        }

        // 原有实验进入
        function runExperiment() {
            location.href='experiments/?id=<?= $contentId ?>';
        }

        function favoriteExperiment() { alert('收藏功能已移至图标栏'); }
        function remixExperiment() { alert('改编功能即将开放'); }

        // 保持原有定时刷新评论
        setInterval(fetchComments, 10000);
        
        // 记录访问（静默）
        fetch('/api/visit.php?id=<?= $contentId ?>&category=<?= $category ?>', { method: 'POST' });
    </script>
    
    <!-- 保留原有的用户卡片及交互脚本 -->
    <script>
    // 以下是原有的 getUserCard 等复杂逻辑，保持不变
    var currentCard = null;
    var isOpeningCard = false;
    function getUserCard(uid) {
        if (isOpeningCard) return;
        if (currentCard) { currentCard.remove(); currentCard = null; }
        isOpeningCard = true;
        fetch('/user/card.php?id=' + uid)
            .then(r => r.text())
            .then(html => {
                const overlay = document.createElement('div');
                overlay.id = 'userCardOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;z-index:9999;';
                overlay.innerHTML = '<div style="position:relative;">' + html + '</div>';
                document.body.appendChild(overlay);
                currentCard = overlay;
                isOpeningCard = false;
                overlay.addEventListener('click', function(e) { if (e.target === overlay) { overlay.remove(); currentCard = null; } }, {once: true});
            })
            .catch(e => { isOpeningCard = false; });
    }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && currentCard) { currentCard.remove(); currentCard = null; } });
    
    // 原有RUser点击跳转
    setInterval(() => {
        document.querySelectorAll('.RUser').forEach(el => {
            el.removeEventListener('click', userClickHandler);
            el.addEventListener('click', userClickHandler = function(e) {
                e.stopPropagation();
                const uid = this.getAttribute('data-user');
                if (uid) window.location.href = 'user.php?id=' + uid;
            });
        });
    }, 500);
    </script>
</body>
</html>