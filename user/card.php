<?php
session_start();
$baseUrl = "http://nlm-api-cn.turtlesim.com/";
$id = $_GET['id'] ?? '';
$requestData = ['ID' => $id];

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Accept-Language: zh-CN',
    'x-API-Token: ' . ($_SESSION['token'] ?? ''),
    'x-API-AuthCode: ' . ($_SESSION['authCode'] ?? '')
];

$url = $baseUrl . 'Users/GetUser';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$userData = $data['Data'] ?? [];

if (empty($userData)) {
    echo '<div style="padding: 20px; text-align: center; color: #666;">用户数据加载失败</div>';
    exit;
}

// 生成头像URL
function getAvatarUrl($userID, $avatarNumber) {
    $part1 = substr($userID, 0, 4);
    $part2 = substr($userID, 4, 2);
    $part3 = substr($userID, 6, 2);
    $part4 = substr($userID, 8, 16);
    return "http://netlogo-cn.oss-cn-hongkong.aliyuncs.com/users/avatars/{$part1}/{$part2}/{$part3}/{$part4}/{$avatarNumber}.jpg!full";
}

$avatarUrl = getAvatarUrl($userData['User']['ID'], $userData['User']['Avatar'] ?? 0);
$vers = [
    'Banned' => '已被封禁',
    'Oldtimer' => '老用户',
    'Volunteer' => '志愿者',
    'Junior' => '见习编辑',
    'Editor' => '认证编辑',
    'Administrator' => '认证管理员'
];
?>

<div class="user-card" onclick="location.href='user.php?id=<?= $userData['User']['ID'] ?>'">
    <div class="avatar-container">
        <img src="<?= htmlspecialchars($avatarUrl) ?>" 
             alt="用户头像" 
             class="avatar"
             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCIgaGVpZ2h0PSIxMDAiIHJ4PSI1MCIgZmlsbD0iI2YwZjBmMCIvPjxwYXRoIGQ9Ik01MCA1MEM1Ni4xODQ4IDUwIDYxIDU1LjgxNTIgNjEgNjJDNjEgNjguMTg0OCA1Ni4xODQ4IDc0IDUwIDc0QzQzLjgxNTIgNzQgMzkgNjguMTg0OCAzOSA2MkMzOSA1NS44MTUyIDQzLjgxNTIgNTAgNTAgNTBaTTUwIDg0QzYzLjI1NDggODQgNzQgOTIuNDMyIDc0IDEwMkg0MkgzMkMyNiA5NC40MzIgMzYuNzUyIDg0IDUwIDg0WiIgZmlsbD0iI2NjYyIvPjwvc3ZnPg=='">
    </div>
    
    <div class="user-details">
        <div class="username"><center>
            <?= htmlspecialchars($userData['User']['Nickname'] ?? '未知用户') ?>
            <?php if (isset($userData['User']['Verification']) && $userData['User']['Verification']): ?><?= htmlspecialchars('(' . $vers[$userData['User']['Verification']] . ')') ?>
            <?php endif; ?></center>
        </div>
        <div class="signature"><center><?= htmlspecialchars(empty($userData['User']['Signature']) ? '尚未设置签名' : $userData['User']['Signature']) ?></center></div>
    </div>
    
    <div class="stats-row">
        <div class="stat-item">
            <span class="text">关注<?= number_format($userData['Statistic']['FollowingCount'] ?? 0) ?></span>
        </div>
        <div class="stat-item">
            <span class="text">粉丝<?= number_format($userData['Statistic']['FollowerCount'] ?? 0) ?></span>
        </div>
    </div>
    
    <div class="data-section">
        <div class="numbers-row">
            <div class="data-number"><?= number_format($userData['Statistic']['ExperimentCount'] ?? 0) ?></div>
            <div class="data-number"><?= number_format($userData['Statistic']['StarCount'] ?? 0) ?></div>
            <div class="data-number"><?= number_format($userData['User']['Prestige'] ?? 0) ?></div>
        </div>
        <div class="data-icons">
            <img src="https://plweb.turtlesim.com/assets/user/Image-Experiments.png" alt="实验" title="实验数">
            <img src="https://plweb.turtlesim.com/assets/user/Image-Stars.png" alt="收藏" title="收藏数">
            <img src="https://plweb.turtlesim.com/assets/user/Image-Prestige.png" alt="精选" title="精选数">
        </div>
    </div>
    
    <button class="follow-button" id="followBtn" <?= ($userData['is_following'] ?? false) ? 'style="display: none;"' : '' ?>>
        关注用户
    </button>
    <button class="unfollow-button" id="unfollowBtn" <?= !($userData['is_following'] ?? false) ? 'style="display: none;"' : '' ?>>
        取消关注用户
    </button>
</div>

<style>
.user-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    box-sizing: border-box;
    position: relative;
    transform: translateY(20px);
    transition: transform 0.3s ease;
    margin: 20px;
}

.user-card-overlay.active .user-card {
    transform: translateY(0);
}

.close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 30px;
    height: 30px;
    background: #f5f5f5;
    border: none;
    border-radius: 50%;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    z-index: 1000;
}

.close-btn:hover {
    background: #e5e5e5;
}

.avatar-container {
    text-align: center;
    margin-bottom: 15px;
}

.avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #f0f0f0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.user-details {
    text-align: center;
    margin-bottom: 20px;
}

.username {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
    line-height: 1.2;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
}

.signature {
    font-size: 14px;
    color: #666;
    line-height: 1.4;
    opacity: 0.9;
    max-width: 90%;
    margin: 0 auto;
    word-break: break-word;
}

.stats-row {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 25px;
    padding: 15px 0;
    border-top: 1px solid #f0f0f0;
    border-bottom: 1px solid #f0f0f0;
}

.stat-item {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    margin: 0 20px;
}

.data-section {
    margin-bottom: 25px;
}

.numbers-row {
    display: flex;
    justify-content: space-around;
    margin-bottom: 10px;
}

.data-number {
    font-size: 20px;
    font-weight: 700;
    color: #1890ff;
    text-align: center;
    flex: 1;
}

.data-icons {
    display: flex;
    justify-content: space-around;
    margin-bottom: 10px;
}

.data-icons img {
    height: 25px;
    filter: brightness(0.9);
    opacity: 0.8;
}

.verification-badge {
    background: #ffd700;
    color: #333;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.follow-button, .unfollow-button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    box-sizing: border-box;
}

.follow-button {
    background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);
    color: white;
}

.follow-button:hover {
    background: linear-gradient(135deg, #096dd9 0%, #1890ff 100%);
}

.unfollow-button {
    background: #f5f5f5;
    color: #666;
    border: 1px solid #d9d9d9;
}

.unfollow-button:hover {
    background: #fff2f0;
    color: #ff4d4f;
    border-color: #ffccc7;
}

@media (max-width: 480px) {
    .user-card {
        padding: 20px;
        margin: 15px;
    }
    
    .avatar {
        width: 90px;
        height: 90px;
    }
    
    .username {
        font-size: 20px;
    }
    
    .stat-item {
        margin: 0 15px;
    }
}
</style>
