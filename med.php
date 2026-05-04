<?php
session_start();

// ---------- 参数获取与登录检查 ----------
$category  = $_GET['category'] ?? 'Model';
$contentId = $_GET['id'] ?? '';
$type      = $_GET['type'] ?? '';

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    $redirectUrl = '/getv.php?r=' . urlencode("med.php?category={$category}&id={$contentId}&type={$type}");
    header('Location: ' . $redirectUrl);
    exit;
}
$token    = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

// ---------- 默认头像/封面 SVG ----------
define('DEFAULT_AVATAR', 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="#e0e0e0"/><circle cx="50" cy="40" r="20" fill="#bdbdbd"/><path d="M30 75 Q50 60 70 75 Z" fill="#bdbdbd"/></svg>'));
define('DEFAULT_COVER', 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400" viewBox="0 0 800 400"><rect width="800" height="400" fill="#f0f0f0"/><path d="M400 240C444.183 240 480 204.183 480 160C480 115.817 444.183 80 400 80C355.817 80 320 115.817 320 160C320 204.183 355.817 240 400 240Z" fill="#cecece"/><path d="M480 280H320C317.911 280 316 281.911 316 284V224C316 222.089 317.911 220 320 220H480C482.089 220 484 222.089 484 224V284C484 281.911 482.089 280 480 280ZM320 224V280H480V224H320Z" fill="#cecece"/></svg>'));

// ---------- API 请求函数 ----------
function getContentSummary($contentId, $category, $token, $authCode) {
    $url = 'http://nlm-api-cn.turtlesim.com/Contents/GetSummary';
    $body = json_encode(['ContentID' => $contentId, 'Category' => $category]);
    $headers = [
        'Content-Type: application/json', 'Accept: application/json', 'Accept-Language: zh-CN',
        'x-API-Token: ' . $token, 'x-API-AuthCode: ' . $authCode,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}

function getDerivativesAndSupporters($contentId, $category, $token, $authCode) {
    $url = 'http://nlm-api-cn.turtlesim.com/Contents/GetDerivatives';
    $body = json_encode(['ContentID' => $contentId, 'Category' => $category]);
    $headers = [
        'Content-Type: application/json', 'Accept: application/json', 'Accept-Language: zh-CN',
        'x-API-Token: ' . $token, 'x-API-AuthCode: ' . $authCode,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 调试日志（上线后可注释）
    // error_log("GetDerivatives HTTP Code: $httpCode, Error: $curlError, Response: $response");

    if ($response === false || $httpCode !== 200) return null;
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $data['Data'] ?? null;
}

function isContentStarred($contentId, $category, $token, $authCode) {
    $url = 'http://nlm-api-cn.turtlesim.com/Contents/IsStarred';
    $body = json_encode(['ContentID' => $contentId, 'Category' => $category]);
    $headers = [
        'Content-Type: application/json',
        'x-API-Token: ' . $token, 'x-API-AuthCode: ' . $authCode,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);
    return ($data && isset($data['Data']) && $data['Data'] === true);
}

// ---------- 获取数据 ----------
$contentData = getContentSummary($contentId, $category, $token, $authCode);
$content = $contentData['Data'] ?? null;

// 改编 + 支持者
$derivativesData = getDerivativesAndSupporters($contentId, $category, $token, $authCode);
$remixes     = $derivativesData['Experiments']['Children-Models'] ?? [];
$supporters  = $derivativesData['Supporters'] ?? [];
$parentModel = $derivativesData['Model'] ?? null;

$isStarred = isContentStarred($contentId, $category, $token, $authCode);

// ---------- 辅助函数 ----------
function buildAvatarUrl($userId, $avatarId) {
    if (!$avatarId || !$userId) return DEFAULT_AVATAR;
    $base = 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/';
    return $base . substr($userId,0,4) . '/' . substr($userId,4,2) . '/' . substr($userId,6,2) . '/' . substr($userId,8,16) . '/' . $avatarId . '.jpg!full';
}

function buildThumbUrl($exp) {
    if (($exp['Image'] ?? 0) < 0) return DEFAULT_COVER;
    $base = 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/';
    $id = $exp['ID'];
    return $base . substr($id,0,4) . '/' . substr($id,4,2) . '/' . substr($id,6,2) . '/' . substr($id,8,16) . '/' . $exp['Image'] . '.jpg!full';
}

class CustomTagParser {
    public static function parse(string $text): string {
        if (empty($text)) return '';
        $text = htmlspecialchars($text);
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
    <title><?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $content['Subject'] ?? '作品详情') ?> - Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:v-sans,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;line-height:1.6;background:#f5f7fa;color:#333}
        .basic-layout{display:flex;height:100vh}
        .layout-left{flex:1;position:relative}
        .layout-right{flex:1;overflow:hidden}
        .cover{height:100%;background-size:cover;background-position:center;position:relative;display:flex;flex-direction:column;padding:20px}
        .title-btn{font-size:24px;font-weight:bold;color:white;margin:10px 0;text-align:left;background:transparent;border:none;cursor:pointer;display:inline-block;position:relative}
        .tag{display:inline-block;padding:4px 12px;margin:2px;border-radius:16px;background:rgba(255,255,255,0.2);color:white;font-size:12px}
        .cover-bottom{margin-top:auto}
        .btns{display:flex;justify-content:space-around;gap:10px}
        .enter{padding:8px 24px;border-radius:25px;background:#2080f0;color:white;border:none;cursor:pointer;font-size:14px}
        .scroll-container{height:100%;overflow-y:auto}
        .context{padding:20px}
        .n-tabs{width:100%}
        .n-tabs-wrapper{display:flex;justify-content:space-evenly}
        .n-tabs-tab{padding:10px 0;cursor:pointer;color:#666}
        .n-tabs-tab--active{color:#18a058;font-weight:500}
        .n-tab-pane{margin-top:20px}
        .gray{background:#f8f9fa;border-radius:12px;padding:15px}
        .intro{text-align:left;line-height:1.8}
        .intro p{margin-bottom:15px}
        .intro h1,.intro h2,.intro h3{color:#2080f0;margin:20px 0 10px}
        .intro ul,.intro ol{margin:10px 0;padding-left:20px}
        .intro li{margin:5px 0}
        .user-info{display:flex;align-items:center;padding:15px;background:white;border-radius:10px;margin:5px 0}
        .user-avatar{width:50px;height:50px;border-radius:50%;margin-right:15px}
        .user-details{text-align:left}
        .user-name{color:#007bff;margin:0;font-size:16px}
        .user-bio{color:gray;margin:5px 0 0}
        .action-icons{display:flex;justify-content:space-around;padding:10px 0}
        .action-item{color:white;display:flex;flex-direction:column;align-items:center;gap:4px;font-size:12px;cursor:pointer}
        .action-item i{font-size:20px}
        .btn-secondary-outline{padding:8px 24px;border-radius:25px;background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.5);cursor:pointer;font-size:14px}
        .title-popup-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:2000;display:flex;justify-content:center}
        .title-popup-box{background:white;border-radius:12px;padding:16px;margin-top:80px;width:280px;box-shadow:0 8px 30px rgba(0,0,0,0.3);z-index:2001;position:absolute}
        .title-popup-item{padding:12px 0;border-bottom:1px solid #eee;cursor:pointer;display:flex;align-items:center;gap:10px;font-size:15px;color:#333}
        .title-popup-item:last-child{border-bottom:none}
        .title-popup-item i{width:20px;color:#2080f0}
        .supporter-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
        .supporter-item{width:40px;height:40px;border-radius:50%;overflow:hidden;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.1)}
        .supporter-item img{width:100%;height:100%;object-fit:cover}
        textarea,input[type=text]{padding:10px 12px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;outline:none;width:100%;resize:vertical}
        textarea:focus,input[type=text]:focus{border-color:#2080f0;box-shadow:0 0 0 3px rgba(32,128,240,0.1)}
        .comment-submit{padding:10px 18px;background:#2080f0;color:white;border:none;border-radius:10px;cursor:pointer;font-size:14px;white-space:nowrap}
        .RUser{color:#1890ff;font-weight:500}
        .experiment-link,.discussion-link,.model-link,.external-link{color:skyblue;text-decoration:none}
        .experiment-link:hover,.discussion-link:hover,.model-link:hover,.external-link:hover{text-decoration:underline}
        @media (max-width:767px){
            .basic-layout{flex-direction:column}
            .layout-left,.layout-right{flex:none}
            .layout-left{height:50vh}
            .layout-right{height:50vh}
            .title-popup-box{margin-top:60px}
        }
    </style>
</head>
<body>
<div id="app">
    <div class="basic-layout">
        <!-- 左侧预览区 -->
        <div class="layout-left">
            <div style="position:absolute;top:16px;left:16px;right:16px;display:flex;justify-content:space-between;z-index:10">
                <button class="icon-btn" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
                <div style="display:flex;gap:8px">
                    <button class="icon-btn" onclick="shareExperiment()"><i class="fas fa-share-alt"></i></button>
                    <button class="icon-btn" onclick="toggleMenu()"><i class="fas fa-ellipsis-h"></i></button>
                </div>
            </div>
            <div class="cover" style="background-image:url('<?= $content ? buildThumbUrl($content) : DEFAULT_COVER ?>');">
                <div>
                    <button class="title-btn" id="titleBtn" onclick="openTitleMenu()"><?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $content['Subject']) ?></button>
                    <div style="margin-top:8px;">
                        <span class="tag" style="color:aquamarine;font-weight:bold"><?= htmlspecialchars($content['Category']) ?></span>
                        <span class="tag"><i class="fas fa-eye"></i> <?= $content['Visits'] ?? 0 ?></span>
                        <?php if ($content && !empty($content['Tags'])): ?>
                            <?php foreach (array_slice($content['Tags'], 0, 5) as $tag): ?>
                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cover-bottom">
                    <div class="action-icons">
                        <div class="action-item" id="btnSupport" onclick="handleSupport()"><i class="far fa-heart"></i><span>赞 <?= $content['Supports'] ?? 0 ?></span></div>
                        <div class="action-item" id="btnStar" onclick="handleStar()"><i class="<?= $isStarred ? 'fas' : 'far' ?> fa-star"></i><span>收藏</span></div>
                        <div class="action-item" onclick="switchTab('info')"><i class="far fa-comment"></i><span><?= $content['Comments'] ?? 0 ?></span></div>
                        <div class="action-item" onclick="shareExperiment()"><i class="fas fa-share"></i><span>分享</span></div>
                    </div>
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
                                <!-- 简介 -->
                                <div id="intro-tab" class="qh">
                                    <div class="user-info" onclick="event.stopPropagation(); getUserCard('<?= $content['User']['ID'] ?>')">
                                        <img class="user-avatar" src="<?= buildAvatarUrl($content['User']['ID'], $content['User']['Avatar'] ?? 0) ?>" alt="">
                                        <div class="user-details">
                                            <p class="user-name"><?= htmlspecialchars($content['User']['Nickname'] ?? '未知') ?></p>
                                            <p class="user-bio"><?= htmlspecialchars($content['User']['Signature'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <!-- 改编自信息 -->
                                    <?php if ($parentModel && $parentModel['ID']): ?>
                                    <div class="user-info" style="cursor:pointer" onclick="location.href='med.php?id=<?= $parentModel['ID'] ?>&category=Model'">
                                        <img class="user-avatar" src="<?= buildThumbUrl($parentModel) ?>" alt="">
                                        <div class="user-details">
                                            <p class="user-name" style="font-size:14px">改编自「<?= htmlspecialchars($parentModel['LocalizedSubject']['Chinese'] ?? $parentModel['Subject'] ?? '未知') ?>」</p>
                                            <p class="user-bio">作者 <?= htmlspecialchars($parentModel['User']['Nickname'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div style="background:white;border-radius:10px;padding:15px;margin-top:5px">
                                        <h3 style="color:#2080f0;text-align:left;margin-bottom:10px">作品介绍</h3>
                                        <div class="markdown-content intro">
                                            <?php if ($content && isset($content['LocalizedDescription']['Chinese'])): ?>
                                                <?= CustomTagParser::parse($content['LocalizedDescription']['Chinese']) ?>
                                            <?php else: ?>
                                                <?= implode('<br>', array_map(function($s){return CustomTagParser::parse($s);}, $content['Description'] ?? [])) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- 支持者 -->
                                    <?php if (!empty($supporters)): ?>
                                    <div style="background:white;border-radius:10px;padding:15px;margin-top:10px">
                                        <h4 style="margin-bottom:10px">支持者</h4>
                                        <div class="supporter-list">
                                            <?php foreach ($supporters as $supporter): 
                                                $supAvatar = $supporter['Avatar'] ?? 0;
                                                $supUserId = $supporter['ID'];
                                                $imgSrc = ($supAvatar > 0) ? buildAvatarUrl($supUserId, $supAvatar) : DEFAULT_AVATAR;
                                                $onError = ($supAvatar > 0) ? "this.onerror=null;this.src='" . DEFAULT_AVATAR . "';" : '';
                                            ?>
                                                <div class="supporter-item" onclick="event.stopPropagation();getUserCard('<?= $supUserId ?>')" title="<?= htmlspecialchars($supporter['Nickname']) ?>">
                                                    <img src="<?= $imgSrc ?>" alt="" <?= $onError ? 'onerror="' . $onError . '"' : '' ?>>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- 评论 -->
                                <div id="info-tab" class="qh" style="display:none">
                                    <div id="comments">加载评论中...</div>
                                    <?php include 'comment/comment.html' ?>
                                </div>

                                <!-- 改编 -->
                                <div id="remix-tab" class="qh" style="display:none">
                                    <div id="remixes-list"><div style="text-align:center;padding:20px;color:#666">加载中...</div></div>
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
// ---------- 全局变量 ----------
const contentId = '<?= $contentId ?>';
const category = '<?= $category ?>';
const subject = '<?= htmlspecialchars($content['LocalizedSubject']['Chinese'] ?? $content['Subject']) ?>';

// 标题菜单
function openTitleMenu() {
    const exist = document.querySelector('.title-popup-overlay');
    if (exist) exist.remove();
    const overlay = document.createElement('div');
    overlay.className = 'title-popup-overlay';
    overlay.innerHTML = `
        <div class="title-popup-box">
            <div class="title-popup-item" onclick="copyID()"><i class="fas fa-copy"></i> 复制序号：${contentId}</div>
            <div class="title-popup-item" onclick="copyLinkCode()"><i class="fas fa-code"></i> 复制链接代码</div>
            <div class="title-popup-item" onclick="copyShareLink()"><i class="fas fa-link"></i> 复制分享链接</div>
        </div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}
function copyID() { navigator.clipboard.writeText(contentId).then(()=>{ alert('序号已复制'); closeTitleMenu(); }); }
function copyLinkCode() {
    const code = `<${category}=${contentId}>${subject}</${category}>`;
    navigator.clipboard.writeText(code).then(()=>{ alert('链接代码已复制'); closeTitleMenu(); });
}
function copyShareLink() { navigator.clipboard.writeText(location.href).then(()=>{ alert('分享链接已复制'); closeTitleMenu(); }); }
function closeTitleMenu() { const o = document.querySelector('.title-popup-overlay'); if(o) o.remove(); }

// 标签切换
function switchTab(tab) {
    document.querySelectorAll('.qh').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.n-tabs-tab').forEach(t => t.classList.remove('n-tabs-tab--active'));
    document.getElementById(tab + '-tab').style.display = 'block';
    const idx = {intro:0, info:1, remix:2}[tab];
    document.querySelectorAll('.n-tabs-tab')[idx].classList.add('n-tabs-tab--active');
    if (tab === 'remix') loadRemixes();
    if (tab === 'info') fetchComments();
}

// 改编列表（从 PHP 注入数据）
function loadRemixes() {
    const container = document.getElementById('remixes-list');
    const remixes = <?= json_encode($remixes, JSON_UNESCAPED_UNICODE) ?>;
    if (!Array.isArray(remixes) || remixes.length === 0) {
        container.innerHTML = '<div class="empty">暂无改编作品</div>';
        return;
    }
    container.innerHTML = remixes.map(r => {
        const title = r.LocalizedSubject?.Chinese || r.Subject || '未命名';
        const uid = r.User?.ID || '';
        const avatar = uid && r.User?.Avatar ? `http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/${uid.substring(0,4)}/${uid.substring(4,6)}/${uid.substring(6,8)}/${uid.substring(8,24)}/${r.User.Avatar}.jpg!full` : '<?= DEFAULT_AVATAR ?>';
        return `<div class="user-info" style="cursor:pointer" onclick="location.href='med.php?id=${r.ID}&category=Model'">
                    <img class="user-avatar" src="${avatar}" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>';">
                    <div class="user-details">
                        <p class="user-name">${title}</p>
                        <p class="user-bio">作者：${r.User?.Nickname||'匿名'}</p>
                    </div>
                </div>`;
    }).join('');
}

// 评论（动态加载）
function fetchComments() {
    fetch('/comment/?category=<?= $category ?>&id=<?= $contentId ?>')
        .then(r => r.text())
        .then(html => { document.getElementById('comments').innerHTML = html; })
        .catch(() => { document.getElementById('comments').innerHTML = '<div class="error">加载失败</div>'; });
}

// 点赞/收藏
let starStatus = <?= $isStarred ? 'true' : 'false' ?>;
let supportStatus = false;
function handleSupport() {
    const ns = supportStatus ? 0 : 1;
    fetch(`/api/star.php?action=support&id=${contentId}&category=${category}&status=${ns}`, {method:'POST'})
        .then(r => r.json()).then(d => {
            if(d.Success) {
                supportStatus = !supportStatus;
                const i = document.querySelector('#btnSupport i');
                i.className = supportStatus ? 'fas fa-heart' : 'far fa-heart';
                const span = document.querySelector('#btnSupport span');
                const cur = parseInt(span.innerText.match(/\d+/)?.[0]||0);
                span.innerText = '赞 ' + (cur + (supportStatus?1:-1));
            }
        });
}
function handleStar() {
    const ns = starStatus ? 0 : 1;
    fetch(`/api/star.php?action=star&id=${contentId}&category=${category}&status=${ns}`, {method:'POST'})
        .then(r => r.json()).then(d => {
            if(d.Success) {
                starStatus = !starStatus;
                document.querySelector('#btnStar i').className = starStatus ? 'fas fa-star' : 'far fa-star';
            }
        });
}

// 分享
function shareExperiment() {
    const data = { title: subject, url: location.href };
    if (navigator.share && navigator.canShare && navigator.canShare(data)) {
        navigator.share(data).catch(()=>{});
    } else {
        navigator.clipboard?.writeText(location.href).then(()=>alert('链接已复制')) || prompt('复制链接：', location.href);
    }
}
function toggleMenu() { alert('菜单功能即将开放'); }
function runExperiment() { location.href = 'experiments/?id=' + contentId; }

// ---------- 用户卡片（原始逻辑） ----------
var currentCard = null, isOpeningCard = false;
function getUserCard(uid) {
    if (isOpeningCard) return;
    if (currentCard) { currentCard.remove(); currentCard = null; }
    isOpeningCard = true;
    fetch('/user/card.php?id=' + uid)
        .then(r => r.text())
        .then(html => {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;z-index:9999';
            overlay.innerHTML = '<div style="position:relative">' + html + '</div>';
            document.body.appendChild(overlay);
            currentCard = overlay;
            isOpeningCard = false;
            overlay.addEventListener('click', function(e) { if (e.target === overlay) { overlay.remove(); currentCard = null; } }, {once: true});
        })
        .catch(() => { isOpeningCard = false; });
}
document.addEventListener('keydown', e => { if (e.key === 'Escape' && currentCard) { currentCard.remove(); currentCard = null; } });

// ---------- 事件委托：用户卡片 & 评论回复 ----------
document.addEventListener('click', function(e) {
    const avatar = e.target.closest('.user-avatar, #avatar');
    const name = e.target.closest('.user-name, .name');
    const ruser = e.target.closest('.RUser');
    if (avatar || name || ruser) {
        e.stopPropagation();
        let uid = null;
        if (ruser) {
            uid = ruser.getAttribute('data-user');
        } else {
            const cc = e.target.closest('#notification_container');
            if (cc) uid = cc.getAttribute('data-rid');
        }
        if (!uid) {
            const ui = e.target.closest('.user-info');
            if (ui) {
                const onclick = ui.getAttribute('onclick') || '';
                const match = onclick.match(/getUserCard\('([^']+)'\)/);
                if (match) uid = match[1];
            }
        }
        if (uid) getUserCard(uid);
        return;
    }
    // 回复
    const cb = e.target.closest('#notification_container');
    if (cb) {
        const nameEl = cb.querySelector('.name');
        const rid = cb.getAttribute('data-rid');
        if (nameEl && rid) {
            const ta = document.querySelector('textarea');
            if (ta) {
                ta.value = '回复@' + nameEl.innerText.trim() + ': ';
                ta.focus();
            }
        }
    }
});

// 定时刷新评论
setInterval(fetchComments, 10000);
// 记录访问
fetch('/api/visit.php?id=' + contentId + '&category=' + category, {method:'POST'});
</script>
</body>
</html>