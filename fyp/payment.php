<?php
require 'db_connection.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/SMTP.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/Exception.php';
require __DIR__ . '/phpqrcode-2010100721_1.1.4/phpqrcode/qrlib.php';
require __DIR__ . '/FPDF-master/FPDF-master/fpdf.php'; // Include FPDF library

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start(); // Ensure session is started

$paymentMessage = '';

// Check if the booking ID and facility ID are set in the session
if (!isset($_SESSION['booking_id']) || !isset($_SESSION['facility_id'])) {
    die("Invalid booking or facility ID.");
}

$booking_id = $_SESSION['booking_id'];
$facility_id = $_SESSION['facility_id'];

// Example usage: Processing a payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_full_name'])) {
    // Handle payment form submission
    processPayment($conn, $booking_id, $facility_id);
}

function generateQRCode($data, $filename) {
    $tempDir = __DIR__ . '/temp/';
    if (!file_exists($tempDir)) {
        mkdir($tempDir);
    }
    $filePath = $tempDir . $filename;
    QRcode::png($data, $filePath, QR_ECLEVEL_L, 5);
    return $filePath;
}

function sendEmail($to, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;
        $mail->Username = 'tanjiajie0321@e.neivce.edu.my'; // SMTP username
        $mail->Password = '030321140539'; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption, ssl also accepted
        $mail->Port = 587; // TCP port to connect to

        // Recipients
        $mail->setFrom('tanjiajie0321@e.neivce.edu.my', 'Facility Booking System');
        $mail->addAddress($to);

        // Attachments
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }

        // Add the signature image
        $mail->AddEmbeddedImage('C:/xampp/htdocs/fyp/images/jiajie.jpg', 'signature_image', 'jiajie.jpg');

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;

        // Email body content with signature
        $mail->Body = "
        <html>
        <body>
            $body
            <div>
                <p>Signature: <span style='font-style:italic;'>Tan</span></p>
                <p>Name: JiaJie</p>
                <img src='cid:signature_image' alt='Signature Image' style='width: 200px; height: auto;'>
            </div>
        </body>
        </html>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

function generateReceipt($booking, $facility_name) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(0, 0, 0);
    $pdf->Cell(0, 10, 'Facility Booking Receipt', 0, 1, 'C', true);
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);

    $pdf->Cell(50, 10, 'Name:', 1);
    $pdf->Cell(0, 10, $booking['full_name'], 1, 1);

    $pdf->Cell(50, 10, 'Facility:', 1);
    $pdf->Cell(0, 10, $facility_name, 1, 1);

    $pdf->Cell(50, 10, 'Booking Date:', 1);
    $pdf->Cell(0, 10, $booking['booking_date'], 1, 1);

    $pdf->Cell(50, 10, 'Booking Time:', 1);
    $pdf->Cell(0, 10, $booking['booking_time'], 1, 1);

    $pdf->Cell(50, 10, 'Booking Duration:', 1);
    $pdf->Cell(0, 10, $booking['booking_duration'] . ' hours', 1, 1);

    $pdf->Cell(50, 10, 'Amount Paid:', 1);
    $pdf->Cell(0, 10, 'RM ' . $booking['fees'], 1, 1);

    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Please use the QR code below to access the facility:', 0, 1, 'C');

    $qrData = "Booking Confirmation for " . $facility_name . " by " . $booking['full_name'] . " on " . $booking['booking_date'] . " at " . $booking['booking_time'];
    if ($booking['type'] == 'nonresident') {
        $qrData .= ". Please use this QR code to enter the condo and the facility.";
    } else {
        $qrData .= ". Please use this QR code to enter the facility.";
    }
    $qrFilePath = generateQRCode($qrData, 'qrcode.png');
    $pdf->Image($qrFilePath, 80, $pdf->GetY(), 50, 50);

    $receiptPath = __DIR__ . '/temp/receipt.pdf';
    $pdf->Output('F', $receiptPath);
    return $receiptPath;
}

function processPayment($conn, $booking_id, $facility_id) {
    global $paymentMessage;
    $full_name = $_POST['payment_full_name'];
    $payment_method = $_POST['payment_method']; // Get the payment method

    // Retrieve the fees amount for the most recent booking
    $stmt = $conn->prepare("
        SELECT booking.id AS booking_id, register_id, facility_id, booking_duration, fees, email, full_name, booking_date, booking_time, booking.type
        FROM booking
        JOIN register ON booking.register_id = register.id
        WHERE booking.id = :booking_id AND facility_id = :facility_id AND status = 'booked'
    ");
    $stmt->bindParam(':booking_id', $booking_id);
    $stmt->bindParam(':facility_id', $facility_id);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking !== false) {
        // Insert payment into the payment table
        $stmt = $conn->prepare("
            INSERT INTO payment (booking_id, amount, payment_method, type)
            VALUES (:booking_id, :amount, :payment_method, :booking_type)
        ");
        $stmt->bindParam(':booking_id', $booking['booking_id']);
        $stmt->bindParam(':amount', $booking['fees']);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':booking_type', $booking['type']);
        $stmt->execute();

        $paymentMessage = "Payment successful.";

        // Generate and send QR code and receipt
        $facility_name = getFacilityName($conn, $booking['facility_id']);
        $receiptPath = generateReceipt($booking, $facility_name);
        $emailBody = "Dear $full_name, your payment of RM " . $booking['fees'] . " for the facility booking has been received. Please find the attached QR code for entry.";
        sendEmail($booking['email'], "Payment Confirmation", $emailBody, [$receiptPath]);
    } else {
        $paymentMessage = "No outstanding fees found for the selected booking.";
    }
}

function getFacilityName($conn, $facility_id) {
    $stmt = $conn->prepare("SELECT name FROM Facility WHERE id = :id");
    $stmt->bindParam(':id', $facility_id);
    $stmt->execute();
    $facility = $stmt->fetch(PDO::FETCH_ASSOC);
    return $facility ? $facility['name'] : 'Facility';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        h1 {
            color: #333;
            text-align: center;
            font-size: 2em;
        }
        .payment-container {
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        form {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .input-field {
            width: 300px; /* Adjust the width as needed */
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"], .payment-button {
            background-color: black;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        input[type="submit"]:hover, .payment-button:hover {
            background-color: DodgerBlue;
        }
        .payment-message {
            color: black;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
        }
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: auto;
            width: 100%;
        }
        .content-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
        }
    </style>
    <script>
        function redirectAfterSuccess() {
            setTimeout(function() {
                window.location.href = 'resident_nonresident.php';
            }, 3000); // Redirect after 3 seconds
        }
    </script>
</head>
<body>
    <div class="content-wrapper">
        <h1>Make a Payment</h1>
        <div class="payment-container">
            <form method="post" action="">
                <label for="payment_full_name">Full Name:</label>
                <input type="text" id="payment_full_name" name="payment_full_name" class="input-field" required value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>">
                <label for="payment_method">Payment Method:</label>
                <select name="payment_method" id="payment_method" class="input-field" required>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Touch 'n Go">Touch 'n Go</option>
                </select>
                <input type="submit" value="Pay">
            </form>

            <?php if ($paymentMessage): ?>
                <p class="payment-message"><?= htmlspecialchars($paymentMessage) ?></p>
                <?php if ($paymentMessage == "Payment successful."): ?>
                    <script>
                        redirectAfterSuccess();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | Created by JiaJie</p>
    </footer>
</body>
</html>
