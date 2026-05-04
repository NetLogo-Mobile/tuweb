<?php
session_start();

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    header('Location: /getv.php?r=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$category = $_GET['category'] ?? 'Model';
$sort     = (int)($_GET['sort'] ?? 0);
$tags     = $_GET['tags'] ?? '';
$userId   = $_GET['userId'] ?? '';

$sortNames = [0 => '最新', 1 => '热门', 2 => '史上热门'];
$sortLabel = $sortNames[$sort] ?? '最新';
$catNames  = ['Model' => '模型', 'Discussion' => '讨论', 'Experiment' => '实验'];
$catLabel  = $catNames[$category] ?? $category;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sortLabel . $catLabel) ?> - Turtle Universe Web</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f0f2f5;font-family:v-sans,system-ui,sans-serif;color:#333}
        .header{position:sticky;top:0;background:white;z-index:10;display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid #e0e0e0}
        .header img{height:24px;cursor:pointer}
        .header .title{flex:1;font-size:18px;font-weight:600;margin:0 16px;white-space:nowrap}
        .header select{padding:6px 12px;border-radius:8px;border:1px solid #ccc;font-size:14px;background:white}
        .container{padding:16px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
        @media (min-width:768px){.grid{grid-template-columns:repeat(4,1fr)}}
        .card{background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);cursor:pointer;transition:transform 0.1s}
        .card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.12)}
        .card .cover{width:100%;padding-top:60%;background-size:cover;background-position:center;background-color:#e0e0e0}
        .card .info{padding:10px}
        .card .title{font-size:14px;font-weight:500;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .card .author{display:flex;align-items:center;gap:8px}
        .card .author img{width:20px;height:20px;border-radius:50%;object-fit:cover}
        .card .author span{font-size:12px;color:#666}
        .load-more{text-align:center;margin:20px 0}
        .load-more button{padding:10px 40px;background:#2080f0;color:white;border:none;border-radius:20px;cursor:pointer;font-size:14px}
        .load-more button:disabled{opacity:0.6}
        .empty{text-align:center;padding:60px 0;color:#999}
        .error{text-align:center;padding:60px 0;color:#e74c3c}
    </style>
</head>
<body>

<div class="header">
    <img src="imgs/return.png" onclick="history.back()" alt="返回">
    <div class="title"><?= htmlspecialchars($sortLabel . $catLabel) ?></div>
    <select id="sortSelect" onchange="changeSort()">
        <option value="0" <?= $sort==0?'selected':'' ?>>最新作品</option>
        <option value="1" <?= $sort==1?'selected':'' ?>>热门作品</option>
        <option value="2" <?= $sort==2?'selected':'' ?>>史上热门作品</option>
    </select>
</div>

<div class="container">
    <div class="grid" id="cardGrid"></div>
    <div class="load-more" id="loadMoreSection" style="display:none">
        <button id="loadMoreBtn" onclick="loadMore()">加载更多</button>
    </div>
    <div id="statusMsg" class="empty">加载中...</div>
</div>

<script>
    const DEFAULT_THUMB = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="240" viewBox="0 0 400 240"><rect width="400" height="240" fill="#f0f0f0"/><rect x="100" y="60" width="200" height="120" rx="10" fill="#d0d0d0"/></svg>');
    const DEFAULT_AVATAR = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="40" fill="#f0f0f0"/><circle cx="40" cy="30" r="12" fill="#d0d0d0"/><path d="M25 56 Q40 48 55 56 Z" fill="#d0d0d0"/></svg>');

    let currentSkip = <?= json_encode((int)($_GET['skip'] ?? 0)) ?>;
    let lastId = null;                // 用于 From 参数
    const take = 20;

    function buildCard(exp) {
        const id = exp.ID;
        const category = exp.Category;
        const title = exp.LocalizedSubject?.Chinese || exp.Subject || '未命名';
        const nick = exp.User?.Nickname || '匿名';

        let coverUrl = DEFAULT_THUMB;
        if (id) {
            const [p1,p2,p3,p4] = [id.substring(0,4), id.substring(4,6), id.substring(6,8), id.substring(8,24)];
            coverUrl = `http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/${p1}/${p2}/${p3}/${p4}/${exp.Image}.jpg!full`;
        }

        let avatarUrl = DEFAULT_AVATAR;
        if (exp.User?.ID && exp.User?.Avatar != null) {
            const uid = exp.User.ID;
            const [a1,a2,a3,a4] = [uid.substring(0,4), uid.substring(4,6), uid.substring(6,8), uid.substring(8,24)];
            avatarUrl = `http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/${a1}/${a2}/${a3}/${a4}/${exp.User.Avatar}.jpg!full`;
        }

        return `
            <div class="card" onclick="location.href='med.php?id=${id}&category=${category}'">
                <div class="cover" style="background-image:url('${coverUrl}')"></div>
                <div class="info">
                    <div class="title">${escHtml(title)}</div>
                    <div class="author">
                        <img src="${avatarUrl}" onerror="this.onerror=null;this.src='${DEFAULT_AVATAR}';">
                        <span>${escHtml(nick)}</span>
                    </div>
                </div>
            </div>`;
    }

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
    }

    async function loadData(skip, append = false) {
        const params = new URLSearchParams(location.search);
        params.set('skip', skip);
        if (append && lastId) {
            params.set('from', lastId);
        } else {
            params.delete('from');
        }
        params.set('take', take);

        try {
            const res = await fetch('/api/query.php?' + params.toString(), { credentials: 'include' });
            const isJson = (res.headers.get('content-type')||'').includes('application/json');
            let json;
            if (isJson) {
                json = await res.json();
            } else {
                const text = await res.text();
                throw new Error(`非 JSON 响应 (${res.status}): ${text}`);
            }

            if (!res.ok) throw new Error(json.error || `HTTP ${res.status}`);

            const items = json.Data;
            if (!Array.isArray(items)) throw new Error('Data 不是数组');

            const grid = document.getElementById('cardGrid');
            const msg = document.getElementById('statusMsg');
            const loadMoreSection = document.getElementById('loadMoreSection');

            if (!append) grid.innerHTML = '';

            if (items.length === 0 && !append) {
                msg.style.display = 'block';
                msg.innerHTML = '<div class="empty">暂无作品</div>';
                loadMoreSection.style.display = 'none';
                return;
            }

            msg.style.display = 'none';
            grid.insertAdjacentHTML('beforeend', items.map(buildCard).join(''));

            // 更新游标和 offset
            if (items.length > 0) {
                lastId = items[items.length - 1].ID;
            }
            currentSkip = skip + items.length;   // 保持 skip 同步

            if (items.length === take) {
                loadMoreSection.style.display = 'block';
                const btn = document.getElementById('loadMoreBtn');
                btn.textContent = '加载更多';
                btn.disabled = false;
            } else {
                loadMoreSection.style.display = 'none';
            }
        } catch (err) {
            document.getElementById('statusMsg').innerHTML = `<div class="error">加载失败：${err.message}</div>`;
        }
    }

    function loadMore() {
        const btn = document.getElementById('loadMoreBtn');
        btn.textContent = '加载中...';
        btn.disabled = true;
        loadData(currentSkip, true);
    }

    function changeSort() {
        const url = new URL(location.href);
        url.searchParams.set('sort', document.getElementById('sortSelect').value);
        url.searchParams.set('skip', 0);
        location.href = url.toString();
    }

    // 首次加载
    loadData(currentSkip, false);
</script>

</body>
</html>