<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/**
 * 通知系统 - 完整PHP实现
 * 使用提供的样式和导航栏
 */

// API配置
define('API_BASE_URL', 'https://nlm-api-cn.turtlesim.com/');

// 检查用户是否登录
function checkAuth() {
    if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
        echo '<script>alert("请先登录"); window.location.href = "login.php";</script>';
        exit();
    }
}

// 检查session数据
$dt = null;
if (isset($_SESSION['responseBody'])) {
    $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
} else {
    $redirectUrl = 'getv.php?r=nof.php';
    if (!headers_sent()) {
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        echo '<script>window.location.href = "' . $redirectUrl . '";</script>';
        exit;
    }
}

// 检查登录状态
$isLoggedIn = isset($dt['Data']['User']['Nickname']) && $dt['Data']['User']['Nickname'] !== '点击登录';

// 调用API函数
function callAPI($endpoint, $method = 'GET', $data = [], $isRetry = false) {
    $url = API_BASE_URL . ltrim($endpoint, '/');
    
    $headers = [
        'X-API-Token: ' . $_SESSION['token'],
        'X-API-AuthCode: ' . $_SESSION['authCode'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return [
            'Status' => 500,
            'Message' => 'CURL Error: ' . $error,
            'Data' => null
        ];
    }
    
    $result = json_decode($response, true);
    
    // 如果token过期，尝试刷新
    if ($httpCode === 401 && !$isRetry) {
        $refreshResult = refreshToken();
        if ($refreshResult['success']) {
            return callAPI($endpoint, $method, $data, true);
        }
    }
    
    return [
        'Status' => $httpCode,
        'Message' => $result['Message'] ?? ($httpCode === 200 ? 'Success' : 'Unknown error'),
        'Data' => $result['Data'] ?? $result
    ];
}

// 刷新token
function refreshToken() {
    if (!isset($_SESSION['refreshToken'])) {
        return ['success' => false];
    }
    
    $data = [
        'refreshToken' => $_SESSION['refreshToken']
    ];
    
    $response = callAPI('/Auth/RefreshToken', 'POST', $data);
    
    if ($response['Status'] === 200) {
        $_SESSION['token'] = $response['Data']['Token'];
        $_SESSION['authCode'] = $response['Data']['AuthCode'];
        if (isset($response['Data']['RefreshToken'])) {
            $_SESSION['refreshToken'] = $response['Data']['RefreshToken'];
        }
        return ['success' => true];
    }
    
    return ['success' => false];
}

// 获取头像URL - 修复avatarNumber问题
function getAvatarUrl($userID, $avatarNumber = 0) {
    if (empty($userID) || strlen($userID) < 24) {
        return '';
    }
    
    $part1 = substr($userID, 0, 4);
    $part2 = substr($userID, 4, 2);
    $part3 = substr($userID, 6, 2);
    $part4 = substr($userID, 8, 16);
    
    return "http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/{$part1}/{$part2}/{$part3}/{$part4}/{$avatarNumber}.jpg!full";
}

// 获取消息列表 - 一次加载100条
function getMessages($categoryID, $skip = 0, $take = 20, $noTemplates = false) {
    $data = [
        'CategoryID' => $categoryID,
        'Skip' => $skip,
        'Take' => $take,
        'NoTemplates' => $noTemplates
    ];
    
    return callAPI('/Messages/GetMessages', 'POST', $data);
}

// 转换函数
function convertUIIndexToCategoryID($n) {
    return $n === 3 ? 2 : ($n === 2 ? 3 : $n);
}

function convertCategoryIDToUIIndex($n) {
    return $n === 2 ? 3 : ($n === 3 ? 2 : $n);
}

// 模板填充函数
function fillInTemplate($data, $message, $userInfo) {
    if (empty($data)) return '';
    
    // 替换用户标记
    if (strpos($data, '{Users}') !== false) {
        $usersHtml = '';
        if (isset($message['Users']) && isset($message['UserNames'])) {
            foreach ($message['Users'] as $index => $userId) {
                $userName = $message['UserNames'][$index] ?? '';
                $usersHtml .= '<user=' . htmlspecialchars($userId) . '>' . 
                            htmlspecialchars($userName) . '</user> ';
            }
        }
        $data = str_replace('{Users}', $usersHtml, $data);
    }
    
    // 替换其他标记
    $replacements = [
        '{$TargetName}' => $message['Fields']['TargetName'] ?? $userInfo['nickName'] ?? '',
        '{$Content}' => $message['Fields']['Content'] ?? '',
        '{$Until}' => $message['Fields']['Unitl'] ?? '',
        '{$Editor}' => $message['Fields']['Editor'] ?? '',
        '{$Gold}' => $message['Numbers']['Gold'] ?? '',
        '{Experiment}' => isset($message['Fields']['Discussion']) ?
            '<discussion=' . ($message['Fields']['DiscussionID'] ?? '') . '>' . 
            htmlspecialchars($message['Fields']['Discussion']) . '</discussion>' : (isset($message['Fields']['Experiment']) ?
            '<experiment=' . ($message['Fields']['ExperimentID'] ?? '') . '>' . 
            htmlspecialchars($message['Fields']['Experiment'] ?? '') . '</experiment>' :
            '<model=' . ($message['Fields']['ModelID'] ?? '') . '>' . 
            htmlspecialchars($message['Fields']['Model'] ?? '') . '</model>')
    ];
    
    foreach ($replacements as $search => $replace) {
        $data = str_replace($search, $replace, $data);
    }
    
    // 清理undefined
    $data = str_replace('undefined', '', $data);
    
    return $data;
}

// 自定义标签解析器类
class CustomTagParser {
    
    /**
     * 解析包含自定义标签的文本
     */
    public static function parse(string $text): string {
        if (empty($text)) {
            return '';
        }
        
        // 先转义HTML特殊字符，防止XSS攻击
        $text = htmlspecialchars($text);
        
        // 先解析Markdown标题（在换行转换之前）
        $text = self::parseMarkdownHeadings($text);
        
        // 将换行符转换为<br>
        $text = nl2br($text);
        
        // 解析自定义标签
        $patterns = [
            // user标签 - 保持原有格式
            '/&lt;user=([a-f0-9]+)&gt;(.*?)&lt;\/user&gt;/i',
            // experiment标签 - 跳转链接
            '/&lt;experiment=([a-f0-9]+)&gt;(.*?)&lt;\/experiment&gt;/i',
            // discussion标签 - 跳转链接
            '/&lt;discussion=([a-f0-9]+)&gt;(.*?)&lt;\/discussion&gt;/i',
            // model标签 - 跳转链接
            '/&lt;model=([a-f0-9]+)&gt;(.*?)&lt;\/model&gt;/i',
            // external标签 - 外部链接
            '/&lt;external=([^&]+)&gt;(.*?)&lt;\/external&gt;/i',
            // size标签
            '/&lt;size=([^&]+)&gt;(.*?)&lt;\/size&gt;/i',
            // color标签 - 新增
            '/&lt;color=([^&]+)&gt;(.*?)&lt;\/color&gt;/i',
            // b标签
            '/&lt;b&gt;(.*?)&lt;\/b&gt;/i',
            // i标签
            '/&lt;i&gt;(.*?)&lt;\/i&gt;/i',
            // a标签 - 深蓝色标签
            '/&lt;a&gt;(.*?)&lt;\/a&gt;/i'
        ];
        
        $replacements = [
            // user标签
            '<span class="RUser" data-user="$1">$2</span>',
            // experiment标签
            '<a href="med.php?id=$1&category=Experiment" class="experiment-link" style="text-decoration:none;">$2</a>',
            // discussion标签
            '<a href="med.php?id=$1&category=Discussion" class="discussion-link" style="text-decoration:none;">$2</a>',
            // model标签
            '<a href="med.php?id=$1&category=Model" class="model-link" style="text-decoration:none;">$2</a>',
            // external标签
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" class="external-link" style="text-decoration:none;">$2</a>',
            // size标签
            '<span style="font-size: $1;">$2</span>',
            // color标签 - 新增
            '<span style="color: $1;">$2</span>',
            // b标签
            '<strong>$1</strong>',
            // i标签
            '<em>$1</em>',
            // a标签
            '<span class="blue-tag" style="text-decoration:none;">$1</span>'
        ];
        
        $text = preg_replace($patterns, $replacements, $text);
        
        // 解析简单的Markdown格式
        $text = self::parseSimpleMarkdown($text);
        
        return $text;
    }
    
    /**
     * 解析Markdown标题
     */
    private static function parseMarkdownHeadings(string $text): string {
        // 支持1-6级标题
        // 一级标题: # 标题
        $text = preg_replace('/^# (.+)$/m', '<strong class="h1">$1</strong>', $text);
        
        // 二级标题: ## 标题
        $text = preg_replace('/^## (.+)$/m', '<strong class="h2">$1</strong>', $text);
        
        // 三级标题: ### 标题
        $text = preg_replace('/^### (.+)$/m', '<strong class="h3">$1</strong>', $text);
        
        // 四级标题: #### 标题
        $text = preg_replace('/^#### (.+)$/m', '<strong class="h4">$1</strong>', $text);
        
        return $text;
    }
    
    /**
     * 解析简单的Markdown格式
     */
    private static function parseSimpleMarkdown(string $text): string {
        // 粗体
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        
        // 斜体
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        
        // 删除线
        $text = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $text);
        
        // 行内代码
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        
        return $text;
    }
}

// 根据消息类型获取图标URL
function getMsgIconUrl($msgType) {
    $icons = [
        1 => 'notifications_system.png',
        2 => 'notifications_comments.png',
        3 => 'notifications_followers.png',
        4 => 'notifications_projects.png',
        5 => 'notifications_admin.png'
    ];
    
    $icon = $icons[$msgType] ?? 'notifications_system.png';
    return "/assets/icons/{$icon}";
}

// 获取路径
function getPath($path) {
    $baseUrl = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/');
    
    if (strpos($path, '/@base/') === 0) {
        return $baseUrl . '/assets' . substr($path, 7);
    } elseif (strpos($path, '/@root/') === 0) {
        return $baseUrl . substr($path, 7);
    } else {
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

// 获取消息类型名称
function getMsgTypeName($msgType) {
    $names = [
        1 => '系统',
        2 => '评论',
        3 => '关注',
        4 => '作品',
        5 => '管理'
    ];
    return $names[$msgType] ?? '消息';
}

// 根据ID计算相对时间
function formatTimeAgo($id) {
    if (!empty($id) && strlen($id) >= 8) {
        try {
            $timestamp = hexdec(substr($id, 0, 8)) * 1000;
            $now = time() * 1000;
            $diff = $now - $timestamp;
            
            if ($diff < 60000) return '刚刚';
            elseif ($diff < 3600000) return floor($diff / 60000) . '分钟前';
            elseif ($diff < 86400000) return floor($iff / 3600000) . '小时前';
            elseif ($diff < 604800000) return floor($diff / 86400000) . '天前';
            else return date('Y-m-d', $timestamp / 1000);
        } catch (Exception $e) {
            return '刚刚';
        }
    }
    return '刚刚';
}

// 渲染单个通知项
function renderNotificationItem($item) {
    // 头像URL - 从消息中获取avatarNumber
    $avatarNumber = isset($item['avatar_number']) ? $item['avatar_number'] : 0;
    $avatarUrl = '';
    if (!empty($item['uid'])) {
        $avatarUrl = getAvatarUrl($item['uid'], $avatarNumber);
    }
    
    // 默认头像
    $defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik0zMCAzNEMzMy4zMTM3IDM0IDM2IDMxLjMxMzcgMzYgMjhDMzYgMjQuNjg2MyAzMy4zMTM3IDIyIDMwIDIyQzI2LjY4NjMgMjIgMjQgMjQuNjg2MyAyNCAyOEMyNCAzMS4zMTM3IDI2LjY4NjMgMzQgMzAgMzRaIiBmaWxsPSIjQ0VDRUNFIi8+CjxwYXRoIGQ9Ik0zNiAzOEgyNEMyMi44OTU0IDM4IDIyIDM3LjEwNDYgMjIgMzZWMjRDMjIgMjIuODk1NCAyMi44OTU0IDIyIDI0IDIySDM2QzM3LjEwNDYgMjIgMzggMjIuODk1NCAzOCAyNFYzNkMzOCAzNy4xMDQ2IDM3LjEwNDYgMzggMzYgMzhaTTI0IDI0VjM2SDM2VjI0SDI0WiIgZmlsbD0iI0NFQ0VDRSIvPgo8L3N2Zz4K';
    
    // 图标URL
    $iconUrl = getMsgIconUrl($item['msg_type']);
    $typeName = getMsgTypeName($item['msg_type']);
    
    // 格式化时间
    $timeAgo = formatTimeAgo($item['id']);

    // 点击跳转URL
    $onclick = '';
    if ($item['msg_type'] === 2 && !empty($item['tid'])) {
        $onclick = 'onclick="window.location.href=\'med.php?category=' . htmlspecialchars($item['category']) . '&id=' . htmlspecialchars($item['tid']) . '\'"';
        $style = 'style="cursor: pointer;"';
    } else {
        $style = 'style="cursor: default;"';
    }

    $html = '<div class="notification-container" ' . $onclick . ' ' . $style . ' data-id="' . htmlspecialchars($item['id']) . '">';
    
    // 头像
    $html .= '<img class="notification-avatar" src="' . (!empty($avatarUrl) ? htmlspecialchars($avatarUrl) : $defaultAvatar) . '" alt="头像" ';
    $html .= 'onerror="this.onerror=null; this.src=\'' . $defaultAvatar . '\'">';
    
    // 内容
    $html .= '<div class="notification-content">';
    $html .= '<div class="notification-title">' . $item['msg_title'] . '</div>';
    $html .= '<div class="notification-message">' . $item['msg'] . '</div>';
    $html .= '<div class="notification-footer">';
    $html .= '<div class="notification-time"><i class="far fa-clock"></i>' . $timeAgo . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// 处理分页加载 - 一次加载100条
function getPagedMessages($categoryID, $skip = 0, $take = 20) {
    $response = getMessages($categoryID, $skip, $take, false);
    
    $items = [];
    if ($response['Status'] === 200) {
        $templates = $response['Data']['Templates'] ?? [];
        $messages = $response['Data']['Messages'] ?? [];
        
        // 用户信息
        global $dt;
        $userInfo = $dt['Data']['User'] ?? [];
        
        foreach ($messages as $message) {
            $template = null;
            foreach ($templates as $t) {
                if ($t['ID'] === $message['TemplateID']) {
                    $template = $t;
                    break;
                }
            }
            
            if (!$template) continue;
            
            $locale = 'Chinese'; // 默认为中文
            
            $msgTitle = fillInTemplate($template['Subject'][$locale] ?? $template['Subject']['Chinese'], $message, $userInfo);
            $msgContent = fillInTemplate($template['Content'][$locale] ?? $template['Content']['Chinese'], $message, $userInfo);
            
            $items[] = [
                'id' => $message['ID'],
                'msg_title' => CustomTagParser::parse($msgTitle),
                'msg' => CustomTagParser::parse($msgContent),
                'msg_type' => convertCategoryIDToUIIndex($message['CategoryID']),
                'category' => isset($message['Fields']['User']) ? 'User' : 
                             (isset($message['Fields']['Discussion']) ? 'Discussion' : 'Experiment'),
                'tid' => $message['Fields']['UserID'] ?? 
                        $message['Fields']['DiscussionID'] ?? 
                        $message['Fields']['ExperimentID'] ?? '',
                'name' => $message['Fields']['Discussion'] ?? 
                         $message['Fields']['Experiment'] ?? 
                         $message['Fields']['User'] ?? '',
                'uid' => $message['Users'][0] ?? '',
                'avatar_number' => $message['AvatarNumber'] ?? 0 // 从消息中获取avatarNumber
            ];
        }
    }
    
    return $items;
}

// 处理不同的请求类型
$action = $_GET['action'] ?? '';
$notificationTypeIndexOfUI = $_GET['type'] ?? 0;

// 检查认证（API请求除外）
if ($action !== 'api' && $action !== 'getMessages' && $action !== 'getUserInfo') {
    checkAuth();
}

// 获取初始消息 - 一次加载100条
$initialItems = [];
if ($isLoggedIn) {
    $initialItems = getPagedMessages(convertUIIndexToCategoryID($notificationTypeIndexOfUI), 0, 20);
}

// 主页面显示
if ($action === '') {
    displayNotificationsPage();
    exit;
}

// API代理处理函数
function handleApiRequest() {
    $endpoint = $_GET['endpoint'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    if (empty($endpoint)) {
        http_response_code(400);
        echo json_encode(['error' => 'Endpoint is required']);
        return;
    }
    
    $data = [];
    if ($method === 'POST' || $method === 'PUT') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: [];
    }
    
    $response = callAPI($endpoint, $method, $data);
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

// 获取消息处理函数
function handleGetMessages() {
    $categoryID = $_POST['CategoryID'] ?? convertUIIndexToCategoryID($_GET['type'] ?? 0);
    $skip = $_POST['Skip'] ?? 0;
    $take = $_POST['Take'] ?? 100; // 改为100条
    
    $response = getMessages($categoryID, $skip, $take, false);
    
    if ($response['Status'] === 200) {
        // 处理消息数据
        global $dt;
        $userInfo = $dt['Data']['User'] ?? [];
        $templates = $response['Data']['Templates'] ?? [];
        $messages = $response['Data']['Messages'] ?? [];
        
        $processedMessages = [];
        foreach ($messages as $message) {
            $template = null;
            foreach ($templates as $t) {
                if ($t['ID'] === $message['TemplateID']) {
                    $template = $t;
                    break;
                }
            }
            
            if (!$template) continue;
            
            $locale = 'Chinese';
            
            $msgTitle = fillInTemplate($template['Subject'][$locale] ?? $template['Subject']['Chinese'], $message, $userInfo);
            $msgContent = fillInTemplate($template['Content'][$locale] ?? $template['Content']['Chinese'], $message, $userInfo);
            
            $processedMessages[] = [
                'id' => $message['ID'],
                'msg_title' => CustomTagParser::parse($msgTitle),
                'msg' => CustomTagParser::parse($msgContent),
                'msg_type' => convertCategoryIDToUIIndex($message['CategoryID']),
                'category' => isset($message['Fields']['User']) ? 'User' : 
                             (isset($message['Fields']['Discussion']) ? 'Discussion' : 'Experiment'),
                'tid' => $message['Fields']['UserID'] ?? 
                        $message['Fields']['DiscussionID'] ?? 
                        $message['Fields']['ExperimentID'] ?? '',
                'name' => $message['Fields']['Discussion'] ?? 
                         $message['Fields']['Experiment'] ?? 
                         $message['Fields']['User'] ?? '',
                'uid' => $message['Users'][0] ?? '',
                'avatar_number' => $message['AvatarNumber'] ?? 0
            ];
        }
        
        $response['Data']['ProcessedMessages'] = $processedMessages;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

// 获取用户信息处理函数
function handleGetUserInfo() {
    $userId = $_GET['userId'] ?? null;
    
    if (!$userId) {
        echo json_encode(['Status' => 400, 'Message' => '用户ID不能为空']);
        return;
    }
    
    $response = callAPI('/Users/GetUserInfo?userId=' . urlencode($userId));
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

// 显示通知页面
function displayNotificationsPage() {
    global $dt, $notificationTypeIndexOfUI, $isLoggedIn, $initialItems;
    
    // 输出HTML页面
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>通知 - Turtle Universe Web</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Arial','Microsoft YaHei',sans-serif;background-color:#f5f7fa;color:#333;line-height:1.6;padding:0;margin:0}.header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,0.1);position:sticky;top:0;z-index:1000}.header h2{font-size:20px;font-weight:bold}.user-info{display:flex;align-items:center;gap:12px;cursor:pointer;transition:opacity 0.3s ease}.user-info:hover{opacity:0.8}.user-info.login-prompt{cursor:pointer}.avatar{width:45px;height:45px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px}.user-details{display:flex;flex-direction:column}.nickname{font-weight:bold;font-size:16px}.level{font-size:12px;opacity:0.8}.content{padding:15px 10px 70px}.notification-container{padding:12px 15px;background:white;border-radius:12px;margin-bottom:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1);display:flex;align-items:flex-start;gap:12px;transition:transform 0.2s ease,box-shadow 0.2s ease}.notification-container:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}.notification-avatar{width:50px;height:50px;border-radius:50%;object-fit:cover;flex-shrink:0}.notification-content{flex:1;min-width:0}.notification-header{display:flex;align-items:center;gap:8px;margin-bottom:8px}.notification-icon{width:20px;height:20px;border-radius:4px;object-fit:contain}.notification-title{font-weight:bold;font-size:15px;color:#333;line-height:1.3;margin-bottom:6px}.notification-message{font-size:13px;color:#666;line-height:1.5;margin-bottom:8px}.notification-footer{display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#999}.notification-time{display:flex;align-items:center;gap:4px}.notification-type{background:#e3f2fd;color:#1976d2;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:500}.tabs-container{display:flex;overflow-x:auto;gap:0;background:white;padding:10px 15px;border-bottom:1px solid #eee;position:sticky;top:70px;z-index:999;-webkit-overflow-scrolling:touch;scrollbar-width:none}.tabs-container::-webkit-scrollbar{display:none}.tab{flex-shrink:0;padding:8px 16px;font-size:14px;color:#666;cursor:pointer;transition:all 0.3s ease;border-bottom:2px solid transparent;white-space:nowrap;text-decoration:none}.tab:hover{color:#667eea}.tab.active{color:#667eea;font-weight:bold;border-bottom-color:#667eea}.loading{text-align:center;padding:30px 0;color:#999}.empty-state{text-align:center;padding:60px 20px;color:#999}.empty-state i{font-size:48px;margin-bottom:15px;color:#ccc}.empty-state h3{font-size:18px;margin-bottom:10px;color:#666}.empty-state p{font-size:14px;color:#999}.footer{position:fixed;bottom:0;left:0;right:0;background:white;display:flex;justify-content:space-around;padding:10px 0;box-shadow:0 -2px 10px rgba(0,0,0,0.1);z-index:1000}.footer div{display:flex;flex-direction:column;align-items:center;gap:5px;font-size:12px;color:#666;transition:color 0.3s ease;cursor:pointer;text-decoration:none}.footer div.active{color:#667eea}.footer i{font-size:20px}.error{text-align:center;padding:40px 20px;color:#e74c3c}.retry-btn{background:#667eea;color:white;border:none;padding:10px 20px;border-radius:20px;margin-top:15px;cursor:pointer;transition:background 0.3s ease;text-decoration:none}.retry-btn:hover{background:#5a6fd8}.notification-container .h1,.notification-container .h2,.notification-container .h3,.notification-container .h4{font-weight:bold;margin:8px 0 4px 0;line-height:1.2}.notification-container .h1{font-size:18px}.notification-container .h2{font-size:16px}.notification-container .h3{font-size:15px}.notification-container .h4{font-size:14px}.notification-container .blue-tag{color:#0000FF;font-size:1em;text-decoration:none;border:none;padding:0;margin:0;background:none;font-weight:normal}.notification-container .experiment-link,.notification-container .discussion-link,.notification-container .model-link,.notification-container .external-link{color:#2881E0;font-size:1em;text-decoration:none;border:none;padding:0;margin:0 2px;background:none;font-weight:normal}.notification-container .experiment-link:hover,.notification-container .discussion-link:hover,.notification-container .model-link:hover,.notification-container .external-link:hover{text-decoration:none}.notification-container code{background-color:#f5f5f5;padding:2px 4px;border-radius:2px;font-family:monospace;font-size:0.9em;color:#c7254e}.notification-container del{color:#999;text-decoration:line-through}.notification-container strong{font-weight:600}.notification-container em{font-style:italic}.notification-container .RUser{color:#2881E0;cursor:pointer;text-decoration:none}.notification-container .RUser:hover{text-decoration:none}.no-more{text-align:center;padding:20px 0;color:#999;font-size:14px}a{text-decoration:none}@media (max-width:768px){.header{padding:12px 15px}.content{padding:15px 10px 70px}.avatar{width:40px;height:40px;font-size:18px}.nickname{font-size:14px}.notification-container{padding:10px 12px}.notification-avatar{width:45px;height:45px}.notification-title{font-size:14px}.notification-message{font-size:12px}}@media (max-width:480px){.tab{padding:8px 12px;font-size:13px}}
    </style>
</head>
<body>
    <header class="header">
        <h2>通知</h2>
    </header>

    <div class="tabs-container" id="tabsContainer">
        <a href="?type=0" class="tab <?= $notificationTypeIndexOfUI == 0 ? 'active' : '' ?>">全部</a>
        <a href="?type=1" class="tab <?= $notificationTypeIndexOfUI == 1 ? 'active' : '' ?>">系统消息</a>
        <a href="?type=2" class="tab <?= $notificationTypeIndexOfUI == 2 ? 'active' : '' ?>">关注和粉丝</a>
        <a href="?type=3" class="tab <?= $notificationTypeIndexOfUI == 3 ? 'active' : '' ?>">回复和评论</a>
        <a href="?type=4" class="tab <?= $notificationTypeIndexOfUI == 4 ? 'active' : '' ?>">作品</a>
        <a href="?type=5" class="tab <?= $notificationTypeIndexOfUI == 5 ? 'active' : '' ?>">管理通知</a>
    </div>

    <main class="content" id="content">
        <?php if (!$isLoggedIn): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>请先登录</h3>
            <p>登录后查看通知消息</p>
            <button class="retry-btn" onclick="location.href='getv.php?r=notification.php'">立即登录</button>
        </div>
        <?php elseif (empty($initialItems)): ?>
        <div class="empty-state">
            <i class="fas fa-bell"></i>
            <h3>暂无通知</h3>
            <p>还没有收到任何通知消息</p>
        </div>
        <?php else: ?>
        <div id="notificationsList">
            <?php foreach ($initialItems as $item): ?>
                <?php echo renderNotificationItem($item); ?>
            <?php endforeach; ?>
            <div class="no-more">
                <i class="fas fa-check-circle"></i> 已显示全部通知
            </div>
        </div>
        <?php endif; ?>
    </main>

    <div class="footer">
        <div onclick="location.href='index.php'"><i class="fas fa-home"></i><span>首页</span></div>
        <div><i class="fas fa-user"></i><span>我的</span></div>
        <div><i class="fas fa-water"></i><span>海水</span></div>
        <div><i class="fas fa-cube" onclick="location.href='model.php'"></i><span>模型库</span></div>
        <div class="active" onclick="location.href='nof.php'"><i class="fas fa-bell"></i><span>通知</span></div>
    </div>

    <script>
    // 用户卡片相关变量
    var currentCard = null;
    var isOpeningCard = false;
    var lastUid = null;
    
    // 防抖函数
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    
    // 获取用户卡片函数
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
        
        fetch('user/card.php?id=' + uid)
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
                        }, { once: true });
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
                isOpeningCard = false;
            });
    }
    
    // 使用防抖包装的getUserCard函数
    var debouncedGetUserCard = debounce(getUserCard, 300);
    
    // 用户卡片事件设置
    function setupUserCardEvents() {
        const overlay = document.getElementById('userCardOverlay');
        if (!overlay) return;
        
        // 关注按钮功能
        const followBtn = overlay.querySelector('#followBtn');
        const unfollowBtn = overlay.querySelector('#unfollowBtn');
        
        if (followBtn) {
            const newFollowBtn = followBtn.cloneNode(true);
            followBtn.parentNode.replaceChild(newFollowBtn, followBtn);
            
            newFollowBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('关注用户');
                
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
            const newUnfollowBtn = unfollowBtn.cloneNode(true);
            unfollowBtn.parentNode.replaceChild(newUnfollowBtn, unfollowBtn);
            
            newUnfollowBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('取消关注用户');
                
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
                isOpeningCard = false;
            }
        }
    }
    
    // 添加ESC键监听
    document.addEventListener('keydown', handleEscapeKey);
    
    // 辅助函数
    function getAvatarUrl(userId, avatarNumber = 0) {
        if (!userId || userId.length < 24) return '';
        const part1 = userId.substring(0, 4);
        const part2 = userId.substring(4, 6);
        const part3 = userId.substring(6, 8);
        const part4 = userId.substring(8, 24);
        return `http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/${part1}/${part2}/${part3}/${part4}/${avatarNumber}.jpg!full`;
    }
    
    function getMsgIconUrl(msgType) {
        const icons = {
            1: 'notifications_system.png',
            2: 'notifications_comments.png',
            3: 'notifications_followers.png',
            4: 'notifications_projects.png',
            5: 'notifications_admin.png'
        };
        const icon = icons[msgType] || 'notifications_system.png';
        return 'assets/icons/' + icon;
    }
    
    function getMsgTypeName(msgType) {
        const names = {
            1: '系统',
            2: '评论',
            3: '关注',
            4: '作品',
            5: '管理'
        };
        return names[msgType] || '消息';
    }
    
    function formatTimeAgo(id) {
        if (!id || id.length < 8) return '刚刚';
        try {
            const timestamp = parseInt(id.substring(0, 8), 16) * 1000;
            const now = Date.now();
            const diff = now - timestamp;
            
            if (diff < 60000) return '刚刚';
            if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前';
            if (diff < 86400000) return Math.floor(diff / 3600000) + '小时前';
            if (diff < 604800000) return Math.floor(diff / 86400000) + '天前';
            return new Date(timestamp).toLocaleDateString();
        } catch (e) {
            return '刚刚';
        }
    }
    
    // 页面加载时初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 处理RUser点击
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('RUser')) {
                e.stopPropagation();
                const userId = e.target.dataset.user;
                if (userId) {
                    debouncedGetUserCard(userId);
                }
            }
        });
        
        // 页面可见性变化时刷新
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                console.log('页面重新激活，可以刷新通知');
                // 可以在这里添加刷新逻辑，如果需要的话
            }
        });
        
        // 处理用户点击跳转到用户页面
        const rUsers = document.querySelectorAll('.RUser');
        rUsers.forEach(rUser => {
            rUser.addEventListener('click', function(e) {
                e.stopPropagation();
                const userId = this.getAttribute('data-user');
                if (userId) {
                    window.location.href = 'user.php?id=' + userId;
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
}

// 根据action分发请求
switch ($action) {
    case 'api':
        handleApiRequest();
        break;
        
    case 'getMessages':
        handleGetMessages();
        break;
        
    case 'getUserInfo':
        handleGetUserInfo();
        break;
        
    default:
        // 已经在上面处理了
        break;
}
?>
