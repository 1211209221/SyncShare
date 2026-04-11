<?php
// =======================================
// 🐦 X / Twitter Universal Free Scraper
// CDN + oEmbed fallback (NO API)
// =======================================

$DEBUG = true;

// =======================================
// Extract Tweet ID
// =======================================
function extractTweetId($url) {
    if (preg_match('/status\/(\d+)/', $url, $m)) {
        return $m[1];
    }
    return null;
}

// =======================================
// CURL helper
// =======================================
function curlGet($url, $headers = []) {

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $defaultHeaders = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
        "Accept: application/json,text/html,*/*"
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        "code" => $code,
        "error" => $err,
        "raw" => $raw,
        "json" => json_decode($raw, true)
    ];
}

// =======================================
// METHOD 1: CDN SYNDICATION
// =======================================
function fetchFromCDN($id) {

    $url = "https://cdn.syndication.twimg.com/tweet-result?id=" . $id;

    $res = curlGet($url);

    return [
        "source" => "cdn",
        "response" => $res
    ];
}

// =======================================
// METHOD 2: OEMBED FALLBACK
// =======================================
function fetchFromOEmbed($url) {

    $api = "https://publish.twitter.com/oembed?url=" . urlencode($url);

    $res = curlGet($api);

    return [
        "source" => "oembed",
        "response" => $res
    ];
}

// =======================================
// NORMALIZE OUTPUT
// =======================================
function normalizeCDN($json) {

    if (!$json || !is_array($json)) {
        return null;
    }

    return [
        "text" => $json["text"] ?? null,
        "author" => $json["user"]["name"] ?? null,
        "handle" => $json["user"]["screen_name"] ?? null,
        "likes" => $json["favorite_count"] ?? 0,
        "retweets" => $json["retweet_count"] ?? 0,
        "replies" => $json["reply_count"] ?? 0,
        "created_at" => $json["created_at"] ?? null,
        "media" => $json["media_details"] ?? null
    ];
}

// =======================================
// MAIN LOGIC
// =======================================
$result = null;
$debug = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $url = trim($_POST["url"] ?? "");
    $id = extractTweetId($url);

    $debug[] = "Input URL: $url";
    $debug[] = "Extracted ID: " . ($id ?? "NULL");

    if (!$id) {
        $result = ["error" => "Invalid X/Twitter URL"];
    } else {

        // ==========================
        // STEP 1: TRY CDN
        // ==========================
        $debug[] = "Trying CDN...";

        $cdn = fetchFromCDN($id);

        $debug[] = "CDN HTTP: " . $cdn["response"]["code"];
        $debug[] = "CDN RAW: " . substr($cdn["response"]["raw"], 0, 200);

        $json = $cdn["response"]["json"] ?? null;

        if (!empty($json)) {

            $debug[] = "CDN SUCCESS";

            $result = [
                "source" => "cdn",
                "data" => normalizeCDN($json)
            ];

        } else {

            $debug[] = "CDN FAILED → Trying oEmbed";

            // ==========================
            // STEP 2: OEMBED FALLBACK
            // ==========================
            $oembed = fetchFromOEmbed($url);

            $debug[] = "OEMBED HTTP: " . $oembed["response"]["code"];
            $debug[] = "OEMBED RAW: " . substr($oembed["response"]["raw"], 0, 200);

            if (!empty($oembed["response"]["json"])) {

                $debug[] = "OEMBED SUCCESS";

                $result = [
                    "source" => "oembed",
                    "html" => $oembed["response"]["json"]["html"] ?? null,
                    "author" => $oembed["response"]["json"]["author_name"] ?? null
                ];

            } else {

                $debug[] = "ALL METHODS FAILED";

                $result = [
                    "error" => "Tweet unavailable via free endpoints",
                    "hint" => "Likely restricted, deleted, or blocked from syndication"
                ];
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>🐦 X Universal Free Scraper</title>

    <style>
        body { font-family: Arial; background:#f4f4f4; padding:20px; }
        .box { max-width:900px; margin:auto; background:white; padding:20px; border-radius:10px; }
        input { width:100%; padding:10px; margin-bottom:10px; }
        button { padding:10px; background:#000; color:white; border:none; cursor:pointer; }
        pre { background:#111; color:#0f0; padding:10px; overflow:auto; }
        .card { padding:15px; border:1px solid #ddd; border-radius:8px; margin-top:10px; }
    </style>
</head>

<body>

<div class="box">

    <h2>🐦 X / Twitter Universal Scraper (No API)</h2>

    <form method="POST">
        <input type="text" name="url" placeholder="Paste tweet URL..." required>
        <button type="submit">Fetch</button>
    </form>

    <?php if ($DEBUG && !empty($debug)): ?>
        <h3>🐛 Debug</h3>
        <pre><?php print_r($debug); ?></pre>
    <?php endif; ?>

    <?php if ($result): ?>
        <h3>📊 Result</h3>

        <div class="card">
            <pre><?php print_r($result); ?></pre>
        </div>

        <?php if (isset($result["data"])): ?>
            <div class="card">
                <h3>Tweet</h3>
                <p><strong>Author:</strong> <?= htmlspecialchars($result["data"]["author"] ?? "N/A") ?></p>
                <p><strong>Handle:</strong> @<?= htmlspecialchars($result["data"]["handle"] ?? "N/A") ?></p>

                <hr>

                <p><?= nl2br(htmlspecialchars($result["data"]["text"] ?? "")) ?></p>

                <hr>

                <p>❤️ Likes: <?= $result["data"]["likes"] ?? 0 ?></p>
                <p>🔁 Retweets: <?= $result["data"]["retweets"] ?? 0 ?></p>
                <p>💬 Replies: <?= $result["data"]["replies"] ?? 0 ?></p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>