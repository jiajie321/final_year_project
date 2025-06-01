<?php
$servername = "127.0.0.1:3307";
$username = "jiajie";
$password = "secef017";
$dbname = "final_year_project";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $residents = [];
    $nonresidents = [];
    $residentPayments = [];
    $nonresidentPayments = [];

    $searchTerm = $_POST['search_term'] ?? $_GET['search_term'] ?? '';

    if ($searchTerm) {
        $stmt = $conn->prepare("SELECT * FROM Resident WHERE full_name LIKE :searchTerm");
        $stmt->execute(['searchTerm' => "%$searchTerm%"]);
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM Nonresident WHERE full_name LIKE :searchTerm");
        $stmt->execute(['searchTerm' => "%$searchTerm%"]);
        $nonresidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM Payments WHERE user_id IN (SELECT id FROM Resident WHERE full_name LIKE :searchTerm)");
        $stmt->execute(['searchTerm' => "%$searchTerm%"]);
        $residentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM NonresidentPayments WHERE user_id IN (SELECT id FROM Nonresident WHERE full_name LIKE :searchTerm)");
        $stmt->execute(['searchTerm' => "%$searchTerm%"]);
        $nonresidentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Resident");
        $stmt->execute();
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM Nonresident");
        $stmt->execute();
        $nonresidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM Payments");
        $stmt->execute();
        $residentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM NonresidentPayments");
        $stmt->execute();
        $nonresidentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Security Check Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }
        h1 {
            color: #333;
            text-align: center;
            font-size: 2em;
        }
        .section {
            margin-top: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .search-form {
            text-align: center;
            margin-bottom: 20px;
        }
        .search-form input[type="text"] {
            padding: 10px;
            width: 300px;
            font-size: 1em;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-form button {
            padding: 10px 20px;
            font-size: 1em;
            border: none;
            background-color: black;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-form button:hover {
            background-color: DodgerBlue;
        }
    </style>
    <script>
        let timeout = null;

        function submitForm() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                document.getElementById('search-form').submit();
            }, 500); // Adjust the delay time as needed
        }
    </script>
</head>
<body>
    <h1>Welcome to Security System</h1>

    <div class="search-form">
        <form id="search-form" method="post" action="">
            <input type="text" name="search_term" placeholder="Enter full name to search" oninput="submitForm()" value="<?= htmlspecialchars($searchTerm) ?>" required>
        </form>
    </div>

    <div class="section">
        <h2>Resident Bookings</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Block</th>
                <th>Facility ID</th>
                <th>Booking Date</th>
                <th>Booking Time</th>
                <th>Duration (hrs)</th>
                <th>Maintenance Fees</th>
                <th>Status</th>
            </tr>
            <?php foreach ($residents as $resident): ?>
                <tr>
                    <td><?= htmlspecialchars($resident['id']) ?></td>
                    <td><?= htmlspecialchars($resident['full_name']) ?></td>
                    <td><?= htmlspecialchars($resident['phone_number']) ?></td>
                    <td><?= htmlspecialchars($resident['email']) ?></td>
                    <td><?= htmlspecialchars($resident['block']) ?></td>
                    <td><?= htmlspecialchars($resident['facility_id']) ?></td>
                    <td><?= htmlspecialchars($resident['booking_date']) ?></td>
                    <td><?= htmlspecialchars($resident['booking_time']) ?></td>
                    <td><?= htmlspecialchars($resident['booking_duration']) ?></td>
                    <td><?= htmlspecialchars($resident['maintenance_fees']) ?></td>
                    <td><?= htmlspecialchars($resident['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Resident Payments</h2>
        <table>
            <tr>
                <th>User ID</th>
                <th>Facility ID</th>
                <th>Amount</th>
                <th>Payment Method</th>
            </tr>
            <?php foreach ($residentPayments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['user_id']) ?></td>
                    <td><?= htmlspecialchars($payment['facility_id']) ?></td>
                    <td><?= htmlspecialchars($payment['amount']) ?></td>
                    <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Nonresident Bookings</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Facility ID</th>
                <th>Booking Date</th>
                <th>Booking Time</th>
                <th>Duration (hrs)</th>
                <th>Rental Fees</th>
                <th>Status</th>
            </tr>
            <?php foreach ($nonresidents as $nonresident): ?>
                <tr>
                    <td><?= htmlspecialchars($nonresident['id']) ?></td>
                    <td><?= htmlspecialchars($nonresident['full_name']) ?></td>
                    <td><?= htmlspecialchars($nonresident['phone_number']) ?></td>
                    <td><?= htmlspecialchars($nonresident['email']) ?></td>
                    <td><?= htmlspecialchars($nonresident['facility_id']) ?></td>
                    <td><?= htmlspecialchars($nonresident['booking_date']) ?></td>
                    <td><?= htmlspecialchars($nonresident['booking_time']) ?></td>
                    <td><?= htmlspecialchars($nonresident['booking_duration']) ?></td>
                    <td><?= htmlspecialchars($nonresident['rental_fees']) ?></td>
                    <td><?= htmlspecialchars($nonresident['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Nonresident Payments</h2>
        <table>
            <tr>
                <th>User ID</th>
                <th>Facility ID</th>
                <th>Amount</th>
                <th>Payment Method</th>
            </tr>
            <?php foreach ($nonresidentPayments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['user_id']) ?></td>
                    <td><?= htmlspecialchars($payment['facility_id']) ?></td>
                    <td><?= htmlspecialchars($payment['amount']) ?></td>
                    <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
