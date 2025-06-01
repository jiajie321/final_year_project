<?php
require 'db_connection.php';

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $conn->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->exec("USE $dbname");

    // Define tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS Super_Admin (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(60) NOT NULL,
            phone_number VARCHAR(25) NOT NULL,
            email VARCHAR(50) NOT NULL,
            user_name VARCHAR(30) NOT NULL,
            user_password VARCHAR(30) NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS Admin_Manager (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(60) NOT NULL,
            joining_date DATE NOT NULL,
            phone_number VARCHAR(25) NOT NULL,
            email VARCHAR(50) NOT NULL,
            user_name VARCHAR(30) NOT NULL,
            user_password VARCHAR(30) NOT NULL,
            added_by_Super_admin INT UNSIGNED,
            FOREIGN KEY (added_by_Super_admin) REFERENCES Super_Admin(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS Facility (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            photo_url VARCHAR(255) DEFAULT NULL,
            status VARCHAR(10) DEFAULT 'Open',
            resident_price DECIMAL(10, 2) DEFAULT 0.00,
            nonresident_price DECIMAL(10, 2) DEFAULT 0.00,
            benefits TEXT,
            created_by_Super_admin INT UNSIGNED,
            updated_by_Super_admin INT UNSIGNED,
            status_updated_by INT UNSIGNED DEFAULT NULL,  
            status_updated_by_role ENUM('Super Admin', 'Admin Manager') DEFAULT NULL,  
            status_updated_by_fullname VARCHAR(255) DEFAULT NULL, 
            FOREIGN KEY (created_by_Super_admin) REFERENCES Super_Admin(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by_Super_admin) REFERENCES Super_Admin(id) ON DELETE SET NULL,
            FOREIGN KEY (status_updated_by) REFERENCES Super_Admin(id) ON DELETE SET NULL  
        )",

        "CREATE TABLE IF NOT EXISTS Announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_by_Admin_manager INT UNSIGNED,
            FOREIGN KEY (sent_by_Admin_manager) REFERENCES Admin_Manager(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS register (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(30) NOT NULL,
            password VARCHAR(30) NOT NULL,
            full_name VARCHAR(60) NOT NULL,
            phone_number VARCHAR(25) NOT NULL,
            email VARCHAR(50) NOT NULL,
            block VARCHAR(50) DEFAULT NULL, -- Only applicable for residents
            type ENUM('resident', 'nonresident') NOT NULL,
            UNIQUE (user_name),
            INDEX (user_name)
        )",
        "CREATE TABLE IF NOT EXISTS booking (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            register_id INT UNSIGNED NOT NULL,
            facility_id INT UNSIGNED NOT NULL,
            booking_date DATE NOT NULL,
            booking_time TIME NOT NULL,
            booking_duration INT NOT NULL,
            fees DECIMAL(10, 2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            type ENUM('resident', 'nonresident') NOT NULL,
            FOREIGN KEY (register_id) REFERENCES register(id) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (facility_id) REFERENCES Facility(id) ON DELETE CASCADE ON UPDATE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS payment (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            payment_method VARCHAR(50) NOT NULL,
            type ENUM('resident', 'nonresident') NOT NULL,
            FOREIGN KEY (booking_id) REFERENCES booking(id) ON DELETE CASCADE ON UPDATE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS Feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            register_id INT UNSIGNED NOT NULL,
            full_name VARCHAR(60) NOT NULL,
            phone_number VARCHAR(25) NOT NULL,
            email VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            photo_url VARCHAR(255) DEFAULT NULL,
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (register_id) REFERENCES register(id) ON DELETE CASCADE ON UPDATE CASCADE
        )"
    ];

    // Create tables
    foreach ($tables as $tableSql) {
        $conn->exec($tableSql);
    }

    echo "<p>All tables created successfully</p>";

    // Insert sample data into Super_Admin table
    $superAdminData = [
        'full_name' => 'TanJiaJie',
        'phone_number' => '011-2613xxxx',
        'email' => 'jiajie@gmail.com',
        'user_name' => 'jiajie',
        'user_password' => '1234'
    ];

    $stmt = $conn->prepare("INSERT INTO Super_Admin (full_name, phone_number, email, user_name, user_password) VALUES (:full_name, :phone_number, :email, :user_name, :user_password)");
    $stmt->execute($superAdminData);

    echo "<p>Super_Admin data inserted successfully</p>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;
?>