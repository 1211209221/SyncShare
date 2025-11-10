<?php
// groq_request.php

header('Content-Type: application/json');

// --------------------
// 1️⃣ Get user input
// --------------------
$content = trim($_POST['content'] ?? '');
$instruction = trim($_POST['instruction'] ?? '');

if (empty($content)) {
    echo json_encode(["error" => "Missing content"]);
    exit;
}

// --------------------
// 2️⃣ Combine both into a clear AI prompt
// --------------------
$finalInput = "Instruction: " . ($instruction ?: "Rewrite the text to improve tone or clarity") .
    "\n\nContent: " . $content .
    "\n\nPlease summarize or rewrite the above content following the instruction, in a concise form under 250 characters.";

// --------------------
// 3️⃣ Groq API settings
// --------------------
$apiKey = getenv('GROQ_API_KEY') ?: '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';
$baseUrl = "https://api.groq.com/openai/v1/responses";
$model = "openai/gpt-oss-20b";

$payload = [
    "model" => $model,
    "input" => $finalInput
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

// --------------------
// 4️⃣ Send request
// --------------------
$ch = curl_init($baseUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payloadJson
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --------------------
// 5️⃣ Extract AI output
// --------------------
$outputText = '';
$data = json_decode($response, true);

if (isset($data['output'])) {
    foreach ($data['output'] as $block) {
        if (isset($block['content'])) {
            foreach ($block['content'] as $contentBlock) {
                if (($contentBlock['type'] ?? '') === 'output_text') {
                    $outputText .= $contentBlock['text'] . "\n";
                }
            }
        }
    }
}

$outputText = trim($outputText);

// --------------------
// 6️⃣ Return response
// --------------------
if ($outputText) {
    echo json_encode(["modified" => $outputText]);
} else {
    $error = $curlError ?: ($data['error']['message'] ?? "No output found or invalid response.");
    echo json_encode(["error" => $error]);
}
