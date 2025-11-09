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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css"> <!-- Font Awesome -->
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
    body {
        margin: 0;
        font-family: 'Lato', sans-serif;
        background: #f4f6f8;
        height: 100vh;
        display: flex;
    }

    .left-side {
        width: 50%;
        background: url('assets/images/Login.png') center center / cover no-repeat;
    }

    .right-side {
        width: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f4f6f8;
    }

    form {
        padding: 30px;
        border-radius: 8px;
        width: 100%;
        max-width: 400px;
    }

    h1 {
        text-align: center;
        margin-bottom: -5px;
        color: #04a3ce;
        font-family: 'Poppins', sans-serif;
        font-size: 50px;
    }

    .sub_title{
        text-align: center;
        margin-bottom: 45px;
        color: #969696ff;
        font-size: 15px;
        font-weight: bold;
    }

    .input-group-custom {
        display: flex;
        align-items: center;
        background: #fff;
        border-radius: 5px;
        margin-bottom: 15px;
        padding: 5px 10px;
        transition: border 0.3s ease;
    }

    .input-group-custom:focus-within {
        border-color: #04a3ce;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.15);
    }

    .input-group-custom i {
        color: #888;
        margin-right: 10px;
        font-size: 16px;
    }

    .input-group-custom input {
        border: none;
        outline: none;
        width: 100%;
        padding: 10px;
        font-size: 16px;
        background: transparent;
    }

    button {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        border-radius: 5px;
        border: none;
        background: #04a3ce;
        color: white;
        cursor: pointer;
        transition: background 0.3s;
        font-weight: 600;
        margin-top: 10px;
        transition: 0.15s ease-in-out;
    }

    button:hover {
        background: #1b78aeff;
        transform: scale(1.05);=
    }

    .error {
        color: red;
        text-align: center;
        margin-top: 10px;
    }

    @media (max-width: 768px) {
        body {
            flex-direction: column;
        }
        .left-side {
            width: 100%;
            height: 200px;
        }
        .right-side {
            width: 100%;
        }
    }
</style>
</head>
<body>
    <div class="left-side"></div>
    <div class="right-side">
        <form method="POST">
            <h1>Sync<b>Share</b></h1>

            <div class="sub_title">Login with Email</div>
            <div class="input-group-custom">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="input-group-custom">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">Login</button>

            <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        </form>
    </div>
</body>
</html>
