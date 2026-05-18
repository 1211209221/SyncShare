<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];

// =====================================================
// API HELPER
// =====================================================
function socialbu_get($endpoint, $queryParams = []) {
    global $token;

    $url = 'https://socialbu.com/api/v1/' . $endpoint;

    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'raw' => $response,
        'data' => json_decode($response, true)
    ];
}

// =====================================================
// QUICK FETCH (FOR OEMBED)
// =====================================================
function quickFetch($url) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    return json_decode($res, true);
}

// =====================================================
// YOUTUBE ID HELPER
// =====================================================
function getYouTubeId($url) {
    preg_match("/(youtu\\.be\\/|v=)([a-zA-Z0-9_-]+)/", $url, $m);
    return $m[2] ?? '';
}

// =====================================================
// EMBED SYSTEM (FIXED HYBRID)
// =====================================================
function renderPostEmbed($url) {

    if (!$url) return "";

    // ===========================
    // 🟠 TWITTER / X (OEMBED FIX)
    // ===========================
    if (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) {

        $api = "https://publish.twitter.com/oembed?url=" . urlencode($url);
        $json = quickFetch($api);

        if (!empty($json['html'])) {
            return $json['html'];
        }

        return "<a href='{$url}' target='_blank'>{$url}</a>";
    }

    // ===========================
    // 🟣 MASTODON (YOUR WORKING METHOD)
    // ===========================
    if (strpos($url, 'mastodon') !== false || strpos($url, 'social') !== false) {
        return "
            <iframe
                src='{$url}/embed'
                style='width:100%; min-height:420px; border:0; border-radius:10px;'
                loading='lazy'>
            </iframe>
        ";
    }

    // ===========================
    // 🎥 YOUTUBE
    // ===========================
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {

        $id = getYouTubeId($url);

        return "
            <iframe
                src='https://www.youtube.com/embed/{$id}'
                style='width:100%; aspect-ratio:16/9; border:0; border-radius:10px;'
                allowfullscreen>
            </iframe>
        ";
    }

    // ===========================
    // 🔵 DEFAULT
    // ===========================
    return "<a href='{$url}' target='_blank' style='word-break:break-all;'>{$url}</a>";
}

// =====================================================
// DATE RANGE
// =====================================================
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

// =====================================================
// API CALLS
// =====================================================
$followers = socialbu_get('insights/accounts/followers');

$followersGrowth = socialbu_get('insights/accounts/followers/growth', [
    'start' => $startDate,
    'end' => $endDate
]);

$engagementTrend = socialbu_get('insights/accounts/engagement/trend', [
    'start' => $startDate,
    'end' => $endDate
]);

$engagementRate = socialbu_get('insights/accounts/engagement/rate');

$userStats = socialbu_get('insights/stats');

// =====================================================
// ACCOUNTS QUERY
// =====================================================
$followersData = $followers['data']['data']['followers_by_account'] ?? [];

$allAccountIds = array_column($followersData, 'account_id');

$accountsQueryString = implode('&', array_map(
    fn($id) => "accounts[]=" . urlencode($id),
    $allAccountIds
));

// =====================================================
// POSTS
// =====================================================
$postsCount = socialbu_get("insights/posts/counts?$accountsQueryString&start=$startDate&end=$endDate");

$postMetrics = socialbu_get("insights/posts/metrics?$accountsQueryString&start=$startDate&end=$endDate&metrics=likes,comments");

// =====================================================
// TOP POSTS
// =====================================================
$topPosts = socialbu_get("insights/posts/top_posts?$accountsQueryString&start=$startDate&end=$endDate&metrics=likes");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SocialBu Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    font-family: "Poppins", sans-serif;
    background: #f5f6fa;
    padding: 30px;
}

.card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
}
</style>
</head>

<body>

<div class="container">

<h1>📊 SocialBu Insights Dashboard</h1>

<!-- FOLLOWERS -->
<div class="card">
    <h3>Total Followers</h3>
    <p><?= $followers['data']['data']['total_followers'] ?? 0 ?></p>
</div>

<!-- ENGAGEMENT RATE -->
<div class="card">
    <h3>Engagement Rate</h3>
    <p>
        <?= round(($engagementRate['data']['data']['total_engagement_rate'] ?? 0) * 100, 2) ?>%
    </p>
</div>

<!-- POSTS -->
<div class="card">
    <h3>Posts Count</h3>
    <pre><?= htmlspecialchars(json_encode($postsCount['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<!-- POST METRICS -->
<div class="card">
    <h3>Post Metrics</h3>
    <pre><?= htmlspecialchars(json_encode($postMetrics['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<!-- ===================================================== -->
<!-- 🔥 TOP POSTS (FIXED EMBEDS) -->
<!-- ===================================================== -->
<div class="card">
    <h3>🔥 Top Posts</h3>

    <?php if ($topPosts['http_code'] === 200 && !empty($topPosts['data']['data'])): ?>

        <div class="row">

            <?php foreach ($topPosts['data']['data'] as $post): ?>

                <?php
                    $url = $post['permalink'] ?? $post['url'] ?? '';
                    $engagement = $post['engagement'] ?? 0;
                    $embed = renderPostEmbed($url);
                ?>

                <div class="col-md-6 mb-3">
                    <div class="card">

                        <div style="font-weight:bold; margin-bottom:10px;">
                            Engagement: <?= number_format($engagement) ?>
                        </div>

                        <div>
                            <?= $embed ?>
                        </div>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>
        <p class="text-warning">No top posts found.</p>
        <pre><?= htmlspecialchars($topPosts['raw'] ?? '') ?></pre>
    <?php endif; ?>
</div>

<!-- USER STATS -->
<div class="card">
    <h3>User Stats</h3>
    <pre><?= htmlspecialchars(json_encode($userStats['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

</div>

</body>
</html>