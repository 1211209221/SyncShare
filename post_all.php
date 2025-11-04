<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];
$responseMessage = "";

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

// Convert UTC → Malaysia time (12-hour format, no seconds)
function utcToMalaysia($utcTime) {
    if (empty($utcTime)) return 'Unknown';
    try {
        $dt = new DateTime($utcTime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
        return $dt->format('Y-m-d g:i A'); // 12-hour format with AM/PM, no seconds
    } catch (Exception $e) {
        return $utcTime;
    }
}

// Fetch both scheduled and published
$scheduledPosts = fetchPosts($token, 'scheduled');
$publishedPosts = fetchPosts($token, 'published');

// Merge & sort by publish time
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f6fa; font-family: "Segoe UI", sans-serif; }
.container { max-width: 900px; margin-top: 40px; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
.post-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #f8f9fa; }
.post-header { display: flex; justify-content: space-between; align-items: center; }
.status { font-size: 0.9em; }
.status.published { color: green; }
.status.scheduled { color: #f0ad4e; }
.status.draft { color: #888; }
.small-muted { color: #666; display:block; margin-top:6px; }
</style>
</head>
<body>
<div class="container">
  <h1>📋 My Posts</h1>
  <p><a href="post_new.php" class="btn btn-primary">➕ Create New Post</a></p>

  <?php if (!empty($posts)): ?>
    <?php foreach ($posts as $post):
      $content = htmlspecialchars($post['content'] ?? '[No content]');
      $postId = htmlspecialchars($post['id'] ?? '');
      $publishAt = utcToMalaysia($post['publish_at'] ?? '');
      $createdAt = utcToMalaysia($post['created_at'] ?? '');
      $accountId = htmlspecialchars($post['account_id'] ?? '');
      $accountType = htmlspecialchars($post['account_type'] ?? '');

      if (!empty($post['draft'])) {
          $statusClass = 'draft';
          $statusLabel = 'Draft';
      } elseif (!empty($post['published'])) {
          $statusClass = 'published';
          $statusLabel = 'Published';
      } else {
          $statusClass = 'scheduled';
          $statusLabel = 'Scheduled';
      }
    ?>
      <div class="post-card">
    <div class="post-header">
        <strong><?= $content ?></strong>
        <span class="status <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>
        <!-- Post Images -->
        <?php if (!empty($post['attachments'])): ?>
            <div class="d-flex flex-wrap gap-2 my-2">
                <?php foreach ($post['attachments'] as $att): ?>
                    <?php if (!empty($att['url'])): ?>
                        <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($att['url']) ?>" 
                                style="max-width:120px; max-height:120px; object-fit:cover; border-radius:6px; border:1px solid #ddd;">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <small class="small-muted">🆔 Post ID: <b><?= $postId ?></b></small>
        <small class="small-muted">📤 Account: <b><?= $accountId ?></b> — <?= $accountType ?></small>
        <small class="small-muted">🕒 Publish at (MYT): <b><?= $publishAt ?></b></small>
        <small class="small-muted">📅 Created at (MYT): <b><?= $createdAt ?></b></small>

        <div style="margin-top:10px;">
            <a href="post_view.php?id=<?= urlencode($postId) ?>" class="btn btn-sm btn-outline-secondary">🔍 View Details</a>
        </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-muted">No posts found.</p>
  <?php endif; ?>
</div>
</body>
</html>
