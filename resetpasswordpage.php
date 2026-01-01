<?php
session_start();
include('includes/dbconnect.php');

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgotpasswordpage.php");
    exit;
}

$errors = [
    'password' => '',
    'confirm_password' => ''
];

if (isset($_POST['reset_password'])) {
    $email = $_SESSION['reset_email'];
    $pass = $_POST['password'];
    $confimpass = $_POST['confirm_password'];

    // Password validation
    if (
        strlen($pass) < 8 ||
        !preg_match('/[A-Z]/', $pass) ||
        !preg_match('/[a-z]/', $pass) ||
        !preg_match('/[0-9]/', $pass) ||
        !preg_match('/[^A-Za-z0-9]/', $pass)
    ) {
        $errors['password'] =
            "Password must be at least 8 characters and include uppercase, lowercase, number, and special character";
    }
    // Confirm password validation
    else if ($pass !== $confirmpass) {
        $errors['confirm_password'] = "Passwords do not match";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        // Update password and clean up OTP
        $stmt = $conn->prepare("
            UPDATE tbl_users SET password = ?, otp= NULL, expired_at = NULL WHERE email = ?
        ");
        $stmt->bind_param("ss", $hash, $email);
        $stmt->execute();
    }

    session_destroy();
    header("Location: loginpage.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Digital Village Dashboard</title>
    <link rel="icon" type="image/png" href="images/icon.png">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <style>
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('images/background.png') no-repeat center center fixed;
            background-size: cover;
            filter: brightness(0.5);
            z-index: -1;
        }
    </style>
</head>

<body>
    <div class="reset-password-box">
        <h2>Reset Your Password</h2>
        <form id="resetForm" method="POST">
            <div class="input-group <?php echo $errors['password'] ? 'error' : ''; ?>">
                <div class="field">
                    <i class="fas fa-lock front-icon"></i>
                    <input type="password" name="password" required placeholder="New password">
                </div>
                <small class="error-text"><?= $errors['password'] ?></small>
            </div>
            <div class="input-group <?php echo $errors['confirm_password'] ? 'error' : ''; ?>">
                <div class="field">
                    <i class="fas fa-lock front-icon"></i>
                    <input type="password" name="confirm_password" required placeholder="Confirm password">
                </div>
                <small class="error-text"><?= $errors['confirm_password'] ?></small>
            </div>
            <button type="submit" name="reset_password">Reset</button>
        </form>
    </div>
</body>

</html>