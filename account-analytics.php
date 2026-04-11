<?php
session_start();

// --- PHP Backend Section --- //
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    // FETCH ACCOUNTS
    if (isset($_SESSION['token']) && $input['action'] === 'fetch') {
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
        echo $response;
        exit;
    }

    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Redirect if not logged in
if (!isset($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

?>
<?php

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

$followersByAccount = [];

if (!empty($followers['data']['data']['followers_by_account'])) {
    foreach ($followers['data']['data']['followers_by_account'] as $acc) {
        $followersByAccount[$acc['account_id']] = $acc['followers'] ?? 0;
    }
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
</style>
</head>
<body>
    <div class="wrapper">
        <div class="d-flex">
            <?php
                include 'sidebar.php';
            ?>
            <div style="width:100%;">
                <pre id="output"></pre>
                <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white; margin-bottom:20px;">
                    <h1 class="mb-0">Account Analytics</h1>
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

                        <div class="ui-container">
                            <h2>Account Followers</h2>

                            <?php if($followers['http_code'] === 200): ?>
                                <h5><strong>Total Followers:</strong> <?= $followers['data']['data']['total_followers'] ?? 0 ?></h5>
                            <?php else: ?>
                                <p class="text-danger">Failed to fetch followers (HTTP <?= $followers['http_code'] ?>)</p>
                                <pre><?= htmlspecialchars($followers['raw']) ?></pre>
                            <?php endif; ?>
                            <!-- Accounts Grid -->
                            <div id="accounts" class="mt-2"></div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mt-3">Follower Growth (Last 7 Days)</h5>

                                <select id="growthMode" class="form-select" style="width:220px;">
                                    <option value="total">Total Followers</option>
                                    <option value="gained">Followers Gained / Lost</option>
                                </select>
                            </div>

                            <canvas id="followersGrowthChart" height="120"></canvas>
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

</body>
<script>
let followersChart = null;

function renderFollowersGrowthChart() {

    if (!followersGrowthData || followersGrowthData.length === 0) {
        console.log("No growth data", followersGrowthData);
        return;
    }

    const mode = document.getElementById("growthMode").value;

const labels = [];
const gainedFollowers = [];
const totalFollowers = [];

const currentTotal = <?= $followers['data']['data']['total_followers'] ?? 0 ?>;

followersGrowthData.forEach(row => {
    labels.push(row.date);
    gainedFollowers.push(row.total_followers);
});

// Build total followers history
let runningTotal = currentTotal;

for (let i = gainedFollowers.length - 1; i >= 0; i--) {
    runningTotal -= gainedFollowers[i];
    totalFollowers.unshift(runningTotal + gainedFollowers[i]);
}

// Remove first entry for growth mode (it's just the initial sync)
if (mode === "gained") {
    labels.shift();
    gainedFollowers.shift();
}

const chartData = mode === "total" ? totalFollowers : gainedFollowers;

    const chartLabel =
        mode === "total"
            ? "Total Followers"
            : "Followers Gained / Lost";

    const ctx = document.getElementById("followersGrowthChart").getContext("2d");

if (followersChart) followersChart.destroy();

followersChart = new Chart(ctx, {
    type: "line",
    data: {
        labels: labels,
        datasets: [{
            label: chartLabel,
            data: chartData,
            tension: 0.4,
            fill: true, // fill area under line
            borderWidth: 2,
            pointBackgroundColor: chartData.map(v => {
                if (mode === "total") return "#04a3ce"; // blue for total
                return v < 0 ? "#dc3545" : "#28a745";   // red/green for gained
            }),
            pointBorderColor: chartData.map(v => {
                if (mode === "total") return "#04a3ce"; // blue for total
                return v < 0 ? "#dc3545" : "#28a745";   // red/green for gained
            }),
            borderColor: mode === "total" ? "#04a3ce" : "#28a745", 
            backgroundColor: mode === "total" ? "rgba(4,163,206,0.2)" : "rgba(0,0,0,0)", // fill handled by segment plugin for gained
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: "bottom" }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: chartLabel }
            },
            x: { title: { display: true, text: "Date" } }
        },
        // only apply segment coloring for gained/lost mode
        datasets: {
            line: {
                segment: {
                    borderColor: ctx => {
                        if (mode === "total") return "#04a3ce"; // total always blue
                        return ctx.p0.parsed.y < 0 || ctx.p1.parsed.y < 0 ? "#dc3545" : "#28a745";
                    },
                    backgroundColor: ctx => {
                        if (mode === "total") return "rgba(4,163,206,0.2)";
                        return ctx.p0.parsed.y < 0 || ctx.p1.parsed.y < 0 ? "rgba(220,53,69,0.2)" : "rgba(40,167,69,0.2)";
                    }
                }
            }
        }
    }
});
}

const followersMap = <?= json_encode($followersByAccount) ?>;

async function loadAccounts() {
    const accountsDiv = document.getElementById('accounts');
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

            const div = document.createElement("div");
            div.className = "account";

            div.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${acc.image || 'https://socialbu.com/images/no-image.png'}"
                        width="50"
                        height="50"
                        style="border-radius:50%; border:1px solid #ccc;">

                    <div>
                        <strong>${acc.name || "(Unnamed Account)"}</strong><br>
                        <small>${acc._type || acc.type || "Unknown Platform"}</small><br>
                        <small><b>${followers.toLocaleString()}</b> Followers</small>
                    </div>
                </div>
            `;

            accountsDiv.appendChild(div);

        });

    } catch (error) {
        accountsDiv.innerHTML = "Error loading accounts.";
        console.error(error);
    }
}

window.onload = () => {
    loadAccounts();
    renderFollowersGrowthChart();
};

document.getElementById("growthMode").addEventListener("change", () => {
    renderFollowersGrowthChart();
});
</script>
</html>
