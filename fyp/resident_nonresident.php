<?php
session_start();
require 'db_connection.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch facilities
    $stmt = $conn->prepare("SELECT id, name, photo_url, status FROM Facility");
    $stmt->execute();
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch announcements
    $stmt = $conn->prepare("SELECT id, title, message, date FROM Announcements ORDER BY date DESC");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $message = '';
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Condominium Facilities Booking Page</title>
    <link rel="stylesheet" href="css/resident.css" />
    <script>
    function checkLogin(event, url) {
        <?php if (!isset($_SESSION['full_name'])): ?>
        event.preventDefault();
        document.getElementById('myModal').style.display = "block";
        <?php else: ?>
        window.location.href = url;
        <?php endif; ?>
    }

    function closeModal() {
        document.getElementById('myModal').style.display = "none";
    }
    </script>
</head>

<body>
    <header>
        <div class="navbar">
            <a href="resident_nonresident.php">Home Page</a>
            <a href="cancel_booking.php" onclick="checkLogin(event, 'cancel_booking.php')">Cancel Booking</a>
            <a href="view_booking_history.php" onclick="checkLogin(event, 'view_booking_history.php')">View Booking
                History</a>
            <a href="submit_feedback.php" onclick="checkLogin(event, 'submit_feedback.php')">Feedback</a>
            <a href="about_us.php">About Us</a>
            <a href="announcements.php">Announcements</a>
        </div>
        <div class="welcome" style="position: relative;">
            <?php if (isset($_SESSION['full_name'])): ?>
            <span id="user-name" style="cursor: pointer;">
                Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <a href="logouts.php" style="color: white; margin-left: 5px;">Log Out</a>
            <!-- Hidden Reset Password link -->
            <a href="reset_password.php" id="reset-password"
                style="display: none; color: white; position: absolute; top: 25px; left: 0;">
                Reset Password
            </a>
            <?php else: ?>
            <a href="login_resident_nonresident.php" style="color: white;">Sign Up</a>
            <?php endif; ?>
        </div>
    </header>

    <script>
    // JavaScript to handle the hover effect and keep the link visible
    const userNameElement = document.getElementById('user-name');
    const resetPasswordLink = document.getElementById('reset-password');

    userNameElement.addEventListener('mouseover', function() {
        resetPasswordLink.style.display = 'block';
    });

    // Keep the reset password link visible once it appears
    resetPasswordLink.addEventListener('mouseover', function() {
        resetPasswordLink.style.display = 'block';
    });

    // Optionally hide the reset password link if the user moves the mouse away
    resetPasswordLink.addEventListener('mouseout', function() {
        resetPasswordLink.style.display = 'none';
    });

    userNameElement.addEventListener('mouseout', function() {
        resetPasswordLink.style.display = 'none';
    });
    </script>


    <div class="content">
        <h1>Condominium Facilities Booking System</h1>
    </div>

    <?php if ($message) echo "<p class='message'>$message</p>"; ?>

    <div id="facilities">
        <div class="facility-photos">
            <?php foreach ($facilities as $facility): ?>
            <div class="facility-photo">
                <h3><?= htmlspecialchars($facility['name']) ?></h3>
                <img src="<?= htmlspecialchars($facility['photo_url']) ?>"
                    alt="<?= htmlspecialchars($facility['name']) ?>"
                    onclick="checkLogin(event, 'detail.php?id=<?= $facility['id'] ?>')">
                <?php if (trim(strtolower($facility['status'])) == 'closed'): ?>
                <button style="background-color: grey; cursor: not-allowed;">Under Maintenance</button>
                <?php else: ?>
                <button onclick="checkLogin(event, 'detail.php?id=<?= $facility['id'] ?>')">More Description</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | Created by JiaJie</p>
    </footer>

    <!-- Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <p>To access this feature, you need to register an account. Please click 'Register' if you don't have an
                account yet, or click 'OK' if you wish to proceed without registering.</p>
            <div class="modal-buttons">
                <a href="login_resident_nonresident.php" class="register-btn">Register</a>
                <button class="close-btn" onclick="closeModal()">OK</button>
            </div>
        </div>
    </div>
</body>

</html>