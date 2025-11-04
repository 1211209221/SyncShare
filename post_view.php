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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Post</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h2>📝 Post Details</h2>

    <div class="mb-3"><strong>Post ID:</strong> <?= htmlspecialchars($post['id']) ?></div>
    <div class="mb-3"><strong>Account:</strong> <?= htmlspecialchars($post['account_type'] . ' - ' . $post['account_id']) ?></div>

    <div class="mb-3">
        <strong>Content:</strong>
        <div class="p-2 border rounded bg-light"><?= nl2br(htmlspecialchars($post['content'] ?? '')) ?></div>
    </div>

    <?php if (!empty($post['attachments'])): ?>
        <div class="mb-3">
            <strong>Attachments:</strong>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($post['attachments'] as $att): ?>
                    <?php if (!empty($att['url'])): ?>
                        <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($att['url']) ?>" alt="Attachment" style="max-width:150px; max-height:150px; object-fit:cover; border-radius:6px;">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-3"><strong>Publish Date (UTC):</strong> <?= htmlspecialchars($post['publish_at'] ?? 'N/A') ?></div>
    <div class="mb-3"><strong>Status:</strong> <?= ($post['published'] ?? false) ? 'Published' : (($post['draft'] ?? false) ? 'Draft' : 'Pending') ?></div>

    <?php if (!empty($post['permalink'])): ?>
        <a href="<?= htmlspecialchars($post['permalink']) ?>" target="_blank" class="btn btn-success mb-3">🌐 Go to Post</a>
    <?php else: ?>
        <div class="alert alert-info mb-3">Post is not published yet, no live link available.</div>
    <?php endif; ?>

    <a href="post_all.php" class="btn btn-secondary">← Back</a>
</div>
</body>
</html>
