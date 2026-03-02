<?php
/**
 * AI-NOC — Admin Alerts Configuration
 * File: /admin/alerts.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\{Auth, CSRF, Helpers};

Auth::requireLogin();

// Recent alerts
$alerts = $db->fetchAll(
    "SELECT a.*, i.title as inc_title, i.severity, p.name as provider_name 
     FROM alerts a 
     LEFT JOIN incidents i ON a.incident_id = i.id 
     LEFT JOIN providers p ON i.provider_id = p.id 
     ORDER BY a.sent_ts DESC LIMIT 50"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts — AI-NOC Admin</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <header class="noc-header">
        <div class="container">
            <div class="header-row">
                <div class="header-brand"><span class="header-dot pulse"></span><h1>AI-NOC Admin</h1></div>
                <nav class="header-nav">
                    <a href="../index.php">Dashboard</a>
                    <a href="settings.php">Settings</a>
                    <a href="providers.php">Providers</a>
                    <a href="alerts.php" class="active">Alerts</a>
                    <a href="diagnostics.php">Diagnostics</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container admin-main">
        <h1 class="page-title">Alert History</h1>
        <p class="page-subtitle">Configure alert channels in <a href="settings.php">Settings</a>.</p>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Channel</th>
                        <th>Provider</th>
                        <th>Incident</th>
                        <th>Severity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="6" class="empty-msg">No alerts sent yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($alerts as $a): ?>
                    <tr>
                        <td class="mono"><?= Helpers::e($a['sent_ts']) ?></td>
                        <td class="mono upper"><?= Helpers::e($a['channel']) ?></td>
                        <td><?= Helpers::e($a['provider_name'] ?? '—') ?></td>
                        <td><?= Helpers::e($a['inc_title'] ?? '—') ?></td>
                        <td><span class="severity-badge severity-<?= Helpers::e($a['severity'] ?? 'unknown') ?>"><?= Helpers::e($a['severity'] ?? '—') ?></span></td>
                        <td><span class="alert-status-<?= $a['status'] ?>"><?= Helpers::e($a['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
