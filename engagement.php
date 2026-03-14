<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];

// Helper function to call SocialBu API
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

    $data = json_decode($response, true);
    return ['http_code' => $httpCode, 'raw' => $response, 'data' => $data];
}

// 1️⃣ Total followers per account
$followers = socialbu_get('insights/accounts/followers');

// 2️⃣ Followers growth (last 7 days)
$startDate = date('Y-m-d', strtotime('-7 days'));
$endDate = date('Y-m-d');
$followersGrowth = socialbu_get('insights/accounts/followers/growth', [
    'start' => $startDate,
    'end' => $endDate
]);

// 3️⃣ Open conversations count
$openConvos = socialbu_get('insights/inbox/unread-count');

// 4️⃣ Posts count (last 7 days, all accounts)
$allAccountIds = array_column($followers['data']['followers_by_account'] ?? [], 'account_id');
// Build query for array
$accountsQuery = [];
foreach ($allAccountIds as $id) {
    $accountsQuery[] = "accounts[]=$id";
}
$accountsQueryString = implode('&', $accountsQuery);

// 5️⃣ Total engagement rate
$engagementRate = socialbu_get('insights/accounts/engagement/rate');

// 6️⃣ User stats
$userStats = socialbu_get('insights/stats');

// 7️⃣ Post metrics (last 7 days, all accounts)

// Posts count
$postsCount = socialbu_get("insights/posts/counts?$accountsQueryString&start=$startDate&end=$endDate");

// Post metrics
$postMetrics = socialbu_get("insights/posts/metrics?$accountsQueryString&start=$startDate&end=$endDate&metrics=likes,comments&post_type=all");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SocialBu Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: "Segoe UI", sans-serif; background: #f5f6fa; padding: 30px; }
.card { padding: 20px; border-radius: 8px; background: #fff; margin-bottom: 20px; }
pre { background: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
h1 { margin-bottom: 30px; }
</style>
</head>
<body>
<div class="container">
<h1>📊 SocialBu Insights Dashboard</h1>

<div class="card">
    <h3>Total Followers</h3>
    <?php if($followers['http_code'] === 200): ?>
        <p><strong>Total Followers:</strong> <?= $followers['data']['data']['total_followers'] ?? 0 ?></p>
        <h5>Followers by Account:</h5>
        <pre><?= htmlspecialchars(json_encode($followers['data']['data']['followers_by_account'], JSON_PRETTY_PRINT)) ?></pre>
    <?php else: ?>
        <p class="text-danger">Failed to fetch followers (HTTP <?= $followers['http_code'] ?>)</p>
        <pre><?= htmlspecialchars($followers['raw']) ?></pre>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Followers Growth (Last 7 Days)</h3>
    <pre><?= htmlspecialchars(json_encode($followersGrowth['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<div class="card">
    <h3>Open Conversations</h3>
    <p><strong>Open Messages:</strong> <?= $openConvos['data']['data']['open_msgs_count'] ?? 0 ?></p>
    <pre><?= htmlspecialchars(json_encode($openConvos['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<div class="card">
    <h3>Posts Count (Last 7 Days)</h3>
    <pre><?= htmlspecialchars(json_encode($postsCount['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<div class="card">
    <h3>Total Engagement Rate</h3>
    <p><strong>Rate:</strong> <?= round(($engagementRate['data']['data']['total_engagement_rate'] ?? 0)*100,2) ?>%</p>
    <pre><?= htmlspecialchars(json_encode($engagementRate['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<div class="card">
    <h3>User Stats</h3>
    <pre><?= htmlspecialchars(json_encode($userStats['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

<div class="card">
    <h3>Post Metrics (Last 7 Days)</h3>
    <pre><?= htmlspecialchars(json_encode($postMetrics['data'], JSON_PRETTY_PRINT)) ?></pre>
</div>

</div>
</body>
</html>