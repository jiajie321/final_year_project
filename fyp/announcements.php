<?php
session_start();
require 'db_connection.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch announcements
    $stmt = $conn->prepare("SELECT id, title, message, date FROM Announcements ORDER BY date DESC");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Announcements</title>
    <link rel="stylesheet" href="css/announcements.css" />
    <script>
    function checkLogin(event, feature) {
        <?php if (!isset($_SESSION['user_name'])): ?>
        event.preventDefault();
        var modal = document.getElementById("myModal");
        modal.style.display = "block";
        <?php else: ?>
        return true;
        <?php endif; ?>
    }

    function closeModal() {
        var modal = document.getElementById("myModal");
        modal.style.display = "none";
    }
    </script>
</head>

<body>
    <header>
        <div class="navbar">
            <a href="resident_nonresident.php">Home Page</a>
            <a href="cancel_booking.php" onclick="checkLogin(event, 'cancel booking')">Cancel Booking</a>
            <a href="view_booking_history.php" onclick="checkLogin(event, 'view booking history')">View Booking
                History</a>
            <a href="submit_feedback.php" onclick="checkLogin(event, 'submit feedback')">Feedback</a>
            <a href="about_us.php">About Us</a>
            <a href="announcements.php">Announcements</a>

            <div class="welcome" style="position: relative;">
                <?php if (isset($_SESSION['full_name'])): ?>
                <span id="user-name" style="cursor: pointer;">
                    Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a href="logouts.php" style="color: white; margin-left: 5px;">Log Out</a>
                <!-- Hidden Reset Password link -->
                <a href="reset_password.php" id="reset-password">Reset Password</a>
                <?php else: ?>
                <a href="login_resident_nonresident.php" style="color: white;">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <h1>Announcements</h1>
    <div class="content-wrapper">
        <div class="content">
            <?php if (empty($announcements)): ?>
            <p>No announcements available.</p>
            <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
            <div class="announcement">
                <div class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></div>
                <div class="announcement-date"><?= htmlspecialchars($announcement['date']) ?></div>
                <div class="announcement-message"><?= nl2br(htmlspecialchars($announcement['message'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | Created by JiaJie</p>
    </footer>

    <div id="myModal" class="modal">
        <div class="modal-content">
            <p>To access this feature, you need to register an account. Please click 'Register' if you don't have an
                account yet, or click 'OK' if you wish to proceed without registering.</p>
            <button class="register" onclick="location.href='login_resident_nonresident.php'">Register</button>
            <button class="ok" onclick="closeModal()">OK</button>
        </div>
    </div>
</body>

</html>