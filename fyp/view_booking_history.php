<?php
session_start();
if (!isset($_SESSION['full_name'])) {
    header("Location: login_resident_nonresident.php");
    exit;
}

require 'db_connection.php';
require __DIR__ . '/phpqrcode-2010100721_1.1.4/phpqrcode/qrlib.php';
require __DIR__ . '/FPDF-master/FPDF-master/fpdf.php'; // Correct include path for FPDF

// Create directories if not exist
$qrDir = __DIR__ . '/qr/';
$receiptDir = __DIR__ . '/receipt/';

if (!file_exists($qrDir)) {
    mkdir($qrDir, 0777, true); // Ensure the directory is created with correct permissions
}

if (!file_exists($receiptDir)) {
    mkdir($receiptDir, 0777, true); // Ensure the directory is created with correct permissions
}

function generateQRCode($data, $filename) {
    global $qrDir;
    $filePath = $qrDir . $filename;
    QRcode::png($data, $filePath, QR_ECLEVEL_L, 5);
    return $filePath;
}

function generateReceipt($bookingDetails, $qrCodePath, $filename) {
    global $receiptDir;
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, 'Facility Booking Receipt', 0, 1, 'C');
    $pdf->Ln(10);

    // Booking Details Table
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1, 'C', true);
    $pdf->Ln(5);

    // Add booking details in a table format
    $pdf->SetFont('Arial', '', 12);
    foreach ($bookingDetails as $key => $value) {
        $pdf->Cell(50, 10, $key . ':', 1);
        $pdf->Cell(0, 10, $value, 1);
        $pdf->Ln();
    }

    // QR Code
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 10, 'Please use the QR code below to access the facility:', 0, 1, 'C');
    $pdf->Image($qrCodePath, $pdf->GetX() + 60, $pdf->GetY(), 50, 50);
    
    // Save PDF to the receipt folder
    $pdfPath = $receiptDir . $filename;
    $pdf->Output('F', $pdfPath);
    return $pdfPath;
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $message = '';

    // Fetch facilities
    $stmt = $conn->prepare("SELECT id, name FROM Facility");
    $stmt->execute();
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $facility_names = array_column($facilities, 'name', 'id');

    // Fetch booking history for the logged-in user
    $full_name = trim($_SESSION['full_name']); // Trim the full name from session
    $stmt = $conn->prepare("
        SELECT full_name, facility_id, booking_date, booking_time, booking_duration, status 
        FROM booking 
        INNER JOIN register ON booking.register_id = register.id
        WHERE TRIM(register.full_name) = TRIM(:full_name)
    ");
    $stmt->bindParam(':full_name', $full_name);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bookings)) {
        $message = "No booking records found for " . htmlspecialchars($full_name) . ".";
    } else {
        // Generate QR codes and receipts for each booking
        foreach ($bookings as &$booking) {
            $qrData = "Booking Confirmation for " . $facility_names[$booking['facility_id']] . " on " . $booking['booking_date'] . " at " . $booking['booking_time'];
            $qrFileName = 'qrcode_' . $booking['facility_id'] . '_' . strtotime($booking['booking_date'] . ' ' . $booking['booking_time']) . '.png';
            $booking['qr_code'] = generateQRCode($qrData, $qrFileName);

            // Prepare booking details for receipt
            $bookingDetails = [
                'Name' => $booking['full_name'],
                'Facility' => $facility_names[$booking['facility_id']],
                'Booking Date' => $booking['booking_date'],
                'Booking Time' => $booking['booking_time'],
                'Booking Duration' => $booking['booking_duration'] . ' hours'
            ];

            // Generate receipt
            $receiptFileName = 'receipt_' . $booking['facility_id'] . '_' . strtotime($booking['booking_date'] . ' ' . $booking['booking_time']) . '.pdf';
            $booking['receipt'] = generateReceipt($bookingDetails, $booking['qr_code'], $receiptFileName);
        }
        unset($booking); // break the reference with the last element
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>View Booking History</title>
    <link rel="stylesheet" href="css/view_booking.css" />
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
                    Welcome, <?= htmlspecialchars(trim($_SESSION['full_name'])); ?>
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
    <h1>View Booking History</h1>
    <div class="form-container">
        <?php if (!empty($message)): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php elseif (isset($bookings) && !empty($bookings)): ?>
        <div class="booking-info">
            <h3>Booking History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Facility</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>QR Code</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?= htmlspecialchars($booking['full_name']) ?></td>
                        <td><?= htmlspecialchars($facility_names[$booking['facility_id']]) ?></td>
                        <td><?= htmlspecialchars($booking['booking_date']) ?></td>
                        <td><?= htmlspecialchars($booking['booking_time']) ?></td>
                        <td><?= htmlspecialchars($booking['booking_duration']) ?> hours</td>
                        <td><?= htmlspecialchars($booking['status']) ?></td>
                        <td class="qr-code">
                            <img src="<?= 'qr/' . htmlspecialchars(basename($booking['qr_code'])) ?>" alt="QR Code"
                                onclick="showModal(this.src)">
                        </td>
                        <td class="receipt-link">
                            <a href="<?= 'receipt/' . htmlspecialchars(basename($booking['receipt'])) ?>"
                                target="_blank">View Receipt</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- The Modal -->
    <div id="myModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="img01">
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | Created by JiaJie</p>
    </footer>

    <script>
    // Get the modal
    var modal = document.getElementById("myModal");

    // Get the image and insert it inside the modal - use its "alt" text as a caption
    var modalImg = document.getElementById("img01");

    function showModal(src) {
        modal.style.display = "block";
        modalImg.src = src;
    }

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
    }

    // Close the modal when the user clicks anywhere outside of the modal image
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>
</body>

</html>