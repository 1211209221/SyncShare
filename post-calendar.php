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
<title>Content Calendar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
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
    transition: 0.2s;
}

.post-card:hover{
    transform: scale(1.02);
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
    width: 210px;
    height: 210px;
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
#calendar {
    background: white;
    padding: 20px;
    border-radius: 8px;
}

.fc-event {
    border: none;
    font-size: 0.85rem;
    cursor: pointer;
}
.fc-daygrid-event-harness a{
    color: white;
    padding-right: 10px;
}
.fc-theme-standard td{
    height: 120px;

}
a[tabindex="0"]{
    width: 100%;
    background: #04a3ce;
    padding: 5px 15px !important;
    color: white;
    transition: 0.2s;
    font-weight: bold;
}
a[tabindex="0"]:hover{
    background: #1b78aeff !important;
    transform: scale(1.02);
}
.fc-daygrid-event-harness a{
    transition: 0.2s;
}
.fc-daygrid-event-harness a:hover{
    transform: scale(1.02);
}
.fc .fc-daygrid-event{
    margin-bottom: 1px;
}
.fc-daygrid-event .fc-event-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fc-event-time{
    font-weight: bold;
}
.fc-event-title{
    font-weight: 400 !important;
}
.fc-daygrid-event {
    overflow: hidden;
}
.fc-daygrid-day-events {
    margin-bottom: 0px !important;
}

.fc-daygrid-event-dot {
    color: white !important;
    border: calc(var(--fc-daygrid-event-dot-width) / 2) solid #ffffff;
    margin-left: 10px;
}
.fc .fc-daygrid-day-top{
    flex-direction: row;
}
.fc-header-toolbar.fc-toolbar.fc-toolbar-ltr{
    background-color: #04a3ce;
    color: white;
    margin: 0;
    border-radius: 10px 10px 0px 0px;
    padding: 10px 0px;
}
.fc-button-primary{
    background-color: transparent !important;
}
.fc-button-primary:hover{
    background-color: transparent !important;
    transform: scale(1.2) !important;
}
.fc .fc-toolbar-title{
    font-size: 35px;
}
tbody tr:nth-child(even){
    background-color: #f8f8f8 !important;
}
thead th:first-child, thead th:last-child{
    border-top-left-radius: 0px !important;
    border-top-right-radius: 0px !important;
}
.fc-popover-title{
    font-weight: bold;
}
.fc-popover-close.fc-icon.fc-icon-x{
    color: #6d6a7a;
    transition: 0.2s;
    font-weight: bold;
}
.fc-popover-close.fc-icon.fc-icon-x:hover{
    transform: scale(1.2) !important;
}
thead[role="presentation"]{
    background-color: #f8f8f8 !important;
}
.fc .fc-daygrid-day.fc-day-today {
    background-color: rgb(0 147 200 / 35%);
    color: white;
}
.fc-daygrid-event-dot{
    display:none;
}
.fc-daygrid-dot-event i{
    color: white !important;
    font-weight: 100 !important;
    pointer-events: none;
    margin-left: 17px;
    margin-right: 0px !important;
}
.fc-event-time {
    margin-right: 6px !important;
}
.fc-event-time i{
    padding-right: 4px;
}
</style>
</head>
<body>
<div class="wrapper">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div style="width:100%;">
            <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white; margin-bottom:20px;">
                    <h1 class="mb-0">Content Calendar</h1>
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
                                <a href="dashboard.php">Content Calendar</a> > <a style="color: #04a3ce !important; font-weight: bold;">Scheduled Posts</a>
                            </div>
                    
                    <div class="ui-container">
                        <h2 style="color: #312b2f !important; padding-left: 20px; margin: 0px 0px -5px 0px;">Scheduled Posts</h2>
                        <div id="calendar"></div>
                        
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
    const postCards = document.querySelectorAll('.accounts-grid .post-card');

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

    function filterPosts() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedPlatform = filterSelect.value.toLowerCase();
        let anyVisible = false;

        postCards.forEach(card => {
            const content = card.querySelector('strong')?.textContent.toLowerCase() || '';
            const platformElement = card.querySelector('.platform-tag');
            let platformTag = platformElement?.textContent.toLowerCase().trim() || '';

            // Normalize platform
            if (platformTag.includes('twitter')) platformTag = 'twitter';
            else if (platformTag.includes('mastodon')) platformTag = 'mastodon';
            else if (platformTag.includes('facebook')) platformTag = 'facebook';
            else if (platformTag.includes('instagram')) platformTag = 'instagram';
            else if (platformTag.includes('linkedin')) platformTag = 'linkedin';

            // Inject icon (only once)
            if (platformElement && !platformElement.dataset.iconInjected) {
                platformElement.innerHTML = getPlatformIcon(platformTag) + platformElement.textContent;
                platformElement.dataset.iconInjected = "true";
            }

            const matchesSearch = content.includes(searchTerm);
            const status = card.dataset.status || '';
            const selectedStatus = statusFilter.value.toLowerCase();

            const matchesPlatform = selectedPlatform === '' || platformTag === selectedPlatform;
            const matchesStatus = selectedStatus === '' || status === selectedStatus;

            if (matchesSearch && matchesPlatform && matchesStatus){
                card.classList.remove('hidden');
                anyVisible = true;
            } else {
                card.classList.add('hidden');
            }
        });

        document.getElementById('noResultsMessage').style.display = anyVisible ? 'none' : 'block';
    }

    searchInput.addEventListener('input', filterPosts);
    filterSelect.addEventListener('change', filterPosts);
    statusFilter.addEventListener('change', filterPosts);

    filterPosts(); // run once on load
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const calendarEl = document.getElementById('calendar');

    const events = [
    <?php foreach ($posts as $post):

    if (!empty($post['draft'])) continue;

    $postId = $post['id'] ?? '';
    $content = addslashes(substr($post['content'] ?? 'No content',0,50));
    $publish = $post['publish_at'] ?? '';

    $accountType = strtolower($post['account_type'] ?? '');

    if (str_contains($accountType,'twitter')) $platformKey = 'twitter';
    elseif (str_contains($accountType,'mastodon')) $platformKey = 'mastodon';
    elseif (str_contains($accountType,'facebook')) $platformKey = 'facebook';
    elseif (str_contains($accountType,'instagram')) $platformKey = 'instagram';
    elseif (str_contains($accountType,'linkedin')) $platformKey = 'linkedin';
    else $platformKey = 'unknown';

    $status = !empty($post['published']) ? 'published' : 'scheduled';

    $redirect = "post-view.php?id=$postId";
?>
{
    title: "<?= $content ?>",
    start: "<?= $publish ?>",
    url: "<?= $redirect ?>",
    extendedProps: {
        status: "<?= $status ?>",
        platform: "<?= $platformKey ?>"
    }
},
<?php endforeach; ?>
    ];

    const calendar = new FullCalendar.Calendar(calendarEl, {

        initialView: 'dayGridMonth',
        height: "auto",

        headerToolbar: {
            left: 'prev',
            center: 'title',
            right: 'next'
        },

        events: events,

        dayMaxEvents: 2, // 👈 max posts shown per day

        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        },

        moreLinkContent: function(arg) {
            return "+ More"; // 👈 custom text
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },

        eventDidMount: function(info) {

            const status = info.event.extendedProps.status;
            const platform = info.event.extendedProps.platform;

            if (status === "scheduled")
                info.el.style.backgroundColor = "#d5a664";

            if (status === "published")
                info.el.style.backgroundColor = "#59b062";

            let icon = "fa-share";

            if (platform === "facebook") icon = "fa-facebook";
            if (platform === "twitter") icon = "fa-twitter";
            if (platform === "instagram") icon = "fa-instagram";
            if (platform === "linkedin") icon = "fa-linkedin";
            if (platform === "mastodon") icon = "fa-mastodon";

            const timeEl = info.el.querySelector('.fc-event-time');

            if (timeEl) {
                const iconEl = document.createElement("i");
                iconEl.className = `fab ${icon}`;
                iconEl.style.marginRight = "6px";

                timeEl.prepend(iconEl); // 👈 places icon BEFORE the time
            }
        }

    });

    calendar.render();
});
</script>

</body>

</html>
