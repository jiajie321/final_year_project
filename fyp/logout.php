<?php
session_start();

// 设置时区为马来西亚时间
date_default_timezone_set("Asia/Kuala_Lumpur");

// 获取当前时间
$logoutDateTime = date("Y-m-d H:i:s");

// 如果用户会话存在，记录用户名
if (isset($_SESSION["user_name"])) {
    $userName = $_SESSION["user_name"];
} else {
    $userName = "Unknown User";
}

// 清除所有会话变量
$_SESSION = array();

// 如果使用cookie保存会话，删除cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 清除用户cookie
setcookie("user", "", time() - 3600);

// 重定向到登录页面
header('Location: http://localhost/fyp/login_superadmin.php');

// 记录注销操作
$logFileName = "logs.txt";
$logFile = fopen($logFileName, "a") or die("Unable to open log file!");
$logMessage = "$userName Logout at {$logoutDateTime}" . PHP_EOL;
fwrite($logFile, $logMessage);
fclose($logFile);

exit;
?>

