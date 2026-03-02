<?php
/**
 * AI-NOC — Status Feed Parsers (JSON + RSS/Atom + Generic)
 * File: /includes/status_parsers.php
 */

declare(strict_types=1);

namespace AiNoc;

class StatusParser
{
    /**
     * Parse a provider's official status feed and return normalized state
     *
     * @return array{state: string, title: string, description: string}|null
     */
    public static function parse(string $url, string $type, string $metadataJson = '{}'): ?array
    {
        $response = HttpClient::fetch($url);
        if (!$response['success'] || empty($response['body'])) {
            Helpers::log('warning', "Failed to fetch status feed: {$url} (HTTP {$response['code']})");
            return null;
        }

        $metadata = json_decode($metadataJson, true) ?: [];

        return match ($type) {
            'json' => self::parseJson($response['body'], $metadata),
            'rss', 'atom' => self::parseRssAtom($response['body'], $metadata),
            default => self::parseGeneric($response['body'], $metadata),
        };
    }

    /**
     * Parse Statuspage.io-style JSON (used by many providers)
     * Supports: status.openai.com, status.anthropic.com, etc.
     */
    private static function parseJson(string $body, array $metadata): ?array
    {
        $data = json_decode($body, true);
        if (!$data) return null;

        // Statuspage.io format (most common)
        if (isset($data['status']['indicator'])) {
            $indicator = $data['status']['indicator'];
            $state = match ($indicator) {
                'none' => 'ok',
                'minor' => 'degraded',
                'major' => 'partial',
                'critical' => 'outage',
                default => 'unknown',
            };
            $desc = $data['status']['description'] ?? '';

            // Check for active incidents
            $title = 'Operational';
            if (isset($data['incidents']) && is_array($data['incidents'])) {
                $active = array_filter($data['incidents'], fn($i) => empty($i['resolved_at']));
                if (!empty($active)) {
                    $latest = reset($active);
                    $title = $latest['name'] ?? 'Active Incident';
                    $desc = $latest['incident_updates'][0]['body'] ?? $desc;
                }
            }

            return ['state' => $state, 'title' => $title, 'description' => $desc];
        }

        // Instatus format
        if (isset($data['page']['status'])) {
            $state = match ($data['page']['status']) {
                'UP' => 'ok',
                'HASISSUES' => 'degraded',
                'UNDERMAINTENANCE' => 'partial',
                default => 'unknown',
            };
            return ['state' => $state, 'title' => $data['page']['name'] ?? '', 'description' => ''];
        }

        // Custom JSON mapping from metadata
        if (isset($metadata['json_status_path'])) {
            $value = self::extractJsonPath($data, $metadata['json_status_path']);
            if ($value !== null) {
                $mapping = $metadata['json_status_mapping'] ?? [];
                $state = $mapping[$value] ?? 'unknown';
                return ['state' => $state, 'title' => (string)$value, 'description' => ''];
            }
        }

        // Try common patterns
        foreach (['status', 'state', 'health'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $val = strtolower($data[$key]);
                $state = match (true) {
                    in_array($val, ['ok', 'operational', 'up', 'healthy', 'green']) => 'ok',
                    in_array($val, ['degraded', 'degraded_performance', 'yellow', 'slow']) => 'degraded',
                    in_array($val, ['partial', 'partial_outage', 'orange']) => 'partial',
                    in_array($val, ['outage', 'major_outage', 'down', 'red', 'critical']) => 'outage',
                    default => 'unknown',
                };
                return ['state' => $state, 'title' => $data[$key], 'description' => ''];
            }
        }

        return null;
    }

    /**
     * Parse RSS/Atom feeds (Azure, some others)
     */
    private static function parseRssAtom(string $body, array $metadata): ?array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (!$xml) {
            Helpers::log('warning', 'Failed to parse RSS/Atom feed');
            return null;
        }

        // Atom feed
        if ($xml->getName() === 'feed') {
            $entries = $xml->entry;
            if (count($entries) === 0) {
                return ['state' => 'ok', 'title' => 'Operational', 'description' => 'No recent incidents'];
            }
            $latest = $entries[0];
            $title = (string)$latest->title;
            $desc = strip_tags((string)($latest->content ?? $latest->summary ?? ''));
            $state = self::inferStateFromText($title . ' ' . $desc);
            return ['state' => $state, 'title' => $title, 'description' => $desc];
        }

        // RSS feed
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
            if (count($items) === 0) {
                return ['state' => 'ok', 'title' => 'Operational', 'description' => 'No recent incidents'];
            }
            $latest = $items[0];
            $title = (string)$latest->title;
            $desc = strip_tags((string)$latest->description);

            // Check if latest item is recent (within 24h)
            $pubDate = strtotime((string)($latest->pubDate ?? ''));
            if ($pubDate && (time() - $pubDate) > 86400) {
                return ['state' => 'ok', 'title' => 'Operational', 'description' => 'No recent incidents'];
            }

            $state = self::inferStateFromText($title . ' ' . $desc);
            return ['state' => $state, 'title' => $title, 'description' => $desc];
        }

        return null;
    }

    /**
     * Generic HTML-based parser — looks for keywords in response body
     */
    private static function parseGeneric(string $body, array $metadata): ?array
    {
        $lower = strtolower($body);
        $state = self::inferStateFromText($lower);
        return ['state' => $state, 'title' => 'Status parsed from page', 'description' => ''];
    }

    /**
     * Infer status from free text
     */
    private static function inferStateFromText(string $text): string
    {
        $lower = strtolower($text);

        if (preg_match('/major\s*outage|fully\s*down|service\s*unavailable|critical/i', $lower)) return 'outage';
        if (preg_match('/partial\s*outage|partially|some\s*services/i', $lower)) return 'partial';
        if (preg_match('/degraded|elevated|increased\s*(error|latency)|slow/i', $lower)) return 'degraded';
        if (preg_match('/resolved|operational|all\s*systems|no\s*(issues|incidents)/i', $lower)) return 'ok';

        return 'unknown';
    }

    /**
     * Extract a value from nested JSON using dot-notation path
     */
    private static function extractJsonPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) return null;
            $current = $current[$key];
        }
        return $current;
    }
}
