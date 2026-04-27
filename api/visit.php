<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    echo json_encode(['Success' => false]);
    exit;
}

$token = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$contentId = $_GET['id'] ?? '';
$category = $_GET['category'] ?? '';

if (empty($contentId) || empty($category)) {
    echo json_encode(['Success' => false]);
    exit;
}

$baseUrl = 'http://nlm-api-cn.turtlesim.com/';
$url = $baseUrl . 'Contents/VisitExperiment';

$requestData = [
    'SummaryID' => $contentId,
    'Category' => $category
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
    CURLOPT_TIMEOUT => 5,
]);

curl_exec($ch);
curl_close($ch);

echo json_encode(['Success' => true]);
?>