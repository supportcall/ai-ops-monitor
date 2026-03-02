<?php
/**
 * AI-NOC — Cron: Send Alerts
 * File: /cron/send_alerts.php
 */

declare(strict_types=1);
define('AINOC_CLI', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once AINOC_ROOT . '/includes/http.php';
require_once AINOC_ROOT . '/includes/alert_engine.php';

use AiNoc\{Helpers, AlertEngine};

$cronId = $db->insert('cron_runs', [
    'script' => 'send_alerts',
    'started_at' => date('Y-m-d H:i:s'),
    'status' => 'running',
]);

try {
    $results = AlertEngine::sendPending();

    // Check if daily digest is due
    $digestTime = $config['DIGEST_TIME'] ?? '08:00';
    $now = date('H:i');
    if ($now >= $digestTime && $now < date('H:i', strtotime($digestTime . ' +5 minutes'))) {
        // Check if we already sent today
        $todayDigest = $db->fetchOne(
            "SELECT id FROM alerts WHERE channel = 'digest' AND sent_ts >= ?",
            [date('Y-m-d') . ' 00:00:00']
        );
        if (!$todayDigest) {
            AlertEngine::sendDailyDigest();
            $db->insert('alerts', [
                'incident_id' => null,
                'channel' => 'digest',
                'sent_ts' => date('Y-m-d H:i:s'),
                'status' => 'sent',
                'response_text' => 'Daily digest',
            ]);
        }
    }

    $sent = count(array_filter($results, fn($r) => $r['success']));
    $failed = count($results) - $sent;

    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'done',
        'details' => "Sent: {$sent}, Failed: {$failed}",
    ], 'id = ?', [$cronId]);

    Helpers::log('info', "send_alerts: {$sent} sent, {$failed} failed", 'cron.log');

} catch (\Throwable $e) {
    $db->update('cron_runs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status' => 'error',
        'details' => $e->getMessage(),
    ], 'id = ?', [$cronId]);
    Helpers::log('error', "send_alerts error: " . $e->getMessage(), 'cron.log');
    exit(1);
}
