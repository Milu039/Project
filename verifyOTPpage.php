<?php
session_start();
include('includes/dbconnect.php');
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgotpasswordpage.php");
    exit;
}

$errorMsg = "";
$expired = false;
$otpmismatch = false;
date_default_timezone_set('Asia/Kuala_Lumpur');
$email = $_SESSION['reset_email'];

$errors = [
    'otp' => ''
];

if (isset($_POST['verify_otp'])) {
    $otp = $_POST['otp'];

    $stmt = $conn->prepare("
        SELECT otp, expired_at
        FROM tbl_users
        WHERE email = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (time() > strtotime($row['expired_at'])) {
            $errors['expired'] = "OTP expired. Please resend OTP.";
        } elseif (!password_verify($otp, $row['otp'])) {
            $errors['otp'] = "Invalid OTP";
        } else {
            header("Location: resetpasswordpage.php");
            exit;
        }
    } else {
        $errors['otp'] = "No OTP found";
    }
}

if (isset($_POST['resend_otp'])) {
    $otp = random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = date("Y-m-d H:i:s", time() + 60);

    $stmt = $conn->prepare("UPDATE tbl_users SET otp = ?, expired_at = ? WHERE email = ?");
    $stmt->bind_param("sss", $otpHash, $expires, $email);
    $stmt->execute();

    try {
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
    } catch (Exception $e) {
        $errorMsg = "Mailer Error: " . $mail->ErrorInfo;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification | Digital Village Dashboard</title>
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
    <div class="OTP-box">
        <h2>OTP Verification</h2>
        <p>Enter OTP Code sent to <?php echo $email ?></p>
        <form method="POST" id="otpForm">
            <div class="input-group <?php echo $errors['otp'] ? 'error' : ''; ?>">
                <div class="otp-inputs">
                    <input type="number" maxlength="1" class="otp-box" inputmode="numeric">
                    <input type="number" maxlength="1" class="otp-box" inputmode="numeric">
                    <input type="number" maxlength="1" class="otp-box" inputmode="numeric">
                    <input type="number" maxlength="1" class="otp-box" inputmode="numeric">
                    <input type="number" maxlength="1" class="otp-box" inputmode="numeric">
                    <input type="number" maxlength="1" class="otp-box" inputmode="numeric">
                </div>
                <input type="hidden" name="otp" id="otpHidden">
                <small class="error-text" style="text-align:center;"><?= $errors['otp'] ?></small>
            </div>
            <p style="margin-top: 30px;">Didn't receive OTP code?</p>
            <button type="submit" name="resend_otp" id="resendBtn" class="resendBtn">Resend Code</button>
            <button type="submit" name="verify_otp" id="verifyBtn" class="verifyBtn">Verify & Proceed</button>
        </form>
    </div>

    <script>
        const boxes = document.querySelectorAll('.otp-box');
        const hiddenOtp = document.getElementById('otpHidden');
        const form = document.getElementById('otpForm');

        boxes.forEach((box, index) => {
            box.addEventListener('input', () => {
                clearOTPError();
                box.value = box.value.replace(/[^0-9]/g, '');

                if (box.value && index < boxes.length - 1) {
                    boxes[index + 1].focus();
                }

                updateOTP();
            });

            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && index > 0) {
                    boxes[index - 1].focus();
                }
            });
        });

        form.addEventListener('submit', function(e) {
            const otpValue = Array.from(boxes).map(b => b.value).join('');
            hiddenOtp.value = otpValue;
        });
    </script>
</body>

</html>