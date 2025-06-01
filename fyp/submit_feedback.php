<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_name'])) {
    die("User not logged in.");
}

require 'db_connection.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $message = '';

     // Get the current user's register_id
    $stmt = $conn->prepare("SELECT id FROM register WHERE user_name = :user_name");
    $stmt->bindParam(':user_name', $_SESSION['user_name']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found in register table.");
    }

    $register_id = $user['id'];

    // Processing feedback form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $full_name = $_POST['feedback_full_name'] ?? '';
        $phone_number = $_POST['feedback_phone_number'] ?? '';
        $email = $_POST['feedback_email'] ?? '';
        $description = $_POST['feedback_description'] ?? '';
        $photo_url = '';

       // Processing photos for upload
        if (isset($_FILES['feedback_photo']) && $_FILES['feedback_photo']['error'] == 0) {
            $photo_dir = 'uploads/';
            $photo_file = $photo_dir . basename($_FILES['feedback_photo']['name']);
            $photo_type = strtolower(pathinfo($photo_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($photo_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['feedback_photo']['tmp_name'], $photo_file)) {
                    $photo_url = $photo_file;
                } else {
                    $message = "Error uploading the photo.";
                }
            } else {
                $message = "Only JPG, JPEG, PNG, WEBP & GIF files are allowed.";
            }
        }

        if ($full_name && $phone_number && $email && $description) {
            $stmt = $conn->prepare("INSERT INTO Feedback (register_id, full_name, phone_number, email, description, photo_url) VALUES (:register_id, :full_name, :phone_number, :email, :description, :photo_url)");
            $stmt->bindParam(':register_id', $register_id);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':photo_url', $photo_url);
            $stmt->execute();

            $message = "Feedback submitted successfully.";
        } else {
            $message = "Please fill in all required fields.";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Submit Feedback</title>
    <link rel="stylesheet" href="css/feedback.css" />
</head>

<body>
    <header>
        <div class="navbar">
            <a href="resident_nonresident.php">Home Page</a>
            <a href="cancel_booking.php">Cancel Booking</a>
            <a href="view_booking_history.php">View Booking History</a>
            <a href="submit_feedback.php">Feedback</a>
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
    <main>
        <h1>Submit Feedback</h1>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>
        <div class="form-container">
            <form method="post" action="" enctype="multipart/form-data">
                <input type="text" id="feedback_full_name" name="feedback_full_name" placeholder="Full Name" required>
                <input type="text" id="feedback_phone_number" name="feedback_phone_number" placeholder="Phone Number"
                    required>
                <input type="email" id="feedback_email" name="feedback_email" placeholder="Email" required>
                <textarea id="feedback_description" name="feedback_description" placeholder="Description"
                    required></textarea>
                <input type="file" id="feedback_photo" name="feedback_photo" accept="image/*">
                <input type="submit" value="Submit Feedback">
            </form>
        </div>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | &copy; Created by
            JiaJie</p>
    </footer>
</body>

</html>