<?php
session_start();
header('Content-Type: application/json');
error_reporting(0);

$gemini_api_key = 'AIzaSyDLq9Ig9JU3l2CRUFv21AGl0F1Gi3FOdEM';
$prompt = trim($_POST['prompt'] ?? '');

if (!$prompt) {
    echo json_encode(['success' => false, 'error' => 'Prompt required']);
    exit;
}

$endpoint = "https://generativelanguage.googleapis.com/v1beta2/models/gemini-2.5-flash-image:generateImage?key=$gemini_api_key";

$payload = [
    'prompt' => $prompt,
    'size' => '1024x1024',
    'candidateCount' => 1
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Return detailed debug info
if ($httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'error' => "Gemini API error $httpCode",
        'curlError' => $curlError,
        'rawResponse' => $response
    ]);
    exit;
}

$data = json_decode($response, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => "Invalid JSON response from Gemini",
        'rawResponse' => $response
    ]);
    exit;
}

$images = [];
if (!empty($data['images'])) {
    foreach ($data['images'] as $img) {
        if (!empty($img['imageUri'])) {
            $images[] = $img['imageUri'];
        } elseif (!empty($img['b64EncodedImage'])) {
            $images[] = 'data:image/png;base64,' . $img['b64EncodedImage'];
        }
    }
}

if ($images) {
    echo json_encode(['success' => true, 'images' => $images]);
} else {
    echo json_encode(['success' => false, 'error' => 'No image generated', 'rawResponse' => $data]);
}