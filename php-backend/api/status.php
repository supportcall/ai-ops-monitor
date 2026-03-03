<?php
/**
 * AI-NOC — Public Status JSON API (unified)
 * Returns providers, incidents, latency, and heatmap data
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\Helpers;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!$config['PUBLIC_STATUS_JSON']) {
    Helpers::jsonResponse(['error' => 'Status API disabled'], 403);
}

// --- Providers ---
$providers = $db->fetchAll("SELECT * FROM providers WHERE enabled = 1 ORDER BY name ASC");

$providerList = [];
foreach ($providers as $p) {
    $state = $db->fetchOne(
        "SELECT state, source, ts FROM provider_state WHERE provider_id = ? ORDER BY ts DESC LIMIT 1",
        [$p['id']]
    );

    $latency = $db->fetchOne(
        "SELECT AVG(total_ms) as avg_ms FROM checks c JOIN endpoints e ON c.endpoint_id = e.id
         WHERE e.provider_id = ? AND c.ts > datetime('now', '-10 minutes') AND c.success = 1",
        [$p['id']]
    );

    $uptime = $db->fetchOne(
        "SELECT COUNT(*) as total, SUM(success) as ok FROM checks c
         JOIN endpoints e ON c.endpoint_id = e.id
         WHERE e.provider_id = ? AND c.ts > datetime('now', '-1 day')",
        [$p['id']]
    );

    $openIncidents = (int)($db->fetchOne(
        "SELECT COUNT(*) as c FROM incidents WHERE provider_id = ? AND end_ts IS NULL",
        [$p['id']]
    )['c'] ?? 0);

    $lastCheck = $db->fetchOne(
        "SELECT MAX(c.ts) as last_ts FROM checks c JOIN endpoints e ON c.endpoint_id = e.id WHERE e.provider_id = ?",
        [$p['id']]
    );

    $endpoints = $db->fetchAll(
        "SELECT id, name, url, type, enabled FROM endpoints WHERE provider_id = ? ORDER BY name",
        [$p['id']]
    );

    $providerList[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'slug' => $p['slug'],
        'status' => $state['state'] ?? 'unknown',
        'source' => $state['source'] ?? 'none',
        'last_checked' => $state['ts'] ?? $lastCheck['last_ts'] ?? null,
        'latency_ms' => $latency['avg_ms'] ? round((float)$latency['avg_ms']) : null,
        'uptime_percent' => ($uptime['total'] > 0) ? round(($uptime['ok'] / $uptime['total']) * 100, 2) : null,
        'open_incidents' => $openIncidents,
        'official_status_url' => $p['official_status_url'],
        'is_official' => !empty($p['official_status_url']),
        'endpoints' => array_map(fn($ep) => [
            'id' => $ep['id'],
            'name' => $ep['name'],
            'url' => $ep['url'],
            'type' => $ep['type'],
            'enabled' => (bool)$ep['enabled'],
        ], $endpoints),
    ];
}

// --- Incidents ---
$incidents = $db->fetchAll(
    "SELECT i.*, p.name as provider_name, p.slug as provider_slug FROM incidents i
     JOIN providers p ON i.provider_id = p.id
     ORDER BY i.start_ts DESC LIMIT 50"
);

$incidentList = array_map(fn($inc) => [
    'id' => $inc['id'],
    'provider_id' => $inc['provider_id'],
    'provider_name' => $inc['provider_name'],
    'provider_slug' => $inc['provider_slug'],
    'start_ts' => $inc['start_ts'],
    'end_ts' => $inc['end_ts'],
    'severity' => $inc['severity'],
    'title' => $inc['title'],
    'description' => $inc['description'] ?? '',
    'source' => $inc['source'] ?? 'synthetic',
], $incidents);

// --- Global Latency (24h, hourly buckets) ---
$latencyData = $db->fetchAll(
    "SELECT strftime('%H:%M', c.ts) as label,
            AVG(c.total_ms) as p50,
            MAX(c.total_ms) as p95
     FROM checks c
     WHERE c.ts > datetime('now', '-1 day') AND c.success = 1
     GROUP BY strftime('%Y-%m-%d %H', c.ts)
     ORDER BY c.ts ASC"
);

$latencyList = array_map(fn($row) => [
    'ts' => $row['label'],
    'p50' => $row['p50'] ? (int)round((float)$row['p50']) : 0,
    'p95' => $row['p95'] ? (int)round((float)$row['p95']) : 0,
], $latencyData);

// --- Per-provider latency (if slug is provided) ---
$providerSlug = $_GET['provider'] ?? null;
$providerLatency = [];
$providerHeatmap = [];

if ($providerSlug) {
    $prov = $db->fetchOne("SELECT id FROM providers WHERE slug = ?", [$providerSlug]);
    if ($prov) {
        $pid = $prov['id'];

        $provLatData = $db->fetchAll(
            "SELECT strftime('%H:%M', c.ts) as label,
                    AVG(c.total_ms) as p50,
                    MAX(c.total_ms) as p95
             FROM checks c JOIN endpoints e ON c.endpoint_id = e.id
             WHERE e.provider_id = ? AND c.ts > datetime('now', '-1 day') AND c.success = 1
             GROUP BY strftime('%Y-%m-%d %H', c.ts)
             ORDER BY c.ts ASC",
            [$pid]
        );
        $providerLatency = array_map(fn($row) => [
            'ts' => $row['label'],
            'p50' => $row['p50'] ? (int)round((float)$row['p50']) : 0,
            'p95' => $row['p95'] ? (int)round((float)$row['p95']) : 0,
        ], $provLatData);

        // 90-day heatmap
        $heatmapData = $db->fetchAll(
            "SELECT date(c.ts) as day,
                    COUNT(*) as total,
                    SUM(c.success) as ok
             FROM checks c JOIN endpoints e ON c.endpoint_id = e.id
             WHERE e.provider_id = ? AND c.ts > datetime('now', '-90 days')
             GROUP BY date(c.ts)
             ORDER BY day ASC",
            [$pid]
        );
        $providerHeatmap = array_map(function($row) {
            $pct = ($row['total'] > 0) ? round(($row['ok'] / $row['total']) * 100, 2) : 0;
            $status = 'ok';
            if ($pct < 95) $status = 'outage';
            elseif ($pct < 98) $status = 'degraded';
            elseif ($pct < 99.5) $status = 'partial';
            return [
                'date' => $row['day'],
                'status' => $status,
                'uptime_percent' => $pct,
            ];
        }, $heatmapData);
    }
}

// --- Global heatmap (90 days) ---
$globalHeatmap = [];
$globalHeatData = $db->fetchAll(
    "SELECT date(c.ts) as day,
            COUNT(*) as total,
            SUM(c.success) as ok
     FROM checks c
     WHERE c.ts > datetime('now', '-90 days')
     GROUP BY date(c.ts)
     ORDER BY day ASC"
);
$globalHeatmap = array_map(function($row) {
    $pct = ($row['total'] > 0) ? round(($row['ok'] / $row['total']) * 100, 2) : 0;
    $status = 'ok';
    if ($pct < 95) $status = 'outage';
    elseif ($pct < 98) $status = 'degraded';
    elseif ($pct < 99.5) $status = 'partial';
    return [
        'date' => $row['day'],
        'status' => $status,
        'uptime_percent' => $pct,
    ];
}, $globalHeatData);

$result = [
    'generated_at' => date('c'),
    'version' => AINOC_VERSION,
    'providers' => $providerList,
    'incidents' => $incidentList,
    'global_latency' => $latencyList,
    'global_heatmap' => $globalHeatmap,
];

if ($providerSlug) {
    $result['provider_latency'] = $providerLatency;
    $result['provider_heatmap'] = $providerHeatmap;
}

Helpers::jsonResponse($result);
