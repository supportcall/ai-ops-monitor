<?php
/**
 * AI-NOC — Cron: Run Synthetic Checks
 * File: /cron/run_checks.php
 * 
 * Usage: php /path/to/ai-noc/cron/run_checks.php
 * Cron:  * * * * * php /home/USER/public_html/ai-noc/cron/run_checks.php >/dev/null 2>&1
 */

declare(strict_types=1);
define('AINOC_CLI', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once AINOC_ROOT . '/includes/http.php';
require_once AINOC_ROOT . '/includes/incident_engine.php';

use AiNoc\{Helpers, HttpClient, IncidentEngine};

// Log cron start
$cronId = $db->insert('cron_runs', [
    'script' => 'run_checks',
    'started_at' => date('Y-m-d H:i:s'),
    'status' => 'running',
]);

try {
    // Get all enabled providers with their endpoints
    $providers = $db->fetchAll("SELECT * FROM providers WHERE enabled = 1");
    $allEndpoints = [];
    $providerMap = [];

    foreach ($providers as $p) {
        $interval = (int)($p['check_interval'] ?? $config['DEFAULT_CHECK_INTERVAL'] ?? 300);
        
        // Check if due (last check older than interval)
        $lastCheck = $db->fetchOne(
            "SELECT MAX(c.ts) as last_ts FROM checks c 
             JOIN endpoints e ON c.endpoint_id = e.id 
             WHERE e.provider_id = ?",
            [$p['id']]
        );

        $lastTs = $lastCheck['last_ts'] ?? null;
        if ($lastTs && (time() - strtotime($lastTs)) < $interval) {
            continue; // Not due yet
        }

        $endpoints = $db->fetchAll(
            "SELECT * FROM endpoints WHERE provider_id = ? AND enabled = 1",
            [$p['id']]
        );

        foreach ($endpoints as $ep) {
            $allEndpoints[] = $ep;
            $providerMap[$ep['id']] = $p['id'];
        }
    }

    if (empty($allEndpoints)) {
        $db->update('cron_runs', [
            'finished_at' => date('Y-m-d H:i:s'),
            'status' => 'done',
            'details' => 'No checks due',
        ], 'id = ?', [$cronId]);
        exit(0);
    }

    // Run checks in parallel (batch of 50)
    $totalChecks = 0;
    $batches = array_chunk($allEndpoints, 50);

    foreach ($batches as $batch) {
        $results = HttpClient::runChecks($batch);

        foreach ($results as $epId => $result) {
            $db->insert('checks', $result);
            $totalChecks++;
        }
    }

    // Compute new state for each checked provider
    $checkedProviders = array_unique(array_values($providerMap));
    foreach ($checkedProviders as $providerId) {
        $stateResult = IncidentEngine::computeState((int)$providerId);
        $db->insert('provider_state', [
            'provider_id' => $providerId,
            'ts' => date('Y-m-d H:i:s'),
            'state' => $stateResult['state'],
            'reason' => $stateResult['reason'],
            'source' => 'synthetic',
        ]);
    }

    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'done',
        'details' => "Ran {$totalChecks} checks across " . count($checkedProviders) . " providers",
    ], 'id = ?', [$cronId]);

    Helpers::log('info', "run_checks completed: {$totalChecks} checks", 'cron.log');

} catch (\Throwable $e) {
    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'error',
        'details' => $e->getMessage(),
    ], 'id = ?', [$cronId]);
    Helpers::log('error', "run_checks error: " . $e->getMessage(), 'cron.log');
    exit(1);
}
