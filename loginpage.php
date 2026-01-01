<?php
session_start();
include('includes/dbconnect.php');

date_default_timezone_set('Asia/Kuala_Lumpur');
$errors = [
    'email' => '',
    'password' => ''
];

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // search user (SQL Injeciton Protection)
    $stmt = $conn->prepare("SELECT * FROM tbl_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Brute Force Protection
        // Check if account is locked
        if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
            $errors['email'] = "Account locked. Try again later.";
        } else {
            if (password_verify($password, $user['password'])) {
                // Reset failed attempts on success
                $reset = $conn->prepare("UPDATE tbl_users SET failed_attempts = 0, lock_until = NULL WHERE email = ?");
                $reset->bind_param("s", $email);
                $reset->execute();

                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Role-based implementation
                if ($user['role'] === '0') {
                    header("Location: ketuakampungdashboard.php");
                } elseif ($user['role'] === '1') {
                    header("Location: penghuludashboard.php");
                } elseif ($user['role'] === '2') {
                    header("Location: pejabatdaerahdashboard.php");
                }
                exit;
            } else {
                // Failed password
                if ($user['failed_attempts'] >= 5) {
                    $attempts = 1;
                } else {
                    $attempts = $user['failed_attempts'] + 1;
                }
                // 5 failed attemps then block for 15 minutes
                $lockUntil = ($attempts >= 5) ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : NULL;

                $update = $conn->prepare(
                    "UPDATE tbl_users SET failed_attempts = ?, lock_until = ? WHERE email = ?"
                );
                $update->bind_param("iss", $attempts, $lockUntil, $email);
                $update->execute();
                $errors['password'] = "Invalid password";
            }
        }
    } else {
        $errors['email'] = "Invalid email";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Login | Digital Village Dashboard</title>
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
    <div class="container">
        <div class="login-box">
            <h2>Login</h2>
            <form method="POST">
                <div class="input-group <?php echo $errors['email'] ? 'error' : ''; ?>">
                    <div class="field">
                        <i class="fas fa-envelope front-icon"></i>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email Address" required>
                    </div>
                    <small class="error-text"><?= $errors['email']; ?>
                    </small>
                </div>

                <div class="input-group <?php echo $errors['password'] ? 'error' : ''; ?>">
                    <div class="field">
                        <i class="fas fa-lock front-icon"></i>
                        <input type="password" name="password" id="password" placeholder="Password" required>
                    </div>
                    <small class="error-text"><?= $errors['password']; ?></small>
                </div>

                <button type="submit" name="login">Login</button>
                <!--
                <a href="registerpage.php" style="margin-top: 30px;">Don't have an account? Register here</a>
                        -->
                <a href="forgotpasswordpage.php" style="margin-top: 30px;">Forgot password?</a>
            </form>
        </div>

        <div class="text-section">
            <h1>Empowering Villages, Connecting Communities</h1>
            <p>Manage village resources efficiently and support your villagers with ease.</p>
        </div>
    </div>
    <?php include_once('includes/footer.php'); ?>
</body>

</html>