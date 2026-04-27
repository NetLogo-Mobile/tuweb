<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    echo json_encode(['Success' => false, 'Message' => 'Not logged in']);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$userId      = $_GET['id'] ?? '';
$displayType = (int)($_GET['displayType'] ?? 1); // 1=Follower, 2=Following
$skip        = (int)($_GET['skip'] ?? 0);
$take        = (int)($_GET['take'] ?? 20);

if (empty($userId)) {
    echo json_encode(['Success' => false, 'Message' => 'Missing user id']);
    exit;
}

$url = 'http://nlm-api-cn.turtlesim.com/Users/GetRelations';
$body = json_encode([
    'UserID'      => $userId,
    'DisplayType' => $displayType,
    'Skip'        => $skip,
    'Take'        => $take
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