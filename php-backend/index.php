<?php
/**
 * AI-NOC — Public Dashboard
 * File: /index.php
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

use AiNoc\Helpers;

// Check public access
if (!$config['PUBLIC_DASHBOARD'] && !\AiNoc\Auth::isLoggedIn()) {
    Helpers::redirect(Helpers::baseUrl('admin/login.php'));
}

$providers = $db->fetchAll("SELECT * FROM providers WHERE enabled = 1 ORDER BY name ASC");

// Enrich with latest state + stats
foreach ($providers as &$p) {
    $state = $db->fetchOne(
        "SELECT state, source FROM provider_state WHERE provider_id = ? ORDER BY ts DESC LIMIT 1",
        [$p['id']]
    );
    $p['current_state'] = $state['state'] ?? 'unknown';
    $p['state_source'] = $state['source'] ?? 'none';

    // Latest check latency (avg across endpoints)
    $latency = $db->fetchOne(
        "SELECT AVG(total_ms) as avg_ms FROM checks c 
         JOIN endpoints e ON c.endpoint_id = e.id 
         WHERE e.provider_id = ? AND c.ts > datetime('now', '-10 minutes')
         AND c.success = 1",
        [$p['id']]
    );
    $p['latency_ms'] = $latency['avg_ms'] ? round((float)$latency['avg_ms']) : null;

    // Uptime % (last 24h)
    $uptime = $db->fetchOne(
        "SELECT COUNT(*) as total, SUM(success) as ok FROM checks c
         JOIN endpoints e ON c.endpoint_id = e.id
         WHERE e.provider_id = ? AND c.ts > datetime('now', '-1 day')",
        [$p['id']]
    );
    $p['uptime_pct'] = ($uptime['total'] > 0) ? round(($uptime['ok'] / $uptime['total']) * 100, 2) : null;

    // Open incidents
    $p['open_incidents'] = (int)($db->fetchOne(
        "SELECT COUNT(*) as c FROM incidents WHERE provider_id = ? AND end_ts IS NULL",
        [$p['id']]
    )['c'] ?? 0);

    // Last check time
    $lastCheck = $db->fetchOne(
        "SELECT MAX(c.ts) as last_ts FROM checks c JOIN endpoints e ON c.endpoint_id = e.id WHERE e.provider_id = ?",
        [$p['id']]
    );
    $p['last_check'] = $lastCheck['last_ts'] ?? null;
}
unset($p);

// Recent incidents
$incidents = $db->fetchAll(
    "SELECT i.*, p.name as provider_name, p.slug as provider_slug FROM incidents i
     JOIN providers p ON i.provider_id = p.id
     ORDER BY i.start_ts DESC LIMIT 20"
);

// Summary counts
$statusCounts = ['ok' => 0, 'degraded' => 0, 'partial' => 0, 'outage' => 0, 'unknown' => 0];
foreach ($providers as $p) {
    $s = $p['current_state'];
    $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
}

$pageTitle = 'AI-NOC Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::e($pageTitle) ?></title>
    <meta name="description" content="AI Provider Network Operations Center — Real-time status monitoring">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <header class="noc-header">
        <div class="container">
            <div class="header-row">
                <div class="header-brand">
                    <span class="header-dot pulse"></span>
                    <h1>AI-NOC</h1>
                    <span class="header-version">v<?= AINOC_VERSION ?></span>
                </div>
                <nav class="header-nav">
                    <a href="index.php" class="active">Dashboard</a>
                    <a href="incidents.php">Incidents</a>
                    <?php if (\AiNoc\Auth::isLoggedIn()): ?>
                        <a href="admin/settings.php">Admin</a>
                        <a href="admin/logout.php">Logout</a>
                    <?php else: ?>
                        <a href="admin/login.php">Login</a>
                    <?php endif; ?>
                </nav>
                <div class="header-time" id="clock"></div>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Status Summary -->
        <section class="status-summary">
            <div class="summary-card summary-ok">
                <span class="summary-count"><?= $statusCounts['ok'] ?></span>
                <span class="summary-label">Operational</span>
            </div>
            <div class="summary-card summary-degraded">
                <span class="summary-count"><?= $statusCounts['degraded'] ?></span>
                <span class="summary-label">Degraded</span>
            </div>
            <div class="summary-card summary-partial">
                <span class="summary-count"><?= $statusCounts['partial'] ?></span>
                <span class="summary-label">Partial</span>
            </div>
            <div class="summary-card summary-outage">
                <span class="summary-count"><?= $statusCounts['outage'] ?></span>
                <span class="summary-label">Outage</span>
            </div>
            <div class="summary-card summary-unknown">
                <span class="summary-count"><?= $statusCounts['unknown'] ?></span>
                <span class="summary-label">Unknown</span>
            </div>
        </section>

        <!-- Provider Grid -->
        <section class="section">
            <h2 class="section-title">Provider Status</h2>
            <div class="provider-grid">
                <?php foreach ($providers as $p): ?>
                <a href="provider.php?slug=<?= Helpers::e($p['slug']) ?>" class="provider-tile state-<?= Helpers::e($p['current_state']) ?>">
                    <div class="tile-header">
                        <span class="tile-dot"></span>
                        <span class="tile-name"><?= Helpers::e($p['name']) ?></span>
                    </div>
                    <div class="tile-status"><?= Helpers::e(Helpers::statusLabel($p['current_state'])) ?></div>
                    <div class="tile-stats">
                        <?php if ($p['latency_ms'] !== null): ?>
                            <span class="tile-stat"><?= $p['latency_ms'] ?>ms</span>
                        <?php else: ?>
                            <span class="tile-stat dim">—</span>
                        <?php endif; ?>
                        <?php if ($p['uptime_pct'] !== null): ?>
                            <span class="tile-stat"><?= $p['uptime_pct'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="tile-footer">
                        <?php if ($p['last_check']): ?>
                            <span class="tile-time"><?= Helpers::timeAgo(new \DateTime($p['last_check'])) ?></span>
                        <?php else: ?>
                            <span class="tile-time dim">No checks yet</span>
                        <?php endif; ?>
                        <?php if (!$p['official_status_url']): ?>
                            <span class="tile-badge unofficial">Synthetic</span>
                        <?php else: ?>
                            <span class="tile-badge official">Official</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Two Column: Chart + Incidents -->
        <div class="two-col">
            <section class="section col-wide">
                <h2 class="section-title">Global Latency (24h)</h2>
                <div class="chart-container">
                    <canvas id="latencyChart" height="200"></canvas>
                    <noscript>
                        <p class="no-js-msg">Charts require JavaScript. Enable JS or view raw data in Admin.</p>
                    </noscript>
                </div>
            </section>
            <section class="section col-narrow">
                <h2 class="section-title">Recent Incidents</h2>
                <div class="incident-feed">
                    <?php if (empty($incidents)): ?>
                        <p class="empty-msg">No incidents recorded yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($incidents, 0, 10) as $inc): ?>
                        <div class="incident-item severity-<?= Helpers::e($inc['severity']) ?>">
                            <div class="inc-header">
                                <span class="inc-dot"></span>
                                <span class="inc-title"><?= Helpers::e($inc['title']) ?></span>
                                <?php if ($inc['end_ts']): ?>
                                    <span class="inc-resolved">Resolved</span>
                                <?php endif; ?>
                            </div>
                            <div class="inc-provider"><?= Helpers::e($inc['provider_name']) ?></div>
                            <div class="inc-time"><?= Helpers::timeAgo(new \DateTime($inc['start_ts'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <footer class="noc-footer">
        <div class="container footer-row">
            <span>AI-NOC v<?= AINOC_VERSION ?> — Network Operations Center</span>
            <span>Data refreshes every 60s • <span id="last-refresh">—</span></span>
        </div>
    </footer>

    <script src="assets/vendor/chart.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/charts.js"></script>
    <script>
        // Auto-refresh every 60s
        setTimeout(() => location.reload(), 60000);
        
        // Latency chart data (injected from PHP)
        <?php
        $chartData = $db->fetchAll(
            "SELECT strftime('%H:%M', c.ts) as label, 
                    AVG(c.total_ms) as avg_ms,
                    MAX(c.total_ms) as p95_ms
             FROM checks c 
             WHERE c.ts > datetime('now', '-1 day') AND c.success = 1
             GROUP BY strftime('%Y-%m-%d %H', c.ts)
             ORDER BY c.ts ASC"
        );
        ?>
        window.latencyData = <?= json_encode($chartData) ?>;
    </script>
</body>
</html>
