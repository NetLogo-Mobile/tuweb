<?php
session_start();

// 设置响应头为JSON
header('Content-Type: application/json');

$id = $_GET['id'] ?? '';
$content = $_GET['con'] ?? '';
$type = $_GET['category'] ?? 'Discussion';
$rid = $_GET['rid'] ?? '';

// 检查必需参数
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['error' => '缺少ID参数']);
    exit;
}

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => '缺少内容参数']);
    exit;
}

// 检查登录状态
if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登录或会话已过期']);
    exit;
}

function sendMessages(
    $TargetID,
    $Content,
    $TargetType = "Discussion",
    $ReplyID = "",
    $token = null,
    $authCode = null
) {
    // 验证必需参数
    if (empty($TargetID)) {
        throw new Exception("TargetID参数不能为空");
    }
    
    if (empty($Content)) {
        throw new Exception("消息内容不能为空");
    }
    
    $baseUrl = "http://nlm-api-cn.turtlesim.com/";
    
    // 准备请求数据
    $requestData = [
        'TargetID' => $TargetID,
        'TargetType' => $TargetType,
        'Language' => "Chinese",
        'ReplyID' => $ReplyID,
        'Content' => $Content,
        'Special' => null,
    ];
    
    // 准备请求头
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Language: zh-CN',
    ];
    
    // 添加认证头
    if ($token && !empty($token)) {
        $headers[] = 'x-API-Token: ' . $token;
    }
    
    if ($authCode && !empty($authCode)) {
        $headers[] = 'x-API-AuthCode: ' . $authCode;
    }
    
    // 发送POST请求
    $url = $baseUrl . 'Messages/PostComment';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 错误处理
    if ($error) {
        throw new Exception("请求失败: " . $error);
    }
    
    if ($httpCode !== 200) {
        // 尝试从响应中获取更多错误信息
        $errorInfo = '';
        if ($response) {
            $responseData = json_decode($response, true);
            if (isset($responseData['Message'])) {
                $errorInfo = ' - ' . $responseData['Message'];
            }
        }
        throw new Exception("API请求失败，HTTP状态码: " . $httpCode . $errorInfo);
    }
    
    // 解析JSON响应
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

try {
    // 修复参数顺序问题
    $result = sendMessages(
        $id,           // TargetID
        $content,      // Content
        $type,         // TargetType
        $rid,          // ReplyID (空字符串)
        $_SESSION['token'] ?? null,     // token
        $_SESSION['authCode'] ?? null   // authCode
    );
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
