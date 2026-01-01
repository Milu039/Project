<?php
require_once('includes/dbconnect.php');

$village_id = isset($_GET['village_id']) ? (int)$_GET['village_id'] : 0;
$stmt = $conn->prepare("SELECT latitude, longitude FROM tbl_villages WHERE id = ?");
$stmt->bind_param("i", $village_id);
$stmt->execute();
$result = $stmt->get_result();
$loc = $result->fetch_assoc();
$stmt->close();

$lat = $loc['latitude'];
$lon = $loc['longitude'];

// fetch from Open‑Meteo
$url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current_weather=true";

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
?>
