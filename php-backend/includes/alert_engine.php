<?php
/**
 * AI-NOC — Alert Engine (Email + Webhook)
 * File: /includes/alert_engine.php
 */

declare(strict_types=1);

namespace AiNoc;

class AlertEngine
{
    /**
     * Queue alerts for newly opened/escalated incidents
     */
    public static function queueAlerts(array $actions): void
    {
        global $db;

        foreach ($actions as $action) {
            if (!in_array($action['action'], ['opened', 'escalated'])) continue;
            $incidentId = $action['incident_id'] ?? null;
            if (!$incidentId) continue;

            // Check cooldown
            if (self::isInCooldown($incidentId)) continue;

            // Queue email
            if (Helpers::config('SMTP_ENABLED')) {
                $db->insert('alerts', [
                    'incident_id' => $incidentId,
                    'channel' => 'email',
                    'sent_ts' => date('Y-m-d H:i:s'),
                    'status' => 'pending',
                    'response_text' => null,
                ]);
            }

            // Queue webhook
            if (Helpers::config('WEBHOOK_ENABLED') && Helpers::config('WEBHOOK_URL')) {
                $db->insert('alerts', [
                    'incident_id' => $incidentId,
                    'channel' => 'webhook',
                    'sent_ts' => date('Y-m-d H:i:s'),
                    'status' => 'pending',
                    'response_text' => null,
                ]);
            }
        }
    }

    /**
     * Send all pending alerts
     */
    public static function sendPending(): array
    {
        global $db;
        $results = [];

        $pending = $db->fetchAll("SELECT a.*, i.title as inc_title, i.severity, i.description as inc_desc, 
            p.name as provider_name FROM alerts a 
            LEFT JOIN incidents i ON a.incident_id = i.id 
            LEFT JOIN providers p ON i.provider_id = p.id 
            WHERE a.status = 'pending' ORDER BY a.sent_ts ASC LIMIT 50");

        foreach ($pending as $alert) {
            $success = false;
            $response = '';

            if ($alert['channel'] === 'email') {
                [$success, $response] = self::sendEmail($alert);
            } elseif ($alert['channel'] === 'webhook') {
                [$success, $response] = self::sendWebhook($alert);
            }

            $db->update('alerts', [
                'status' => $success ? 'sent' : 'failed',
                'sent_ts' => date('Y-m-d H:i:s'),
                'response_text' => substr($response, 0, 500),
            ], 'id = ?', [$alert['id']]);

            $results[] = ['id' => $alert['id'], 'channel' => $alert['channel'], 'success' => $success];
        }

        return $results;
    }

    /**
     * Send email via SMTP
     */
    private static function sendEmail(array $alert): array
    {
        $host = Helpers::config('SMTP_HOST');
        $port = (int)Helpers::config('SMTP_PORT', 587);
        $user = Helpers::config('SMTP_USER');
        $pass = Helpers::config('SMTP_PASS');
        $from = Helpers::config('SMTP_FROM', 'noc@localhost');
        $fromName = Helpers::config('SMTP_FROM_NAME', 'AI-NOC');
        $encryption = Helpers::config('SMTP_ENCRYPTION', 'tls');

        if (empty($host)) return [false, 'SMTP not configured'];

        // Get admin emails
        global $db;
        $admins = $db->fetchAll("SELECT email FROM users WHERE role = 'admin'");
        if (empty($admins)) return [false, 'No admin recipients'];

        $to = array_column($admins, 'email');
        $subject = "[AI-NOC] {$alert['severity']}: {$alert['provider_name']} — {$alert['inc_title']}";
        $body = "Provider: {$alert['provider_name']}\n"
            . "Severity: " . Helpers::statusLabel($alert['severity']) . "\n"
            . "Issue: {$alert['inc_title']}\n"
            . "Details: {$alert['inc_desc']}\n\n"
            . "— AI-NOC " . Helpers::baseUrl();

        // Simple SMTP implementation using fsockopen
        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
            if (!$fp) return [false, "Connect failed: {$errstr}"];

            $resp = fgets($fp, 512);
            fputs($fp, "EHLO " . gethostname() . "\r\n"); fgets($fp, 512);

            if ($encryption === 'tls') {
                fputs($fp, "STARTTLS\r\n");
                $resp = fgets($fp, 512);
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($fp, "EHLO " . gethostname() . "\r\n");
                // Read multi-line EHLO response
                while ($line = fgets($fp, 512)) {
                    if (str_starts_with($line, '250 ')) break;
                }
            }

            fputs($fp, "AUTH LOGIN\r\n"); fgets($fp, 512);
            fputs($fp, base64_encode($user) . "\r\n"); fgets($fp, 512);
            fputs($fp, base64_encode($pass) . "\r\n");
            $authResp = fgets($fp, 512);
            if (!str_starts_with(trim($authResp), '235')) {
                fclose($fp);
                return [false, "Auth failed: {$authResp}"];
            }

            fputs($fp, "MAIL FROM:<{$from}>\r\n"); fgets($fp, 512);
            foreach ($to as $addr) {
                fputs($fp, "RCPT TO:<{$addr}>\r\n"); fgets($fp, 512);
            }
            fputs($fp, "DATA\r\n"); fgets($fp, 512);

            $headers = "From: {$fromName} <{$from}>\r\n"
                . "To: " . implode(', ', $to) . "\r\n"
                . "Subject: {$subject}\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n";

            fputs($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
            $dataResp = fgets($fp, 512);
            fputs($fp, "QUIT\r\n");
            fclose($fp);

            return [str_starts_with(trim($dataResp), '250'), $dataResp];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * Send webhook POST
     */
    private static function sendWebhook(array $alert): array
    {
        $url = Helpers::config('WEBHOOK_URL');
        if (empty($url)) return [false, 'Webhook URL not configured'];

        $payload = json_encode([
            'event' => 'incident',
            'provider' => $alert['provider_name'],
            'severity' => $alert['severity'],
            'title' => $alert['inc_title'],
            'description' => $alert['inc_desc'],
            'timestamp' => date('c'),
            'dashboard_url' => Helpers::baseUrl(),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $success = ($code >= 200 && $code < 300);
        return [$success, $error ?: "HTTP {$code}: " . substr($response, 0, 200)];
    }

    /**
     * Check if an incident is within cooldown period
     */
    private static function isInCooldown(int $incidentId): bool
    {
        global $db;
        $cooldownMins = (int)Helpers::config('ALERT_COOLDOWN_MINS', 30);

        $lastAlert = $db->fetchOne(
            "SELECT sent_ts FROM alerts WHERE incident_id = ? AND status = 'sent' ORDER BY sent_ts DESC LIMIT 1",
            [$incidentId]
        );

        if (!$lastAlert) return false;

        $lastSent = strtotime($lastAlert['sent_ts']);
        return (time() - $lastSent) < ($cooldownMins * 60);
    }

    /**
     * Generate and send daily digest
     */
    public static function sendDailyDigest(): bool
    {
        global $db;

        if (!Helpers::config('SMTP_ENABLED')) return false;

        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $incidents = $db->fetchAll(
            "SELECT i.*, p.name as provider_name FROM incidents i 
             JOIN providers p ON i.provider_id = p.id 
             WHERE i.start_ts >= ? OR (i.end_ts IS NULL) 
             ORDER BY i.start_ts DESC",
            [$yesterday . ' 00:00:00']
        );

        $providers = $db->fetchAll("SELECT * FROM providers WHERE enabled = 1");
        $okCount = 0;
        foreach ($providers as $p) {
            $state = $db->fetchOne(
                "SELECT state FROM provider_state WHERE provider_id = ? ORDER BY ts DESC LIMIT 1",
                [$p['id']]
            );
            if (($state['state'] ?? 'unknown') === 'ok') $okCount++;
        }

        $subject = "[AI-NOC] Daily Digest — " . date('M j, Y');
        $body = "AI-NOC Daily Status Digest\n"
            . "==========================\n\n"
            . "Providers: " . count($providers) . " monitored, {$okCount} operational\n\n";

        if (!empty($incidents)) {
            $body .= "Incidents (last 24h):\n";
            foreach ($incidents as $inc) {
                $status = $inc['end_ts'] ? '✓ Resolved' : '⚠ Active';
                $body .= "  [{$status}] {$inc['provider_name']}: {$inc['title']} ({$inc['severity']})\n";
            }
        } else {
            $body .= "No incidents in the last 24 hours. ✓\n";
        }

        $body .= "\n— AI-NOC " . Helpers::baseUrl();

        // Send to all admins
        $admins = $db->fetchAll("SELECT email FROM users WHERE role = 'admin'");
        $to = array_column($admins, 'email');

        if (empty($to)) return false;

        // Use PHP mail() for digest as a simpler fallback
        $headers = "From: " . Helpers::config('SMTP_FROM_NAME', 'AI-NOC') . " <" . Helpers::config('SMTP_FROM') . ">\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";

        return mail(implode(',', $to), $subject, $body, $headers);
    }
}
