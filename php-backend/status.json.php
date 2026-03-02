<?php
/**
 * AI-NOC — Public Status JSON API
 * File: /status.json.php
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

use AiNoc\Helpers;

if (!$config['PUBLIC_STATUS_JSON']) {
    Helpers::jsonResponse(['error' => 'Status API disabled'], 403);
}

$providers = $db->fetchAll("SELECT * FROM providers WHERE enabled = 1 ORDER BY name");

$result = [
    'generated_at' => date('c'),
    'version' => AINOC_VERSION,
    'providers' => [],
];

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

    $openIncidents = (int)($db->fetchOne(
        "SELECT COUNT(*) as c FROM incidents WHERE provider_id = ? AND end_ts IS NULL",
        [$p['id']]
    )['c'] ?? 0);

    $result['providers'][] = [
        'name' => $p['name'],
        'slug' => $p['slug'],
        'status' => $state['state'] ?? 'unknown',
        'source' => $state['source'] ?? 'none',
        'last_checked' => $state['ts'] ?? null,
        'latency_ms' => $latency['avg_ms'] ? round((float)$latency['avg_ms']) : null,
        'open_incidents' => $openIncidents,
        'official_status_url' => $p['official_status_url'],
    ];
}

Helpers::jsonResponse($result);
