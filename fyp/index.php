<?php
$host = '3307';        // 如果你用 Docker Compose，可能是 'mysql'
$username = 'jiajie';         // 改成你的 phpMyAdmin 用户名
$password = 'secef017';             // 改成你的密码
$database = 'final_year_project';          // 改成你的数据库名称

// 创建连接
$conn = new mysqli($host, $username, $password, $database);

// 检查连接
if ($conn->connect_error) {
    die("❌ 连接数据库失败: " . $conn->connect_error);
}

echo "<h1>✅ 成功连接数据库！欢迎来到我的 Final Year Project 系统！</h1>";

$conn->close();
?>
