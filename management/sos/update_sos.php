<?php
require_once('../../includes/dbconnect.php');

if (!isset($_POST['role'], $_POST['id'], $_POST['sos_action'])) {
    die('Missing POST data');
}

$role   = $_POST['role'];
$id     = (int)$_POST['id'];
$action = $_POST['sos_action'];

switch ($action) {
    case 'approve':
        if ($role == 0) {
            $status = 'In Progress';
        } elseif ($role == 1) {
            $status = 'Progressing';
        } elseif ($role == 2) {
            $status = 'Resolved';
        } else {
            die('Invalid role');
        }
        break;

    case 'reject':
        $status = 'Reject';
        break;

    default:
        die('Invalid action');
}

$stmt = $conn->prepare("UPDATE tbl_sos SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    if ($role == 0) {
        header("Location: ../../ketuakampungdashboard.php?page=overview");
        exit;
    } elseif ($role == 1) {
        header("Location: ../../penghuludashboard.php?page=overview");
        exit;
    } elseif ($role == 2) {
        header("Location: ../../pejabatdaerahdashboard.php?page=overview");
        exit;
    }
}

echo "Database error";
$stmt->close();
