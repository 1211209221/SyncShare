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
// Current Malaysia time
date_default_timezone_set('Asia/Kuala_Lumpur');
$minDateTime = date('Y-m-d\TH:i'); // Format required for datetime-local
?>
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

    echo "<pre>==== STEP 1: FILE VALIDATION ====\n";

    if (!file_exists($filePath)) {
        echo "File does NOT exist: $filePath\n</pre>";
        return ["error" => "File not found: $filePath"];
    }

    echo "File exists: $filePath\n";

    $fileName = basename($filePath);
    $mimeType = mime_content_type($filePath);
    $fileSize = filesize($filePath);

    echo "Filename: $fileName\n";
    echo "Mime type: $mimeType\n";
    echo "File size: $fileSize bytes\n";



    echo "\n==== STEP 2: REQUEST SIGNED URL ====\n";

    $payload = json_encode([
        "name" => $fileName,
        "mime_type" => $mimeType
    ]);

    echo "Payload sent:\n$payload\n";

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
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "cURL Error: $curlError\n";
    echo "Response:\n$resp\n";

    if ($httpCode !== 200) {
        echo "</pre>";
        return ["error" => "Failed to get signed URL. HTTP $httpCode: $resp"];
    }



    echo "\n==== STEP 3: PARSE SIGNED URL ====\n";

    $data = json_decode($resp, true);

    if (!$data) {
        echo "JSON decode failed\n</pre>";
        return ["error" => "Invalid JSON response"];
    }

    print_r($data);

    if (empty($data['signed_url']) || empty($data['key'])) {
        echo "Missing signed_url or key\n</pre>";
        return ["error" => "Invalid response: $resp"];
    }

    $signedUrl = $data['signed_url'];
    $key = $data['key'];

    echo "Signed URL:\n$signedUrl\n";
    echo "Key:\n$key\n";



    echo "\n==== STEP 4: UPLOAD FILE TO SIGNED URL ====\n";

    $fileData = file_get_contents($filePath);

    $ch = curl_init($signedUrl);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $fileData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-amz-acl: private",
            "Content-Type: $mimeType",
            "Content-Length: " . strlen($fileData)
        ]
    ]);

    $uploadResp = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);

    curl_close($ch);
    echo "Upload HTTP Code: {$info['http_code']}\n";
    echo "Upload cURL Error: $error\n";
    echo "Upload Response:\n$uploadResp\n";

    if (!in_array($info['http_code'], [200, 201])) {
        echo "</pre>";
        return [
            "error" => "Upload failed. HTTP {$info['http_code']}. cURL error: $error",
            "response" => $uploadResp
        ];
    }



    echo "\n==== STEP 5: VERIFY UPLOAD STATUS ====\n";

    $attempts = 0;
    $uploadToken = null;

    while ($attempts < 5 && !$uploadToken) {

        echo "Attempt #" . ($attempts + 1) . "\n";

        $statusUrl = "https://socialbu.com/api/v1/upload_media/status?key=" . urlencode($key);

        echo "Status URL: $statusUrl\n";

        $ch = curl_init($statusUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token"
            ]
        ]);

        $statusResp = curl_exec($ch);
        $statusError = curl_error($ch);
        curl_close($ch);

        echo "Status cURL Error: $statusError\n";
        echo "Status Response:\n$statusResp\n";

        $statusData = json_decode($statusResp, true);

        if (!empty($statusData['upload_token'])) {
            $uploadToken = $statusData['upload_token'];
            echo "Upload token received: $uploadToken\n";
            break;
        }

        $attempts++;
        sleep(2);
    }

    echo "\n==== FINAL RESULT ====\n";

    if (!$uploadToken) {
        echo "Upload verification failed\n</pre>";
        return ["error" => "Upload verification failed", "response" => $statusResp];
    }

    echo "SUCCESS\n";
    echo "Upload Token: $uploadToken\n";
    echo "</pre>";

    return [
        "success" => true,
        "upload_token" => $uploadToken
    ];
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
// 🔹 Step 2: Handle Post Update
// ===========================
$postId = $_GET['id'] ?? null;
if (!$postId) die("❌ No post ID provided.");

// Only proceed for form submission (excluding AI AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST["ajax_generate_image"])) {

    $isDraftChecked = isset($_POST['draft']) ? true : false; // true if checked, false if not
    $accounts_selected = isset($_POST['accounts']) ? array_map('intval', $_POST['accounts']) : [];
    $content = trim($_POST['content'] ?? '');
    $upload_tokens = [];

    // ===========================
    // 🔹 Handle Uploaded Files
    // ===========================
    if (!empty($_FILES['media']['name'][0])) {
        foreach ($_FILES['media']['tmp_name'] as $key => $tmpPath) {
            if ($_FILES['media']['error'][$key] === UPLOAD_ERR_OK) {
                $result = uploadMediaToSocialBu($tmpPath, $token);
                if (!empty($result['success'])) {
                    $upload_tokens[] = $result['upload_token'];
                } else {
                    error_log("Failed to upload library image $img: " . json_encode($result));
                }
            }
        }
    }

    // ===========================
    // 🔹 Handle Library Images
    // ===========================
    if (!empty($_POST['library_images'])) {
        $images = json_decode($_POST['library_images'], true);
        if (is_array($images)) {
            foreach ($images as $img) {
                if (file_exists($img)) {
                    $result = uploadMediaToSocialBu($img, $token);
                } else {
                    $imageData = @file_get_contents($img);
                    if ($imageData !== false) {
                        $tempFile = tempnam(sys_get_temp_dir(), 'lib_');
                        file_put_contents($tempFile, $imageData);
                        $result = uploadMediaToSocialBu($tempFile, $token);
                        unlink($tempFile);
                    } else continue;
                }
                if (!empty($result['success'])) {
                    $upload_tokens[] = $result['upload_token'];
                }
            }
        }
    }

    // ===========================
    // 🔹 Validation
    // ===========================
    if (empty($content)) {
        $responseMessage .= "<div class='alert alert-warning'>⚠️ Post content cannot be empty.</div>";
    } else {
        // Convert Malaysia time → UTC
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
        
       // Grab existing attachments from submitted form
        $existingTokens = $_POST['existing_attachments'] ?? [];

        // Convert to SocialBu payload format
        $existingAttachmentsPayload = array_map(function($token){
            return ["upload_token" => $token];
        }, $existingTokens);

        // Add newly uploaded files
        $newAttachmentsPayload = array_map(function($token){
            return ["upload_token" => $token];
        }, $upload_tokens);

        // Merge them
        $allAttachmentsPayload = array_merge($existingAttachmentsPayload, $newAttachmentsPayload);

        // Build payload
        $payload = [
            "accounts" => $accounts_selected,
            "content" => $content,
            "publish_at" => $publish_at,
            "draft" => $isDraftChecked,
            "team_id" => 0,
            "options" => new stdClass()
        ];
        $json_payload = json_encode($payload, JSON_PRETTY_PRINT);

        // PATCH request to update post
// -----------------------------
// DEBUG: Show payload and headers
// -----------------------------

$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
];

// PATCH request
$ch = curl_init("https://socialbu.com/api/v1/posts/$postId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $json_payload
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


curl_close($ch);
        if ($httpcode >= 200 && $httpcode < 300) {

            $responseMessage .= '
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert" style="margin:0 !important;border:none !important;outline:none !important;color:#668f6c;">
                <i class="fas fa-check-circle" style="margin-right:10px;"></i>
                <b>Post updated successfully!</b>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="background-color:transparent !important;"></button>
            </div>';

        } else {

            $responseMessage .= '
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert" style="margin:0 !important;border:none !important;outline:none !important;color:#683636;">
                <i class="fas fa-times-circle" style="margin-right:10px;"></i>
                <b>Failed to update post. Please try again.</b>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="background-color:transparent !important;"></button>
            </div>';

        }
    }

    // Redirect if post was published (not a draft)
    if ($httpcode === 200 && !$isDraftChecked) {
        header("Location: post-all.php");
        exit;
    }

}
?>
<?php
    // ===========================
    // Timezone settings
    // ===========================
    date_default_timezone_set('Asia/Kuala_Lumpur');

    // Minimum selectable datetime (cannot pick past)
    $minDateTime = date('Y-m-d\TH:i'); // format required by datetime-local

    // Helper: Convert UTC → Malaysia time for input
    function utcToMalaysiaInput($utcTime) {
        if (empty($utcTime)) return '';
        $dt = new DateTime($utcTime, new DateTimeZone('UTC')); // assume UTC from API
        $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur')); // convert to Malaysia time
        return $dt->format('Y-m-d\TH:i'); // format for datetime-local
    }

    $postId = $_GET['id'] ?? null;
    if (!$postId) die("Post ID missing.");

    // Fetch the post details
    $ch = curl_init("https://socialbu.com/api/v1/posts/$postId");
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

    if ($httpCode !== 200) die("Failed to fetch post details. HTTP code: $httpCode");

    $post = json_decode($response, true);

    // Sanitize and set defaults
    $postContent = htmlspecialchars($post['content'] ?? '');
    $selectedAccounts = $post['account_ids'] ?? [$post['account_id'] ?? ''];
    $attachments = $post['attachments'] ?? [];
    $publishAt = !empty($post['publish_at']) ? utcToMalaysiaInput($post['publish_at']) : '';
    $isDraft = !empty($post['draft']);
    $aiOutput = htmlspecialchars($post['ai_output'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Post | Edit Draft</title>
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
    background: #04a3ce !important;
}
button[type="submit"]:hover {
    transform: scale(1.02);
    background: #1b78aeff !important;
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

.account-item input {
    cursor: pointer;
}

</style>
</head>
<body>
<div class="wrapper">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div style="width:100%;">
            <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white;">
                <h1 class="mb-0">Edit Draft</h1>
                <div class="dropdown">
                    <button class="btn dropdown-toggle signout" type="button" data-bs-toggle="dropdown"
                        style="background:none;color:#312b2f !important;font-weight:bold;margin:0!important;">
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
            <?php if (!empty($responseMessage)): ?>
                <div style="width: 100%;">
                    <?= $responseMessage ?>
                </div>
            <?php endif; ?>
                <div class="d-flex" style="width: 100%; margin-top: 20px;">
                    <form method="POST" enctype="multipart/form-data"  style="width: 100%;">
                        <div class="newpost container container-fluid" id="newpost">
                            <div style="display: flex; justify-content: center;" class="container">
                            <div style="width: 53%; margin: 0px 20px 15px 10px; color: #44424d;">
                                <a href="dashboard.php">Posts</a> > <a href="post-all.php">Post List</a> > <a style="color: #04a3ce !important; font-weight: bold;">Edit Draft</a>
                            </div>
                            <div style="width: 35%; margin: 0px 10px;"></div>
                        </div>
                            <div style="display: flex; justify-content: center;">
                                <div class="ui-container">
                                    <h2 style="color: #312b2f; font-size: 24px;">Edit Draft <i class="fas fa-pen" style="padding-left: 7px; font-size: 21px;"></i></h2>
                                    <hr style="margin: 10px 0px 7px 0px;">
                                    <div class="accounts mt-3">
                                        <div class="mb-3">
                                            <label class="form-label">Selected Account</label>
                                            <div class="account-list">
                                                <?php if (!empty($accounts) && !empty($selectedAccounts)): ?>
                                                    <?php 
                                                    // Ensure selectedAccounts is an array of integers
                                                    $selectedAccounts = array_map('intval', $selectedAccounts);  
                                                    ?>
                                                    <?php foreach ($accounts as $acc): 
                                                        $accId = intval($acc['id']); 
                                                        
                                                        // Only show if account is selected
                                                        if (!in_array($accId, $selectedAccounts)) continue;

                                                        $isChecked = 'checked';
                                                    ?>
                                                        <label class="account-item">
                                                            <div style="justify-content: space-between; display: flex;">
                                                                <img src="<?= htmlspecialchars($acc['image']) ?>" alt="icon" style="width:40px; height:40px; object-fit:cover;">
                                                                <span class="account-info">
                                                                    <?php if (!empty($acc['name'])): ?>
                                                                        <div><strong><?= htmlspecialchars($acc['name']) ?></strong></div>
                                                                        <small><?= htmlspecialchars($acc['_type']) ?></small>
                                                                    <?php else: ?>
                                                                        <small><?= htmlspecialchars($acc['_type']) ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                            <!-- Checkbox is checked and disabled -->
                                                            <input type="checkbox" class="account-checkbox" name="accounts[]" value="<?= $accId ?>" <?= $isChecked ?> disabled>
                                                        </label>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted">No connected accounts found or no account selected for this post.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Post Content</label>
                                            <textarea style="height: 160px;" class="form-control" id="postContent" name="content" rows="4" placeholder="Write your post here..." required><?= $postContent ?></textarea>
                                        </div>
                                        <input type="hidden" id="library_images" name="library_images" value='[]'>

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

                                        <!-- Preview Container -->
                                        <div id="previewContainer" class="d-flex gap-2 flex-wrap mt-2">
                                            <?php if (!empty($attachments) && is_array($attachments)): ?>
                                                <?php foreach ($attachments as $att): ?>
                                                    <?php if (!empty($att['url'])): ?>
                                                        <div class="preview-wrapper" style="width:100px; height:100px;">
                                                            <img src="<?= htmlspecialchars($att['url']) ?>" 
                                                                alt="<?= htmlspecialchars($att['name'] ?? 'attachment') ?>" 
                                                                class="img-thumbnail w-100 h-100" 
                                                                style="object-fit: cover; padding: 0px !important;">
                                                            <!-- Optional: hidden input for UI reference, won't be submitted -->
                                                            <input type="hidden" name="existing_attachments_ui[]" 
                                                                value="<?= htmlspecialchars($att['url']) ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-muted">No attachments available.</p>
                                            <?php endif; ?>
                                        </div>

                                        <small class="text-muted">
                                            You can upload files or select from Unsplash.
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Publish At (Malaysia Time)</label>
                                        <input type="datetime-local" class="form-control" name="publish_at"
                                            min="<?= $minDateTime ?>" value="<?= $publishAt ?>">
                                        <small class="text-muted">Leave empty to publish immediately.</small>
                                    </div>

                                    <div class="form-check mb-3" style="padding-left: 0px; display: flex;">
                                        <input type="checkbox" name="draft" id="draft" style="width: 20px; height: 20px; margin-right: 10px;" <?= $isDraft ? 'checked' : '' ?>>
                                        <label for="draft" class="form-check-label" style="margin-top: -4px; font-size: 18px;">Save as Draft</label>
                                    </div>

                                    <button type="submit" id="submitBtn" class="btn btn-primary w-100"><?= $isDraft ? 'Update Draft' : 'Create Post' ?></button>
                                    </div>
                                </div>
                                <div class="ui-container second" style="width: 35% !important;">
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
                                        <textarea class="form-control mt-3" id="aiOutput" name="ai_output" rows="4" placeholder="AI rewrite will appear here..."><?= $aiOutput ?></textarea>
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
                        </div>
                        <!-- <div style="display: flex; justify-content: center;" class="container">
                            <div style="width: 50%;">
                                <button class="btn btn-outline-primary mb-2" style="color: white !important;">
                                    <a href="dashboard.php" style="color: inherit; text-decoration: none;">Back to list</a>
                                </button>
                            </div>
                            <div style="width: 35%;"></div>
                        </div> -->
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
    document.getElementById('library_images').value = JSON.stringify(selected);
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
<script>
document.addEventListener('DOMContentLoaded', () => {

    // ------------------------------
    // 1️⃣ Original post accounts
    // ------------------------------
    const postAccounts = <?= json_encode($post['accounts'] ?? []) ?>; // e.g., [171315, 171331]
    let newAccounts = [];

    // ------------------------------
    // 2️⃣ Tick original accounts and add active class
    // ------------------------------
    document.querySelectorAll('.account-checkbox').forEach(cb => {
        const accountId = parseInt(cb.value);
        if (postAccounts.includes(accountId)) {
            cb.checked = true;
            cb.closest('.account-item').classList.add('active');
        }
    });

    // ------------------------------
    // 3️⃣ Handle checkbox changes
    // ------------------------------
    document.querySelectorAll('.account-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            const parent = cb.closest('.account-item');
            parent.classList.toggle('active', cb.checked);

            const accountId = parseInt(cb.value);

            // Track new accounts
            if (!postAccounts.includes(accountId)) {
                if (cb.checked && !newAccounts.includes(accountId)) {
                    newAccounts.push(accountId);
                } else if (!cb.checked && newAccounts.includes(accountId)) {
                    newAccounts = newAccounts.filter(id => id !== accountId);
                }
            }
        });
    });

    // ------------------------------
    // 4️⃣ Optional: debug current selection
    // ------------------------------
    const debugContainer = document.getElementById('accountDebug');
    if (debugContainer) {
        document.querySelectorAll('.account-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                debugContainer.innerText = `
Original accounts: ${postAccounts.join(', ')}
New accounts: ${newAccounts.join(', ')}
All selected: ${[...document.querySelectorAll('.account-checkbox:checked')].map(c => c.value).join(', ')}
                `;
            });
        });
    }
});
</script>

<script>
// Grab elements
const draftCheckbox = document.getElementById('draft');
const submitBtn = document.getElementById('submitBtn');

// Update button text on checkbox change
draftCheckbox.addEventListener('change', function() {
    submitBtn.textContent = this.checked ? 'Update Draft' : 'Create Post';
});
</script>
<!-- Debug container somewhere below the AI input -->
<!-- <pre id="aiPromptDebug" style="background:#eef;padding:10px;border-radius:8px;margin-top:10px;"></pre> -->
</body>
</html>