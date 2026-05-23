<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];

$accountImageMap = [];
$accountNameMap  = [];

if (isset($_SESSION['token'])) {
    $ch = curl_init('https://socialbu.com/api/v1/accounts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $_SESSION['token'],
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $accounts = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        // Example: print each account's name and profile picture
        foreach ($accounts as $acc) {
            $id = $acc['id'] ?? $acc['account_id']; // use whichever exists
            $accountImageMap[$id] = $acc['image'] ?? 'assets/default-avatar.png';
            $accountNameMap[$id]  = $acc['name'] ?? 'Unknown Account';
        }
    }
}


// Fetch helper
function fetchPosts($token, $type = 'scheduled') {

    $allPosts = [];
    $page = 1;
    $perPage = 100;

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
            ]
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return [];
        }

        $data = json_decode($response, true);
        $items = $data['items'] ?? [];

        if (empty($items)) {
            break;
        }

        $allPosts = array_merge($allPosts, $items);

        if (count($items) < $perPage) {
            break;
        }

        $page++;
    }

    return $allPosts;
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

// Convert UTC → Malaysia time (format: Jan 1, 10:21AM)
function utcToMalaysia($utcTime) {
    if (empty($utcTime)) return 'Unknown';
    try {
        $dt = new DateTime($utcTime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
        return $dt->format('Y M j · g:i A');  // Example: Jan 1, 10:21AM
    } catch (Exception $e) {
        return $utcTime;
    }
}

// Fetch posts
$draftPosts = fetchPosts($token, 'draft');
$scheduledPosts = fetchPosts($token, 'scheduled');
$publishedPosts = fetchPosts($token, 'published');
$posts = array_merge($draftPosts, $scheduledPosts, $publishedPosts);
usort($posts, function($a, $b) {
    $pa = !empty($a['publish_at']) ? strtotime($a['publish_at']) : 0;
    $pb = !empty($b['publish_at']) ? strtotime($b['publish_at']) : 0;
    return $pb <=> $pa; // newest first
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
body {
    background: #f5f6fa;
    font-family: "Segoe UI", sans-serif;
}

.post-card {
    border-radius: 8px;
    padding: 15px;
    background: #f3f4f6;
    transition: 0.15s;
}

.post-card:hover{
    background-color: #e2e1e7;
    cursor: pointer;
    color: #53515f;
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
    width: 170px;
    height: 170px;
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
    object-fit: cover;
    object-position: center;
    border-radius: 6px;
    border: 1px solid #f3f4f6;
    background: white;

    display: block;
}
</style>
</head>
<body>
<div class="wrapper">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div style="width:100%;">
            <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white; margin-bottom:20px;">
                    <h1 class="mb-0">Posts List</h1>
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
            <div class="d-flex">
                <div class="dashboard container container-fluid" id="dashboard">
                    <div style="width: 53%; margin: 0px 20px 15px 10px; color: #44424d;">
                                <a href="dashboard.php">Posts</a> > <a style="color: #04a3ce !important; font-weight: bold;">Post List</a>
                            </div>
                    <div class="d-flex gap-2 mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search" style="color: #9a97a7;"></i></span>
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
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="published">Published</option>
                        </select>
                        <button class="add-account btn btn-primary w-100" onclick="window.location.href='post-new.php'">
                            <i class="fas fa-plus"></i><b> New Post</b>
                        </button>
                    </div>
                    <div class="ui-container">
                        <h2 style="color: #312b2f !important;">Your Posts</h2>
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
                                $accountPfp  = $accountImageMap[$accountId] ?? 'assets/default-avatar.png';
                                $accountName = $accountNameMap[$accountId] ?? 'Unknown Account';

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
                                    'twitter' => '#42a1dd',
                                    'mastodon' => '#6565c8',
                                    'facebook' => '#5883bb',
                                    'instagram' => '#c75078',
                                    'linkedin' => '#2789bd'
                                ];
                                $platformColor = $platformColors[$platformKey] ?? '#9a97a7';

                                // Status color
                                $statusColors = [
                                    'draft' => '#9a97a7',
                                    'scheduled' => '#d19541',
                                    'published' => '#47a55d'
                                ];
                                $statusColor = $statusColors[$statusClass] ?? '#9a97a7';
                            ?>
                            <?php
                                $redirectPage = ($statusClass === 'draft')
                                    ? "post-edit.php?id=$postId"
                                    : "post-view.php?id=$postId";
                                ?>

                                <div class="post-card d-flex justify-content-between align-items-start"
                                    data-status="<?= strtolower($statusLabel) ?>"
                                    onclick="window.location.href='<?= $redirectPage ?>'">
                                <!-- Left: Post info -->
                                <div class="post-info" style="flex: 1; padding-right: 15px; height: 100%;">
                                    <div class="post-header mb-2">
                                        <!-- Account Info Row -->
                                        <div class="d-flex align-items-center mb-2">

                                            <img src="<?= $accountPfp ?>" 
                                                alt="Profile Picture"
                                                style="width:50px; height:50px; border-radius:50%; object-fit:cover; margin-right:10px; border:1px solid #ddd;">

                                            <div>
                                                <div style="font-weight:600; font-size:16px; color: #312b2f;">
                                                    <?= htmlspecialchars($accountName) ?>
                                                </div>
                                                <div style="font-size:13px; color:#777;">
                                                    <div class="d-flex gap-2">
                                                        <span class="platform-tag"  style="background: #fff; color: <?= $platformColor ?>; padding:2px 8px; border-radius:4px; font-size:0.85rem; border: 2px solid <?= $platformColor ?> !important; font-weight: 500;">
                                                            <?= ucfirst($accountType) ?>
                                                        </span>
                                                        <span style="background: <?= $statusColor ?>; color:white; padding:2px 8px; border-radius:4px; font-size:0.85rem;    border: 2px solid <?= $statusColor ?> !important; font-weight: 500;">
                                                            <?= $statusLabel ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <!-- Post Title -->
                                    <div style="margin-left: 7px; display: flex; flex-direction: column; justify-content: space-between; height: 70%;">
                                        <strong style="color: #312b2f; font-size: 17px; word-break: break-word; overflow-wrap: anywhere;">
                                            <?= $content ?>
                                        </strong>
                                        <div>
                                            <small class="small-muted" style="margin-bottom: 10px;">
                                                <i class="fas fa-clock" style="padding-right: 6px;"></i> <b><?= $publishAt ?></b>
                                            </small>
                                            <!-- <small class="small-muted">
                                                <i class="fas fa-calendar-alt"></i> Created <b><?= $createdAt ?></b>
                                            </small> -->
                                        </div>
                                    </div>
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
                                $gridClass = $imageCount === 1 ? 'single' : ($imageCount > 1 ? 'multiple' : '');
                                ?>

                                <div class="post-images <?= $gridClass ?>">
                                    <?php if ($imageCount > 0): ?>
                                        <?php foreach (array_slice($validImages, 0, 4) as $url): ?>
                                            <div href="<?= htmlspecialchars($url) ?>" target="_blank">
                                                <img src="<?= htmlspecialchars($url) ?>" loading="<?= $index < 2 ? 'eager' : 'lazy' ?>" decoding="async" draggable="false">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="d-flex flex-column justify-content-center align-items-center w-100 h-100">
                                            <i class="fas fa-image" style="color:#ccc; font-size: 80px;"></i>
                                            <p class="text-muted mb-0" style="font-size:0.9rem;">No image</p>
                                        </div>
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
        const statusFilter = document.getElementById('statusFilter');
        const noResults = document.getElementById('noResultsMessage');

        const postCards = [...document.querySelectorAll('.accounts-grid .post-card')];

        function normalizePlatform(platform) {

            if (platform.includes('twitter')) return 'twitter';
            if (platform.includes('mastodon')) return 'mastodon';
            if (platform.includes('facebook')) return 'facebook';
            if (platform.includes('instagram')) return 'instagram';
            if (platform.includes('linkedin')) return 'linkedin';

            return platform;
        }

        function getPlatformIcon(platform) {

            switch (platform) {

                case 'twitter':
                    return '<i class="fab fa-twitter"></i>';

                case 'facebook':
                    return '<i class="fab fa-facebook"></i>';

                case 'instagram':
                    return '<i class="fab fa-instagram"></i>';

                case 'linkedin':
                    return '<i class="fab fa-linkedin"></i>';

                case 'mastodon':
                    return '<i class="fab fa-mastodon"></i>';

                default:
                    return '<i class="fas fa-share"></i>';
            }
        }

        // PREPROCESS EVERYTHING ONCE
        postCards.forEach(card => {

            const strong = card.querySelector('strong');
            const platformElement = card.querySelector('.platform-tag');

            const content =
                strong?.textContent.toLowerCase() || '';

            let platform =
                platformElement?.textContent.toLowerCase().trim() || '';

            platform = normalizePlatform(platform);

            card.dataset.content = content;
            card.dataset.platform = platform;
            card.dataset.status = (card.dataset.status || '').toLowerCase();

            // Inject icon once only
            if (platformElement) {

                platformElement.innerHTML =
                    getPlatformIcon(platform) + ' ' + platformElement.textContent;

            }

        });

        function filterPosts() {

            const searchTerm =
                searchInput.value.toLowerCase().trim();

            const selectedPlatform =
                filterSelect.value.toLowerCase();

            const selectedStatus =
                statusFilter.value.toLowerCase();

            let anyVisible = false;

            for (const card of postCards) {

                const matchesSearch =
                    card.dataset.content.includes(searchTerm);

                const matchesPlatform =
                    !selectedPlatform ||
                    card.dataset.platform === selectedPlatform;

                const matchesStatus =
                    !selectedStatus ||
                    card.dataset.status === selectedStatus;

                const visible =
                    matchesSearch &&
                    matchesPlatform &&
                    matchesStatus;

                card.classList.toggle('hidden', !visible);

                if (visible) {
                    anyVisible = true;
                }
            }

            noResults.style.display =
                anyVisible ? 'none' : 'block';
        }

        // FILTER EVENTS
        searchInput.addEventListener('input', filterPosts);
        filterSelect.addEventListener('change', filterPosts);
        statusFilter.addEventListener('change', filterPosts);

        // INITIAL RUN
        filterPosts();

    });
</script>
</body>

</html>
