<?php
session_start();

// 获取参数，category首字母大写
$category = isset($_GET['category']) ? ucfirst($_GET['category']) : 'Model';
$contentId = $_GET['id'] ?? '';
$skp = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;

// 获取用户数据
$dt = null;
if (isset($_SESSION['responseBody'])) {
    $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
} else {
    $redirectUrl = '/getv.php?r=' . urlencode('med.php?category=' . $category . '&id=' . $contentId . '&skip=' . $skp);
    header('Location: ' . $redirectUrl);
    exit;
}

// 类形式的实现
class MessageService {
    private $token;
    private $authCode;
    private $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    
    public function __construct($token = null, $authCode = null) {
        $this->token = $token;
        $this->authCode = $authCode;
    }
    
    /**
     * 获取留言/评论信息
     */
    public function getMessages(
        $ID, 
        $type = "Discussion", 
        $take = 20, 
        $from = null, 
        $skip = 0
    ) {
        // 验证必需参数
        if (empty($ID)) {
            throw new Exception("ID参数不能为空");
        }
        
        if (empty($type)) {
            throw new Exception("类型参数不能为空");
        }
        
        if ($take > 100) {
            throw new Exception("消息获取数量一次最多为100条");
        }
        
        $take = -$take;
        
        $requestData = [
            'TargetID' => $ID,
            'TargetType' => $type,
            'Take' => $take,
            'Skip' => $skip,
        ];
        
        // 只有在提供了from参数时才添加CommentID字段
        if ($from !== null) {
            $requestData['CommentID'] = $from;
        }
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Language: zh-CN',
        ];
        
        if ($this->token && !empty($this->token)) {
            $headers[] = 'x-API-Token: ' . $this->token;
        }
        
        if ($this->authCode && !empty($this->authCode)) {
            $headers[] = 'x-API-AuthCode: ' . $this->authCode;
        }
        
        $url = $this->baseUrl . 'Messages/GetComments';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("请求失败: " . $error);
        }
        
        if ($httpCode !== 200) {
            $errorInfo = '';
            if ($response) {
                $responseData = json_decode($response, true);
                if (isset($responseData['Message'])) {
                    $errorInfo = ' - ' . $responseData['Message'];
                }
            }
            throw new Exception("API请求失败，HTTP状态码: " . $httpCode . $errorInfo);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON解析失败: " . json_last_error_msg());
        }
        
        // 检查API返回的业务状态码
        if (isset($data['Status']) && $data['Status'] !== 200) {
            $errorMsg = $data['Message'] ?? '未知错误';
            throw new Exception("API返回错误: " . $errorMsg);
        }
        
        return $data;
    }
    
    // 设置认证信息
    public function setAuth($token, $authCode) {
        $this->token = $token;
        $this->authCode = $authCode;
        return $this;
    }
}

// 类形式的使用示例：
$messageService = new MessageService($_SESSION['token'] ?? null, $_SESSION['authCode'] ?? null);
try {
    $messages = $messageService->getMessages($contentId, $category, 20, null, $skp);
    $target = $messages['Data']['Target'] ?? [];
    $comments = $messages['Data']['Comments'] ?? [];
    $totalComments = $messages['Data']['Count'] ?? 0;
} catch (Exception $e) {
    $error = $e->getMessage();
}

// 生成头像URL函数
function getAvatarUrl($userID, $avatarNumber) {
    // 根据用户ID构建头像路径
    $part1 = substr($userID, 0, 4);
    $part2 = substr($userID, 4, 2);
    $part3 = substr($userID, 6, 2);
    $part4 = substr($userID, 8, 16);
    
    return "http://netlogo-static-cn.turtlesim.com/users/avatars/{$part1}/{$part2}/{$part3}/{$part4}/{$avatarNumber}.jpg!full";
}

// 格式化时间函数
function formatCommentTime($timestamp) {
    if (!$timestamp) return '未知时间';
    
    // 如果是毫秒时间戳，转换为秒
    if ($timestamp > 9999999999) {
        $timestamp = $timestamp / 1000;
    }
    
    $date = date('n/j', $timestamp);
    $hour = date('G', $timestamp);
    $minute = date('i', $timestamp);
    
    // 转换为12小时制并添加上午/下午
    if ($hour < 12) {
        $period = '上午';
        $displayHour = $hour == 0 ? 12 : $hour;
    } else {
        $period = '下午';
        $displayHour = $hour > 12 ? $hour - 12 : $hour;
    }
    
    return "{$date} {$period}{$displayHour}:{$minute}";
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
            '<a href="med.php?id=$1&category=Experiment" class="experiment-link">$2</a>',
            // discussion标签
            '<a href="med.php?id=$1&category=Discussion" class="discussion-link">$2</a>',
            // model标签
            '<a href="med.php?id=$1&category=Model" class="model-link">$2</a>',
            // external标签
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" class="external-link">$2</a>',
            // size标签
            '<span style="font-size: $1;">$2</span>',
            // color标签 - 新增
            '<span style="color: $1;">$2</span>',
            // b标签
            '<strong>$1</strong>',
            // i标签
            '<em>$1</em>',
            // a标签
            '<span class="blue-tag">$1</span>'
        ];
        
        $text = preg_replace($patterns, $replacements, $text);
        
        // 解析简单的Markdown格式
        $text = self::parseSimpleMarkdown($text);
        
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
?>
<style>
        #notification_container {
            display: flex;
            padding: 12px 0;
            align-items: flex-start;
        }
        
        .img {
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        #avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e0e0e0;
        }
        
        .notification {
            flex: 1;
            min-width: 0;
        }
        
        .notification_title {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .name {
            font-weight: 600;
            font-size: 15px;
            color: #1a1a1a;
            flex: 1;
        }
        
        .time {
            font-size: 13px;
            color: #999;
            margin-left: 8px;
        }
        
        .notification_message {
            margin-top: 2px;
        }
        
        .notification_text {
            font-size: 15px;
            color: #333;
            line-height: 1.4;
            word-break: break-word;
        }
        
        .RUser {
            color: #1890ff;
            font-weight: 500;
        }
        
        .n-divider {
            display: flex;
            align-items: center;
            margin: 16px 0;
        }
        
        .n-divider__line {
            flex: 1;
            height: 1px;
            background-color: #efeff5;
        }
        
        .n-divider__line--left {
            margin-right: 0;
        }
        
        .observer-element {
            height: 20px;
        }
        
        .error-message {
            background: #fff2f0;
            border: 1px solid #ffccc7;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 16px;
            color: #a8071a;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #d9d9d9;
        }
</style>
<style>
/* <a>标签样式 - 深蓝色标签，无边框，大小与普通字体一样 */
.blue-tag {
    color: #0000FF;
    font-size: 1em;
    text-decoration: none;
    border: none;
    padding: 0;
    margin: 0;
    background: none;
    font-weight: normal;
}

/* 会跳转的标签样式 - 包括experiment, discussion, model, external */
.experiment-link,
.discussion-link,
.model-link,
.external-link {
    color: #2881E0;
    font-size: 1em;
    text-decoration: none;
    border: none;
    padding: 0;
    margin: 0 2px;
    background: none;
    font-weight: normal;
}

.experiment-link:hover,
.discussion-link:hover,
.model-link:hover,
.external-link:hover {
    text-decoration: underline;
}

/* color标签样式 - 继承父元素字体大小 */
.notification_text [style*="color:"] {
    font-size: inherit;
    border: none;
    padding: 0;
    margin: 0;
    background: none;
}

/* Markdown代码样式 */
.notification_text code {
    background-color: #f5f5f5;
    padding: 2px 4px;
    border-radius: 2px;
    font-family: monospace;
    font-size: 0.9em;
    color: #c7254e;
}

.notification_text del {
    color: #999;
    text-decoration: line-through;
}

.notification_text strong {
    font-weight: 600;
}

.notification_text em {
    font-style: italic;
}
</style>

<div id="top-con"></div>
<div class="scroll-container">
    <?php if (isset($error)): ?>
        <div class="error-message">
            <strong>加载失败：</strong><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($comments)): ?>
        <div class="empty-state">
            <p>暂无评论</p>
        </div>
    <?php else: ?>
        <?php foreach ($comments as $index => $comment): ?>
            <div>
                <div id="notification_container" data-rid="<?= htmlspecialchars($comment['UserID'] ?? '') ?>">
                    <div class="img">
                        <img id="avatar" 
                             src="<?= getAvatarUrl($comment['UserID'] ?? '', $comment['Avatar'] ?? 0) ?>" 
                             alt="<?= htmlspecialchars($comment['Nickname'] ?? '用户') ?>"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiByeD0iMjAiIGZpbGw9IiNGRjVFNjYiLz4KPHN2ZyB4PSI4IiB5PSI4IiB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyQzIgMTcuNTIgNi40OCAyMiAxMiAyMkMxNy41MiAyMiAyMiAxNy41MiAyMiAxMkMyMiA2LjQ4IDE3LjUyIDIgMTIgMlpNMTIgNUMxMy42NiA1IDE1IDYuMzQgMTUgOEMxNSA5LjY2IDEzLjY2IDExIDEyIDExQzEwLjM0IDExIDkgOS42NiA5IDhDOSA2LjM0IDEwLjM0IDUgMTIgNVpNMTIgMTkuMkM5LjUgMTkuMiA3LjIxIDE3LjkyIDYgMTUuOThDNi4wMSAxMy45OSAxMCAxMi45IDEyIDEyLjlDMTMuOTkgMTIuOSAxNy45OSAxMy45OSAxOCAxNS45OEMxNi43OSAxNy45MiAxNC41IDE5LjIgMTIgMTkuMloiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo8L3N2Zz4K'">
                    </div>
                    <div id="notification" class="notification">
                        <div id="notification_title" class="notification_title">
                            <div class="name"><?= htmlspecialchars($comment['Nickname'] ?? '匿名用户') ?></div>
                            <div class="time"><?= formatCommentTime($comment['Timestamp'] ?? 0) ?></div>
                        </div>
                        <div id="notification_message" class="notification_message">
                            <div id="notification_text" class="notification_text">
                                <?= CustomTagParser::parse($comment['Content'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($index < count($comments) - 1): ?>
                <div role="separator" class="n-divider n-divider--no-title" style="--n-bezier: cubic-bezier(0.4, 0, 0.2, 1); --n-color: rgb(239, 239, 245); --n-text-color: rgb(31, 34, 37); --n-font-weight: 500; margin: 0px;">
                    <div class="n-divider__line n-divider__line--left"></div>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div id="bottom-con" style="margin-top: 0px;"></div>

<script>
// 添加点击交互功能
document.addEventListener('DOMContentLoaded', function() {
    // 点击用户头像或名称时跳转到用户页面
    const userElements = document.querySelectorAll('#notification_container');
    userElements.forEach(container => {
        const avatar = container.querySelector('#avatar');
        const name = container.querySelector('.name');
        const userId = container.getAttribute('data-rid');
        
        const onClick = function() {
            // 跳转到用户页面
            if (userId) {
                window.location.href = 'user.php?id=' + userId;
            }
        };
        
        if (avatar) avatar.addEventListener('click', onClick);
        if (name) name.addEventListener('click', onClick);
    });
    
    // 点击@用户时跳转到对应用户页面
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
