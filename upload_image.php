<?php
if (isset($_POST['imageUrl'])) {
    $imageUrl = $_POST['imageUrl'];
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch image.']);
        exit;
    }

    $filename = 'uploads/ai_' . time() . '.png';
    if (!is_dir('uploads')) mkdir('uploads', 0777, true);

    if (file_put_contents($filename, $imageData)) {
        // Optionally generate an upload token for your API
        $uploadToken = base64_encode($filename); // Example: your system may differ
        echo json_encode(['success' => true, 'filename' => $filename, 'upload_token' => $uploadToken]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save image.']);
    }
}
?>