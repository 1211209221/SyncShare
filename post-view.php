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

</style>
</head>





<body>
    <div class="wrapper">
        <div class="d-flex">
            <?php include 'sidebar.php'; ?>
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
                                    <div class="ui-container">
                                        <h2 style="color: #312b2f; font-size: 24px;">
                                            Post Details
                                            <i class="fas fa-info-circle" style="padding-left: 7px; font-size: 21px;"></i>
                                        </h2>
                                        <hr style="margin: 10px 0px 7px 0px;">
                                        <div class="accounts mt-3">
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
                                                <div class="post-info" style="flex:1; padding-right:15px;">

                                                    <div class="post-header mb-2">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <img src="<?= $accountPfp ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; margin-right:10px; border:1px solid #ddd;">
                                                            <div>
                                                                <div style="font-weight:600; font-size:16px; color:#312b2f;">
                                                                    <?= htmlspecialchars($accountName) ?>
                                                                </div>
                                                                <div class="d-flex gap-2">
                                                                    <span style="background:#fff; color:<?= $platformColor ?>; padding:2px 8px; border-radius:4px; font-size:.85rem; border:2px solid <?= $platformColor ?> !important;">
                                                                        <?= ucfirst($platform) ?>
                                                                    </span>
                                                                    <span style="background:<?= $statusColor ?>; color:<?= $textColor ?>; padding:2px 8px; border-radius:4px; font-size:.85rem; border:2px solid <?= $statusColor ?> !important;">
                                                                        <?= $statusLabel ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <strong style="color:#312b2f; font-size:17px; display:block; margin-bottom:40px;">
                                                        <?= nl2br($postContent) ?>
                                                    </strong>

                                                    <small class="small-muted" style="position:absolute; bottom:15px; left:15px;">
                                                        <i class="fas fa-clock"></i>
                                                        <b><?= $publishAt ?></b>
                                                    </small>
                                                </div>

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

                                                $gridClass =
                                                    $imageCount === 1
                                                        ? 'single'
                                                        : ($imageCount > 1 ? 'multiple' : '');
                                                ?>
                                                <div class="post-images <?= $gridClass ?>">
                                                    <?php if ($imageCount > 0): ?>
                                                        <?php foreach ($validImages as $url): ?>
                                                            <div>
                                                                <img src="<?= htmlspecialchars($url) ?>">
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-column justify-content-center align-items-center w-100 h-100">
                                                            <i class="fas fa-image" style="color:#ccc; font-size:80px;"></i>
                                                            <p class="text-muted mb-0" style="font-size:0.9rem;">
                                                                No image
                                                            </p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="display: flex;">
                                                <!-- <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                <button 
                                                    type="submit" 
                                                    name="delete_post"
                                                    class="btn btn-danger w-100"
                                                    onclick="return confirm('Delete this post permanently?')"
                                                >
                                                Delete Post
                                                </button> -->
                                                <?php if (!empty($post['permalink'])): ?>
                                                <a href="<?= htmlspecialchars($post['permalink']) ?>" 
                                                target="_blank" 
                                                class="btn btn-outline-secondary w-100" style="margin-left: 0px; font-size: 20px; padding: 9px; border: 2px solid;">
                                                Go to Post
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ui-container second" style="width: 35% !important;">
                                        <h2 style="color: #312b2f; font-size: 24px;">
                                            Post Insights
                                            <i class="fas fa-share-square" style="padding-left: 5px;"></i>
                                        </h2>
                                        <hr style="margin: 10px 0px 16px 0px;">
                                        <div class="mb-3">
                                            <label class="form-label">Engagement Metrics</label>
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
                                        $totalEngagement = 0;

                                        if (!empty($metricsData['data'])) {
                                            foreach ($metricsData['data'] as $metric => $entries) {
                                                foreach ($entries as $entry) {
                                                    $totalEngagement += intval($entry['value']);
                                                }
                                            }
                                        }
                                        ?>

                                        <?php if ($totalEngagement > 0): ?>

                                            <div class="mb-3">
                                                <div>
                                                    <label for="metricsRange" class="form-label">Select Range:</label>
                                                    <select id="metricsRange" class="form-select" style="width:200px; display:inline-block;">
                                                        <option value="24h">First 24 Hours</option>
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
    // Raw data from PHP
    const rawReplies    = <?= json_encode($metricsData['data']['replies']) ?>;
    const rawReblogs    = <?= json_encode($metricsData['data']['reblogs']) ?>;
    const rawFavourites = <?= json_encode($metricsData['data']['favourites']) ?>;

    const ctx = document.getElementById('metricsChart');
    let metricsChart;

    // Function to filter data by range
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
                case 'all':
                default:
                    return true;
            }
        });
    }

    // Convert date to "DD/MM" format
    function formatDates(array) {
        return array.map(item => {
            const d = new Date(item.date);
            return d.getDate() + "/" + (d.getMonth() + 1);
        });
    }

    // Update chart data
    function updateRange(range) {
        const filteredReplies    = filterByRange(rawReplies, range);
        const filteredReblogs    = filterByRange(rawReblogs, range);
        const filteredFavourites = filterByRange(rawFavourites, range);

        const labels = formatDates(filteredReplies);
        const replies    = filteredReplies.map(item => item.value);
        const reblogs    = filteredReblogs.map(item => item.value);
        const favourites = filteredFavourites.map(item => item.value);

        const chartData = {
            labels: labels,
            datasets: [
                {
                    label: 'Replies',
                    data: replies,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.1)',
                    tension: 0.3
                },
                {
                    label: 'Reblogs',
                    data: reblogs,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.1)',
                    tension: 0.3
                },
                {
                    label: 'Favourites',
                    data: favourites,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255,193,7,0.1)',
                    tension: 0.3
                }
            ]
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
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    // Dropdown listener
    document.getElementById('metricsRange').addEventListener('change', (e) => {
        updateRange(e.target.value);
    });

    // Initial chart load
    updateRange('all');
</script>
</html>