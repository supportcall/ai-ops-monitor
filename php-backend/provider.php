<?php
/**
 * AI-NOC — Provider Detail Page
 * File: /provider.php
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

use AiNoc\Helpers;

if (!$config['PUBLIC_DASHBOARD'] && !\AiNoc\Auth::isLoggedIn()) {
    Helpers::redirect(Helpers::baseUrl('admin/login.php'));
}

$slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'] ?? '');
if (empty($slug)) Helpers::redirect(Helpers::baseUrl());

$provider = $db->fetchOne("SELECT * FROM providers WHERE slug = ? AND enabled = 1", [$slug]);
if (!$provider) {
    http_response_code(404);
    die('Provider not found');
}

$providerId = $provider['id'];

// Current state
$state = $db->fetchOne("SELECT * FROM provider_state WHERE provider_id = ? ORDER BY ts DESC LIMIT 1", [$providerId]);
$currentState = $state['state'] ?? 'unknown';

// Endpoints
$endpoints = $db->fetchAll("SELECT * FROM endpoints WHERE provider_id = ? ORDER BY name", [$providerId]);

// Enrich endpoints with last check
foreach ($endpoints as &$ep) {
    $ep['last_check'] = $db->fetchOne(
        "SELECT * FROM checks WHERE endpoint_id = ? ORDER BY ts DESC LIMIT 1", [$ep['id']]
    );
}
unset($ep);

// Latency data (24h)
$latencyData = $db->fetchAll(
    "SELECT strftime('%H:%M', c.ts) as label, AVG(c.total_ms) as p50, MAX(c.total_ms) as p95
     FROM checks c JOIN endpoints e ON c.endpoint_id = e.id
     WHERE e.provider_id = ? AND c.ts > datetime('now', '-1 day') AND c.success = 1
     GROUP BY strftime('%Y-%m-%d %H', c.ts) ORDER BY c.ts ASC",
    [$providerId]
);

// Uptime heatmap (90 days)
$uptimeData = $db->fetchAll(
    "SELECT date(c.ts) as day, COUNT(*) as total, SUM(c.success) as ok
     FROM checks c JOIN endpoints e ON c.endpoint_id = e.id
     WHERE e.provider_id = ? AND c.ts > datetime('now', '-90 days')
     GROUP BY date(c.ts) ORDER BY day ASC",
    [$providerId]
);

// Incidents
$incidents = $db->fetchAll(
    "SELECT * FROM incidents WHERE provider_id = ? ORDER BY start_ts DESC LIMIT 20",
    [$providerId]
);

// Uptime stats
$uptimeStats = $db->fetchOne(
    "SELECT COUNT(*) as total, SUM(success) as ok FROM checks c
     JOIN endpoints e ON c.endpoint_id = e.id
     WHERE e.provider_id = ? AND c.ts > datetime('now', '-30 days')",
    [$providerId]
);
$uptimePct = ($uptimeStats['total'] > 0) ? round(($uptimeStats['ok'] / $uptimeStats['total']) * 100, 2) : null;

$avgLatency = $db->fetchOne(
    "SELECT AVG(total_ms) as avg_ms FROM checks c
     JOIN endpoints e ON c.endpoint_id = e.id
     WHERE e.provider_id = ? AND c.ts > datetime('now', '-1 hour') AND c.success = 1",
    [$providerId]
);

$lastCheck = $db->fetchOne(
    "SELECT MAX(c.ts) as ts FROM checks c JOIN endpoints e ON c.endpoint_id = e.id WHERE e.provider_id = ?",
    [$providerId]
);

$pageTitle = $provider['name'] . ' — AI-NOC';
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
                    <a href="incidents.php">Incidents</a>
                    <?php if (\AiNoc\Auth::isLoggedIn()): ?>
                        <a href="admin/settings.php">Admin</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>

        <div class="provider-header">
            <div class="provider-info">
                <div class="provider-name-row">
                    <span class="status-dot state-dot-<?= Helpers::e($currentState) ?> <?= $currentState === 'outage' ? 'pulse' : '' ?>"></span>
                    <h1><?= Helpers::e($provider['name']) ?></h1>
                </div>
                <div class="provider-meta">
                    <span class="state-label state-<?= Helpers::e($currentState) ?>"><?= Helpers::e(Helpers::statusLabel($currentState)) ?></span>
                    <span class="meta-sep">•</span>
                    <?php if ($provider['official_status_url']): ?>
                        <span class="meta-badge official">Official feed</span>
                        <a href="<?= Helpers::e($provider['official_status_url']) ?>" target="_blank" rel="noopener" class="meta-link">Status page ↗</a>
                    <?php else: ?>
                        <span class="meta-badge unofficial">Synthetic only</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-label">Uptime (30d)</div>
                    <div class="stat-value"><?= $uptimePct !== null ? $uptimePct . '%' : '—' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Latency</div>
                    <div class="stat-value"><?= $avgLatency['avg_ms'] ? round((float)$avgLatency['avg_ms']) . 'ms' : '—' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Last Check</div>
                    <div class="stat-value"><?= $lastCheck['ts'] ? Helpers::timeAgo(new \DateTime($lastCheck['ts'])) : '—' ?></div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="two-col equal">
            <section class="section">
                <h2 class="section-title"><?= Helpers::e($provider['name']) ?> Latency (24h)</h2>
                <div class="chart-container">
                    <canvas id="providerLatencyChart" height="200"></canvas>
                </div>
            </section>
            <section class="section">
                <h2 class="section-title">90-Day Uptime</h2>
                <div class="heatmap" id="uptimeHeatmap">
                    <?php foreach ($uptimeData as $day):
                        $pct = ($day['total'] > 0) ? ($day['ok'] / $day['total']) * 100 : 0;
                        $cls = $pct >= 99.5 ? 'ok' : ($pct >= 97 ? 'degraded' : ($pct >= 95 ? 'partial' : ($pct > 0 ? 'outage' : 'unknown')));
                    ?>
                        <div class="heatmap-cell hm-<?= $cls ?>" title="<?= Helpers::e($day['day']) ?>: <?= round($pct, 1) ?>%"></div>
                    <?php endforeach; ?>
                    <?php if (empty($uptimeData)): ?>
                        <p class="empty-msg">No data yet — checks will populate this heatmap.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Endpoints -->
        <section class="section">
            <h2 class="section-title">Monitored Endpoints</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Latency</th>
                            <th>Last Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoints as $ep): ?>
                        <tr>
                            <td><?= Helpers::e($ep['name']) ?></td>
                            <td class="mono"><?= Helpers::e($ep['url']) ?></td>
                            <td class="mono upper"><?= Helpers::e($ep['type']) ?></td>
                            <td>
                                <?php if ($ep['last_check']): ?>
                                    <span class="status-dot state-dot-<?= $ep['last_check']['success'] ? 'ok' : 'outage' ?>"></span>
                                    <?= $ep['last_check']['success'] ? 'OK' : 'FAIL' ?>
                                <?php else: ?>
                                    <span class="dim">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= $ep['last_check'] ? Helpers::formatMs((float)$ep['last_check']['total_ms']) : '—' ?></td>
                            <td class="mono"><?= $ep['last_check'] ? Helpers::timeAgo(new \DateTime($ep['last_check']['ts'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Incidents -->
        <?php if (!empty($incidents)): ?>
        <section class="section">
            <h2 class="section-title">Recent Incidents</h2>
            <div class="incident-list">
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
                    <p class="inc-desc"><?= Helpers::e($inc['description'] ?? '') ?></p>
                    <div class="inc-meta">
                        <span>Started <?= Helpers::timeAgo(new \DateTime($inc['start_ts'])) ?></span>
                        <span class="meta-sep">•</span>
                        <span class="inc-source"><?= Helpers::e($inc['source']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer class="noc-footer">
        <div class="container footer-row">
            <span>AI-NOC v<?= AINOC_VERSION ?></span>
            <span>Auto-refreshes every 60s</span>
        </div>
    </footer>

    <script src="assets/vendor/chart.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/charts.js"></script>
    <script>
        setTimeout(() => location.reload(), 60000);
        window.providerLatencyData = <?= json_encode($latencyData) ?>;
    </script>
</body>
</html>
