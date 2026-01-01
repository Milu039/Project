<?php
session_start();
require_once __DIR__ . '/includes/dbconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    die("Access denied");
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="incidents.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Village', 'Type', 'Urgency', 'Status']);

$q = $conn->query("
    SELECT v.village_name, i.type, i.urgency_level, i.status
    FROM tbl_incident i
    JOIN tbl_villages v ON i.village_id = v.id
");

while ($row = $q->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
