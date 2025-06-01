<?php
require 'db_connection.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/SMTP.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/Exception.php';
require __DIR__ . '/phpqrcode-2010100721_1.1.4/phpqrcode/qrlib.php';
require __DIR__ . '/FPDF-master/FPDF-master/fpdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");

$qrDir = __DIR__ . '/qr/';
$receiptDir = __DIR__ . '/receipt/';

if (!file_exists($qrDir)) {
    mkdir($qrDir, 0777, true);
}

if (!file_exists($receiptDir)) {
    mkdir($receiptDir, 0777, true);
}

if (!isset($_GET['id'])) {
    die("Invalid facility ID.");
}

$facility_id = $_GET['id'];

try {
    $stmt = $conn->prepare("SELECT id, name, photo_url, status, resident_price, nonresident_price, benefits FROM Facility WHERE id = :id AND status != 'Closed'");
    $stmt->bindParam(':id', $facility_id);
    $stmt->execute();
    $facility = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$facility) {
        die("Facility not found or it is closed.");
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    die();
}

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$prevMonth = date('Y-m', strtotime($currentMonth . " -1 month"));
$nextMonth = date('Y-m', strtotime($currentMonth . " +1 month"));
$daysInMonth = date('t', strtotime($currentMonth . '-01'));
$firstDayOfMonth = date('N', strtotime($currentMonth . '-01'));
$currentDay = date('Y-m-d');

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['user_type']) && isset($_POST['facility_id'])) {
        processBooking($conn, $facility);
    }
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
    
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, 'Facility Booking Receipt', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1, 'C', true);
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 12);
    foreach ($bookingDetails as $key => $value) {
        $pdf->Cell(50, 10, $key . ':', 1);
        $pdf->Cell(0, 10, $value, 1);
        $pdf->Ln();
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 10, 'Please use the QR code below to access the facility:', 0, 1, 'C');
    $pdf->Image($qrCodePath, $pdf->GetX() + 60, $pdf->GetY(), 50, 50);
    
    $pdfPath = $receiptDir . $filename;
    $pdf->Output('F', $pdfPath);
    return $pdfPath;
}

function sendEmail($to, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tanjiajie0321@e.neivce.edu.my'; // Update this with your actual email
        $mail->Password = '030321140539'; // Update this with your actual email password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('tanjiajie0321@e.neivce.edu.my', 'Facility Booking System');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;

        $mail->Body = "
        <html>
        <body>
            $body
            <p>Please take a moment to fill out our feedback form: <a href='https://forms.gle/nLiCN3qNUo1uNMST7'>Feedback Form</a></p>
        </body>
        </html>";

        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }

        $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

function processBooking($conn, $facility) {
    global $message;
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
    $facility_id = isset($_POST['facility_id']) ? $_POST['facility_id'] : '';
    $register_id = isset($_SESSION['register_id']) ? $_SESSION['register_id'] : null; // Get the user's register_id.

    if ($register_id === null) {
        $message = "User is not logged in or register ID is missing.";
        return;
    }

    if ($facility['status'] !== 'Open') {
        $message = "This facility is currently closed for booking.";
        return;
    }

    $base_fee = $user_type == "resident" ? $facility['resident_price'] : $facility['nonresident_price'];

    $full_name = isset($_POST['full_name']) ? $_POST['full_name'] : '';
    $phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $block = isset($_POST['block']) ? $_POST['block'] : '';
    $booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';
    $booking_time = isset($_POST['booking_time']) ? $_POST['booking_time'] : '';
    $booking_duration = isset($_POST['booking_duration']) ? $_POST['booking_duration'] : '';

    if (!$full_name || !$phone_number || !$email || !$booking_date || !$booking_time || !$booking_duration) {
        $message = "Please fill all the required fields.";
        return;
    }

    $current_date = date('Y-m-d');
    $current_time = date('H:i');
    
    // Validate booking time within allowed range
    $startTime = strtotime("10:00 AM");
    $endTime = strtotime("08:30 PM");
    $selectedTime = strtotime($booking_time);
    
    if ($selectedTime < $startTime || $selectedTime > $endTime) {
        $message = "Booking time must be between 10:00 AM and 8:30 PM.";
        return;
    }

    if ($booking_date == $current_date && $booking_time <= $current_time) {
        $_SESSION['booking_time_error'] = 'Booking time must be set in the future. Please choose a time later than the current one.';
        return;
    }

    // Check for existing bookings at the same time (only for non-gym facilities)
    if (strtolower($facility['name']) != 'gym') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM booking
            WHERE facility_id = :facility_id 
            AND booking_date = :booking_date 
            AND (
                (booking_time <= :booking_time AND ADDTIME(booking_time, SEC_TO_TIME(booking_duration * 3600)) > :booking_time) OR
                (booking_time < ADDTIME(:booking_time, SEC_TO_TIME(:booking_duration * 3600)) AND ADDTIME(booking_time, SEC_TO_TIME(booking_duration * 3600)) >= ADDTIME(:booking_time, SEC_TO_TIME(booking_duration * 3600)))
            )
            AND status != 'cancelled'
        ");
        $stmt->bindParam(':facility_id', $facility_id);
        $stmt->bindParam(':booking_date', $booking_date);
        $stmt->bindParam(':booking_time', $booking_time);
        $stmt->bindParam(':booking_duration', $booking_duration);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['booking_error'] = 'The selected time slot is already booked.';
            return;
        }
    }

    $total_fees = $base_fee * $booking_duration;

    // Insert booking information into the database
    $stmt = $conn->prepare("
        INSERT INTO booking (register_id, facility_id, booking_date, booking_time, booking_duration, fees, status, type)
        VALUES (:register_id, :facility_id, :booking_date, :booking_time, :booking_duration, :fees, 'booked', :type)
    ");
    $stmt->bindParam(':register_id', $register_id);
    $stmt->bindParam(':facility_id', $facility_id);
    $stmt->bindParam(':booking_date', $booking_date);
    $stmt->bindParam(':booking_time', $booking_time);
    $stmt->bindParam(':booking_duration', $booking_duration);
    $stmt->bindParam(':fees', $total_fees);
    $stmt->bindParam(':type', $user_type);
    $stmt->execute();

    // Ensure booking was successful
    if ($stmt->rowCount() > 0) {
        // Generate QR code and receipt
        $qrData = "Booking Confirmation for " . $facility['name'] . " on " . $booking_date . " at " . $booking_time;
        $qrCodePath = generateQRCode($qrData, $register_id . '_qrcode.png');
        $bookingDetails = [
            'Name' => $full_name,
            'Facility' => $facility['name'],
            'Booking Date' => $booking_date,
            'Booking Time' => $booking_time,
            'Booking Duration' => $booking_duration . ' hours',
            'Amount Paid' => 'RM ' . $total_fees
        ];
        $receiptPath = generateReceipt($bookingDetails, $qrCodePath, $register_id . '_receipt.pdf');

        // Send confirmation email
        $emailBody = "Your booking for " . $facility['name'] . " has been confirmed. Please find the QR code and receipt attached.";
        sendEmail($email, "Booking Confirmation for " . $facility['name'], $emailBody, [$qrCodePath, $receiptPath]);

        // Redirect to payment page
        $_SESSION['booking_success'] = 'Booking successful, please continue to complete the payment steps';
        $_SESSION['booking_id'] = $conn->lastInsertId();
        $_SESSION['facility_id'] = $facility_id;
    } else {
        $message = "Booking failed, please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link rel="stylesheet" href="css/detail.css" />

<head>
    <title><?= htmlspecialchars($facility['name']) ?> Booking Calendar</title>

</head>
<div class="navbar">
    <a href="resident_nonresident.php">Home Page</a>
    <a href="cancel_booking.php">Cancel Booking</a>
    <a href="view_booking_history.php">View Booking History</a>
    <a href="submit_feedback.php">Feedback</a>
    <a href="about_us.php">About Us</a>
    <a href="announcements.php">Announcements</a>
    <div class="welcome-container">
        <div class="welcome">
            <span id="user-name" style="cursor: pointer;">
                Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div id="reset-password">
                <a href="reset_password.php">Reset Password</a>
            </div>
        </div>
        <a href="logouts.php" class="log-out">Log Out</a>
    </div>
</div>


<body>
    <h1><?= htmlspecialchars($facility['name']) ?> Booking</h1>
    <div class="facility-details">
        <img src="<?= htmlspecialchars($facility['photo_url']) ?>" alt="<?= htmlspecialchars($facility['name']) ?>">
        <div class="facility-description">
            <h3>Benefits of using <?= htmlspecialchars($facility['name']) ?></h3>
            <ul class="benefits-list">
                <?php
                $benefits = explode("\n", $facility['benefits']);
                foreach ($benefits as $benefit) {
                    echo '<li><span>' . htmlspecialchars($benefit) . '</span></li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <?php if (strtolower($facility['name']) != 'gym') : ?>
    <div class="calendar-header">
        <div class="month-nav"><a href="#" onclick="navigateMonth('<?= $prevMonth ?>')">Previous Month</a></div>
        <div><strong><?= date('F Y', strtotime($currentMonth)) ?></strong></div>
        <div class="month-nav"><a href="#" onclick="navigateMonth('<?= $nextMonth ?>')">Next Month</a></div>
    </div>

    <div class="week-days">
        <div>Monday</div>
        <div>Tuesday</div>
        <div>Wednesday</div>
        <div>Thursday</div>
        <div>Friday</div>
        <div>Saturday</div>
        <div>Sunday</div>
    </div>

    <div class="calendar" id="calendar">
        <?php
    $stmt = $conn->prepare("
        SELECT booking_date, booking_time, booking_duration, status
        FROM booking
        WHERE facility_id = :facility_id AND booking_date >= :current_date
    ");
    $stmt->bindParam(':facility_id', $facility['id']);
    $stmt->bindParam(':current_date', $currentMonth);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedSlots = [];
    foreach ($bookings as $booking) {
        $bookedSlots[$booking['booking_date']][] = [
            'time' => $booking['booking_time'],
            'duration' => $booking['booking_duration'],
            'status' => $booking['status']
        ];
    }

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = date('Y-m-d', strtotime("$currentMonth-" . sprintf('%02d', $day)));
        $isToday = $date == $currentDay ? 'today' : '';

        // Adjust day of the week
        $dayOfWeek = date('N', strtotime($date)); // 1 (for Monday) through 7 (for Sunday)

        if ($day == 1) {
            // Start the week on Monday
            for ($blank = 1; $blank < $firstDayOfMonth; $blank++) {
                echo '<div class="calendar-day unavailable"></div>';
            }
        }

        if ($date < $currentDay) {
            $dayClass = 'unavailable';
        } elseif (isset($bookedSlots[$date])) {
            $dayClass = 'available';
            foreach ($bookedSlots[$date] as $booking) {
                if ($booking['status'] != 'cancelled') {
                    $dayClass = 'booked';
                    break;
                }
            }
        } else {
            $dayClass = 'available';
        }

        echo '<div class="calendar-day ' . $dayClass . '">';
        echo '<div class="day-number">' . $day . '</div>';
        if (isset($bookedSlots[$date])) {
            echo '<div class="booking-time">';
            foreach ($bookedSlots[$date] as $booking) {
                if ($booking['status'] != 'cancelled') {
                    echo '<span>' . date('H:i', strtotime($booking['time'])) . ' (' . $booking['duration'] . ' hours duration)</span>';
                }
            }
            echo '</div>';
        } else {
            echo '<div class="booking-time"><span>No bookings</span></div>';
        }
        echo '</div>';

        if ($dayOfWeek == 7) {
            echo '</div><div class="calendar">';
        }
    }
    ?>
    </div>

    <?php endif; ?>

    <div id="booking-forms">
        <h2>Book a Facility</h2>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>
        <form method="post" action="" oninput="updateFees()">
            <input type="hidden" name="facility_id" value="<?= $facility['id'] ?>">
            <input type="hidden" name="user_type" value="<?= $_SESSION['user_type'] ?>">
            <label for="full_name">Full Name:</label>
            <input type="text" id="full_name" name="full_name" required
                value="<?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : ''; ?>" readonly>
            <label for="phone_number">Phone Number:</label>
            <input type="text" id="phone_number" name="phone_number" required
                value="<?= isset($_SESSION['phone_number']) ? htmlspecialchars($_SESSION['phone_number']) : ''; ?>">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required
                value="<?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
            <div id="block_field">
                <label for="block">Block:</label>
                <input type="text" id="block" name="block"
                    value="<?= isset($_SESSION['block']) ? htmlspecialchars($_SESSION['block']) : ''; ?>" readonly>
            </div>
            <label for="booking_date">Booking Date:</label>
            <input type="date" id="booking_date" name="booking_date" required min="<?= date('Y-m-d'); ?>">

            <label for="booking_time">Booking Time:</label>
            <input type="text" id="booking_time" name="booking_time" required>



            <label for="booking_duration">Booking Duration (hours):</label>
            <input type="number" id="booking_duration" name="booking_duration" min="1" max="3" required>
            <div id="fee_display">Total Fee: RM 0</div>
            <input type="submit" value="Book">
        </form>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved.</p>
    </footer>

    <div id="myModal" class="modal">
        <div class="modal-content">
            <p id="modalMessage"></p>
            <button id="modalOkButton">OK</button>
        </div>
    </div>
    <script>
    flatpickr("#booking_time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 30,
        minTime: "10:00",
        maxTime: "20:30",
        disable: [
            function(date) {
                // Disable all times that are not in allowedTimes
                const hour = date.getHours();
                const minute = date.getMinutes();
                return !(hour >= 10 && hour <= 20 && (minute === 0 || minute === 30));
            }
        ]
    });
    </script>
    <script>
    function showBookingForms() {
        document.getElementById('booking-forms').classList.remove('hidden');
        document.getElementById('booking-forms').scrollIntoView({
            behavior: 'smooth'
        });
    }

    function updateFees() {
        const bookingDuration = document.getElementById('booking_duration').value;
        let baseFee = 0;

        <?php if ($_SESSION['user_type'] === 'resident'): ?>
        baseFee = <?= $facility['resident_price'] ?>;
        <?php else: ?>
        baseFee = <?= $facility['nonresident_price'] ?>;
        <?php endif; ?>

        const totalFee = baseFee * bookingDuration;
        document.getElementById('fee_display').innerText = `Total Fee: RM ${totalFee}`;
    }

    function toggleBlockField() {
        const userType = '<?php echo $_SESSION['user_type']; ?>';
        const blockField = document.getElementById('block_field');
        if (userType === "resident") {
            blockField.style.display = 'block';
        } else {
            blockField.style.display = 'none';
        }
    }

    function navigateMonth(month) {
        const url = new URL(window.location.href);
        url.searchParams.set('month', month);
        fetch(url)
            .then(response => response.text())
            .then(html => {
                document.querySelector('body').innerHTML = html;
                document.getElementById('calendar').scrollIntoView({
                    behavior: 'smooth'
                });
            });
    }

    window.onload = function() {
        toggleBlockField();

        <?php if (isset($_SESSION['booking_error'])): ?>
        showModal("<?= $_SESSION['booking_error'] ?>");
        <?php unset($_SESSION['booking_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['booking_time_error'])): ?>
        showModal("<?= $_SESSION['booking_time_error'] ?>");
        <?php unset($_SESSION['booking_time_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['booking_success'])): ?>
        showModal("<?= $_SESSION['booking_success'] ?>", true);
        <?php unset($_SESSION['booking_success']); ?>
        <?php endif; ?>
    }

    function showModal(message, redirect = false) {
        var modal = document.getElementById("myModal");
        var modalMessage = document.getElementById("modalMessage");
        modalMessage.innerText = message;
        modal.style.display = "block";

        var okButton = document.getElementById("modalOkButton");
        okButton.onclick = function() {
            closeModal();
            if (redirect) {
                window.location.href = 'payment.php';
            }
        };
    }

    function closeModal() {
        var modal = document.getElementById("myModal");
        modal.style.display = "none";
    }
    </script>
</body>

</html>