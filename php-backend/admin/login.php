<?php
/**
 * AI-NOC — Admin Login
 * File: /admin/login.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\{Auth, CSRF, Helpers};

if (Auth::isLoggedIn()) {
    Helpers::redirect(Helpers::baseUrl('admin/settings.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password required.';
    } elseif (Auth::attempt($email, $password)) {
        Helpers::redirect(Helpers::baseUrl('admin/settings.php'));
    } else {
        $error = 'Invalid credentials or account locked.';
        Helpers::log('warning', "Failed login attempt for: {$email}");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — AI-NOC</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-header">
            <span class="header-dot pulse"></span>
            <h1>AI-NOC</h1>
        </div>
        <h2>Admin Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= Helpers::e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?= CSRF::field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>
    </div>
</body>
</html>
