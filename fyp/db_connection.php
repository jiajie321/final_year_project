<?php

$servername = "127.0.0.1:3307"; // The server address and port where the MySQL database is hosted (localhost with port 3307)
$username = "jiajie";           // The username for accessing the MySQL database
$password = "secef017";         // The password for the specified username
$dbname = "final_year_project"; // The name of the database to connect to

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>