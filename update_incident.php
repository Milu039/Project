<?php
require_once('includes/dbconnect.php');

if (isset($_POST['id'], $_POST['action'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $status = 'In Progress';
    } elseif ($action === 'reject') {
        $status = 'Reject';
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tbl_incidents SET urgency_level = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'status' => $status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
}
?>
