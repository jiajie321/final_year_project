<?php
require 'db_connection.php';
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");
// Log writing function
function writeToLogFile($action) {
    $logFileName = "logs.txt";
    $currentTime = date("Y-m-d H:i:s") . " - " . $action . PHP_EOL;

    $logFile = fopen($logFileName, "a") or die("Unable to open log file!");
    fwrite($logFile, $currentTime);
    fclose($logFile);
}

// Display logs
function displayLogs($user_role, $user_name = null) {
    $logFileName = "logs.txt";
    if (!file_exists($logFileName)) {
        echo "<tr><td colspan='2'>No logs available.</td></tr>";
        return;
    }

    $logs = file($logFileName, FILE_IGNORE_NEW_LINES);
    foreach ($logs as $log) {
        if ($user_role === 'Super Admin' || ($user_role === 'Apartment Manager' && strpos($log, $user_name) !== false)) {
            $parts = explode(" - ", $log, 2);
            $timestamp = $parts[0];
            $action = $parts[1] ?? '';

            echo "<tr><td>{$timestamp}</td><td>{$action}</td></tr>";
        }
    }
}

// Add Super Admin
function addSuperAdmin($conn, $full_name, $phone_number, $email, $user_name, $user_password) {
    try {
        $stmt = $conn->prepare("INSERT INTO Super_Admin (full_name, phone_number, email, user_name, user_password) 
                                VALUES (:full_name, :phone_number, :email, :user_name, :user_password)");
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':user_password', $user_password);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success text-center' role='alert'>Super Admin added successfully!</div>";
        } else {
            echo "<div class='alert alert-danger text-center' role='alert'>Failed to add Super Admin.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger text-center' role='alert'>Error: " . $e->getMessage() . "</div>";
    }
}

// Add Apartment Manager
function addApartmentManager($conn, $full_name, $joining_date, $phone_number, $email, $user_name, $user_password, $super_admin_id) {
    try {
        $stmt = $conn->prepare("INSERT INTO Admin_Manager (full_name, joining_date, phone_number, email, user_name, user_password, added_by_Super_admin) 
                                VALUES (:full_name, :joining_date, :phone_number, :email, :user_name, :user_password, :added_by_Super_admin)");
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':joining_date', $joining_date);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':user_password', $user_password);
        $stmt->bindParam(':added_by_Super_admin', $super_admin_id);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success text-center' role='alert'>Apartment Manager added successfully.</div>";
        } else {
            echo "<div class='alert alert-danger text-center' role='alert'>Failed to add Apartment Manager.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger text-center' role='alert'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch user full name
function getUserFullName($conn, $user_id, $user_role) {
    if ($user_role === 'Super Admin') {
        $stmt = $conn->prepare("SELECT full_name FROM Super_Admin WHERE id = :user_id");
    } else {
        $stmt = $conn->prepare("SELECT full_name FROM Admin_Manager WHERE id = :user_id");
    }
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
                            ORDER BY b.id DESC
                            LIMIT :limit");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Fetch the total registered count
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

// View booking history
function viewBookingHistory($conn, $searchDate = '', $searchTime = '', $residentType = 'resident') {
    $query = "SELECT 
                b.id, 
                r.full_name AS name, 
                f.name AS facility_name, 
                b.booking_date, 
                b.booking_time, 
                b.booking_duration, 
                b.status, 
                p.amount, 
                p.payment_method 
              FROM booking b
              INNER JOIN register r ON b.register_id = r.id
              INNER JOIN Facility f ON b.facility_id = f.id
              LEFT JOIN payment p ON b.id = p.booking_id
              WHERE r.type = :residentType";
    
    $conditions = [];
    
    if (!empty($searchDate)) {
        $conditions[] = "b.booking_date = :searchDate";
    }
    
    if (!empty($searchTime)) {
        $conditions[] = "b.booking_time = :searchTime";
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(' AND ', $conditions);
    }
    
    $query .= " ORDER BY b.id ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':residentType', $residentType);
    
    if (!empty($searchDate)) {
        $stmt->bindParam(':searchDate', $searchDate);
    }
    
    if (!empty($searchTime)) {
        $stmt->bindParam(':searchTime', $searchTime);
    }
    
    $stmt->execute();
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($bookings)) {
        echo "<tr><td colspan='9'>No booking record found.</td></tr>";
    } else {
        foreach ($bookings as $booking) {
            echo "<tr>
                    <td>{$booking['id']}</td>
                    <td>{$booking['name']}</td>
                    <td>{$booking['facility_name']}</td>
                    <td>{$booking['booking_date']}</td>
                    <td>{$booking['booking_time']}</td>
                    <td>{$booking['booking_duration']}</td>
                    <td>{$booking['status']}</td>
                    <td>RM {$booking['amount']}</td>
                    <td>{$booking['payment_method']}</td>
                  </tr>";
        }
    }
}

// Send announcement
function sendAnnouncement($conn) {
    global $announcementSuccess, $message; 
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_announcement'])) {
        $title = $_POST['announcement_title'];
        $message = $_POST['announcement'];
        $currentDate = date("Y-m-d H:i:s");

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
        $stmt->bindParam(':date', $currentDate);
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

// Delete announcement
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

// Manage facilities
function manageFacilities($conn) {
    global $facilityStatusChangeSuccess, $message;
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['change_facility_status'])) {
            $facility_name = $_POST['facility_name'];
            $status = $_POST['status'];
            
            if (isset($_SESSION["user_id"])) {
                $admin_manager_id = $_SESSION["user_id"];

                $stmt = $conn->prepare("SELECT id FROM Admin_Manager WHERE id = :admin_manager_id");
                $stmt->bindParam(':admin_manager_id', $admin_manager_id);
                $stmt->execute();
                $adminManager = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($adminManager) {
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

// View external rental booking history
function viewExternalRental($conn, $searchDate = '', $searchTime = '') {
    $query = "SELECT 
                b.id, 
                r.full_name AS name, 
                f.name AS facility_name, 
                b.booking_date, 
                b.booking_time, 
                b.booking_duration, 
                b.status, 
                p.amount, 
                p.payment_method 
              FROM booking b
              INNER JOIN register r ON b.register_id = r.id
              INNER JOIN Facility f ON b.facility_id = f.id
              LEFT JOIN payment p ON b.id = p.booking_id
              WHERE r.type = 'nonresident'";
    
    $conditions = [];
    
    if (!empty($searchDate)) {
        $conditions[] = "b.booking_date = :searchDate";
    }
    
    if (!empty($searchTime)) {
        $conditions[] = "b.booking_time = :searchTime";
    }
    
    if (!empty($conditions)) {
        $query .= " AND " . implode(' AND ', $conditions);
    }
    
    $query .= " ORDER BY b.id ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($searchDate)) {
        $stmt->bindParam(':searchDate', $searchDate);
    }
    
    if (!empty($searchTime)) {
        $stmt->bindParam(':searchTime', $searchTime);
    }
    
    $stmt->execute();
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($bookings)) {
        echo "<tr><td colspan='9'>No booking record found.</td></tr>";
    } else {
        foreach ($bookings as $booking) {
            echo "<tr>
                    <td>{$booking['id']}</td>
                    <td>{$booking['name']}</td>
                    <td>{$booking['facility_name']}</td>
                    <td>{$booking['booking_date']}</td>
                    <td>{$booking['booking_time']}</td>
                    <td>{$booking['booking_duration']}</td>
                    <td>{$booking['status']}</td>
                    <td>RM {$booking['amount']}</td>
                    <td>{$booking['payment_method']}</td>
                  </tr>";
        }
    }
}

// Read feedback
function readFeedback($conn) {
    $stmt = $conn->prepare("SELECT * FROM Feedback");
    $stmt->execute();
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($feedbacks)) {
        echo "<tr><td colspan='6'>No feedback available.</td></tr>";
    } else {
        foreach ($feedbacks as $feedback) {
            echo "<tr>
                    <td>{$feedback['full_name']}</td>
                    <td>{$feedback['phone_number']}</td>
                    <td>{$feedback['email']}</td>
                    <td>{$feedback['description']}</td>
                    <td><img src='{$feedback['photo_url']}' alt='Feedback Photo' style='width:100px;'></td>
                    <td>{$feedback['date']}</td>
                  </tr>";
        }
    }
}

// View sales analysis
function viewSalesAnalysis($conn, $year) {
    $query = "SELECT 
                MONTH(booking_date) AS month, 
                SUM(CASE WHEN r.type = 'resident' THEN p.amount ELSE 0 END) AS resident_payments, 
                SUM(CASE WHEN r.type = 'nonresident' THEN p.amount ELSE 0 END) AS nonresident_payments 
              FROM booking b
              INNER JOIN register r ON b.register_id = r.id
              LEFT JOIN payment p ON b.id = p.booking_id
              WHERE YEAR(booking_date) = :year
              GROUP BY MONTH(booking_date)";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':year', $year, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo "<tr><td colspan='3'>No sales data available for the selected year.</td></tr>";
    } else {
        foreach ($results as $result) {
            echo "<tr>
                    <td>" . date('F', mktime(0, 0, 0, $result['month'], 10)) . "</td>
                    <td>RM {$result['resident_payments']}</td>
                    <td>RM {$result['nonresident_payments']}</td>
                  </tr>";
        }
    }
}

// Process page requests
$message = '';
$activeTab = '';
$searchDate = '';
$searchTime = '';
list($totalResidents, $totalNonresidents) = getTotalRegistered($conn);

if (isset($_SESSION["user_id"])) {
    $fullName = getUserFullName($conn, $_SESSION["user_id"], $_SESSION["user_role"]);
} else {
    $fullName = '';
}

$latestBookings = getLatestBookings($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $user_name = $_POST['user_name'];
        $user_password = $_POST['user_password'];

        if (!empty($user_name) && !empty($user_password)) {
            // First, check in Admin_Manager table
            $stmt = $conn->prepare("SELECT * FROM Admin_Manager WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // If user not found in Admin_Manager, check in Super_Admin
            if (!$user) {
                $stmt = $conn->prepare("SELECT * FROM Super_Admin WHERE user_name = :user_name");
                $stmt->bindParam(':user_name', $user_name);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $user['user_role'] = 'Super Admin'; // Manually set user role for Super Admin
                }
            } else {
                $user['user_role'] = 'Apartment Manager'; // Manually set user role for Apartment Manager
            }

            // Verify if user exists and password matches
            if ($user && $user_password === $user['user_password']) {
                $_SESSION["user_name"] = $user_name;
                $_SESSION["user_role"] = $user['user_role'];
                $_SESSION["user_id"] = $user['id'];
                writeToLogFile($user['user_role'] . " Login: " . $user_name);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = "Invalid username or password.";
            }
        } else {
            $message = "Please fill in both fields.";
        }
    } elseif (isset($_POST['logout'])) {
        if (isset($_SESSION['user_role']) && isset($_SESSION["user_name"])) {
            $logoutDateTime = date("Y-m-d H:i:s");
            writeToLogFile($_SESSION['user_role'] . " Logout: " . $_SESSION["user_name"] . " at " . $logoutDateTime);
        }

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

        header('Location: backend.php');
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
    } elseif (isset($_POST['search_sales_analysis_btn'])) {
        $activeTab = 'salesAnalysis';
        $selectedYear = $_POST['selected_year'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Backend Dashboard</title>
    <style>
    /* General reset and base styles */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body,
    html {
        font-family: 'Arial', sans-serif;
        background-color: #f4f4f4;
        color: #333;
        margin: 0;
        /* Removes default body margin */
        padding: 0;
    }

    /* Sidebar styling */
    .sidebar {
        width: 200px;
        position: fixed;
        height: 100%;
        background-color: #e9ecf2;
        padding: 20px 0;
        overflow-y: auto;
    }

    .sidebar a {
        padding: 10px 15px;
        text-decoration: none;
        font-size: 16px;
        color: black;
        display: block;
        transition: all 0.3s ease;
    }

    .sidebar a:hover {
        background-color: #e0f7fa;
    }

    /* Container for content */
    .container {
        margin-left: 7rem;
        /* Adjust margin to make space for sidebar */
        padding: 20px;
    }

    h1 {
        margin-left: 12rem;
        padding: 3rem;
    }

    /* Header styles */
    h2 {
        color: #333;
        margin-top: 0;
        /* Ensures no margin pushes the title down */
        font-size: 24px;
    }

    h3 {
        color: #333;
        font-size: 20px;
        margin-top: 20px;
        margin-bottom: 10px;
    }

    h4 {
        color: #333;
        font-size: 18px;
        margin-top: 15px;
        margin-bottom: 5px;
    }


    .loginform-container {
        background: #ffffff;
        padding: 30px;
        border-radius: 10px;
        margin-top: 5rem;
        width: 350px;
        margin-left: 20rem;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        text-align: center;

    }

    /* Heading style for better emphasis */
    .loginform-container h3 {
        margin-bottom: 20px;
        font-size: 24px;
        color: #333;
    }

    /* Input fields styling */
    .loginform-container input[type="text"],
    .loginform-container input[type="password"] {
        width: calc(100% - 20px);
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        display: block;
        margin-left: auto;
        margin-right: auto;
    }


    .loginform-container button {
        width: 100%;
        padding: 12px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        margin-top: 15px;
    }

    .loginform-container button:hover {
        background-color: #0056b3;

    }

    /* Responsive design for smaller screens */
    @media screen and (max-width: 768px) {
        .loginform-container {
            width: 90%;
            /* Full width for mobile */
            margin: 20px auto;
            /* Center and adjust margin */
        }
    }


    .form-containers {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-left: 7rem;
    }

    /* Form and button styles */
    .form-container {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    input[type="text"],
    input[type="password"],
    input[type="email"],
    input[type="date"],
    input[type="file"],
    input[type="number"],
    textarea,
    select {
        width: calc(100% - 22px);
        /* Ensure inputs span full width of form */
        padding: 10px;
        margin: 8px 0;
        display: block;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    textarea {
        height: 100px;
    }

    input[type="submit"],
    button {
        width: auto;
        background-color: #e57373;
        color: white;
        padding: 10px 20px;
        margin: 8px 0;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    input[type="submit"]:hover,
    button:hover {
        background-color: DodgerBlue;
    }

    /* Responsive tables */
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table,
    th,
    td {
        border: 1px solid #ccc;
    }

    th,
    td {
        text-align: left;
        padding: 8px;
    }

    th {
        background-color: #333;
        color: white;
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

    /* Media queries for responsive layouts */
    @media screen and (max-width: 768px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }

        .container {
            margin-left: 0;
            padding-top: 0;
        }

        .form-inline .form-group {
            display: block;
            width: 100%;
        }

        .form-inline button {
            width: 100%;
        }
    }

    /* Function display management */
    .function {
        display: none;
    }

    .function.active {
        display: block;
    }

    .sub-menu {
        display: none;
        margin-left: 20px;
        margin-top: 10px;
    }

    .sub-menu a {
        font-size: 14px;
        color: #333;
        background-color: DodgerBlue;
        padding: 5px;
        border-radius: 4px;
        display: block;
        margin-bottom: 5px;
        text-decoration: none;
    }

    .sub-menu a:hover {
        background-color: #e0f7fa;
    }

    .log-container {
        background: #f8f9fa;
        border: 1px solid #ccc;
        padding: 15px;
        margin-top: 20px;
        overflow-y: auto;
        height: 400px;
    }

    .alert-container {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .alert {
        padding: 15px;
        margin: 0 auto;
        border: 1px solid transparent;
        border-radius: 4px;
        text-align: center;
        width: 50%;
    }

    .alert-success {
        color: black;
        background-color: white;
        border-color: black;
    }

    .alert-warning {
        color: #black;
        background-color: #white;
        border-color: #black;
    }

    /* Dashboard Container Styling */
    #dashboard {
        margin-left: 8rem;
        /* Aligns the dashboard content properly */
        padding: 20px;
        /* Adds spacing around the content */
        background-color: #ffffff;
        /* Sets a white background for better contrast */
        border-radius: 10px;
        /* Rounds the corners of the container */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        /* Adds a subtle shadow for depth */
    }

    /* Dashboard Heading Styling */
    #dashboard h2 {
        color: black;
        /* Changes the color to a primary blue shade */
        margin-bottom: 20px;
        /* Adds space below the heading */
        font-weight: bold;
        /* Makes the font bold */
        text-align: center;
        /* Centers the text */
    }

    /* Dashboard Subheading Styling */
    #dashboard h4 {
        color: #333;
        /* Sets a dark gray color for subheadings */
        margin-bottom: 15px;
        /* Adds space below subheadings */
        font-weight: 600;
        /* Slightly bolder font weight */
    }

    /* Table Styles for Dashboard Content */
    #dashboard .table-container table {
        background-color: #f8f9fa;
        /* Light gray background for table */
        margin-top: 15px;
        /* Adds spacing above the table */
        border: 1px solid #ddd;
        /* Light border around the table */
        width: 100%;
        /* Full width for table */
        border-collapse: collapse;
        /* Collapses table borders */
    }

    /* Table Header Styles */
    #dashboard .table-container th {
        background-color: black;
        /* Sets a primary blue color for headers */
        color: #ffffff;
        /* White text color for better contrast */
        padding: 10px;
        /* Adds padding for better spacing */
        text-align: center;
        /* Centers the text */
        font-weight: bold;
        /* Makes the text bold */
    }

    /* Table Row and Cell Styles */
    #dashboard .table-container td {
        padding: 10px;
        /* Adds padding for better spacing */
        text-align: center;
        /* Centers the text in cells */
        border: 1px solid #ddd;
        /* Adds border between cells */
        vertical-align: middle;
        /* Aligns text vertically in the middle */
    }

    /* Image Styles for Facility Photos */
    #dashboard .facility-photo {
        width: 80px;
        /* Sets a fixed width for consistency */
        height: auto;
        /* Maintains aspect ratio */
        border-radius: 5px;
        /* Rounds the corners of images */
        cursor: pointer;
        /* Changes cursor to pointer on hover */
        transition: transform 0.2s ease;
        /* Smooth transition for hover effect */
    }

    #dashboard .facility-photo:hover {
        transform: scale(1.1);
        /* Slightly enlarges the image on hover */
    }

    /* Responsive Design Adjustments */
    @media screen and (max-width: 768px) {
        #dashboard {
            margin-left: 0;
            /* Removes left margin for smaller screens */
            padding: 10px;
            /* Reduces padding for smaller screens */
        }

        #dashboard h2 {
            font-size: 20px;
            /* Reduces heading size on smaller screens */
        }

        #dashboard h4 {
            font-size: 16px;
            /* Reduces subheading size on smaller screens */
        }

        #dashboard .table-container table {
            font-size: 14px;
            /* Reduces table font size for readability on smaller screens */
        }
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

    document.addEventListener('DOMContentLoaded', function() {
        var activeTab = '<?php echo $activeTab; ?>';
        if (activeTab) {
            showFunction(activeTab);
        }

        // Hide alert after 2 seconds
        var alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 2000);
        }
    });
    </script>
</head>

<body>
    <?php if (isset($_SESSION['user_name'])): ?>
    <!-- Sidebar -->
    <div class="sidebar">
        <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
        <!-- Super Admin Sidebar Links -->
        <a href="javascript:void(0)" onclick="showFunction('dashboard')">Dashboard</a>
        <a href="javascript:void(0)" onclick="showFunction('superAdminManagement')">Super Admin Management</a>
        <a href="javascript:void(0)" onclick="showFunction('managerManagement')">Manager Management</a>
        <a href="javascript:void(0)" onclick="showFunction('facilityManagement')">Facility Management</a>
        <a href="javascript:void(0)" onclick="showFunction('viewBookingHistory')">Resident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('viewExternalRental')">Nonresident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('feedback')">Feedback</a>
        <a href="javascript:void(0)" onclick="showFunction('log')">Logs</a>
        <a href="javascript:void(0)" onclick="showFunction('salesAnalysis')">Sales Analysis</a>
        <?php elseif ($_SESSION['user_role'] === 'Apartment Manager'): ?>
        <!-- Apartment Manager Sidebar Links -->
        <a href="javascript:void(0)" onclick="showFunction('dashboard')">Dashboard</a>
        <a href="javascript:void(0)" onclick="showFunction('viewBookingHistory')">View Resident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('viewExternalRental')">View Nonresident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('sendAnnouncement')">Send Announcement</a>
        <a href="javascript:void(0)" onclick="showFunction('deleteAnnouncement')">Delete Announcement</a>
        <a href="javascript:void(0)" onclick="showFunction('feedback')">Feedback</a>
        <a href="javascript:void(0)" onclick="showFunction('manageFacilities')">Manage Facilities</a>
        <a>Total Residents Registered: <?php echo $totalResidents; ?></a>
        <a>Total Nonresidents Registered: <?php echo $totalNonresidents; ?></a>
        <?php endif; ?>
        <form method="post" action="">
            <button type="submit" name="logout" class="btn btn-danger w-100 mt-4">Logout</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Dashboard Content -->
        <div id="dashboard" class="function">
            <h2>
                <?php 
        // Check the user's role and display the corresponding dashboard title
        if (isset($_SESSION['user_role'])) {
            if ($_SESSION['user_role'] === 'Super Admin') {
                echo "Super Admin Dashboard";
            } elseif ($_SESSION['user_role'] === 'Apartment Manager') {
                echo "Apartment Manager Dashboard";
            }
        }
        ?>
            </h2>

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
        <!-- Content -->
        <div class="container">
            <?php if (!isset($_SESSION["user_name"])): ?>
            <h1>28 BLVD Management System</h1>
            <!-- Login Form -->
            <div id='login' class='function active loginform-container'>
                <h3>Login</h3>
                <form method='post' action='' class='form-inline'>
                    <div class='form-group mb-2'>
                        <label for='user_name' class='sr-only'>User Name</label>
                        <input type='text' class='form-control' id='user_name' name='user_name' placeholder='User Name'
                            required>
                    </div>
                    <div class='form-group mx-sm-3 mb-2'>
                        <label for='user_password' class='sr-only'>Password</label>
                        <input type='password' class='form-control' id='user_password' name='user_password'
                            placeholder='Password' required>
                    </div>
                    <button type='submit' name='login' class='btn btn-primary mb-2'>Login</button>
                </form>
            </div>
            <?php else: ?>
            <!-- Super Admin Management -->
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Super Admin'): ?>
            <div id='superAdminManagement' class='function form-container'>
                <h3>Super Admin Management</h3>
                <form method='post' action='' class='form-inline mb-3'>
                    <div class='form-group mb-2'>
                        <label for='full_name'>Full Name:</label>
                        <input type='text' class='form-control' id='full_name' name='full_name' placeholder='Full Name'
                            required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='phone_number'>Phone Number:</label>
                        <input type='text' class='form-control' id='phone_number' name='phone_number'
                            placeholder='Phone Number' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='email'>Email:</label>
                        <input type='email' class='form-control' id='email' name='email' placeholder='Email' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='user_name'>Username:</label>
                        <input type='text' class='form-control' id='user_name' name='user_name' placeholder='Username'
                            required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='user_password'>Password:</label>
                        <input type='password' class='form-control' id='user_password' name='user_password'
                            placeholder='Password' required>
                    </div>
                    <button type='submit' name='add_super_admin' class='btn btn-custom mb-2'>Add Super Admin</button>
                </form>
                <form method='post' action='' class='form-inline'>
                    <div class='form-group mb-2'>
                        <label for='user_name_delete'>Username:</label>
                        <input type='text' class='form-control' id='user_name_delete' name='user_name'
                            placeholder='Username' required>
                    </div>
                    <button type='submit' name='delete_super_admin' class='btn btn-danger mb-2'>Delete Super
                        Admin</button>
                </form>
            </div>
            <!-- Manager Management -->
            <div id='managerManagement' class='function form-container'>
                <h3>Manager Management</h3>
                <form method='post' action='' class='form-inline mb-3'>
                    <div class='form-group mb-2'>
                        <label for='full_name'>Full Name:</label>
                        <input type='text' class='form-control' id='full_name' name='full_name' placeholder='Full Name'
                            required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='joining_date'>Joining Date:</label>
                        <input type='date' class='form-control' id='joining_date' name='joining_date' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='phone_number'>Phone Number:</label>
                        <input type='text' class='form-control' id='phone_number' name='phone_number'
                            placeholder='Phone Number' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='email'>Email:</label>
                        <input type='email' class='form-control' id='email' name='email' placeholder='Email' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='user_name'>Username:</label>
                        <input type='text' class='form-control' id='user_name' name='user_name' placeholder='Username'
                            required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='user_password'>Password:</label>
                        <input type='password' class='form-control' id='user_password' name='user_password'
                            placeholder='Password' required>
                    </div>
                    <button type='submit' name='add_apartment_manager' class='btn btn-custom mb-2'>Add Manager</button>
                </form>
                <form method='post' action='' class='form-inline'>
                    <div class='form-group mb-2'>
                        <label for='user_name_delete_manager'>Username:</label>
                        <input type='text' class='form-control' id='user_name_delete_manager' name='user_name'
                            placeholder='Username' required>
                    </div>
                    <button type='submit' name='delete_apartment_manager' class='btn btn-danger mb-2'>Delete
                        Manager</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Facility Management List and Creation Form -->
            <div id='facilityManagement' class='function form-container'>
                <h3>Facility Management</h3>

                <!-- Facility Creation Form -->
                <div id='createFacility'>
                    <h4>Create Facility</h4>
                    <form method='post' action='' enctype='multipart/form-data' class='form-inline mb-3'>
                        <div class='form-group mb-2'>
                            <label for='facility_name'>Facility Name:</label>
                            <input type='text' class='form-control' id='facility_name' name='facility_name'
                                placeholder='Facility Name' required>
                        </div>
                        <div class='form-group mb-2'>
                            <label for='facility_photo'>Facility Photo:</label>
                            <input type='file' class='form-control' id='facility_photo' name='facility_photo' required>
                        </div>
                        <div class='form-group mb-2'>
                            <label for='resident_price'>Resident Price:</label>
                            <input type='number' step='0.01' class='form-control' id='resident_price'
                                name='resident_price' placeholder='Resident Price' required>
                        </div>
                        <div class='form-group mb-2'>
                            <label for='nonresident_price'>Nonresident Price:</label>
                            <input type='number' step='0.01' class='form-control' id='nonresident_price'
                                name='nonresident_price' placeholder='Nonresident Price' required>
                        </div>
                        <div class='form-group mb-2'>
                            <label for='benefits'>Benefits:</label>
                            <textarea class='form-control' id='benefits' name='benefits' placeholder='Benefits'
                                required></textarea>
                        </div>
                        <button type='submit' name='create_facility' class='btn btn-custom mb-2'>Create
                            Facility</button>
                    </form>
                </div>

                <!-- Facility Management List -->
                <div class='table-container'>
                    <h4>Existing Facilities</h4>
                    <table class='table table-bordered'>
                        <thead>
                            <tr>
                                <th>Facility Name</th>
                                <th>Resident Price</th>
                                <th>Nonresident Price</th>
                                <th>Photo</th>
                                <th>Benefits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch all facilities from the database
                            $stmt = $conn->prepare("SELECT * FROM Facility");
                            $stmt->execute();
                            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($facilities as $facility) {
                                echo "<tr>
                                        <td>{$facility['name']}</td>
                                        <td>RM {$facility['resident_price']}</td>
                                        <td>RM {$facility['nonresident_price']}</td>
                                        <td><img src='{$facility['photo_url']}' alt='Facility Photo' style='width:100px; cursor:pointer;' onclick='openModal(\"{$facility['photo_url']}\")'></td>
                                        <td>{$facility['benefits']}</td>
                                        <td>
                                            <form method='post' action=''>
                                                <input type='hidden' name='facility_id' value='{$facility['id']}'>
                                                <button type='submit' name='edit_facility' class='btn btn-primary'>Edit</button>
                                                <button type='submit' name='delete_facility' class='btn btn-danger'>Delete</button>
                                            </form>
                                        </td>
                                      </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Facility Edit Form -->
            <?php if (isset($_POST['edit_facility'])): ?>
            <?php
            $facility_id = $_POST['facility_id'];
            $stmt = $conn->prepare("SELECT * FROM Facility WHERE id = :facility_id");
            $stmt->bindParam(':facility_id', $facility_id);
            $stmt->execute();
            $facility = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div id='editFacility' class='function form-container active'>
                <h3>Edit Facility</h3>
                <form method='post' action='' class='form-inline mb-3' enctype='multipart/form-data'>
                    <input type='hidden' name='facility_id' value='<?php echo $facility['id']; ?>'>
                    <div class='form-group mb-2'>
                        <label for='facility_name_edit'>Facility Name:</label>
                        <input type='text' class='form-control' id='facility_name_edit' name='facility_name'
                            value='<?php echo $facility['name']; ?>' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='resident_price_edit'>Resident Price:</label>
                        <input type='number' step='0.01' class='form-control' id='resident_price_edit'
                            name='resident_price' value='<?php echo $facility['resident_price']; ?>' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='nonresident_price_edit'>Nonresident Price:</label>
                        <input type='number' step='0.01' class='form-control' id='nonresident_price_edit'
                            name='nonresident_price' value='<?php echo $facility['nonresident_price']; ?>' required>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='facility_photo_edit'>Facility Photo:</label>
                        <input type='file' class='form-control' id='facility_photo_edit' name='facility_photo'>
                        <p>Current Photo: <img src='<?php echo $facility['photo_url']; ?>' alt='Facility Photo'
                                style='width:100px; cursor:pointer;'
                                onclick='openModal("<?php echo $facility['photo_url']; ?>")'></p>
                    </div>
                    <div class='form-group mb-2'>
                        <label for='benefits_edit'>Benefits:</label>
                        <textarea class='form-control' id='benefits_edit' name='benefits'
                            required><?php echo $facility['benefits']; ?></textarea>
                    </div>
                    <button type='submit' name='update_facility' class='btn btn-success mb-2'>Update Facility</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Resident Booking History -->
            <div id='viewBookingHistory'
                class='function<?php if ($activeTab === 'viewBookingHistory') echo ' active'; ?>'>
                <h3>View Resident Booking History</h3>
                <form method='post' action='#viewBookingHistory' class='form-inline mb-3'>
                    <div class='form-group mx-sm-3 mb-2'>
                        <label for='search_date'>Search Date</label>
                        <input type='date' class='form-control' id='search_date' name='search_date'
                            placeholder='Search Date'>
                    </div>
                    <div class='form-group mx-sm-3 mb-2'>
                        <label for='search_time'>Search Time</label>
                        <input type='time' class='form-control' id='search_time' name='search_time'
                            placeholder='Search Time'>
                    </div>
                    <button type='submit' name='search_resident_btn' class='btn btn-primary mb-2'>Search</button>
                    <button type='submit' name='view_all_resident_btn' class='btn btn-secondary mb-2 ml-2'>View
                        All</button>
                </form>
                <div class='table-container'>
                    <table class='table table-bordered'>
                        <thead>
                            <tr>
                                <th>Resident ID</th>
                                <th>Resident Name</th>
                                <th>Facility Name</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($activeTab === 'viewBookingHistory') {
                                if (isset($searchDate) || isset($searchTime)) {
                                    viewBookingHistory($conn, $searchDate ?? '', $searchTime ?? ''); 
                                } else {
                                    viewBookingHistory($conn); 
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- View Nonresident Booking History -->
            <div id='viewExternalRental'
                class='function<?php if ($activeTab === 'viewExternalRental') echo ' active'; ?>'>
                <h3>View Nonresident Booking History</h3>
                <form method='post' action='#viewExternalRental' class='form-inline mb-3'>
                    <div class='form-group mx-sm-3 mb-2'>
                        <label for='search_date_nonresident'>Search Date</label>
                        <input type='date' class='form-control' id='search_date_nonresident' name='search_date'
                            placeholder='Search Date'>
                    </div>
                    <div class='form-group mx-sm-3 mb-2'>
                        <label for='search_time_nonresident'>Search Time</label>
                        <input type='time' class='form-control' id='search_time_nonresident' name='search_time'
                            placeholder='Search Time'>
                    </div>
                    <button type='submit' name='search_nonresident_btn' class='btn btn-primary mb-2'>Search</button>
                    <button type='submit' name='view_all_nonresident_btn' class='btn btn-secondary mb-2 ml-2'>View
                        All</button>
                </form>
                <div class='table-container'>
                    <table class='table table-bordered'>
                        <thead>
                            <tr>
                                <th>Nonresident ID</th>
                                <th>Full Name</th>
                                <th>Facility Name</th>
                                <th>Rental Date</th>
                                <th>Rental Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($activeTab === 'viewExternalRental') {
                                if (isset($searchDate) || isset($searchTime)) {
                                    viewExternalRental($conn, $searchDate ?? '', $searchTime ?? ''); 
                                } else {
                                    viewExternalRental($conn); 
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feedback -->
            <div id='feedback' class='function table-container'>
                <h3>Read Feedback</h3>
                <table class='table table-bordered'>
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
                        <?php readFeedback($conn); ?>
                    </tbody>
                </table>
            </div>

            <!-- Log Display -->
            <div id='log' class='function'>
                <h3>System Logs</h3>
                <div class='log-container table-container'>
                    <table class='table table-bordered'>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php displayLogs($_SESSION['user_role'], $_SESSION['user_name'] ?? null); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales Analysis -->
            <div id='salesAnalysis' class='function<?php if ($activeTab === 'salesAnalysis') echo ' active'; ?>'>
                <h3>Sales Analysis</h3>
                <form method='post' action='#salesAnalysis' class='form-inline mb-3'>
                    <div class='form-group mx-sm-3 mb-2'>
                        <label for='selected_year' class='mr-2'>Select Year:</label>
                        <select name='selected_year' class='form-control' id='selected_year' required>
                            <?php 
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= 2020; $year--) {
                                echo "<option value='$year'>$year</option>";
                            }
                        ?>
                        </select>
                    </div>
                    <button type='submit' name='search_sales_analysis_btn' class='btn btn-primary mb-2'>Search</button>
                </form>
                <div class='table-container'>
                    <table class='table table-bordered'>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Payments Total Amount (RM)</th>
                                <th>Nonresident Payments Total Amount (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($activeTab === 'salesAnalysis' && isset($selectedYear)) {
                                viewSalesAnalysis($conn, $selectedYear); 
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Send Announcement -->
        <div id="sendAnnouncement" class="function form-containers">
            <h3>Send Announcement</h3>
            <form method="post" action="">
                <div class="form-group">
                    <label for="announcement_title">Announcement Title</label>
                    <input type="text" class="form-control" id="announcement_title" name="announcement_title" required>
                </div>
                <div class="form-group">
                    <label for="announcement">Announcement Message</label>
                    <textarea class="form-control announcement-textarea" id="announcement" name="announcement"
                        required></textarea>
                </div>
                <button type="submit" name="send_announcement" class="btn btn-primary">Send Announcement</button>
            </form>
        </div>

        <!-- Delete Announcement -->
        <div id="deleteAnnouncement" class="function form-containers">
            <h3>Delete Announcement</h3>
            <form method="post" action="">
                <div class="form-group">
                    <label for="announcement_title">Announcement Title</label>
                    <input type="text" class="form-control" id="announcement_title" name="announcement_title" required>
                </div>
                <button type="submit" name="delete_announcement" class="btn btn-danger">Delete Announcement</button>
            </form>
        </div>

        <!-- Manage Facilities -->
        <div id="manageFacilities" class="function form-containers">
            <h3>Manage Facilities</h3>
            <form method="post" action="">
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
            </form>
        </div>


        <!-- Remove extra endif -->

        <script>
        showDeleteModal();
        </script>

</body>

</html>