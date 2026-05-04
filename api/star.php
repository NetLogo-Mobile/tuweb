<?php
session_start();
header('Content-Type: application/json');

// 验证登录
if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    echo json_encode(['Success' => false, 'Message' => 'Not logged in']);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$contentId = $_GET['id'] ?? '';
$category = $_GET['category'] ?? '';
$action = $_GET['action'] ?? '';
$status = isset($_GET['status']) ? (bool)$_GET['status'] : true;

if (empty($contentId) || empty($category) || !in_array($action, ['star', 'support'])) {
    echo json_encode(['Success' => false, 'Message' => 'Invalid parameters']);
    exit;
}

$baseUrl = 'http://nlm-api-cn.turtlesim.com/';
$url = $baseUrl . 'Contents/StarContent';

$starType = $action === 'star' ? 0 : 1; // 0=Star, 1=Support

$requestData = [
    'ContentID' => $contentId,
    'Category' => $category,
    'Type' => $starType,
    'Status' => $status
];

$headers = [
    "Content-Type: application/json",
    "x-API-Token: " . $token,
    "x-API-AuthCode: " . $authCode,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($body, true);
$success = ($httpCode == 200 && isset($data['Status']) && $data['Status'] == 200);

echo json_encode([
    'Success' => $success,
    'Data' => $data
]);
?>