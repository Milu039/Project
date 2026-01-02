<?php
session_start();
include('includes/dbconnect.php');

$user_role = (int)$_SESSION['role']; // Creator role
$user_area_id = (int)$_SESSION['area_id']; // Creator area id
$role_options = [];
$errors = [
    'role' => '',
    'email' => '',
    'phone' => '',
    'area' => '',
    'password' => '',
    'address' => '',
    'confirm_password' => ''
];
$registerFailed = false;

// Determine allowed roles for creator
if ($user_role == 1) {
    $role_options = ['0' => 'Ketua Kampung'];
} elseif ($user_role == 2) {
    $role_options = [
        '0' => 'Ketua Kampung',
        '1' => 'Penghulu',
        '2' => 'Pejabat Daerah'
    ];
}

if (isset($_POST['register'])) {

    $role = (int)$_POST['role'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    $area = trim($_POST['area']);
    $pass = $_POST['password'];
    $confirmpass = $_POST['confirm_password'];

    $hasError = false;

    // Server-side enforcement: role must be allowed for creator
    if ($user_role != 0) {
        if (!array_key_exists($role, $role_options)) {
            $errors['role'] = "You are not allowed to create this role";
            $hasError = true;
        }
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email address";
        $hasError = true;
    } else {
        $check = $conn->prepare("SELECT id FROM tbl_users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors['email'] = "Email already registered";
            $hasError = true;
        }
        $check->close();
    }

    // Phone validation
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        $errors['phone'] = "Phone number must be 10–15 digits";
        $hasError = true;
    } else {
        $check = $conn->prepare("SELECT id FROM tbl_users WHERE phone = ?");
        $check->bind_param("s", $phone);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors['phone'] = "Phone already existed";
            $hasError = true;
        }
    }

    // Address validation for ketua kampung
    if ($user_role == 0) {
        $address = trim($_POST['address'] ?? '');
        if ($address === '') {
            $errors['address'] = "Required field";
            $hasError = true;
        }
    }

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
        $hasError = true;
    }

    // Confirm password validation
    if ($pass !== $confirmpass) {
        $errors['confirm_password'] = "Passwords do not match";
        $hasError = true;
    }

    if ($hasError == false) {
        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

        if ($user_role == 0) { // Ketua Kampung
            // Check if exists
            $stmt = $conn->prepare("SELECT id FROM tbl_villages WHERE village_name = ?");
            $stmt->bind_param("s", $area);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($village_id);


            if ($stmt->num_rows > 0) {
                $stmt->fetch(); // if yes get id
            } else {
                $errors['area'] = "Invalid Village Name";
                exit;
            }
            $stmt->close();

            // Insert villager using village_id
            $stmt = $conn->prepare("INSERT INTO tbl_villagers (name, email, phone, village_id, address, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiss", $name, $email, $phone, $village_id, $address, $hashedPassword);
        } else if ($user_role == 1) { // Penghulu
            $stmt = $conn->prepare("SELECT id FROM tbl_villages WHERE village_name = ? AND subdistrict_id = ?");
            $stmt->bind_param("si", $area, $user_area_id);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($village_id);

            if ($stmt->num_rows > 0) {
                $stmt->fetch(); // if yes get id
            } else {
                // Insert new village under subdistrict
                $coords = getCoordinates($area);
                $lat = $coords['lat'];
                $lon = $coords['lon'];
                $stmtInsert = $conn->prepare("INSERT INTO tbl_villages (village_name, latitude, longitude, subdistrict_id) VALUES (?, ?)");
                $stmtInsert->bind_param("sddi", $area, $lat, $lon, $user_area_id);
                $stmtInsert->execute();
                $village_id = $conn->insert_id;
                $stmtInsert->close();
            }
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO tbl_users (role, name, email, phone, village_id, password, expired_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssis", $role, $name, $email, $phone, $village_id, $hashedPassword);
        } else if ($user_role == 2) { // Pejabat Daerah
            if ($role == 0) {
                // Find a subdistrict under this district
                $stmt = $conn->prepare("SELECT id FROM tbl_subdistricts WHERE district_id = ? LIMIT 1");
                $stmt->bind_param("i", $user_area_id); // district area id
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($subdistrict_id);
                if ($stmt->num_rows > 0) {
                    $stmt->fetch();
                } else {
                    $errors['area'] = "No subdistrict found under this district.";
                    $stmt->close();
                    exit;
                }
                $stmt->close();

                $stmt = $conn->prepare("SELECT id FROM tbl_villages WHERE village_name = ? AND subdistrict_id = ?");
                $stmt->bind_param("s", $area, $subdistrict_id);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($village_id);

                if ($stmt->num_rows > 0) {
                    $stmt->fetch(); // if yes get id
                } else {
                    $coords = getCoordinates($area);
                    $lat = $coords['lat'];
                    $lon = $coords['lon'];
                    $stmtInsert = $conn->prepare("INSERT INTO tbl_villages (village_name, latitude, longitude, subdistrict_id) VALUES (?, ?)");
                    $stmtInsert->bind_param("si", $area, $lat, $lon, $subdistrict_id);
                    $stmtInsert->execute();
                    $village_id = $conn->insert_id;
                    $stmtInsert->close();
                }
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO tbl_users (role, name, email, phone, village_id, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssis", $role, $name, $email, $phone, $village_id, $hashedPassword);
            } else if ($role == 1) {
                $stmt = $conn->prepare("SELECT id FROM tbl_subdistricts WHERE name = ? AND district_id = ?");
                $stmt->bind_param("s", $area, $user_area_id); // disctrict area id
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($subdistrict_id);

                if ($stmt->num_rows > 0) {
                    $stmt->fetch(); // if yes get id
                } else {
                    // Insert new subdistrict under district
                    $coords = getCoordinates($area);
                    $lat = $coords['lat'];
                    $lon = $coords['lon'];
                    $stmtInsert = $conn->prepare("INSERT INTO tbl_subdistricts (name, latitude, longitude, district_id) VALUES (?, ?)");
                    $stmtInsert->bind_param("sddi", $area, $lat, $lon, $user_area_id);
                    $stmtInsert->execute();
                    $subdistrict_id = $conn->insert_id;
                    $stmtInsert->close();
                }
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO tbl_users (role, name, email, phone, subdistrict_id, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssis", $role, $name, $email, $phone, $subdistrict_id, $hashedPassword);
            } else if ($role == 2) {
                $stmt = $conn->prepare("SELECT id FROM tbl_districts WHERE name = ?");
                $stmt->bind_param("s", $area);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($district_id);

                if ($stmt->num_rows > 0) {
                    $stmt->fetch(); // if yes get id
                } else {
                    // Insert new district
                    $coords = getCoordinates($area);
                    $lat = $coords['lat'];
                    $lon = $coords['lon'];
                    $stmtInsert = $conn->prepare("INSERT INTO tbl_districts (name, latitude, longitude) VALUES (?,?,?)");
                    $stmtInsert->bind_param("sdd", $area, $lat, $lon);
                    $stmtInsert->execute();
                    $district_id = $conn->insert_id;
                    $stmtInsert->close();
                }
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO tbl_users (role, name, email, phone, district_id, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssis", $role, $name, $email, $phone, $district_id, $hashedPassword);
            }
        }


        if ($stmt->execute()) {
            // Redirect to **creator’s dashboard** after registration
            $dashboardPages = [
                0 => 'ketuakampungdashboard.php',
                1 => 'penghuludashboard.php',
                2 => 'pejabatdaerahdashboard.php'
            ];
            $redirectPage = isset($dashboardPages[$user_role]) ? $dashboardPages[$user_role] : 'loginpage.php';
            header("Location: $redirectPage");
            exit;
        }
    } else {
        $registerFailed = true;
    }
}

function getCoordinates($villageName)
{
    $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($villageName) . "&format=json&limit=1";

    $opts = [
        "http" => [
            "header" => "User-Agent: DVMD-App/1.0\r\n" // Nominatim requires a User-Agent
        ]
    ];
    $context = stream_context_create($opts);

    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);

    if (!empty($data)) {
        return [
            'lat' => $data[0]['lat'],
            'lon' => $data[0]['lon']
        ];
    }

    // Fallback if not found
    return ['lat' => null, 'lon' => null];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Digital Village Dashboard</title>
    <link rel="icon" type="image/png" href="images/icon.png">
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

    <div class="register-box" style="min-width: 525px;">
        <h2>Register</h2>

        <form id="registerForm" method="POST">
            <!-- Role selection -->
            <?php if ($user_role != 0): ?>
                <div class="input-group <?= $errors['role'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-id-badge front-icon"></i>
                        <select id="roleSelect" name="role" required>
                            <?php foreach ($role_options as $value => $label): ?>
                                <option value="<?= $value ?>" <?= (isset($_POST['role']) && $_POST['role'] == $value) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <div class="field">
                            <i class="fas fa-user front-icon"></i>
                            <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Full Name" required>
                        </div>
                    </div>

                    <div class="input-group <?= $errors['email'] ? 'error' : '' ?>">
                        <div class="field">
                            <i class="fas fa-envelope front-icon"></i>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email Address" required>
                        </div>
                        <small class="error-text"><?= $errors['email']; ?></small>
                    </div>

                    <div class="input-group <?= $errors['phone'] ? 'error' : '' ?>">
                        <div class="field">
                            <i class="fas fa-phone front-icon"></i>
                            <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="Phone Number (e.g. 0123456789)" required>
                        </div>
                        <small class="error-text"><?= $errors['phone']; ?></small>
                    </div>

                    <div class="input-group <?= $errors['area'] ? 'error' : '' ?>">
                        <div class="field">
                            <i class="fas fa-map-marker-alt front-icon"></i>
                            <input type="text" name="area" id="areaHint" value="<?= htmlspecialchars($_POST['area'] ?? '') ?>" placeholder="Village name" required>
                        </div>
                        <small class="error-text"><?= $errors['area']; ?></small>
                    </div>

                    <div class="input-group <?= $errors['password'] ? 'error' : '' ?>">
                        <div class="field">
                            <i class="fas fa-lock front-icon"></i>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <small class="error-text"><?= $errors['password']; ?></small>
                    </div>

                    <div class="input-group <?= $errors['confirm_password'] ? 'error' : '' ?>">
                        <div class="field">
                            <i class="fas fa-lock front-icon"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                        </div>
                        <small class="error-text"><?= $errors['confirm_password']; ?></small>
                    </div>
                    <small class="error-text"><?= $errors['role']; ?></small>
                </div>
            <?php else: ?>
                <input type="hidden" name="role" value="0">
                <div class="input-group">
                    <div class="field">
                        <i class="fas fa-user front-icon"></i>
                        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Full Name" required>
                    </div>
                </div>

                <div class="input-group <?= $errors['email'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-envelope front-icon"></i>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email Address" required>
                    </div>
                    <small class="error-text"><?= $errors['email']; ?></small>
                </div>

                <div class="input-group <?= $errors['phone'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-phone front-icon"></i>
                        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="Phone Number (e.g. 0123456789)" required>
                    </div>
                    <small class="error-text"><?= $errors['phone']; ?></small>
                </div>

                <div class="input-group <?= $errors['area'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-map-marker-alt front-icon"></i>
                        <input type="text" name="area" id="areaHint" value="<?= htmlspecialchars($_POST['area'] ?? '') ?>" placeholder="Village name" required>
                    </div>
                    <small class="error-text"><?= $errors['area']; ?></small>
                </div>

                <div class="input-group <?= $errors['address'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-map-marker-alt front-icon"></i>
                        <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" placeholder="Address" required>
                    </div>
                    <small class="error-text"><?= $errors['address']; ?></small>
                </div>

                <div class="input-group <?= $errors['password'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-lock front-icon"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <small class="error-text"><?= $errors['password']; ?></small>
                </div>

                <div class="input-group <?= $errors['confirm_password'] ? 'error' : '' ?>">
                    <div class="field">
                        <i class="fas fa-lock front-icon"></i>
                        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    </div>
                    <small class="error-text"><?= $errors['confirm_password']; ?></small>
                </div>
            <?php endif; ?>


            <button type="submit" name="register">Register</button>
            <a href="<?php $dashboardPages = [
                            '0' => 'ketuakampungdashboard.php',
                            '1' => 'penghuludashboard.php',
                            '2' => 'pejabatdaerahdashboard.php'
                        ];
                        $redirectPage = isset($dashboardPages[$user_role]) ? $dashboardPages[$user_role] : 'loginpage.php';
                        echo "$redirectPage"; ?>" name="cancel" class="btn-cancel" style="color: #fff;">Cancel</a>
        </form>
    </div>

    <?php include_once('includes/footer.php'); ?>

    <script>
        const roleSelect = document.getElementById('roleSelect');
        const areaHint = document.getElementById('areaHint');

        roleSelect.addEventListener('change', () => {
            if (roleSelect.value == '0') {
                areaHint.placeholder = "Responsible village (e.g. Kampung Baru / Taman Desa)";
            } else if (roleSelect.value == '1') {
                areaHint.placeholder = "Responsible subdistrict (e.g. Changlun)";
            } else if (roleSelect.value == '2') {
                areaHint.placeholder = "Responsible district (e.g. Kota Setar)";
            }
        });

        <?php if ($registerFailed): ?>
            alert("Registration failed!");
        <?php endif; ?>
    </script>

</body>

</html>