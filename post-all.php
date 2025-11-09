<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];

// Fetch helper
function fetchPosts($token, $type = 'scheduled') {
    $url = "https://socialbu.com/api/v1/posts?type=$type";
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
        return $data['items'] ?? [];
    } else {
        echo "<div class='alert alert-danger'>❌ Failed to fetch $type posts. HTTP $code<br><pre>$response</pre></div>";
        return [];
    }
}

// Get account details by ID
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

// Convert UTC → Malaysia time (12-hour format)
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

// Fetch posts
$scheduledPosts = fetchPosts($token, 'scheduled');
$publishedPosts = fetchPosts($token, 'published');
$posts = array_merge($scheduledPosts, $publishedPosts);
usort($posts, function($a, $b) {
    $pa = !empty($a['publish_at']) ? strtotime($a['publish_at']) : 0;
    $pb = !empty($b['publish_at']) ? strtotime($b['publish_at']) : 0;
    return $pa <=> $pb;
});
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
<style>
body { background: #f5f6fa; font-family: "Segoe UI", sans-serif; }
.post-card {border-radius: 8px; padding: 15px; background: #f3f4f6; }
.post-header { display: flex; justify-content: space-between; align-items: center; }
.status { font-size: 0.9em; }
.status.published { color: green; }
.status.scheduled { color: #f0ad4e; }
.status.draft { color: #888; }
.small-muted { color: #666; display:block; margin-top:6px; }
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
    color: #888;
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


</style>
</head>
<body>
<div class="wrapper">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div style="width:100%;">
            <div class="py-4 px-4"><h1>Posts List</h1></div>
            <div class="d-flex">
                <div class="dashboard container container-fluid" id="dashboard">
                    <div class="d-flex gap-2 mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search" style="color: #888;"></i></span>
                            <input type="text" id="accountSearch" class="form-control border-start-0" placeholder="Search posts...">
                        </div>
                        <select id="accountFilter" class="form-select">
                            <option value="">All Platforms</option>
                            <option value="instagram">Instagram</option>
                            <option value="twitter">Twitter</option>
                            <option value="facebook">Facebook</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="mastodon">Mastodon</option>
                        </select>
                        <button class="add-account btn btn-primary w-100" onclick="window.location.href='post-new.php'">
                            <i class="fas fa-plus"></i><b> New Post</b>
                        </button>
                    </div>
                    <div class="ui-container">
                        <h2>Your Posts</h2>
                        <div class="accounts mt-4">
    <div class="accounts-grid mt-4" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(48%, 1fr)); gap:15px;">

                        <?php if (!empty($posts)): ?>
                            <?php foreach ($posts as $post):
                                $fullContent = htmlspecialchars($post['content'] ?? '[No content]');
                                $content = strlen($fullContent) > 80 ? substr($fullContent, 0, 80) . '...' : $fullContent;

                                $postId = htmlspecialchars($post['id'] ?? '');
                                $publishAt = utcToMalaysia($post['publish_at'] ?? '');
                                $createdAt = utcToMalaysia($post['created_at'] ?? '');
                                $accountId = htmlspecialchars($post['account_id'] ?? '');
                                $accountType = htmlspecialchars($post['account_type'] ?? '');
                                $accountName = getAccountName($token, $accountId);

                                // Status
                                if (!empty($post['draft'])) {
                                    $statusClass = 'draft'; $statusLabel = 'Draft';
                                } elseif (!empty($post['published'])) {
                                    $statusClass = 'published'; $statusLabel = 'Published';
                                } else {
                                    $statusClass = 'scheduled'; $statusLabel = 'Scheduled';
                                }

                                // Platform color
                                $platformKey = strtolower($accountType);
                                if (str_contains($platformKey, 'twitter')) $platformKey = 'twitter';
                                elseif (str_contains($platformKey, 'mastodon')) $platformKey = 'mastodon';

                                $platformColors = [
                                    'twitter' => '#1da1f2',
                                    'mastodon' => '#6364ff',
                                    'facebook' => '#1877f2',
                                    'instagram' => '#e1306c',
                                    'linkedin' => '#0077b5'
                                ];
                                $platformColor = $platformColors[$platformKey] ?? '#888';

                                // Status color
                                $statusColors = [
                                    'draft' => '#888',
                                    'scheduled' => '#f0ad4e',
                                    'published' => '#28a745'
                                ];
                                $statusColor = $statusColors[$statusClass] ?? '#888';
                            ?>
                            <div class="post-card d-flex justify-content-between align-items-start" style="padding:15px; border-radius:8px;">
                                <!-- Left: Post info -->
                                <div class="post-info" style="flex: 1; padding-right: 15px;">
                                    <div class="post-header mb-2">
                                        <strong style="color: black; font-size: 17px; word-break: break-word; overflow-wrap: anywhere;"><?= $content ?></strong>
                                    </div>

                                    <!-- Tags -->
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="platform-tag" 
                                        style="background: #fff; 
                                                color: <?= $platformColor ?>; 
                                                padding:2px 8px; 
                                                border-radius:4px; 
                                                font-size:0.85rem; 
                                                border: 2px solid <?= $platformColor ?> !important; 
                                                font-weight: 500;">
                                        <?= ucfirst($accountType) ?>
                                    </span>

                                        <span style="background: <?= $statusColor ?>; color:white; padding:2px 8px; border-radius:4px; font-size:0.85rem;">
                                            <?= $statusLabel ?>
                                        </span>
                                    </div>


                                    <small class="small-muted">
                                        <i class="fas fa-share-square"></i> Published to: <b><?= htmlspecialchars($accountName) ?></b>
                                    </small>
                                    <small class="small-muted">
                                        <i class="fas fa-clock"></i> Publish at: <b><?= $publishAt ?></b>
                                    </small>
                                    <small class="small-muted">
                                        <i class="fas fa-calendar-alt"></i> Created at: <b><?= $createdAt ?></b>
                                    </small>

                                    <div style="margin-top:10px;">
                                        <a href="post-view.php?id=<?= urlencode($postId) ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-search"></i> View Details
                                        </a>
                                    </div>
                                </div>

                                <!-- Right: Post images or placeholder -->
                        <div class="post-images d-flex flex-column justify-content-center align-items-center" style="width:250px; min-height:250px; text-align:center;">
                            <?php if (!empty($post['attachments'])): ?>
                                <?php foreach ($post['attachments'] as $att): ?>
                                    <?php if (!empty($att['url'])): ?>
                                        <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank">
                                            <img src="<?= htmlspecialchars($att['url']) ?>" 
                                                style="width:250px; height:250px; object-fit:cover; border-radius:6px; border:1px solid #ddd;">
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <i class="fas fa-image" style="color:#ccc; font-size: 150px;"></i>
                                <p class="text-muted" style="font-size:0.9rem;">No image</p>
                            <?php endif; ?>
                        </div>

                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No posts found.</p>
                        <?php endif; ?>

                        <div id="noResultsMessage" class="text-center text-muted" style="grid-column: 1 / -1; padding: 30px; display: none;">
    <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
    <strong>No posts found.</strong>
</div>

                        </div>



                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('accountSearch');
    const filterSelect = document.getElementById('accountFilter');
    const postCards = document.querySelectorAll('.accounts-grid .post-card');

    function filterPosts() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const selectedPlatform = filterSelect.value.toLowerCase();
    let anyVisible = false;

    postCards.forEach(card => {
        const content = card.querySelector('strong')?.textContent.toLowerCase() || '';
        let platformTag = card.querySelector('.platform-tag')?.textContent.toLowerCase().trim() || '';

        // Normalize platform
        if (platformTag.includes('twitter')) platformTag = 'twitter';
        else if (platformTag.includes('mastodon')) platformTag = 'mastodon';
        else if (platformTag.includes('facebook')) platformTag = 'facebook';
        else if (platformTag.includes('instagram')) platformTag = 'instagram';
        else if (platformTag.includes('linkedin')) platformTag = 'linkedin';

        const matchesSearch = content.includes(searchTerm);
        const matchesPlatform = selectedPlatform === '' || platformTag === selectedPlatform;

        if (matchesSearch && matchesPlatform) {
            card.classList.remove('hidden');
            anyVisible = true;
        } else {
            card.classList.add('hidden');
        }
    });

    // Show or hide "No posts found" message
    document.getElementById('noResultsMessage').style.display = anyVisible ? 'none' : 'block';
}


    searchInput.addEventListener('input', filterPosts);
    filterSelect.addEventListener('change', filterPosts);

    filterPosts(); // run once on page load
});
</script>


</body>

</html>
