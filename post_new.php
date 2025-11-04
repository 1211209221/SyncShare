<?php
session_start();

// ===========================
// 1️⃣ Check API token
// ===========================
if (empty($_SESSION['token'])) die("❌ No API token found in session.");
$token = $_SESSION['token'];
$responseMessage = "";

// ===========================
// 🔹 Function: Upload Media
// ===========================
function uploadMediaToSocialBu($filePath, $token) {
    if (!file_exists($filePath)) return ["error" => "File not found: $filePath"];

    $fileName = basename($filePath);
    $mimeType = mime_content_type($filePath);

    // Step 1: Request signed URL
    $payload = json_encode(["name" => $fileName, "mime_type" => $mimeType]);
    $ch = curl_init("https://socialbu.com/api/v1/upload_media");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return ["error" => "Failed to get signed URL. HTTP $httpCode: $resp"];

    $data = json_decode($resp, true);
    if (empty($data['signed_url']) || empty($data['key'])) return ["error" => "Invalid response: $resp"];

    $signedUrl = $data['signed_url'];
    $key = $data['key'];

    // Step 2: Upload file via PUT
    $parsed = parse_url($signedUrl);
    parse_str($parsed['query'] ?? '', $query);
    $signedHeaders = explode(';', $query['X-Amz-SignedHeaders'] ?? '');
    $headersToSend = ["Content-Type: $mimeType", "Expect:"];
    foreach ($signedHeaders as $h) {
        if ($h === 'x-amz-acl') $headersToSend[] = "x-amz-acl: private";
    }

    $fp = fopen($filePath, 'rb');
    $ch = curl_init($signedUrl);
    curl_setopt_array($ch, [
        CURLOPT_PUT => true,
        CURLOPT_INFILE => $fp,
        CURLOPT_INFILESIZE => filesize($filePath),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headersToSend,
        CURLOPT_VERBOSE => true
    ]);
    $uploadResp = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!in_array($info['http_code'], [200, 201])) {
        return ["error" => "Upload failed. HTTP {$info['http_code']}. cURL error: $error", "response" => $uploadResp];
    }

    // Step 3: Verify upload status
    $attempts = 0;
    $uploadToken = null;
    while ($attempts < 5 && !$uploadToken) {
        $ch = curl_init("https://socialbu.com/api/v1/upload_media/status?key=" . urlencode($key));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]
        ]);
        $statusResp = curl_exec($ch);
        curl_close($ch);

        $statusData = json_decode($statusResp, true);
        if (!empty($statusData['upload_token'])) {
            $uploadToken = $statusData['upload_token'];
            break;
        }
        $attempts++;
        sleep(2);
    }

    if (!$uploadToken) return ["error" => "Upload verification failed", "response" => $statusResp];

    return ["success" => true, "upload_token" => $uploadToken];
}

// ===========================
// 🔹 Step 1: Fetch Accounts
// ===========================
$ch = curl_init('https://socialbu.com/api/v1/accounts');
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

$accounts = ($httpcode === 200) ? json_decode($response, true) : [];
if ($httpcode !== 200) {
    $responseMessage .= "<pre style='background:#ffe6e6;padding:10px;border-radius:8px;'>❌ Failed to fetch accounts. HTTP $httpcode\n$response</pre>";
}

// ===========================
// 🔹 Step 2: Handle Post Submission
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accounts_selected = isset($_POST['accounts']) ? array_map('intval', $_POST['accounts']) : [];
    $content = trim($_POST['content'] ?? '');
    $publish_at_input = trim($_POST['publish_at'] ?? '');
    $draft = isset($_POST['draft']);

    if (empty($accounts_selected)) {
        $responseMessage .= "<div class='alert alert-warning'>⚠️ Please select at least one account.</div>";
    } elseif (empty($content)) {
        $responseMessage .= "<div class='alert alert-warning'>⚠️ Post content cannot be empty.</div>";
    } else {
        // Convert Malaysia time to UTC
        if (!empty($publish_at_input)) {
            try {
                $local = new DateTime($publish_at_input, new DateTimeZone('Asia/Kuala_Lumpur'));
                $local->setTimezone(new DateTimeZone('UTC'));
                $publish_at = $local->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $publish_at = gmdate("Y-m-d H:i:s");
            }
        } else {
            $publish_at = gmdate("Y-m-d H:i:s");
        }

        // Upload Media
        $upload_tokens = [];
        if (!empty($_FILES['media']['tmp_name'][0])) {
            foreach ($_FILES['media']['tmp_name'] as $i => $tmpFile) {
                $result = uploadMediaToSocialBu($tmpFile, $token);
                if (!empty($result['success'])) {
                    $upload_tokens[] = $result['upload_token'];
                } else {
                    $responseMessage .= "<div class='alert alert-danger'>❌ Media upload failed: " 
                        . htmlspecialchars($result['error'] ?? 'Unknown error') 
                        . "</div>";
                    if (!empty($result['response'])) {
                        $responseMessage .= "<pre>" . htmlspecialchars($result['response']) . "</pre>";
                    }
                }
            }
        }

        // Create Post
        $attachments = array_map(fn($t) => ["upload_token" => $t], $upload_tokens);

        $payload = [
            "accounts" => $accounts_selected,
            "publish_at" => $publish_at,
            "content" => $content,
            "draft" => $draft,
            "existing_attachments" => $attachments, // wrap each token
            "options" => new stdClass(),
            "postback_url" => "",
            "queue_ids" => [],
            "team_id" => 0
        ];


        $json_payload = json_encode($payload, JSON_PRETTY_PRINT);
        $ch = curl_init("https://socialbu.com/api/v1/posts");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_payload
        ]);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $responseMessage .= "<h6>🔍 Debug Info</h6>"
            . "<pre style='background:#eef;padding:10px;border-radius:8px;'>Payload:\n"
            . htmlspecialchars($json_payload)
            . "\n\nHTTP $httpcode Response:\n"
            . htmlspecialchars($response)
            . "\n\ncURL Error: "
            . htmlspecialchars($curl_error)
            . "</pre>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Post | Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f6fa; font-family: "Segoe UI", sans-serif; }
.container { max-width: 850px; margin-top: 40px; background: #fff; padding: 30px;
  border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
.account-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-bottom: 20px; }
.account-item { display: flex; align-items: center; gap: 10px; background: #f8f9fa;
  padding: 8px 12px; border-radius: 8px; border: 1px solid #e0e0e0; cursor: pointer; transition: 0.2s; }
.account-item:hover { background: #e9f5ff; }
.account-item img { width: 32px; height: 32px; border-radius: 50%; }
h1 { text-align: center; margin-bottom: 25px; }
footer { text-align: center; margin-top: 25px; color: #888; }
</style>
</head>
<body>
<div class="container">
  <h1>📝 Create a New Post</h1>

  <form method="POST" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Select Accounts</label>
      <div class="account-list">
        <?php if (!empty($accounts)): ?>
          <?php foreach ($accounts as $acc): ?>
            <label class="account-item">
              <input type="checkbox" name="accounts[]" value="<?= htmlspecialchars($acc['id']) ?>">
              <img src="<?= htmlspecialchars($acc['image']) ?>" alt="icon">
              <span><?= htmlspecialchars($acc['_type'] . (!empty($acc['name']) ? " - " . $acc['name'] : '')) ?></span>
            </label>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted">No connected accounts found or error fetching accounts.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Post Content</label>
      <textarea class="form-control" name="content" rows="4" placeholder="Write your post here..." required></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Attach Images</label>
      <input type="file" class="form-control" name="media[]" multiple accept="image/*">
      <small class="text-muted">You can select multiple images.</small>
    </div>

    <div class="mb-3">
      <label class="form-label">Publish At (Malaysia Time)</label>
      <input type="datetime-local" class="form-control" name="publish_at">
      <small class="text-muted">Leave empty to publish immediately.</small>
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
    <a href="dashboard.php">← Back to Dashboard</a>
  </footer>
</div>
</body>
</html>
