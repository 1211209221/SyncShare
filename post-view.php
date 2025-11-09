<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}
$token = $_SESSION['token'];
$postId = $_GET['id'] ?? null;

if (!$postId) {
    die("Missing post ID");
}

// Fetch post
$ch = curl_init("https://socialbu.com/api/v1/posts/" . intval($postId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode === 200) {
    $post = json_decode($response, true);
    if (!$post || empty($post['id'])) die("Post not found in response");
} else {
    die("❌ Failed to fetch post. HTTP $httpcode<br><pre>$response</pre>");
}

// Helper function for UTC → Malaysia time
function utcToMalaysia($utcTime) {
    if (empty($utcTime)) return 'Unknown';
    try {
        $dt = new DateTime($utcTime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
        return $dt->format('Y-m-d g:i A');
    } catch (Exception $e) {
        return $utcTime;
    }
}

// Platform colors
$platformColors = [
    'twitter' => '#1da1f2',
    'mastodon' => '#6364ff',
    'facebook' => '#1877f2',
    'instagram' => '#e1306c',
    'linkedin' => '#0077b5'
];

// Status colors
$statusColors = [
    'draft' => '#888',
    'scheduled' => '#f0ad4e',
    'published' => '#28a745'
];

// Determine status
if (!empty($post['draft'])) {
    $statusClass = 'draft'; 
    $statusLabel = 'Draft';
} elseif (!empty($post['published'])) {
    $statusClass = 'published'; 
    $statusLabel = 'Published';
} else {
    $statusClass = 'scheduled'; 
    $statusLabel = 'Pending';
}

// Determine platform color
$accountType = strtolower($post['account_type'] ?? 'unknown');
$platformKey = $accountType;
if (str_contains($platformKey, 'twitter')) $platformKey = 'twitter';
elseif (str_contains($platformKey, 'mastodon')) $platformKey = 'mastodon';
$platformColor = $platformColors[$platformKey] ?? '#888';
$statusColor = $statusColors[$statusClass] ?? '#888';

// Get account name
$accountsCache = [];
function getAccountName($token, $accountId) {
    global $accountsCache;
    if (isset($accountsCache[$accountId])) return $accountsCache[$accountId];

    $url = "https://socialbu.com/api/v1/accounts/$accountId";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($response, true);
        $accountsCache[$accountId] = $data['name'] ?? 'Unknown Account';
    } else {
        $accountsCache[$accountId] = 'Unknown Account';
    }

    return $accountsCache[$accountId];
}
$accountName = getAccountName($token, $post['account_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Post Detail</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
.post-card { border-radius: 8px; padding: 15px; margin:20px auto; max-width:900px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.platform-tag { padding:2px 8px; border-radius:4px; font-size:0.85rem; font-weight:500; }
.status-tag { padding:2px 8px; border-radius:4px; font-size:0.85rem; font-weight:500; color:#fff; }
.post-images img { width:250px; height:250px; object-fit:cover; border-radius:6px; border:1px solid #ddd; }
.small-muted { display:block; color:#555; margin-bottom:3px; }
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
    color: #888;
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

</style>
</head>
<body>
<body>
    <div class="wrapper">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <div style="width:100%;">
            <div class="py-4 px-4">
                <h1>Accounts</h1>
            </div>

            <div class="d-flex justify-content-center">
                <div class="post-card d-flex justify-content-between align-items-start">

                    <!-- Left: Post Info -->
                    <div class="post-info" style="flex:1; padding-right:15px;">
                        <div class="post-header mb-2">
                            <strong style="color:black; font-size:17px; word-break:break-word; overflow-wrap:anywhere;">
                                <?= nl2br(htmlspecialchars($post['content'] ?? '[No content]')) ?>
                            </strong>
                        </div>

                        <!-- Tags -->
                        <div class="d-flex gap-2 mb-2">
                            <span class="platform-tag" style="background:#fff; color:<?= $platformColor ?>; border:2px solid <?= $platformColor ?> !important;">
                                <?= ucfirst($post['account_type'] ?? 'Unknown') ?>
                            </span>
                            <span class="status-tag" style="background:<?= $statusColor ?>;">
                                <?= $statusLabel ?>
                            </span>
                        </div>

                        <small class="small-muted">
                            <i class="fas fa-user"></i> Account Name: <b><?= htmlspecialchars($accountName) ?></b>
                        </small>
                        <small class="small-muted">
                            <i class="fas fa-share-square"></i> Published to: <b><?= htmlspecialchars($post['account_type'] ?? 'Unknown') ?></b>
                        </small>
                        <small class="small-muted">
                            <i class="fas fa-clock"></i> Publish at: <b><?= utcToMalaysia($post['publish_at'] ?? '') ?></b>
                        </small>
                        <small class="small-muted">
                            <i class="fas fa-calendar-alt"></i> Created at: <b><?= utcToMalaysia($post['created_at'] ?? '') ?></b>
                        </small>

                        <div style="margin-top:10px;">
                            <a href="post-view.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-search"></i> View Details
                            </a>
                        </div>
                    </div>

                    <!-- Right: Post images -->
                    <div class="post-images d-flex flex-column justify-content-center align-items-center" style="width:250px; min-height:250px; text-align:center;">
                        <?php if (!empty($post['attachments'])): ?>
                            <?php foreach ($post['attachments'] as $att): ?>
                                <?php if (!empty($att['url'])): ?>
                                    <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($att['url']) ?>" alt="Attachment">
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span>No image</span>
                        <?php endif; ?>
                    </div>

                </div> <!-- /.post-card -->
            </div> <!-- /.d-flex justify-content-center -->
        </div> <!-- /.width:100% -->
    </div> <!-- /.d-flex -->
</div> <!-- /.wrapper -->
</body>
</html>