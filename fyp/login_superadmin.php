<?php
require 'db_connection.php';
session_start();

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
            print_r($stmt->errorInfo());
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger text-center' role='alert'>Error: " . $e->getMessage() . "</div>";
    }
}

function addApartmentManager($conn, $full_name, $joining_date, $phone_number, $email, $user_name, $user_password, $super_admin_id) {
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

function updateFacility($conn, $facility_id, $facility_name, $resident_price, $nonresident_price, $benefits, $photo_url, $updated_by) {
    if ($photo_url) {
        $stmt = $conn->prepare("UPDATE Facility SET name = :name, resident_price = :resident_price, nonresident_price = :nonresident_price, benefits = :benefits, photo_url = :photo_url, updated_by_Super_admin = :updated_by 
                                WHERE id = :id");
        $stmt->bindParam(':photo_url', $photo_url);
    } else {
        $stmt = $conn->prepare("UPDATE Facility SET name = :name, resident_price = :resident_price, nonresident_price = :nonresident_price, benefits = :benefits, updated_by_Super_admin = :updated_by 
                                WHERE id = :id");
    }

    $stmt->bindParam(':name', $facility_name);
    $stmt->bindParam(':resident_price', $resident_price);
    $stmt->bindParam(':nonresident_price', $nonresident_price);
    $stmt->bindParam(':benefits', $benefits);
    $stmt->bindParam(':updated_by', $updated_by);
    $stmt->bindParam(':id', $facility_id);
    
    if ($stmt->execute()) {
        echo "<div class='alert alert-success text-center' role='alert'>Facility updated successfully.</div>";
    } 
}

// Fetch the full name of the logged-in user
function getUserFullName($conn, $user_id) {
    $stmt = $conn->prepare("SELECT full_name FROM Super_Admin WHERE id = :user_id");
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

// Fetch the full name of the logged-in user
$fullName = isset($_SESSION["user_id"]) ? getUserFullName($conn, $_SESSION["user_id"]) : '';
$latestBookings = getLatestBookings($conn);

// Resident Booking History
function viewBookingHistory($conn, $searchDate = '', $searchTime = '') {
    $query = "SELECT 
                b.id, 
                r.full_name AS resident_name, 
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
              WHERE r.type = 'resident'";
    
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
    
    foreach ($bookings as $booking) {
        echo "<tr>
                <td>{$booking['id']}</td>
                <td>{$booking['resident_name']}</td>
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


function viewExternalRental($conn, $searchDate = '', $searchTime = '') {
    $query = "SELECT 
                b.id, 
                r.full_name AS nonresident_name, 
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
    
    foreach ($bookings as $booking) {
        echo "<tr>
                <td>{$booking['id']}</td>
                <td>{$booking['nonresident_name']}</td>
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

// 读取反馈
function readFeedback($conn) {
    $query = "SELECT 
                f.full_name, 
                f.phone_number, 
                f.email, 
                f.description, 
                f.photo_url, 
                f.date 
              FROM Feedback f
              INNER JOIN register r ON f.register_id = r.id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

// 销售分析
function viewSalesAnalysis($conn, $selectedYear) {
    // Initialize an array to store the monthly totals
    $monthlyData = array_fill(1, 12, ['resident_total' => 0, 'nonresident_total' => 0]);

    // Query to fetch the sales data
    $query = "SELECT 
                MONTH(b.booking_date) AS month, 
                SUM(CASE WHEN r.type = 'resident' THEN p.amount ELSE 0 END) AS resident_total, 
                SUM(CASE WHEN r.type = 'nonresident' THEN p.amount ELSE 0 END) AS nonresident_total 
              FROM payment p
              INNER JOIN booking b ON p.booking_id = b.id
              INNER JOIN register r ON b.register_id = r.id
              WHERE YEAR(b.booking_date) = :selectedYear
              GROUP BY month";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':selectedYear', $selectedYear);
    $stmt->execute();
    
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Populate the monthly data array with the fetched results
    foreach ($sales as $sale) {
        $month = (int)$sale['month'];
        $monthlyData[$month]['resident_total'] = $sale['resident_total'];
        $monthlyData[$month]['nonresident_total'] = $sale['nonresident_total'];
    }

    // Output the data for all 12 months
    foreach ($monthlyData as $month => $data) {
        echo "<tr>
                <td>" . date("F", mktime(0, 0, 0, $month, 10)) . "</td>
                <td>RM {$data['resident_total']}</td>
                <td>RM {$data['nonresident_total']}</td>
              </tr>";
    }
}

// 检查设施是否有预订记录
function canDeleteFacility($conn, $facility_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE facility_id = :facility_id");
    $stmt->bindParam(':facility_id', $facility_id, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    return $count == 0; // 如果没有预订记录，返回true
}

function deleteFacility($conn, $facility_id) {
    if (canDeleteFacility($conn, $facility_id)) {
        $stmt = $conn->prepare("DELETE FROM Facility WHERE id = :facility_id");
        $stmt->bindParam(':facility_id', $facility_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success text-center' role='alert'>Facility deleted successfully.</div>";
        } else {
            echo "<div class='alert alert-danger text-center' role='alert'>Failed to delete Facility. Error: " . implode(" ", $stmt->errorInfo()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning text-center' role='alert'>Cannot delete facility because it has associated booking records.</div>";
    }
}

$message = '';
$activeTab = '';
$activeSubTab = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $user_name = $_POST['user_name'];
        $user_password = $_POST['user_password'];

        if (!empty($user_name) && !empty($user_password)) {
            $stmt = $conn->prepare("SELECT * FROM Super_Admin WHERE user_name = :user_name AND user_password = :user_password");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->bindParam(':user_password', $user_password);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION["user_id"] = $row['id'];  // Assuming 'id' is the column name for Super Admin ID
                $_SESSION["user_name"] = $user_name;
                $_SESSION["user_role"] = "Super Admin";
                writeToLogFile("Super Admin Login: " . $user_name);
                header('Location: '.$_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>Invalid username or password.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Please fill in both fields.</div>";
        }
    } elseif (isset($_POST['logout'])) {
        $logoutDateTime = date("Y-m-d H:i:s");
        writeToLogFile("Super Admin Logout: " . $_SESSION["user_name"] . " at " . $logoutDateTime);
        
        // 清除所有会话变量
        $_SESSION = array();

        // 如果使用cookie保存会话，删除cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // 销毁会话
        session_destroy();

        // 清除用户cookie
        setcookie("user", "", time() - 3600);

        // 重定向到登录页面
        header('Location: loginpage_super_and_manager.php');
        exit;
    } elseif (isset($_POST['add_super_admin'])) {
        $full_name = $_POST['full_name'];
        $phone_number = $_POST['phone_number'];
        $email = $_POST['email'];
        $user_name = $_POST['user_name'];
        $user_password = $_POST['user_password'];

        if (!empty($full_name) && !empty($phone_number) && !empty($email) && !empty($user_name) && !empty($user_password)) {
            addSuperAdmin($conn, $full_name, $phone_number, $email, $user_name, $user_password);
        } else {
            echo "<div class='alert alert-warning text-center' role='alert'>Please fill in all fields.</div>";
        }
    } elseif (isset($_POST['delete_super_admin'])) {
        $user_name = $_POST['user_name'];

        if (!empty($user_name)) {
            $stmt = $conn->prepare("DELETE FROM Super_Admin WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            if ($stmt->execute()) {
                echo "<div class='alert alert-success text-center' role='alert'>Super Admin deleted successfully!</div>";
            } else {
                echo "<div class='alert alert-danger text-center' role='alert'>Failed to delete Super Admin.</div>";
                print_r($stmt->errorInfo());
            }
        } else {
            echo "<div class='alert alert-warning text-center' role='alert'>Username is required.</div>";
        }
    } elseif (isset($_POST['delete_apartment_manager'])) {
        $user_name = $_POST['user_name'];

        if (!empty($user_name)) {
            $stmt = $conn->prepare("SELECT * FROM Admin_Manager WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("DELETE FROM Admin_Manager WHERE user_name = :user_name");
                $stmt->bindParam(':user_name', $user_name);
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success text-center' role='alert'>Apartment Manager deleted successfully.</div>";
                } 
            } else {
                $message = "<div class='alert alert-warning text-center' role='alert'>No such user found in the database.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Username is required.</div>";
        }
    } elseif (isset($_POST['add_apartment_manager'])) {
        $full_name = $_POST['full_name'];
        $joining_date = $_POST['joining_date'];
        $phone_number = $_POST['phone_number'];
        $email = $_POST['email'];
        $user_name = $_POST['user_name'];
        $user_password = $_POST['user_password'];

        if (!empty($full_name) && !empty($joining_date) && !empty($phone_number) && !empty($email) && !empty($user_name) && !empty($user_password)) {
            if (isset($_SESSION["user_id"])) {
                $added_by = $_SESSION["user_id"];
                if (addApartmentManager($conn, $full_name, $joining_date, $phone_number, $email, $user_name, $user_password, $added_by)) {
                    $message = "<div class='alert alert-success text-center' role='alert'>Apartment Manager added successfully.</div>";
                } 
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>Super Admin ID is missing. Please log in again.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Please fill in all fields.</div>";
        }
    } elseif (isset($_POST['delete_facility'])) {
        $facility_id = $_POST['facility_id'] ?? '';

        if (!empty($facility_id)) {
            deleteFacility($conn, $facility_id);
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Facility ID is required.</div>";
        }
    } elseif (isset($_POST['create_facility'])) {
        $facility_name = $_POST['facility_name'] ?? '';
        $photo = $_FILES['facility_photo'] ?? '';
        $resident_price = $_POST['resident_price'] ?? '';
        $nonresident_price = $_POST['nonresident_price'] ?? '';
        $benefits = $_POST['benefits'] ?? '';

        if (!empty($facility_name) && !empty($photo['name']) && !empty($resident_price) && !empty($nonresident_price) && !empty($benefits)) {
            $targetDir = "uploads/";
            $targetFile = $targetDir . basename($photo['name']);
            $uploadOk = 1;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            $check = getimagesize($photo["tmp_name"]);
            if($check !== false) {
                $uploadOk = 1;
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>File is not an image.</div>";
                $uploadOk = 0;
            }

            if ($photo["size"] > 5000000) {
                $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, your file is too large.</div>";
                $uploadOk = 0;
            }

            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" 
            && $imageFileType != "gif" ) {
                $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, only JPG, JPEG, PNG & GIF files are allowed.</div>";
                $uploadOk = 0;
            }

            if ($uploadOk == 0) {
                $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, your file was not uploaded.</div>";
            } else {
                if (move_uploaded_file($photo["tmp_name"], $targetFile)) {
                    $created_by = $_SESSION["user_id"];
                    if (createFacility($conn, $facility_name, $targetFile, $resident_price, $nonresident_price, $benefits, $created_by)) {
                        $message = "<div class='alert alert-success text-center' role='alert'>Facility created successfully with photo.</div>";
                    } 
                } else {
                    $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, there was an error uploading your file.</div>";
                }
            }
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Please fill in all fields and upload a photo.</div>";
        }
    } elseif (isset($_POST['update_facility'])) {
        $facility_id = $_POST['facility_id'] ?? '';
        $facility_name = $_POST['facility_name'] ?? '';
        $resident_price = $_POST['resident_price'] ?? '';
        $nonresident_price = $_POST['nonresident_price'] ?? '';
        $benefits = $_POST['benefits'] ?? '';
        $photo = $_FILES['facility_photo'] ?? '';

        if (!empty($facility_name) && !empty($resident_price) && !empty($nonresident_price) && !empty($benefits)) {
            $updated_by = $_SESSION["user_id"];
            if (!empty($photo['name'])) {
                $targetDir = "uploads/";
                $targetFile = $targetDir . basename($photo['name']);
                $uploadOk = 1;
                $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

                $check = getimagesize($photo["tmp_name"]);
                if($check !== false) {
                    $uploadOk = 1;
                } else {
                    $message = "<div class='alert alert-danger text-center' role='alert'>File is not an image.</div>";
                    $uploadOk = 0;
                }

                if ($photo["size"] > 5000000) {
                    $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, your file is too large.</div>";
                    $uploadOk = 0;
                }

                if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" 
                && $imageFileType != "gif" ) {
                    $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, only JPG, JPEG, PNG & GIF files are allowed.</div>";
                    $uploadOk = 0;
                }

                if ($uploadOk == 0) {
                    $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, your file was not uploaded.</div>";
                } else {
                    if (move_uploaded_file($photo["tmp_name"], $targetFile)) {
                        if (!updateFacility($conn, $facility_id, $facility_name, $resident_price, $nonresident_price, $benefits, $targetFile, $updated_by)) {
                            $message = "<div class='alert alert-danger text-center' role='alert'>Error updating facility photo.</div>";
                        }
                    } else {
                        $message = "<div class='alert alert-danger text-center' role='alert'>Sorry, there was an error uploading your file.</div>";
                    }
                }
            }

            if (empty($message)) {
                if (updateFacility($conn, $facility_id, $facility_name, $resident_price, $nonresident_price, $benefits, $photo['name'] ? $targetFile : null, $updated_by)) {
                    $message = "<div class='alert alert-success text-center' role='alert'>Facility updated successfully.</div>";
                } 
            }
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Please fill in all fields.</div>";
        }
    } elseif (isset($_POST['search_resident_btn'])) {
        $activeTab = 'viewBookingHistory';
        $searchDate = $_POST['search_date'] ?? '';
        $searchTime = $_POST['search_time'] ?? '';
    } elseif (isset($_POST['view_all_resident_btn'])) {
        $activeTab = 'viewBookingHistory';
    } elseif (isset($_POST['search_nonresident_btn'])) {
        $activeTab = 'viewExternalRental';
        $searchDate = $_POST['search_date'] ?? '';
        $searchTime = $_POST['search_time'] ?? '';
    } elseif (isset($_POST['view_all_nonresident_btn'])) {
        $activeTab = 'viewExternalRental';
    } elseif (isset($_POST['search_sales_analysis_btn'])) {
        $activeTab = 'salesAnalysis';
        $selectedYear = $_POST['selected_year'] ?? '';
    } elseif (isset($_POST['update_facility_price'])) {
        $facility_name = $_POST['facility_name'] ?? '';
        $resident_price = $_POST['resident_price'] ?? '';
        $nonresident_price = $_POST['nonresident_price'] ?? '';

        if (!empty($facility_name) && !empty($resident_price) && !empty($nonresident_price)) {
            $stmt = $conn->prepare("UPDATE Facility SET resident_price = :resident_price, nonresident_price = :nonresident_price WHERE name = :facility_name");
            $stmt->bindParam(':facility_name', $facility_name);
            $stmt->bindParam(':resident_price', $resident_price);
            $stmt->bindParam(':nonresident_price', $nonresident_price);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success text-center' role='alert'>Facility prices updated successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger text-center' role='alert'>Error updating facility prices.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning text-center' role='alert'>Please fill in all fields.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
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
        margin-left: 13rem;
        /* Adjust margin to make space for sidebar */
        padding: 20px;
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
    </style>
    <script>
    function showFunction(id) {
        var functions = document.getElementsByClassName('function');
        for (var i = 0; i < functions.length; i++) {
            functions[i].classList.remove('active');
        }
        document.getElementById(id).classList.add('active');
    }

    function toggleSubMenu() {
        var subMenu = document.getElementById('facilitySubMenu');
        if (subMenu.style.display === 'block') {
            subMenu.style.display = 'none';
        } else {
            subMenu.style.display = 'block';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var activeTab = '<?php echo $activeTab; ?>';
        var activeSubTab = '<?php echo $activeSubTab; ?>';
        if (activeTab) {
            showFunction(activeTab);
        }
        if (activeTab === 'facilityManagement') {
            toggleSubMenu();
        }
        if (activeSubTab) {
            showFunction(activeSubTab);
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

    <div class="sidebar">
        <a href="javascript:void(0)" onclick="showFunction('dashboard')">Dashboard</a>
        <a href="javascript:void(0)" onclick="showFunction('superAdminManagement')">Super Admin Management</a>
        <a href="javascript:void(0)" onclick="showFunction('managerManagement')">Manager Management</a>
        <a href="javascript:void(0)" onclick="showFunction('facilityManagement')">Facility Management</a>
        <a href="javascript:void(0)" onclick="showFunction('viewBookingHistory')">Resident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('viewExternalRental')">Nonresident Booking History</a>
        <a href="javascript:void(0)" onclick="showFunction('feedback')">Feedback</a>
        <a href="javascript:void(0)" onclick="showFunction('log')">Logs</a>
        <a href="javascript:void(0)" onclick="showFunction('salesAnalysis')">Sales Analysis</a>
        <form method="post" action="">
            <button type="submit" name="logout" class="btn btn-danger w-100 mt-4">Logout</button>
        </form>
    </div>
    <div class="container">
        <!-- Dashboard Content -->
        <div id="dashboard" class="function">
            <h2>Super Admin Dashboard</h2>

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
        <!-- Display message -->
        <div class="alert-container">
            <?php if ($message): ?>
            <div class='alert alert-success' role='alert'>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!isset($_SESSION["user_name"])): ?>
        <!-- Login Form -->
        <div id='login' class='function active form-container'>
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

        <!-- Super Admin Management -->
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

        <!-- Read Resident Feedback -->
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
</body>

</html>