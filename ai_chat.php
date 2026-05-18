<?php
header("Content-Type: application/json");

// 🔐 API KEY
$API_KEY = "AIzaSyDLq9Ig9JU3l2CRUFv21AGl0F1Gi3FOdEM";

// ==========================
// 1. READ INPUT
// ==========================
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// ==========================
// DEBUG
// ==========================
$debug = [];
$debug["raw_input"] = $rawInput;
$debug["decoded_input"] = $input;

// ==========================
// 2. VALIDATE INPUT
// ==========================
$postText = $input["text"] ?? "";
$metrics  = $input["metrics"] ?? [];
$embed    = $input["embed"] ?? "";
$replies  = $input["replies"] ?? [];

if (empty($postText)) {
    echo json_encode([
        "success" => false,
        "error" => "Missing 'text' field",
        "debug" => $debug
    ]);
    exit;
}

// ==========================
// 3. FORMAT METRICS
// ==========================
$metricsText = "";

if (is_array($metrics) && !empty($metrics)) {
    foreach ($metrics as $k => $v) {
        $metricsText .= ucfirst($k) . ": " . $v . "\n";
    }
} else {
    $metricsText = "No metrics available\n";
}

// ==========================
// 4. FORMAT REPLIES
// ==========================
$repliesText = "";

if (is_array($replies) && !empty($replies)) {
    foreach ($replies as $r) {
        $author  = $r["author"] ?? "Unknown";
        $handle  = $r["handle"] ?? "";
        $content = $r["content"] ?? "";

        $repliesText .= "- {$author} (@{$handle}): {$content}\n";
    }
} else {
    $repliesText = "No replies available\n";
}

// ==========================
// 5. BUILD CHAT PROMPT (FLEXIBLE)
// ==========================
$prompt = "
You are a social media analysis AI.

Analyze this post and respond clearly and concisely.

POST:
$postText

ORIGINAL EMBED:
$embed

REPLIES:
$repliesText

METRICS:
$metricsText

User instruction:
Provide the most useful analysis based on the user's request.
Be structured, but do not follow a fixed format unless necessary.
";

// ==========================
// DEBUG PROMPT
// ==========================
$debug["prompt_sent"] = $prompt;

// ==========================
// 6. CALL GEMINI
// ==========================
$payload = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

$debug["gemini_payload"] = $payload;

$ch = curl_init();

// curl_setopt($ch, CURLOPT_URL,
//     "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $API_KEY
// );


curl_setopt($ch, CURLOPT_URL,
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key=" . $API_KEY
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

// ==========================
// DEBUG RESPONSE
// ==========================
$debug["curl_error"] = $curlError;
$debug["raw_gemini_response"] = $response;

$data = json_decode($response, true);
$debug["decoded_gemini"] = $data;

// ==========================
// 7. EXTRACT RESULT
// ==========================
$summary = null;

if (
    isset($data["candidates"][0]["content"]["parts"][0]["text"])
) {
    $summary = $data["candidates"][0]["content"]["parts"][0]["text"];
} else {
    $debug["gemini_error"] = $data["error"] ?? $data;
}

// ==========================
// 8. RETURN RESPONSE
// ==========================
header('Content-Type: application/json');

echo json_encode([
    "success" => !empty($summary),
    "summary" => $summary,
    "debug" => $debug
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;