<?php
/**
 * AI-NOC — Cron: Evaluate Incidents
 * File: /cron/evaluate_incidents.php
 */

declare(strict_types=1);
define('AINOC_CLI', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once AINOC_ROOT . '/includes/http.php';
require_once AINOC_ROOT . '/includes/incident_engine.php';
require_once AINOC_ROOT . '/includes/alert_engine.php';

use AiNoc\{Helpers, IncidentEngine, AlertEngine};

$cronId = $db->insert('cron_runs', [
    'script' => 'evaluate_incidents',
    'started_at' => date('Y-m-d H:i:s'),
    'status' => 'running',
]);

try {
    $actions = IncidentEngine::evaluate();

    // Queue alerts for new/escalated incidents
    if (!empty($actions)) {
        AlertEngine::queueAlerts($actions);
    }

    $summary = count($actions) . " actions: " . implode(', ', array_map(
        fn($a) => "{$a['action']}({$a['provider']})",
        $actions
    ));

    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'done',
        'details' => $summary ?: 'No state changes',
    ], 'id = ?', [$cronId]);

    Helpers::log('info', "evaluate_incidents: {$summary}", 'cron.log');

} catch (\Throwable $e) {
    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'error',
        'details' => $e->getMessage(),
    ], 'id = ?', [$cronId]);
    Helpers::log('error', "evaluate_incidents error: " . $e->getMessage(), 'cron.log');
    exit(1);
}
