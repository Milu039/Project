<?php
session_start();
include(__DIR__ . '/dbconnect.php');

// 1. Check login
if (!isset($_SESSION['user_email'], $_SESSION['role'])) {
    header("Location: ../loginpage.php");
    exit;
}

// 2. Check role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['0', '1', '2'], true)) {
    header("Location: ../loginpage.php");
    exit;
}

// 3. Load fresh user data
$email = $_SESSION['user_email'];
$stmt = $conn->prepare("SELECT id, name, email, role, village_id, subdistrict_id, district_id FROM tbl_users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: ../loginpage.php");
    exit;
}

$user = $result->fetch_assoc();

// Make user data available
$_SESSION['user_id']  = $user['id'];
$_SESSION['user_name']  = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['role']       = $user['role'];
if ($user['village_id'] != null) {
    $_SESSION['village_id']  = $user['village_id'];
}
if ($user['subdistrict_id'] != null) {
    $_SESSION['subdistrct_id']  = $user['subdistrict_id'];
}
if ($user['district_id'] != null) {
    $_SESSION['district_id']  = $user['district_id'];
}