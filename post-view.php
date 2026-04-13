<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token  = $_SESSION['token'];
$postId = $_GET['id'] ?? null;

if (!$postId) {
    die("Missing post ID");
}

/* =====================================================
   HELPER FUNCTIONS
===================================================== */

function apiRequest($url, $token) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [$code, $response];
}


function utcToMalaysia($utcTime) {
    if (empty($utcTime)) return "Unknown";

    try {
        $dt = new DateTime($utcTime, new DateTimeZone("UTC"));
        $dt->setTimezone(new DateTimeZone("Asia/Kuala_Lumpur"));
        return $dt->format("Y-m-d g:i A");
    } catch (Exception $e) {
        return $utcTime;
    }
}


/* =====================================================
   FETCH POST
===================================================== */

list($httpCode, $response) =
    apiRequest("https://socialbu.com/api/v1/posts/" . intval($postId), $token);

if ($httpCode !== 200) {
    die("Failed to fetch post. HTTP $httpCode<br><pre>$response</pre>");
}

$post = json_decode($response, true);

if (!$post || empty($post['id'])) {
    die("Post not found in response");
}


$post = json_decode($response, true);

if (!$post || empty($post['id'])) {
    die("Post not found in response");
}

/* =====================================================
   🔥 ADD REPLIES FETCHING HERE
===================================================== */

$repliesData = [];

$url = $post['permalink'] ?? '';
$isMastodon = preg_match('/https?:\/\/([^\/]+)\/@[^\/]+\/(\d+)/', $url, $matches);

if ($isMastodon) {

    $domain = $matches[1];
    $statusId = $matches[2];

    $contextUrl = "https://{$domain}/api/v1/statuses/{$statusId}/context";

    function fetchJSON($url) {
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

    $context = fetchJSON($contextUrl);

    if (!empty($context['descendants'])) {
        foreach ($context['descendants'] as $reply) {
            $repliesData[] = [
                "author" => $reply['account']['display_name'] ?? 'Unknown',
                "handle" => $reply['account']['acct'] ?? '',
                "content" => strip_tags($reply['content'] ?? ''),
                "avatar" => $reply['account']['avatar'] ?? ''
            ];
        }
    }
}
/* =====================================================
   DETERMINE POST TYPE
===================================================== */

$postType = !empty($post['type'])
    ? strtolower($post['type'])
    : "text";


/* =====================================================
   NORMALIZE PLATFORM
===================================================== */

$platformRaw = strtolower($post['account_type'] ?? "unknown");

if (str_contains($platformRaw, "instagram")) {
    $platform = "instagram";
}
elseif (str_contains($platformRaw, "facebook")) {
    $platform = "facebook";
}
elseif (str_contains($platformRaw, "twitter")) {
    $platform = "twitter";
}
elseif (str_contains($platformRaw, "mastodon")) {
    $platform = "mastodon";
}
elseif (str_contains($platformRaw, "linkedin")) {
    $platform = "linkedin";
}
else {
    $platform = "unknown";
}


/* =====================================================
   DETERMINE METRICS
===================================================== */

switch ($platform) {

    case "instagram":
        $metrics = $postType === "video"
            ? "views,likes,comments,reach,saved,shares"
            : "likes,comments,reach,saved,shares";
        break;

    case "facebook":
        $metrics = $postType === "video"
            ? "reactions,comments,shares,impressions,post_clicks,video_views"
            : "reactions,comments,shares,impressions,post_clicks";
        break;

    case "twitter":
        $metrics = "retweets,likes";
        break;

    case "mastodon":
        $metrics = "replies,reblogs,favourites";
        break;

    case "linkedin":
        $metrics = "clicks,comments,engagement,impressions,likes,shares,unique_impressions,video_views";
        break;

    default:
        $metrics = "";
}


/* =====================================================
   DATE RANGE
===================================================== */

$start = date("Y-m-d", strtotime($post['publish_at'] ?? "now"));
$end   = date("Y-m-d");


/* =====================================================
   BUILD METRICS REQUEST
===================================================== */

$accountIds = [$post['account_id']];

$query = http_build_query([
    "accounts"  => $accountIds,
    "metrics"   => $metrics,
    "post_type" => $postType,
    "start"     => $start,
    "end"       => $end
]);


$metricsUrl = "https://socialbu.com/api/v1/insights/posts/metrics?$query";


/* =====================================================
   FETCH METRICS
===================================================== */

list($metricsCode, $metricsResponse) =
    apiRequest($metricsUrl, $token);

$metricsData = json_decode($metricsResponse, true);

/* =====================================================
   FETCH ACCOUNT INFO
===================================================== */

$accountId = $post['account_id'];

list($accountCode, $accountResponse) =
    apiRequest("https://socialbu.com/api/v1/accounts/$accountId", $token);

$accountName  = "Unknown Account";
$accountImage = "assets/default-avatar.png";

if ($accountCode === 200) {

    $accountData = json_decode($accountResponse, true);

    $accountName  = $accountData['name']  ?? $accountName;
    $accountImage = $accountData['image'] ?? $accountImage;
}

// Default to unknown
$postStatus = "unknown";

// If the API has a 'status' field
if (isset($post['status'])) {
    $postStatus = strtolower($post['status']); // published, draft, scheduled, etc.
} else {
    // If only 'draft' boolean exists
    if (!empty($post['draft'])) {
        $postStatus = "draft";
    } elseif (!empty($post['publish_at'])) {
        $publishTime = strtotime($post['publish_at']);
        $now = time();

        if ($publishTime > $now) {
            $postStatus = "scheduled";
        } else {
            $postStatus = "published";
        }
    }
}

// Determine status label and color
switch ($postStatus) {
    case 'draft':
        $statusLabel = 'Draft';
        $statusColor = '#b0b0b0'; // gray
        $textColor = 'white';
        break;

    case 'scheduled':
        $statusLabel = 'Scheduled';
        $statusColor = '#d19541 '; // orange/yellow
        $textColor = 'white';
        break;

    case 'published':
    default:
        $statusLabel = 'Published';
        $statusColor = '#47a55d'; // green
        $textColor = 'white';
        break;
}

/* =====================================================
   DEBUG OUTPUT
===================================================== */

// echo "<pre>DEBUG: Sending parameters:\n";

// print_r([
//     "accounts"  => $accountIds,
//     "metrics"   => $metrics,
//     "post_type" => $postType,
//     "start"     => $start,
//     "end"       => $end
// ]);

// echo "</pre>";

?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {

    $postId = intval($_POST['post_id']);

    $deleteUrl = "https://socialbu.com/api/v1/posts/" . $postId;

    $ch = curl_init($deleteUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => "DELETE",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $token,
            "Accept: application/json"
        ]
    ]);

    $response   = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $result = json_decode($response, true);

    if ($statusCode === 200 && !empty($result['success'])) {
        header("Location: post-all.php?deleted=1");
        exit;
    } else {
        $responseMessage = "Failed to delete post.<br>
        Status: $statusCode
        <pre>$response</pre>";
    }
}

$metricsTotals = [];

if (!empty($metricsData['data'])) {
    foreach ($metricsData['data'] as $metric => $entries) {

        $total = 0;

        foreach ($entries as $entry) {
            $total += intval($entry['value']);
        }

        $metricsTotals[$metric] = $total;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📋 My Posts</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    background: #f5f6fa;
    font-family: "Segoe UI", sans-serif;
}

.post-card {
    border-radius: 8px;
    padding: 15px;
    background: #f3f4f6;
    transition: 0.2s;
    word-break: break-word;
    overflow-wrap: anywhere;
    margin-bottom: 15px;
}

.post-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status {
    font-size: 0.9em;
}

.status.published {
    color: green;
}

.status.scheduled {
    color: #f0ad4e;
}

.status.draft {
    color: #9a97a7;
}

.small-muted {
    display:block;
    margin-top:6px;
}

h1 {
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 0px;
}

.actions button{
    display: inline-block;
    width: auto;
    margin-right: 5px;
    background: #28a745;
    transition: transform 0.15s ease-in-out;
}

.modal-button{
    width: auto;
    margin-right: 5px;
    background: #28a745;
    color: white;
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

.actions i:hover{
    transform: scale(1.1);
    color:#04a3ce;
    cursor: pointer;
}

.modal-button{
    height: 50px !important;
    margin: 0;
    font-size: 18px;
    transition: 0.15s;
}

.modal-button i{
    margin-right: 5px;
}

.modal-button:hover {
    transform: scale(1.05);
    cursor: pointer;
    color: white !important;
    background-color:#1b78aeff !important;
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

#statusSearch, #statusFilter, #statusSearch::placeholder{
    font-size: 17px;
    color: #9a97a7;
    margin: 0;
}

#statusSearch{
    width: 50% !important;
}

#statusFilter{
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

.modal-header{
    background-color: #04a3ce !important;
    justify-content: space-between;
    padding-top: 0px;
    padding-bottom: 0px;
}

.modal-close-btn{
    width: 10%;
}

.modal-close-btn i{
    transform: scale(1.3);
    cursor: pointer;
    transition: 0.15s;
}

.modal-close-btn:hover i{
    transform: scale(1.4);
    cursor: pointer;
}

.modal-title, .fa-times{
    color: white;
}

.post-card.hidden {
    display: none !important;
}
.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(48%, 1fr));
  gap: 15px;
}
.post-images {
    width: 220px;
    height: 220px;
    display: grid;
    gap: 6px;
    overflow: hidden;
}

.post-images div{
    background-color: white;
    border-radius: 6px;
    border: 1px solid #f3f4f6;
}

.post-images.single {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr;
}

.post-images.single div {
    width: 100%;
    height: 100%;
}

.post-images.multiple {
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(2, 1fr);
}

.post-images.multiple div {
    aspect-ratio: 1 / 1;  /* 🔥 forces square cells */
}

.post-images div {
    width: 100%;
    height: 100%;
    display: block;
}

.post-images img {
    width: 100%;
    height: 100%;
    object-fit: cover;        /* 🔥 crops instead of stretching */
    object-position: center;  /* center the crop */
    border-radius: 6px;
    border: 1px solid #f3f4f6;
    background: white;
}

.account-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-bottom: 20px; }
.account-item { display: flex; align-items: center; gap: 10px; background: #f3f4f6 !important; outline: none !important;
  padding: 8px 12px; border-radius: 8px; border: 0px solid #e0e0e0; cursor: pointer; transition: 0.2s; justify-content: space-between;}
.account-item:hover { background: #e9f5ff; }
.account-item img { width: 47px; height: 47px; border-radius: 50%; margin-right: 10px;}
footer { text-align: center; margin-top: 25px; color: #9a97a7; }
#aiOutput{
      height: 160px;

}


button#applyAI:hover{
    background-color: #1b78aeff !important;
}


.account-item.active {
    background-color: #69c6df !important;
    color: white;
}

.account-item.active small {
    color: #ffffff !important;
}

.account-item.active:hover {
    background-color: #1b78aeff !important;
}

.account-item:hover {
    background-color: #dfdee2 !important;
}


/* Masonry layout for Unsplash results */
#results {
    column-count: 4;
    column-gap: 15px;
}

.image-result {
    width: 100%;
    display: block;
    margin-bottom: 15px;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.2s;
}

.image-result:hover {
    transform: scale(1.05);
}

.image-result.selected {
    border: 5px solid #0d6efd;
}

/* Preview thumbnails */
.preview-thumb {
    width: 100px;
    border-radius: 10px;
}
h1 {
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 0px;
}

.actions button{
    display: inline-block;
    width: auto;
    margin-right: 5px;
    background: #28a745;
    transition: transform 0.15s ease-in-out;
}

.modal-button{
    width: auto;
    margin-right: 5px;
    background: #28a745;
    color: white;
    transition: transform 0.15s ease-in-out;
}

.actions button.delete { background: #dc3545; }
.actions button.update { background: #ffc107; color: #000; }
.ui-container{
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    color: #9a97a7;
    width: 50%;
    margin: 0px 10px 20px 10px;
}

.actions i:hover{
    transform: scale(1.1);
    color:#04a3ce;
    cursor: pointer;
}

.modal-button{
    height: 50px !important;
    margin: 0;
    font-size: 18px;
    transition: 0.15s;
}

.modal-button i{
    margin-right: 5px;
}

.modal-button:hover {
    transform: scale(1.05);
    cursor: pointer;
    color: white !important;
    background-color:#1b78aeff !important;
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

input, select, button, span, textarea{
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

.modal-header{
    background-color: #04a3ce !important;
    justify-content: space-between;
    padding-top: 0px;
    padding-bottom: 0px;
}

.modal-close-btn{
    width: 10%;
}

.modal-close-btn i{
    transform: scale(1.3);
    cursor: pointer;
    transition: 0.15s;
}

.modal-close-btn:hover i{
    transform: scale(1.4);
    cursor: pointer;
}

.modal-title, .fa-times{
    color: white;
}

.post-card.hidden {
    display: none !important;
}
.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(48%, 1fr));
  gap: 15px;
}
textarea, input{
    background: #f3f4f6 !important;
}
textarea{
    resize: none;
}
.form-label{
    font-size: 17px;
    font-weight: bold;
}
.account-checkbox {
    color: white;
    margin-left: auto;
    accent-color: #69c6df;
    width: 20px;      
    height: 20px;   
    transition: 0.2s;  
    border: 0 !important;
    outline: none !important;
}

.account-checkbox:hover {
    color: white;
    margin-left: auto;
    accent-color: #1b78aeff !important;
    width: 20px;      
    height: 20px;
    transition: 0.2s;
}

button{
    transition: 0.2s !important;
    color: white !important;
}

button[type="submit"]{
    font-size: 20px;
    font-weight:  bold;
    padding: 10px 0px;
    background: #da2929 !important;
}
button[type="submit"]:hover {
    transform: scale(1.02);
    background: #9d1414 !important;
}

.btn:hover {
    background-color: #1b78aeff !important;
}

.preview-wrapper {
    position: relative;
    display: inline-block;
    cursor: pointer;
}

.preview-thumb {
    width: 100px;
    height: 100px;
    border-radius: 10px;
    object-fit: cover;
    transition: 0.2s;
    display: block;
}

.preview-wrapper:hover .preview-thumb {
    filter: brightness(50%);
}

.remove-icon {
    position: absolute;
    top: 5px;
    right: 5px;
    color: white;
    font-weight: bold;
    font-size: 18px;
    opacity: 0;
    transition: opacity 0.2s;
    pointer-events: none; /* allows clicking on the image itself */
}

.preview-wrapper:hover .remove-icon {
    opacity: 1;
    pointer-events: auto; /* enable click */
}

label{
    color: #312b2f;
}

#aiImageResult img{
    transition: 0.2s;
}

#aiImageResult img:hover{
    transform: scale(1.03);
    filter: brightness(50%);
}

a.btn-outline-secondary{
    transition: 0.2s;
}

a.btn-outline-secondary:hover{
    transform: scale(1.02);
}

.chat-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 500px;
    overflow-y: auto;
    padding: 10px;
    background: #f0f2f6;
    height: 500px;
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
    border-bottom-left-radius: 4px;
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
</style>
</head>

<body>
    <div class="wrapper">
        <div class="d-flex">
            <?php
                include 'sidebar.php';
                $status = trim(strtolower($postStatus ?? 'unknown'));
            ?>
            <div style="width:100%;">
                <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white; margin-bottom:20px;">
                    <h1 class="mb-0">Post Overview</h1>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle signout"
                                type="button"
                                data-bs-toggle="dropdown"
                                style="background:none;color:#312b2f !important;font-weight:bold;margin:0!important;">

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
                <?php if (!empty($responseMessage)): ?>
                    <div style="width: 100%;">
                        <?= $responseMessage ?>
                    </div>
                <?php endif; ?>
                <div class="d-flex" style="width: 100%; margin-top: 20px;">
                    <form method="POST" enctype="multipart/form-data" style="width: 100%;">
                        <div class="newpost container container-fluid" id="newpost">
                            <div style="display: flex; justify-content: center;" class="container">
                                <div style="width: 53%; margin: 0px 20px 15px 10px; color: #44424d;">
                                    <a href="dashboard.php">Posts</a> >
                                    <a href="post-all.php">Post List</a> >
                                    <a style="color: #04a3ce !important; font-weight: bold;">Post Overview</a>
                                </div>
                                <div style="width: 35%; margin: 0px 10px;"></div>
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <div style="display: flex; justify-content: center;">
                                    <?php if ($status === 'scheduled'): ?>
                                        <div class="ui-container" style="width: 50% !important;">
                                    <?php else: ?>
                                        <div class="ui-container" style="width: 40% !important;">
                                    <?php endif; ?>
                                        <h2 style="color: #312b2f; font-size: 24px;">
                                            Post Details
                                            <i class="fas fa-info-circle" style="padding-left: 7px; font-size: 21px;"></i>
                                        </h2>
                                        <hr style="margin: 10px 0px 7px 0px;">
                                        <div class="accounts mt-3">

                                        <?php if (!empty($post)): ?>

                                            <?php
                                            $url = $post['permalink'] ?? '';

                                            $isMastodon = preg_match('/https?:\/\/[^\/]+\/@[^\/]+\/\d+/', $url);

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
                                            ?>
                                            <!-- 📝 SCHEDULED -->
                                            <?php if ($status === 'scheduled'): ?>

                                            <div class="post-card d-flex justify-content-between align-items-start" style="position: relative;">

                                                <?php
                                                $postContent = htmlspecialchars($post['content'] ?? '[No content]');
                                                $publishAt   = utcToMalaysia($post['publish_at'] ?? '');
                                                $createdAt   = utcToMalaysia($post['created_at'] ?? '');
                                                $accountId   = $post['account_id'] ?? '';

                                                $accountPfp  = $accountImage ?? 'assets/default-avatar.png';
                                                $accountName = $accountName ?? 'Unknown Account';

                                                $platformColors = [
                                                    'twitter'   => '#42a1dd',
                                                    'mastodon'  => '#6565c8',
                                                    'facebook'  => '#5883bb',
                                                    'instagram' => '#c75078',
                                                    'linkedin'  => '#2789bd'
                                                ];

                                                $platformColor = $platformColors[$platform] ?? '#9a97a7';
                                                ?>

                                                <!-- LEFT -->
                                                <div class="post-info" style="flex:1; padding-right:15px;">

                                                    <div class="d-flex align-items-center mb-2">
                                                        <img src="<?= $accountPfp ?>"
                                                            style="width:50px;height:50px;border-radius:50%;margin-right:10px;border:1px solid #ddd;">

                                                        <div>
                                                            <div style="font-weight:600;color:#312b2f;">
                                                                <?= htmlspecialchars($accountName) ?>
                                                            </div>

                                                            <div class="d-flex gap-2">
                                                                <span style="background:#fff;color:<?= $platformColor ?>;
                                                                            padding:2px 8px;border-radius:4px;border:2px solid <?= $platformColor ?>;">
                                                                    <?= ucfirst($platform) ?>
                                                                </span>

                                                                <span style="background:<?= $statusColor ?>;color:<?= $textColor ?>;
                                                                            padding:2px 8px;border-radius:4px;border:2px solid <?= $statusColor ?>;">
                                                                    <?= $statusLabel ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <strong style="display:block;margin-bottom:30px;">
                                                        <?= nl2br($postContent) ?>
                                                    </strong>

                                                    <small style="position:absolute;bottom:15px;left:15px;">
                                                        <i class="fas fa-clock"></i>
                                                        <b><?= $publishAt ?></b>
                                                    </small>
                                                </div>

                                                <!-- RIGHT (IMAGES) -->
                                                <?php
                                                $validImages = [];

                                                if (!empty($post['attachments'])) {
                                                    foreach ($post['attachments'] as $att) {
                                                        if (!empty($att['url'])) {
                                                            $validImages[] = $att['url'];
                                                        }
                                                    }
                                                }

                                                $imageCount = count($validImages);

                                                $gridClass = $imageCount === 1 ? 'single' : ($imageCount > 1 ? 'multiple' : '');
                                                ?>

                                                <div class="post-images <?= $gridClass ?>">

                                                    <?php if ($imageCount > 0): ?>
                                                        <?php foreach ($validImages as $imgUrl): ?>
                                                            <div>
                                                                <img src="<?= htmlspecialchars($imgUrl) ?>">
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-column justify-content-center align-items-center w-100 h-100">
                                                            <i class="fas fa-image" style="color:#ccc;font-size:80px;"></i>
                                                            <p class="text-muted mb-0">No image</p>
                                                        </div>
                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                            <!-- ACTIONS (OUTSIDE CARD) -->
                                            <div style="display:flex; margin-top:10px;">

                                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">

                                                <button type="submit"
                                                        name="delete_post"
                                                        class="btn btn-danger w-100"
                                                        onclick="return confirm('Delete this post permanently?')">
                                                    Delete Post
                                                </button>

                                                <?php if (!empty($post['permalink'])): ?>
                                                    <a href="<?= htmlspecialchars($post['permalink']) ?>"
                                                    target="_blank"
                                                    class="btn btn-outline-secondary w-100"
                                                    style="margin-left:10px;">
                                                        Go to Post
                                                    </a>
                                                <?php endif; ?>

                                            </div>

                                            <!-- ========================= -->
                                            <!-- 🌐 PUBLISHED -->
                                            <!-- ========================= -->
                                            <?php elseif ($status === 'published' && !empty($url)): ?>

                                                <?php
                                                $embedHtml = "";

                                                if ($isMastodon) {
                                                    preg_match('/https?:\/\/([^\/]+)/', $url, $m);
                                                    $domain = $m[1] ?? null;

                                                    if ($domain) {
                                                        $api = "https://{$domain}/api/oembed?url=" . urlencode($url);
                                                        $json = quickFetch($api);
                                                        $embedHtml = $json['html'] ?? "";
                                                    }

                                                } else {
                                                    $api = "https://publish.twitter.com/oembed?url=" . urlencode($url);
                                                    $json = quickFetch($api);
                                                    $embedHtml = $json['html'] ?? "";
                                                }
                                                ?>

                                                <?php if (!empty($embedHtml)): ?>
                                                    <div style="width:100%;">
                                                        <?= $embedHtml ?>
                                                    </div>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                                                        View Post
                                                    </a>
                                                <?php endif; ?>

                                            <!-- ========================= -->
                                            <!-- ❌ FALLBACK -->
                                            <!-- ========================= -->
                                            <?php else: ?>

                                                <p style="color:#999;">Post status not recognized: <?= htmlspecialchars($status) ?></p>
                                                

                                            <?php endif; ?>

                                        <?php endif; ?>

                                        </div>
                                    </div>
                                    <?php if ($status === 'scheduled'): ?>
                                        <div class="ui-container second" style="width: 35% !important;">
                                    <?php else: ?>
                                        <div class="ui-container second" style="width: 45% !important;">
                                    <?php endif; ?>
                                        <h2 style="color: #312b2f; font-size: 24px;">
                                            Post Digest AI
                                            <i class="fas fa-cog" style="padding-left: 5px;"></i>
                                        </h2>
                                        <hr style="margin: 10px 0px 16px 0px;">
                                        <div class="mb-3">
                                            <!-- <label class="form-label">Engagement Metrics</label>
                                            <div class="input-group mb-2" style="flex-wrap: wrap; gap: 10px;">
                                                <?php
                                                if (!empty($metricsData['data'])) {
                                                    foreach ($metricsData['data'] as $metric => $entries) {
                                                        $metricTotal = 0;
                                                        foreach ($entries as $entry) {
                                                            $metricTotal += intval($entry['value']);
                                                        }
                                                        ?>
                                                        <span style="background:#f3f4f6; padding:5px 10px; border-radius:6px; font-weight:600; color:#312b2f; border:1px solid #ddd;">
                                                            <?= ucfirst($metric) ?>: <?= number_format($metricTotal) ?>
                                                        </span>
                                                        <?php
                                                    }
                                                } else {
                                                    echo "<span>No engagement data available</span>";
                                                }
                                                ?>
                                            </div> -->
                                            <div style=" border: 1px solid #ddd; border-radius: 10px;">
                                                <div id="chatBox" class="chat-box" style="border-top-left-radius: 10px; border-top-right-radius: 10px;"></div>
                                                <div style="background-color: #f0f2f6; padding: 1px 0px; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                                                    <div style="display: flex; position: relative; background-color: #f0f2f6; margin: 5px;">
                                                        <textarea id="postText" class="form-control" rows="5" placeholder="Paste post content here..." style="max-height: 85px; background-color: white !important; border-bottom-left-radius: 0px; border-bottom-right-radius: 0px;"></textarea>
                                                        <div style="margin-bottom:10px; position: absolute; top: 40px; left: 10px;" class="Options">
                                                            <button type="button" class="btn btn-outline-primary analysis-btn" data-type="summary" style="font-size: 13px; background-color: transparent !important;">
                                                                Short Summary
                                                            </button>

                                                            <?php if ($status !== 'scheduled' && $status === 'published' && !empty($url)): ?>
                                                            <button type="button"
                                                                    class="btn btn-outline-primary analysis-btn"
                                                                    data-type="reception"
                                                                    style="font-size: 13px;">
                                                                Public Reception
                                                            </button>
                                                        <?php endif; ?>

                                                            <button type="button" class="btn btn-outline-primary analysis-btn" data-type="sentiment" style="font-size: 13px;">
                                                                Sentiment Breakdown
                                                            </button>
                                                        </div>
                                                        <button type="button" onclick="getAISummary()" class="btn btn-primary AISummary" style="width: 95px;"><i class="fas fa-magic" style="font-size: 20px;"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: center;">
                                    <div class="ui-container second" style="width: 87%;">
                                        <h2 style="color: #312b2f; font-size: 24px;">
                                            Graph View
                                            <i class="fas fa-chart-area" style="padding-left: 5px;"></i>
                                        </h2>
                                        <hr style="margin: 10px 0px 16px 0px;">

                                        <?php
                                            $hasData = false;

                                            if (!empty($metricsData['data'])) {
                                                foreach ($metricsData['data'] as $entries) {
                                                    foreach ($entries as $entry) {
                                                        if (intval($entry['value']) !== 0) {
                                                            $hasData = true;
                                                            break 2; // stop both loops early
                                                        }
                                                    }
                                                }
                                            }
                                        ?>

                                        <?php if ($hasData == true): ?>

                                            <div class="mb-3">
                                                <div>
                                                    <label for="metricsRange" class="form-label">Select Range:</label>
                                                    <select id="metricsRange" class="form-select" style="width:200px; display:inline-block;">
                                                        <!-- <option value="24h">First 24 Hours</option> -->
                                                        <option value="week">First Week</option>
                                                        <option value="month">First Month</option>
                                                        <option value="all" selected>Overall</option>
                                                    </select>
                                                </div>

                                                <canvas id="metricsChart" height="100" style="margin-top:10px;"></canvas>
                                            </div>

                                        <?php else: ?>

                                            <div class="mb-3 text-center text-muted" style="height: 150px; display: flex; justify-content: center; align-items: center; color: #9a97a7 !important;">
                                                No engagement data available
                                            </div>

                                        <?php endif; ?>

                                    </div>
                                </div>
                                <div style="display: flex; justify-content: center;">
                                    <div class="ui-container second" style="width: 87%;">
                                        <h2 style="color: #312b2f; font-size: 24px;">
                                            Replies
                                            <i class="fas fa-comments" style="padding-left: 5px;"></i>
                                        </h2>
                                        <hr style="margin: 10px 0px 16px 0px;">

                                        <?php if (!empty($repliesData)): ?>

                                            <div style="display:flex; flex-direction:column; gap:10px;">

                                                <?php foreach ($repliesData as $reply): ?>

                                                    <div style="border:1px solid #ddd; border-radius:8px; padding:10px; display:flex; gap:10px;">
                                                        
                                                        <img src="<?= htmlspecialchars($reply['avatar']) ?>"
                                                            style="width:40px; height:40px; border-radius:50%;">

                                                        <div>
                                                            <strong><?= htmlspecialchars($reply['author']) ?></strong>
                                                            @<?= htmlspecialchars($reply['handle']) ?>

                                                            <div style="margin-top:5px;">
                                                                <?= htmlspecialchars($reply['content']) ?>
                                                            </div>
                                                        </div>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php else: ?>

                                            <p style="color:#999;">
                                                No replies found or unavailable for this platform.
                                            </p>

                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- <pre><?= htmlspecialchars($metricsResponse) ?></pre> -->
</body>
<script>
    const replies = <?= json_encode($repliesData ?? []) ?>;

    // ✅ Get ALL metrics dynamically from PHP
    const rawMetrics = <?= json_encode($metricsData['data'] ?? []) ?>;

    const ctx = document.getElementById('metricsChart');
    let metricsChart;

    // 🎨 Auto colors (fallback palette)
    const colors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545',
        '#6f42c1', '#17a2b8', '#fd7e14', '#20c997'
    ];

    // Filter data by range
    function filterByRange(dataArray, range) {
        const now = new Date();

        return dataArray.filter(item => {
            const itemDate = new Date(item.date);

            switch (range) {
                case '24h':
                    return now - itemDate <= 24 * 60 * 60 * 1000;
                case 'week':
                    return now - itemDate <= 7 * 24 * 60 * 60 * 1000;
                case 'month':
                    return now - itemDate <= 30 * 24 * 60 * 60 * 1000;
                default:
                    return true;
            }
        });
    }

    // Format date labels
    function formatDates(array) {
        return array.map(item => {
            const d = new Date(item.date);
            return d.getDate() + "/" + (d.getMonth() + 1);
        });
    }

    function updateRange(range) {

        const metricKeys = Object.keys(rawMetrics);

        if (metricKeys.length === 0) return;

        let labels = [];
        let datasets = [];

        metricKeys.forEach((metric, index) => {

            const filtered = filterByRange(rawMetrics[metric], range);

            if (filtered.length === 0) return;

            // Use first metric for labels
            if (labels.length === 0) {
                labels = formatDates(filtered);
            }

            datasets.push({
                label: metric.charAt(0).toUpperCase() + metric.slice(1),
                data: filtered.map(item => item.value),
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length] + '20',
                tension: 0.3
            });

        });

        const chartData = {
            labels: labels,
            datasets: datasets
        };

        if (metricsChart) {
            metricsChart.data = chartData;
            metricsChart.update();
        } else {
            metricsChart = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    // Dropdown listener
    document.getElementById('metricsRange').addEventListener('change', (e) => {
        updateRange(e.target.value);
    });

    // Initial load
    updateRange('all');
</script>
<script>
document.querySelectorAll(".analysis-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const type = btn.dataset.type;

        // auto-fill prompt based on button type
        let prompt = "";

        switch (type) {
            case "summary":
                prompt = "Give a short summary of this post";
                break;

            case "reception":
                prompt = "Analyze the public reception of this post";
                break;

            case "sentiment":
                prompt = "Break down the sentiment of this post";
                break;

            default:
                prompt = "";
        }

        // put into textarea
        document.getElementById("postText").value = prompt;

        // immediately send
        getAISummary();
    });
});
</script>
<script>
document.getElementById("postText").addEventListener("keydown", function(e) {
    if (e.key === "Enter" && e.ctrlKey) {
        getAISummary();
    }
});
</script>
<!-- <script>
async function getAISummary() {

    const postText = document.getElementById("postText").value.trim();

    const metrics = <?= json_encode($metricsTotals ?? []) ?>;
    const embed = <?= json_encode($post['content'] ?? '') ?>;

    const finalPrompt = postText || "Analyze this post:";

    const chatBox = document.getElementById("chatBox");

    // =========================
    // 1. USER MESSAGE
    // =========================
    const userMsg = document.createElement("div");
    userMsg.className = "msg-row";
    userMsg.innerHTML = `
        <div class="msg user">${escapeHtml(postText)}</div>
    `;
    chatBox.appendChild(userMsg);

    chatBox.scrollTop = chatBox.scrollHeight;

    try {
        const res = await fetch("ai_chat.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                text: finalPrompt,
                embed: embed,
                replies: replies,
                metrics: metrics
            })
        });

        // 🔥 IMPORTANT: read as TEXT first (NOT JSON)
        const raw = await res.text();
        console.log("RAW RESPONSE:", raw);

        let data = {};
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error("Invalid JSON from server:\n" + raw);
        }

        // =========================
        // 2. EXTRACT OUTPUT SAFELY
        // =========================
        let output = "";

        if (data?.debug?.raw_gemini_response) {
            output = data.debug.raw_gemini_response;
        } else if (data?.summary) {
            output = data.summary;
        } else if (data?.debug?.gemini_error) {
            output = "Gemini Error:\n" + JSON.stringify(data.debug.gemini_error, null, 2);
        } else {
            output = "No response returned.";
        }

        // =========================
        // 3. AI MESSAGE
        // =========================
        const aiMsg = document.createElement("div");
        aiMsg.className = "msg-row";
        aiMsg.innerHTML = `
            <div class="msg ai" style="white-space: pre-wrap; font-family: monospace;">
                ${escapeHtml(output)}
            </div>
        `;

        chatBox.appendChild(aiMsg);
        chatBox.scrollTop = chatBox.scrollHeight;

    } catch (err) {
        console.error("FETCH ERROR:", err);

        const errorMsg = document.createElement("div");
        errorMsg.className = "msg-row";
        errorMsg.innerHTML = `
            <div class="msg ai" style="white-space: pre-wrap; color: red;">
                ${escapeHtml(err.message)}
            </div>
        `;

        chatBox.appendChild(errorMsg);
    }
}

// prevent HTML injection
function escapeHtml(text) {
    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;");
}
</script> -->
<script>
async function getAISummary() {

    const postText = document.getElementById("postText").value.trim();
    const metrics = <?= json_encode($metricsTotals ?? []) ?>;
    const embed = <?= json_encode($post['content'] ?? '') ?>;

    const finalPrompt = postText || "Analyze this post:";
    const chatBox = document.getElementById("chatBox");

    // =========================
    // USER MESSAGE
    // =========================
    const userMsg = document.createElement("div");
    userMsg.className = "msg-row";
    userMsg.innerHTML = `<div class="msg user">${escapeHtml(postText)}</div>`;
    chatBox.appendChild(userMsg);

    chatBox.scrollTop = chatBox.scrollHeight;

    // =========================
    // LOADING INDICATOR
    // =========================
    const loadingEl = showLoading(chatBox);

    try {
        const res = await fetch("ai_chat.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                text: finalPrompt,
                embed: embed,
                replies: replies ?? [],
                metrics: metrics
            })
        });

        const data = await res.json();

        // remove loading bubble
        loadingEl.remove();

        let output = "";

        if (data?.summary) {
            output = data.summary;
        } else {
            output = "No response returned.";
        }

        const aiMsg = document.createElement("div");
        aiMsg.className = "msg-row";
        aiMsg.innerHTML = `
            <div class="msg ai">
                ${formatAIText(output)}
            </div>
        `;

        chatBox.appendChild(aiMsg);
        chatBox.scrollTop = chatBox.scrollHeight;

    } catch (err) {

        loadingEl.remove();

        const errorMsg = document.createElement("div");
        errorMsg.className = "msg-row";
        errorMsg.innerHTML = `
            <div class="msg ai" style="color:red;">
                Error generating response.
            </div>
        `;

        chatBox.appendChild(errorMsg);
    }
}

function formatAIText(text) {
    if (!text) return "";

    let html = String(text);

    // 1. Escape HTML first (security)
    html = html
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;");

    // 2. Bold **text**
    html = html.replace(/\*\*(.*?)\*\*/g, "<b>$1</b>");

    return html;
}

// fallback if you still use escapeHtml elsewhere
function escapeHtml(text) {
    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;");
}

document.addEventListener("DOMContentLoaded", () => {
    const chatBox = document.getElementById("chatBox");

    const intro = document.createElement("div");
    intro.className = "msg-row";
    intro.innerHTML = `<div class="msg ai" style="padding: 10px 14px;">I'm your <b>Post Digest AI Assistant</b>! What would you like help with today?</div>`;

    chatBox.appendChild(intro);
});

function showLoading(chatBox) {
    const loadingRow = document.createElement("div");
    loadingRow.className = "msg-row";
    loadingRow.id = "ai-loading";

    loadingRow.innerHTML = `
        <div class="msg ai" style="display:flex; align-items:center; gap:10px;">
            <i class="fas fa-spinner fa-spin"></i>
            Analyzing...
        </div>
    `;

    chatBox.appendChild(loadingRow);
    chatBox.scrollTop = chatBox.scrollHeight;

    return loadingRow;
}
</script>
</html>