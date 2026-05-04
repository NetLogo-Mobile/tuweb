<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    http_response_code(401);
    echo json_encode(['Success' => false, 'Message' => 'Not logged in']);
    exit;
}

$token    = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$userId      = $_GET['id'] ?? '';
$displayType = (int)($_GET['displayType'] ?? 1);
$skip        = (int)($_GET['skip'] ?? 0);
$take        = (int)($_GET['take'] ?? 20);

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['Success' => false, 'Message' => 'Missing user id']);
    exit;
}

// 先取负，再放入请求体
$take = -$take;

$body = json_encode([
    'UserID'      => $userId,
    'DisplayType' => $displayType,
    'Skip'        => $skip,
    'Take'        => $take,
]);

$headers = [
    'Content-Type: application/json',
    'x-API-Token: ' . $token,
    'x-API-AuthCode: ' . $authCode,
];

$url = 'http://nlm-api-cn.turtlesim.com/Users/GetRelations';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_TIMEOUT        => 10,
]);

$response   = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$bodyResp   = substr($response, $headerSize);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($bodyResp, true);
$success = ($httpCode == 200 && isset($data['Status']) && $data['Status'] == 200);

// ----- 关键修复：处理 .NET 包装的 $values -----
$result = $data['Data'] ?? null;
if ($result && isset($result['$values']) && is_array($result['$values'])) {
    $result = $result['$values'];
}

echo json_encode([
    'Success' => $success,
    'Data'    => $result,
    'Message' => $data['Message'] ?? ''
]);