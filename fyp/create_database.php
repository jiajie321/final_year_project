<?php
$servername = "127.0.0.1:3307"; $username = "jiajie"; $password = "secef017";
try {
$conn = new PDO("mysql:host=$servername", $username, $password);
// set the PDO error mode to exception
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "CREATE DATABASE final_year_project";
// use exec() because no results are returned
$conn->exec($sql);
echo "Database created successfully<br>";
} catch(PDOException $e) {
echo $sql . "<br>" . $e->getMessage();
}
$conn = null;
?>