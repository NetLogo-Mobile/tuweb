<?php
session_start();
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="海龟实验室网页版">
    <meta name="keywords" content="海实,海龟实验室,网页版,Turtle Universe,Turtle Universe Web,tuweb">
    <link rel="preconnect" href="https://netlogo-cn.oss-cn-hongkong.aliyuncs.com" crossorigin>
    <meta name="referrer" content="no-referrer">
    <title>海龟实验室网页版 - Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="tuweb">
    <link rel="apple-touch-icon" href="imgs/icon.png">
    <link rel="stylesheet" href="styles/library.css">
    <style>
        .item-img { width:60px; height:60px; object-fit:cover; background-color:#f0f0f0; }
        .placeholder-text { color:#aaa; }
        .attendance-modal-overlay {
            position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.6); z-index:9999;
            display:flex; justify-content:center; align-items:center;
            animation:fadeIn 0.2s ease;
        }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
        .attendance-modal {
            background:white; border-radius:16px; width:90%; max-width:700px;
            max-height:85vh; display:flex; overflow:hidden; position:relative;
            animation:slideIn 0.2s ease;
        }
        @keyframes slideIn { from{transform:translateY(20px); opacity:0;} to{transform:translateY(0); opacity:1;} }
        .modal-close-btn {
            position:absolute; top:12px; right:15px; font-size:26px;
            cursor:pointer; color:#999; z-index:10; line-height:1;
        }
        .modal-close-btn:hover { color:#333; }
        .modal-left-menu {
            width:180px; background:#f7f9fc; padding:20px 0; flex-shrink:0;
            border-right:1px solid #eaeaea; overflow-y:auto;
        }
        .modal-left-menu .menu-item {
            padding:15px 20px; cursor:pointer; color:#333; transition:background 0.2s;
        }
        .modal-left-menu .menu-item:hover { background:#eef2f6; }
        .modal-left-menu .menu-item.active { background:#2196f3; color:white; }
        .modal-left-menu .menu-item .sub { display:block; font-size:12px; opacity:0.8; margin-top:4px; }
        .modal-right-content { flex:1; padding:30px 25px; overflow-y:auto; }
        .attendance-grid {
            display:grid; grid-template-columns:repeat(4, 1fr);
            gap:12px; margin-top:20px;
        }
        .attendance-item {
            background:#fdfdfd; border:1px solid #eee; border-radius:10px;
            padding:12px 8px; text-align:center; position:relative;
            min-height:120px; display:flex; flex-direction:column;
            justify-content:center; align-items:center; cursor:pointer;
        }
        .attendance-item .day-header { font-size:12px; color:#666; margin-bottom:8px; }
        .attendance-item .reward-icon { font-size:26px; margin:5px 0; pointer-events:none; }
        .attendance-item .reward-value { font-weight:bold; font-size:16px; color:#333; pointer-events:none; }
        .attendance-item .reward-label { font-size:12px; color:#666; pointer-events:none; }
        .attendance-item.claimed { background:#f0f0f0; border-color:#ddd; cursor:default; }
        .attendance-item.claimed .claimed-overlay {
            position:absolute; top:0; left:0; width:100%; height:100%;
            background:rgba(180,180,180,0.5);
            display:flex; justify-content:center; align-items:center;
            font-weight:bold; color:#333; font-size:14px; border-radius:10px; pointer-events:none;
        }
        .attendance-item.today { border:2px solid #2196f3; background:#ffffff; }
        .attendance-item.today .day-header { color:#2196f3; font-weight:bold; }
        .attendance-item.today:hover { background:#f0f7ff; }
        @media (max-width:600px) {
            .attendance-modal { flex-direction:column; max-height:90vh; width:95%; }
            .modal-left-menu { width:100%; display:flex; flex-direction:row; padding:10px 0; border-right:none; border-bottom:1px solid #eaeaea; }
            .modal-left-menu .menu-item { flex:1; text-align:center; padding:10px; }
            .attendance-grid { grid-template-columns:repeat(2, 1fr); }
        }
.daily-activity-card {
    background: linear-gradient(135deg, #a18cd1, #fbc2eb);
    color: #333;
    border-radius: 12px;
    padding: 16px 20px;
    margin-top: 20px;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    transition: transform .2s
}
    </style>
</head>
<body>
<header class="header">
    <div class="user-info" id="user-info-area">
        <div class="avatar"><i class="fas fa-user"></i></div>
        <div class="user-details">
            <span class="nickname" id="user-nickname">点击登录</span>
            <span class="level" id="user-level">Level 1</span>
        </div>
    </div>
    <div class="resources">
        <span class="coin"><i class="fas fa-coins"></i> <span id="user-gold">0</span></span>
        <span class="diamond"><i class="fas fa-gem"></i> <span id="user-diamond">0</span></span>
    </div>
</header>
<main class="content">
    <div class="community-grid">
        <?php
        // 模块数据（硬编码示例，实际从 API 获取）
        $modules = [
            ['title' => '探索指南', 'count' => 3, 'featured' => true],
            ['title' => '精选实验', 'count' => 3, 'featured' => true],
            ['title' => '每日模型', 'count' => 4, 'featured' => false],
            ['title' => '热门实验', 'count' => 4, 'featured' => false],
            ['title' => '最新实验', 'count' => 4, 'featured' => false],
            ['title' => '可视化编程', 'count' => 4, 'featured' => false],
            ['title' => '实验知识库', 'count' => 4, 'featured' => false]
        ];
        foreach ($modules as $index => $module): $blockClass = $module['featured'] ? 'block featured-block' : 'block'; ?>
            <div class="<?= $blockClass ?>" data-module-index="<?= $index ?>">
                <div class="block-header"><?= $module['title'] ?></div>
                <div class="block-content" data-module-content="<?= $index ?>">
                    <?php for ($i=0; $i<$module['count']; $i++): ?>
                        <div class="item" data-item-index="<?= $i ?>">
                            <img class="item-img" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60' viewBox='0 0 60 60'%3E%3Crect width='60' height='60' fill='%23dddddd'/%3E%3C/svg%3E" alt="加载中">
                            <div class="item-details">
                                <div class="item-title placeholder-text">加载中...</div>
                                <div class="item-meta placeholder-text">加载中...</div>
                                <div class="item-tags"><span class="tag placeholder-text">加载中</span></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="more-button-container"><button class="more-button">查看更多</button></div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div class="footer">
    <div class="active"><i class="fas fa-home"></i><span>首页</span></div>
    <div onclick="location.href='me.php'"><i class="fas fa-user"></i><span>我的</span></div>
    <div onclick="location.href='discussion.php'"><i class="fas fa-water"></i><span>海水</span></div>
    <div onclick="location.href='model.php'"><i class="fas fa-cube"></i><span>模型库</span></div>
    <div onclick="location.href='nof.php'"><i class="fas fa-bell"></i><span>通知</span></div>
</div>

<?php flush(); include 'getv.php'; ?>
<?php
// 安全处理：抹除 Token 和 AuthCode
unset($dt['AuthCode']);
unset($dt['Data']['AuthCode']);
unset($dt['Token']);
unset($dt['Data']['Token']);
?>
<script>
var tuData = <?php echo json_encode($dt, JSON_UNESCAPED_UNICODE); ?>;

// ---------- 安全工具函数 ----------
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(id) { if(!id || id.length<8) return '未知'; var t = parseInt(id.substring(0,8),16); var d = new Date(t*1000); return (d.getMonth()+1)+'/'+d.getDate(); }
function getImageUrl(exp) { if(!exp) return ''; var id = exp.ID||'0000000000000000'; var img = (exp.Image != null) ? exp.Image : 'default'; var p1=id.substring(0,4)||'0000', p2=id.substring(4,6)||'00', p3=id.substring(6,8)||'00', p4=id.substring(8,24)||'0000000000000000'; return 'https://netlogo-cn.oss-cn-hongkong.aliyuncs.com/experiments/images/'+p1+'/'+p2+'/'+p3+'/'+p4+'/'+img+'.jpg!full'; }

function closeActivitiesModal() { var o = document.querySelector('.attendance-modal-overlay'); if(o) o.remove(); }

function openActivitiesModal() {
    fetch('/api/activity.php?action=sync', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ 
            UserID: tuData.Data.User.ID, 
            Statistic: tuData.Data.Statistic 
        })
    })
    .then(r => r.json())
    .then(apiData => {
        if(apiData.Status === 200 && apiData.Data && typeof apiData.Data.Statistic === 'object') {
            tuData.Data.Statistic = apiData.Data.Statistic;
        }
        buildModal();
    })
    .catch(() => { buildModal(); });
}

// ---------- 活动弹窗 ----------
function buildModal() {
    var all = tuData.Data.Activities || [];
    if(!all.length) return;
    var overlay = document.createElement('div'); overlay.className = 'attendance-modal-overlay';
    overlay.onclick = function(e){ if(e.target===this) closeActivitiesModal(); };
    var modal = document.createElement('div'); modal.className = 'attendance-modal';
    var left = document.createElement('div'); left.className = 'modal-left-menu';
    var right = document.createElement('div'); right.className = 'modal-right-content';
    var closeBtn = document.createElement('div'); closeBtn.className = 'modal-close-btn'; 
    closeBtn.textContent = '×';
    closeBtn.onclick = closeActivitiesModal;
    modal.appendChild(left); modal.appendChild(right); modal.appendChild(closeBtn);
    overlay.appendChild(modal); document.body.appendChild(overlay);

    // 渲染活动内容（完全使用 DOM API，无 innerHTML）
    function renderActivity(act) {
        // 清空右侧
        right.innerHTML = ''; // 这里仅清空，后续全部用 appendChild

        // 活动日期
        var dateLabel = document.createElement('h3');
        dateLabel.textContent = '活动日期';
        dateLabel.style.margin = '0 0 5px';
        var dateRange = document.createElement('p');
        dateRange.style.cssText = 'color:#666;font-size:14px;margin-bottom:15px;';
        dateRange.textContent = (act.StartDate||'').split('T')[0] + ' - ' + (act.FinishDate||'').split('T')[0];
        right.appendChild(dateLabel);
        right.appendChild(dateRange);

        // 活动说明
        var descLabel = document.createElement('h3');
        descLabel.textContent = '活动说明';
        descLabel.style.marginBottom = '5px';
        var descDiv = document.createElement('div');
        descDiv.style.cssText = 'color:#333;font-size:14px;line-height:1.6;margin-bottom:20px;max-height:200px;overflow-y:auto;padding-right:5px;';
        var descText = act.Contents && act.Contents[0] ? act.Contents[0].Chinese : '';
        // 安全转义并转换换行
        var lines = escapeHtml(descText).split('\n');
        lines.forEach(function(line, idx) {
            if (idx > 0) descDiv.appendChild(document.createElement('br'));
            descDiv.appendChild(document.createTextNode(line));
        });
        right.appendChild(descLabel);
        right.appendChild(descDiv);

        // 签到网格
        if (act.Items && act.Items.length>0 && act.Items[0].Condition === 'Attendance') {
            var stat = tuData.Data.Statistic || {};
            var cur = 0;
            if (stat && stat.Activities) {
                var targetAct = stat.Activities.find(function(a) { 
                    return a.ActivityID === act.ID; 
                });
                if (targetAct && targetAct.Counters) {
                    for(var i=0; i<targetAct.Counters.length; i++) {
                        if(targetAct.Counters[i] > 0) cur++;
                        else break;
                    }
                }
            }
            var grid = document.createElement('div');
            grid.className = 'attendance-grid';
            act.Items.forEach(function(item, idx) {
                var day = idx+1;
                var bonus = item.Bonuses;
                var type = Object.keys(bonus)[0];
                var val = bonus[type];
                var label = type==='Gold'?'积分':(type==='Experience'?'经验值':'钻石');
                var iconClass = type==='Gold'?'fa-coins':(type==='Experience'?'fa-star':'fa-gem');
                var color = type==='Gold'?'#f1c40f':(type==='Experience'?'#9b59b6':'#00bcd4');
                var isClaimed = idx < cur;
                var isToday = idx === cur;
                var itemDiv = document.createElement('div');
                itemDiv.className = 'attendance-item' + (isClaimed ? ' claimed' : '') + (isToday ? ' today' : '');
                itemDiv.dataset.aid = act.ID;
                itemDiv.dataset.idx = idx;
                itemDiv.dataset.claimed = isClaimed ? 'true' : 'false';
                itemDiv.dataset.today = isToday ? 'true' : 'false';

                // day header
                var dayHeader = document.createElement('div');
                dayHeader.className = 'day-header';
                dayHeader.textContent = '第' + day + '天';
                itemDiv.appendChild(dayHeader);

                // reward icon
                var iconWrap = document.createElement('div');
                iconWrap.className = 'reward-icon';
                var icon = document.createElement('i');
                icon.className = 'fas ' + iconClass;
                icon.style.color = color;
                iconWrap.appendChild(icon);
                itemDiv.appendChild(iconWrap);

                // value
                var valDiv = document.createElement('div');
                valDiv.className = 'reward-value';
                valDiv.textContent = val;
                itemDiv.appendChild(valDiv);

                // label
                var labelDiv = document.createElement('div');
                labelDiv.className = 'reward-label';
                labelDiv.textContent = label;
                itemDiv.appendChild(labelDiv);

                // claimed overlay
                if (isClaimed) {
                    var overlay = document.createElement('div');
                    overlay.className = 'claimed-overlay';
                    overlay.textContent = '已领取';
                    itemDiv.appendChild(overlay);
                }

                grid.appendChild(itemDiv);
            });
            right.appendChild(grid);

            // 点击事件委托到 right
            right.onclick = function(e) {
                var item = e.target.closest('.attendance-item');
                if (!item) return;
                if (item.dataset.claimed === 'true') return alert('已领取');
                if (item.dataset.today !== 'true') return alert('请先领取前一天的奖励');

                var statObj = JSON.parse(JSON.stringify(tuData.Data.Statistic));
                if (!statObj || typeof statObj !== 'object') {
                    statObj = { ID: tuData.Data.User.ID, Records: {} };
                }
                statObj.ID = tuData.Data.User.ID;

                fetch('/api/activity.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        ActivityID: item.dataset.aid,
                        Index: parseInt(item.dataset.idx),
                        Statistic: statObj
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if(data.Status === 200) {
                        tuData.Data.Statistic = data.Data.Statistic;
                        tuData.Data.User = data.Data.User;
                        updateHeader();
                        renderActivity(act);
                    } else {
                        alert('领取失败: '+(data.Message||'未知错误'));
                    }
                })
                .catch(() => alert('网络请求失败，请重试'));
            };
        }
    }

    // 左侧菜单
    all.forEach(function(act, idx){
        var name = act.Subject ? (act.Subject.Chinese||act.Subject.English||'活动') : '活动';
        var sub = act.Subject ? (act.Subject.English||'') : '';
        var div = document.createElement('div');
        div.className = 'menu-item' + (idx===0 ? ' active' : '');
        var nameSpan = document.createElement('span');
        nameSpan.textContent = name;
        var subSpan = document.createElement('span');
        subSpan.className = 'sub';
        subSpan.textContent = sub;
        div.appendChild(nameSpan);
        div.appendChild(subSpan);
        div.onclick = function(){
            left.querySelectorAll('.menu-item').forEach(el=>el.classList.remove('active'));
            div.classList.add('active');
            renderActivity(act);
        };
        left.appendChild(div);
    });
    if(all.length) renderActivity(all[0]);
}

// ---------- 解析 TargetLink 为 query.php 参数 ----------
function parseTargetLink(targetLink) {
    if (!targetLink) return null;
    // 匹配 scheme://path
    var match = targetLink.match(/^([a-zA-Z]+):\/\/(.*)$/);
    if (!match) {
        // 如果不是自定义协议，直接返回原值（可能是正常 URL）
        return targetLink;
    }
    var scheme = match[1];
    var path = match[2];

    // 显式映射 scheme -> category
    var categoryMap = {
        'models': 'Model',
        'experiments': 'Experiment',
        'discussions': 'Discussion'
    };
    var category = categoryMap[scheme] || 'Model'; // 默认 Model

    var params = { category: category };
    // 分割路径，过滤空段
    var segments = path.split('/').filter(function(s) { return s.length > 0; });
    for (var i = 0; i < segments.length - 1; i += 2) {
        var key = segments[i];
        var value = segments[i + 1];
        if (!key || !value) continue;
        // 参数名首字母小写
        var paramName = key.charAt(0).toLowerCase() + key.slice(1);
        // 特殊处理 sort
        if (paramName === 'sort') {
            if (value === 'Default') value = 0;
            else if (value === 'Popularity') value = 1;
            else value = parseInt(value) || 0;
        }
        params[paramName] = value;
    }
    // 构建 query 字符串
    var queryParts = [];
    for (var p in params) {
        queryParts.push(encodeURIComponent(p) + '=' + encodeURIComponent(params[p]));
    }
    return 'query.php?' + queryParts.join('&');
}

// ---------- 主页渲染 ----------
if(tuData && tuData.Data && tuData.Data.Library && tuData.Data.Library.Blocks) {
    var blocks = tuData.Data.Library.Blocks;
    var els = document.querySelectorAll('.block');

    blocks.forEach(function(block, i){
        var el = els[i];
        if(!el) return;

        var items = el.querySelectorAll('.item');
        var sums = block.Summaries || [];
        items.forEach(function(item) { item.style.display = 'none'; });

        sums.forEach(function(exp, j){
            if(j < items.length) {
                var item = items[j];
                var img = item.querySelector('.item-img');
                if(img) {
                    img.src = getImageUrl(exp);
                    img.onerror = function(){ this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0zMCAzNEMzMy4zMTM3IDM0IDM2IDMxLjMxMzcgMzYgMjhDMzYgMjQuNjg2MyAzMy4zMTM3IDIyIDMwIDIyQzI2LjY4NjMgMjIgMjQgMjQuNjg2MyAyNCAyOEMyNCAzMS4zMTM3IDI2LjY4NjMgMzQgMzAgMzRaIiBmaWxsPSIjQ0VDRUNFIi8+CjxwYXRoIGQ9Ik0zNiAzOEgyNEMyMi44OTU0IDM4IDIyIDM3LjEwNDYgMjIgMzZWMjRDMjIgMjIuODk1NCAyMi44OTU0IDIyIDI0IDIySDM2QzM3LjEwNDYgMjIgMzggMjIuODk1NCAzOCAyNFYzNkMzOCAzNy4xMDQ2IDM3LjEwNDYgMzggMzYgMzhaTTI0IDI0VjM2SDM2VjI0SDI0WiIgZmlsbD0iI0NFQ0VDRSIvPgo8L3N2Zz4K'; };
                }
                var title = item.querySelector('.item-title');
                if(title) {
                    var raw = exp.LocalizedSubject?.Chinese || exp.Subject || '未知';
                    title.textContent = raw;
                    title.classList.remove('placeholder-text');
                }
                var meta = item.querySelector('.item-meta');
                if(meta) {
                    meta.textContent = (exp.User?.Nickname||'未知') + ' - ' + formatDate(exp.ID);
                    meta.classList.remove('placeholder-text');
                }
                var tags = item.querySelector('.item-tags');
                if(tags) {
                    tags.innerHTML = ''; // 清空，但使用 DOM 重建
                    (exp.Tags||[]).slice(0,3).forEach(function(t){
                        var s=document.createElement('span');
                        s.className='tag';
                        s.textContent=t;
                        tags.appendChild(s);
                    });
                }
                item.style.display = 'flex';
                item.onclick = function(){ window.location='med.php?category='+encodeURIComponent(exp.Category||'Model')+'&id='+encodeURIComponent(exp.ID); };
            }
        });

        // ----- 更多按钮：使用解析函数 -----
        var more = el.querySelector('.more-button');
        if (more && block.TargetLink) {
            var url = parseTargetLink(block.TargetLink);
            if (url) {
                more.onclick = function(){ window.location.href = url; };
            } else {
                // 如果解析失败，可以留空或使用原值（但原值可能无效）
                // 这里我们保留原值作为备用，但可能仍会出错
                more.onclick = function(){ window.location.href = block.TargetLink; };
            }
        }
    });

    // ----- 每日活动卡片（独立包裹） -----
    if (blocks.length > 0) {
        var firstBlock = document.querySelector('.block[data-module-index="0"]');
        if (firstBlock) {
            var auxText = blocks[0].AuxiliaryText;
            var auxLink = blocks[0].AuxiliaryLink;
            if (auxText && auxLink) {
                var wrapper = document.createElement('div');
                wrapper.className = 'block-wrapper';
                wrapper.style.display = 'flex';
                wrapper.style.flexDirection = 'column';
                wrapper.style.height = '100%';
                wrapper.style.gap = '20px';
                firstBlock.parentNode.insertBefore(wrapper, firstBlock);
                wrapper.appendChild(firstBlock);
                var card = document.createElement('div');
                card.className = 'daily-activity-card';
                card.textContent = auxText;
                card.onclick = openActivitiesModal;
                wrapper.appendChild(card);
                var content = firstBlock.querySelector('.block-content');
                if (content) content.style.flex = '1';
                firstBlock.style.display = 'flex';
                firstBlock.style.flexDirection = 'column';
                firstBlock.style.height = '100%';
            }
        }
    }
}

// ---------- 更新头部 ----------
function updateHeader() {
    if(tuData && tuData.Data && tuData.Data.User) {
        var u = tuData.Data.User;
        document.getElementById('user-nickname').textContent = u.Nickname||'点击登录';
        document.getElementById('user-level').textContent = 'Level '+(u.Level||1);
        document.getElementById('user-gold').textContent = u.Gold||0;
        document.getElementById('user-diamond').textContent = u.Diamond||0;
    }
}
updateHeader();

// ---------- 点击登录跳转 ----------
document.getElementById('user-info-area').addEventListener('click', function() {
    window.location.href = 'login.php';
});
</script>
<?php flush(); ?>
</body>
</html>