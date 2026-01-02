<?php
session_start();
require_once('includes/dbconnect.php');

$role    = (int) $_SESSION['role'];
$area_id = (int) $_SESSION['area_id'];

$sql = "
SELECT 
    'incident' AS report_type,
    i.type,
    i.urgency_level,
    i.status,
    i.latitude,
    i.longitude,
    i.description,
    i.date_created
FROM tbl_incidents i
JOIN tbl_villages v ON i.village_id = v.id
JOIN tbl_subdistricts s ON v.subdistrict_id = s.id
WHERE 1=1
";

$params = [];
$types  = "";

// Role-based filtering
if ($role === 0) { // Ketua Kampung
    $sql .= " AND i.village_id = ?";
    $params[] = $area_id;
    $types .= "i";
} elseif ($role === 1) { // Penghulu
    $sql .= " AND v.subdistrict_id = ?";
    $params[] = $area_id;
    $types .= "i";
} elseif ($role === 2) { // Pejabat Daerah
    $sql .= " AND s.district_id = ?";
    $params[] = $area_id;
    $types .= "i";
}

// UNION SOS
$sql .= "
UNION ALL
SELECT 
    'sos' AS report_type,
    so.type,
    so.urgency_level,
    so.status,
    so.latitude,
    so.longitude,
    so.description,
    so.created_at
FROM tbl_sos so
JOIN tbl_villages v ON so.village_id = v.id
JOIN tbl_subdistricts s ON v.subdistrict_id = s.id
WHERE 1=1
";

if ($role === 0) {
    $sql .= " AND so.village_id = ?";
    $params[] = $area_id;
    $types .= "i";
} elseif ($role === 1) {
    $sql .= " AND v.subdistrict_id = ?";
    $params[] = $area_id;
    $types .= "i";
} elseif ($role === 2) {
    $sql .= " AND s.district_id = ?";
    $params[] = $area_id;
    $types .= "i";
}

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);


$stmt->execute();
$result = $stmt->get_result();

$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = [
        "reportType" => $row['report_type'],       // incident or sos
        "type"       => $row['type'],
        "status"     => $row['status'],
        "level"      => strtolower($row['urgency_level']),
        "lat"        => (float)$row['latitude'],
        "lng"        => (float)$row['longitude'],
        "description"=> $row['description'],
        "date"       => $row['date_created']
    ];
}

echo json_encode($reports);
