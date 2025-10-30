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
<h2>📝 Post Details</h2>
<pre><?= htmlspecialchars(json_encode($post, JSON_PRETTY_PRINT)) ?></pre>
<a href="posts_list.php" class="btn btn-secondary">← Back</a>
</body>
</html>
