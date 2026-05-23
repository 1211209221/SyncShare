<?php
header("Content-Type: application/json");

// ==========================
// API KEY
// ==========================
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
// 2. NORMALIZE INPUT (SINGLE SOURCE OF TRUTH)
// ==========================
$type = $input["type"] ?? null;
$presetKey = $input["preset_key"] ?? null;

$postText = $input["text"] ?? "";
$metrics = $input["metrics"] ?? [];
$topPosts = $input["metrics"]["top_posts"] ?? $input["top_posts"] ?? [];
$followers = $input["followers"] ?? [];
$platforms = $input["platform_distribution"] ?? [];
$embed = $input["embed"] ?? "";

// ==========================
// 3. FORMAT METRICS
// ==========================
$metricsText = "";

if (!empty($metrics)) {
    foreach ($metrics as $k => $v) {
        if (is_array($v)) {
            $v = json_encode($v);
        }
        $metricsText .= ucfirst($k) . ": " . $v . "\n";
    }
} else {
    $metricsText = "No metrics available\n";
}

// ==========================
// 4. FORMAT TOP POSTS (REAL DATA ONLY)
// ==========================
$topPostsText = "";

if (!empty($topPosts) && is_array($topPosts)) {

    foreach ($topPosts as $p) {

        $content = trim($p["content"] ?? "");
        $url = $p["url"] ?? "";

        if ($content === "") {
            $content = "[No content available]";
        }

        $topPostsText .= "
Post Content:
{$content}

URL:
{$url}

------------------------
";
    }

} else {
    $topPostsText = "No top posts available.";
}
// ==========================
// 5. ROUTER (PRESET LOGIC ONLY)
// ==========================
$prompt = "";

// =====================================================
// OVERVIEW
// =====================================================
if ($presetKey === "overview") {

    $prompt = "
You are a social media analytics assistant.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>


Provide a concise overview of the account based ONLY on these metrics.

METRICS:
$metricsText

Focus on:
- overall engagement health
- posting activity
- follower status
- platform performance
- short-term trends

IMPORTANT:
- Historical data only begins from the moment the account was connected to analytics.
- Do NOT assume the account previously had zero followers before tracking began.
- Do NOT infer fake growth or artificial follower spikes from missing historical data.

STYLE:
- concise
- clear
- professional
- under 120 words
";


// =====================================================
// OVERALL HEALTH
// =====================================================
} elseif ($presetKey === "overall_health") {

    $prompt = "
You are a senior social media strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Analyze the overall health of this account using ONLY these metrics.

METRICS:
$metricsText

Evaluate:
- account stability
- audience health
- engagement quality
- posting consistency
- follower distribution
- growth sustainability

IMPORTANT:
- Historical analytics begin at account connection date.
- Missing earlier data does NOT mean the account had zero followers previously.

Focus on:
- strengths
- weaknesses
- risks

Return clean HTML only.
";


// =====================================================
// GROWTH INSIGHTS
// =====================================================
} elseif ($presetKey === "growth_insights") {

    $prompt = "
You are a social media growth strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Analyze ONLY these growth metrics.

METRICS:
$metricsText

IMPORTANT CONTEXT:
- Historical follower tracking only begins when the account was connected to analytics.
- Initial follower jumps are NOT suspicious by default.
- Do NOT claim followers were artificially boosted, injected, or purchased unless explicitly supported by data.

Focus on:
- follower growth trends
- momentum changes
- platform performance
- audience behavior
- growth consistency
- likely causes of increases or declines

Do NOT analyze post content.

Return clean HTML only.
";


// =====================================================
// PERFORMANCE TIPS
// =====================================================
} elseif ($presetKey === "performance_tips") {

    $prompt = "
You are a social media performance strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Using ONLY these metrics, provide actionable recommendations.

METRICS:
$metricsText

Focus on:
- increasing engagement
- improving reach
- strengthening retention
- optimizing posting consistency
- platform optimization
- improving content direction

Avoid generic advice.
Prioritize practical recommendations.

Return clean HTML only.
";


// =====================================================
// TOP POSTS SUMMARY
// =====================================================
} elseif ($presetKey === "top_posts_summary") {

    $prompt = "
You are a social media content analyst.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Analyze ONLY these top performing posts.

TOP POSTS:
$topPostsText

Focus on:
- why the posts performed well
- recurring themes
- emotional hooks
- writing patterns
- audience appeal
- humor style
- repeatable strategies

Do NOT analyze unrelated metrics.

Return clean HTML only.
";


// =====================================================
// PLATFORM FOCUS
// =====================================================
} elseif ($presetKey === "platform_focus") {

    $prompt = "
You are a platform growth strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Analyze the audience distribution and platform concentration.

METRICS:
$metricsText

Focus on:
- which platform dominates growth
- audience concentration risks
- platform dependence
- expansion opportunities
- where future effort should be invested
- whether diversification is needed

IMPORTANT:
- Do NOT assume missing platform data means failure.
- Analytics only reflect tracked periods.

Return clean HTML only.
";


// =====================================================
// ENGAGEMENT DROP
// =====================================================
} elseif ($presetKey === "engagement_drop") {

    $prompt = "
You are an engagement analyst.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Analyze the engagement changes using ONLY these metrics.

METRICS:
$metricsText

Focus on:
- likely causes of engagement decline or growth
- posting frequency effects
- engagement volatility
- momentum loss
- audience fatigue
- content consistency issues

IMPORTANT:
- Avoid dramatic assumptions.
- Base conclusions only on available tracked data.

Return clean HTML only.
";


// =====================================================
// FOLLOWER CONCENTRATION
// =====================================================
} elseif ($presetKey === "follower_concentration") {

    $prompt = "
You are a social audience strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Analyze audience concentration across accounts and platforms.

METRICS:
$metricsText

Focus on:
- follower distribution
- overreliance on a single platform
- concentration risks
- resilience of audience structure
- diversification opportunities

IMPORTANT:
- Analytics only represent connected accounts and tracked periods.

Return clean HTML only.
";


// =====================================================
// NEXT CONTENT PLAN
// =====================================================
} elseif ($presetKey === "next_content_plan") {

    $prompt = "
You are a social media content strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Using these metrics and top posts, suggest what content should be created next.

METRICS:
$metricsText

TOP POSTS:
$topPostsText

Focus on:
- repeatable winning content
- content formats to prioritize
- emotional hooks
- audience interests
- posting direction
- content experimentation opportunities

Provide:
- specific content ideas
- strategic themes
- engagement-focused recommendations

Return clean HTML only.
";


// =====================================================
// GROWTH MOMENTUM
// =====================================================
} elseif ($presetKey === "growth_momentum") {

    $prompt = "
You are a social media trend analyst.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Assess whether this account is gaining, maintaining, or losing momentum.

METRICS:
$metricsText

Focus on:
- growth velocity
- engagement momentum
- consistency trends
- directional movement
- signs of acceleration or stagnation

IMPORTANT:
- Historical tracking begins only after account connection.
- Avoid assuming earlier inactivity.

Return clean HTML only.
";


// =====================================================
// ACCOUNT STRATEGY
// =====================================================
} elseif ($presetKey === "account_strategy") {

    $prompt = "
You are a senior social media strategist.Return compact clean HTML only.

Rules:
- Avoid excessive spacing
- Do NOT use empty <p> tags
- Do NOT insert unnecessary line breaks
- Keep lists compact
- Use:
  - <h4>
  - <p>
  - <ul><li>
  - <b>

Create a strategic assessment using ONLY these metrics.

METRICS:
$metricsText

Focus on:
- growth priorities
- engagement opportunities
- platform focus
- audience development
- content direction
- strategic weaknesses
- next-step recommendations

Provide:
- strategic observations
- tactical priorities
- long-term improvement suggestions

Return clean HTML only.
";


// =====================================================
// DEFAULT FALLBACK
// =====================================================
} else {

    $prompt = "
You are a social media analysis AI.

USER REQUEST:
$postText

METRICS:
$metricsText

TOP POSTS:
$topPostsText

IMPORTANT:
- Historical analytics only begin from account connection date.
- Missing older data does NOT imply zero followers or suspicious growth.

Answer the request directly.

Return plain text only.
Do NOT use HTML.
Do NOT use markdown.
";
}

// ==========================
// DEBUG PROMPT
// ==========================
$debug["prompt_sent"] = $prompt;
$debug["preset_key"] = $presetKey;
$debug["type"] = $type;

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

$ch = curl_init();

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
$summary = $data["candidates"][0]["content"]["parts"][0]["text"] ?? null;

if (!$summary) {
    $debug["gemini_error"] = $data["error"] ?? "Unknown error";
}

// ==========================
// 8. RETURN
// ==========================
echo json_encode([
    "success" => !empty($summary),
    "summary" => $summary,
    "debug" => $debug
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
?>