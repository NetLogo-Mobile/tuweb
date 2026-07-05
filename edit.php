<?php
session_start();
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');

// ---------- 包含认证模块 ----------
include 'getv.php';

// ---------- 从 session 获取 token 和 authCode ----------
$token = $_SESSION['token'] ?? '';
$authCode = $_SESSION['authCode'] ?? '';

// ---------- 当前用户信息 ----------
$currentUser = isset($dt['Data']['User']) ? $dt['Data']['User'] : null;
$isLoggedIn = ($currentUser !== null && !empty($currentUser['ID']));
$userId = $isLoggedIn ? (string)$currentUser['ID'] : '';

// ---------- 获取编辑参数 ----------
$editId = isset($_GET['id']) ? trim($_GET['id']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : 'Model';
$isEditMode = !empty($editId);

// ---------- 初始化内容变量 ----------
$summary = null;
$isAuthor = false;
$errorMsg = '';

// ---------- 如果是编辑模式，从 API 获取内容 ----------
if ($isEditMode) {
    // 修复：使用 HTTPS 协议
    $apiUrl = 'https://nlm-api-cn.turtlesim.com/Contents/GetSummary';
    $body = json_encode(['ContentID' => $editId, 'Category' => $category]);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Language: zh-CN',
        'x-API-Token: ' . $token,
        'x-API-AuthCode: ' . $authCode,
    ];
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyResp = substr($response, $headerSize);
    curl_close($ch);
    $data = json_decode($bodyResp, true);

    // 增强错误处理：如果 API 返回错误，提取信息
    if ($data && isset($data['Data'])) {
        $summary = $data['Data'];
        if ($isLoggedIn && isset($summary['User']['ID'])) {
            $userIdStr = (string)$userId;
            $authorIdStr = (string)$summary['User']['ID'];
            $isCoauthor = false;
            if (!empty($summary['Coauthors']) && is_array($summary['Coauthors'])) {
                foreach ($summary['Coauthors'] as $coauthor) {
                    if (isset($coauthor['ID']) && (string)$coauthor['ID'] === $userIdStr) {
                        $isCoauthor = true;
                        break;
                    }
                }
            }
            $isAuthor = ($authorIdStr === $userIdStr) || $isCoauthor;
        }
    } else {
        $errorMsg = '获取内容失败：' . ($data['Message'] ?? '未知错误');
    }
}

// ---------- 权限判断 ----------
$canEdit = $isLoggedIn && (!$isEditMode || $isAuthor);
$isReadonly = !$canEdit;

// ---------- 表单默认值 ----------
$title = $summary ? ($summary['LocalizedSubject']['Chinese'] ?? $summary['Subject']['Chinese'] ?? $summary['Subject'] ?? '') : '';
$tags = $summary ? implode(',', $summary['Tags'] ?? []) : '';
$content = $summary ? ($summary['LocalizedDescription']['Chinese'] ?? implode("\n", $summary['Description'] ?? [])) : '';
$categoryValue = $summary ? $summary['Category'] : $category;

if (!$isEditMode) {
    $title = isset($_GET['title']) ? trim($_GET['title']) : $title;
    $content = isset($_GET['content']) ? trim($_GET['content']) : $content;
    $tags = isset($_GET['tags']) ? trim($_GET['tags']) : $tags;
}

// ---------- 提取编辑模式所需的额外字段 ----------
$existingData = null;
if ($isEditMode && $summary) {
    $existingData = [
        'ParentID' => $summary['ParentID'] ?? null,
        'ModelID'  => $summary['ModelID'] ?? null,
        'Version'  => $summary['Version'] ?? 1401,
        'Language' => $summary['Language'] ?? 'Chinese',
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= $isEditMode ? '编辑作品' : '发布新作品' ?> - Turtle Universe</title>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f5f7fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #24292e; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #d0d7de; border-radius: 6px; font-size: 14px; background: #fafbfc; }
        .form-group input:focus, .form-group select:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }
        .form-group textarea { width: 100%; min-height: 300px; padding: 12px; border: 1px solid #d0d7de; border-radius: 6px; font-family: 'Consolas', monospace; font-size: 14px; resize: vertical; }
        .form-group textarea:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }
        .form-group textarea[readonly] { background: #f6f8fa; color: #586069; cursor: not-allowed; }
        .preview-box { border: 1px solid #e1e4e8; border-radius: 6px; padding: 12px; background: #fafbfc; min-height: 100px; margin-top: 8px; }
        .preview-box a { color: #0366d6; text-decoration: none; }
        .preview-box a:hover { text-decoration: underline; }
        .actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 16px; }
        .btn { padding: 10px 28px; border: none; border-radius: 6px; font-size: 15px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a6fd8; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { background: #e1e4e8; color: #333; }
        .btn-secondary:hover { background: #d1d5da; }
        .status-msg { padding: 12px 16px; border-radius: 6px; display: none; margin-bottom: 16px; }
        .status-msg.error { background: #ffe3e3; color: #b33; border: 1px solid #f5c6c6; }
        .status-msg.success { background: #e3f5e3; color: #2c6b2c; border: 1px solid #b8e0b8; }
        .notice { background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 16px; border: 1px solid #ffeeba; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .top-bar h2 { color: #333; }
        .top-bar .back-link { color: #667eea; text-decoration: none; font-size: 14px; }
        @media (max-width: 600px) { body { padding: 10px; } .card { padding: 15px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h2><?= $isEditMode ? '编辑作品' : '发布新作品' ?></h2>
        <a href="javascript:history.back()" class="back-link">← 返回</a>
    </div>

    <div class="card">
        <?php if ($errorMsg): ?>
            <div class="status-msg error" style="display:block;"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <?php if (!$isLoggedIn): ?>
            <div class="notice">您尚未登录，请先 <a href="login.php" style="color:#667eea;">登录</a> 以发布或编辑内容。</div>
        <?php elseif ($isEditMode && !$isAuthor): ?>
            <div class="notice">您不是该作品的作者或合作者，无法编辑。当前为只读模式。</div>
        <?php endif; ?>

        <div id="statusMsg" class="status-msg"></div>

        <form id="editForm" onsubmit="return false;">
            <div class="form-group">
                <label for="categorySelect">分类</label>
                <select id="categorySelect" <?= $isReadonly ? 'disabled' : '' ?>>
                    <option value="Model" <?= $categoryValue === 'Model' ? 'selected' : '' ?>>模型</option>
                    <option value="Experiment" <?= $categoryValue === 'Experiment' ? 'selected' : '' ?>>实验</option>
                    <option value="Discussion" <?= $categoryValue === 'Discussion' ? 'selected' : '' ?>>讨论</option>
                </select>
            </div>
            <div class="form-group">
                <label for="titleInput">标题</label>
                <input type="text" id="titleInput" placeholder="输入标题..." value="<?= htmlspecialchars($title) ?>" <?= $isReadonly ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label for="tagsInput">标签（用逗号分隔）</label>
                <input type="text" id="tagsInput" placeholder="例如：精选,物理" value="<?= htmlspecialchars($tags) ?>" <?= $isReadonly ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label for="editorSource">内容（支持 Markdown）</label>
                <textarea id="editorSource" <?= $isReadonly ? 'readonly' : '' ?>><?= htmlspecialchars($content) ?></textarea>
                <div style="margin-top: 8px;">
                    <button type="button" id="togglePreviewBtn" class="btn btn-secondary" style="padding:4px 12px; font-size:12px;">切换预览</button>
                </div>
                <div id="previewContainer" class="preview-box" style="display:none;"></div>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">取消</button>
                <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-primary" id="publishBtn"><?= $isEditMode ? '更新' : '发布' ?></button>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" disabled>无权限</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
// ---------- PHP 注入 ----------
const isReadonly = <?= json_encode($isReadonly) ?>;
const isEditMode = <?= json_encode($isEditMode) ?>;
const editId = <?= json_encode($editId) ?>;
const existingData = <?= json_encode($existingData) ?>;

// ---------- 预览 ----------
const source = document.getElementById('editorSource');
const previewContainer = document.getElementById('previewContainer');
let previewVisible = false;

function renderPreview() {
    if (!previewVisible) return;
    const raw = source.value;
    marked.setOptions({ breaks: true, gfm: true });
    const rawHtml = marked.parse(raw);
    const safeHtml = DOMPurify.sanitize(rawHtml);
    previewContainer.innerHTML = safeHtml;
}
source.addEventListener('input', renderPreview);
document.getElementById('togglePreviewBtn').addEventListener('click', function() {
    previewVisible = !previewVisible;
    previewContainer.style.display = previewVisible ? 'block' : 'none';
    if (previewVisible) renderPreview();
    this.textContent = previewVisible ? '隐藏预览' : '切换预览';
});

// ---------- 提交 ----------
document.getElementById('publishBtn').addEventListener('click', function(e) {
    e.preventDefault();
    const title = document.getElementById('titleInput').value.trim();
    if (!title) { showStatus('请输入标题', 'error'); return; }
    const content = source.value;
    if (!content) { showStatus('请输入内容', 'error'); return; }
    const category = document.getElementById('categorySelect').value;
    const tags = document.getElementById('tagsInput').value.split(',').map(t => t.trim()).filter(Boolean);

    // ---------- 将内容按行拆分为数组 ----------
    const descriptionLines = content.split('\n');

    // ---------- 构建符合 API 要求的 Summary ----------
    const summary = {
        Category: category,
        Subject: title,
        LocalizedSubject: { Chinese: title },
        Description: descriptionLines,
        LocalizedDescription: null,
        Tags: tags,
        Image: 0,
        Visibility: 0,
    };

    if (isEditMode && editId) {
        summary.ID = editId;
        if (existingData) {
            if (existingData.ParentID) summary.ParentID = existingData.ParentID;
            if (existingData.ModelID) summary.ModelID = existingData.ModelID;
            if (existingData.Version) summary.Version = existingData.Version;
            if (existingData.Language) summary.Language = existingData.Language;
        }
    }

    // ---------- 发送请求 ----------
    fetch('/api/edit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'submit', Summary: summary })
    })
    .then(res => res.text().then(text => {
        try { return { status: res.status, body: JSON.parse(text) }; } 
        catch(e) { return { status: res.status, body: { Message: text } }; }
    }))
    .then(({ status, body }) => {
        if (status === 200 && body.Status === 200) {
            showStatus('✅ ' + (isEditMode ? '更新' : '发布') + '成功！', 'success');
            if (body.Data?.Summary?.ID) {
                setTimeout(() => {
                    window.location.href = 'med.php?id=' + body.Data.Summary.ID + '&category=' + category;
                }, 1500);
            }
        } else {
            showStatus('❌ 操作失败：' + (body.Message || body.error || '未知错误'), 'error');
        }
    })
    .catch(err => showStatus('❌ 网络错误：' + err.message, 'error'));
});

function showStatus(msg, type) {
    const el = document.getElementById('statusMsg');
    el.textContent = msg;
    el.className = 'status-msg ' + type;
    el.style.display = 'block';
}
</script>
</body>
</html>