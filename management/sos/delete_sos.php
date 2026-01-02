<?php
require_once('../../includes/dbconnect.php');

if (!isset($_POST['role'], $_POST['id'], $_POST['sos_action'])) {
    die('Missing POST data');
}

$role   = $_POST['role'];
$id     = (int)$_POST['id'];
$action = $_POST['sos_action'];

if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM tbl_sos WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($role == 0) {
            header("Location: ../../ketuakampungdashboard.php?page=history");
            exit;
        } elseif ($role == 1) {
            header("Location: ../../penghuludashboard.php?page=history");
            exit;
        } elseif ($role == 2) {
            header("Location: ../../pejabatdaerahdashboard.php?page=history");
            exit;
        }
    }
}

echo "Database error";
$stmt->close();
