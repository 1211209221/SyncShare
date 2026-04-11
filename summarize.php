<?php
// =======================================
// 🌐 Universal Social Post Scraper
// Supports:
// - X (Twitter): CDN + oEmbed fallback
// - Mastodon: oEmbed
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
// Detect Mastodon URL
// =======================================
function isMastodon($url) {
    return preg_match('/https?:\/\/[^\/]+\/@[^\/]+\/\d+/', $url);
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
// X METHOD 1: CDN
// =======================================
function fetchFromCDN($id) {

    $url = "https://cdn.syndication.twimg.com/tweet-result?id=" . $id;

    return curlGet($url);
}

// =======================================
// X METHOD 2: OEMBED
// =======================================
function fetchFromOEmbed($url) {

    $api = "https://publish.twitter.com/oembed?url=" . urlencode($url);

    return curlGet($api);
}

// =======================================
// Mastodon METHOD: OEMBED
// =======================================
function fetchMastodon($url) {

    preg_match('/https?:\/\/([^\/]+)/', $url, $m);
    $domain = $m[1] ?? null;

    if (!$domain) return null;

    $api = "https://" . $domain . "/api/oembed?url=" . urlencode($url);

    return curlGet($api);
}

// =======================================
// Normalize CDN Tweet
// =======================================
function normalizeCDN($json) {

    if (!$json || !is_array($json)) return null;

    return [
        "text" => $json["text"] ?? "",
        "author" => $json["user"]["name"] ?? "",
        "handle" => $json["user"]["screen_name"] ?? "",
        "likes" => $json["favorite_count"] ?? 0,
        "retweets" => $json["retweet_count"] ?? 0,
        "replies" => $json["reply_count"] ?? 0,
        "created_at" => $json["created_at"] ?? "",
        "media" => $json["media_details"] ?? []
    ];
}

// =======================================
// MAIN
// =======================================
$result = null;
$debug = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $url = trim($_POST["url"] ?? "");

    $debug[] = "Input URL: $url";

    // ==========================
    // 🐘 Mastodon Path
    // ==========================
    if (isMastodon($url)) {

        $debug[] = "Platform: Mastodon";

        $res = fetchMastodon($url);

        $debug[] = "HTTP: " . $res["code"];
        $debug[] = "RAW: " . substr($res["raw"], 0, 200);

        if (!empty($res["json"])) {

            $result = [
                "type" => "mastodon",
                "html" => $res["json"]["html"] ?? "",
                "author" => $res["json"]["author_name"] ?? "",
                "provider" => $res["json"]["provider_name"] ?? ""
            ];

        } else {
            $result = ["error" => "Failed to fetch Mastodon post"];
        }

    } else {

        // ==========================
        // 🐦 X / Twitter Path
        // ==========================
        $id = extractTweetId($url);

        $debug[] = "Platform: X/Twitter";
        $debug[] = "Tweet ID: " . ($id ?? "NULL");

        if (!$id) {
            $result = ["error" => "Invalid Twitter URL"];
        } else {

            // STEP 1: CDN
            $cdn = fetchFromCDN($id);

            $debug[] = "CDN HTTP: " . $cdn["code"];
            $debug[] = "CDN RAW: " . substr($cdn["raw"], 0, 200);

            if (!empty($cdn["json"])) {

                $result = [
                    "type" => "twitter_cdn",
                    "data" => normalizeCDN($cdn["json"])
                ];

            } else {

                $debug[] = "CDN FAILED → Using oEmbed";

                // STEP 2: oEmbed
                $oembed = fetchFromOEmbed($url);

                $debug[] = "OEMBED HTTP: " . $oembed["code"];

                if (!empty($oembed["json"])) {

                    $result = [
                        "type" => "twitter_embed",
                        "html" => $oembed["json"]["html"] ?? "",
                        "author" => $oembed["json"]["author_name"] ?? ""
                    ];

                } else {

                    $result = [
                        "error" => "Tweet unavailable (possibly private or deleted)"
                    ];
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>🌐 Universal Social Scraper</title>

    <style>
        body { font-family: Arial; background:#f4f4f4; padding:20px; }
        .box { max-width:900px; margin:auto; background:white; padding:20px; border-radius:10px; }
        input { width:100%; padding:10px; margin-bottom:10px; }
        button { padding:10px; background:black; color:white; border:none; cursor:pointer; }
        pre { background:#111; color:#0f0; padding:10px; overflow:auto; }
        .card { padding:15px; border:1px solid #ddd; border-radius:8px; margin-top:15px; }
    </style>
</head>

<body>

<div class="box">

    <h2>🌐 Universal Social Post Scraper</h2>

    <form method="POST">
        <input type="text" name="url" placeholder="Paste X or Mastodon URL..." required>
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

        <!-- 🐦 Twitter CDN -->
        <?php if (($result["type"] ?? "") === "twitter_cdn"): ?>
            <div class="card">
                <h3>🐦 Tweet</h3>

                <p><strong><?= htmlspecialchars($result["data"]["author"]) ?></strong>
                (@<?= htmlspecialchars($result["data"]["handle"]) ?>)</p>

                <hr>

                <p><?= nl2br(htmlspecialchars($result["data"]["text"])) ?></p>

                <hr>

                ❤️ <?= $result["data"]["likes"] ?>
                | 🔁 <?= $result["data"]["retweets"] ?>
                | 💬 <?= $result["data"]["replies"] ?>
            </div>
        <?php endif; ?>

        <!-- 🐦 Twitter Embed -->
        <?php if (($result["type"] ?? "") === "twitter_embed"): ?>
            <div class="card">
                <h3>🐦 Embedded Tweet</h3>
                <?= $result["html"] ?>
            </div>
        <?php endif; ?>

        <!-- 🐘 Mastodon -->
        <?php if (($result["type"] ?? "") === "mastodon"): ?>
            <div class="card">
                <h3>🐘 Mastodon Post</h3>

                <p><strong><?= htmlspecialchars($result["author"]) ?></strong></p>
                <p><em><?= htmlspecialchars($result["provider"]) ?></em></p>

                <hr>

                <?= $result["html"] ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>