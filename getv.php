<?php
// getv.php - 替换原重定向逻辑，改为被 include 的认证模块
// 保证变量名与原 index.php 等页面期望的完全一致：$dt, $isLoggedIn, $errorMsg

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 初始化变量（与原页面中的初始化逻辑一致）
$dt = null;
$isLoggedIn = false;
$errorMsg = "";   // 原 getv.php 中定义的错误变量

// 1. 从 session 解析 $dt
if (isset($_SESSION['responseBody'])) {
    $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
}

// 2. 判断登录状态（使用原 index.php 的判断方式）
$isLoggedIn = isset($dt['Data']['User']['Nickname']) && $dt['Data']['User']['Nickname'] !== '点击登录';

// 3. 如果已登录，直接返回，让页面继续渲染（不执行任何重定向）
if ($isLoggedIn) {
    return;
}

// 4. 未登录：执行自动登录（原 getv.php 中的逻辑，但去掉最后的重定向）
$r = urldecode($_GET['r'] ?? '');   // 原参数保留，但不再用于跳转

class AuthService {
    private $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    public $token;
    public $authCode;
    public $responseBody;

    public function login() {
        $identifier = bin2hex(random_bytes(20));
        $identifier = str_pad($identifier, 40, '0', STR_PAD_LEFT);

        $requestData = [
            'Login' => '',
            'Password' => '',
            'Version' => 2411,
            'Device' => [
                'Identifier' => $identifier,
                'Language' => "Chinese",
            ],
            'Statistic' => null,
        ];

        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Accept-Language: zh-CN",
        ];

        $response = $this->postRequest("Users/Authenticate", $requestData, $headers);
        $statusCode = $response['status'];
        $this->responseBody = $response['body'];
        
        $responseData = json_decode($this->responseBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("API响应格式错误: " . json_last_error_msg());
        }
        
        if (isset($responseData['Status'])) {
            if ($responseData['Status'] === 200) {
                $this->token = $responseData['Token'] ?? '';
                $this->authCode = $responseData['AuthCode'] ?? '';
                return $responseData;
            } else {
                $errorMsg = $responseData['Message'] ?? '登录失败';
                throw new Exception($errorMsg);
            }
        } elseif ($statusCode >= 400) {
            $errorMap = [
                403 => '访问被拒绝(403)',
                404 => '资源不存在(404)',
                500 => '服务器错误(500)'
            ];
            $errorMsg = $errorMap[$statusCode] ?? "HTTP 错误: $statusCode";
            throw new Exception($errorMsg);
        } else {
            throw new Exception("无效的API响应");
        }
    }

    private function postRequest($endpoint, $data, $headers = []) {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("请求失败: $error");
        }
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        curl_close($ch);
        
        return [
            'status' => $statusCode,
            'body' => $body
        ];
    }
}

try {
    $authService = new AuthService();
    $loginData = $authService->login();
    $_SESSION['token'] = $authService->token;
    $_SESSION['authCode'] = $authService->authCode;
    $_SESSION['responseBody'] = $authService->responseBody;
    
    // 登录成功后，重新解析 $dt 和 $isLoggedIn
    if (isset($_SESSION['responseBody'])) {
        $dt = is_string($_SESSION['responseBody']) ? json_decode($_SESSION['responseBody'], true) : $_SESSION['responseBody'];
        $isLoggedIn = isset($dt['Data']['User']['Nickname']) && $dt['Data']['User']['Nickname'] !== '点击登录';
    }
    // 注意：不发送任何 header('Location:')，也不 exit
    return;
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    // 不 echo 也不 exit，只设置错误变量
    // 原有 getv.php 中 catch 块是 echo $errorMsg; 但没有 exit，
    // 为了不破坏页面布局，我们也不输出（或者可以保留 echo 但需谨慎）
    // 如果希望保留原有行为（输出错误），可以取消下行注释
    // echo $errorMsg;
}
// 脚本结束，返回到包含它的页面
?>