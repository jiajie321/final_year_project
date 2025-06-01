<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connection.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $form_to_show = 'login'; // Default to show login form

    // Function to reset password
    function resetPassword($conn, $user_name, $new_password) {
        $stmt = $conn->prepare("UPDATE register SET password = :new_password WHERE user_name = :user_name");
        $stmt->bindParam(':new_password', $new_password);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->execute();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';
        $user_name = $_POST['user_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $user_type = $_POST['user_type'] ?? ''; // "resident" or "nonresident"
        $block = $_POST['block'] ?? '';
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        if ($action == "register") {
            // Check for duplicate usernames in the register table
            $stmt = $conn->prepare("SELECT COUNT(*) FROM register WHERE user_name = :user_name");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $register_error = "Username already exists. Please choose a different username.";
                $form_to_show = 'signup';
            } else {
                $stmt = $conn->prepare("INSERT INTO register (user_name, password, full_name, phone_number, email, block, type) VALUES (:user_name, :password, :full_name, :phone_number, :email, :block, :type)");
                
                $stmt->bindParam(':user_name', $user_name);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':block', $block);
                $stmt->bindParam(':type', $user_type);
                $stmt->execute();

                $success = "Registration successful. You can now log in.";
                $form_to_show = 'signup'; // Show signup form to display success message
            }
        } elseif ($action == "login") {
            // Check in register table for both resident and nonresident
            $stmt = $conn->prepare("SELECT * FROM register WHERE user_name = :user_name AND password = :password");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->bindParam(':password', $password);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Successful login, set session variables
                $_SESSION['register_id'] = $user['id']; // Make sure to save the register_id
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['phone_number'] = $user['phone_number'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['block'] = $user['block'];
                $_SESSION['user_type'] = $user['type'];
                header("Location: resident_nonresident.php");
                exit;
            } else {
                $login_error = "Invalid username or password";
                $form_to_show = 'login';
            }
        } elseif ($action == "forgot_password") {
            $user_name = $_POST['user_name'] ?? '';
            $email = $_POST['email'] ?? '';

            // Check if username and email exist and match in register
            $stmt = $conn->prepare("SELECT * FROM register WHERE user_name = :user_name AND email = :email");
            $stmt->bindParam(':user_name', $user_name);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $form_to_show = 'new_password';
                $_SESSION['reset_user_name'] = $user_name; // Store the username in the session
            } else {
                $reset_error = "Username and email do not match our records.";
                $form_to_show = 'forgot_password';
            }
        } elseif ($action == "new_password") {
            if (isset($_SESSION['reset_user_name'])) {
                $user_name = $_SESSION['reset_user_name'];
                $new_password = $_POST['new_password'];

                // Directly reset the password
                resetPassword($conn, $user_name, $new_password);
                $success = "Your password has been reset. You can now log in.";
                $form_to_show = 'login'; // Redirect to login form
                unset($_SESSION['reset_user_name']); // Clear the session variable
            } else {
                $reset_error = "Session expired. Please try again.";
                $form_to_show = 'forgot_password';
            }
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Register/Login</title>
    <link rel="stylesheet" href="css/login_resident.css" />
</head>

<body>
    <div class="container">
        <div class="toggle-buttons">
            <button id="loginBtn" onclick="toggleForm('login')">Login</button>
            <button id="signupBtn" onclick="toggleForm('signup')">Signup</button>
        </div>

        <!-- Login Form -->
        <form id="loginForm" method="post" action="">
            <input type="hidden" name="action" value="login">
            <h2>Login Form</h2>
            <label for="user_name">Username:</label>
            <input type="text" id="user_name" name="user_name" required>
            <label for="password">Password:</label>
            <input type="password" id="password_login" name="password" required>
            <!-- Show Password Checkbox for Login -->
            <label>
                <input type="checkbox" onclick="togglePasswordVisibility('password_login')"> Show Password
            </label>
            <input type="submit" value="Login">
            <?php if (isset($login_error)): ?>
            <p class="error"><?php echo $login_error; ?></p>
            <?php endif; ?>
            <div class="links">
                <p>Don't have an account? <a href="javascript:void(0)" onclick="toggleForm('signup')">Please
                        register</a>.</p>
                <p><a href="javascript:void(0)" onclick="toggleForm('forgot_password')">Forgot Password?</a></p>
            </div>
        </form>

        <!-- Signup Form -->
        <form id="signupForm" method="post" action="">
            <input type="hidden" name="action" value="register">
            <h2>Signup Form</h2>
            <label for="full_name">Full Name:</label>
            <input type="text" id="full_name" name="full_name" required>
            <label for="phone_number">Phone Number:</label>
            <input type="text" id="phone_number" name="phone_number" required>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <label for="user_name">Username:</label>
            <input type="text" id="user_name_signup" name="user_name" required>
            <label for="password">Password:</label>
            <input type="password" id="password_signup" name="password" required>
            <!-- Show Password Checkbox for Signup -->
            <label>
                <input type="checkbox" onclick="togglePasswordVisibility('password_signup')"> Show Password
            </label>
            <div id="block_field">
                <label for="block">Block:</label>
                <input type="text" id="block" name="block">
            </div>
            <label for="user_type">User Type:</label>
            <select id="user_type" name="user_type" onchange="toggleBlockField()" required>
                <option value="resident">Resident</option>
                <option value="nonresident">Non-resident</option>
            </select>
            <input type="submit" value="Signup">
            <?php if (isset($register_error)): ?>
            <p class="error"><?php echo $register_error; ?></p>
            <?php endif; ?>
            <?php if (isset($success) && $form_to_show == 'signup'): ?>
            <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>
        </form>

        <!-- Forgot Password Form -->
        <form id="forgotPasswordForm" method="post" action="">
            <input type="hidden" name="action" value="forgot_password">
            <h2>Forgot Password</h2>
            <label for="user_name">Username:</label>
            <input type="text" id="user_name_forgot" name="user_name" required>
            <label for="email">Registered Email:</label>
            <input type="email" id="email_forgot" name="email" required>
            <input type="submit" value="Submit">
            <?php if (isset($reset_error)): ?>
            <p class="error"><?php echo $reset_error; ?></p>
            <?php endif; ?>
        </form>

        <!-- New Password Form -->
        <form id="newPasswordForm" method="post" action="">
            <input type="hidden" name="action" value="new_password">
            <h2>Set New Password</h2>
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required>
            <!-- Show Password Checkbox for New Password -->
            <label>
                <input type="checkbox" onclick="togglePasswordVisibility('new_password')"> Show Password
            </label>
            <input type="submit" value="Set Password">
            <?php if (isset($success) && $form_to_show == 'new_password'): ?>
            <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>
        </form>
    </div>

    <script>
    function toggleForm(formType) {
        var loginForm = document.getElementById("loginForm");
        var signupForm = document.getElementById("signupForm");
        var forgotPasswordForm = document.getElementById("forgotPasswordForm");
        var newPasswordForm = document.getElementById("newPasswordForm");
        var loginBtn = document.getElementById("loginBtn");
        var signupBtn = document.getElementById("signupBtn");

        if (formType === 'login') {
            loginForm.classList.add('active');
            signupForm.classList.remove('active');
            forgotPasswordForm.classList.remove('active');
            newPasswordForm.classList.remove('active');
            loginBtn.classList.add('active');
            signupBtn.classList.remove('active');
        } else if (formType === 'signup') {
            signupForm.classList.add('active');
            loginForm.classList.remove('active');
            forgotPasswordForm.classList.remove('active');
            newPasswordForm.classList.remove('active');
            signupBtn.classList.add('active');
            loginBtn.classList.remove('active');
        } else if (formType === 'forgot_password') {
            forgotPasswordForm.classList.add('active');
            loginForm.classList.remove('active');
            signupForm.classList.remove('active');
            newPasswordForm.classList.remove('active');
        } else if (formType === 'new_password') {
            newPasswordForm.classList.add('active');
            loginForm.classList.remove('active');
            signupForm.classList.remove('active');
            forgotPasswordForm.classList.remove('active');
        }
    }

    function toggleBlockField() {
        var userType = document.getElementById("user_type").value;
        var blockField = document.getElementById("block_field");
        if (userType === "resident") {
            blockField.style.display = "block";
        } else {
            blockField.style.display = "none";
        }
    }

    function togglePasswordVisibility(id) {
        var input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
        } else {
            input.type = "password";
        }
    }

    // Set the initial form to show based on the PHP variable
    document.addEventListener("DOMContentLoaded", function() {
        var formToShow = "<?php echo $form_to_show; ?>";
        toggleForm(formToShow);
    });
    </script>
    <footer>
        <p>&copy; <?= date('Y') ?> Condominium Facilities Booking System. All rights reserved. | Created by JiaJie</p>
    </footer>
</body>

</html>