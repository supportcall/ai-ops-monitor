<?php
/**
 * AI-NOC — Cron: Cleanup Old Data
 * File: /cron/cleanup.php
 */

declare(strict_types=1);
define('AINOC_CLI', true);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\Helpers;

$cronId = $db->insert('cron_runs', [
    'script' => 'cleanup',
    'started_at' => date('Y-m-d H:i:s'),
    'status' => 'running',
]);

try {
    $retentionDays = (int)($config['RETENTION_RAW_CHECKS'] ?? 90);
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

    // Delete old raw checks
    $stmt = $db->query("DELETE FROM checks WHERE ts < ?", [$cutoff]);
    $deletedChecks = $stmt->rowCount();

    // Delete old provider_state records (keep 90 days)
    $stmt = $db->query("DELETE FROM provider_state WHERE ts < ?", [$cutoff]);
    $deletedStates = $stmt->rowCount();

    // Delete old resolved incidents beyond retention
    $incRetention = (int)($config['RETENTION_INCIDENTS'] ?? 365);
    $incCutoff = date('Y-m-d H:i:s', strtotime("-{$incRetention} days"));
    $stmt = $db->query("DELETE FROM incidents WHERE end_ts IS NOT NULL AND end_ts < ?", [$incCutoff]);
    $deletedInc = $stmt->rowCount();

    // Delete old cron_runs (keep 30 days)
    $db->query("DELETE FROM cron_runs WHERE started_at < ?", [date('Y-m-d H:i:s', strtotime('-30 days'))]);

    // Vacuum SQLite
    if ($db->driver() === 'sqlite') {
        $db->pdo()->exec('VACUUM');
    }

    $summary = "Deleted: {$deletedChecks} checks, {$deletedStates} states, {$deletedInc} incidents";

    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'done',
        'details' => $summary,
    ], 'id = ?', [$cronId]);

    Helpers::log('info', "cleanup: {$summary}", 'cron.log');

} catch (\Throwable $e) {
    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'error',
        'details' => $e->getMessage(),
    ], 'id = ?', [$cronId]);
    Helpers::log('error', "cleanup error: " . $e->getMessage(), 'cron.log');
    exit(1);
}
