<?php
require 'db_connection.php';
session_start();

// Set timezone to Malaysia time
date_default_timezone_set('Asia/Kuala_Lumpur');

$loginSuccess = false;
$announcementSuccess = false;
$deleteSuccess = false;
$facilityStatusChangeSuccess = false;
$loginError = '';
$message = '';

// Fetch the full name of the logged-in user
function getUserFullName($conn, $user_id) {
    $stmt = $conn->prepare("SELECT full_name FROM Admin_Manager WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['full_name'] ?? '';
}

// Fetch the latest 5 booking records
function getLatestBookings($conn, $limit = 5) {
    $stmt = $conn->prepare("SELECT 
                                b.id, 
                                r.full_name AS resident_name, 
                                f.name AS facility_name, 
                                f.photo_url AS facility_photo, 
                                b.booking_date, 
                                b.booking_time 
                            FROM booking b
                            INNER JOIN register r ON b.register_id = r.id
                            INNER JOIN Facility f ON b.facility_id = f.id
                            ORDER BY b.booking_date DESC, b.booking_time DESC
                            LIMIT :limit");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function writeToLogFile($action) {
    $logFileName = "logs.txt";
    $currentTime = date("Y-m-d H:i:s") . " - " . $action . PHP_EOL;

    $logFile = fopen($logFileName, "a") or die("Unable to open log file!");
    fwrite($logFile, $currentTime);
    fclose($logFile);
}

function displayLogs($user_role, $user_name = null) {
    $logFileName = "logs.txt";
    if (!file_exists($logFileName)) {
        echo "No logs available.";
        return;
    }

    $logs = file($logFileName, FILE_IGNORE_NEW_LINES);
    foreach ($logs as $log) {
        if ($user_role === 'Super Admin' || ($user_role === 'Admin_Manager' && strpos($log, $user_name) !== false)) {
            echo $log . "<br>";
        }
    }
}

function viewBookingHistory($conn, $searchDate = '', $searchTime = '') {
    $sql = "SELECT r.full_name as resident_name, f.name as facility_name, b.booking_date as date, b.booking_time as time, b.booking_duration as duration, b.status as status 
            FROM booking b
            LEFT JOIN register r ON b.register_id = r.id
            LEFT JOIN Facility f ON b.facility_id = f.id
            WHERE r.type = 'resident'";

    // Add conditions
    $conditions = [];
    if (!empty($searchDate)) {
        $conditions[] = "b.booking_date = :search_date";
    }
    if (!empty($searchTime)) {
        $conditions[] = "b.booking_time = :search_time";
    }

    // Append conditions to the SQL query
    if (!empty($conditions)) {
        $sql .= " AND " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY b.id ASC"; // Order by booking_id in ascending order

    $stmt = $conn->prepare($sql);

    if (!empty($searchDate)) {
        $stmt->bindParam(':search_date', $searchDate);
    }
    if (!empty($searchTime)) {
        $stmt->bindParam(':search_time', $searchTime);
    }

    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bookings)) {
        echo "<tr><td colspan='6'>No Resident booking record.</td></tr>";
    } else {
        foreach ($bookings as $booking) {
            echo "<tr><td>" . $booking['resident_name'] . "</td><td>" . $booking['facility_name'] . "</td><td>" . $booking['date'] . "</td><td>" . $booking['time'] . "</td><td>" . $booking['duration'] . "</td><td>" . $booking['status'] . "</td></tr>";
        }
    }
}

function viewExternalRental($conn, $searchDate = '', $searchTime = '') {
    $sql = "SELECT r.full_name, f.name as facility_name, b.booking_date as rental_date, b.booking_time as rental_time, b.booking_duration as duration, b.status as status 
            FROM booking b
            LEFT JOIN register r ON b.register_id = r.id
            LEFT JOIN Facility f ON b.facility_id = f.id
            WHERE r.type = 'nonresident'";

    // Add conditions
    $conditions = [];
    if (!empty($searchDate)) {
        $conditions[] = "b.booking_date = :search_date";
    }
    if (!empty($searchTime)) {
        $conditions[] = "b.booking_time = :search_time";
    }

    // Append conditions to the SQL query
    if (!empty($conditions)) {
        $sql .= " AND " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY b.id ASC"; // Order by booking_id in ascending order

    $stmt = $conn->prepare($sql);

    if (!empty($searchDate)) {
        $stmt->bindParam(':search_date', $searchDate);
    }
    if (!empty($searchTime)) {
        $stmt->bindParam(':search_time', $searchTime);
    }

    $stmt->execute();
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rentals)) {
        echo "<tr><td colspan='6'>No Nonresident booking record.</td></tr>";
    } else {
        foreach ($rentals as $rental) {
            echo "<tr><td>" . $rental['full_name'] . "</td><td>" . $rental['facility_name'] . "</td><td>" . $rental['rental_date'] . "</td><td>" . $rental['rental_time'] . "</td><td>" . $rental['duration'] . "</td><td>" . $rental['status'] . "</td></tr>";
        }
    }
}

function sendAnnouncement($conn) {
    global $announcementSuccess, $message; 
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_announcement'])) {
        $title = $_POST['announcement_title'];
        $message = $_POST['announcement'];
        $currentDate = date("Y-m-d H:i:s"); // Store the date in a variable

        if (!isset($_SESSION["user_id"]) || $_SESSION["user_id"] === null) {
            echo "<div class='alert alert-danger'>User ID is not set. Please log in again.</div>";
            return;
        }

        $stmt = $conn->prepare("SELECT id FROM Admin_Manager WHERE id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION["user_id"]);
        $stmt->execute();
        $adminManager = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminManager) {
            echo "<div class='alert alert-danger'>Invalid user ID. Please log in again.</div>";
            return;
        }

        $sql = "INSERT INTO Announcements (title, message, date, sent_by_Admin_manager) VALUES (:title, :message, :date, :sent_by)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':date', $currentDate); // Use the variable here
        $stmt->bindParam(':sent_by', $_SESSION["user_id"]);

        if ($stmt->execute()) {
            $announcementSuccess = true;
            $message = "Announcement sent successfully.";
            writeToLogFile("Announcement sent: $title");
        } else {
            echo "<div class='alert alert-danger'>Failed to send announcement.</div>";
        }
    }
}

function deleteAnnouncement($conn) {
    global $deleteSuccess, $message; 
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_announcement'])) {
        $title = $_POST['announcement_title'];

        $sql = "DELETE FROM Announcements WHERE title = :title";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $title);

        if ($stmt->execute()) {
            $deleteSuccess = true;
            $message = "Announcement deleted successfully.";
            writeToLogFile("Announcement deleted: $title");
        } else {
            echo "<div class='alert alert-danger'>Failed to delete announcement.</div>";
        }
    }
}

function manageFacilities($conn) {
    global $facilityStatusChangeSuccess, $message;
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['change_facility_status'])) {
            $facility_name = $_POST['facility_name'];
            $status = $_POST['status'];
            
            // 获取会话中的管理员ID
            if (isset($_SESSION["user_id"])) {
                $admin_manager_id = $_SESSION["user_id"];

                // 检查 admin_manager_id 是否有效
                $stmt = $conn->prepare("SELECT id FROM Admin_Manager WHERE id = :admin_manager_id");
                $stmt->bindParam(':admin_manager_id', $admin_manager_id);
                $stmt->execute();
                $adminManager = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($adminManager) {
                    // Update facility status and set status_updated_by_Admin_manager
                    $stmt = $conn->prepare("UPDATE Facility SET status = :status, status_updated_by_Admin_manager = :updated_by WHERE name = :facility_name");
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':updated_by', $admin_manager_id);
                    $stmt->bindParam(':facility_name', $facility_name);

                    if ($stmt->execute()) {
                        $facilityStatusChangeSuccess = true;
                        $message = "Facility status changed successfully.";
                        writeToLogFile("Facility status changed: $facility_name to $status by Admin Manager ID: $admin_manager_id");
                    } else {
                        echo "<div class='alert alert-danger'>Failed to change facility status.</div>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>Invalid Admin Manager ID. Please log in again.</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>User ID is not set. Please log in again.</div>";
            }
        }
    }
}

function getTotalRegistered($conn) {
    $sqlResident = "SELECT COUNT(*) as total_residents FROM register WHERE type = 'resident'";
    $stmtResident = $conn->prepare($sqlResident);
    $stmtResident->execute();
    $totalResidents = $stmtResident->fetch(PDO::FETCH_ASSOC)['total_residents'];

    $sqlNonresident = "SELECT COUNT(*) as total_nonresidents FROM register WHERE type = 'nonresident'";
    $stmtNonresident = $conn->prepare($sqlNonresident);
    $stmtNonresident->execute();
    $totalNonresidents = $stmtNonresident->fetch(PDO::FETCH_ASSOC)['total_nonresidents'];

    return [$totalResidents, $totalNonresidents];
}

$activeTab = '';
$searchDate = '';
$searchTime = '';
list($totalResidents, $totalNonresidents) = getTotalRegistered($conn);

// Fetch the full name of the logged-in user
$fullName = isset($_SESSION["user_id"]) ? getUserFullName($conn, $_SESSION["user_id"]) : '';
$latestBookings = getLatestBookings($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $user_name = $_POST['user_name'];
        $user_password = $_POST['user_password'];

        if (!empty($user_name) && !empty($user_password)) {
            $stmt = $conn->prepare("SELECT * FROM Admin_Manager WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user_password === $user['user_password']) {
                $_SESSION["user_name"] = $user_name;
                $_SESSION["user_role"] = $user['user_role'];
                $_SESSION["user_id"] = $user['id']; // Store user ID in session
                $loginSuccess = true;
                writeToLogFile("Admin Manager Login: " . $user_name);
                $self = $_SERVER['PHP_SELF'];
                header("Location: $self");
                exit;
            } else {
                $loginError = "Invalid username or password.";
            }
        } else {
            $loginError = "Please fill in both fields.";
        }
    } elseif (isset($_POST['logout'])) {
        $logoutDateTime = date("Y-m-d H:i:s");
        writeToLogFile("Admin Manager Logout: " . $_SESSION["user_name"] . " at " . $logoutDateTime);

        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        setcookie("user", "", time() - 3600);

        header('Location: loginpage_super_and_manager.php');
        exit;
    
    } elseif (isset($_POST['send_announcement'])) {
        sendAnnouncement($conn);
    } elseif (isset($_POST['delete_announcement'])) {
        deleteAnnouncement($conn);
    } elseif (isset($_POST['search_resident_btn'])) {
        $activeTab = 'viewBookingHistory';
        $searchDate = $_POST['search_date'];
        $searchTime = $_POST['search_time'];
    } elseif (isset($_POST['view_all_resident_btn'])) {
        $activeTab = 'viewBookingHistory';
    } elseif (isset($_POST['search_nonresident_btn'])) {
        $activeTab = 'viewExternalRental';
        $searchDate = $_POST['search_date'];
        $searchTime = $_POST['search_time'];
    } elseif (isset($_POST['view_all_nonresident_btn'])) {
        $activeTab = 'viewExternalRental';
    } elseif (isset($_POST['change_facility_status'])) {
        manageFacilities($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Apartment Manager Dashboard</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background: #f8f9fa;
        margin: 0;
        padding: 0;
    }

    .container {
        margin-top: 30px;
        margin-left: 200px;
        padding: 10px;
    }

    .function {
        margin-bottom: 20px;
        display: none;
    }

    .function.active {
        display: block;
    }

    .table-container {
        margin-bottom: 20px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    table,
    th,
    td {
        border: 1px solid #ccc;
    }

    th,
    td {
        text-align: left;
        padding: 6px;
    }

    th {
        background-color: #333;
        color: white;
    }

    h2,
    h3 {
        color: #333;
        margin-bottom: 15px;
        font-size: 18px;
    }

    .btn-custom {
        background-color: #343a40;
        color: white;
        padding: 5px 10px;
        font-size: 12px;
    }

    .announcement-textarea {
        width: 100%;
        height: 60px;
    }

    .sidebar {
        height: 100%;
        width: 180px;
        position: fixed;
        top: 0;
        left: 0;
        background-color: #e9ecf2;
        padding-top: 10px;
        overflow-y: auto;
    }

    .sidebar a {
        padding: 8px 12px;
        text-decoration: none;
        font-size: 16px;
        color: black;
        display: block;
        transition: all 0.3s ease;
    }

    .sidebar a:hover {
        background-color: #e0f7fa;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 15px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        width: 300px;
    }

    .modal.show {
        display: block;
    }

    .alert-center {
        text-align: center;
        margin: 0;
        padding: 5px;
        font-size: 14px;
    }

    .alert {
        transition: opacity 0.5s ease-out;
        padding: 5px;
    }

    .form-container {
        background: white;
        padding: 15px;
        border-radius: 6px;
        margin-top: 15px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    input[type="text"],
    input[type="password"],
    input[type="email"],
    input[type="date"],
    input[type="file"],
    input[type="number"],
    textarea,
    select {
        width: 90%;
        padding: 8px;
        margin: 5px 0;
        display: inline-block;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    textarea {
        height: 80px;
    }

    input[type="submit"],
    button {
        width: auto;
        background-color: #e57373;
        color: white;
        padding: 8px 15px;
        margin: 8px 0;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    input[type="submit"]:hover,
    button:hover {
        background-color: DodgerBlue;
    }

    .log-container {
        background: #f8f9fa;
        border: 1px solid #ccc;
        padding: 10px;
        margin-top: 15px;
        overflow-y: auto;
        height: 300px;
        font-size: 12px;
    }

    @media screen and (max-width: 768px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }

        .container {
            margin-left: 0;
        }

        .form-inline .form-group {
            display: block;
            width: 100%;
        }

        .form-inline button {
            width: 100%;
        }
    }

    .facility-photo {
        max-width: 100px;
        /* Adjust the width as needed */
        height: auto;
        /* Maintain aspect ratio */
        display: block;
        margin: 0 auto;
        /* Center the image in the cell */
    }
    </style>
    <script>
    function showFunction(id) {
        var functions = document.getElementsByClassName('function');
        for (var i = 0; i < functions.length; i++) {
            functions[i].classList.remove('active');
        }
        document.getElementById(id).classList.add('active');
    }

    function showSuccessModal() {
        var modal = document.getElementById('successModal');
        modal.classList.add('show');
        setTimeout(function() {
            modal.classList.remove('show');
        }, 2000);
    }

    function showDeleteModal() {
        var modal = document.getElementById('deleteModal');
        modal.classList.add('show');
        setTimeout(function() {
            modal.classList.remove('show');
        }, 2000);
    }

    function showFacilityStatusChangeModal() {
        var modal = document.getElementById('facilityStatusChangeModal');
        modal.classList.add('show');
        setTimeout(function() {
            modal.classList.remove('show');
        }, 2000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var activeTab = '<?php echo $activeTab; ?>';
        if (activeTab) {
            showFunction(activeTab);
        }

        <?php if ($facilityStatusChangeSuccess): ?>
        showFacilityStatusChangeModal();
        <?php endif; ?>
        <?php if ($announcementSuccess): ?>
        showSuccessModal();
        <?php endif; ?>
        <?php if ($deleteSuccess): ?>
        showDeleteModal();
        <?php endif; ?>
    });
    </script>
</head>

<body>
    <div class="modal" id="successModal">
        <div class="alert alert-success">Announcement sent successfully.</div>
    </div>
    <div class="modal" id="deleteModal">
        <div class="alert alert-success">Announcement deleted successfully.</div>
    </div>
    <div class="modal" id="facilityStatusChangeModal">
        <div class="alert alert-success">Facility status changed successfully.</div>
    </div>
    <div class="sidebar">
        <a href="javascript:void(0)" onclick="showFunction('dashboard')">Dashboard</a>
        <a href="javascript:void(0)" onclick="showFunction('viewBookingHistory')">View Resident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('viewExternalRental')">View Nonresident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('sendAnnouncement')">Send Announcement</a>
        <a href="javascript:void(0)" onclick="showFunction('deleteAnnouncement')">Delete Announcement</a>
        <a href="javascript:void(0)" onclick="showFunction('readFeedback')">Read Resident Feedback</a>
        <a href="javascript:void(0)" onclick="showFunction('manageFacilities')">Manage Facilities</a>
        <a>Total Residents Registered: <?php echo $totalResidents; ?></a>
        <a>Total Nonresidents Registered: <?php echo $totalNonresidents; ?></a>
        <form method="post" action="">
            <button type="submit" name="logout" class="btn btn-danger w-100 mt-4">Logout</button>
        </form>
    </div>
    <div class="container">
        <!-- Dashboard Content -->
        <div id="dashboard" class="function">
            <h2>Apartment Manager Dashboard</h2>

            <!-- Welcome Message -->
            <?php if ($fullName): ?>
            <h3>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h3>
            <?php endif; ?>

            <!-- Latest Booking Records -->
            <h4>Latest 5 Booking Records</h4>
            <div class="table-container">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Resident Name</th>
                            <th>Facility Name</th>
                            <th>Booking Date</th>
                            <th>Booking Time</th>
                            <th>Facility Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($latestBookings)): ?>
                        <?php foreach ($latestBookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['id']); ?></td>
                            <td><?php echo htmlspecialchars($booking['resident_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                            <td><?php echo htmlspecialchars($booking['booking_time']); ?></td>
                            <td><img src="<?php echo htmlspecialchars($booking['facility_photo']); ?>"
                                    alt="Facility Photo" class="facility-photo"></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6">No booking records found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!isset($_SESSION["user_name"])): ?>
        <div id="dashboard" class="function active">
            <h3>Login</h3>
            <?php if ($loginError): ?>
            <div class="alert alert-danger alert-center"><?php echo $loginError; ?></div>
            <?php endif; ?>
            <form method="post" action="" class="form-inline">
                <div class="form-group mb-2">
                    <label for="user_name" class="sr-only">User Name</label>
                    <input type="text" class="form-control" id="user_name" name="user_name" placeholder="User Name"
                        required>
                </div>
                <div class="form-group mx-sm-3 mb-2">
                    <label for="user_password" class="sr-only">Password</label>
                    <input type="password" class="form-control" id="user_password" name="user_password"
                        placeholder="Password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary mb-2">Login</button>
            </form>
        </div>
        <?php else: ?>
        <div id="viewBookingHistory" class="function<?php if ($activeTab === 'viewBookingHistory') echo ' active'; ?>">
            <h3>View Resident Booking History</h3>
            <form method="post" action="#viewBookingHistory" class="form-inline mb-3">
                <div class="form-group mx-sm-3 mb-2">
                    <input type="date" class="form-control" name="search_date" placeholder="Search Date">
                </div>
                <div class="form-group mx-sm-3 mb-2">
                    <input type="time" class="form-control" name="search_time" placeholder="Search Time">
                </div>
                <button type="submit" name="search_resident_btn" class="btn btn-primary mb-2">Search</button>
                <button type="submit" name="view_all_resident_btn" class="btn btn-secondary mb-2 ml-2">View All</button>
            </form>
            <div class="table-container">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Resident Name</th>
                            <th>Facility Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            if ($activeTab === 'viewBookingHistory') {
                                if (isset($searchDate) || isset($searchTime)) {
                                    viewBookingHistory($conn, $searchDate, $searchTime); 
                                } else {
                                    viewBookingHistory($conn); 
                                }
                            }
                            ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="viewExternalRental" class="function<?php if ($activeTab === 'viewExternalRental') echo ' active'; ?>">
            <h3>View Nonresident Booking History</h3>
            <form method="post" action="#viewExternalRental" class="form-inline mb-3">
                <div class="form-group mx-sm-3 mb-2">
                    <input type="date" class="form-control" name="search_date" placeholder="Search Date">
                </div>
                <div class="form-group mx-sm-3 mb-2">
                    <input type="time" class="form-control" name="search_time" placeholder="Search Time">
                </div>
                <button type="submit" name="search_nonresident_btn" class="btn btn-primary mb-2">Search</button>
                <button type="submit" name="view_all_nonresident_btn" class="btn btn-secondary mb-2 ml-2">View
                    All</button>
            </form>
            <div class="table-container">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Facility Name</th>
                            <th>Rental Date</th>
                            <th>Rental Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            if ($activeTab === 'viewExternalRental') {
                                if (isset($searchDate) || isset($searchTime)) {
                                    viewExternalRental($conn, $searchDate, $searchTime); 
                                } else {
                                    viewExternalRental($conn); 
                                }
                            }
                            ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="sendAnnouncement" class="function">
            <h3>Send Announcement</h3>
            <form method="post" action="">
                <div class="form-container">
                    <div class="form-group">
                        <label for="announcement_title">Announcement Title</label>
                        <input type="text" class="form-control" id="announcement_title" name="announcement_title"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="announcement">Announcement Message</label>
                        <textarea class="form-control announcement-textarea" id="announcement" name="announcement"
                            required></textarea>
                    </div>
                    <button type="submit" name="send_announcement" class="btn btn-primary">Send Announcement</button>
                </div>
            </form>
        </div>

        <div id="deleteAnnouncement" class="function">
            <h3>Delete Announcement</h3>
            <form method="post" action="">
                <div class="form-container">
                    <div class="form-group">
                        <label for="announcement_title">Announcement Title</label>
                        <input type="text" class="form-control" id="announcement_title" name="announcement_title"
                            required>
                    </div>
                    <button type="submit" name="delete_announcement" class="btn btn-danger">Delete Announcement</button>
                </div>
            </form>
        </div>

        <div id="readFeedback" class="function">
            <h3>Read Feedback</h3>
            <div class="table-container">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Phone Number</th>
                            <th>Email</th>
                            <th>Description</th>
                            <th>Photo</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $sql = "SELECT * FROM Feedback";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($feedbacks)) {
                                echo "<tr><td colspan='7'>No feedback available.</td></tr>";
                            } else {
                                foreach ($feedbacks as $feedback) {
                                    echo "<tr>
                                        <td>" . $feedback['full_name'] . "</td>
                                        <td>" . $feedback['phone_number'] . "</td>
                                        <td>" . $feedback['email'] . "</td>
                                        <td>" . $feedback['description'] . "</td>
                                        <td><img src='" . $feedback['photo_url'] . "' alt='Photo' style='width:100px;'></td>
                                        <td>" . $feedback['date'] . "</td>
                                    </tr>";
                                }
                            }
                            ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="manageFacilities" class="function">
            <h3>Manage Facilities</h3>
            <form method="post" action="">
                <div class="form-container">
                    <div class="form-group">
                        <label for="facility_name">Facility Name</label>
                        <input type="text" class="form-control" id="facility_name" name="facility_name" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <button type="submit" name="change_facility_status" class="btn btn-primary">Change Status</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($deleteSuccess): ?>
    <script>
    showDeleteModal();
    </script>
    <?php endif; ?>

</body>

</html>