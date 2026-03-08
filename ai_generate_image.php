<?php
session_start();

$pixazoApiKey = "17c0f129d252488eb099ad0f16da85d0";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax_generate_image"])) {

    $prompt = trim($_POST["prompt"] ?? "");

    if (empty($prompt)) {
        echo json_encode(["success" => false, "message" => "Prompt is required"]);
        exit;
    }

    $pixazoUrl = "https://gateway.pixazo.ai/getImage/v1/getSDXLImage";

    $payload = json_encode([
        "prompt" => $prompt,
        "negative_prompt" => "low quality, blurry",
        "height" => 1024,
        "width" => 1024,
        "num_steps" => 20,
        "guidance_scale" => 5,
        "seed" => rand(1, 999999)
    ]);

    $ch = curl_init($pixazoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Ocp-Apim-Subscription-Key: $pixazoApiKey"
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($result, true);

    if ($httpCode === 200 && isset($decoded['imageUrl'])) {
        echo json_encode([
            "success" => true,
            "imageUrl" => $decoded['imageUrl']
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Image generation failed"
        ]);
    }
    exit;
}
?>