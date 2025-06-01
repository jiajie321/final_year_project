<?php
session_start();
require 'db_connection.php';

// Enable error reporting for debugging (keep this for development, remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';
$reset_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the form fields are set
    if (isset($_POST['username']) && isset($_POST['new_password'])) {
        $username = $_POST['username'];
        $new_password = $_POST['new_password'];

        // Simple validation (you can expand this as needed)
        if (!empty($username) && !empty($new_password)) {

            // First check if the username exists in the database
            $check_stmt = $conn->prepare("SELECT * FROM register WHERE user_name = :username");
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                // Username exists, proceed with password update
                $stmt = $conn->prepare("UPDATE register SET password = :new_password WHERE user_name = :username");
                $stmt->bindParam(':new_password', $new_password);
                $stmt->bindParam(':username', $username);

                if ($stmt->execute()) {
                    $reset_success = true;
                    $message = "Password reset successfully!";
                } else {
                    $message = "Failed to reset password. Please try again.";
                }
            } else {
                // Username does not exist
                $message = "Username is wrong.";
            }
        } else {
            $message = "Please fill in all fields.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 8rem;
        background-color: #f9f9f9;
    }

    .container {
        max-width: 400px;
        margin: 0 auto;
        padding: 20px;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }

    h1 {
        text-align: center;
        color: #333;
    }

    .message {
        margin: 10px 0;
        padding: 10px;
        color: #fff;
        background-color: #333;
        border-radius: 4px;
        text-align: center;
    }

    label {
        display: block;
        margin-bottom: 5px;
        color: #333;
    }

    input[type="text"],
    input[type="password"] {
        width: 100%;
        padding: 10px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    button {
        width: 100%;
        padding: 10px;
        background-color: #333;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background-color: DodgerBlue;
    }
    </style>
    <script>
    function showCustomAlert(message) {
        const alertBox = document.createElement('div');
        alertBox.style.position = 'fixed';
        alertBox.style.top = '50%';
        alertBox.style.left = '50%';
        alertBox.style.transform = 'translate(-50%, -50%)';
        alertBox.style.backgroundColor = '#fff';
        alertBox.style.padding = '20px';
        alertBox.style.borderRadius = '8px';
        alertBox.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.2)';
        alertBox.style.zIndex = '1001';
        alertBox.style.textAlign = 'center';

        const messageParagraph = document.createElement('p');
        messageParagraph.textContent = message;
        alertBox.appendChild(messageParagraph);

        const okButton = document.createElement('button');
        okButton.textContent = 'OK';
        okButton.style.marginTop = '20px';
        okButton.onclick = function() {
            window.location.href = 'resident_nonresident.php';
        };
        alertBox.appendChild(okButton);

        document.body.appendChild(alertBox);
    }
    </script>
</head>

<body>

    <div class="container">
        <h1>Reset Password</h1>

        <form action="reset_password.php" method="post">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required>

            <button type="submit">Reset Password</button>
        </form>
    </div>

    <?php if ($reset_success): ?>
    <script>
    showCustomAlert('Password reset successfully!');
    </script>
    <?php elseif (!empty($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

</body>

</html>