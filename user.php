<?php
session_start();

// 参数（兼容 category=User，便于 comment.html 内 JS 解析）
$targetId   = $_GET['id'] ?? '';
$category   = $_GET['category'] ?? 'User';   // 主要为了 URL 中包含 category=User
$targetName = $_GET['name'] ?? '';

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    $redirectUrl = '/getv.php?r=' . urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . $redirectUrl);
    exit;
}
$token    = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

define('DEFAULT_AVATAR', 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="#e0e0e0"/><circle cx="50" cy="40" r="20" fill="#bdbdbd"/><path d="M30 75 Q50 60 70 75 Z" fill="#bdbdbd"/></svg>'));

// ---------- API ----------
function getUserInfo(string $userId, string $token, string $authCode): ?array {
    $url = 'http://nlm-api-cn.turtlesim.com/Users/GetUser';
    $body = json_encode(['ID' => $userId]);
    $headers = ['Content-Type: application/json', 'x-API-Token: ' . $token, 'x-API-AuthCode: ' . $authCode];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);
    return (json_last_error() === JSON_ERROR_NONE && isset($data['Data'])) ? $data['Data'] : null;
}

function getUserProfileProjects(string $userId, string $token, string $authCode): ?array {
    $url = 'http://nlm-api-cn.turtlesim.com/Contents/GetProfile';
    $body = json_encode(['ID' => $userId]);
    $headers = ['Content-Type: application/json', 'x-API-Token: ' . $token, 'x-API-AuthCode: ' . $authCode];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);
    return (json_last_error() === JSON_ERROR_NONE && isset($data['Data'])) ? $data['Data'] : null;
}

// 获取用户数据
$userInfo = null; $profile = null;
if (!empty($targetId)) {
    $userInfo = getUserInfo($targetId, $token, $authCode);
    $profile  = getUserProfileProjects($targetId, $token, $authCode);
} elseif (!empty($targetName)) {
    // 用昵称查 ID（简单实现）
    $url = 'http://nlm-api-cn.turtlesim.com/Users/GetUser';
    $body = json_encode(['Name' => $targetName]);
    $headers = ['Content-Type: application/json', 'x-API-Token: ' . $token, 'x-API-AuthCode: ' . $authCode];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);
    if ($data && isset($data['Data'])) {
        $userInfo = $data['Data'];
        $targetId = $userInfo['User']['ID'] ?? '';
        if ($targetId) $profile = getUserProfileProjects($targetId, $token, $authCode);
    }
}

$targetUser     = $userInfo['User'] ?? null;
$statistic      = $userInfo['Statistic'] ?? null;
$followingCount = $statistic['FollowingCount'] ?? 0;
$followerCount  = $statistic['FollowerCount'] ?? 0;
$isFollowed     = ($userInfo['Relation'] ?? 0) == 1;
$coverSummary   = $statistic['Cover'] ?? null;
$experiments    = $profile['Experiments'] ?? [];

$moduleOrder = [
    'Featured-Models' => '精选实验', 'Featured-Discussions' => '精选讨论', 'Featured-Experiments' => '精选实验',
    'Popular-Models' => '热门实验', 'Popular-Discussions' => '热门讨论', 'Popular-Experiments' => '热门实验',
    'Latest-Models' => '最新实验', 'Latest-Discussions' => '最新讨论', 'Latest-Experiments' => '最新实验',
];

function buildAvatarUrl($userId, $avatarId) {
    if ($avatarId <= 0 || !$userId) return DEFAULT_AVATAR;
    return 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/' . substr($userId,0,4) . '/' . substr($userId,4,2) . '/' . substr($userId,6,2) . '/' . substr($userId,8,16) . '/' . $avatarId . '.jpg!full';
}
function buildThumbUrl($exp) {
    if (($exp['Image'] ?? 0) <= 0) return DEFAULT_AVATAR;
    $id = $exp['ID'];
    return 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/' . substr($id,0,4) . '/' . substr($id,4,2) . '/' . substr($id,6,2) . '/' . substr($id,8,16) . '/' . $exp['Image'] . '.jpg!full';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($targetUser['Nickname'] ?? '用户主页') ?> - Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:v-sans,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;line-height:1.6;background:#f5f7fa;color:#333}
        .basic-layout{display:flex;height:100vh}
        .layout-left{flex:1;position:relative;background:#2c3e50;display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;padding:20px}
        .layout-right{flex:1;overflow:hidden}
        .top-bar{position:absolute;top:16px;left:16px;right:16px;display:flex;justify-content:space-between;z-index:10}
        .icon-btn{background:rgba(255,255,255,0.25);border:none;color:white;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;backdrop-filter:blur(4px)}
        .user-info-box{position:relative;text-align:center;padding:20px 0}
        .user-avatar-large{width:80px;height:80px;border-radius:50%;margin-bottom:10px;border:3px solid white}
        .user-nickname{font-size:22px;font-weight:bold}
        .user-tags{display:flex;gap:6px;justify-content:center;margin:8px 0;flex-wrap:wrap}
        .user-tag{background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:12px}
        .stats-row{display:flex;gap:20px;justify-content:center;margin:10px 0}
        .stat-item{text-align:center}
        .stat-num{font-size:18px;font-weight:bold}
        .stat-label{font-size:12px;opacity:0.8}
        .cover-hint{background:rgba(255,255,255,0.15);padding:8px 16px;border-radius:8px;margin:12px 0;cursor:pointer;font-size:13px}
        .action-buttons{display:flex;gap:10px;margin-top:15px}
        .btn-follow,.btn-message{padding:8px 24px;border-radius:20px;border:none;cursor:pointer;font-size:14px;display:flex;align-items:center;gap:5px}
        .btn-follow{background:#2080f0;color:white}
        .btn-follow.following{background:#666}
        .btn-message{background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.5)}
        .scroll-container{height:100%;overflow-y:auto}
        .content-area{padding:20px}
        .tab-bar{display:flex;justify-content:space-evenly;border-bottom:1px solid #eee;margin-bottom:20px}
        .tab-item{padding:10px 20px;cursor:pointer;color:#666;position:relative}
        .tab-item.active{color:#18a058;font-weight:500}
        .tab-item.active::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:2px;background:#18a058}
        .module{margin-bottom:25px}
        .module-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
        .module-title{font-size:16px;font-weight:600}
        .more-link{color:#2080f0;font-size:13px;text-decoration:none}
        .card-list{display:grid;grid-template-columns:1fr;gap:10px}
        .work-card{display:flex;background:white;border-radius:10px;padding:10px;cursor:pointer;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,0.05)}
        .work-thumb{width:60px;height:45px;border-radius:6px;object-fit:cover;margin-right:12px;background:#ececec}
        .work-info{flex:1}
        .work-title{font-weight:500;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .work-meta{font-size:12px;color:#888;display:flex;gap:8px}
        .empty-state{text-align:center;color:#999;padding:40px}

        /* 评论 / 留言统一样式（与 comment/index.php 完全一致） */
        #notification_container { display: flex; padding: 12px 0; align-items: flex-start; }
        .img { margin-right: 12px; flex-shrink: 0; }
        #avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #e0e0e0; }
        .notification { flex: 1; min-width: 0; }
        .notification_title { display: flex; align-items: center; margin-bottom: 4px; }
        .name { font-weight: 600; font-size: 15px; color: #1a1a1a; flex: 1; }
        .time { font-size: 13px; color: #999; margin-left: 8px; }
        .notification_message { margin-top: 2px; }
        .notification_text { font-size: 15px; color: #333; line-height: 1.4; word-break: break-word; }
        .RUser { color: #1890ff; font-weight: 500; }
        .n-divider { display: flex; align-items: center; margin: 16px 0; }
        .n-divider__line { flex: 1; height: 1px; background-color: #efeff5; }
        .n-divider__line--left { margin-right: 0; }

        @media (min-width:768px) {
            .card-list{grid-template-columns:repeat(2,1fr)}
        }
        @media (max-width:767px) {
            .basic-layout{flex-direction:column}
            .layout-left{height:50vh;flex:none}
            .layout-right{height:50vh}
        }
    </style>
</head>
<body>
<div id="app">
    <div class="basic-layout">
        <!-- 左侧用户信息区（不变） -->
        <div class="layout-left">
            <div class="top-bar">
                <button class="icon-btn" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
                <div style="display:flex;gap:8px">
                    <button class="icon-btn" onclick="shareProfile()"><i class="fas fa-share-alt"></i></button>
                    <button class="icon-btn" onclick="toggleMenu()"><i class="fas fa-ellipsis-h"></i></button>
                </div>
            </div>
            <div class="user-info-box">
                <img class="user-avatar-large" src="<?= buildAvatarUrl($targetUser['ID'] ?? '', $targetUser['Avatar'] ?? 0) ?>" alt="">
                <div class="user-nickname"><?= htmlspecialchars($targetUser['Nickname'] ?? '未知用户') ?></div>
                <div class="user-tags">
                    <?php if ($targetUser['Verification'] === 'Editor'): ?><span class="user-tag">编辑</span><?php endif; ?>
                    <?php if ($targetUser['Verification'] === 'Volunteer'): ?><span class="user-tag">志愿者</span><?php endif; ?>
                </div>
                <div class="stats-row">
                    <div class="stat-item"><span class="stat-num"><?= $followingCount ?></span><span class="stat-label">关注</span></div>
                    <div class="stat-item"><span class="stat-num"><?= $followerCount ?></span><span class="stat-label">粉丝</span></div>
                </div>
                <?php if ($coverSummary): ?>
                <div class="cover-hint" onclick="location.href='med.php?id=<?= $coverSummary['ID'] ?>&category=<?= $coverSummary['Category'] ?>'">
                    📌 <?= htmlspecialchars($coverSummary['Subject'] ?? '封面作品') ?>
                </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <button id="followBtn" class="btn-follow <?= $isFollowed ? 'following' : '' ?>" onclick="toggleFollow()">
                        <i class="fas <?= $isFollowed ? 'fa-check' : 'fa-plus' ?>"></i>
                        <span><?= $isFollowed ? '已关注' : '关注' ?></span>
                    </button>
                    <button class="btn-message" onclick="switchTab('comments')"><i class="far fa-comment"></i> 留言</button>
                </div>
            </div>
        </div>

        <!-- 右侧内容区 -->
        <div class="layout-right">
            <div class="scroll-container">
                <div class="content-area">
                    <div class="tab-bar">
                        <div class="tab-item active" data-tab="works">作品</div>
                        <div class="tab-item" data-tab="comments">留言</div>
                        <div class="tab-item" data-tab="fans">粉丝</div>
                    </div>

                    <!-- 作品标签页 -->
                    <div id="works-tab" class="tab-content">
                        <?php if (!empty($experiments)): ?>
                            <?php foreach ($experiments as $key => $list):
                                if (empty($list)) continue;
                                $title = $moduleOrder[$key] ?? $key;
                            ?>
                            <div class="module">
                                <div class="module-header">
                                    <span class="module-title"><?= $title ?></span>
                                    <a href="#" class="more-link">更多 &gt;</a>
                                </div>
                                <div class="card-list">
                                    <?php foreach ($list as $exp): ?>
                                    <div class="work-card" onclick="location.href='med.php?id=<?= $exp['ID'] ?>&category=<?= $exp['Category'] ?>'">
                                        <img class="work-thumb" src="<?= buildThumbUrl($exp) ?>" alt="">
                                        <div class="work-info">
                                            <div class="work-title"><?= htmlspecialchars($exp['LocalizedSubject']['Chinese'] ?? $exp['Subject']) ?></div>
                                            <div class="work-meta">
                                                <span><?= htmlspecialchars($exp['User']['Nickname'] ?? '') ?></span>
                                                <?php if (!empty($exp['Tags'])): ?>
                                                    <span><?= implode(' · ', array_slice($exp['Tags'], 0, 2)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">暂无作品</div>
                        <?php endif; ?>
                    </div>

                    <!-- 留言标签页 -->
                    <div id="comments-tab" class="tab-content" style="display:none">
                        <!-- 关键修改：容器 ID 必须为 comments，以便 sm 函数刷新时找到 -->
                        <div id="comments">加载中...</div>
                        <!-- 直接 include 与 med.php 完全相同的输入组件 -->
                        <?php include 'comment/comment.html' ?>
                    </div>

                    <!-- 粉丝标签页（占位） -->
                    <div id="fans-tab" class="tab-content" style="display:none">
                        <ul id="fansList" class="fans-list"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ---------- 全局变量 ----------
const targetUserId = '<?= $targetId ?>';

// ---------- 用户卡片（与 med.php 相同） ----------
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

// ---------- 事件委托：头像/用户名/回复 ----------
document.addEventListener('click', function(e) {
    const avatar = e.target.closest('.user-avatar, #avatar');
    const name   = e.target.closest('.user-name, .name');
    const ruser  = e.target.closest('.RUser');
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
    // 回复（与 med.php 相同）
    const cb = e.target.closest('#notification_container');
    if (cb) {
        const nameEl = cb.querySelector('.name');
        const rid = cb.getAttribute('data-rid');
        if (nameEl && rid) {
            const ta = document.querySelector('dialog textarea, #comment-textarea');
            if (ta) {
                ta.value = '回复@' + nameEl.innerText.trim() + ': ';
                ta.focus();
            }
        }
    }
});

// ---------- 标签切换 ----------
function switchTab(tabName) {
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    const activeTab = document.querySelector(`.tab-item[data-tab="${tabName}"]`);
    if (activeTab) activeTab.classList.add('active');
    const content = document.getElementById(tabName + '-tab');
    if (content) content.style.display = 'block';

    if (tabName === 'comments') loadUserComments();
    if (tabName === 'fans') loadFans();
}

document.querySelectorAll('.tab-item').forEach(tab => {
    tab.addEventListener('click', function() { switchTab(this.dataset.tab); });
});

// 加载留言（复用 /comment/?category=User...）
function loadUserComments() {
    const container = document.getElementById('comments');
    container.innerHTML = '加载中...';
    fetch('/comment/?category=User&id=' + targetUserId)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(() => {
            container.innerHTML = '<div class="error">加载失败</div>';
        });
}

/**
 * 加载粉丝列表（调用 Users/GetRelations 接口，DisplayType=1 代表粉丝）
 * 需要页面中存在 id="fansList" 的容器元素，以及全局变量 targetUserId
 */
function loadFans() {
    const list = document.getElementById('fansList');
    if (!list) return;
    list.innerHTML = '<div class="empty-state">加载中...</div>';

    fetch('/api/get_relations.php?id=' + targetUserId + '&displayType=1&skip=0&take=50')
        .then(res => res.json())
        .then(data => {
            list.innerHTML = '';
            if (!data.Success || !data.Data) {
                list.innerHTML = '<div class="empty-state">加载失败</div>';
                return;
            }
            const users = Array.isArray(data.Data) ? data.Data : (data.Data.Users || []);
            if (users.length === 0) {
                list.innerHTML = '<div class="empty-state">暂无粉丝</div>';
                return;
            }
            users.forEach(user => {
                const uid = user.ID;
                const avatarUrl = (user.Avatar > 0 && uid)
                    ? `http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/${uid.substring(0,4)}/${uid.substring(4,6)}/${uid.substring(6,8)}/${uid.substring(8,16)}/${user.Avatar}.jpg!full`
                    : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iMjAiIGZpbGw9IiNFMEUwRTAiLz4KPHBhdGggZD0iTTIwIDI1QzIzLjMxNCAyNSAyNiAyMi4zMTQgMjYgMTlDMjYgMTUuNjg2IDIzLjMxNCAxMyAyMCAxM0MxNi42ODYgMTMgMTQgMTUuNjg2IDE0IDE5QzE0IDIyLjMxNCAxNi42ODYgMjUgMjAgMjVaIiBmaWxsPSIjQkRCREJEIi8+CjxwYXRoIGQ9Ik0yOSAzMEgxMUMxMC40NDc3IDMwIDEwIDI5LjU1MjMgMTAgMjlWMjRDMTAgMjMuNDQ3NyAxMC40NDc3IDIzIDExIDIzSDI5QzI5LjU1MjMgMjMgMzAgMjMuNDQ3NyAzMCAyNFYyOU MyMCAyOS41NTIzIDI5LjU1MjMgMzAgMjkgMzBaTTExIDI0VjI5SDI5VjI0SDExWiIgZmlsbD0iI0JEQkRCRCIvPgo8L3N2Zz4=';
                const li = document.createElement('li');
                li.className = 'fans-item';
                li.style.cssText = 'display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f0f0f0;cursor:pointer';
                li.innerHTML = `
                    <img class="fans-avatar" src="${avatarUrl}" onerror="this.onerror=null;this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iMjAiIGZpbGw9IiNFMEUwRTAiLz4KPHBhdGggZD0iTTIwIDI1QzIzLjMxNCAyNSAyNiAyMi4zMTQgMjYgMTlDMjYgMTUuNjg2IDIzLjMxNCAxMyAyMCAxM0MxNi42ODYgMTMgMTQgMTUuNjg2IDE0IDE5QzE0IDIyLjMxNCAxNi42ODYgMjUgMjAgMjVaIiBmaWxsPSIjQkRCREJEIi8+CjxwYXRoIGQ9Ik0yOSAzMEgxMUMxMC40NDc3IDMwIDEwIDI5LjU1MjMgMTAgMjlWMjRDMTAgMjMuNDQ3NyAxMC40NDc3IDIzIDExIDIzSDI5QzI5LjU1MjMgMjMgMzAgMjMuNDQ3NyAzMCAyNFYyOU MyMCAyOS41NTIzIDI5LjU1MjMgMzAgMjkgMzBaTTExIDI0VjI5SDI5VjI0SDExWiIgZmlsbD0iI0JEQkRCRCIvPgo8L3N2Zz4=';" style="width:40px;height:40px;border-radius:50%;margin-right:10px;flex-shrink:0">
                    <span style="font-weight:500;">${escapeHtml(user.Nickname || '匿名')}</span>
                `;
                li.addEventListener('click', function() {
                    location.href = 'user.php?id=' + uid;
                });
                list.appendChild(li);
            });
        })
        .catch(() => {
            list.innerHTML = '<div class="empty-state">网络错误</div>';
        });
}

// 关注/取消关注
var isFollowed = <?= $isFollowed ? 'true' : 'false' ?>;
function toggleFollow() {
    const action = isFollowed ? 0 : 1;  // 0 取消关注，1 关注
    fetch('/api/follow.php?id=' + targetUserId + '&action=' + action, { method: 'POST' })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.Success) {
                isFollowed = !isFollowed;
                var btn = document.getElementById('followBtn');
                btn.className = 'btn-follow' + (isFollowed ? ' following' : '');
                btn.innerHTML = isFollowed ?
                    '<i class="fas fa-check"></i> <span>已关注</span>' :
                    '<i class="fas fa-plus"></i> <span>关注</span>';

                // 更新粉丝数（简单 +1 / -1）
                var fansNum = document.querySelector('.stat-item:nth-child(2) .stat-num');
                if (fansNum) {
                    var n = parseInt(fansNum.innerText, 10) || 0;
                    fansNum.innerText = n + (isFollowed ? 1 : -1);
                }
            } else {
                alert('操作失败');
            }
        })
        .catch(function() {
            alert('网络错误');
        });
}

function shareProfile() {
    if (navigator.share) navigator.share({ title: document.title, url: location.href }).catch(()=>{});
    else prompt('复制链接分享:', location.href);
}
function toggleMenu() { alert('菜单功能即将开放'); }

function buildAvatarUrl(uid, av) {
    if (!av || !uid) return '<?= DEFAULT_AVATAR ?>';
    return 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/' + uid.substring(0,4) + '/' + uid.substring(4,6) + '/' + uid.substring(6,8) + '/' + uid.substring(8,16) + '/' + av + '.jpg!full';
}
function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
}
</script>
</body>
</html>