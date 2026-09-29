<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

if (empty($_FILES['image']['tmp_name'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'no image received by proxy']);
    exit;
}

$cFile = curl_file_create($_FILES['image']['tmp_name'], $_FILES['image']['type'], 'frame.jpg');

$ch = curl_init('http://72.61.177.197:5050/ocr');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ['image' => $cFile],
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json');

if ($curlErr) {
    echo json_encode(['error' => 'curl_error: ' . $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['error' => 'vps_http_' . $httpCode, 'body' => substr($response, 0, 300)]);
    exit;
}

echo $response;