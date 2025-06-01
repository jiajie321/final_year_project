<?php
session_start();
?>
<!DOCTYPE html>
<html>

<head>
    <title>About Us</title>
    <link rel="stylesheet" href="css/about_us.css" />
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
    <div class="main">
        <div class="sidebar">
            <h1>About Us</h1>
            <p>Welcome to our condominium facility management system. We strive to provide the best services for our
                residents and visitors.</p>
            <h2>Operation Hours</h2>
            <p>Our facilities are open from 10:00 AM to 9:30 PM daily, including weekends and public holidays. (Booking
                system available from 10:00 AM to 8:30 PM)</p>
            <h2>Management Contact</h2>
            <p>For any inquiries or assistance, please contact our management office at:</p>
            <p>Email: management@condominium.com</p>
            <p>Phone: (123) 456-7890</p>
            <h2>Security Contact</h2>
            <p>For security concerns or emergencies, contact our security team at:</p>
            <p>Email: security@condominium.com</p>
            <p>Phone: (098) 765-4321</p>
        </div>
        <div class="content">
            <h2>Our Location</h2>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15935.645874494741!2d101.7441073!3d3.1181191!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc367bb3bf511b%3A0xddc6d8ab54e0bd5f!2s28%20Boulevard%20(28%20BLVD)!5e0!3m2!1sen!2smy!4v1719755052079!5m2!1sen!2smy"
                height="450" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
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