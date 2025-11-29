<?php
session_start();

// 如果已登录，重定向到首页
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    header('Location: /');
    exit;
}

$username = "";
$token = "";
$authCode = "";
$responseBody = "";
$errorMsg = ""; // 新增：用于存储错误信息

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
        
        // 检查JSON解码是否成功
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("API响应格式错误: " . json_last_error_msg());
        }
        
        // 优先检查业务状态码
        if (isset($responseData['Status'])) {
            if ($responseData['Status'] === 200) {
                $this->token = $responseData['Token'] ?? '';
                $this->authCode = $responseData['AuthCode'] ?? '';
                return $responseData;
            } else {
                $errorMsg = $responseData['Message'] ?? '登录失败';
                throw new Exception($errorMsg);
            }
        } 
        // 处理 HTTP 错误
        elseif ($statusCode >= 400) {
            $errorMap = [
                403 => '访问被拒绝(403)',
                404 => '资源不存在(404)',
                500 => '服务器错误(500)'
            ];
            $errorMsg = $errorMap[$statusCode] ?? "HTTP 错误: $statusCode";
            throw new Exception($errorMsg);
        } 
        // 完全无效的响应
        else {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authService = new AuthService();
        $loginData = $authService->login();
        
        // 登录成功，保存到session
        $_SESSION['username'] = $loginData['Data']['User']['Nickname'] ?? '匿名用户';
        $_SESSION['token'] = $authService->token;
        $_SESSION['authCode'] = $authService->authCode;
        $_SESSION['responseBody'] = $authService->responseBody;
        
        // 重定向到首页
        header('Location: /');
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}
?>
