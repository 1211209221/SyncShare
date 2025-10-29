<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $ch = curl_init('https://socialbu.com/api/v1/auth/get_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!empty($data['authToken'])) {
        $_SESSION['token'] = $data['authToken'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Login failed. Please check your credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - SocialBu Dashboard</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 40px; }
form {
    background: #fff; padding: 20px; border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    max-width: 400px; margin: auto;
}
input, button { width: 100%; padding: 10px; margin: 10px 0; font-size: 16px; }
button { background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
button:hover { background: #0056b3; }
.error { color: red; }
</style>
</head>
<body>
<h2>Login to SocialBu Dashboard</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
</form>
</body>
</html>
