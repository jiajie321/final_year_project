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
            writeToLogFile("Super Admin added: $user_name");
        } else {
            echo "<div class='alert alert-danger text-center' role='alert'>Failed to add Super Admin.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger text-center' role='alert'>Error: " . $e->getMessage() . "</div>";
    }
}

function deleteSuperAdmin($conn, $user_name) {
    try {
        // Check if the user exists first
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM Super_Admin WHERE user_name = :user_name");
        $checkStmt->bindParam(':user_name', $user_name);
        $checkStmt->execute();
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            // User exists, proceed with deletion
            $stmt = $conn->prepare("DELETE FROM Super_Admin WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            if ($stmt->execute()) {
                echo "<div class='alert alert-success text-center' role='alert'>Super Admin deleted successfully!</div>";
                writeToLogFile("Super Admin deleted: $user_name");
            } else {
                echo "<div class='alert alert-danger text-center' role='alert'>Failed to delete Super Admin.</div>";
            }
        } else {
            // User not found
            echo "<div class='alert alert-warning text-center' role='alert'>Username not found in database.</div>";
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
            writeToLogFile("Apartment Manager added: $user_name");
        } else {
            echo "<div class='alert alert-danger text-center' role='alert'>Failed to add Apartment Manager.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger text-center' role='alert'>Error: " . $e->getMessage() . "</div>";
    }
}

function deleteApartmentManager($conn, $user_name) {
    try {
        // Check if the apartment manager exists first
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM Admin_Manager WHERE user_name = :user_name");
        $checkStmt->bindParam(':user_name', $user_name);
        $checkStmt->execute();
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            // User exists, proceed with deletion
            $stmt = $conn->prepare("DELETE FROM Admin_Manager WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            if ($stmt->execute()) {
                echo "<div class='alert alert-success text-center' role='alert'>Apartment Manager deleted successfully!</div>";
                writeToLogFile("Apartment Manager deleted: $user_name");
            } else {
                echo "<div class='alert alert-danger text-center' role='alert'>Failed to delete Apartment Manager.</div>";
            }
        } else {
            // User not found
            echo "<div class='alert alert-warning text-center' role='alert'>Username not found in database.</div>";
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
            // Check if the photo_url is null
            $photo = !empty($feedback['photo_url']) ? "<img src='{$feedback['photo_url']}' alt='Feedback Photo' style='width:100px;'>" : 'No Image Uploaded';

            echo "<tr>
                    <td>{$feedback['full_name']}</td>
                    <td>{$feedback['phone_number']}</td>
                    <td>{$feedback['email']}</td>
                    <td>{$feedback['description']}</td>
                    <td>{$photo}</td>
                    <td>{$feedback['date']}</td>
                  </tr>";
        }
    }
}


// View sales analysis
function viewSalesAnalysis($conn, $year) {
    // Initialize an array to hold all months
    $months = [
        'January' => 0, 'February' => 0, 'March' => 0, 'April' => 0,
        'May' => 0, 'June' => 0, 'July' => 0, 'August' => 0,
        'September' => 0, 'October' => 0, 'November' => 0, 'December' => 0
    ];
    
    // Initialize arrays to hold payments for residents and non-residents
    $residentPayments = $months;
    $nonresidentPayments = $months;

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

    // Populate resident and nonresident payments arrays with actual data
    foreach ($results as $result) {
        $monthName = date('F', mktime(0, 0, 0, $result['month'], 10));
        $residentPayments[$monthName] = $result['resident_payments'];
        $nonresidentPayments[$monthName] = $result['nonresident_payments'];
    }

    // Display the table rows
    foreach ($months as $month => $value) {
        echo "<tr>
                <td>$month</td>
                <td>RM " . number_format($residentPayments[$month], 2) . "</td>
                <td>RM " . number_format($nonresidentPayments[$month], 2) . "</td>
              </tr>";
    }
}


// Send Announcement
function sendAnnouncement($conn) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_announcement'])) {
        $title = trim($_POST['announcement_title']);
        $message = trim($_POST['announcement']);
        $currentDate = date("Y-m-d H:i:s");

        if (!isset($_SESSION["user_id"]) || $_SESSION["user_id"] === null) {
            echo "<div class='alert alert-danger'>User ID is not set. Please log in again.</div>";
            return;
        }

        // Ensure user role is valid (check for 'Apartment Manager' or 'Super Admin')
        if ($_SESSION["user_role"] !== 'Apartment Manager' && $_SESSION["user_role"] !== 'Super Admin') {
            echo "<div class='alert alert-danger'>You do not have permission to send announcements.</div>";
            return;
        }

        // Insert announcement
        $sql = "INSERT INTO Announcements (title, message, date, sent_by_Admin_manager) VALUES (:title, :message, :date, :sent_by)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':date', $currentDate);
        $stmt->bindParam(':sent_by', $_SESSION["user_id"]);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success'>Announcement sent successfully.</div>";
            writeToLogFile("Announcement sent: $title by user ID: {$_SESSION["user_id"]}");
        } else {
            echo "<div class='alert alert-danger'>Failed to send announcement.</div>";
        }
    }
}


// Delete Announcement
function deleteAnnouncement($conn) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_announcement'])) {
        $title = $_POST['announcement_title'];

        // Check if the announcement exists first
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM Announcements WHERE title = :title");
        $checkStmt->bindParam(':title', $title);
        $checkStmt->execute();
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            // Announcement exists, proceed with deletion
            $sql = "DELETE FROM Announcements WHERE title = :title";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':title', $title);

            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>Announcement deleted successfully.</div>";
                writeToLogFile("Announcement deleted: $title");
            } else {
                echo "<div class='alert alert-danger'>Failed to delete announcement.</div>";
            }
        } else {
            // Announcement not found
            echo "<div class='alert alert-warning'>Announcement title not found in database.</div>";
        }
    }
}

// Manage Facilities
function manageFacilities($conn) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_facility_status'])) {
        $facility_name = $_POST['facility_name'];
        $status = $_POST['status'];
        $user_id = $_SESSION["user_id"] ?? null;
        $user_role = $_SESSION["user_role"] ?? null;

        if ($user_id && ($user_role === 'Super Admin' || $user_role === 'Apartment Manager')) {
            // Get the full name of the user
            $full_name = getUserFullName($conn, $user_id, $user_role);
            
            // Determine the role string for updating
            $role = ($user_role === 'Super Admin') ? 'Super Admin' : 'Admin Manager';

            // Fetch the current facility record to check the previous status updater
            $stmt_check = $conn->prepare("SELECT status_updated_by, status_updated_by_role FROM Facility WHERE name = :facility_name");
            $stmt_check->bindParam(':facility_name', $facility_name);
            $stmt_check->execute();
            $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Check if the current user is the same as the previous updater
                if ($result['status_updated_by'] != $user_id || $result['status_updated_by_role'] != $role) {
                    // Prepare SQL statement to update status and set opposite role info to NULL
                    $stmt_update = $conn->prepare("
                        UPDATE Facility 
                        SET status = :status, 
                            status_updated_by = :updated_by, 
                            status_updated_by_role = :updated_by_role, 
                            status_updated_by_fullname = :updated_by_fullname 
                        WHERE name = :facility_name
                    ");
                    $stmt_update->bindParam(':status', $status);
                    $stmt_update->bindParam(':updated_by', $user_id);
                    $stmt_update->bindParam(':updated_by_role', $role);
                    $stmt_update->bindParam(':updated_by_fullname', $full_name);
                    $stmt_update->bindParam(':facility_name', $facility_name);
                } else {
                    // Prepare SQL statement to update status without changing updater info
                    $stmt_update = $conn->prepare("
                        UPDATE Facility 
                        SET status = :status 
                        WHERE name = :facility_name
                    ");
                    $stmt_update->bindParam(':status', $status);
                    $stmt_update->bindParam(':facility_name', $facility_name);
                }

                if ($stmt_update->execute()) {
                    echo "<div class='alert alert-success'>Facility status changed successfully.</div>";
                    writeToLogFile("Facility status changed: $facility_name to $status by $user_role: $full_name");
                } else {
                    echo "<div class='alert alert-danger'>Failed to change facility status.</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Facility not found.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Invalid User ID or Role. Please log in again.</div>";
        }
    }
}


function createFacility($conn, $facility_name, $photo_url, $resident_price, $nonresident_price, $benefits, $created_by) {
    $stmt = $conn->prepare("INSERT INTO Facility (name, photo_url, resident_price, nonresident_price, benefits, created_by_Super_admin) 
                            VALUES (:name, :photo_url, :resident_price, :nonresident_price, :benefits, :created_by_Super_admin)");
    $stmt->bindParam(':name', $facility_name);
    $stmt->bindParam(':photo_url', $photo_url);
    $stmt->bindParam(':resident_price', $resident_price);
    $stmt->bindParam(':nonresident_price', $nonresident_price);
    $stmt->bindParam(':benefits', $benefits);
    $stmt->bindParam(':created_by_Super_admin', $created_by);
    if ($stmt->execute()) {
        echo "<div class='alert alert-success text-center' role='alert'>Facility created successfully with photo.</div>";
    } 
}
// Update Facility
function updateFacility($conn) {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_facility'])) {
        $facility_id = $_POST['facility_id'];
        $facility_name = $_POST['facility_name'];
        $resident_price = $_POST['resident_price'];
        $nonresident_price = $_POST['nonresident_price'];
        $benefits = $_POST['benefits'];

        // Get the current user's ID and role from the session
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];

        $photo_url = '';
        if (isset($_FILES['facility_photo']) && $_FILES['facility_photo']['error'] == UPLOAD_ERR_OK) {
            $photo_url = 'uploads/' . basename($_FILES['facility_photo']['name']);
            move_uploaded_file($_FILES['facility_photo']['tmp_name'], $photo_url);
        } else {
            $stmt = $conn->prepare("SELECT photo_url FROM Facility WHERE id = :facility_id");
            $stmt->bindParam(':facility_id', $facility_id);
            $stmt->execute();
            $facility = $stmt->fetch(PDO::FETCH_ASSOC);
            $photo_url = $facility['photo_url'];
        }

        // Update the facility record with the user who made the changes
        $stmt = $conn->prepare("UPDATE Facility 
            SET name = :name, 
                resident_price = :resident_price, 
                nonresident_price = :nonresident_price, 
                benefits = :benefits, 
                photo_url = :photo_url, 
                updated_by_Super_admin = :updated_by 
            WHERE id = :facility_id");

        $stmt->bindParam(':name', $facility_name);
        $stmt->bindParam(':resident_price', $resident_price);
        $stmt->bindParam(':nonresident_price', $nonresident_price);
        $stmt->bindParam(':benefits', $benefits);
        $stmt->bindParam(':photo_url', $photo_url);
        $stmt->bindParam(':facility_id', $facility_id);
        $stmt->bindParam(':updated_by', $user_id);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success'>Facility updated successfully.</div>";
            writeToLogFile("Facility updated: $facility_name by $user_role ID: $user_id");
        } else {
            echo "<div class='alert alert-danger'>Failed to update facility.</div>";
        }
    }
}


// Check if a facility has bookings
function hasBookings($conn, $facility_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as booking_count FROM booking WHERE facility_id = :facility_id");
    $stmt->bindParam(':facility_id', $facility_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['booking_count'] > 0;
}

// Delete Facility
function deleteFacility($conn, $facility_id) {
    // Check if the facility has any bookings
    if (hasBookings($conn, $facility_id)) {
        echo "<div class='alert alert-danger text-center' role='alert'>Cannot delete facility. There are existing bookings for this facility.</div>";
        return;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM Facility WHERE id = :facility_id");
        $stmt->bindParam(':facility_id', $facility_id);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success text-center' role='alert'>Facility deleted successfully!</div>";
            writeToLogFile("Facility deleted: ID $facility_id");
        } else {
            echo "<div class='alert alert-danger text-center' role='alert'>Failed to delete facility.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger text-center' role='alert'>Error: " . $e->getMessage() . "</div>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['create_facility'])) {
       // Get form data
        $facility_name = $_POST['facility_name'];
        $resident_price = $_POST['resident_price'];
        $nonresident_price = $_POST['nonresident_price'];
        $benefits = $_POST['benefits'];
        $created_by = $_SESSION['user_id']; // Make sure the session variable is set

       // Check and process uploaded files
        if (isset($_FILES['facility_photo']) && $_FILES['facility_photo']['error'] == UPLOAD_ERR_OK) {
            $photo_url = 'uploads/' . basename($_FILES['facility_photo']['name']);
            move_uploaded_file($_FILES['facility_photo']['tmp_name'], $photo_url);
            
            // Call the function that creates the facility
            createFacility($conn, $facility_name, $photo_url, $resident_price, $nonresident_price, $benefits, $created_by);
        } else {
            echo "<div class='alert alert-danger'>Failed to upload photo. Please try again.</div>";
        }
    }
}


// Initialize session and check user role
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
            $stmt = $conn->prepare("SELECT * FROM Admin_Manager WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $stmt = $conn->prepare("SELECT * FROM Super_Admin WHERE user_name = :user_name");
                $stmt->bindParam(':user_name', $user_name);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $user['user_role'] = 'Super Admin';
                }
            } else {
                $user['user_role'] = 'Apartment Manager';
            }

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

        header('Location: back.php');
        exit;
    } elseif (isset($_POST['add_super_admin'])) {
        addSuperAdmin($conn, $_POST['full_name'], $_POST['phone_number'], $_POST['email'], $_POST['user_name'], $_POST['user_password']);
    } elseif (isset($_POST['delete_super_admin'])) {
        deleteSuperAdmin($conn, $_POST['user_name']);
    } elseif (isset($_POST['add_apartment_manager'])) {
        addApartmentManager($conn, $_POST['full_name'], $_POST['joining_date'], $_POST['phone_number'], $_POST['email'], $_POST['user_name'], $_POST['user_password'], $_SESSION['user_id']);
    } elseif (isset($_POST['delete_apartment_manager'])) {
        deleteApartmentManager($conn, $_POST['user_name']);
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
    } elseif (isset($_POST['update_facility'])) {
        updateFacility($conn);
    }elseif (isset($_POST['delete_facility'])) {
        deleteFacility($conn, $_POST['facility_id']); 
    }elseif (isset($_POST['change_facility_status'])) {
        manageFacilities($conn);
    }elseif (isset($_POST['send_announcement'])) {
        sendAnnouncement($conn);
    }elseif (isset($_POST['delete_announcement'])) {
        deleteAnnouncement($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Backend Dashboard</title>
    <link rel="stylesheet" href="css/back.css" />
    <style>

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
        <a href="javascript:void(0)" onclick="showFunction('managerManagement')">Apartment Manager Management</a>
        <a href="javascript:void(0)" onclick="showFunction('facilityManagement')">Facility Management</a>
        <a href="javascript:void(0)" onclick="showFunction('manageFacilities')">Manage Facilities</a>
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
        <a href="javascript:void(0)" onclick="showFunction('manageFacilities')">Manage Facilities</a>
        <a href="javascript:void(0)" onclick="showFunction('feedback')">Feedback</a>
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
                if (isset($_SESSION['user_role'])) {
                    if ($_SESSION['user_role'] === 'Super Admin') {
                        echo "Super Admin Dashboard";
                    } elseif ($_SESSION['user_role'] === 'Apartment Manager') {
                        echo "Apartment Manager Dashboard";
                    }
                }
                ?>
            </h2>

            <?php if ($fullName): ?>
            <h3>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h3>
            <?php endif; ?>

            <!-- Total Registered Users Section -->
            <div class="table-containers">
                <h4>Total Registered Users</h4>
                <table class="table table-bordered" style="width: auto; margin: 0 auto;">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Total Residents Registered</th>
                            <th style="width: 150px;">Total Nonresidents Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;"><?php echo $totalResidents; ?></td>
                            <td style="text-align: center;"><?php echo $totalNonresidents; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

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

        <!-- Login Form -->
        <?php if (!isset($_SESSION["user_name"])): ?>
        <h1>28 BLVD Management System</h1>
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
                <button type='submit' name='delete_super_admin' class='btn btn-danger mb-2'>Delete Super Admin</button>
            </form>
        </div>

        <!-- Manager Management -->
        <div id='managerManagement' class='function form-container'>
            <h3>Apartment Manager Management</h3>
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
                <button type='submit' name='add_apartment_manager' class='btn btn-custom mb-2'>Add Apartment
                    Manager</button>
            </form>
            <form method='post' action='' class='form-inline'>
                <div class='form-group mb-2'>
                    <label for='user_name_delete_manager'>Username:</label>
                    <input type='text' class='form-control' id='user_name_delete_manager' name='user_name'
                        placeholder='Username' required>
                </div>
                <button type='submit' name='delete_apartment_manager' class='btn btn-danger mb-2'>Delete Apartment
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
                        <input type='number' step='0.01' class='form-control' id='resident_price' name='resident_price'
                            placeholder='Resident Price' required>
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
                    <button type='submit' name='create_facility' class='btn btn-custom mb-2'>Create Facility</button>
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
                    <input type='number' step='0.01' class='form-control' id='resident_price_edit' name='resident_price'
                        value='<?php echo $facility['resident_price']; ?>' required>
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
        <div id='viewBookingHistory' class='function<?php if ($activeTab === 'viewBookingHistory') echo ' active'; ?>'>
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
                <button type='submit' name='view_all_resident_btn' class='btn btn-secondary mb-2 ml-2'>View All</button>
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
        <div id='viewExternalRental' class='function<?php if ($activeTab === 'viewExternalRental') echo ' active'; ?>'>
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
            <h3>Feedback</h3>
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
            <h3>Logs</h3>
            <div class='table-containerss' style="max-height: 300px; overflow-y: auto;">
                <!-- Add this style to limit height and enable scrolling -->
                <table class='table table-bordered'>
                    <thead>
                        <tr>
                            <th>Date Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php displayLogs($_SESSION['user_role'], $fullName); ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Sales Analysis -->
        <div id='salesAnalysis' class='function<?php if ($activeTab === 'salesAnalysis') echo ' active'; ?>'>
            <h3>Sales Analysis</h3>
            <form method='post' action='#salesAnalysis' class='form-inline mb-3'>
                <div class='form-group mx-sm-3 mb-2'>
                    <label for='selected_year'>Select Year</label>
                    <select id='selected_year' name='selected_year' class='form-control'>
                        <?php
                        $currentYear = date("Y");
                        for ($year = $currentYear; $year >= 2022; $year--) {
                            echo "<option value='$year'" . ($year == $currentYear ? " selected" : "") . ">$year</option>";
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
                            <th>Resident Payments</th>
                            <th>Nonresident Payments</th>
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

        <!-- Send Announcement -->
        <div id='sendAnnouncement' class='function'>
            <h3>Send Announcement</h3>
            <form method='post' action=''>
                <div class='form-group mb-2'>
                    <label for='announcement_title'>Announcement Title:</label>
                    <input type='text' class='form-control' id='announcement_title' name='announcement_title'
                        placeholder='Title' required>
                </div>
                <div class='form-group mb-2'>
                    <label for='announcement'>Announcement:</label>
                    <textarea class='form-control' id='announcement' name='announcement' rows='4'
                        placeholder='Type your announcement here' required></textarea>
                </div>
                <button type='submit' name='send_announcement' class='btn btn-custom mb-2'>Send Announcement</button>
            </form>
        </div>

        <!-- Delete Announcement -->
        <div id='deleteAnnouncement' class='function'>
            <h3>Delete Announcement</h3>
            <form method='post' action=''>
                <div class='form-group mb-2'>
                    <label for='announcement_title'>Announcement Title:</label>
                    <input type='text' class='form-control' id='announcement_title' name='announcement_title'
                        placeholder='Title' required>
                </div>
                <button type='submit' name='delete_announcement' class='btn btn-danger mb-2'>Delete
                    Announcement</button>
            </form>
        </div>
        <?php endif; ?>
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


    <!-- Modal -->
    <div id='photoModal' class='modal'>
        <span class='close'>&times;</span>
        <img class='modal-content' id='modalImage'>
    </div>

    <script>
    var modal = document.getElementById('photoModal');
    var modalImg = document.getElementById('modalImage');
    var span = document.getElementsByClassName('close')[0];

    function openModal(photoUrl) {
        modal.style.display = 'block';
        modalImg.src = photoUrl;
    }

    span.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>

</html>