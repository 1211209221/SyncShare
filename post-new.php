<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsplash Image Selector</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<!-- Unsplash Modal -->
<div class="modal fade" id="unsplashModal">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Select up to 4 images</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="search" class="form-control" placeholder="Search images">
                    <button class="btn btn-primary" onclick="searchImages(true)">Search</button>
                </div>

                <div id="results"></div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="confirmSelection()" data-bs-dismiss="modal">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>


<?php
session_start();



// ✅ Your Pixazo API Key
$pixazoApiKey = "17c0f129d252488eb099ad0f16da85d0";

// 🔹 AJAX: Generate AI Image
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax_generate_image"])) {

    header("Content-Type: application/json");

    $prompt = trim($_POST["prompt"] ?? "");

    if (empty($prompt)) {
        echo json_encode([
            "success" => false,
            "message" => "Prompt is required"
        ]);
        exit;
    }

    $pixazoUrl = "https://gateway.pixazo.ai/getImage/v1/getSDXLImage";

    $payload = json_encode([
        "prompt" => $prompt,
        "negative_prompt" => "low quality, blurry",
        "height" => 1024,
        "width" => 1024,
        "num_steps" => 20,
        "guidance_scale" => 5,
        "seed" => rand(1, 999999)
    ]);

    $ch = curl_init($pixazoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Ocp-Apim-Subscription-Key: $pixazoApiKey"
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($result, true);

    // 🔹 Proper JSON response for JS
    if ($httpCode === 200 && isset($decoded['imageUrl'])) {
        echo json_encode([
            "success" => true,
            "imageUrl" => $decoded['imageUrl']
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Image generation failed",
            "rawResponse" => $result
        ]);
    }

    exit;
}
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST["ajax_generate_image"])) {
    $accounts_selected = isset($_POST['accounts']) ? array_map('intval', $_POST['accounts']) : [];
    $content = trim($_POST['content'] ?? '');
    $upload_tokens = [];

    // Override content with AI output if available
    if (!empty($_POST['ai_output'])) {
        $aiOutputText = trim($_POST['ai_output']);
        if (!empty($aiOutputText)) $content = $aiOutputText;
    }

    // 🔹 2️⃣ Handle Unsplash-selected images
    // 🔹 2️⃣ Handle library images (Unsplash + AI)
    if (!empty($_POST['library_images'])) {

        $images = json_decode($_POST['library_images'], true);

        if (is_array($images)) {

            foreach ($images as $img) {

                // If it's a local uploaded AI image
                if (file_exists($img)) {

                    $result = uploadMediaToSocialBu($img, $token);

                } else {

                    // Otherwise treat as URL (Unsplash)
                    $imageData = @file_get_contents($img);

                    if ($imageData !== false) {

                        $tempFile = tempnam(sys_get_temp_dir(), 'lib_');
                        file_put_contents($tempFile, $imageData);

                        $result = uploadMediaToSocialBu($tempFile, $token);

                        unlink($tempFile);

                    } else {
                        continue;
                    }
                }

                if (!empty($result['success'])) {
                    $upload_tokens[] = $result['upload_token'];
                }
            }
        }
    }

    // 🔹 3️⃣ Validate
    if (empty($accounts_selected)) {
        $responseMessage .= "<div class='alert alert-warning'>⚠️ Please select at least one account.</div>";
    } elseif (empty($content)) {
        $responseMessage .= "<div class='alert alert-warning'>⚠️ Post content cannot be empty.</div>";
    } else {
        // Convert Malaysia time to UTC
        $publish_at_input = trim($_POST['publish_at'] ?? '');
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

        // 🔹 4️⃣ Create Post
        $attachments = array_map(fn($t) => ["upload_token" => $t], $upload_tokens);
        $payload = [
            "accounts" => $accounts_selected,
            "publish_at" => $publish_at,
            "content" => $content,
            "draft" => isset($_POST['draft']),
            "existing_attachments" => $attachments,
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
<title>Create Post | New Post</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
.account-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-bottom: 20px; }
.account-item { display: flex; align-items: center; gap: 10px; background: #f3f4f6 !important; outline: none !important;
  padding: 8px 12px; border-radius: 8px; border: 0px solid #e0e0e0; cursor: pointer; transition: 0.2s; justify-content: space-between;}
.account-item:hover { background: #e9f5ff; }
.account-item img { width: 47px; height: 47px; border-radius: 50%; margin-right: 10px;}
footer { text-align: center; margin-top: 25px; color: #9a97a7; }
#aiOutput{
      height: 160px;

}


button#applyAI:hover{
    background-color: #1b78aeff !important;
}


.account-item.active {
    background-color: #69c6df !important;
    color: white;
}

.account-item.active small {
    color: #ffffff !important;
}

.account-item.active:hover {
    background-color: #1b78aeff !important;
}

.account-item:hover {
    background-color: #dfdee2 !important;
}


/* Masonry layout for Unsplash results */
#results {
    column-count: 4;
    column-gap: 15px;
}

.image-result {
    width: 100%;
    display: block;
    margin-bottom: 15px;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.2s;
}

.image-result:hover {
    transform: scale(1.05);
}

.image-result.selected {
    border: 5px solid #0d6efd;
}

/* Preview thumbnails */
.preview-thumb {
    width: 100px;
    border-radius: 10px;
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
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    color: #9a97a7;
    width: 50%;
    margin: 0px 10px 20px 10px;
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

input, select, button, span, textarea{
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
textarea, input{
    background: #f3f4f6 !important;
}
textarea{
    resize: none;
}
.form-label{
    font-size: 17px;
    font-weight: bold;
}
.account-checkbox {
    color: white;
    margin-left: auto;
    accent-color: #69c6df;
    width: 20px;      
    height: 20px;   
    transition: 0.2s;  
    border: 0 !important;
    outline: none !important;
}

.account-checkbox:hover {
    color: white;
    margin-left: auto;
    accent-color: #1b78aeff !important;
    width: 20px;      
    height: 20px;
    transition: 0.2s;
}

button{
    transition: 0.2s !important;
    color: white !important;
}

button[type="submit"]{
    font-size: 20px;
    font-weight:  bold;
    padding: 10px 0px;
}
button[type="submit"]:hover {
    transform: scale(1.02);
    background-color: #1b78aeff !important;
}

.btn:hover {
    background-color: #1b78aeff !important;
}

.preview-wrapper {
    position: relative;
    display: inline-block;
    cursor: pointer;
}

.preview-thumb {
    width: 100px;
    height: 100px;
    border-radius: 10px;
    object-fit: cover;
    transition: 0.2s;
    display: block;
}

.preview-wrapper:hover .preview-thumb {
    filter: brightness(50%);
}

.remove-icon {
    position: absolute;
    top: 5px;
    right: 5px;
    color: white;
    font-weight: bold;
    font-size: 18px;
    opacity: 0;
    transition: opacity 0.2s;
    pointer-events: none; /* allows clicking on the image itself */
}

.preview-wrapper:hover .remove-icon {
    opacity: 1;
    pointer-events: auto; /* enable click */
}

label{
    color: #312b2f;
}

#aiImageResult img{
    transition: 0.2s;
}

#aiImageResult img:hover{
    transform: scale(1.03);
    filter: brightness(50%);
}

</style>
</head>
<body>
<div class="wrapper">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div style="width:100%;">
            <div class="py-3 px-3" style="background-color: white; margin-bottom: 20px;"><h1>New Post</h1></div>
                <div class="d-flex" style="width: 100%;">
                    <form method="POST" enctype="multipart/form-data"  style="width: 100%;">
                        <div class="newpost container container-fluid" id="newpost" style="display: flex; justify-content: center;">
                            <div class="ui-container">
                                <h2 style="color: #312b2f; font-size: 24px;">Create a New Post <i class="fas fa-pen" style="padding-left: 7px; font-size: 21px;"></i></h2>
                                <hr style="margin: 10px 0px 7px 0px;">
                                <div class="accounts mt-3">
                                    <div class="mb-3">
                                        <label class="form-label">Select Accounts</label>
                                        <div class="account-list">
                                            <?php if (!empty($accounts)): ?>
                                            <?php foreach ($accounts as $acc): ?>
                                                <label class="account-item">
                                                    <div style="justify-content: space-between;display: flex;">
                                                        <img src="<?= htmlspecialchars($acc['image']) ?>" alt="icon">

                                                        <span class="account-info">
                                                            <?php if (!empty($acc['name'])): ?>
                                                                <div><strong><?= htmlspecialchars($acc['name']) ?></strong></div>
                                                                <small><?= htmlspecialchars($acc['_type']) ?></small>
                                                            <?php else: ?>
                                                                <small><?= htmlspecialchars($acc['_type']) ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>

                                                    <input type="checkbox" name="accounts[]" value="<?= htmlspecialchars($acc['id']) ?>" class="account-checkbox">

                                                </label>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <p class="text-muted">No connected accounts found or error fetching accounts.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Post Content</label>
                                        <textarea style="height: 160px;" class="form-control" id="postContent" name="content" rows="4" placeholder="Write your post here..." required></textarea>
                                    </div>
                                    
                                    <input type="hidden" id="library_images" name="library_images">

                                    <div class="mb-3">
                                    <label class="form-label">Attach Images</label>

                                    <!-- Unsplash button -->
                                    <button type="button" style="margin-left: 20px;" class="btn btn-outline-primary mb-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#unsplashModal">
                                        <i class="fas fa-images" style="margin-right: 6px;"></i> Browse Library
                                    </button>

                                    <!-- File upload -->
                                    <input type="file" class="form-control" name="media[]" multiple accept="image/*">

                                    <!-- Preview -->
                                    <div id="previewContainer" class="d-flex gap-2 flex-wrap mt-2"></div>

                                    <small class="text-muted">
                                        You can upload files or select from Unsplash.
                                    </small>
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

                                    <button type="submit" class="btn btn-primary w-100">Create Post</button>
                                    

                                    <!-- <?php if ($responseMessage): ?>
                                        <div class="mt-4">
                                        <h5>Response</h5>
                                        <?= $responseMessage ?>
                                        </div>
                                    <?php endif; ?> -->

                                    <footer>
                                        <a href="dashboard.php">← Back to Dashboard</a>
                                    </footer>
                                </div>
                            </div>
                            <div class="ui-container second" style="width: 45% !important;">
                                <h2 style="color: #312b2f; font-size: 24px;">AI Assistant <i class="fas fa-cog" style="padding-left: 5px;"></i></h2>
                                <hr style="margin: 10px 0px 16px 0px;">
                                <div class="mb-3">
                                    <label class="form-label">AI-Rewrite</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="aiInstruction" class="form-control" placeholder="e.g., Make this sound more friendly">
                                        <button type="button" id="applyAI" class="btn btn-outline-secondary"><i class="fas fa-magic" style="margin-right: 6px;"></i> Apply AI</button>
                                    </div>
                                    <small class="text-muted">Enter your instruction or choose one of the style options below.</small>

                                    <!-- ✅ Style Options -->
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        <label><input type="checkbox" class="ai-option" value="short"> Short</label>
                                        <label><input type="checkbox" class="ai-option" value="detailed"> Detailed</label>
                                        <label><input type="checkbox" class="ai-option" value="hashtags"> Hashtags</label>
                                        <label><input type="checkbox" class="ai-option" value="professional"> Professional</label>
                                        <label><input type="checkbox" class="ai-option" value="trendy"> Trendy</label>
                                        <label><input type="checkbox" class="ai-option" value="emojis"> Emojis</label>
                                    </div>

                                    <!-- ✅ Separate AI output -->
                                    <textarea class="form-control mt-3" id="aiOutput" name="ai_output" rows="4" placeholder="AI rewrite will appear here..."></textarea>
                                    <div id="aiMessage" class="mt-2"></div>
                                </div>  

                                <div class="mb-3">
                                    <label class="form-label">AI Image Generator</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="aiImagePrompt" class="form-control" placeholder="Describe the image you want">
                                        <button type="button" id="generateAIImage" class="btn btn-outline-secondary">
                                            <i class="fas fa-image" style="margin-right: 6px;"></i> Generate Image
                                        </button>
                                    </div>
                                    <small class="text-muted">Enter a prompt to generate an image using Gemini AI.</small>
                                    <div id="aiImageMessage" class="mt-2"></div>
                                    <div id="aiImageResult" class="mt-3 d-flex flex-wrap gap-2"></div>
                                </div>
                                <?php if (!empty($generatedImageUrl)): ?>
                                    <h3>Generated Image:</h3>
                                    <img src="<?php echo htmlspecialchars($generatedImageUrl); ?>" 
                                        style="max-width: 500px; border-radius: 10px;">
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
<script>

document.querySelectorAll('.account-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const parent = this.closest('.account-item');

        if (this.checked) {
            parent.classList.add('active');
        } else {
            parent.classList.remove('active');
        }
    });
});

document.getElementById('applyAI').addEventListener('click', async () => {
  const content = document.getElementById('postContent').value.trim();
  const instruction = document.getElementById('aiInstruction').value.trim();
  const options = Array.from(document.querySelectorAll('.ai-option:checked')).map(cb => cb.value);
  const messageBox = document.getElementById('aiMessage');
  const aiOutput = document.getElementById('aiOutput');
  const button = document.getElementById('applyAI');

  messageBox.innerHTML = '';
  aiOutput.value = '';

  if (!content && !instruction && options.length === 0) {
    messageBox.innerHTML = `<div class="text-danger mt-1">⚠️ Please enter content or select an AI option.</div>`;
    return;
  }

  button.disabled = true;
  button.textContent = "⏳ Processing...";

  try {
    // --------------------------
    // Construct AI instruction with style
    // --------------------------
    let charInstruction = '';
    let minLength = 0, maxLength = 0;
    let styleInstructions = [];

    options.forEach(opt => {
      switch (opt) {
        case 'short': charInstruction = 'Rewrite this text to be within 70–100 characters'; minLength = 70; maxLength = 100; break;
        case 'detailed': charInstruction = 'Rewrite this text to be within 450–500 characters'; minLength = 450; maxLength = 500; break;
        case 'hashtags': styleInstructions.push('add relevant hashtags at the end'); break;
        case 'professional': styleInstructions.push('use a professional and polished tone'); break;
        case 'trendy': styleInstructions.push('use Gen Z slang'); break;
        case 'emojis': styleInstructions.push('include suitable emojis naturally'); break;
      }
    });

    let fullInstruction = '';
    if (charInstruction) fullInstruction += charInstruction + '.';
    if (instruction) fullInstruction += (fullInstruction ? ' ' : '') + instruction + '.';
    if (styleInstructions.length) fullInstruction += ' ' + styleInstructions.join(', ') + '.';
    fullInstruction += ' Keep it clear, readable, and within the specified character range.';

    // --------------------------
    // Debug: Show full prompt on page
    // --------------------------
    const debugContainer = document.getElementById('aiPromptDebug');
    if (debugContainer) debugContainer.innerText = fullInstruction;

    // --------------------------
    // Send request to AI
    // --------------------------
    const res = await fetch('ai_rewrite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ content, instruction: fullInstruction })
    });

    const data = await res.json();

    // --------------------------
    // Determine selected accounts and max character limit
    // --------------------------
    const selectedCheckboxes = document.querySelectorAll('input[name="accounts[]"]:checked');
    let twitterSelected = false;
    let mastodonSelected = false;

    selectedCheckboxes.forEach(cb => {
      const labelText = cb.closest('.account-item')?.innerText.toLowerCase() || '';
      if (labelText.includes('twitter')) twitterSelected = true;
      if (labelText.includes('mastodon')) mastodonSelected = true;
    });

    let enforceCharLimit = false;
    let charLimit = 0;
    if (twitterSelected && mastodonSelected) {
      enforceCharLimit = true;
      charLimit = 280; // lower limit
    } else if (twitterSelected) {
      enforceCharLimit = true;
      charLimit = 280;
    } else if (mastodonSelected) {
      enforceCharLimit = true;
      charLimit = 500;
    }

    // --------------------------
    // Handle AI response and show warnings
    // --------------------------
    if (data.modified) {
      let outputText = data.modified;
      aiOutput.value = outputText;

      if (enforceCharLimit) {
        if (outputText.length > charLimit) {
          messageBox.innerHTML = `<div class="text-warning mt-1">⚠️ AI output exceeds ${charLimit} characters (${outputText.length}).</div>`;
        } else {
          messageBox.innerHTML = `<div class="text-success mt-1">✅ AI output is within the character limit (${outputText.length}/${charLimit}) for selected platform(s).</div>`;
        }
      } else {
        messageBox.innerHTML = `<div class="text-success mt-1">✅ AI rewrite generated successfully (${outputText.length} characters).</div>`;
      }

    } else {
      messageBox.innerHTML = `<div class="text-danger mt-1">❌ AI failed: ${data.error || 'Unknown error'}</div>`;
    }

  } catch (err) {
    messageBox.innerHTML = `<div class="text-danger mt-1">❌ Error connecting to AI: ${err.message}</div>`;
  }

  button.disabled = false;
  button.textContent = "✨ Apply AI";


  const form = document.querySelector('form');
    /*form.addEventListener('submit', () => {
        const aiOutput = document.getElementById('aiOutput').value.trim();
        if (aiOutput) {
            document.getElementById('postContent').value = aiOutput;
        }
    });*/
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const UNSPLASH_KEY = "ss5ZA_F9VdyaTWXOm6v3k3lWPvXOpAfftC6k-2ESEHE";
let selected = [];       // Unsplash-selected images
let uploadedFiles = [];  // User-uploaded images
let currentPage = 1;

const previewContainer = document.getElementById('previewContainer');
const fileInput = document.querySelector('input[type="file"][name="media[]"]');

/* ===============================
   Search Images from Unsplash
================================ */
async function searchImages(reset = false, page = 1) {
    if (reset) currentPage = 1;

    const query = document.getElementById("search").value || "nature";
    const res = await fetch(
        `https://api.unsplash.com/search/photos?per_page=30&page=${page}&query=${query}&client_id=${UNSPLASH_KEY}`
    );
    const data = await res.json();
    let html = "";

    if (data.results && data.results.length > 0) {
        data.results.forEach(img => {
            html += `
                <img src="${img.urls.small}" 
                     class="image-result" 
                     onclick="toggleSelect('${img.urls.full}', this)">
            `;
        });
    } else {
        html = `<p class="text-center text-muted mt-3">No results found.</p>`;
    }

    if (page === 1) {
        document.getElementById("results").innerHTML = html;
    } else {
        document.getElementById("results").innerHTML += html;
    }

    // Load More button
    if (data.total && currentPage * 30 < data.total) {
        if (!document.getElementById("loadMoreBtn")) {
            const btn = document.createElement("button");
            btn.id = "loadMoreBtn";
            btn.className = "btn btn-secondary w-100 mt-2";
            btn.textContent = "Load More";
            btn.onclick = () => {
                currentPage++;
                searchImages(false, currentPage);
            };
            document.getElementById("results").after(btn);
        }
    } else {
        const btn = document.getElementById("loadMoreBtn");
        if (btn) btn.remove();
    }
}

/* ===============================
   Toggle Unsplash Selection
================================ */
function toggleSelect(url, el) {
    if (selected.includes(url)) {
        selected = selected.filter(i => i !== url);
        el.classList.remove("selected");
    } else {
        if (selected.length >= 4) {
            alert("Maximum 4 images");
            return;
        }
        selected.push(url);
        el.classList.add("selected");
    }
}

/* ===============================
   Confirm Unsplash Selection
================================ */
function confirmSelection() {
    renderPreview();
    updateLibraryInput();
}

/* ===============================
   Render Preview (Unsplash + Uploaded)
================================ */
function renderPreview() {
    previewContainer.innerHTML = '';

    // Uploaded images first
    uploadedFiles.forEach(file => {
        const wrapper = document.createElement('div');
        wrapper.className = 'preview-wrapper';
        wrapper.dataset.type = 'uploaded';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'preview-thumb';

        const removeIcon = document.createElement('span');
        removeIcon.className = 'remove-icon';
        removeIcon.textContent = '✖';

        wrapper.addEventListener('click', () => {
            previewContainer.removeChild(wrapper);
            uploadedFiles = uploadedFiles.filter(f => f !== file);
            updateLibraryInput();
            fileInput.value = '';
        });

        wrapper.appendChild(img);
        wrapper.appendChild(removeIcon);
        previewContainer.appendChild(wrapper);
    });

    // Unsplash images
    selected.forEach(url => {
        const wrapper = document.createElement('div');
        wrapper.className = 'preview-wrapper';
        wrapper.dataset.type = 'unsplash';

        const img = document.createElement('img');
        img.src = url;
        img.className = 'preview-thumb';

        const removeIcon = document.createElement('span');
        removeIcon.className = 'remove-icon';
        removeIcon.textContent = '✖';

        wrapper.addEventListener('click', () => {
            previewContainer.removeChild(wrapper);
            selected = selected.filter(u => u !== url);
            updateLibraryInput();
        });

        wrapper.appendChild(img);
        wrapper.appendChild(removeIcon);
        previewContainer.appendChild(wrapper);
    });
}

/* ===============================
   Update Hidden Input
================================ */
function updateLibraryInput() {
    const uploadedNames = uploadedFiles.map(f => f.name); 
    document.getElementById('library_images').value = JSON.stringify([...selected, ...uploadedNames]);
}

/* ===============================
   File Upload Preview (Attach Once)
================================ */
fileInput.addEventListener('change', (e) => {
    const files = Array.from(e.target.files);
    files.forEach(file => {
        uploadedFiles.push(file); // add file first
    });
    renderPreview();
    updateLibraryInput();
});

/* ===============================
   Auto Load Unsplash on Modal Open
================================ */
document.getElementById('unsplashModal').addEventListener('shown.bs.modal', () => {
    if (!document.getElementById("results").innerHTML) {
        currentPage = 1;
        searchImages();
    }
});

document.querySelector("form").addEventListener("submit", function(e) {

    const checkedAccounts = document.querySelectorAll('.account-checkbox:checked');

    if (checkedAccounts.length === 0) {
        e.preventDefault(); // stop form submission
        alert("⚠️ Please select at least one account before creating the post.");

        // scroll to the accounts section
        document.querySelector(".account-list").scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }

});
</script>


<script>
document.getElementById("generateAIImage").addEventListener("click", async function () {
    const prompt = document.getElementById("aiImagePrompt").value.trim();
    const messageBox = document.getElementById("aiImageMessage");
    const resultBox = document.getElementById("aiImageResult");

    if (!prompt) {
        messageBox.innerHTML = "<span class='text-danger'>Please enter a prompt.</span>";
        return;
    }

    messageBox.innerHTML = "<span class='text-info'>Generating image... please wait.</span>";
    resultBox.innerHTML = "";

    try {
        const formData = new FormData();
        formData.append("ajax_generate_image", "1");
        formData.append("prompt", prompt);

        const res = await fetch("ai_generate_image.php", { method: "POST", body: formData });
        const data = await res.json();

        if (data.success && data.imageUrl) {
            // --- Wrapper div ---
            const wrapper = document.createElement("div");
            wrapper.style.position = "relative";
            wrapper.style.display = "inline-block";
            wrapper.style.cursor = "pointer";
            wrapper.style.marginRight = "10px";

            // --- Image element ---
            const img = document.createElement("img");
            img.src = data.imageUrl;
            img.style.maxWidth = "200px";
            img.style.borderRadius = "10px";
            img.style.display = "block";

            // --- Hover overlay text ---
            const overlay = document.createElement("div");
            overlay.textContent = "Add Image";
            overlay.style.position = "absolute";
            overlay.style.top = "0";
            overlay.style.left = "0";
            overlay.style.width = "100%";
            overlay.style.height = "100%";
            overlay.style.display = "flex";
            overlay.style.justifyContent = "center";
            overlay.style.alignItems = "center";
            overlay.style.color = "white";
            overlay.style.fontWeight = "bold";
            overlay.style.fontSize = "18px";
            overlay.style.backgroundColor = "rgba(0,0,0,0.5)";
            overlay.style.borderRadius = "10px";
            overlay.style.opacity = "0";
            overlay.style.transition = "opacity 0.2s";

            wrapper.addEventListener("mouseenter", () => overlay.style.opacity = "1");
            wrapper.addEventListener("mouseleave", () => overlay.style.opacity = "0");

            wrapper.addEventListener("click", async () => {

    messageBox.innerHTML = "<span class='text-info'>Downloading image...</span>";

    try {

        const formData = new FormData();
        formData.append("imageUrl", data.imageUrl);

        const resp = await fetch("upload_image.php", {
            method: "POST",
            body: formData
        });

        const result = await resp.json();

        if (!result.success) {
            messageBox.innerHTML = "<span class='text-danger'>❌ " + result.message + "</span>";
            return;
        }

        const savedPath = result.filename;

        // Add to hidden input for submission
        let currentImages = [];

        try {
            currentImages = JSON.parse(document.getElementById("library_images").value || "[]");
        } catch(e){}

        currentImages.push(savedPath);

        document.getElementById("library_images").value = JSON.stringify(currentImages);

        // Show preview
        const preview = document.createElement("img");
        preview.src = savedPath;
        preview.className = "preview-thumb";

        document.getElementById("previewContainer").appendChild(preview);

        messageBox.innerHTML = "<span class='text-success'>✅ Image added to post.</span>";

    } catch(err) {
        console.error(err);
        messageBox.innerHTML = "<span class='text-danger'>❌ Failed to download image</span>";
    }
});

            wrapper.appendChild(img);
            wrapper.appendChild(overlay);
            resultBox.innerHTML = "";
            resultBox.appendChild(wrapper);

            messageBox.innerHTML = "<span class='text-success'>Image generated successfully! Hover and click 'Add Image' to include it in your post.</span>";
        } else {
            messageBox.innerHTML = `<span class='text-danger'>❌ ${data.message || 'Image generation failed.'}</span>`;
        }
    } catch (err) {
        console.error(err);
        messageBox.innerHTML = `<span class='text-danger'>❌ Error: ${err.message}</span>`;
    }
});
</script>
<!-- Debug container somewhere below the AI input -->
<!-- <pre id="aiPromptDebug" style="background:#eef;padding:10px;border-radius:8px;margin-top:10px;"></pre> -->
</body>
</html>
