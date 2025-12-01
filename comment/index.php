<?php
session_start();

// 获取参数
$category = $_GET['category'] ?? 'Model';
$contentId = $_GET['id'] ?? '';
$skp = number_format($_GET['skip']) ?? 0;

// 获取用户数据
$dt = null;
if (isset($_SESSION['responseBody'])) {
    $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
} else {
  $redirectUrl = '/getv.php?r=' . urlencode('med.php?category=' . $category . '&id=' . $contentId . '&skip=' . $skp);
  header('Location: ' . $redirectUrl);
  exit;
}

// 类形式的实现（可选）
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
$messageService = new MessageService($_SESSION['token'], $_SESSION['authCode']);
try {
    $messages = $messageService->getMessages($contentId, $category, 20);
    $target = $messages['Data']['Target'] ?? [];
    $comments = $messages['Data']['Comments'] ?? [];
    $totalComments = $messages['Data']['Count'] ?? 0;
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
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

// 处理回复内容中的@用户标签
function processReplyContent($content) {
    // 匹配 <user=用户ID>@用户名 格式
    $pattern = '/<user=([a-f0-9]+)>([^<]+)<\/user>/';
    $replacement = '<span class="RUser" data-user="$1">$2</span>';
    
    return preg_replace($pattern, $replacement, $content);
}
?>
    <style>
       /* * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .scroll-container {
            max-width: 80vh;
            margin: 0 auto;
            padding: 16px;
        }*/
        
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
                    <div id="notification_container" data-rid="<?= $comment['UserID'] ?? '' ?>">
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
                                    <?= processReplyContent($comment['Content'] ?? '') ?>
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
            
            <div class="observer-element" style="margin-top: 0px;"></div>
        <?php endif; ?>
    </div>
