<?php
require 'db_connection.php';
session_start(); // Ensure session is started

// Check if full_name is set in the session
if (!isset($_SESSION['full_name'])) {
    die("User not logged in or session expired.");
}

// Trim the full_name to remove any leading or trailing spaces
$full_name = trim($_SESSION['full_name']);

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch upcoming bookings for the user from the unified booking table
    $stmt = $conn->prepare("
    SELECT booking.id, booking.facility_id, booking.booking_date, booking.booking_time, booking.status, register.type AS booking_type
    FROM booking
    INNER JOIN register ON booking.register_id = register.id
    WHERE TRIM(register.full_name) = TRIM(:full_name)
    AND (
        booking.booking_date > CURDATE() 
        OR (booking.booking_date = CURDATE() AND booking.booking_time > CURTIME())
    )
    AND booking.status = 'booked'
    ORDER BY booking.booking_date, booking.booking_time
");

    $stmt->bindParam(':full_name', $full_name);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch facilities
    $stmt = $conn->prepare("SELECT id, name FROM Facility");
    $stmt->execute();
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $facilityNames = array_column($facilities, 'name', 'id');

    // Process cancel booking form submission via AJAX
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel_booking') {
        if (!empty($_POST['booking_id'])) {
            $booking_id = $_POST['booking_id'];

            // Update the booking status to 'cancelled'
            $stmt = $conn->prepare("UPDATE booking SET status = 'cancelled' WHERE id = :booking_id AND status = 'booked'");
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->execute();

            // Send a JSON response
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => 'Booking cancelled successfully.']);
            } else {
                echo json_encode(['error' => 'Failed to cancel booking.']);
            }
            exit; // End the script after sending the response
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking</title>
    <link rel="stylesheet" href="css/cancel_booking.css" />
</head>

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

<body>
    <div class="header2">
        <h1>Cancel Booking</h1>
    </div>
    <div class="container">
        <p class="note">Note: No refund will be provided for cancelled bookings.</p>

        <?php if (!empty($bookings)): ?>
        <table>
            <thead>
                <tr>
                    <th>Facility</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?= htmlspecialchars($facilityNames[$booking['facility_id']]) ?></td>
                    <td><?= htmlspecialchars($booking['booking_date']) ?></td>
                    <td><?= htmlspecialchars($booking['booking_time']) ?></td>
                    <td>
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <input type="button" value="Cancel" class="cancel-button">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No upcoming bookings to cancel.</p>
        <?php endif; ?>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to cancel this booking? No refunds will be provided.</p>
            <div class="modal-buttons">
                <button id="confirmButton" class="modal-button">Yes, Cancel</button>
                <button id="cancelButton" class="modal-button">No, Go Back</button>
            </div>
        </div>
    </div>

    <!-- Custom Alert Modal -->
    <div id="customAlertModal" class="modal">
        <div class="modal-content">
            <p id="alertMessage"></p>
            <button id="closeAlertButton" class="modal-button">OK</button>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | Created by JiaJie</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get the modal
        var modal = document.getElementById("confirmationModal");
        var alertModal = document.getElementById("customAlertModal");
        var alertMessage = document.getElementById("alertMessage");
        var closeAlertButton = document.getElementById("closeAlertButton");

        // Get the buttons that open the modal
        var cancelButtons = document.querySelectorAll('.cancel-button');

        // Get the elements inside the modal
        var confirmButton = document.getElementById("confirmButton");
        var cancelButton = document.getElementById("cancelButton");

        // Store the booking ID to be cancelled
        var currentBookingId;

        // Loop through all cancel buttons
        cancelButtons.forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent the form from submitting
                currentBookingId = this.previousElementSibling.value;
                modal.style.display = "flex"; // Show the modal
            });
        });

        // When the user clicks on the "Yes, Cancel" button
        confirmButton.onclick = function() {
            modal.style.display = "none";
            // Perform AJAX request to cancel booking
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alertMessage.textContent = response.success;
                        alertModal.style.display = "flex";
                        setTimeout(function() {
                            location.reload(); // Reload the page to reflect changes
                        }, 1000);
                    } else if (response.error) {
                        alertMessage.textContent = response.error;
                        alertModal.style.display = "flex";
                    }
                } else {
                    alertMessage.textContent = "An error occurred.";
                    alertModal.style.display = "flex";
                }
            };
            xhr.send("action=cancel_booking&booking_id=" + encodeURIComponent(currentBookingId));
        };

        // When the user clicks on the "No, Go Back" button
        cancelButton.onclick = function() {
            modal.style.display = "none"; // Hide the modal
        };

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal || event.target == alertModal) {
                modal.style.display = "none";
                alertModal.style.display = "none";
            }
        };

        // When the user clicks the "OK" button in the alert modal
        closeAlertButton.onclick = function() {
            alertModal.style.display = "none";
        }
    });
    </script>
</body>

</html>