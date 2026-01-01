<?php
session_start();
include('includes/dbconnect.php');
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$errors = ['email' => ''];
date_default_timezone_set('Asia/Kuala_Lumpur');

if (isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);

    // Check email exists
    $stmt = $conn->prepare("SELECT id FROM tbl_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $errors['email'] = "Email not found";
    } else {
        // Generate OTP
        $otp = random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date("Y-m-d H:i:s", time() + 60);

        // Insert new OTP
        $stmt = $conn->prepare("
            UPDATE tbl_users 
            SET otp = ?, expired_at = ?
            WHERE email = ?
        ");
        $stmt->bind_param("sss", $otpHash, $expires, $email);
        $stmt->execute();

        // Send Email
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jackshentan0831@gmail.com';
        $mail->Password = 'rjgixoakxeimohqy';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('jackshentan0831@gmail.com', 'DVDM | Digital Village Dashboard Management');
        $mail->addAddress($email);

        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your OTP is: $otp\n\nThis OTP expires in 60 seconds.";
        $mail->send();

        $_SESSION['reset_email'] = $email;
        header("Location: verifyOTPpage.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Digital Village Dashboard</title>
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
    <div class=forgot-password-box>
        <h2>Forgot Password?</h2>
        <p>Enter the email address associated with your account.</p>
        <form method="POST">
            <div class="input-group <?php echo $errors['email'] ? 'error' : ''; ?>">
                <div class="field">
                    <i class="fas fa-envelope front-icon"></i>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Enter Email Address" required>
                </div>
                <small class="error-text"><?= $errors['email']; ?></small>
            </div>
            <button type="submit" name="send_otp">Next</button>
        </form>
    </div>
    <?php include_once('includes/footer.php'); ?>
</body>

</html>