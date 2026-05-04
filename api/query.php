<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['token']) || !isset($_SESSION['authCode'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$token    = $_SESSION['token'];
$authCode = $_SESSION['authCode'];

$category  = $_GET['category']  ?? 'Model';
$userId    = trim($_GET['userId'] ?? '');
$tagsParam = trim($_GET['tags']   ?? '');
$sort      = (int)($_GET['sort']  ?? 0);
$skip      = (int)($_GET['skip']  ?? 0);
$take      = min((int)($_GET['take'] ?? 20), 100);
$from      = trim($_GET['from']   ?? '');

// 标签处理
$tags = [];
if ($tagsParam !== '') {
    $raw = explode(',', $tagsParam);
    foreach ($raw as $t) {
        $t = trim($t);
        if ($t !== '') $tags[] = $t;
    }
    $tags = array_values(array_unique($tags));
}

// 构造请求 Query
$query = [
    'Category'          => $category,
    'Languages'         => [],
    'ExcludeLanguages'  => null,
    'Tags'              => $tags,
    'ModelTags'         => null,
    'ExcludeTags'       => null,
    'ModelID'           => null,
    'ParentID'          => null,
    'UserID'            => $userId !== '' ? $userId : null,
    'Special'           => null,
    'From'              => $from !== '' ? $from : null,
    'Skip'              => $skip,
    'Take'              => -$take,
    'Days'              => 0,
    'Sort'              => $sort,
    'ShowAnnouncement'  => false,
];

$requestBody = json_encode(['Query' => $query], JSON_UNESCAPED_UNICODE);

$url = 'http://nlm-api-cn.turtlesim.com/Contents/QueryExperiments';
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Accept-Language: zh-CN',
    'x-API-Token: ' . $token,
    'x-API-AuthCode: ' . $authCode,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_TIMEOUT        => 15,
]);

$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body       = substr($response, $headerSize);
$curlError  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'CURL error: ' . $curlError]);
    exit;
}
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => "Upstream HTTP $httpCode: $body"]);
    exit;
}

$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid upstream JSON']);
    exit;
}
if (($data['Status'] ?? 200) !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'API error: ' . ($data['Message'] ?? 'Unknown')]);
    exit;
}

// 处理 .NET 可能的多层包装
$result = $data['Data'] ?? [];
if (isset($result['$values'])) {
    $result = $result['$values'];
}
if (!is_array($result)) {
    http_response_code(502);
    echo json_encode(['error' => 'Data is not array: ' . json_encode($result)]);
    exit;
}

echo json_encode(['Data' => $result, 'Status' => 200]);