<?php
session_start();
$username = "匿名用户";
$token = "";
$authCode = "";
$responseBody = ""; // 新增：用于存储响应主体

class AuthService {
    private $baseUrl = 'http://nlm-api-cn.turtlesim.com/';
    private $username;
    private $password;
    public $token;
    public $authCode;
    public $responseBody; // 新增：存储API响应主体

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    public function login() {
        $identifier = bin2hex(random_bytes(20));
        $identifier = str_pad($identifier, 40, '0', STR_PAD_LEFT);

        $requestData = [
            'Login' => $this->username,
            'Password' => $this->password,
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
        $this->responseBody = $response['body']; // 存储原始响应主体
        
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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            $authService = new AuthService($username, $password);
            $loginData = $authService->login();
            
            // 登录成功，保存到session
            $_SESSION['username'] = $username;
            $_SESSION['token'] = $authService->token;
            $_SESSION['authCode'] = $authService->authCode;
            $_SESSION['responseBody'] = $authService->responseBody; // 存储响应主体到session
            
            // 重定向或显示成功消息
            header('Location: /');
            exit;
        } else {
            $error = "用户名和密码不能为空";
        }
    }
} catch (Exception $e) {
    
}
?>
</pre>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>欢迎回来</h1>
            <p>请登录您的账户</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
                <?php if(isset($_SESSION['responseBody'])): ?>
                    <details style="margin-top: 10px;">
                        <summary>查看详细响应</summary>
                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;">
<?= htmlspecialchars($_SESSION['responseBody']) ?>
                        </pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert success">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <div class="form-group">
                <label for="username">邮箱/手机号</label>
                <input type="text" id="username" name="username" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autofocus
                       placeholder="请输入邮箱/手机号">
                <span class="input-icon">👤</span>
            </div>

            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password"
                       required
                       placeholder="请输入密码">
                <span class="input-icon">🔒</span>
            </div>

            <button type="submit" class="btn primary">立即登录</button>
            
            <div class="auth-footer">
                <span>没有账户？</span>
                <a href="register.php" class="link">立即注册</a>
            </div>
        </form>
    </div>
</div>
