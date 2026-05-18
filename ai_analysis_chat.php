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
// 2. DETECT MODE
// ==========================
$isAnalyticsMode = isset($input["summary"]) || isset($input["followers"]);

$prompt = "";

// =====================================================
// ✅ ANALYTICS MODE (DASHBOARD)
// =====================================================
if ($isAnalyticsMode) {

    $summary   = $input["summary"] ?? [];
    $followers = $input["followers"] ?? [];
    $platforms = $input["platform_distribution"] ?? [];
    $topPosts  = $input["top_posts"] ?? [];

    // ==========================
    // FORMAT SUMMARY
    // ==========================
    $summaryText = "
Posts (7d): {$summary['posts_last_7_days']} (Prev: {$summary['posts_prev_7_days']})
Change: {$summary['posts_change_pct']}%

Engagement (7d): {$summary['engagement_last_7_days']} (Prev: {$summary['engagement_prev_7_days']})
Change: {$summary['engagement_change_pct']}%

Engagement Rate: {$summary['engagement_rate']}%
";

    // ==========================
    // FOLLOWERS
    // ==========================
    $followersText = "Total Followers: " . ($followers['total_followers'] ?? 0) . "\n";

    if (!empty($followers["by_account"])) {
        foreach ($followers["by_account"] as $id => $count) {
            $followersText .= "- Account {$id}: {$count}\n";
        }
    }

    // ==========================
    // PLATFORM DISTRIBUTION
    // ==========================
    $platformText = "Platform Distribution:\n";

    if (!empty($platforms)) {
        foreach ($platforms as $platform => $count) {
            $platformText .= "- {$platform}: {$count}\n";
        }
    } else {
        $platformText .= "No platform data\n";
    }

    // ==========================
    // TOP POSTS
    // ==========================
    $topPostsText = "Top Posts:\n";

    if (!empty($topPosts)) {
        foreach ($topPosts as $p) {
            $url = $p["url"] ?? "";
            $eng = $p["engagement"] ?? 0;
            $topPostsText .= "- {$url} (Engagement: {$eng})\n";
        }
    } else {
        $topPostsText .= "No top posts\n";
    }

// ==========================
// PROMPT (SMART CHAT MODE)
// ==========================
$prompt = "
You are a social media AI assistant.

The user may ask questions OR request analysis.

=========================
USER INPUT
=========================
$postText

=========================
POST CONTENT
=========================
$embed

=========================
REPLIES
=========================
$repliesText

=========================
METRICS
=========================
$metricsText

=========================
INSTRUCTIONS
=========================
- If the user asks a SPECIFIC question → answer it directly using the data
- If the user asks for analysis → provide insights (strengths, weaknesses, improvements)
- If the question involves numbers (e.g. most followers), compute the answer from the metrics
- Be concise and relevant
- Do NOT force analysis if it's not requested
";

    $debug["mode"] = "analytics";
}

// =====================================================
// ✅ POST MODE (ORIGINAL)
// =====================================================
else {

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
    // METRICS
    // ==========================
    $metricsText = "";

    if (!empty($metrics)) {
        foreach ($metrics as $k => $v) {

            // handle arrays properly
            if (is_array($v)) {
                $v = json_encode($v);
            }

            $metricsText .= ucfirst($k) . ": " . $v . "\n";
        }
    } else {
        $metricsText = "No metrics available\n";
    }

    // ==========================
    // REPLIES
    // ==========================
    $repliesText = "";

    if (!empty($replies)) {
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
    // PROMPT
    // ==========================
    $prompt = "
You are a social media analysis AI.

Analyze this post and provide useful insights.

POST:
$postText

EMBED:
$embed

REPLIES:
$repliesText

METRICS:
$metricsText

INSTRUCTIONS:
- Identify strengths
- Identify weaknesses
- Suggest improvements
- Keep it concise
";

    $debug["mode"] = "post";
}

$debug["prompt_sent"] = $prompt;

// ==========================
// 3. CALL GEMINI
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

$ch = curl_init();

// curl_setopt($ch, CURLOPT_URL,
//     "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $API_KEY
// );

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
// 4. EXTRACT RESULT
// ==========================
$summary = null;

if (isset($data["candidates"][0]["content"]["parts"][0]["text"])) {
    $summary = $data["candidates"][0]["content"]["parts"][0]["text"];
} else {
    $debug["gemini_error"] = $data["error"] ?? "Unknown error";
}

// ==========================
// 5. RETURN
// ==========================
echo json_encode([
    "success" => !empty($summary),
    "summary" => $summary,
    "debug" => $debug
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
?>
```
