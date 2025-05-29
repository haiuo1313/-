<?php 
// 定义文件存储目录
$dir = 'sj';
// 创建目录（如需递归创建可设置第三个参数为true）
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// 检查并创建必要的文件（路径统一添加目录前缀）
$userFile = "$dir/zh.txt"; 
$chatFile = "$dir/wz.txt"; 
$checkFile = "$dir/jc.txt";
$banFile = "$dir/bn.txt"; // 封禁账号记录文件
 
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

// 处理创建账号或登录 
session_start(); 
$message = '';
$banMessage = '';
$sendMessageError = '';

if (isset($_POST['action'])) { 
    $username = $_POST['username']; 
    $password = $_POST['password']; 
    
    // 检查账号是否被封禁
    if (isUserBanned($username)) {
        $banMessage = "账号已被临时封禁，请5分钟后再尝试登录";
    } else {
        if ($_POST['action'] === 'create_account') { 
            if (containsForbiddenName($username, $forbiddenNames)) { 
                $message = "名字包含违规内容，请换个名字"; 
            } else { 
                $users = file($userFile, FILE_IGNORE_NEW_LINES); 
                foreach ($users as $user) { 
                    list($existingUsername, ) = explode(':', $user); 
                    if ($existingUsername === $username) { 
                        $message = "名字已存在，请换个名字"; 
                        break; 
                    } 
                } 
                if (empty($message)) { 
                    // 使用密码哈希代替明文存储
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    file_put_contents($userFile, "$username:$hashedPassword\n", FILE_APPEND); 
                    $message = "账号创建成功，请登录"; 
                } 
            } 
        } elseif ($_POST['action'] === 'login') { 
            $users = file($userFile, FILE_IGNORE_NEW_LINES); 
            $loginSuccess = false;
            
            foreach ($users as $user) { 
                list($existingUsername, $existingPassword) = explode(':', $user); 
                if ($existingUsername === $username && password_verify($password, $existingPassword)) { 
                    $_SESSION['username'] = $username; 
                    $loginSuccess = true;
                    recordLoginAttempt($username, true);
                    header("Location: {$_SERVER['PHP_SELF']}"); 
                    exit; 
                } 
            } 
            
            if (!$loginSuccess) {
                $message = "用户名或密码错误";
                recordLoginAttempt($username, false);
                
                // 检查是否达到封禁条件
                $bannedUsers = getBannedUsers();
                if (isset($bannedUsers[$username]) && $bannedUsers[$username]['attempts'] >= 5) {
                    $banMessage = "由于多次密码错误，账号已被临时封禁5分钟";
                }
            }
        } 
    }
} 
 
// 处理发送消息 - 使用AJAX处理，避免页面刷新
if (isset($_POST['send_message']) && isset($_SESSION['username']) && isset($_POST['message'])) { 
    $messageText = $_POST['message']; 
    if (containsForbiddenName($messageText, $forbiddenNames)) { 
        $sendMessageError = "文字有违规，请重新编辑文本"; 
        echo json_encode(['error' => $sendMessageError]);
    } else { 
        $ip = $_SERVER['REMOTE_ADDR']; 
        $time = date('Y.m.d.H.i.s'); 
        $region = "未知地区"; // 这里可以使用 IP 定位服务获取实际地区 
        $chatLine = "$time-$ip-$messageText-$region-{$_SESSION['username']}\n"; 
        file_put_contents($chatFile, $chatLine, FILE_APPEND); 
        echo json_encode(['success' => true]);
    }
    exit;
} 
 
// 获取聊天记录 - 不再反转数组，保持原始顺序（最新消息在数组末尾）
$chatRecords = file($chatFile, FILE_IGNORE_NEW_LINES);
?> 
 
<!DOCTYPE html> 
<html lang="zh-CN"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>书签聊天室</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        :root {
            --primary-color: #a57f5b;
            --primary-light: #e6d8b5;
            --primary-dark: #89744b;
            --background-color: #f5f2e9;
            --paper-color: #fffef7;
            --border-color: #d1c9b7;
            --text-color: #333;
            --title-color: #5a4630;
            --avatar-size: 48px; /* 增大头像尺寸以容纳更多文字 */
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: var(--background-color);
            margin: 0;
            padding: 8px;
            font-family: 'ZCOOL XiaoWei', serif;
            color: var(--text-color);
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23d1c9b7' fill-opacity='0.3' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
        
        .book-container {
            max-width: 100%;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            border-radius: 8px;
            overflow: hidden;
            background: var(--paper-color);
            background-image: linear-gradient(to bottom, var(--paper-color) 0%, var(--background-color) 100%);
        }
        
        .book-header {
            position: relative;
            padding: 16px 18px 10px;
            background: var(--primary-light);
            background-image: linear-gradient(to right, #d4c6a2, var(--primary-light));
            border-bottom: 1px solid var(--border-color);
        }
        
        .book-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: repeating-linear-gradient(
                90deg,
                var(--border-color),
                var(--border-color) 6px,
                var(--primary-light) 6px,
                var(--primary-light) 12px
            );
        }
        
        .book-title {
            font-family: 'Ma Shan Zheng', cursive;
            font-size: clamp(1.5rem, 3vw, 1.8rem);
            color: var(--title-color);
            margin: 0;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .book-marks {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            flex-direction: column;
        }
        
        .book-mark {
            width: 10px;
            height: 26px;
            margin: 2px 0;
            border-radius: 10px 0 0 10px;
            background: var(--primary-color);
            opacity: 0.7;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        .book-mark:nth-child(1) { background-color: #c18a6b; }
        .book-mark:nth-child(2) { background-color: var(--primary-color); }
        .book-mark:nth-child(3) { background-color: var(--primary-dark); }
        
        .chat-container {
            padding: 12px;
        }
        
        .chat-box {
            min-height: 200px;
            max-height: 65vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background-color: var(--paper-color);
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            position: relative;
            display: flex;
            flex-direction: column-reverse; /* 关键修改：将容器内的子元素反向排列 */
        }
        
        .chat-box::before {
            content: "";
            position: absolute;
            bottom: 0; /* 原来的top改为bottom */
            left: 0;
            right: 0;
            height: 12px;
            background: repeating-linear-gradient(
                90deg,
                var(--background-color),
                var(--background-color) 6px,
                var(--paper-color) 6px,
                var(--paper-color) 12px
            );
            border-top: 1px solid var(--border-color); /* 原来的border-bottom改为border-top */
            border-radius: 0 0 8px 8px; /* 原来的border-radius修改 */
        }
        
        .chat-message {
            margin-bottom: 15px;
            display: flex;
            position: relative;
            align-items: flex-start;
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
            font-size: 14px; /* 调整字体大小以适应多行文字 */
            font-weight: bold;
            color: white;
            margin-right: 12px;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            line-height: 1.2;
            text-align: center;
            padding: 2px;
        }
        
        .chat-message:nth-child(odd) .message-bubble {
            background-color: #f8f0e0;
        }
        
        .chat-message:nth-child(even) .message-bubble {
            background-color: #e8e0d0;
        }
        
        .message-time {
            position: absolute;
            bottom: -18px; /* 原来的top改为bottom */
            left: calc(var(--avatar-size) + 12px);
            font-size: 10px;
            color: #888;
            font-style: italic;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-bubble {
            position: relative;
            padding: 12px 15px;
            border-radius: 10px;
            max-width: 80%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            word-break: break-word;
            border: 1px solid var(--border-color);
        }
        
        .message-bubble::after {
            content: "";
            position: absolute;
            bottom: 12px; /* 原来的top改为bottom */
            left: -8px;
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-right: 8px solid;
            border-right-color: inherit;
        }
        
        .input-area {
            display: flex;
            align-items: flex-end;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .message-input {
            flex: 1;
            min-width: 0;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: 'ZCOOL XiaoWei', serif;
            font-size: 14px;
            background-color: var(--paper-color);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s;
            margin-right: 8px;
            margin-bottom: 8px;
            height: 40px;
        }
        
        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05), 0 0 0 3px rgba(165, 127, 91, 0.2);
        }
        
        .emoji-picker {
            margin: 0 5px;
        }
        
        .emoji-button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            height: 40px;
        }
        
        .emoji-list {
            display: none;
            position: absolute;
            background: var(--paper-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 10;
            max-width: 240px;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .emoji-list span {
            font-size: 18px;
            margin: 4px;
            cursor: pointer;
            display: inline-block;
            width: 24px;
            text-align: center;
        }
        
        .send-button {
            padding: 0 16px;
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: 'ZCOOL XiaoWei', serif;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s;
            min-width: 60px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-button i {
            margin-right: 5px;
        }
        
        .send-button:hover {
            background-color: var(--primary-dark);
        }
        
        .login-container {
            padding: 20px;
            text-align: center;
        }
        
        .login-title {
            font-family: 'Ma Shan Zheng', cursive;
            font-size: clamp(1.5rem, 3vw, 1.8rem);
            color: var(--title-color);
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: 'ZCOOL XiaoWei', serif;
            font-size: 14px;
            background-color: var(--paper-color);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .form-actions {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-button {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: 'ZCOOL XiaoWei', serif;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-button i {
            margin-right: 5px;
        }
        
        .action-button:hover {
            background-color: var(--primary-dark);
        }
        
        .error-message {
            color: #c0392b;
            margin-bottom: 12px;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .welcome-text {
            font-family: 'Ma Shan Zheng', cursive;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            color: var(--title-color);
            margin: 0;
        }
        
        .logout-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
        }
        
        .logout-link i {
            margin-right: 5px;
        }
        
        .logout-link:hover {
            color: var(--primary-dark);
        }
        
        /* 页脚版权样式 */
        .book-footer {
            padding: 12px;
            text-align: center;
            font-size: 14px;
            color: var(--primary-dark);
            font-family: 'ZCOOL XiaoWei', serif;
            border-top: 1px solid var(--border-color);
            background: var(--primary-light);
            background-image: linear-gradient(to right, var(--primary-light), #d4c6a2);
            position: relative;
        }
        
        .book-footer::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: repeating-linear-gradient(
                90deg,
                var(--primary-light),
                var(--primary-light) 6px,
                #d4c6a2 6px,
                #d4c6a2 12px
            );
        }
        
        .copyright {
            display: inline-block;
            animation: fadeIn 1.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* 密码提示样式 */
        .password-tip {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f5ee;
            border-left: 3px solid var(--primary-color);
            border-radius: 0 4px 4px 0;
            font-style: italic;
            color: var(--primary-dark);
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .password-tip i {
            color: var(--primary-color);
            font-size: 16px;
        }
        
        /* 响应式设计 */
        @media (min-width: 768px) {
            .book-container {
                max-width: 800px;
            }
            
            .chat-box {
                max-height: 500px;
            }
            
            .book-title {
                font-size: 28px;
            }
            
            .welcome-text {
                font-size: 24px;
            }
            
            .form-actions {
                flex-direction: row;
            }
            
            .message-avatar {
                width: 56px;
                height: 56px;
                font-size: 16px;
            }
            
            :root {
                --avatar-size: 56px;
            }
            
            .message-time {
                left: calc(var(--avatar-size) + 15px);
            }
            
            .book-footer {
                font-size: 16px;
            }
            
            .password-tip {
                justify-content: center;
            }
        }
    </style> 
</head> 
<body> 
    <div class="book-container">
        <div class="book-header">
            <h1 class="book-title">书签聊天室</h1>
            <div class="book-marks">
                <div class="book-mark"></div>
                <div class="book-mark"></div>
                <div class="book-mark"></div>
            </div>
        </div>
        
        <div class="chat-container">
            <?php if (!isset($_SESSION['username'])): ?> 
                <div class="login-container">
                    <h2 class="login-title">欢迎来到书签聊天室</h2>
                    
                    <?php if (!empty($banMessage)): ?> 
                        <p class="error-message"><?php echo $banMessage; ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($message)): ?> 
                        <p class="error-message"><?php echo $message; ?></p>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="form-group">
                            <input type="text" class="form-control" name="username" placeholder="请输入用户名" required>
                        </div>
                        <div class="form-group">
                            <input type="password" class="form-control" name="password" placeholder="请输入密码" required>
                        </div>
                        <div class="form-actions">
                        <a href="/sj/admin.php" class="action-button admin-button">
                            <i class="fas fa-cog"></i> 管理员登录
                        </a>
                            <button type="submit" name="action" value="create_account" class="action-button">
                                <i class="fas fa-user-plus"></i> 注册账号
                            </button>
                            <button type="submit" name="action" value="login" class="action-button">
                                <i class="fas fa-sign-in-alt"></i> 登录
                            </button>
                        </div>
                    </form>
                    
                    <!-- 新增的密码提示信息 -->
                    <div class="password-tip">
                        <i class="fas fa-lock"></i>
                        我们将采用随机哈希值保存密码，密码无法被除了你以外的任何人获取，请妥善保存好密码
                    </div>
                    
                </div>
            <?php else: ?> 
                <div class="user-info">
                    <h2 class="welcome-text">欢迎，<?php echo $_SESSION['username']; ?></h2>
                    <a href="?logout=1" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> 退出登录
                    </a>
                </div>
                
                <?php if (!empty($sendMessageError)): ?> 
                    <p class="error-message"><?php echo $sendMessageError; ?></p>
                <?php endif; ?>
                
                <div class="chat-box" id="chat-box">
                    <?php // 从后往前遍历，确保最新消息在底部 ?>
                    <?php for ($i = count($chatRecords) - 1; $i >= 0; $i--): 
                        $record = $chatRecords[$i];
                        list($time, $ip, $messageText, $region, $username) = explode('-', $record);
                        $avatarColor = generateAvatarColor($username);
                        $avatarText = generateAvatarText($username);
                    ?> 
                        <div class="chat-message">
                            <div class="message-avatar" style="background-color: <?php echo $avatarColor; ?>">
                                <?php echo $avatarText; ?>
                            </div>
                            <div class="message-time"><?php echo $time; ?></div>
                            <div class="message-content">
                                <div class="message-bubble"><?php echo $messageText; ?></div>
                            </div>
                        </div>
                    <?php endfor; ?> 
                </div>
                
                <div class="input-area">
                    <input type="text" id="message-input" class="message-input" name="message" placeholder="输入消息...">
                    <div class="emoji-picker">
                        <button id="emoji-button" class="emoji-button">😀</button>
                        <div class="emoji-list" id="emoji-list">
                            <span onclick="insertEmoji('😀')">😀</span>
                            <span onclick="insertEmoji('🤔')">🤔</span>
                            <span onclick="insertEmoji('🙄')">🙄</span>
                            <span onclick="insertEmoji('👌')">👌</span>
                            <span onclick="insertEmoji('😂')">😂</span>
                            <span onclick="insertEmoji('🤓')">🤓</span>
                            <span onclick="insertEmoji('🤡')">🤡</span>
                            <span onclick="insertEmoji('💩')">💩</span>
                            <span onclick="insertEmoji('🧧')">🧧</span>
                            <span onclick="insertEmoji('🧨')">🧨</span>
                        </div>
                    </div>
                    <button type="button" class="send-button" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i> 发送
                    </button>
                </div>
            <?php endif; ?> 
        </div>
        
        <div class="book-footer">
            <div class="copyright">
                版权所有2025&copy;书签聊天室
            </div>
        </div>
    </div>

    <script> 
        function insertEmoji(emoji) { 
            var input = document.getElementById('message-input'); 
            input.value += emoji; 
            input.focus(); 
        } 
 
        document.getElementById('emoji-button').addEventListener('click', function() { 
            var emojiList = document.getElementById('emoji-list'); 
            emojiList.style.display = emojiList.style.display === 'block' ? 'none' : 'block'; 
        }); 
 
        // 点击页面其他地方关闭表情选择器
        document.addEventListener('click', function(event) {
            var emojiButton = document.getElementById('emoji-button');
            var emojiList = document.getElementById('emoji-list');
            
            if (!emojiButton.contains(event.target) && !emojiList.contains(event.target)) {
                emojiList.style.display = 'none';
            }
        });
 
        function sendMessage() { 
            var message = document.getElementById('message-input').value.trim();
            if (message === '') return;
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo $_SERVER['PHP_SELF']; ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        alert(response.error);
                    } else {
                        document.getElementById('message-input').value = '';
                        updateChat(); // 发送成功后更新聊天记录
                    }
                }
            };
            xhr.send('send_message=1&message=' + encodeURIComponent(message));
        } 
 
        function updateChat() { 
            var xhr = new XMLHttpRequest(); 
            xhr.open('GET', '<?php echo $_SERVER['PHP_SELF']; ?>', true); 
            xhr.onreadystatechange = function() { 
                if (xhr.readyState === 4 && xhr.status === 200) { 
                    var parser = new DOMParser(); 
                    var doc = parser.parseFromString(xhr.responseText, 'text/html'); 
                    var chatBox = document.getElementById('chat-box'); 
                    
                    // 保存当前滚动位置
                    var scrollTop = chatBox.scrollTop;
                    var isAtBottom = scrollTop + chatBox.clientHeight >= chatBox.scrollHeight - 10;
                    
                    // 更新聊天内容
                    chatBox.innerHTML = doc.getElementById('chat-box').innerHTML;
                    
                    // 如果之前在底部，滚动到底部，否则保持原有位置
                    if (isAtBottom) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    } else {
                        chatBox.scrollTop = scrollTop;
                    }
                } 
            }; 
            xhr.send(); 
        } 
 
        // 初始加载后滚动到底部
        document.addEventListener('DOMContentLoaded', function() {
            var chatBox = document.getElementById('chat-box');
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
            
            // 每3秒更新一次聊天记录
            setInterval(updateChat, 3000);
        });
        
        // 处理退出登录
        <?php if (isset($_GET['logout'])) { 
            session_destroy(); 
        } ?>
    </script> 
</body> 
</html>