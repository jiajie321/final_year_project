<?php
session_start();
// 销毁所有会话数据
session_destroy();

// 重定向到登录页面
header("Location: login_resident_nonresident.php");
exit();
?>
