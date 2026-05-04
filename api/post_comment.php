<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    echo json_encode(['Success' => false, 'Message' => 'Not logged in']);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

// 获取 POST 请求体
$input = json_decode(file_get_contents('php://input'), true);
$targetId   = $input['TargetID'] ?? '';
$targetType = $input['TargetType'] ?? 'User';
$content    = $input['Content'] ?? '';
$replyId    = $input['ReplyID'] ?? '';

if (empty($targetId) || empty($content)) {
    echo json_encode(['Success' => false, 'Message' => 'Missing required fields']);
    exit;
}

$url = 'http://nlm-api-cn.turtlesim.com/Messages/PostComment';
$body = json_encode([
    'TargetID'   => $targetId,
    'TargetType' => $targetType,
    'Content'    => $content,
    'ReplyID'    => $replyId,
    'Language'   => 'Chinese'
]);

$headers = [
    'Content-Type: application/json',
    'x-API-Token: ' . $token,
    'x-API-AuthCode: ' . $authCode,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$bodyResp = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($bodyResp, true);
$success = ($httpCode == 200 && isset($data['Status']) && $data['Status'] == 200);

echo json_encode(['Success' => $success, 'Data' => $data['Data'] ?? null, 'Message' => $data['Message'] ?? '']);
?>