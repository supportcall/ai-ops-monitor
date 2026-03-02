<?php
/**
 * AI-NOC — Global Incidents Page
 * File: /incidents.php
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

use AiNoc\Helpers;

if (!$config['PUBLIC_DASHBOARD'] && !\AiNoc\Auth::isLoggedIn()) {
    Helpers::redirect(Helpers::baseUrl('admin/login.php'));
}

$filter = $_GET['filter'] ?? 'all'; // all, active, resolved
$providerSlug = $_GET['provider'] ?? '';

$where = "1=1";
$params = [];

if ($filter === 'active') { $where .= " AND i.end_ts IS NULL"; }
elseif ($filter === 'resolved') { $where .= " AND i.end_ts IS NOT NULL"; }

if ($providerSlug) {
    $where .= " AND p.slug = ?";
    $params[] = $providerSlug;
}

$incidents = $db->fetchAll(
    "SELECT i.*, p.name as provider_name, p.slug as provider_slug FROM incidents i
     JOIN providers p ON i.provider_id = p.id
     WHERE {$where} ORDER BY i.start_ts DESC LIMIT 100",
    $params
);

$providers = $db->fetchAll("SELECT slug, name FROM providers WHERE enabled = 1 ORDER BY name");

$pageTitle = 'Incidents — AI-NOC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <header class="noc-header">
        <div class="container">
            <div class="header-row">
                <div class="header-brand">
                    <span class="header-dot pulse"></span>
                    <h1>AI-NOC</h1>
                </div>
                <nav class="header-nav">
                    <a href="index.php">Dashboard</a>
                    <a href="incidents.php" class="active">Incidents</a>
                    <?php if (\AiNoc\Auth::isLoggedIn()): ?>
                        <a href="admin/settings.php">Admin</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <h1 class="page-title">Incident History</h1>

        <!-- Filters -->
        <div class="filter-bar">
            <a href="incidents.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="incidents.php?filter=active" class="filter-btn <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="incidents.php?filter=resolved" class="filter-btn <?= $filter === 'resolved' ? 'active' : '' ?>">Resolved</a>
            <select class="filter-select" onchange="location.href='incidents.php?filter=<?= Helpers::e($filter) ?>&provider='+this.value">
                <option value="">All Providers</option>
                <?php foreach ($providers as $prov): ?>
                    <option value="<?= Helpers::e($prov['slug']) ?>" <?= $providerSlug === $prov['slug'] ? 'selected' : '' ?>>
                        <?= Helpers::e($prov['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Incident List -->
        <div class="incident-list">
            <?php if (empty($incidents)): ?>
                <p class="empty-msg">No incidents found.</p>
            <?php endif; ?>
            <?php foreach ($incidents as $inc): ?>
            <div class="incident-item severity-<?= Helpers::e($inc['severity']) ?>">
                <div class="inc-header">
                    <span class="inc-dot"></span>
                    <span class="inc-title"><?= Helpers::e($inc['title']) ?></span>
                    <?php if ($inc['end_ts']): ?>
                        <span class="inc-resolved">Resolved</span>
                    <?php else: ?>
                        <span class="inc-active">Active</span>
                    <?php endif; ?>
                </div>
                <a href="provider.php?slug=<?= Helpers::e($inc['provider_slug']) ?>" class="inc-provider"><?= Helpers::e($inc['provider_name']) ?></a>
                <p class="inc-desc"><?= Helpers::e($inc['description'] ?? '') ?></p>
                <div class="inc-meta">
                    <span>Started: <?= Helpers::e($inc['start_ts']) ?></span>
                    <?php if ($inc['end_ts']): ?>
                        <span class="meta-sep">•</span>
                        <span>Ended: <?= Helpers::e($inc['end_ts']) ?></span>
                    <?php endif; ?>
                    <span class="meta-sep">•</span>
                    <span class="inc-source"><?= Helpers::e($inc['source']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="noc-footer">
        <div class="container footer-row">
            <span>AI-NOC v<?= AINOC_VERSION ?></span>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
