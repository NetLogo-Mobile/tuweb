<?php
session_start();

// ==================== 配置区域 ====================
define('EXPERIMENTS_DIR', __DIR__ . '/experiments/');
define('MODEL_LIFETIME', 60);
define('NETLOGO_BASE', 'https://netlogoweb.org');
// =================================================

// 主处理逻辑
$modelId = $_GET['id'] ?? '';
$action = $_GET['action'] ?? '';

if ($action === 'model') {
    handleModelFile();
    exit();
}

// 如果没有模型ID，直接结束
if (empty($modelId)) {
    echo '请在URL中添加参数 id=模型ID';
    exit();
}

// 处理模型并跳转
handleModelRedirect($modelId);
exit();

// ==================== 核心功能函数 ====================

/**
 * 处理模型并跳转到NetLogo Web
 */
function handleModelRedirect($contentId) {
    // 获取并保存模型
    $modelInfo = fetchAndSaveModel($contentId);
    
    if (!$modelInfo || !$modelInfo['success']) {
        echo '模型获取失败';
        exit();
    }
    
    // 获取模型URL
    $modelUrl = getModelUrl($modelInfo['filename']);
    
    // 直接跳转到NetLogo Web的model.html页面
    $redirectUrl = 'model.html?url=' . $modelUrl;
    header('Location: ' . $redirectUrl);
    exit();
}

/**
 * 获取并保存模型
 */
function fetchAndSaveModel($contentId) {
    $result = getSav($contentId, $_SESSION['token'] ?? '', $_SESSION['authCode'] ?? '');
    
    if (isset($result['error']) || $result['http_status'] !== 200) {
        return ['success' => false];
    }
    
    // 提取模型内容
    if (!isset($result['Data']['ModelCode']) || !$result['Data']['ModelCode']) {
        return ['success' => false];
    }
    
    // 保存文件
    $filename = $contentId . '.nlogo';
    $filepath = EXPERIMENTS_DIR . $filename;
    
    if (file_put_contents($filepath, $result['Data']['ModelCode']) !== false) {
        return [
            'success' => true,
            'filename' => $filename,
        ];
    }
    
    return ['success' => false];
}

/**
 * 处理模型文件请求
 */
function handleModelFile() {
    $filename = $_GET['file'] ?? '';
    
    if (!$filename) {
        echo '缺少文件参数';
        exit();
    }
    
    // 安全过滤文件名
    $filename = basename($filename);
    $filepath = EXPERIMENTS_DIR . $filename;
    
    if (!file_exists($filepath)) {
        echo '模型文件不存在';
        exit();
    }
    
    header('Content-Type: text/plain');
    header('Content-Disposition: inline');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    readfile($filepath);
    exit();
}

/**
 * 获取模型URL
 */
function getModelUrl($filename) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'tuweb.page.gd';
    $path = dirname($_SERVER['PHP_SELF'] ?? '/experiments');
    
    if (substr($path, -1) !== '/') {
        $path .= '/';
    }
    
    return $protocol . '://' . $host . $path . 'experiments/' . urlencode($filename);
}

/**
 * 清理过期文件
 */
function cleanupExpiredFiles() {
    if (!is_dir(EXPERIMENTS_DIR)) return;
    
    $files = scandir(EXPERIMENTS_DIR);
    $now = time();
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $filePath = EXPERIMENTS_DIR . $file;
        
        if (file_exists($filePath) && is_file($filePath)) {
            $fileTime = filemtime($filePath);
            
            if (($now - $fileTime) > MODEL_LIFETIME && strpos($file,'.nlogo') !== false) {
                @unlink($filePath);
            }
        }
    }
}

/**
 * 获取模型数据
 */
function getSav($contentId, $token, $authCode) {
    $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    $url = $baseUrl . 'Contents/GetWorkspace';
    
    $requestData = ['ContentID' => $contentId];
    
    $headers = [
        "Content-Type: application/json",
        "Accept: application/json",
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
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        return ['error' => 'CURL Error'];
    }
    
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    $responseData = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'JSON Parse Error'];
    }
    
    $responseData['http_status'] = $statusCode;
    
    return $responseData;
}
?>
