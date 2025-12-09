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
    
    if (isset($token) && isset($authCode)) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel='stylesheet' href='../styles/main.css'/>
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
                        <div style="position: absolute; z-index: 100;">
                            <div class="tag" style="color: aquamarine; font-weight: bold;">
                                <?= htmlspecialchars($content['Category']) ?>
                            </div><div class="tag"><i class="fas fa-eye"></i>&nbsp;<?= $content['Visits'] ?? 0 ?></div>
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
                        <!-- 标签页 -->
                        <div class="n-tabs">
                            <div class="n-tabs-wrapper">
                                <div class="n-tabs-tab n-tabs-tab--active" onclick="switchTab('intro')">简介</div>
                                <div class="n-tabs-tab" onclick="switchTab('info')">评论(<?= $content['Comments'] ?? 0 ?>)</div>
                            </div>
                            
                            <div class="n-tab-pane">
                                <div class="gray">
                                    <!-- 简介标签页 -->
                                    <div id="intro-tab" class="qh">
<!-- 用户信息卡片 -->
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
                                                    <?= nl2br(htmlspecialchars($content['LocalizedDescription']['Chinese'])) ?>
                                                <?php else: ?>
                                                    <?= implode('<br>', $content['Description']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 详细信息标签页 -->
                                    <div id="info-tab" class="qh" style="display: none;"><div id="comments">加载评论中...</div><?php include 'comment/comment.html' ?></div>
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
        </div>
    </div>
    <script>
        // 标签页切换
        function switchTab(tabName) {
            // 隐藏所有标签内容
            document.querySelectorAll('.qh').forEach(tab => {
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
// 使用JavaScript动态加载评论
fetch('/comment/?category=<?= $category ?>&id=<?= $contentId ?>')
    .then(response => response.text())
    .then(html => {
        document.getElementById('comments').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('comments').innerHTML = '<div class="error">加载评论失败</div>';
    });
  </script>
    <script>
    setInterval(()=>{
            // 点击用户头像或名称时跳转到用户页面
            const userElements = document.querySelectorAll('#notification_container');
            userElements.forEach(container => {
                const avatar = container.querySelector('#avatar');
                const name = container.querySelector('.name');
                container.addEventListener('click', function(){
                  document.body.querySelector('textarea').value='回复@'+name.innerHTML+': ';
                  const bt=document.getElementById('start');
                  bt.innerText=' 回复@'+name.innerText+'：';
                  bt.style.color='black';
                  bt.dataset.rid=container.dataset.rid;
                });
                const onClick = function() {
                  const userId = container.getAttribute('data-rid');
                  getUserCard(userId);
                };
                
                if (avatar) avatar.addEventListener('click', onClick);
                if (name) name.addEventListener('click', onClick);
            });
            
            // 点击@用户时跳转到对应用户页面
            const rUsers = document.querySelectorAll('.RUser');
            rUsers.forEach(rUser => {
                rUser.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user');
                    getUserCard(userId);
                    // 这里可以添加跳转到对应用户页面的逻辑
                });
            });
     }, 500);
        const commentBox = document.getElementById('comment');
        const topSentinel = document.getElementById('top-con');
        const bottomSentinel = document.getElementById('bottom-con');
        
        let isAtTop = false;
        let isAtBottom = false;
        
        // 创建观察器
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.target === topSentinel) {
                    isAtTop = entry.isIntersecting;
                }
                if (entry.target === bottomSentinel) {
                    isAtBottom = entry.isIntersecting;
                }
            });
        }, {
            root: commentBox,
            threshold: 0
        });
        
        // 观察顶部和底部标记
        observer.observe(topSentinel);
        observer.observe(bottomSentinel);
        
        // 监听滚动事件
        commentBox.addEventListener('scroll', (e) => {
            // 获取滚动信息
            const scrollTop = commentBox.scrollTop;
            const scrollHeight = commentBox.scrollHeight;
            const clientHeight = commentBox.clientHeight;
            
            // 超出底部边界时执行
            if (scrollTop + clientHeight >= scrollHeight - 1 && !isAtBottom) {
                console.log('已经到底部，继续往下翻');
                // 执行你的底部代码
                onReachBottom();
            }
            
            // 超出顶部边界时执行
            if (scrollTop <= 0 && !isAtTop) {
                console.log('已经到顶部，继续往上翻');
                // 执行你的顶部代码
                onReachTop();
            }
        });
        
        // 防止过度触发，添加节流
        let isThrottled = false;
        
        function onReachBottom() {
            if (isThrottled) return;
            fetch('/comment/?category=<?= $category ?>&id=<?= $contentId ?>&skip=20')
    .then(response => response.text())
    .then(html => {
        document.getElementById('comments').innerHTML += html;
    })
    .catch(error => {
        document.getElementById('comments').innerHTML += '<div class="error">加载评论失败</div>';
    });           
            isThrottled = true;
            setTimeout(() => {
                isThrottled = false;
            }, 500);
        }
        
        function onReachTop() {
            if (isThrottled) return;
            fetch('/comment/?category=<?= $category ?>&id=<?= $contentId ?>')
    .then(response => response.text())
    .then(html => {
        document.getElementById('comments').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('comments').innerHTML = '<div class="error">加载评论失败</div>';
    });           
            isThrottled = true;
            setTimeout(() => {
                isThrottled = false;
            }, 500);
        }

// 全局变量跟踪当前卡片
var currentCard = null;
// 防止重复调用的标志位
var isOpeningCard = false;
// 存储最后一次调用的参数
var lastUid = null;

// 修改后的 getUserCard 函数，添加防抖处理
function getUserCard(uid) {
    // 如果正在打开卡片，直接返回
    if (isOpeningCard) {
        console.log('卡片正在打开中，忽略重复调用');
        return;
    }
    
    // 如果已有卡片，先移除
    if (currentCard) {
        currentCard.remove();
        currentCard = null;
    }
    
    // 设置正在打开标志
    isOpeningCard = true;
    lastUid = uid;
    
    console.log('显示用户卡片，ID:', uid);
    
    fetch('/user/card.php?id=' + uid)
        .then(r => r.text())
        .then(html => {
            // 创建遮罩层
            const overlay = document.createElement('div');
            overlay.id = 'userCardOverlay';
            overlay.style.cssText = `
                position:fixed;
                top:0;left:0;right:0;bottom:0;
                background:rgba(0,0,0,0.5);
                display:flex;
                justify-content:center;
                align-items:center;
                z-index:9999;
            `;
            
            // 提取卡片内容
            const temp = document.createElement('div');
            temp.innerHTML = html;
            let cardHTML = temp.innerHTML;
            
            // 如果是完整HTML，提取body内容
            if (html.includes('<body')) {
                const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
                if (bodyMatch) {
                    const temp2 = document.createElement('div');
                    temp2.innerHTML = bodyMatch[1];
                    cardHTML = temp2.innerHTML;
                }
            }
            
            // 插入卡片
            overlay.innerHTML = `<div style="position:relative;">${cardHTML}</div>`;
            
            // 添加到页面并保存引用
            document.body.appendChild(overlay);
            currentCard = overlay;
            
            // 重置打开标志
            isOpeningCard = false;
            
            // 事件监听器1：点击遮罩层关闭（使用once确保只绑定一次）
            overlay.addEventListener('click', function closeOnOverlayClick(e) {
                if (e.target === overlay) {
                    // 立即移除事件监听器，防止重复触发
                    overlay.removeEventListener('click', closeOnOverlayClick);
                    overlay.remove();
                    currentCard = null;
                }
            }, { once: true });
            
            // 事件监听器2：查找并绑定关闭按钮
            setTimeout(() => {
                // 查找所有关闭按钮
                const closeBtns = overlay.querySelectorAll('.close-btn, button[onclick*="close"]');
                
                closeBtns.forEach(btn => {
                    // 移除原有的click事件监听器，防止重复绑定
                    const newBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(newBtn, btn);
                    
                    // 为新按钮绑定事件
                    newBtn.addEventListener('click', function closeBtnClick(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        
                        if (overlay.parentNode) {
                            overlay.remove();
                            currentCard = null;
                        }
                    }, { once: true }); // 使用once确保只触发一次
                });
                
                // 阻止卡片内部点击事件冒泡
                const cardContent = overlay.querySelector('.user-card') || 
                                    overlay.querySelector('.centered-container') ||
                                    overlay.querySelector('div > div');
                if (cardContent) {
                    cardContent.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }
                
                // 绑定关注按钮功能（可选）
                setupUserCardEvents();
            }, 0);
        })
        .catch(e => {
            console.error('加载失败:', e);
            isOpeningCard = false; // 出错时也要重置标志
        });
}

// 防抖函数，防止快速多次点击
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// 使用防抖包装的getUserCard函数
var debouncedGetUserCard = debounce(getUserCard, 300);

// 如果您需要替换原来的调用，可以使用这个防抖版本
// 示例：debouncedGetUserCard(uid);

// 其他函数保持不变
function setupUserCardEvents() {
    const overlay = document.getElementById('userCardOverlay');
    if (!overlay) return;
    
    // 关注按钮功能
    const followBtn = overlay.querySelector('#followBtn');
    const unfollowBtn = overlay.querySelector('#unfollowBtn');
    
    if (followBtn) {
        // 克隆并替换按钮，移除旧的事件监听器
        const newFollowBtn = followBtn.cloneNode(true);
        followBtn.parentNode.replaceChild(newFollowBtn, followBtn);
        
        newFollowBtn.addEventListener('click', function(e) {
            e.stopPropagation(); // 防止事件冒泡
            console.log('关注用户');
            
            // 模拟API请求
            setTimeout(() => {
                newFollowBtn.style.display = 'none';
                if (unfollowBtn) {
                    const newUnfollowBtn = unfollowBtn.cloneNode(true);
                    unfollowBtn.parentNode.replaceChild(newUnfollowBtn, unfollowBtn);
                    newUnfollowBtn.style.display = 'block';
                }
                updateFollowersCount(1);
            }, 300);
        });
    }
    
    if (unfollowBtn) {
        // 克隆并替换按钮，移除旧的事件监听器
        const newUnfollowBtn = unfollowBtn.cloneNode(true);
        unfollowBtn.parentNode.replaceChild(newUnfollowBtn, unfollowBtn);
        
        newUnfollowBtn.addEventListener('click', function(e) {
            e.stopPropagation(); // 防止事件冒泡
            console.log('取消关注用户');
            
            // 模拟API请求
            setTimeout(() => {
                newUnfollowBtn.style.display = 'none';
                if (followBtn) {
                    const newFollowBtn = followBtn.cloneNode(true);
                    followBtn.parentNode.replaceChild(newFollowBtn, followBtn);
                    newFollowBtn.style.display = 'block';
                }
                updateFollowersCount(-1);
            }, 300);
        });
    }
}

function updateFollowersCount(change) {
    const overlay = document.getElementById('userCardOverlay');
    if (!overlay) return;
    
    const statItems = overlay.querySelectorAll('.stat-item');
    if (statItems.length > 1) {
        const followersElement = statItems[1].querySelector('.text');
        if (followersElement) {
            const text = followersElement.textContent;
            const current = parseInt(text.replace(/[^\d]/g, '')) || 0;
            const newCount = current + change;
            followersElement.textContent = '粉丝' + newCount.toLocaleString();
        }
    }
}

function handleEscapeKey(e) {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('userCardOverlay');
        if (overlay) {
            overlay.remove();
            currentCard = null;
            isOpeningCard = false; // 重置打开标志
        }
    }
}

// 添加ESC键监听
document.addEventListener('keydown', handleEscapeKey);

// 如果需要修复页面中的点击事件，可以添加以下代码
document.addEventListener('DOMContentLoaded', function() {
    // 查找所有可能触发getUserCard的元素
    const triggerElements = document.querySelectorAll('[onclick*="getUserCard"], [data-user-id]');
    
    triggerElements.forEach(element => {
        // 移除原有的onclick事件
        const originalOnClick = element.getAttribute('onclick');
        if (originalOnClick && originalOnClick.includes('getUserCard')) {
            element.removeAttribute('onclick');
            
            // 获取用户ID
            const uid = element.dataset.userId || 
                       originalOnClick.match(/getUserCard\(['"]([^'"]+)['"]\)/)?.[1] ||
                       originalOnClick.match(/getUserCard\(([^)]+)\)/)?.[1];
            
            if (uid) {
                // 添加新的点击事件，使用防抖版本
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    debouncedGetUserCard(uid.trim());
                });
            }
        }
    });
});
    </script>
</body>
</html>
