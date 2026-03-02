<?php
/**
 * AI-NOC — Cron: Pull Official Status Feeds
 * File: /cron/pull_official.php
 */

declare(strict_types=1);
define('AINOC_CLI', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once AINOC_ROOT . '/includes/http.php';
require_once AINOC_ROOT . '/includes/status_parsers.php';

use AiNoc\{Helpers, StatusParser};

$cronId = $db->insert('cron_runs', [
    'script' => 'pull_official',
    'started_at' => date('Y-m-d H:i:s'),
    'status' => 'running',
]);

try {
    $providers = $db->fetchAll(
        "SELECT * FROM providers WHERE enabled = 1 AND official_status_url IS NOT NULL AND official_status_url != ''"
    );

    $processed = 0;

    foreach ($providers as $p) {
        $result = StatusParser::parse(
            $p['official_status_url'],
            $p['official_status_type'] ?? 'json',
            $p['metadata_json'] ?? '{}'
        );

        if ($result) {
            // Get current synthetic state
            $syntheticState = $db->fetchOne(
                "SELECT state FROM provider_state WHERE provider_id = ? AND source = 'synthetic' ORDER BY ts DESC LIMIT 1",
                [$p['id']]
            );

            // Official feed can override severity upward
            $officialState = $result['state'];
            $severityOrder = ['ok' => 0, 'unknown' => 1, 'degraded' => 2, 'partial' => 3, 'outage' => 4];
            $synSev = $severityOrder[$syntheticState['state'] ?? 'unknown'] ?? 0;
            $offSev = $severityOrder[$officialState] ?? 0;

            // Use whichever is more severe
            $finalState = ($offSev >= $synSev) ? $officialState : ($syntheticState['state'] ?? 'unknown');

            $db->insert('provider_state', [
                'provider_id' => $p['id'],
                'ts' => date('Y-m-d H:i:s'),
                'state' => $finalState,
                'reason' => $result['title'] . ': ' . substr($result['description'], 0, 200),
                'source' => 'official',
            ]);

            $processed++;
        } else {
            Helpers::log('warning', "Could not parse official feed for {$p['name']}", 'cron.log');
        }
    }

    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'done',
        'details' => "Processed {$processed}/" . count($providers) . " official feeds",
    ], 'id = ?', [$cronId]);

    Helpers::log('info', "pull_official completed: {$processed} feeds", 'cron.log');

} catch (\Throwable $e) {
    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'error',
        'details' => $e->getMessage(),
    ], 'id = ?', [$cronId]);
    Helpers::log('error', "pull_official error: " . $e->getMessage(), 'cron.log');
    exit(1);
}
