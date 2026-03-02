<?php
/**
 * AI-NOC — HTTP Client (curl_multi for parallel checks)
 * File: /includes/http.php
 */

declare(strict_types=1);

namespace AiNoc;

class HttpClient
{
    /**
     * Run multiple HTTP checks in parallel using curl_multi
     *
     * @param array $endpoints Array of endpoint records from DB
     * @return array Results keyed by endpoint ID
     */
    public static function runChecks(array $endpoints): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($endpoints as $ep) {
            $ch = curl_init();
            $url = $ep['url'];
            $method = strtoupper($ep['method'] ?? 'GET');
            $timeout = ($ep['timeout_ms'] ?? 10000) / 1000;
            $headers = json_decode($ep['headers_json'] ?? '{}', true) ?: [];

            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = "{$k}: {$v}";
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => (int)$timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, (int)$timeout),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_USERAGENT => 'AI-NOC/' . AINOC_VERSION . ' (Status Monitor)',
                CURLOPT_NOBODY => ($method === 'HEAD'),
            ]);

            if ($method === 'POST' && !empty($ep['body'])) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $ep['body']);
            }

            // Enable timing info
            curl_setopt($ch, CURLOPT_HEADER, false);

            $handles[$ep['id']] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        // Execute all handles
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
        } while ($running > 0);

        // Collect results
        foreach ($handles as $epId => $ch) {
            $info = curl_getinfo($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            $dnsMs = ($info['namelookup_time'] ?? 0) * 1000;
            $tlsMs = max(0, (($info['appconnect_time'] ?? 0) - ($info['connect_time'] ?? 0))) * 1000;
            $ttfbMs = ($info['starttransfer_time'] ?? 0) * 1000;
            $totalMs = ($info['total_time'] ?? 0) * 1000;
            $httpCode = (int)($info['http_code'] ?? 0);
            $bytes = (int)($info['size_download'] ?? 0);

            $success = ($errno === 0 && $httpCode > 0 && $httpCode < 500);

            // Check expect_code if configured
            $ep = null;
            foreach ($endpoints as $e) {
                if ($e['id'] == $epId) { $ep = $e; break; }
            }
            if ($ep && !empty($ep['expect_code']) && $httpCode !== (int)$ep['expect_code']) {
                $success = false;
            }

            $results[$epId] = [
                'endpoint_id' => $epId,
                'ts' => date('Y-m-d H:i:s'),
                'success' => $success ? 1 : 0,
                'http_code' => $httpCode,
                'dns_ms' => round($dnsMs, 2),
                'tls_ms' => round($tlsMs, 2),
                'ttfb_ms' => round($ttfbMs, 2),
                'total_ms' => round($totalMs, 2),
                'bytes' => $bytes,
                'error_text' => $error ?: null,
            ];

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Fetch a URL and return body + metadata
     */
    public static function fetch(string $url, int $timeoutSec = 15): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_USERAGENT => 'AI-NOC/' . AINOC_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'body' => $body ?: '',
            'code' => $code,
            'error' => $error,
            'success' => ($code >= 200 && $code < 400),
        ];
    }
}
