<?php
require_once('includes/dbconnect.php');

$areaType = $_GET['area_type'] ?? '0';
$areaId   = intval($_GET['area_id'] ?? 0);

$lat = $lng = null;

if($areaType == '0'){ // Village
    $stmt = $conn->prepare("SELECT latitude, longitude FROM tbl_villages WHERE id=?");
} elseif($areaType == '1'){ // Subdistrict
    $stmt = $conn->prepare("SELECT latitude, longitude FROM tbl_subdistricts WHERE id=?");
} else { // District
    $stmt = $conn->prepare("SELECT latitude, longitude FROM tbl_districts WHERE id=?");
}

$stmt->bind_param("i", $areaId);
$stmt->execute();
$stmt->bind_result($lat, $lng);
$stmt->fetch();
$stmt->close();

// If lat/lng are missing, provide defaults
if (!$lat || !$lng) {
    echo json_encode(["temp"=>"--","code"=>"-1"]);
    exit;
}

// fetch from Open‑Meteo (weather api)
$url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lng}&current_weather=true";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (isset($data['current_weather'])) {
    echo json_encode([
        "temp" => round($data['current_weather']['temperature']),
        "code" => $data['current_weather']['weathercode']
    ]);
} else {
    echo json_encode(["temp"=>"--","code"=>"-1"]);
}
