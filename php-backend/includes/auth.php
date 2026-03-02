<?php
/**
 * AI-NOC — Authentication
 * File: /includes/auth.php
 */

declare(strict_types=1);

namespace AiNoc;

class Auth
{
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            Helpers::redirect(Helpers::baseUrl('admin/login.php'));
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        global $db, $config;

        $email = strtolower(trim($email));
        $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user) return false;

        // Check lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return false;
        }

        if (!password_verify($password, $user['pass_hash'])) {
            $attempts = $user['failed_logins'] + 1;
            $lockUntil = null;
            $maxAttempts = $config['LOGIN_MAX_ATTEMPTS'] ?? 5;
            $lockoutMins = $config['LOGIN_LOCKOUT_MINS'] ?? 15;

            if ($attempts >= $maxAttempts) {
                $lockUntil = date('Y-m-d H:i:s', time() + ($lockoutMins * 60));
                Helpers::log('warning', "Account locked: {$email}");
            }

            $db->update('users', [
                'failed_logins' => $attempts,
                'locked_until' => $lockUntil,
            ], 'id = ?', [$user['id']]);

            return false;
        }

        // Success
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();

        $db->update('users', [
            'failed_logins' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        Helpers::log('info', "Login successful: {$email}");
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function createUser(string $email, string $password, string $role = 'admin'): int
    {
        global $db;
        $hash = password_hash($password, PASSWORD_ARGON2ID) ?: password_hash($password, PASSWORD_BCRYPT);
        return (int)$db->insert('users', [
            'email' => strtolower(trim($email)),
            'pass_hash' => $hash,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
