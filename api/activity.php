<?php
// /api/activity.php
session_start();

// 解析 action 参数：sync 同步进度，receive 领取奖励
$action = $_GET['action'] ?? 'receive';
$endpoint = '';
if ($action === 'sync') {
    $endpoint = 'Users/SyncActivities';
} else {
    $endpoint = 'Users/ReceiveBonus';
}

$url = 'https://nlm-api-cn.turtlesim.com/' . $endpoint;
$inputJSON = file_get_contents('php://input');

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($inputJSON),
    'x-API-Token: ' . $_SESSION['token'],
    'x-API-AuthCode: ' . $_SESSION['authCode'],
    'User-Agent: TurtleUniverseWeb/1.0'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 前端错误捕获与兜底
if ($response === false) {
    http_response_code(500);
    echo json_encode(['Status' => 500, 'Message' => 'Internal Proxy Error: ' . $curlError]);
    exit;
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    // 防止 API 返回空 Body 导致前端解析崩溃
    if (empty($response)) {
        echo json_encode(['Status' => $httpCode, 'Message' => 'API server returned an empty error response.']);
    } else {
        echo $response;
    }
    exit;
}

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;
exit;