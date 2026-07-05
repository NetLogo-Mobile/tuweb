<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    http_response_code(401);
    echo json_encode(['Status' => 401, 'Message' => 'Not logged in']);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$rawInput = file_get_contents('php://input');
if ($rawInput === false || strlen($rawInput) === 0) {
    http_response_code(400);
    echo json_encode(['Status' => 400, 'Message' => 'Empty request body']);
    exit;
}

$targetUrl = 'https://nlm-api-cn.turtlesim.com/Contents/SubmitExperiment';

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Accept-Language: zh-CN',
    'x-API-Version: 2411',
    'x-API-Token: ' . $token,
    'x-API-AuthCode: ' . $authCode,
];

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,           // 不返回头部
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $rawInput,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['Status' => 500, 'Message' => 'Proxy request failed: ' . $curlError]);
    exit;
}

// 直接返回 API 的原始响应（状态码和内容）
http_response_code($httpCode);
echo $response;