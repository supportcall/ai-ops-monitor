<?php
/**
 * AI-NOC — Incident Engine
 * File: /includes/incident_engine.php
 */

declare(strict_types=1);

namespace AiNoc;

class IncidentEngine
{
    /**
     * Evaluate all providers and create/close incidents
     */
    public static function evaluate(): array
    {
        global $db, $config;

        $confirmN = (int)($config['INCIDENT_CONFIRM_CHECKS'] ?? 3);
        $resolveM = (int)($config['INCIDENT_RESOLVE_CHECKS'] ?? 3);
        $actions = [];

        $providers = $db->fetchAll("SELECT * FROM providers WHERE enabled = 1");

        foreach ($providers as $provider) {
            $providerId = $provider['id'];

            // Get latest states
            $states = $db->fetchAll(
                "SELECT * FROM provider_state WHERE provider_id = ? ORDER BY ts DESC LIMIT ?",
                [$providerId, max($confirmN, $resolveM)]
            );

            if (empty($states)) continue;

            $currentState = $states[0]['state'] ?? 'unknown';
            $source = $states[0]['source'] ?? 'synthetic';

            // Check for open incident
            $openIncident = $db->fetchOne(
                "SELECT * FROM incidents WHERE provider_id = ? AND end_ts IS NULL ORDER BY start_ts DESC LIMIT 1",
                [$providerId]
            );

            if ($currentState !== 'ok' && $currentState !== 'unknown') {
                // Count consecutive non-OK states
                $nonOkCount = 0;
                foreach ($states as $s) {
                    if ($s['state'] !== 'ok') $nonOkCount++;
                    else break;
                }

                if ($nonOkCount >= $confirmN && !$openIncident) {
                    // Create new incident
                    $dedupeKey = $providerId . '-' . date('Y-m-d-H');
                    $existing = $db->fetchOne(
                        "SELECT id FROM incidents WHERE dedupe_key = ?",
                        [$dedupeKey]
                    );

                    if (!$existing) {
                        $reason = $states[0]['reason'] ?? 'Multiple consecutive check failures';
                        $id = $db->insert('incidents', [
                            'provider_id' => $providerId,
                            'start_ts' => $states[$nonOkCount - 1]['ts'],
                            'end_ts' => null,
                            'severity' => $currentState,
                            'title' => Helpers::statusLabel($currentState) . ' detected',
                            'description' => $reason,
                            'source' => $source,
                            'dedupe_key' => $dedupeKey,
                        ]);
                        $actions[] = ['action' => 'opened', 'provider' => $provider['name'], 'incident_id' => $id, 'severity' => $currentState];
                        Helpers::log('info', "Incident opened for {$provider['name']}: {$currentState}", 'cron.log');
                    }
                } elseif ($openIncident) {
                    // Update severity if escalated
                    $severityOrder = ['ok' => 0, 'unknown' => 1, 'degraded' => 2, 'partial' => 3, 'outage' => 4];
                    $currentSev = $severityOrder[$currentState] ?? 0;
                    $incSev = $severityOrder[$openIncident['severity']] ?? 0;
                    if ($currentSev > $incSev) {
                        $db->update('incidents', ['severity' => $currentState], 'id = ?', [$openIncident['id']]);
                        $actions[] = ['action' => 'escalated', 'provider' => $provider['name'], 'to' => $currentState];
                    }
                }
            } elseif ($currentState === 'ok' && $openIncident) {
                // Count consecutive OK states
                $okCount = 0;
                foreach ($states as $s) {
                    if ($s['state'] === 'ok') $okCount++;
                    else break;
                }

                if ($okCount >= $resolveM) {
                    $db->update('incidents', [
                        'end_ts' => date('Y-m-d H:i:s'),
                    ], 'id = ?', [$openIncident['id']]);
                    $actions[] = ['action' => 'resolved', 'provider' => $provider['name'], 'incident_id' => $openIncident['id']];
                    Helpers::log('info', "Incident resolved for {$provider['name']}", 'cron.log');
                }
            }
        }

        return $actions;
    }

    /**
     * Compute provider state from latest check results
     */
    public static function computeState(int $providerId): array
    {
        global $db, $config;

        $endpoints = $db->fetchAll(
            "SELECT * FROM endpoints WHERE provider_id = ? AND enabled = 1",
            [$providerId]
        );

        if (empty($endpoints)) {
            return ['state' => 'unknown', 'reason' => 'No enabled endpoints'];
        }

        $totalEndpoints = count($endpoints);
        $failedEndpoints = 0;
        $degradedEndpoints = 0;
        $latencyThreshold = (int)($config['DEFAULT_TIMEOUT_MS'] ?? 5000);

        // Get provider-specific threshold
        $provider = $db->fetchOne("SELECT * FROM providers WHERE id = ?", [$providerId]);
        if ($provider) {
            $latencyThreshold = (int)($provider['latency_threshold_ms'] ?? $latencyThreshold);
        }

        foreach ($endpoints as $ep) {
            $latest = $db->fetchOne(
                "SELECT * FROM checks WHERE endpoint_id = ? ORDER BY ts DESC LIMIT 1",
                [$ep['id']]
            );

            if (!$latest) {
                $failedEndpoints++;
                continue;
            }

            // Check staleness (2x interval)
            $checkInterval = $provider['check_interval'] ?? $config['DEFAULT_CHECK_INTERVAL'] ?? 300;
            $checkAge = time() - strtotime($latest['ts']);
            if ($checkAge > $checkInterval * 2) {
                $failedEndpoints++;
                continue;
            }

            if (!$latest['success']) {
                $failedEndpoints++;
            } elseif ($latest['total_ms'] > $latencyThreshold) {
                $degradedEndpoints++;
            }
        }

        $failRate = $failedEndpoints / $totalEndpoints;

        if ($failRate >= 0.5) {
            return ['state' => 'outage', 'reason' => "{$failedEndpoints}/{$totalEndpoints} endpoints failing"];
        }
        if ($failRate > 0) {
            return ['state' => 'partial', 'reason' => "{$failedEndpoints}/{$totalEndpoints} endpoints failing"];
        }
        if ($degradedEndpoints > 0) {
            return ['state' => 'degraded', 'reason' => "High latency on {$degradedEndpoints} endpoint(s)"];
        }

        return ['state' => 'ok', 'reason' => 'All endpoints healthy'];
    }
}
