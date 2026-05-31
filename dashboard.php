<?php
session_start();

if (!isset($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_SESSION['token'];

/**
 * -----------------------------
 * AJAX: FETCH ACCOUNTS
 * -----------------------------
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch'
) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action']) && $input['action'] === 'fetch') {

        $ch = curl_init('https://socialbu.com/api/v1/accounts');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        echo $response;
        exit;
    }

    echo json_encode(['error' => 'Invalid request']);
    exit;
}

/**
 * -----------------------------
 * HELPER: SOCIALBU API CALL
 * -----------------------------
 */
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

/**
 * -----------------------------
 * RANGE FILTER (7 / 30 / all)
 * -----------------------------
 */
$range = $_GET['range'] ?? '89';

if ($range === 'all') {
    $startDate = '2000-01-01'; // fallback for full history
} else {
    $startDate = date('Y-m-d', strtotime("-{$range} days"));
}

$endDate = date('Y-m-d');

/**
 * -----------------------------
 * API CALLS
 * -----------------------------
 */

// Followers per account
$followers = socialbu_get('insights/accounts/followers');

// Followers growth (dynamic range)
$followersGrowth = socialbu_get('insights/accounts/followers/growth', [
    'start' => $startDate,
    'end' => $endDate
]);

// Inbox stats
$openConvos = socialbu_get('insights/inbox/unread-count');

// Engagement rate
$engagementRate = socialbu_get('insights/accounts/engagement/rate');

// User stats
$userStats = socialbu_get('insights/stats');

/**
 * -----------------------------
 * ACCOUNT IDS (SAFE HANDLING)
 * -----------------------------
 */
$followersByAccount = [];

$followersData = $followers['data']['data']['followers_by_account'] ?? [];

foreach ($followersData as $acc) {
    $followersByAccount[$acc['account_id']] = $acc['followers'] ?? 0;
}

$allAccountIds = array_keys($followersByAccount);

/**
 * -----------------------------
 * BUILD QUERY STRING
 * -----------------------------
 */
$accountsQuery = [];

foreach ($allAccountIds as $id) {
    $accountsQuery[] = "accounts[]=" . urlencode($id);
}

$accountsQueryString = implode('&', $accountsQuery);

/**
 * -----------------------------
 * POSTS / METRICS (RANGE BASED)
 * -----------------------------
 */
$postsCount = socialbu_get(
    "insights/posts/counts?$accountsQueryString&start=$startDate&end=$endDate"
);

$postMetrics = socialbu_get(
    "insights/posts/metrics?$accountsQueryString&start=$startDate&end=$endDate&metrics=likes,comments&post_type=all"
);

// ============================================
// ✅ ENGAGEMENT TREND
// ============================================
$engagementTrend = socialbu_get('insights/accounts/engagement/trend', [
    'start' => $startDate,
    'end' => $endDate
]);
function fetchPosts($token, $type = 'published') {

    $allPosts = [];
    $page = 1;
    $perPage = 8;
    $maxPosts = 8; // HARD LIMIT

    while (true) {

        $url = "https://socialbu.com/api/v1/posts?" . http_build_query([
            'type' => $type,
            'page' => $page,
            'perPage' => $perPage
        ]);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Accept: application/json"
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            break;
        }

        $data = json_decode($response, true);

        $items =
            $data['items'] ??
            $data['data']['items'] ??
            $data['data'] ??
            [];

        if (!is_array($items) || empty($items)) {
            break;
        }

        foreach ($items as $item) {
            $allPosts[] = $item;

            // 🔥 HARD STOP
            if (count($allPosts) >= $maxPosts) {
                return array_slice($allPosts, 0, $maxPosts);
            }
        }

        if (count($items) < $perPage) {
            break;
        }

        $page++;
    }

    return array_slice($allPosts, 0, $maxPosts);
}

$posts = fetchPosts($token, 'published');

usort($posts, function($a, $b) {
    $pa = !empty($a['publish_at']) ? strtotime($a['publish_at']) : 0;
    $pb = !empty($b['publish_at']) ? strtotime($b['publish_at']) : 0;
    return $pb <=> $pa; // newest first
});
// ===============================
// LAST 7 DAYS WINDOW
// ===============================
$start7 = date('Y-m-d', strtotime('-7 days'));
$end7 = date('Y-m-d');

// ===============================
// ENGAGEMENT TOTAL (7 DAYS)
// ===============================
$engagementData = $engagementTrend['data']['data'] ?? [];

$totalEngagement7 = 0;

foreach ($engagementData as $row) {
    $date = $row['date'] ?? null;
    if ($date && $date >= $start7 && $date <= $end7) {
        $totalEngagement7 += (int)($row['engagements'] ?? 0);
    }
}

// ===============================
// POSTS TOTAL (7 DAYS)
// ===============================
$postsData = $postsCount['data']['data'] ?? [];

$totalPosts7 = 0;

foreach ($postsData as $row) {
    $date = $row['date'] ?? null;
    if ($date && $date >= $start7 && $date <= $end7) {
        $totalPosts7 += (int)($row['count'] ?? 0);
    }
}

// ===============================
// ENGAGEMENT RATE (7 DAYS)
// ===============================
$totalEngagementRate7 = 0;

if (!empty($engagementRate['data']['data']['total_engagement_rate'])) {
    $totalEngagementRate7 = $engagementRate['data']['data']['total_engagement_rate'] * 100;
}
?>
<?php

// ===============================
// CURRENT WEEK (already exists)
// ===============================
$currentPosts = $totalPosts7;
$currentEngagement = $totalEngagement7;
$currentRate = $totalEngagementRate7;

// ===============================
// PREVIOUS WEEK DATA
// (Assumes you have daily arrays like posts/engagement trend)
// ===============================

// Helper to sum last N days excluding last X days
function sumRange($data, $startOffset, $length, $key) {
    $slice = array_slice($data, -($startOffset + $length), $length);
    return array_reduce($slice, function($sum, $row) use ($key) {
        return $sum + (float)($row[$key] ?? 0);
    }, 0);
}

// Example datasets (adjust keys if needed)
$postsData = $postsCount['data']['data'] ?? [];
$engagementData = $engagementTrend['data']['data'] ?? [];

// ===============================
// PREVIOUS WEEK (7–14 days ago)
// ===============================
$prevPosts = sumRange($postsData, 7, 7, 'count');
$prevEngagement = sumRange($engagementData, 7, 7, 'engagements');

// Engagement rate = engagement / followers (adjust if needed)
$prevRate = $prevPosts > 0 ? ($prevEngagement / $prevPosts) : 0;

// ===============================
// % CHANGE FUNCTION
// ===============================
function percentChange($current, $previous) {
    if ($previous == 0) return 0;
    return (($current - $previous) / $previous) * 100;
}

$postsChange = percentChange($currentPosts, $prevPosts);
$engagementChange = percentChange($currentEngagement, $prevEngagement);
$rateChange = percentChange($currentRate, $prevRate);

// =====================================================
// QUICK FETCH (FOR OEMBED)
// =====================================================
function quickFetch($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

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

$topPostsClean = [];

if (!empty($topPosts['data']['data'])) {

    foreach ($topPosts['data']['data'] as $post) {
        $url = $post['permalink'] ?? $post['url'] ?? '';
        $content = trim($post['content'] ?? '');

        if ($content === '') {
            $content = '[No content available]';
        }

        $topPostsClean[] = [
            'content' => $content,
            'engagement' => $post['engagement'] ?? 0,
            'url' => $url
        ];
    }
}


$aiPayload = [
    "summary" => [
        "posts_last_7_days" => $currentPosts,
        "posts_prev_7_days" => $prevPosts,
        "posts_change_pct" => $postsChange,

        "engagement_last_7_days" => $currentEngagement,
        "engagement_prev_7_days" => $prevEngagement,
        "engagement_change_pct" => $engagementChange,

        "engagement_rate" => $currentRate,
        "engagement_rate_prev" => $prevRate,
        "engagement_rate_change_pct" => $rateChange,
    ],

    "followers" => [
        "total_followers" => $followers['data']['data']['total_followers'] ?? 0,
        "by_account" => $followersByAccount,
        "growth" => $followersGrowth['data']['data'] ?? []
    ],

    "platform_distribution" => [], // will fill in JS
    "posts" => $postsCount['data']['data'] ?? [],
    "engagement_trend" => $engagementTrend['data']['data'] ?? [],

    "top_posts" => $topPostsClean
];
?>
<?php

// ============================================
// BUILD HISTORICAL ARRAYS FOR AI
// ============================================

$engagementHistory = [];
$postsHistory = [];
$followersHistory = [];

// ============================================
// ENGAGEMENT HISTORY
// ============================================

foreach (($engagementTrend['data']['data'] ?? []) as $row) {

    $engagementHistory[] = [
        "date" => $row['date'] ?? null,
        "engagement" => (int)($row['engagements'] ?? 0)
    ];
}

// ============================================
// POSTS HISTORY
// ============================================

foreach (($postsCount['data']['data'] ?? []) as $row) {

    $postsHistory[] = [
        "date" => $row['date'] ?? null,
        "posts" => (int)($row['count'] ?? 0)
    ];
}

// ============================================
// FOLLOWERS HISTORY
// ============================================

foreach (($followersGrowth['data']['data'] ?? []) as $row) {

    $followersHistory[] = [
        "date" => $row['date'] ?? null,
        "followers" => (int)($row['followers'] ?? 0)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SocialBu Account Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f8;
    color: #333;
    margin: 0;
}
h1 {
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 0px;
}
.dashboard {
    
}
button, select {
    display: block;
    width: 100%;
    margin: 10px 0;
    padding: 10px;
    font-size: 16px;
}
button {
    background: #007bff;
    border: none;
    color: white;
    border-radius: 5px;
    cursor: pointer;
}
button:hover { background: #0056b3; }
pre { background: #04a3ce; color: white; padding: 0px 25px; border-radius: 0px; margin: 0px;font-family: 'Lato', sans-serif;}
#accounts {
  display: grid;
  grid-template-columns: repeat(3, 1fr); /* exactly 3 per row */
  gap: 20px;
}

@media (max-width: 1024px) {
  #accounts {
    grid-template-columns: repeat(2, 1fr); /* 2 per row on smaller screens */
  }
}

@media (max-width: 768px) {
  #accounts {
    grid-template-columns: 1fr; /* 1 per row on mobile */
  }
}


.account {
  background: #f3f4f6;
  border-radius: 12px;
  padding: 15px;
  
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  justify-content: space-between;
}

.account img{
    width: 60px;
    height: 60px;
}

.actions{
    flex-direction: column;
}

.actions button{
    display: inline-block;
    width: auto;
    margin-right: 5px;
    background: #28a745;
    transition: transform 0.15s ease-in-out;
}

.actions button.delete { background: #dc3545; }
.actions button.update { background: #ffc107; color: #000; }
.ui-container{
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #9a97a7;
}

.ui-container{
}

.actions i:hover{
    transform: scale(1.1);
    color:#04a3ce;
    cursor: pointer;
}

.container{
    padding: 0px 1.5rem!important
}

input:focus,
select:focus,
button:focus {
    outline: none !important;
    box-shadow: none !important;
}

input, select, button, span{
    border: none !important;
}

#accountSearch, #accountFilter, #accountSearch::placeholder{
    font-size: 17px;
    color: #9a97a7;
    margin: 0;
}

#accountSearch{
    width: 50% !important;
}

#accountFilter{
    width: 25% !important;
}

.add-account{
    width: 25% !important;
    margin: 0;
}

button.add-account{
    background: #04a3ce !important;
    color: white;
    cursor: pointer;
    transition: 0.15s ease-in-out;
}

button.add-account:hover {
    background: #1b78aeff !important;
    transform: scale(1.05);
}

select{
    font-size: 17px !important;
    color: #9a97a7 !important;
    margin: 0;
    background-color: #f3f4f6 !important;
}
#followersTableBody tr:first-child {
    border-bottom: 2px solid #e5e7eb;
}

#followersTableBody tr:hover {
    background-color: #f9fafb;
}

#followersTableBody tr td {
    border-left: none !important;
    border-right: none !important;
    border-top: 1px solid #c5c7cc !important;
    border-bottom: 1px solid #c5c7cc !important;
}

#followersTableBody tr {
    background: #fff;
    border-radius: 6px;
}
.pie-wrapper {
    height: 320px;   /* 🔥 FORCE SAME HEIGHT */
    width: 320px;
    position: relative;
}

.pie-wrapper canvas {
    width: 100% !important;
    height: 100% !important;
}

.metric-change {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    margin-top: 6px;
}

.metric-up {
    background-color: rgba(23,129,62,0.15);
    color: #19773c;
}

.metric-down {
    background-color: rgba(239,68,68,0.15);
    color: #b41c1c;
}
.horizontal-scroll {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 10px;
    margin-top: 6px;

    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
}

/* EACH ITEM */
.post-card {
    flex: 0 0 350px;
    scroll-snap-align: start;
}

/* optional styling */
.post-card .ui-container {
    height: 100%;
}

/* scrollbar */
.horizontal-scroll::-webkit-scrollbar {
    height: 8px;
}

.horizontal-scroll::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 10px;
}

.horizontal-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.twitter-tweet-rendered{
    margin: 0px !important;
}
.horizontal-scroll .ui-container{
    border-radius: 12px;
    margin: 0px !important;
}
#ai-loading:first-of-type {
    display: none !important;
}
.chat-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 550px;
    overflow-y: auto;
    padding: 10px;
    background: #f0f2f6;
    height: 550px;
}

.msg-row {
    display: flex;
    width: 100%;
}

.msg {
    max-width: 70%;
    padding: 0px 14px;
    border-radius: 12px;
    white-space: pre-wrap;
    font-size: 14px;
}

.msg.user {
    margin-left: auto;
    background: #04a3ce;
    color: white;
    border-bottom-right-radius: 4px;
    padding: 10px 14px;
}

.msg.ai {
    margin-right: auto;
    background: white;
    color: #111;
}

textarea:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: inherit !important;
}

.Options button{
    background-color: transparent !important;
    border: 2px #8e9093 solid !important;
    color: #8e9093 !important;
    transition: 0.25s;
}

.Options button:hover{
    transform: scale(1.0) !important;
    background-color: transparent !important;
    color: #04a3ce !important;
    border: 2px #04a3ce solid !important;
}

.analysis-btn{
    white-space: nowrap;
}

.pretty-ai-response {
    line-height: 1.5;
    font-size: 0.95rem;
    white-space: normal;
    padding-top: 8px;
    padding-bottom: 8px;
}

/* HEADINGS */
.pretty-ai-response h1,
.pretty-ai-response h2,
.pretty-ai-response h3,
.pretty-ai-response h4 {
    margin-top: 16px;
    margin-bottom: 8px;
    font-weight: 600;
    line-height: 1.3;
}

/* PARAGRAPHS */
.pretty-ai-response p {
    margin: 6px 0;
}

/* LISTS */
.pretty-ai-response ul,
.pretty-ai-response ol {
    margin: 6px 0 10px 18px;
    padding-left: 16px;
}

/* LIST ITEMS */
.pretty-ai-response li {
    margin-bottom: 6px;
}

/* STRONG/BOLD */
.pretty-ai-response b,
.pretty-ai-response strong {
    font-weight: 600;
}

/* OPTIONAL CARDS */
.pretty-ai-response .ai-section {
    padding: 12px 14px;
    border-radius: 10px;
    background: rgba(255,255,255,0.03);
    margin-bottom: 12px;
}

.quick-actions button{
    margin: 5px 0;
    color: #04a3ce;
    transition: 0.15s;
    border: solid 2px #04a3ce !important;
    background-color: transparent !important;
}

.quick-actions button:hover{
    font-weight: 600;
}

.btn-close-white{
    background-color: transparent !important;
    color: white !important;
}

button .btn-close-white:hover{
    background-color: transparent !important;
    color: white !important;
}
.btn.btn-primary{
    transition: 0.15s;
}
.create-post{
    transition: 0.15s;
}
.create-post:hover{
    background: linear-gradient(to right, #007595 0%, #007595 100%) !important;
    border-color: #007595 !important;
    transform: scale(1.05);
    transition: 0.15s;
}
.card.ui-container:has(.twitter-tweet-rendered),
.card.ui-container:has(.twitter-tweet) {
    padding: 0 !important;
}
iframe{
    min-height: 605px !important;
}
</style>

</head>
<body>
    <div class="wrapper">
        <div class="d-flex">
            <?php
                include 'sidebar.php';
            ?>
            <div style="width:100%;">
                <!-- <pre id="output"></pre> -->
                <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white; margin-bottom:20px;">
                    <h1 class="mb-0">Dashboard</h1>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle signout" type="button" data-bs-toggle="dropdown"
                            style="background:none;color:#312b2f;font-weight:bold;margin:0!important;">
                            <i class="fas fa-user" style="padding-right:6px;"></i>
                            <?= htmlspecialchars($_SESSION['user_email'] ?? 'Account') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item text-danger signout_dropdown" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> <b>Logout</b>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="dashboard container container-fluid" id="dashboard">
                        <div style="display: flex; justify-content: space-around; justify-content: space-around;  margin: 0px -9px;">
                           <div class="ui-container"  style="width: 23.5%; display: flex; flex-direction: column; height: 165px; justify-content: space-between; color: white; background-color: #eec54c;">
                                <h5 style="margin-bottom: 20px;">Posts (Last 7 Days)</h5>
                                <h3 style="color:black; font-size:45px; font-weight:bold; top: 22px; position: relative; color: white;">
                                    <?= number_format($currentPosts) ?>
                                </h3>
                                <i class="fas fa-sticky-note" style="padding-right: 10px;  font-size: 18px; bottom: 10px; left: 170px; z-index: 100; bottom: 75px; font-size: 90px; position: relative; color: #db9f41;"></i>
                            </div>
                            <div class="ui-container" style="width: 23.5%; display: flex; flex-direction: column; height: 165px; justify-content: space-between; color: white; background-color: #ee4c4d">
                                <h5 style="margin-bottom: 20px;">Total Engagement (Last 7 Days)</h5>
                                <h3 style="color:black; font-size:45px; font-weight:bold; color: white;">
                                    <?= number_format($currentEngagement) ?>
                                </h3>
                                <i class="fas fa-thumbs-up" style="padding-right: 10px; font-size: 18px; bottom: 10px; left: 165px; z-index: 100; bottom: 94px; font-size: 85px; position: relative; color: #ce1d3e;"></i>
                            </div>
                            <div class="ui-container"  style="width: 23.5%; display: flex; flex-direction: column; height: 165px; justify-content: space-between; color: white; background-color: #60c370">
                                <h5 style="margin-bottom: 20px;">Engagement Rate (Last 7 Days)</h5>
                                <h3 style="color:black; font-size:45px; font-weight:bold; color: white;">
                                    <?= number_format($currentRate, 2) ?>%
                                </h3>
                                <i class="fas fa-percent" style="padding-right: 10px; font-size: 18px; bottom: 10px; left: 180px; z-index: 100; bottom: 95px; font-size: 80px; position: relative; color: #449d5d;"></i>
                            </div>
                            <div class="ui-container"  style="width: 23.5%; display: flex; flex-direction: column; height: 165px; justify-content: space-between; color: white; background-color: #04a3ce; overflow: hidden;">
                                <h5 style="margin-bottom: 20px;">Total Followers</h5>
                                <h3 style="color:black; font-size:45px; font-weight:bold; top: 22px; position: relative; color: white; z-index: 200;">
                                    <?= number_format($followers['data']['data']['total_followers']) ?>
                                </h3>
                                <i class="fas fas fa-users" style="padding-right: 10px; font-size: 18px; bottom: 10px; left: 140px; z-index: 100; bottom: 75px; font-size: 90px; position: relative; color: #0b7eb1;"></i>
                            </div>
                        </div>
                        <div class="ui-container">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5><i class="fas fa-chart-line" style="padding-right: 10px; font-size: 18px;"></i>Follower Growth</h5>
                                <input type="hidden" id="metricMode" value="total">
                                <input type="hidden" id="timeRange" value="7">
                            </div>
                            <canvas id="followersGrowthChart" height="100"></canvas>
                        </div>
                        <?php
                            $posts = fetchPosts($token, 'published');
                        ?>

                        <div class="ui-container" style="max-width: 1220px;">
                            <div style="display:flex; justify-content: space-between;">
                                <h5>
                                    <i class="fas fa-history" style="padding-right: 10px;"></i>
                                    Recent Posts
                                </h5>
                                <div>
                                    <a href="post-all.php" class="add-account btn btn-primary w-100 create-post" style="width: 100% !important;">
                                        <b style="padding-right: 10px;">See all</b><i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="horizontal-scroll">

                                <?php if (!empty($posts)): ?>

                                    <?php foreach ($posts as $post): ?>

                                        <?php
                                            $url =
                                                $post['permalink'] ??
                                                $post['url'] ??
                                                $post['link'] ??
                                                '';

                                            $content =
                                                trim($post['content'] ??
                                                $post['text'] ??
                                                $post['message'] ??
                                                '');

                                            if ($content === '') {
                                                $content = '[No content available]';
                                            }

                                            $embed = $url ? renderPostEmbed($url) : null;
                                        ?>

                                        <div class="post-card">
                                            <div class="card ui-container" style="padding:12px; min-height:120px;">

                                                <?php if ($embed): ?>
                                                    <?= $embed ?>
                                                <?php else: ?>
                                                    <p style="margin:0; word-break:break-word;">
                                                        <?= htmlspecialchars($content) ?>
                                                    </p>
                                                <?php endif; ?>

                                            </div>
                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <div class="post-card">
                                        <div class="ui-container">
                                            <p class="text-warning mb-0">
                                                No recent posts found (API returned empty dataset).
                                            </p>
                                        </div>
                                    </div>

                                <?php endif; ?>

                            </div>
                        </div>


                        <div class="d-flex"  style="display: none !important;">
                            <div class="dashboard container container-fluid" id="dashboard">
                                
                                <div style="display: flex; justify-content: space-between;">
                                    <div class="ui-container" style="width: 48.5%;">
                                        <h5><i class="fas fa-chart-pie" style="padding-right: 10px; font-size: 18px;"></i> Follower Distribution by Account/Platform</h5>
                                        <div style="display:flex;">
                                            <div class="pie-wrapper">
                                                <canvas id="followersPieChart"></canvas>
                                            </div>

                                            <div class="pie-wrapper">
                                                <canvas id="platformPieChart"></canvas>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ui-container" style="width: 50%;">
                                        <div>
                                            <h5><i class="fas fa-table" style="padding-right: 10px; font-size: 18px;"></i>Daily Growth across Accounts</h5>

                                            <table class="table" style="margin-top: 20px;">
                                                <tbody id="followersTableBody"></tbody>
                                            </table>
                                        </div>
                                        <script>
                                        const followersGrowthData = <?= json_encode($followersGrowth['data']['data'] ?? []) ?>;
                                        </script>
                                        <!-- <pre><?php print_r($followersGrowth); ?></pre> -->
                                    </div>
                                </div>                
                            </div>
                        </div>


                    </div>                
                </div>
            </div>
        </div>
    </div>
</div>

</body>
<script>
    function renderPlatformPieChart() {

    if (!Object.keys(platformFollowersMap).length) return;

    const ctx = document.getElementById("platformPieChart").getContext("2d");

    const labels = Object.keys(platformFollowersMap);
    const values = Object.values(platformFollowersMap);

    if (platformPieChart) platformPieChart.destroy();

    const total = values.reduce((a, b) => a + b, 0);
    const maxIndex = values.indexOf(Math.max(...values));
    const maxLabel = labels[maxIndex] || "Top";
    const maxPercent = total ? ((values[maxIndex] / total) * 100).toFixed(2) : 0;

    const centerTextPlugin = {
        id: "centerText",
        beforeDraw(chart) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return;

            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;

            ctx.save();
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";

            ctx.font = "bold 18px Poppins";
            ctx.fillStyle = "#111";
            ctx.fillText(`${maxPercent}%`, x, y - 10);

            ctx.font = "12px Poppins";
            ctx.fillStyle = "#666";
            ctx.fillText(maxLabel, x, y + 12);

            ctx.restore();
        }
    };

    platformPieChart = new Chart(ctx, {
        type: "doughnut",
        plugins: [centerTextPlugin],

        data: {
            labels,
            datasets: [{
                data: values,
                cutout: "70%",
                radius: "80%",
                backgroundColor: [
                    "#a855f7",
                    "#1da1f2",
                    "#e1306c",
                    "#6366f1",
                    "#22c55e",
                    "#f97316"
                ]
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,
            aspectRatio: 1,

            layout: {
                padding: 10
            },

            plugins: {
                legend: {
                    position: "bottom",
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                },

                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const val = ctx.raw;
                            const percent = total ? ((val / total) * 100).toFixed(2) : 0;
                            return `${ctx.label}: ${val.toLocaleString()} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}
</script>
<script>
const followersByAccountData = <?= json_encode($followersByAccount) ?>;
let platformPieChart = null;
let platformFollowersMap = {};

function formatFullDate(dateStr) {
    const d = new Date(dateStr);

    const day = d.getDate();
    const month = d.toLocaleDateString("en-US", { month: "long" });
    const year = d.getFullYear();

    const suffix =
        day % 10 === 1 && day !== 11 ? "st" :
        day % 10 === 2 && day !== 12 ? "nd" :
        day % 10 === 3 && day !== 13 ? "rd" : "th";

    return `${day}${suffix} ${month} ${year}`;
}
</script>
<script>
function renderFollowersTable() {

    if (!followersGrowthData || followersGrowthData.length === 0) return;

    const tableBody = document.getElementById("followersTableBody");
    tableBody.innerHTML = "";

    const currentTotal = <?= $followers['data']['data']['total_followers'] ?? 0 ?>;

    // ============================================
    // SORT DATA (OLDEST -> NEWEST)
    // ============================================
    const sortedData = [...followersGrowthData].sort((a, b) => {
        return new Date(a.date) - new Date(b.date);
    });

    // ============================================
    // LAST 7 DAYS (NEWEST -> OLDEST FOR DISPLAY)
    // ============================================
    const last7 = sortedData.slice(-7).reverse();

    // ============================================
    // REBUILD TOTALS
    // ============================================
    let runningTotal = currentTotal;

    const totals = last7.map(row => {

        const total = runningTotal;

        runningTotal -= Number(row.total_followers) || 0;

        return total;
    });

    // ============================================
    // SUMMARY
    // ============================================
    const totalNet = last7.reduce((sum, row) => {
        return sum + (Number(row.total_followers) || 0);
    }, 0);

    const latestTotal = currentTotal;

    // ============================================
    // HEADER + SUMMARY ROW
    // ============================================
    const summaryRow = document.createElement("tr");

    summaryRow.style.background = "#e5e7eb";
    summaryRow.style.fontWeight = "bold";
    summaryRow.style.border = "1px solid #d1d5db";

    summaryRow.innerHTML = `
        <td style="padding: 5px 10px; background-color: #f3f4f6;">
            <div style="font-size:12px; color:#555;">Followers across</div>
            <div style="font-size:22px;">Last 7 Days</div>
        </td>

        <td style="padding: 5px 10px; text-align:center; background-color: #f3f4f6;">
            <div style="font-size:12px; color:#555;">Total Followers</div>
            <div style="font-size:22px;">
                ${latestTotal.toLocaleString()}
            </div>
        </td>

        <td style="padding: 5px 10px; text-align:center; background-color: #f3f4f6;">
            <div style="font-size:12px; color:#555;">Net Change</div>

            <div style="
                font-size:22px;
                color:${totalNet >= 0 ? 'green' : 'red'};
            ">
                ${totalNet >= 0 ? '+' : ''}
                ${totalNet.toLocaleString()}
            </div>
        </td>
    `;

    tableBody.appendChild(summaryRow);

    // ============================================
    // DAILY ROWS
    // ============================================
    last7.forEach((row, index) => {

        const date = formatFullDate(row.date);

        const net = Number(row.total_followers) || 0;

        const total = totals[index];

        const tr = document.createElement("tr");

        tr.style.background = "#ffffff";
        tr.style.border = "1px solid #e5e7eb";

        tr.innerHTML = `
            <td style="
                padding:6px 10px;
                color:#6d6874;
                font-size:15px;
            ">
                ${date}
            </td>

            <td style="
                padding:6px 10px;
                color:#6d6874;
                text-align:center;
                font-size:15px;
            ">
                ${total.toLocaleString()}
            </td>

            <td style="
                padding:6px 10px;
                text-align:center;
                color:${net >= 0 ? 'green' : 'red'};
                font-size:15px;
            ">
                ${net >= 0 ? '+' : ''}
                ${net.toLocaleString()}
            </td>
        `;

        tableBody.appendChild(tr);
    });
}
</script>
<script>
let followersPieChart = null;

function renderFollowersPieChart() {

    if (!followersByAccountData || Object.keys(followersByAccountData).length === 0) return;

    const ctx = document.getElementById("followersPieChart").getContext("2d");

    const labels = [];
    const values = [];

    Object.entries(followersByAccountData).forEach(([id, count]) => {
        labels.push(accountNamesMap[id] || `Account ${id}`);
        values.push(Number(count) || 0);
    });

    const total = values.reduce((a, b) => a + b, 0);

    if (followersPieChart) followersPieChart.destroy();

    const centerTextPlugin = {
        id: "centerText",
        beforeDraw(chart) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return;

            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;

            ctx.save();
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";

            // ✅ TOTAL FOLLOWERS (FIXED AS REQUESTED)
            ctx.font = "bold 18px Poppins";
            ctx.fillStyle = "#111";
            ctx.fillText(total.toLocaleString(), x, y - 10);

            ctx.font = "12px Poppins";
            ctx.fillStyle = "#666";
            ctx.fillText("Followers", x, y + 12);

            ctx.restore();
        }
    };

    followersPieChart = new Chart(ctx, {
        type: "doughnut",
        plugins: [centerTextPlugin],

        data: {
            labels,
            datasets: [{
                data: values,
                cutout: "70%",
                radius: "80%",
                backgroundColor: [
                    "#04a3ce",
                    "#a855f7",
                    "#f59e0b",
                    "#ef4444",
                    "#10b981",
                    "#3b82f6",
                    "#f97316"
                ]
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,
            aspectRatio: 1,

            layout: {
                padding: 10
            },

            plugins: {
                legend: {
                    position: "bottom",
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                },

                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const val = ctx.raw;
                            const percent = total ? ((val / total) * 100).toFixed(2) : 0;
                            return `${ctx.label}: ${val.toLocaleString()} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}
</script>
<script>
const engagementTrendData = <?= json_encode($engagementTrend['data']['data'] ?? []) ?>;

const postsData = <?= json_encode($postsCount['data']['data'] ?? []) ?>;

let engagementChart = null;

function renderEngagementTrendChart() {

    const type = document.getElementById("engagementType").value;
    const timeRange = document.getElementById("engagementRange").value;

    let sourceData = [];
    let labelText = "";
    let color = "";

    // =====================================================
    // SELECT DATASET
    // =====================================================
    if (type === "posts") {
        sourceData = postsData;
        labelText = "Posts Published";
        color = "#ef4444"; // 🔴 RED
    } else {
        sourceData = engagementTrendData;
        labelText = "Engagements";
        color = "#f59e0b"; // 🟡 YELLOW
    }

    if (!sourceData || sourceData.length === 0) {
        console.log("No data");
        return;
    }

    // =====================================================
    // SORT
    // =====================================================
    const sortedData = [...sourceData].sort((a, b) => {
        return new Date(a.date) - new Date(b.date);
    });

    const now = new Date();

    // =====================================================
    // FILTER RANGE
    // =====================================================
    const filteredData = sortedData.filter(row => {

        if (timeRange === "all") return true;

        const days = parseInt(timeRange);
        const rowDate = new Date(row.date);

        const diffDays = (now - rowDate) / (1000 * 60 * 60 * 24);

        return diffDays <= days;
    });

    // =====================================================
    // BUILD ARRAYS
    // =====================================================
    const labels = [];
    const values = [];

    filteredData.forEach(row => {

        const date = new Date(row.date);

        labels.push(date.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric"
        }));

        // posts vs engagement field handling
        if (type === "posts") {
            values.push(Number(row.count || row.posts || 0));
        } else {
            values.push(Number(row.engagements || 0));
        }
    });

    // =====================================================
    // DESTROY OLD CHART
    // =====================================================
    const ctx = document.getElementById("engagementTrendChart").getContext("2d");
    if (engagementChart) engagementChart.destroy();

    // =====================================================
    // CREATE CHART
    // =====================================================
    engagementChart = new Chart(ctx, {
        type: "line",

        data: {
            labels,
            datasets: [{
                label: labelText,
                data: values,

                tension: 0.4,
                borderWidth: 2,

                borderColor: color,
                pointBackgroundColor: color,
                pointBorderColor: color,

                fill: true,
                backgroundColor: color === "#ef4444"
                    ? "rgba(239,68,68,0.2)"
                    : "rgba(245,158,11,0.2)"
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: "index",
                intersect: false
            },

            plugins: {
                legend: {
                    position: "bottom"
                },

                tooltip: {
                    mode: "index",
                    intersect: false,
                    callbacks: {
                        title: (items) => `Date: ${items[0].label}`,
                        label: (item) =>
                            `${labelText}: ${item.parsed.y.toLocaleString()}`
                    }
                }
            },

            scales: {
                x: {
                    grid: { display: false }
                },

                y: {
                    grid: { display: true },
                    beginAtZero: true,
                    grace: "33%",
                    title: {
                        display: true,
                        text: labelText
                    }
                }
            }
        }
    });
}
</script>
<script>
let followersChart = null;

function renderFollowersGrowthChart() {

    if (!followersGrowthData || followersGrowthData.length === 0) {
        console.log("No growth data", followersGrowthData);
        return;
    }

    const metricMode = document.getElementById("metricMode").value;
    const timeRange = document.getElementById("timeRange").value;

    const labels = [];
    const gainedFollowers = [];
    const totalFollowers = [];

    const currentTotal = <?= $followers['data']['data']['total_followers'] ?? 0 ?>;

    // =====================================================
    // 1. SORT DATA BY DATE
    // =====================================================
    const sortedData = [...followersGrowthData].sort((a, b) => {
        return new Date(a.date) - new Date(b.date);
    });

    // =====================================================
    // 2. FIND FIRST VALID DATA POINT (> 0 followers)
    // =====================================================
    const firstValidIndex = sortedData.findIndex(r => {
        const val = Number(r.total_followers);
        return !isNaN(val) && val !== 0;
    });

    // =====================================================
    // FIND FIRST REAL "ACTIVATION START"
    // (skip ALL leading zeros, not just first match)
    // =====================================================

    let startIndex = 0;

    // find first point where followers becomes non-zero
    for (let i = 0; i < sortedData.length; i++) {
        const val = Number(sortedData[i].total_followers);

        if (!isNaN(val) && val > 0) {
            startIndex = i;
            break;
        }
    }

    // OPTIONAL: skip ONE extra point AFTER activation (your "2 days padding")
    startIndex = Math.max(0, startIndex + 1);

    const trimmedData = sortedData.slice(startIndex);

    // =====================================================
    // 3. APPLY RANGE FILTER
    // =====================================================
    const now = new Date();

    let filteredData = trimmedData.filter(row => {

        if (timeRange === "all") return true;

        const days = parseInt(timeRange);
        const rowDate = new Date(row.date);

        const diffDays = (now - rowDate) / (1000 * 60 * 60 * 24);

        if (timeRange === "7") return diffDays <= 7;
        if (timeRange === "30") return diffDays <= 30;

        return true;
    });

    // =====================================================
    // 4. BUILD ARRAYS
    // =====================================================
    filteredData.forEach(row => {
        labels.push(formatMonthDay(row.date)); // 👈 formatted label
        gainedFollowers.push(Number(row.total_followers));
    });

    // =====================================================
    // 5. REBUILD TOTAL FOLLOWERS HISTORY
    // =====================================================
    let runningTotal = currentTotal;

    for (let i = gainedFollowers.length - 1; i >= 0; i--) {
        runningTotal -= gainedFollowers[i];
        totalFollowers.unshift(runningTotal + gainedFollowers[i]);
    }

    const chartData = metricMode === "total"
        ? totalFollowers
        : gainedFollowers;

    const chartLabel = metricMode === "total"
        ? "Total Followers"
        : "Followers Gained / Lost";

    const color = metricMode === "total" ? "#04a3ce" : "#7c3aed";

    const ctx = document.getElementById("followersGrowthChart").getContext("2d");

    if (followersChart) followersChart.destroy();

    // =====================================================
    // HOVER LINE PLUGIN
    // =====================================================
    const verticalHoverLine = {
        id: "verticalHoverLine",
        afterDraw(chart) {
            const tooltip = chart.tooltip;

            if (!tooltip || !tooltip.getActiveElements().length) return;

            const ctx = chart.ctx;
            const active = tooltip.getActiveElements()[0];

            const x = active.element.x;
            const top = chart.scales.y.top;
            const bottom = chart.scales.y.bottom;

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x, top);
            ctx.lineTo(x, bottom);
            ctx.strokeStyle = "rgba(0,0,0,0.15)";
            ctx.lineWidth = 1;
            ctx.stroke();
            ctx.restore();
        }
    };

    followersChart = new Chart(ctx, {
        type: "line",
        plugins: [verticalHoverLine],

        data: {
            labels,
            datasets: [{
                label: chartLabel,
                data: chartData,

                tension: 0.4,
                borderWidth: 2,

                borderColor: color,
                pointBackgroundColor: color,
                pointBorderColor: color,

                fill: true,
                backgroundColor: color === "#04a3ce"
                    ? "rgba(4,163,206,0.2)"
                    : "rgba(124,58,237,0.2)"
            }]
        },

        options: {
            responsive: true,

            interaction: {
                mode: "index",
                intersect: false
            },

            plugins: {
                legend: {
                    position: "bottom"
                },

                tooltip: {
                    mode: "index",
                    intersect: false,
                    displayColors: false,

                    callbacks: {

                        // Date header
                        title: (items) => `Date: ${items[0].label}`,

                        // MAIN LINE VALUE
                        label: (item) => {
                            const value = item.parsed.y;

                            if (metricMode === "total") {
                                return `Total Followers: ${value.toLocaleString()}`;
                            }

                            return `Net Change: ${value.toLocaleString()}`;
                        },

                        // 🔥 EXTRA LINE FOR NET MODE ONLY
                        afterBody: (items) => {

                            if (metricMode === "total") return "";

                            const index = items[0].dataIndex;
                            const total = totalFollowers[index];

                            return `Total Followers: ${Number(total).toLocaleString()}`;
                        }
                    }
                }
            },

            scales: {
                x: {
                    title: {
                        display: true,
                        text: "Date"
                    },
                    grid: {
                        display: false // ❌ removes vertical lines
                    }
                },

                y: {
                    beginAtZero: false,
                    grace: "33%",
                    title: {
                        display: true,
                        text: chartLabel
                    },
                    grid: {
                        display: true // ✅ keep horizontal lines
                    }
                }
            }
        }
    });
}

function formatMonthDay(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric"
    });
}

const followersMap = <?= json_encode($followersByAccount) ?>;

// =============================
// LOAD ACCOUNTS
// =============================
async function loadAccounts() {

    const accountsDiv = document.getElementById('accounts');
    platformFollowersMap = {};
    accountsDiv.innerHTML = "Loading accounts...";

    try {
        const res = await fetch(window.location.href, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "fetch"
            },
            body: JSON.stringify({ action: "fetch" })
        });

        const data = await res.json();
        const accounts = Array.isArray(data) ? data : data.items || [];

        if (!accounts.length) {
            accountsDiv.innerHTML = "No accounts found.";
            return;
        }

        accountsDiv.innerHTML = "";

        accounts.forEach(acc => {

            const followers = followersMap[String(acc.id)] ?? followersMap[acc.id] ?? 0;

            const platform = (acc._type || acc.type || "unknown").toLowerCase();

            // ==============================
            // GROUP FOLLOWERS BY PLATFORM
            // ==============================
            if (!platformFollowersMap[platform]) {
                platformFollowersMap[platform] = 0;
            }
            platformFollowersMap[platform] += Number(followers);

            accountNamesMap[String(acc.id)] = acc.name || `Account ${acc.id}`;

            const div = document.createElement("div");
            div.className = "account";

            div.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${acc.image || 'https://socialbu.com/images/no-image.png'}"
                        width="50" height="50"
                        style="border-radius:50%; border:1px solid #ccc;">

                    <div>
                        <strong>${acc.name || "(Unnamed Account)"}</strong><br>
                        <small>${platform}</small><br>
                        <small><b>${followers.toLocaleString()}</b> Followers</small>
                    </div>
                </div>
            `;

            accountsDiv.appendChild(div);
        });
    } catch (error) {
        console.error(error);
        accountsDiv.innerHTML = "Error loading accounts.";
    }

    // =====================================
    // CONVERT IDS -> ACCOUNT NAMES
    // =====================================
    const namedFollowersMap = {};

    Object.entries(followersMap).forEach(([id, count]) => {

        const accountName =
            accountNamesMap[String(id)] ||
            `Account ${id}`;

        namedFollowersMap[accountName] = count;
    });

    // =====================================
    // SAVE TO AI METRICS
    // =====================================
    AI_METRICS.followers_by_account = namedFollowersMap;

    AI_METRICS.platform_distribution = platformFollowersMap;
}

// =============================
// INIT
// =============================
window.onload = async () => {
    renderFollowersGrowthChart();
    renderEngagementTrendChart();
    renderFollowersTable();
    renderPlatformPieChart();
    renderFollowersPieChart();
    sendToAI();
};

document.getElementById("metricMode").addEventListener("change", renderFollowersGrowthChart);
document.getElementById("timeRange").addEventListener("change", renderFollowersGrowthChart);
document.getElementById("engagementType").addEventListener("change", renderEngagementTrendChart);
document.getElementById("engagementRange").addEventListener("change", renderEngagementTrendChart);
</script>
</html>