<?php
/**
 * AI-NOC — Admin Settings
 * File: /admin/settings.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\{Auth, CSRF, Helpers};

Auth::requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'general') {
        // Update config values that can be changed at runtime (stored in DB settings)
        $crypto = \AiNoc\Crypto::fromConfig();
        $settingsToSave = [
            'public_dashboard' => !empty($_POST['public_dashboard']) ? '1' : '0',
            'public_status_json' => !empty($_POST['public_status_json']) ? '1' : '0',
            'default_check_interval' => max(60, min(3600, (int)($_POST['check_interval'] ?? 300))),
            'incident_confirm_checks' => max(1, min(10, (int)($_POST['confirm_checks'] ?? 3))),
            'incident_resolve_checks' => max(1, min(10, (int)($_POST['resolve_checks'] ?? 3))),
            'retention_raw_checks' => max(7, min(365, (int)($_POST['retention_days'] ?? 90))),
        ];

        foreach ($settingsToSave as $key => $val) {
            $encrypted = $crypto->encrypt((string)$val);
            $existing = $db->fetchOne("SELECT key FROM settings WHERE key = ?", [$key]);
            if ($existing) {
                $db->update('settings', ['value_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')], "key = ?", [$key]);
            } else {
                $db->insert('settings', ['key' => $key, 'value_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $success = 'Settings saved.';
    }

    if ($action === 'smtp') {
        $crypto = \AiNoc\Crypto::fromConfig();
        $smtpFields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_from_name', 'smtp_encryption'];
        foreach ($smtpFields as $field) {
            $val = trim($_POST[$field] ?? '');
            $encrypted = $crypto->encrypt($val);
            $existing = $db->fetchOne("SELECT key FROM settings WHERE key = ?", [$field]);
            if ($existing) {
                $db->update('settings', ['value_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')], "key = ?", [$field]);
            } else {
                $db->insert('settings', ['key' => $field, 'value_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $success = 'SMTP settings saved.';
    }

    if ($action === 'webhook') {
        $crypto = \AiNoc\Crypto::fromConfig();
        $url = filter_var(trim($_POST['webhook_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
        $existing = $db->fetchOne("SELECT key FROM settings WHERE key = 'webhook_url'");
        $encrypted = $crypto->encrypt($url);
        if ($existing) {
            $db->update('settings', ['value_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')], "key = 'webhook_url'");
        } else {
            $db->insert('settings', ['key' => 'webhook_url', 'value_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $success = 'Webhook settings saved.';
    }
}

// Load current DB settings
$crypto = \AiNoc\Crypto::fromConfig();
$dbSettings = [];
$rows = $db->fetchAll("SELECT * FROM settings");
foreach ($rows as $r) {
    try {
        $dbSettings[$r['key']] = $crypto->decrypt($r['value_encrypted']);
    } catch (\Throwable $e) {
        $dbSettings[$r['key']] = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — AI-NOC Admin</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <header class="noc-header">
        <div class="container">
            <div class="header-row">
                <div class="header-brand"><span class="header-dot pulse"></span><h1>AI-NOC Admin</h1></div>
                <nav class="header-nav">
                    <a href="../index.php">Dashboard</a>
                    <a href="settings.php" class="active">Settings</a>
                    <a href="providers.php">Providers</a>
                    <a href="alerts.php">Alerts</a>
                    <a href="diagnostics.php">Diagnostics</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container admin-main">
        <h1 class="page-title">Settings</h1>

        <?php if ($success): ?><div class="alert alert-success"><?= Helpers::e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= Helpers::e($error) ?></div><?php endif; ?>

        <!-- General Settings -->
        <section class="admin-section">
            <h2>General</h2>
            <form method="post">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="general">
                <div class="form-grid">
                    <div class="form-group">
                        <label><input type="checkbox" name="public_dashboard" <?= ($dbSettings['public_dashboard'] ?? '1') === '1' ? 'checked' : '' ?>> Public dashboard (no login required)</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="public_status_json" <?= ($dbSettings['public_status_json'] ?? '1') === '1' ? 'checked' : '' ?>> Enable /status.json endpoint</label>
                    </div>
                    <div class="form-group">
                        <label for="check_interval">Default check interval (seconds)</label>
                        <input type="number" id="check_interval" name="check_interval" value="<?= Helpers::e($dbSettings['default_check_interval'] ?? '300') ?>" min="60" max="3600">
                    </div>
                    <div class="form-group">
                        <label for="confirm_checks">Checks to confirm incident</label>
                        <input type="number" id="confirm_checks" name="confirm_checks" value="<?= Helpers::e($dbSettings['incident_confirm_checks'] ?? '3') ?>" min="1" max="10">
                    </div>
                    <div class="form-group">
                        <label for="resolve_checks">Checks to resolve incident</label>
                        <input type="number" id="resolve_checks" name="resolve_checks" value="<?= Helpers::e($dbSettings['incident_resolve_checks'] ?? '3') ?>" min="1" max="10">
                    </div>
                    <div class="form-group">
                        <label for="retention_days">Raw check retention (days)</label>
                        <input type="number" id="retention_days" name="retention_days" value="<?= Helpers::e($dbSettings['retention_raw_checks'] ?? '90') ?>" min="7" max="365">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save General Settings</button>
            </form>
        </section>

        <!-- SMTP Settings -->
        <section class="admin-section">
            <h2>SMTP (Email Alerts)</h2>
            <form method="post">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="smtp">
                <div class="form-grid">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= Helpers::e($dbSettings['smtp_host'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?= Helpers::e($dbSettings['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP User</label>
                        <input type="text" name="smtp_user" value="<?= Helpers::e($dbSettings['smtp_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_pass" value="" placeholder="<?= !empty($dbSettings['smtp_pass']) ? '••••••••' : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="smtp_from" value="<?= Helpers::e($dbSettings['smtp_from'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?= Helpers::e($dbSettings['smtp_from_name'] ?? 'AI-NOC') ?>">
                    </div>
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption">
                            <option value="tls" <?= ($dbSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($dbSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($dbSettings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
            </form>
        </section>

        <!-- Webhook -->
        <section class="admin-section">
            <h2>Webhook</h2>
            <form method="post">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="webhook">
                <div class="form-group">
                    <label>Webhook URL (POST)</label>
                    <input type="url" name="webhook_url" value="<?= Helpers::e($dbSettings['webhook_url'] ?? '') ?>" placeholder="https://hooks.slack.com/...">
                </div>
                <button type="submit" class="btn btn-primary">Save Webhook</button>
            </form>
        </section>
    </main>
</body>
</html>
