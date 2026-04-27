<?php
session_start();

// 获取参数
$targetId = $_GET['id'] ?? '';
$targetName = $_GET['name'] ?? '';

// 若未登录，重定向去获取 token
if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    $redirectUrl = '/getv.php?r=' . urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . $redirectUrl);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

// ---------- 后端 API 调用函数 ----------

/**
 * 获取用户基本信息与关系（调用 Users/GetUser）
 */
function getUserInfo(string $userId, string $token, string $authCode): ?array
{
    $url = 'http://nlm-api-cn.turtlesim.com/Users/GetUser';
    $body = json_encode(['ID' => $userId]);
    $headers = [
        'Content-Type: application/json',
        'x-API-Token: ' . $token,
        'x-API-AuthCode: ' . $authCode,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);

    $data = json_decode($bodyResp, true);
    return (json_last_error() === JSON_ERROR_NONE && isset($data['Data'])) ? $data['Data'] : null;
}

/**
 * 获取用户主页作品列表（调用 Contents/GetProfile）
 */
function getUserProfileProjects(string $userId, string $token, string $authCode): ?array
{
    $url = 'http://nlm-api-cn.turtlesim.com/Contents/GetProfile';
    $body = json_encode(['ID' => $userId]);
    $headers = [
        'Content-Type: application/json',
        'x-API-Token: ' . $token,
        'x-API-AuthCode: ' . $authCode,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);

    $data = json_decode($bodyResp, true);
    return (json_last_error() === JSON_ERROR_NONE && isset($data['Data'])) ? $data['Data'] : null;
}

// ---------- 获取数据 ----------

$userInfo = null;
$profile = null;

if (!empty($targetId)) {
    $userInfo = getUserInfo($targetId, $token, $authCode);
    $profile = getUserProfileProjects($targetId, $token, $authCode);
} elseif (!empty($targetName)) {
    // 通过昵称查找用户
    $url = 'http://nlm-api-cn.turtlesim.com/Users/GetUser';
    $body = json_encode(['Name' => $targetName]);
    $headers = [
        'Content-Type: application/json',
        'x-API-Token: ' . $token,
        'x-API-AuthCode: ' . $authCode,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);
    if ($data && isset($data['Data'])) {
        $userInfo = $data['Data'];
        $targetId = $userInfo['User']['ID'] ?? '';
        if ($targetId) {
            $profile = getUserProfileProjects($targetId, $token, $authCode);
        }
    }
}

// 提取字段（依据 GetUser 实际响应）
$targetUser = $userInfo['User'] ?? null;
$statistic  = $userInfo['Statistic'] ?? null;

// 关注数、粉丝数来自 Statistic
$followingCount = $statistic['FollowingCount'] ?? 0;
$followerCount  = $statistic['FollowerCount'] ?? 0;

// 关系（Relation 为 int，1 表示已关注）
$isFollowed = ($userInfo['Relation'] ?? 0) == 1;

// 封面作品（来自 Statistic.Cover）
$coverSummary = $statistic['Cover'] ?? null;

// 作品分组（Profile 接口返回的 Experiments 字典）
$experiments = $profile['Experiments'] ?? [];

// 模块名称映射
$moduleOrder = [
    'Featured-Models' => '精选实验',
    'Featured-Discussions' => '精选讨论',
    'Featured-Experiments' => '精选实验',
    'Popular-Models' => '热门实验',
    'Popular-Discussions' => '热门讨论',
    'Popular-Experiments' => '热门实验',
    'Latest-Models' => '最新实验',
    'Latest-Discussions' => '最新讨论',
    'Latest-Experiments' => '最新实验',
];

// 头像 URL
function buildAvatarUrl($userId, $avatarId) {
    if ($avatarId > 0) {
        $base = 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/';
        return $base . substr($userId,0,4) . '/' . substr($userId,4,2) . '/' . substr($userId,6,2) . '/' . substr($userId,8,16) . '/' . $avatarId . '.jpg!full';
    }
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iNDAiIGZpbGw9IiNFMEUwRTAiLz4KPHBhdGggZD0iTTQwIDUwQzQ1LjUyIDUwIDUwIDQ1LjUyIDUwIDQwQzUwIDM0LjQ4IDQ1LjUyIDMwIDQwIDMwQzM0LjQ4IDMwIDMwIDM0LjQ4IDMwIDQwQzMwIDQ1LjUyIDM0LjQ4IDUwIDQwIDUwWiIgZmlsbD0iI0JEQkRCRCIvPgo8cGF0aCBkPSJNNTIgNjBIMjhDMjcuNDQ3NyA2MCAyNyA1OS41NTIzIDI3IDU5VjQ5QzI3IDQ4LjQ0NzcgMjcuNDQ3NyA0OCAyOCA0OEg1MkM1Mi41NTIzIDQ4IDUzIDQ4LjQ0NzcgNTMgNDlWNTlDNTMgNTkuNTUyMyA1Mi41NTIzIDYwIDUyIDYwWk0yOCA0OVY1OUg1MlY0OUgyOFoiIGZpbGw9IiNCREJEQkQiLz4KPC9zdmc+';
}

// 作品缩略图 URL
function buildThumbUrl($item) {
    if (($item['Image'] ?? 0) > 0) {
        $base = 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/';
        $id = $item['ID'];
        return $base . substr($id,0,4) . '/' . substr($id,4,2) . '/' . substr($id,6,2) . '/' . substr($id,8,16) . '/' . $item['Image'] . '.jpg!full';
    }
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNDUiIHZpZXdCb3g9IjAgMCA2MCA0NSIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjQ1IiBmaWxsPSIjRUNFQ0VDIi8+CjxwYXRoIGQ9Ik0zMCAyMk0zMiAxOEgzOEw0MCAyMkw0MiAxOEg0OEw0NSAyMkw0NyAxOEg1Mkw0NSAyNEgzNUwzMCAyMloiIGZpbGw9IiNCRUJFQkUiLz4KPC9zdmc+';
}

// 当前登录用户 ID (用于判断留言归属)
$currentUserId = $_SESSION['user_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= htmlspecialchars($targetUser['Nickname'] ?? '用户主页') ?> - Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel='stylesheet' href='../styles/main.css'/>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:v-sans,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;line-height:1.6;background:#f5f7fa;color:#333}
        .basic-layout{display:flex;height:100vh}
        .layout-left{flex:1;position:relative;background:#2c3e50;display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;padding:20px}
        .layout-right{flex:1;overflow:hidden}
        .cover-bg{position:absolute;top:0;left:0;width:100%;height:100%;background-size:cover;background-position:center;opacity:0.3}
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

        /* 留言区域样式 */
        .comment-list{padding:0;list-style:none}
        .comment-item{display:flex;padding:12px 0;border-bottom:1px solid #f0f0f0}
        .comment-avatar{width:40px;height:40px;border-radius:50%;margin-right:10px;flex-shrink:0}
        .comment-body{flex:1}
        .comment-header{display:flex;justify-content:space-between;margin-bottom:4px}
        .comment-author{font-weight:500;color:#2080f0}
        .comment-time{font-size:12px;color:#999}
        .comment-content{word-break:break-word}
        .comment-post-box{display:flex;gap:8px;margin-top:20px}
        .comment-textarea{flex:1;padding:8px;border-radius:8px;border:1px solid #ddd;resize:none;font-size:14px}
        .comment-submit{padding:8px 16px;background:#2080f0;color:white;border:none;border-radius:8px;cursor:pointer;white-space:nowrap}

        /* 粉丝列表样式 */
        .fans-list{list-style:none}
        .fans-item{display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f0f0f0;cursor:pointer}
        .fans-avatar{width:40px;height:40px;border-radius:50%;margin-right:10px;flex-shrink:0}
        .fans-nickname{font-weight:500}

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
            <!-- 左侧用户信息区 -->
            <div class="layout-left">
                <div class="cover-bg" style="background-image:url('');"></div>
                <div class="top-bar">
                    <button class="icon-btn" onclick="window.history.back()"><i class="fas fa-arrow-left"></i></button>
                    <div style="display:flex;gap:8px">
                        <button class="icon-btn" onclick="shareProfile()"><i class="fas fa-share-alt"></i></button>
                        <button class="icon-btn" onclick="toggleMenu()"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                </div>
                <div class="user-info-box">
                    <img class="user-avatar-large" src="<?= buildAvatarUrl($targetUser['ID'] ?? '', $targetUser['Avatar'] ?? 0) ?>" alt="头像">
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
                        <button class="btn-message" onclick="switchTab('comments')">
                            <i class="far fa-comment"></i> 留言
                        </button>
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
                            <ul id="commentList" class="comment-list"></ul>
                            <div class="comment-post-box">
                                <textarea id="commentInput" class="comment-textarea" rows="2" placeholder="写下你的留言..."></textarea>
                                <button class="comment-submit" onclick="postComment()">发送</button>
                            </div>
                        </div>

                        <!-- 粉丝标签页 -->
                        <div id="fans-tab" class="tab-content" style="display:none">
                            <ul id="fansList" class="fans-list"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 标签切换
        function switchTab(tabName) {
            document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            const activeTab = document.querySelector(`.tab-item[data-tab="${tabName}"]`);
            if (activeTab) activeTab.classList.add('active');
            const content = document.getElementById(tabName + '-tab');
            if (content) content.style.display = 'block';

            // 动态加载内容
            if (tabName === 'comments') loadComments();
            if (tabName === 'fans') loadFans();
        }

        // 初始标签点击事件
        document.querySelectorAll('.tab-item').forEach(tab => {
            tab.addEventListener('click', function() {
                switchTab(this.dataset.tab);
            });
        });

        // ---------- 留言功能 ----------
        function loadComments() {
            fetch('/api/get_comments.php?id=<?= $targetId ?>&type=User&skip=0&take=20')
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('commentList');
                    list.innerHTML = '';
                    if (data.Success && data.Data && data.Data.Comments) {
                        data.Data.Comments.forEach(comment => {
                            const author = comment.User?.Nickname || '匿名';
                            const avatarId = comment.User?.Avatar || 0;
                            const uid = comment.User?.ID || '';
                            const avatarUrl = uid ? 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/' + uid.substring(0,4)+'/'+uid.substring(4,6)+'/'+uid.substring(6,8)+'/'+uid.substring(8,16)+'/'+avatarId+'.jpg!full' : 'data:image/svg+xml,...';
                            const content = comment.Content;
                            const time = new Date(comment.Created).toLocaleString();
                            const li = document.createElement('li');
                            li.className = 'comment-item';
                            li.innerHTML = `
                                <img class="comment-avatar" src="${avatarUrl}" onerror="this.src='data:image/svg+xml,...'">
                                <div class="comment-body">
                                    <div class="comment-header">
                                        <span class="comment-author" onclick="location.href='user.php?id=${uid}'">${author}</span>
                                        <span class="comment-time">${time}</span>
                                    </div>
                                    <div class="comment-content">${escapeHtml(content)}</div>
                                </div>
                            `;
                            list.appendChild(li);
                        });
                        if (data.Data.Comments.length === 0) {
                            list.innerHTML = '<div class="empty-state">暂无留言</div>';
                        }
                    } else {
                        list.innerHTML = '<div class="empty-state">加载失败</div>';
                    }
                })
                .catch(e => {
                    document.getElementById('commentList').innerHTML = '<div class="empty-state">网络错误</div>';
                });
        }

        function postComment() {
            const content = document.getElementById('commentInput').value.trim();
            if (!content) return alert('请输入内容');
            fetch('/api/post_comment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    TargetID: '<?= $targetId ?>',
                    TargetType: 'User',
                    Content: content
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.Success) {
                    document.getElementById('commentInput').value = '';
                    loadComments();
                    // 更新留言计数（简单演示）
                } else {
                    alert('发送失败: ' + (data.Message || ''));
                }
            });
        }

        // HTML转义
        function escapeHtml(text) {
            return String(text).replace(/[&<>"']/g, function(m) {
                return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m];
            });
        }

        // ---------- 粉丝列表 ----------
        function loadFans() {
            // DisplayType: 1 = Follower, 2 = Following (根据实际定义调整)
            fetch('/api/get_relations.php?id=<?= $targetId ?>&displayType=1&skip=0&take=20')
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('fansList');
                    list.innerHTML = '';
                    if (data.Success && data.Data) {
                        const users = data.Data; // 直接返回用户数组
                        if (users.length === 0) {
                            list.innerHTML = '<div class="empty-state">暂无粉丝</div>';
                            return;
                        }
                        users.forEach(user => {
                            const uid = user.ID;
                            const avatarUrl = user.Avatar > 0 ? 'http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/' + uid.substring(0,4)+'/'+uid.substring(4,6)+'/'+uid.substring(6,8)+'/'+uid.substring(8,16)+'/'+user.Avatar+'.jpg!full' : 'data:image/svg+xml,...';
                            const li = document.createElement('li');
                            li.className = 'fans-item';
                            li.innerHTML = `
                                <img class="fans-avatar" src="${avatarUrl}" onerror="this.src='data:image/svg+xml,...'">
                                <span class="fans-nickname" onclick="location.href='user.php?id=${uid}'">${escapeHtml(user.Nickname || '匿名')}</span>
                            `;
                            list.appendChild(li);
                        });
                    } else {
                        list.innerHTML = '<div class="empty-state">加载失败</div>';
                    }
                })
                .catch(e => {
                    document.getElementById('fansList').innerHTML = '<div class="empty-state">网络错误</div>';
                });
        }

        // ---------- 关注/取消关注（不变） ----------
        var isFollowed = <?= $isFollowed ? 'true' : 'false' ?>;
        function toggleFollow() {
            const action = isFollowed ? 0 : 1;
            fetch('/api/follow.php?id=<?= $targetId ?>&action=' + action, { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.Success) {
                        isFollowed = !isFollowed;
                        const btn = document.getElementById('followBtn');
                        btn.className = 'btn-follow' + (isFollowed ? ' following' : '');
                        btn.innerHTML = isFollowed ? '<i class="fas fa-check"></i> <span>已关注</span>' : '<i class="fas fa-plus"></i> <span>关注</span>';
                        // 更新粉丝数
                        const fansNum = document.querySelector('.stat-item:nth-child(2) .stat-num');
                        if (fansNum) {
                            let n = parseInt(fansNum.innerText) || 0;
                            fansNum.innerText = n + (isFollowed ? 1 : -1);
                        }
                    } else {
                        alert('操作失败');
                    }
                });
        }

        function sendMessage() { switchTab('comments'); }
        function shareProfile() {
            if (navigator.share) {
                navigator.share({ title: '<?= htmlspecialchars($targetUser['Nickname'] ?? '用户主页') ?>', url: window.location.href }).catch(()=>{});
            } else {
                prompt('复制链接分享:', window.location.href);
            }
        }
        function toggleMenu() { alert('菜单功能（举报等）即将开放'); }
    </script>
</body>
</html>