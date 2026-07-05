<?php
session_start();
require_once 'getv.php';  // 确保 token/authCode 已设置

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
    <meta name="referrer" content="no-referrer">
    <style>
        /* 样式保持不变（与之前相同，为了简洁此处省略，实际使用请复制原样式） */
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f0f2f5;font-family:v-sans,system-ui,sans-serif;color:#333}
        .header{position:sticky;top:0;background:white;z-index:10;display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid #e0e0e0}
        .header img{height:24px;cursor:pointer}
        .header .title{flex:1;font-size:18px;font-weight:600;margin:0 16px;white-space:nowrap}
        .header select{padding:6px 12px;border-radius:8px;border:1px solid #ccc;font-size:14px;background:white}
        .container{padding:16px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
        @media (min-width:768px){.grid{grid-template-columns:repeat(4,1fr)}}
        .card, .skeleton-card{background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);cursor:pointer;transition:transform 0.1s}
        .card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.12)}
        .cover{width:100%;padding-top:60%;background-size:cover;background-position:center;background-color:#e0e0e0}
        .skeleton-cover{width:100%;padding-top:60%;background:#e0e0e0;animation:pulse 1.5s infinite}
        .info{padding:10px}
        .title{font-size:14px;font-weight:500;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .author{display:flex;align-items:center;gap:8px}
        .author img{width:20px;height:20px;border-radius:50%;object-fit:cover}
        .skeleton-text{height:14px;background:#e0e0e0;border-radius:4px;margin-bottom:8px;animation:pulse 1.5s infinite}
        .skeleton-author{display:flex;align-items:center;gap:8px}
        .skeleton-avatar{width:20px;height:20px;border-radius:50%;background:#e0e0e0;animation:pulse 1.5s infinite}
        .skeleton-name{width:80px;height:12px;background:#e0e0e0;border-radius:4px;animation:pulse 1.5s infinite}
        @keyframes pulse{0%{opacity:0.6}50%{opacity:1}100%{opacity:0.6}}
        .load-trigger{height:20px;margin:20px 0;text-align:center}
        .load-trigger .loading-spinner{display:inline-block;width:24px;height:24px;border:2px solid #ccc;border-top-color:#2080f0;border-radius:50%;animation:spin 0.8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .empty{text-align:center;padding:60px 0;color:#999}
        .error{text-align:center;padding:60px 0;color:#e74c3c}
        .retry-btn{padding:8px 24px;background:#2080f0;color:white;border:none;border-radius:20px;cursor:pointer;margin-top:12px}
        .cache-toast{position:fixed;top:70px;left:16px;right:16px;background:#fff9e6;border-left:4px solid #ff9800;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;z-index:1000;backdrop-filter:blur(4px)}
        .cache-toast span{font-size:14px;color:#5d4a00}
        .cache-toast button{margin-left:12px;background:#ff9800;border:none;padding:4px 12px;border-radius:20px;color:white;cursor:pointer;font-size:12px}
        .cache-toast button:last-child{background:#ccc;color:#333}
        .cache-toast button:hover{opacity:0.8}
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
    <div id="loadTrigger" class="load-trigger" style="display: none;">
        <div class="loading-spinner"></div>
    </div>
    <div id="statusMsg" style="display: none;"></div>
</div>

<script>
    // ---------- 配置 ----------
    const TAKE = 20;
    const DEFAULT_THUMB = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="240" viewBox="0 0 400 240"><rect width="400" height="240" fill="#f0f0f0"/><rect x="100" y="60" width="200" height="120" rx="10" fill="#d0d0d0"/></svg>');
    const DEFAULT_AVATAR = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="40" fill="#f0f0f0"/><circle cx="40" cy="30" r="12" fill="#d0d0d0"/><path d="M25 56 Q40 48 55 56 Z" fill="#d0d0d0"/></svg>');
    
    let currentSkip = <?= json_encode((int)($_GET['skip'] ?? 0)) ?>;
    let lastId = null;
    let isLoading = false;
    let hasMore = true;
    let observer = null;
    let cacheEnabled = false;
    let cacheKey = location.pathname + location.search;
    let allItems = [];          // 存储所有已加载的原始数据（用于缓存）

    // ---------- 缓存权限管理 ----------
    function initCachePermission() {
        const saved = localStorage.getItem('cache_permission');
        if (saved === 'true') {
            cacheEnabled = true;
            return true;
        } else if (saved === 'false') {
            cacheEnabled = false;
            return false;
        } else {
            showCachePrompt();
            return false;
        }
    }

    function showCachePrompt() {
        const toast = document.createElement('div');
        toast.className = 'cache-toast';
        toast.innerHTML = `
            <span>🔐 允许使用 sessionStorage 缓存数据，可提升下次访问速度？</span>
            <div>
                <button id="cacheAllow">允许</button>
                <button id="cacheDeny">拒绝</button>
            </div>
        `;
        document.body.appendChild(toast);
        document.getElementById('cacheAllow').onclick = () => {
            localStorage.setItem('cache_permission', 'true');
            cacheEnabled = true;
            toast.remove();
            // 如果已有数据，立即缓存
            if (allItems.length) {
                saveToCache();
            }
        };
        document.getElementById('cacheDeny').onclick = () => {
            localStorage.setItem('cache_permission', 'false');
            cacheEnabled = false;
            toast.remove();
        };
    }

    // 保存当前所有数据到缓存
    function saveToCache() {
        if (!cacheEnabled) return;
        const cacheData = {
            items: allItems,           // 全部原始数据
            nextSkip: currentSkip,
            lastId: lastId,
            hasMore: hasMore
        };
        sessionStorage.setItem(cacheKey, JSON.stringify(cacheData));
    }

    // 从缓存恢复
    function tryLoadFromCache() {
        if (!cacheEnabled) return false;
        const cached = sessionStorage.getItem(cacheKey);
        if (!cached) return false;
        try {
            const data = JSON.parse(cached);
            if (data.items && Array.isArray(data.items) && data.items.length) {
                allItems = data.items;
                currentSkip = data.nextSkip;
                lastId = data.lastId;
                hasMore = data.hasMore;
                // 渲染全部卡片
                const grid = document.getElementById('cardGrid');
                grid.innerHTML = allItems.map(buildCard).join('');
                if (hasMore) {
                    document.getElementById('loadTrigger').style.display = 'block';
                    initInfiniteScroll();
                } else {
                    document.getElementById('loadTrigger').style.display = 'none';
                }
                document.getElementById('statusMsg').style.display = 'none';
                return true;
            }
        } catch(e) {}
        return false;
    }

    // ---------- 辅助函数 ----------
    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
    }

    function buildCard(exp) {
        const id = exp.ID;
        const category = exp.Category;
        const title = exp.LocalizedSubject?.Chinese || exp.Subject || '未命名';
        const nick = exp.User?.Nickname || '匿名';

        let coverUrl = DEFAULT_THUMB;
        if (id && (exp.Image >= 0)) {
            const [p1,p2,p3,p4] = [id.substring(0,4), id.substring(4,6), id.substring(6,8), id.substring(8,24)];
            coverUrl = `https://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/${p1}/${p2}/${p3}/${p4}/${exp.Image}.jpg!full`;
        }

        let avatarUrl = DEFAULT_AVATAR;
        if (exp.User?.ID && exp.User?.Avatar) {
            const uid = exp.User.ID;
            const [a1,a2,a3,a4] = [uid.substring(0,4), uid.substring(4,6), uid.substring(6,8), uid.substring(8,24)];
            avatarUrl = `https://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/${a1}/${a2}/${a3}/${a4}/${exp.User.Avatar}.jpg!full`;
        }

        return `
            <div class="card" data-id="${id}" onclick="location.href='med.php?id=${id}&category=${category}'">
                <div class="cover" style="background-image:url('${coverUrl}')"></div>
                <div class="info">
                    <div class="title">${escHtml(title)}</div>
                    <div class="author">
                        <img src="${avatarUrl}" loading="lazy" onerror="this.onerror=null;this.src='${DEFAULT_AVATAR}';">
                        <span>${escHtml(nick)}</span>
                    </div>
                </div>
            </div>`;
    }

    function renderSkeleton(count) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
                <div class="skeleton-card">
                    <div class="skeleton-cover"></div>
                    <div class="info">
                        <div class="skeleton-text" style="width: 80%"></div>
                        <div class="skeleton-author">
                            <div class="skeleton-avatar"></div>
                            <div class="skeleton-name"></div>
                        </div>
                    </div>
                </div>`;
        }
        document.getElementById('cardGrid').innerHTML = html;
    }

    function clearSkeleton() {
        const grid = document.getElementById('cardGrid');
        if (grid.children.length && grid.children[0].classList.contains('skeleton-card')) {
            grid.innerHTML = '';
        }
    }

    async function loadData(append = false) {
        if (isLoading) return;
        if (!append) {
            renderSkeleton(TAKE);
        } else {
            if (!hasMore) return;
            document.getElementById('loadTrigger').style.display = 'block';
        }

        isLoading = true;

        const params = new URLSearchParams(location.search);
        params.set('skip', currentSkip);
        if (append && lastId) {
            params.set('from', lastId);
        } else {
            params.delete('from');
        }
        params.set('take', TAKE);

        try {
            const res = await fetch('/api/query.php?' + params.toString(), { credentials: 'include' });
            const isJson = (res.headers.get('content-type') || '').includes('application/json');
            let json;
            if (isJson) {
                json = await res.json();
            } else {
                const text = await res.text();
                throw new Error(`非 JSON 响应 (${res.status}): ${text.substring(0, 100)}`);
            }

            if (!res.ok) throw new Error(json.error || `HTTP ${res.status}`);

            const items = json.Data;
            if (!Array.isArray(items)) throw new Error('Data 不是数组');

            const grid = document.getElementById('cardGrid');
            const statusDiv = document.getElementById('statusMsg');
            const loadTrigger = document.getElementById('loadTrigger');

            if (!append) {
                clearSkeleton();
                grid.innerHTML = '';
                allItems = [];          // 重置缓存数组
            }

            if (items.length === 0 && !append) {
                statusDiv.style.display = 'block';
                statusDiv.innerHTML = '<div class="empty">暂无作品</div>';
                loadTrigger.style.display = 'none';
                hasMore = false;
                return;
            }

            statusDiv.style.display = 'none';
            grid.insertAdjacentHTML('beforeend', items.map(buildCard).join(''));

            // 合并到 allItems
            allItems.push(...items);

            if (items.length > 0) {
                lastId = items[items.length - 1].ID;
            }
            currentSkip += items.length;

            if (items.length < TAKE) {
                hasMore = false;
                loadTrigger.style.display = 'none';
            } else {
                hasMore = true;
                loadTrigger.style.display = 'block';
            }

            // 每次加载后更新缓存（如果允许）
            if (cacheEnabled) {
                saveToCache();
            }
        } catch (err) {
            console.error(err);
            const statusDiv = document.getElementById('statusMsg');
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = `<div class="error">加载失败：${err.message}<br><button class="retry-btn" onclick="location.reload()">重试</button></div>`;
            document.getElementById('loadTrigger').style.display = 'none';
        } finally {
            isLoading = false;
            if (append) {
                document.getElementById('loadTrigger').style.display = hasMore ? 'block' : 'none';
            }
        }
    }

    function initInfiniteScroll() {
        const trigger = document.getElementById('loadTrigger');
        if (!trigger) return;
        observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && hasMore && !isLoading) {
                loadData(true);
            }
        }, { threshold: 0.1, rootMargin: '0px 0px 200px 0px' });
        observer.observe(trigger);
    }

    function changeSort() {
        const url = new URL(location.href);
        url.searchParams.set('sort', document.getElementById('sortSelect').value);
        url.searchParams.set('skip', 0);
        location.href = url.toString();
    }

    // ---------- 初始化 ----------
    (function init() {
        const cacheAllowed = initCachePermission();
        if (cacheAllowed) {
            if (tryLoadFromCache()) {
                return; // 成功从缓存恢复
            }
        }
        loadData(false).then(() => {
            if (hasMore) initInfiniteScroll();
        });
    })();
</script>

</body>
</html>