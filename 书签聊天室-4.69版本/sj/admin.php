<?php 
// 检查并创建必要的文件 
$userFile = 'zh.txt'; 
$chatFile = 'wz.txt'; 
$checkFile = 'jc.txt';
$banFile = 'bn.txt'; // 封禁账号记录文件
$adminFile = 'admin.txt'; // 管理员账号文件
$versionFile = 'version.txt'; // 版本文件

// 检查文件是否存在，若不存在则创建 
if (!file_exists($userFile)) { 
    file_put_contents($userFile, ''); 
} 
if (!file_exists($chatFile)) { 
    file_put_contents($chatFile, ''); 
} 
if (!file_exists($checkFile)) { 
    file_put_contents($checkFile, "违规名字1\n违规名字2"); 
}
if (!file_exists($banFile)) { 
    file_put_contents($banFile, ''); 
} 
if (!file_exists($adminFile)) { 
    file_put_contents($adminFile, ''); 
} 
if (!file_exists($versionFile)) { 
    file_put_contents($versionFile, '4.69'); // 默认版本号
}
// 检查并测试sdcs.txt文件
$sdcsFile = 'sdcs.txt';
if (!file_exists($sdcsFile)) {
    // 生成随机内容
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $content = '';
    
    // 测试写入速度
    $startWrite = microtime(true);
    for ($i = 0; $i < 10000; $i++) {
        $lineLength = rand(10, 30);
        $line = '';
        for ($j = 0; $j < $lineLength; $j++) {
            $line .= $chars[rand(0, strlen($chars)-1)];
        }
        $content .= $line . "\n";
    }
    file_put_contents($sdcsFile, $content);
    $writeTime = microtime(true) - $startWrite;
    $writeSpeed = strlen($content) / $writeTime;
    
    // 测试读取速度
    $startRead = microtime(true);
    $readContent = file_get_contents($sdcsFile);
    $readTime = microtime(true) - $startRead;
    $readSpeed = strlen($readContent) / $readTime;
    
    // 保存速度信息
    $speedInfo = "" . round($writeSpeed/1048576, 2) . "MB/s\n" . round($readSpeed/1048576, 2) . "MB/s\n";
    file_put_contents('speed_info.txt', $speedInfo);
} else {
    // 如果文件已存在，读取速度信息
    if (file_exists('speed_info.txt')) {
        $speedInfo = file_get_contents('speed_info.txt');
    } else {
        $speedInfo = "未知MB/s\n未知MB/s\n";
    }
} 

// 获取当前版本号
function getCurrentVersion() {
    global $versionFile;
    return trim(file_get_contents($versionFile));
}

// 检测最新版本
function checkLatestVersion() {
    $url = 'http://945202.xyz/sp/ltsgx.html';
    $currentVersion = getCurrentVersion();
    
    // 初始化cURL会话
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过SSL验证
    
    // 执行cURL请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 检查请求是否成功
    if ($httpCode === 200 && $response) {
        // 使用正则表达式匹配版本号
        preg_match('/<p>当前书签聊天室最新版本([\d.]+)<\/p>/', $response, $matches);
        
        if (isset($matches[1])) {
            $latestVersion = $matches[1];
            
            // 比较版本号
            if (version_compare($currentVersion, $latestVersion, '<')) {
                return [
                    'updateAvailable' => true,
                    'currentVersion' => $currentVersion,
                    'latestVersion' => $latestVersion,
                    'updateUrl' => $url
                ];
            }
        }
    }
    
    return [
        'updateAvailable' => false,
        'currentVersion' => $currentVersion
    ];
}

// 获取违规名字列表 
$forbiddenNames = file($checkFile, FILE_IGNORE_NEW_LINES); 

// 获取封禁账号列表
function getBannedUsers() {
    global $banFile;
    $banData = file($banFile, FILE_IGNORE_NEW_LINES);
    $bannedUsers = [];
    
    foreach ($banData as $banLine) {
        $data = explode(':', $banLine);
        if (count($data) == 3) {
            $bannedUsers[$data[0]] = [
                'attempts' => (int)$data[1],
                'banTime' => (int)$data[2]
            ];
        }
    }
    
    return $bannedUsers;
}

// 保存封禁账号列表
function saveBannedUsers($bannedUsers) {
    global $banFile;
    $banData = [];
    
    foreach ($bannedUsers as $username => $data) {
        $banData[] = "{$username}:{$data['attempts']}:{$data['banTime']}";
    }
    
    file_put_contents($banFile, implode("\n", $banData));
}

// 检查账号是否被封禁
function isUserBanned($username) {
    $bannedUsers = getBannedUsers();
    if (isset($bannedUsers[$username])) {
        $banTime = $bannedUsers[$username]['banTime'];
        $currentTime = time();
        
        // 检查封禁是否已过期
        if ($banTime + 300 > $currentTime) { // 5分钟 = 300秒
            return true;
        } else {
            // 封禁过期，移除记录
            unset($bannedUsers[$username]);
            saveBannedUsers($bannedUsers);
        }
    }
    return false;
}

// 记录登录尝试
function recordLoginAttempt($username, $isSuccess) {
    $bannedUsers = getBannedUsers();
    
    if ($isSuccess) {
        // 登录成功，清除尝试记录
        if (isset($bannedUsers[$username])) {
            unset($bannedUsers[$username]);
            saveBannedUsers($bannedUsers);
        }
    } else {
        // 登录失败，记录尝试
        if (!isset($bannedUsers[$username])) {
            $bannedUsers[$username] = [
                'attempts' => 1,
                'banTime' => time()
            ];
        } else {
            $bannedUsers[$username]['attempts']++;
            // 达到5次尝试，设置封禁时间
            if ($bannedUsers[$username]['attempts'] >= 5) {
                $bannedUsers[$username]['banTime'] = time();
            }
        }
        saveBannedUsers($bannedUsers);
    }
}

// 检查是否包含违规名字 
function containsForbiddenName($text, $forbiddenNames) { 
    foreach ($forbiddenNames as $name) { 
        if (strpos($text, $name) !== false) { 
            return true; 
        } 
    } 
    return false; 
} 

// 生成文字头像背景色
function generateAvatarColor($username) {
    $hash = crc32($username);
    $r = ($hash >> 16) & 0xFF;
    $g = ($hash >> 8) & 0xFF;
    $b = $hash & 0xFF;
    // 调整颜色使其更柔和且避免过亮或过暗
    $r = min(220, max(60, $r));
    $g = min(220, max(60, $g));
    $b = min(220, max(60, $b));
    return "rgb($r, $g, $b)";
}

// 生成文字头像显示文本
function generateAvatarText($username) {
    $text = mb_substr($username, 0, 6, 'UTF-8'); // 最多取6个字符
    // 处理过长的情况，自动换行或调整
    if (mb_strlen($text, 'UTF-8') > 3) {
        $firstLine = mb_substr($text, 0, 3, 'UTF-8');
        $secondLine = mb_substr($text, 3, 3, 'UTF-8');
        return "$firstLine\n$secondLine";
    }
    return $text;
}

// 管理员相关功能
function createAdminAccount($username, $password) {
    global $adminFile;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    file_put_contents($adminFile, "$username:$hashedPassword");
}

function checkAdminExists() {
    global $adminFile;
    $content = trim(file_get_contents($adminFile));
    return !empty($content);
}

function verifyAdmin($username, $password) {
    global $adminFile;
    $content = trim(file_get_contents($adminFile));
    if (empty($content)) return false;
    
    list($storedUsername, $storedHash) = explode(':', $content);
    return ($username === $storedUsername && password_verify($password, $storedHash));
}

// 处理管理员登录
session_start();
$adminMessage = '';

if (isset($_POST['admin_action'])) {
    if ($_POST['admin_action'] === 'create_admin') {
        $adminUsername = $_POST['admin_username'];
        $adminPassword = $_POST['admin_password'];
        
        if (empty($adminUsername) || empty($adminPassword)) {
            $adminMessage = "用户名和密码不能为空";
        } else {
            createAdminAccount($adminUsername, $adminPassword);
            $adminMessage = "管理员账号创建成功，请登录";
        }
    } elseif ($_POST['admin_action'] === 'login_admin') {
        $adminUsername = $_POST['admin_username'];
        $adminPassword = $_POST['admin_password'];
        
        if (verifyAdmin($adminUsername, $adminPassword)) {
            $_SESSION['admin'] = $adminUsername;
            header("Location: {$_SERVER['PHP_SELF']}");
            exit;
        } else {
            $adminMessage = "用户名或密码错误";
        }
    }
}

// 检查更新
$updateInfo = checkLatestVersion();

// 处理管理员操作
if (isset($_SESSION['admin'])) {
    // 用户管理操作
    if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['username'])) {
        $username = $_GET['username'];
        $users = file($userFile, FILE_IGNORE_NEW_LINES);
        $newUsers = [];
        
        foreach ($users as $user) {
            list($existingUsername, ) = explode(':', $user);
            if ($existingUsername !== $username) {
                $newUsers[] = $user;
            }
        }
        
        file_put_contents($userFile, implode("\n", $newUsers));
        
        // 删除用户后不退出登录，重定向回用户管理页面
        header("Location: {$_SERVER['PHP_SELF']}?tab=users");
        exit;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'ban_user' && isset($_GET['username'])) {
        $username = $_GET['username'];
        $duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 5; // 默认5分钟
        
        $bannedUsers = getBannedUsers();
        $bannedUsers[$username] = [
            'attempts' => 0,
            'banTime' => time() + ($duration * 60)
        ];
        
        saveBannedUsers($bannedUsers);
        header("Location: {$_SERVER['PHP_SELF']}?tab=users");
        exit;
    }
    
    // 解除禁言功能
    if (isset($_GET['action']) && $_GET['action'] === 'unban_user' && isset($_GET['username'])) {
        $username = $_GET['username'];
        $bannedUsers = getBannedUsers();
        
        if (isset($bannedUsers[$username])) {
            unset($bannedUsers[$username]);
            saveBannedUsers($bannedUsers);
        }
        
        header("Location: {$_SERVER['PHP_SELF']}?tab=users");
        exit;
    }
    
    // 聊天记录操作
    if (isset($_GET['action']) && $_GET['action'] === 'delete_message' && isset($_GET['index'])) {
        $index = (int)$_GET['index'];
        $chatRecords = file($chatFile, FILE_IGNORE_NEW_LINES);
        
        if (isset($chatRecords[$index])) {
            unset($chatRecords[$index]);
            file_put_contents($chatFile, implode("\n", $chatRecords));
        }
        
        header("Location: {$_SERVER['PHP_SELF']}?tab=chat");
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'edit_message' && isset($_POST['index'])) {
        $index = (int)$_POST['index'];
        $newMessage = $_POST['new_message'];
        $chatRecords = file($chatFile, FILE_IGNORE_NEW_LINES);
        
        if (isset($chatRecords[$index])) {
            list($time, $ip, $oldMessage, $region, $username) = explode('-', $chatRecords[$index]);
            $chatRecords[$index] = "$time-$ip-$newMessage-$region-$username";
            file_put_contents($chatFile, implode("\n", $chatRecords));
        }
        
        header("Location: {$_SERVER['PHP_SELF']}?tab=chat");
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'send_admin_message') {
        $messageText = $_POST['admin_message'];
        if (!empty($messageText)) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $time = date('Y.m.d.H.i.s');
            $region = "未知地区";
            $chatLine = "$time-$ip-$messageText-$region-管理员\n";
            file_put_contents($chatFile, $chatLine, FILE_APPEND);
        }
        
        header("Location: {$_SERVER['PHP_SELF']}?tab=chat");
        exit;
    }
}

// 获取聊天记录
$chatRecords = file($chatFile, FILE_IGNORE_NEW_LINES);
// 获取用户列表
$users = file($userFile, FILE_IGNORE_NEW_LINES);
// 获取封禁用户列表
$bannedUsers = getBannedUsers();
?> 

<!DOCTYPE html> 
<html lang="zh-CN"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>书签聊天室 - 管理员后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
:root {
    --primary-color: #8B5A2B;
    --primary-light: #D2B48C;
    --primary-dark: #6B4423;
    --background-color: #F5F5DC;
    --paper-color: #FFFFF0;
    --border-color: #D2B48C;
    --text-color: #333;
    --title-color: #5A4630;
    --avatar-size: 48px;
    --admin-primary: #5A4630;
    --admin-secondary: #89744B;
    --admin-accent: #C18A6B;
    --success-color: #228B22;
    --warning-color: #FF8C00;
    --danger-color: #B22222;
    --info-color: #4682B4;
    --transition: all 0.3s ease;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'ZCOOL XiaoWei', serif;
}

body {
    background-color: var(--background-color);
    margin: 0;
    padding: 8px;
    color: var(--text-color);
    background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23d1c9b7' fill-opacity='0.3' fill-rule='evenodd'/%3E%3C/svg%3E");
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.header {
    background: var(--admin-primary);
    color: white;
    padding: 1.5rem;
    border-radius: 0.5rem 0.5rem 0 0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    font-family: 'Ma Shan Zheng', cursive;
    font-size: clamp(1.5rem, 3vw, 2rem);
    margin: 0;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.logout-link {
    color: white;
    text-decoration: none;
    font-weight: bold;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    background-color: rgba(255, 255, 255, 0.1);
}

.logout-link:hover {
    color: var(--primary-light);
    background-color: rgba(255, 255, 255, 0.2);
}

.tabs {
    display: flex;
    background: var(--primary-light);
    border-radius: 0 0 0.5rem 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    white-space: nowrap;
}

.tab {
    padding: 1rem 1.5rem;
    cursor: pointer;
    transition: var(--transition);
    font-weight: bold;
    color: var(--admin-primary);
    border-right: 1px solid var(--border-color);
    flex-shrink: 0;
}

.tab:hover {
    background-color: var(--primary-color);
    color: white;
}

.tab.active {
    background-color: var(--admin-primary);
    color: white;
}

.tab-content {
    display: none;
    animation: fadeIn 0.5s ease-in-out;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.card {
    background: var(--paper-color);
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: var(--transition);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.15);
}

.card-header {
    background: var(--primary-light);
    color: var(--admin-primary);
    padding: 1rem;
    font-weight: bold;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-body {
    padding: 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: var(--paper-color);
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: var(--transition);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background-color: var(--admin-primary);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-title {
    color: var(--admin-secondary);
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    color: var(--admin-primary);
    font-size: 2rem;
    font-weight: bold;
    font-family: 'Ma Shan Zheng', cursive;
    transition: var(--transition);
}

.stat-card:hover .stat-value {
    transform: scale(1.1);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    text-align: left;
}

.table th {
    background-color: var(--admin-primary);
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tr:nth-child(even) {
    background-color: var(--primary-light);
}

.table tr:hover {
    background-color: var(--primary-color);
    color: white;
    transition: var(--transition);
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.25rem;
    cursor: pointer;
    font-size: 0.9rem;
    transition: var(--transition);
    margin-right: 0.5rem;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.btn-primary {
    background-color: var(--admin-primary);
    color: white;
}

.btn-primary:hover {
    background-color: var(--admin-secondary);
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #8B0000;
}

.btn-warning {
    background-color: var(--warning-color);
    color: white;
}

.btn-warning:hover {
    background-color: #FFA500;
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #006400;
}

.btn-info {
    background-color: var(--info-color);
    color: white;
}

.btn-info:hover {
    background-color: #2E5B80;
}

.chat-messages {
    max-height: 500px;
    overflow-y: auto;
    margin-bottom: 1.5rem;
    padding-right: 0.5rem;
    display: flex;
    flex-direction: column;
}

.chat-message {
    margin-bottom: 1.5rem;
    display: flex;
    position: relative;
    align-items: flex-start;
    opacity: 0;
    animation: fadeIn 0.5s ease forwards;
    transition: var(--transition);
}

.message-avatar {
    width: var(--avatar-size);
    height: var(--avatar-size);
    border-radius: 50%;
    background-color: #d1c9b7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Ma Shan Zheng', cursive;
    font-size: 14px;
    font-weight: bold;
    color: white;
    margin-right: 1rem;
    flex-shrink: 0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    line-height: 1.2;
    text-align: center;
    padding: 2px;
    transition: var(--transition);
    background-size: cover;
    background-position: center;
}

.message-avatar:hover {
    transform: scale(1.05);
}

.admin-message .message-bubble {
    background-color: var(--primary-light);
}

.admin-message .message-time::after {
    content: " 管理员";
    color: var(--admin-primary);
    font-weight: bold;
}

.message-time {
    position: absolute;
    bottom: -1.2rem;
    left: calc(var(--avatar-size) + 1rem);
    font-size: 0.75rem;
    color: #888;
    font-style: italic;
}

.message-content {
    flex: 1;
}

.message-bubble {
    position: relative;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    max-width: 80%;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    word-break: break-word;
    border: 1px solid var(--border-color);
    background: var(--paper-color);
    transition: var(--transition);
}

.message-bubble:hover {
    transform: translateY(-2px);
}

.message-bubble::after {
    content: "";
    position: absolute;
    bottom: 0.75rem;
    left: -0.5rem;
    width: 0;
    height: 0;
    border-top: 0.5rem solid transparent;
    border-bottom: 0.5rem solid transparent;
    border-right: 0.5rem solid;
    border-right-color: inherit;
}

.message-actions {
    margin-top: 0.5rem;
    display: flex;
    gap: 0.5rem;
}

.edit-message-form {
    margin-top: 0.75rem;
}

.edit-message-form input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 0.25rem;
    background-color: var(--paper-color);
    margin-bottom: 0.5rem;
    transition: var(--transition);
}

.edit-message-form input:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 2px rgba(90, 70, 48, 0.2);
}

.chat-input {
    display: flex;
    gap: 0.75rem;
}

.chat-input input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 0.25rem;
    background-color: var(--paper-color);
    transition: var(--transition);
}

.chat-input input:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 2px rgba(90, 70, 48, 0.2);
}

.login-card {
    max-width: 400px;
    margin: 3rem auto;
    background: var(--paper-color);
    border-radius: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: var(--transition);
}

.login-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.login-header {
    background: var(--admin-primary);
    color: white;
    padding: 1.5rem;
    text-align: center;
}

.login-title {
    font-family: 'Ma Shan Zheng', cursive;
    font-size: 1.5rem;
    margin: 0;
}

.login-body {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 0.25rem;
    background-color: var(--paper-color);
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 2px rgba(90, 70, 48, 0.2);
}

.error-message {
    color: var(--danger-color);
    margin-bottom: 1rem;
    font-weight: bold;
    padding: 0.5rem;
    background-color: rgba(178, 34, 34, 0.1);
    border-radius: 0.25rem;
}

.footer {
    text-align: center;
    padding: 1rem;
    color: var(--admin-primary);
    font-size: 0.9rem;
}

.search-container {
    display: flex;
    margin-bottom: 1.5rem;
}

.search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 0.25rem 0 0 0.25rem;
    background-color: var(--paper-color);
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 2px rgba(90, 70, 48, 0.2);
}

.search-button {
    padding: 0.75rem 1.5rem;
    background-color: var(--admin-primary);
    color: white;
    border: none;
    border-radius: 0 0.25rem 0.25rem 0;
    cursor: pointer;
    transition: var(--transition);
}

.search-button:hover {
    background-color: var(--admin-secondary);
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.loading-overlay.active {
    opacity: 1;
    visibility: visible;
}

.loader {
    border: 5px solid var(--paper-color);
    border-radius: 50%;
    border-top: 5px solid var(--admin-primary);
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem;
    border-radius: 0.5rem;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 999;
    opacity: 0;
    transform: translateY(-20px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.notification.success {
    background-color: var(--success-color);
}

.notification.error {
    background-color: var(--danger-color);
}

.notification.info {
    background-color: var(--info-color);
}

.notification.active {
    opacity: 1;
    transform: translateY(0);
}

/* 版本更新提示 */
.update-notification {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    padding: 1rem 1.5rem;
    background-color: var(--warning-color);
    color: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 999;
    display: flex;
    align-items: center;
    max-width: 80%;
    animation: fadeIn 0.5s ease forwards;
}

.update-notification p {
    margin-right: 1rem;
    margin-bottom: 0;
    flex: 1;
}

.update-notification .btn {
    margin-left: 0.5rem;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .logout-link {
        position: static;
        transform: none;
        margin-top: 1rem;
        display: inline-block;
    }
    
    .tabs {
        flex-wrap: wrap;
    }
    
    .tab {
        flex: 1 1 auto;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .table th, .table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .chat-input {
        flex-direction: column;
    }
    
    .chat-input button {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .header {
        flex-direction: column;
        text-align: center;
    }
    
    .logout-link {
        margin-top: 1rem;
    }
    
    .message-bubble {
        max-width: 90%;
    }
    
    .update-notification {
        flex-direction: column;
        text-align: center;
    }
    
    .update-notification p {
        margin-right: 0;
        margin-bottom: 1rem;
    }
    
    .update-notification .btn {
        margin: 0.25rem;
    }
}
    </style> 
</head> 
<body> 
    <div class="container">
        <div class="header">
            <h1 class="header-title">书签聊天室 - 管理员后台</h1>
            <?php if (isset($_SESSION['admin'])): ?>
                <a href="?logout=1" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (!isset($_SESSION['admin'])): ?>
            <div class="login-card">
                <div class="login-header">
                    <h2 class="login-title">管理员登录</h2>
                </div>
                <div class="login-body">
                    <?php if (!empty($adminMessage)): ?>
                        <p class="error-message"><?php echo $adminMessage; ?></p>
                    <?php endif; ?>
                    
                    <?php if (!checkAdminExists()): ?>
                        <form method="post">
                            <div class="form-group">
                                <input type="text" class="form-control" name="admin_username" placeholder="创建管理员用户名" required>
                            </div>
                            <div class="form-group">
                                <input type="password" class="form-control" name="admin_password" placeholder="创建管理员密码" required>
                            </div>
                            <button type="submit" name="admin_action" value="create_admin" class="btn btn-primary w-full">
                                <i class="fas fa-user-plus"></i> 创建管理员账号
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <div class="form-group">
                                <input type="text" class="form-control" name="admin_username" placeholder="管理员用户名" required>
                            </div>
                            <div class="form-group">
                                <input type="password" class="form-control" name="admin_password" placeholder="管理员密码" required>
                            </div>
                            <button type="submit" name="admin_action" value="login_admin" class="btn btn-primary w-full">
                                <i class="fas fa-sign-in-alt"></i> 登录
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- 版本更新提示 -->
            <?php if ($updateInfo['updateAvailable']): ?>
                <div class="update-notification" id="update-notification">
                    <p>
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        检测到新版本！当前版本是 <?php echo $updateInfo['currentVersion']; ?>，最新版本是 <?php echo $updateInfo['latestVersion']; ?>。
                    </p>
                    <div>
                        <button class="btn btn-primary" onclick="window.location.href='<?php echo $updateInfo['updateUrl']; ?>'">
                            <i class="fas fa-download"></i> 前往更新
                        </button>
                        <button class="btn btn-secondary" onclick="document.getElementById('update-notification').style.display='none'">
                            <i class="fas fa-times"></i> 稍后提醒
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 选项卡导航 -->
            <div class="tabs">
                <div class="tab active" data-tab="dashboard">
                    <i class="fas fa-tachometer-alt"></i> 系统概览
                </div>
                <div class="tab" data-tab="users">
                    <i class="fas fa-users"></i> 用户管理
                </div>
                <div class="tab" data-tab="chat">
                    <i class="fas fa-comments"></i> 聊天记录
                </div>
            </div>
            
            <!-- 系统概览 -->
            <div class="tab-content active" id="dashboard">
                <div class="card">
                    <div class="card-header">
                        <span>系统概览</span>
                        <div class="refresh-btn" onclick="refreshDashboard()">
                            <i class="fas fa-refresh"></i> 刷新
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-title">在线用户</div>
                                <div class="stat-value" id="online-users">0</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">注册用户</div>
                                <div class="stat-value"><?php echo count($users); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">封禁用户</div>
                                <div class="stat-value"><?php echo count($bannedUsers); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">消息总数</div>
                                <div class="stat-value"><?php echo count($chatRecords); ?></div>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">服务器信息</div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4"> 
                                          <div> 
                                              <p><strong>当前书签聊天室版本:</strong> <?php echo getCurrentVersion(); ?><?php if (!$updateInfo['updateAvailable']): ?> <span class="text-success">(目前已是最新版本)</span><?php endif; ?></p> 
                                              <p><strong>服务器时间:</strong> <?php echo date('Y-m-d H:i:s'); ?></p> 
                                              <p><strong>PHP 版本:</strong> <?php echo phpversion(); ?></p> 
                                              <p><strong>CPU 型号:</strong> <?php echo php_uname('m'); ?></p> 
                                              <p><strong>服务器域名:</strong> <?php echo $_SERVER['SERVER_NAME']; ?></p> 
                                          </div> 
                                          <div> 
                                              <p><strong>操作系统:</strong> <?php echo php_uname('s'); ?></p> 
                                              <p><strong>管理员:</strong> <?php echo $_SESSION['admin']; ?></p> 
                                              <p><strong>当前端口:</strong> <?php echo $_SERVER['SERVER_PORT']; ?></p> 
                                              <p><strong>最大内存:</strong> <?php echo ini_get('memory_limit'); ?></p> 
                                          </div> 
                                          <div> 
                                              <p><strong>CPU 负载:</strong> <?php echo sys_getloadavg()[0]; ?></p> 
                                              <p><strong>CPU 使用率:</strong> <?php echo round(sys_getloadavg()[0] * 100, 2); ?>%</p> 
                                              <p><strong>内存使用:</strong> <?php echo round(memory_get_usage()/1024/1024, 2); ?> MB / <?php echo round(memory_get_peak_usage()/1024/1024, 2); ?> MB</p> 
                                              <?php if ($updateInfo['updateAvailable']): ?> 
                                                  <p class="text-warning"> 
                                                      <i class="fas fa-exclamation-circle"></i> 
                                                      有新版本可用: <?php echo $updateInfo['latestVersion']; ?> 
                                                  </p> 
                                              <?php endif; ?> 
                                          </div> 
                                         <div> 
                                             <p><strong>当前文件夹:</strong> <?php echo dirname(__FILE__); ?></p> 
                                             <p><strong>文件夹可读:</strong> <?php echo is_readable(dirname(__FILE__)) ? '是' : '否'; ?></p> 
                                             <p><strong>文件夹可写:</strong> <?php echo is_writable(dirname(__FILE__)) ? '是' : '否'; ?></p> 
                                             <p><strong>文件IO测试:</strong> <?php echo file_exists('sdcs.txt') ? '已完成' : '未完成'; ?></p> 
                                             <p><strong>IO写入速度(MB/s):</strong> <?php echo isset($speedInfo) ? explode("\n", $speedInfo)[0] : '未测试'; ?></p>
                                             <p><strong>IO读取速度(MB/s):</strong> <?php echo isset($speedInfo) ? explode("\n", $speedInfo)[1] : '未测试'; ?></p> 
                                         </div> 
                                 </div>
                            </div>
                            <div class="card-header"></div>
                            <div>    <?php echo getRemoteNotice(); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 用户管理 -->
            <div class="tab-content" id="users">
                <div class="card">
                    <div class="card-header">
                        <span>用户管理</span>
                        <div class="refresh-btn" onclick="refreshUsers()">
                            <i class="fas fa-refresh"></i> 刷新
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <input type="text" id="user-search" class="search-input" placeholder="搜索用户...">
                            <button onclick="searchUsers()" class="search-button">
                                <i class="fas fa-search"></i> 搜索
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>用户名</th>
                                        <th>状态</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="users-table-body">
                                    <?php foreach ($users as $user): ?>
                                        <?php list($username, $passwordHash) = explode(':', $user); ?>
                                        <tr>
                                            <td><?php echo $username; ?></td>
                                            <td>
                                                <?php if (isset($bannedUsers[$username])): ?>
                                                    <span class="text-danger"><i class="fas fa-lock"></i> 已封禁</span>
                                                    <?php $remainingTime = $bannedUsers[$username]['banTime'] - time(); ?>
                                                    <?php if ($remainingTime > 0): ?>
                                                        (剩余 <?php echo ceil($remainingTime / 60); ?> 分钟)
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> 正常</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i:s', filemtime($userFile)); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-danger" onclick="deleteUser('<?php echo $username; ?>')">
                                                        <i class="fas fa-trash"></i> 删除
                                                    </button>
                                                    <?php if (isset($bannedUsers[$username])): ?>
                                                        <button class="btn btn-success" onclick="unbanUser('<?php echo $username; ?>')">
                                                            <i class="fas fa-unlock"></i> 解封
                                                        </button>
                                                    <?php else: ?>
                                                        <div class="ban-form">
                                                            <input type="number" min="1" max="1440" value="5" placeholder="分钟" class="form-control" style="width: 80px; display: inline-block; margin-right: 0.5rem;">
                                                            <button onclick="banUser('<?php echo $username; ?>', this.previousElementSibling.value)" class="btn btn-warning">
                                                                <i class="fas fa-lock"></i> 封禁
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 聊天记录 -->
            <div class="tab-content" id="chat">
                <div class="card">
                    <div class="card-header">
                        <span>聊天记录</span>
                        <div class="refresh-btn" onclick="refreshChat()">
                            <i class="fas fa-refresh"></i> 刷新
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="search-container">
                            <input type="text" id="message-search" class="search-input" placeholder="搜索消息...">
                            <button onclick="searchMessages()" class="search-button">
                                <i class="fas fa-search"></i> 搜索
                            </button>
                        </div>
                        
                        <div class="chat-messages" id="admin-chat-messages">
                            <?php foreach ($chatRecords as $index => $record): ?>
                                <?php 
                                    list($time, $ip, $messageText, $region, $username) = explode('-', $record);
                                    $isAdminMessage = ($username === '管理员');
                                ?>
                                <div class="chat-message <?php echo $isAdminMessage ? 'admin-message' : ''; ?>" data-index="<?php echo $index; ?>" data-time="<?php echo $time; ?>">
                                    <div class="message-avatar" style="background-color: <?php echo generateAvatarColor($username); ?>">
                                        <?php echo generateAvatarText($username); ?>
                                    </div>
                                    <div class="message-time"><?php echo $time; ?></div>
                                    <div class="message-content">
                                        <div class="message-bubble">
                                            <?php echo htmlspecialchars($messageText); ?>
                                        </div>
                                        <div class="message-actions">
                                            <button class="btn btn-primary btn-sm" onclick="toggleEditMessage(<?php echo $index; ?>)">
                                                <i class="fas fa-edit"></i> 编辑
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteMessage(<?php echo $index; ?>)">
                                                <i class="fas fa-trash"></i> 删除
                                            </button>
                                        </div>
                                        
                                        <div class="edit-message-form" id="edit-form-<?php echo $index; ?>" style="display: none;">
                                            <input type="text" value="<?php echo htmlspecialchars($messageText); ?>" id="edit-message-<?php echo $index; ?>" class="form-control">
                                            <button onclick="saveEditMessage(<?php echo $index; ?>)" class="btn btn-primary">
                                                <i class="fas fa-save"></i> 保存
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="chat-input">
                            <input type="text" id="admin-message-input" class="form-control" placeholder="发送管理员消息...">
                            <button onclick="sendAdminMessage()" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> 发送
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>版权所有2025&copy;书签聊天室 - 管理员后台</p>
        </div>
    </div>
    
    <!-- 加载遮罩 -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loader"></div>
    </div>
    
    <!-- 通知提示 -->
    <div class="notification" id="notification">
        <span id="notification-message"></span>
    </div>

    <script> 
        // 管理员面板选项卡切换
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // 移除所有活动状态
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // 添加当前活动状态
                    tab.classList.add('active');
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                    
                    // 更新URL参数
                    history.pushState(null, null, `?tab=${tabId}`);
                    
                    // 显示加载状态
                    showLoading();
                    
                    // 模拟数据加载延迟
                    setTimeout(() => {
                        hideLoading();
                    }, 500);
                });
            });
            
            // 处理URL参数设置初始选项卡
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                tabs.forEach(tab => {
                    if (tab.getAttribute('data-tab') === tabParam) {
                        tab.click();
                    }
                });
            }
            
            // 为聊天消息添加动画延迟
            const chatMessages = document.querySelectorAll('.chat-message');
            chatMessages.forEach((message, index) => {
                message.style.animationDelay = `${index * 0.05}s`;
            });
            
            // 尝试连接WebSocket进行实时更新
            connectWebSocket();
            
            // 定时刷新数据
            setInterval(() => {
                if (document.getElementById('dashboard').classList.contains('active')) {
                    refreshDashboard();
                } else if (document.getElementById('users').classList.contains('active')) {
                    refreshUsers();
                } else if (document.getElementById('chat').classList.contains('active')) {
                    refreshChat();
                }
            }, 30000); // 每30秒刷新一次
        });
        

        // 用户管理功能
        function searchUsers() {
            const searchTerm = document.getElementById('user-search').value.toLowerCase();
            const rows = document.querySelectorAll('#users-table-body tr');
            
            rows.forEach(row => {
                const username = row.querySelector('td:first-child').textContent.toLowerCase();
                if (username.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function deleteUser(username) {
            if (confirm(`确定要删除用户 "${username}" 吗？此操作不可撤销。`)) {
                showLoading();
                window.location.href = `?action=delete_user&username=${encodeURIComponent(username)}`;
            }
        }
        
        function banUser(username, duration) {
            if (confirm(`确定要封禁用户 "${username}" ${duration} 分钟吗？`)) {
                showLoading();
                window.location.href = `?action=ban_user&username=${encodeURIComponent(username)}&duration=${duration}`;
            }
        }
        
        function unbanUser(username) {
            if (confirm(`确定要解封用户 "${username}" 吗？`)) {
                showLoading();
                window.location.href = `?action=unban_user&username=${encodeURIComponent(username)}`;
            }
        }
        
        // 聊天记录功能
        function searchMessages() {
            const searchTerm = document.getElementById('message-search').value.toLowerCase();
            const messages = document.querySelectorAll('.chat-messages .chat-message');
            
            messages.forEach(message => {
                const messageText = message.querySelector('.message-bubble').textContent.toLowerCase();
                if (messageText.includes(searchTerm)) {
                    message.style.display = '';
                } else {
                    message.style.display = 'none';
                }
            });
        }
        
        function toggleEditMessage(index) {
            const editForm = document.getElementById(`edit-form-${index}`);
            editForm.style.display = (editForm.style.display === 'none') ? 'block' : 'none';
        }
        
        function saveEditMessage(index) {
            const newMessage = document.getElementById(`edit-message-${index}`).value;
            if (newMessage.trim() === '') {
                showNotification('消息内容不能为空', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'edit_message');
            formData.append('index', index);
            formData.append('new_message', newMessage);
            
            showLoading();
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                hideLoading();
                showNotification('消息已更新', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                showNotification('保存失败，请重试', 'error');
            });
        }
        
        function deleteMessage(index) {
            if (confirm('确定要删除这条消息吗？')) {
                showLoading();
                window.location.href = `?action=delete_message&index=${index}`;
            }
        }
        
        function sendAdminMessage() {
            const message = document.getElementById('admin-message-input').value.trim();
            if (message === '') return;
            
            const formData = new FormData();
            formData.append('action', 'send_admin_message');
            formData.append('admin_message', message);
            
            showLoading();
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                hideLoading();
                document.getElementById('admin-message-input').value = '';
                showNotification('消息已发送', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                showNotification('发送失败，请重试', 'error');
            });
        }
        
        // 实时更新相关功能
        function addNewChatMessage(messageData) {
            const chatContainer = document.getElementById('admin-chat-messages');
            const isAdmin = messageData.username === '管理员';
            
            // 创建新消息元素
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${isAdmin ? 'admin-message' : ''}`;
            messageElement.setAttribute('data-index', -1); // 临时索引
            messageElement.setAttribute('data-time', messageData.time);
            
            // 设置消息内容
            messageElement.innerHTML = `
                <div class="message-avatar" style="background-color: ${generateAvatarColor(messageData.username)}">
                    ${generateAvatarText(messageData.username)}
                </div>
                <div class="message-time">${messageData.time}</div>
                <div class="message-content">
                    <div class="message-bubble">
                        ${htmlspecialchars(messageData.text)}
                    </div>
                    <div class="message-actions">
                        <button class="btn btn-primary btn-sm" onclick="toggleEditMessage(-1)">
                            <i class="fas fa-edit"></i> 编辑
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteMessage(-1)">
                            <i class="fas fa-trash"></i> 删除
                        </button>
                    </div>
                    
                    <div class="edit-message-form" id="edit-form--1" style="display: none;">
                        <input type="text" value="${htmlspecialchars(messageData.text)}" id="edit-message--1" class="form-control">
                        <button onclick="saveEditMessage(-1)" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存
                        </button>
                    </div>
                </div>
            `;
            
            // 添加到聊天容器
            chatContainer.appendChild(messageElement);
            
            // 滚动到底部
            chatContainer.scrollTop = chatContainer.scrollHeight;
            
            // 显示通知
            showNotification(`收到新消息: ${messageData.text.substring(0, 20)}...`, 'info');
        }
        
        function refreshDashboard() {
            // 模拟刷新系统概览数据
            const onlineUsers = document.getElementById('online-users');
            if (onlineUsers) {
                // 这里可以通过AJAX获取最新数据
                const randomOnline = Math.floor(Math.random() * 20);
                onlineUsers.textContent = randomOnline;
            }
        }
        
        function refreshUsers() {
            // 刷新用户列表
            showLoading();
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=get_users')
                .then(response => response.text())
                .then(data => {
                    // 这里应该解析返回的用户数据并更新表格
                    // 简化示例，实际应该解析JSON数据
                    hideLoading();
                    showNotification('用户列表已更新', 'success');
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideLoading();
                    showNotification('刷新失败，请重试', 'error');
                });
        }
        
        function refreshChat() {
            // 刷新聊天记录
            showLoading();
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=get_chat_messages')
                .then(response => response.text())
                .then(data => {
                    // 这里应该解析返回的聊天数据并更新UI
                    // 简化示例，实际应该解析JSON数据
                    hideLoading();
                    showNotification('聊天记录已更新', 'success');
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideLoading();
                    showNotification('刷新失败，请重试', 'error');
                });
        }
        
        function updateStats(stats) {
            // 更新统计数据
            const onlineUsers = document.getElementById('online-users');
            if (onlineUsers && stats.online_users !== undefined) {
                onlineUsers.textContent = stats.online_users;
            }
        }
        
        // 辅助函数
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return str;
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        function generateAvatarColor(username) {
            // 客户端版本的generateAvatarColor函数
            const hash = username.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
            const r = (hash >> 16) & 0xFF;
            const g = (hash >> 8) & 0xFF;
            const b = hash & 0xFF;
            return `rgb(${r}, ${g}, ${b})`;
        }
        
        function generateAvatarText(username) {
            // 客户端版本的generateAvatarText函数
            const text = username.substring(0, 6);
            if (text.length > 3) {
                const firstLine = text.substring(0, 3);
                const secondLine = text.substring(3, 6);
                return `${firstLine}\n${secondLine}`;
            }
            return text;
        }
        
        function showLoading() {
            const loadingOverlay = document.getElementById('loading-overlay');
            loadingOverlay.classList.add('active');
        }
        
        function hideLoading() {
            const loadingOverlay = document.getElementById('loading-overlay');
            loadingOverlay.classList.remove('active');
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notification-message');
            
            notificationMessage.textContent = message;
            notification.className = `notification active ${type}`;
            
            // 3秒后隐藏通知
            setTimeout(() => {
                notification.classList.remove('active');
            }, 3000);
        }
        
        // 处理退出登录
        <?php if (isset($_GET['logout'])) { 
            session_destroy(); 
            echo 'window.location.href = "'.basename($_SERVER['PHP_SELF']).'";';
        } ?>
    </script> 
<script>
// 30秒自动刷新页面
setInterval(function() {
    location.reload();
}, 30000);
</script>
</body> 
<?php
function getRemoteNotice() {
    $url = 'http://945202.xyz/sp/ltsgx.html';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        preg_match_all('/<(h1|h2|p)[^>]*>(.*?)<\/\1>/i', $response, $matches);
        $noticeContent = '';
        foreach ($matches[2] as $content) {
            $noticeContent .= '<div class="notice-item">' . htmlspecialchars($content) . '</div>';
        }
        
        file_put_contents(__DIR__.'/公告栏与说明.txt', $noticeContent);
        
        return $noticeContent;
    }
    return '<div class="notice-item">无法获取</div>';
}
?>


<style>
.notice-board {
    margin: 20px 0;
    padding: 0;
    background: var(--paper-color);
    border-radius: 5px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
.notice-header {
    background: var(--primary-light);
    color: var(--admin-primary);
    padding: 1rem;
    font-weight: bold;
    border-bottom: 1px solid var(--border-color);
    font-size: 1.2rem;
}
.notice-item h1 {
    font-size: 1.5rem;
    margin: 1rem 0;
    color: var(--admin-primary);
    text-align: center;
    font-family: 'Ma Shan Zheng', cursive;
    font-weight: bold;
}
.notice-item h2 {
    font-size: 1.3rem;
    margin: 0.8rem 0;
    color: var(--admin-secondary);
    text-align: center;
    font-family: 'Ma Shan Zheng', cursive;
}
.notice-item p {
    margin: 0.5rem 1rem;
    padding: 0.5rem;
    line-height: 1.6;
    color: var(--text-color);
    border-left: 3px solid var(--admin-accent);
    background: rgba(255,255,255,0.5);
    font-weight: bold;
}
</style>
</html>
