<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    echo json_encode(['Success' => false, 'Message' => 'Not logged in']);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$targetId = $_GET['id'] ?? '';
$action = isset($_GET['action']) ? (int)$_GET['action'] : 1; // 1=关注, 0=取消

if (empty($targetId)) {
    echo json_encode(['Success' => false, 'Message' => 'Missing user id']);
    exit;
}

$url = 'http://nlm-api-cn.turtlesim.com/Users/Follow';
$body = json_encode([
    'TargetID' => $targetId,
    'Action' => $action
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

echo json_encode(['Success' => $success]);
?>
