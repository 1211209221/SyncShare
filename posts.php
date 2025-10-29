<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];
$responseMessage = "";

// 🔹 Fetch connected accounts from SocialBu API
$ch = curl_init("https://socialbu.com/api/v1/accounts");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]
]);
$accountsResponse = curl_exec($ch);
curl_close($ch);
$accounts = json_decode($accountsResponse, true);
if (!is_array($accounts)) $accounts = [];

// 🔹 Handle new post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedAccounts = isset($_POST['accounts']) ? array_map('intval', $_POST['accounts']) : [];

    $data = [
        "accounts" => $selectedAccounts,
        "publish_at" => $_POST['publish_at'] ?? "",
        "content" => $_POST['content'] ?? "",
        "draft" => isset($_POST['draft']) ? true : false,
        "existing_attachments" => [],
        "options" => new stdClass(),
        "postback_url" => "",
        "queue_ids" => [],
        "team_id" => null
    ];

    $ch = curl_init("https://socialbu.com/api/v1/posts");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseMessage = "<pre style='background:#f8f9fa;padding:10px;border-radius:8px;'>
HTTP $httpcode Response:
" . htmlspecialchars($response) . "
</pre>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Post | Dashboard</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  background: #f5f6fa;
  font-family: "Segoe UI", sans-serif;
}
.container {
  max-width: 800px;
  margin-top: 40px;
  background: #fff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
}
h1 {
  font-size: 1.8rem;
  margin-bottom: 20px;
  text-align: center;
}
.form-label {
  font-weight: 500;
}
textarea {
  resize: vertical;
}
footer {
  margin-top: 20px;
  text-align: center;
  color: #888;
}
.account-option {
  display: flex;
  align-items: center;
  gap: 8px;
}
.account-option img {
  border-radius: 50%;
  border: 1px solid #ccc;
}
</style>
</head>

<body>
<div class="container">
  <h1>📝 Create a New Post</h1>

  <form method="POST">
    <div class="mb-3">
        <label class="form-label">Select Connected Accounts</label>
        <div class="border rounded p-3" style="max-height: 250px; overflow-y: auto;">
            <?php if (!empty($accounts)): ?>
            <?php foreach ($accounts as $acc): ?>
                <div class="form-check mb-2">
                <input 
                    class="form-check-input" 
                    type="checkbox" 
                    name="accounts[]" 
                    id="acc<?= htmlspecialchars($acc['id']) ?>" 
                    value="<?= htmlspecialchars($acc['id']) ?>">
                <label class="form-check-label" for="acc<?= htmlspecialchars($acc['id']) ?>">
                    <img src="<?= htmlspecialchars($acc['image'] ?? 'https://socialbu.com/images/no-image.png') ?>" 
                        alt="Profile" width="25" height="25" 
                        style="border-radius:50%; border:1px solid #ccc; margin-right:8px;">
                    <strong><?= htmlspecialchars($acc['name'] ?? 'Unnamed Account') ?></strong>
                    <small class="text-muted">
                    (<?= htmlspecialchars($acc['_type'] ?? $acc['type'] ?? 'Unknown') ?>)
                    </small>
                </label>
                </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-muted">⚠️ No connected accounts found.</p>
            <?php endif; ?>
        </div>
        <small class="text-muted">Select one or more accounts to publish to.</small>
        </div>


    <div class="mb-3">
      <label class="form-label">Post Content</label>
      <textarea class="form-control" name="content" rows="4" placeholder="Write your post here..." required></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Publish At (UTC)</label>
      <input type="datetime-local" class="form-control" name="publish_at" required>
      <small class="text-muted">Use UTC time (e.g. 2025-10-30 15:00:00)</small>
    </div>

    <div class="form-check mb-3">
      <input type="checkbox" class="form-check-input" name="draft" id="draft">
      <label for="draft" class="form-check-label">Save as Draft</label>
    </div>

    <button type="submit" class="btn btn-primary w-100">🚀 Create Post</button>
  </form>

  <?php if ($responseMessage): ?>
    <div class="mt-4">
      <h5>Response</h5>
      <?= $responseMessage ?>
    </div>
  <?php endif; ?>

  <footer>
    <small>Connected as: <b><?= htmlspecialchars($_SESSION['email'] ?? 'Unknown') ?></b></small><br>
    <a href="dashboard.php">← Back to Dashboard</a>
  </footer>
</div>

</body>
</html>
