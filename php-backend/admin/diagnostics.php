<?php
/**
 * AI-NOC — Admin Diagnostics
 * File: /admin/diagnostics.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\{Auth, Helpers};

Auth::requireLogin();

// System info
$phpVersion = PHP_VERSION;
$extensions = get_loaded_extensions();
$requiredExts = ['curl', 'pdo', 'pdo_sqlite', 'json', 'mbstring', 'openssl'];
$optionalExts = ['sodium', 'pdo_mysql', 'simplexml'];

// Cron last runs
$cronRuns = $db->fetchAll("SELECT * FROM cron_runs ORDER BY started_at DESC LIMIT 20");

// DB stats
$checkCount = (int)($db->fetchOne("SELECT COUNT(*) as c FROM checks")['c'] ?? 0);
$incidentCount = (int)($db->fetchOne("SELECT COUNT(*) as c FROM incidents")['c'] ?? 0);
$providerCount = (int)($db->fetchOne("SELECT COUNT(*) as c FROM providers")['c'] ?? 0);

// File permissions
$paths = [
    'data/' => AINOC_ROOT . '/data',
    'logs/' => AINOC_ROOT . '/logs',
    'config/' => AINOC_ROOT . '/config',
    'data/ai-noc.sqlite' => AINOC_ROOT . '/data/ai-noc.sqlite',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostics — AI-NOC Admin</title>
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
                    <a href="alerts.php">Alerts</a>
                    <a href="diagnostics.php" class="active">Diagnostics</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container admin-main">
        <h1 class="page-title">System Diagnostics</h1>

        <div class="diag-grid">
            <!-- System -->
            <section class="admin-section">
                <h2>System</h2>
                <table class="data-table">
                    <tr><td>AI-NOC Version</td><td class="mono"><?= AINOC_VERSION ?></td></tr>
                    <tr><td>PHP Version</td><td class="mono"><?= $phpVersion ?></td></tr>
                    <tr><td>Server</td><td class="mono"><?= Helpers::e($_SERVER['SERVER_SOFTWARE'] ?? 'CLI') ?></td></tr>
                    <tr><td>Timezone</td><td class="mono"><?= date_default_timezone_get() ?></td></tr>
                    <tr><td>Current Time</td><td class="mono"><?= date('Y-m-d H:i:s T') ?></td></tr>
                </table>
            </section>

            <!-- Extensions -->
            <section class="admin-section">
                <h2>PHP Extensions</h2>
                <table class="data-table">
                    <?php foreach ($requiredExts as $ext): ?>
                    <tr>
                        <td><?= $ext ?> <span class="dim">(required)</span></td>
                        <td><?= in_array($ext, $extensions) ? '<span class="status-ok">✓</span>' : '<span class="status-fail">✗ Missing</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($optionalExts as $ext): ?>
                    <tr>
                        <td><?= $ext ?> <span class="dim">(optional)</span></td>
                        <td><?= in_array($ext, $extensions) ? '<span class="status-ok">✓</span>' : '<span class="dim">—</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </section>

            <!-- DB Stats -->
            <section class="admin-section">
                <h2>Database</h2>
                <table class="data-table">
                    <tr><td>Driver</td><td class="mono"><?= $db->driver() ?></td></tr>
                    <tr><td>Providers</td><td class="mono"><?= $providerCount ?></td></tr>
                    <tr><td>Total Checks</td><td class="mono"><?= number_format($checkCount) ?></td></tr>
                    <tr><td>Total Incidents</td><td class="mono"><?= number_format($incidentCount) ?></td></tr>
                </table>
            </section>

            <!-- Permissions -->
            <section class="admin-section">
                <h2>File Permissions</h2>
                <table class="data-table">
                    <?php foreach ($paths as $label => $path): ?>
                    <tr>
                        <td class="mono"><?= $label ?></td>
                        <td>
                            <?php if (file_exists($path)): ?>
                                <?= is_writable($path) ? '<span class="status-ok">Writable ✓</span>' : '<span class="status-fail">Not writable ✗</span>' ?>
                                <span class="dim mono">(<?= substr(sprintf('%o', fileperms($path)), -4) ?>)</span>
                            <?php else: ?>
                                <span class="status-fail">Missing ✗</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </section>
        </div>

        <!-- Cron Runs -->
        <section class="admin-section">
            <h2>Recent Cron Runs</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Script</th><th>Started</th><th>Finished</th><th>Status</th><th>Details</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cronRuns)): ?>
                            <tr><td colspan="5" class="empty-msg">No cron runs recorded yet. Set up cron jobs per the documentation.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cronRuns as $run): ?>
                        <tr>
                            <td class="mono"><?= Helpers::e($run['script']) ?></td>
                            <td class="mono"><?= Helpers::e($run['started_at']) ?></td>
                            <td class="mono"><?= Helpers::e($run['finished_at'] ?? '—') ?></td>
                            <td><span class="alert-status-<?= $run['status'] ?>"><?= Helpers::e($run['status']) ?></span></td>
                            <td class="mono dim"><?= Helpers::e(substr($run['details'] ?? '', 0, 100)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
