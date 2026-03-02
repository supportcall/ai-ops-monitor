<?php
/**
 * AI-NOC — Helpers
 * File: /includes/helpers.php
 */

declare(strict_types=1);

namespace AiNoc;

class Helpers
{
    public static function e(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        global $config;
        return $config[$key] ?? $default;
    }

    public static function baseUrl(string $path = ''): string
    {
        $base = rtrim(self::config('BASE_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function redirect(string $url, int $code = 302): never
    {
        header("Location: {$url}", true, $code);
        exit;
    }

    public static function jsonResponse(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function timeAgo(\DateTimeInterface $dt): string
    {
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $dt->getTimestamp();

        if ($diff < 60) return $diff . 's ago';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }

    public static function log(string $level, string $message, string $logFile = 'app.log'): void
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $configLevel = self::config('LOG_LEVEL', 'info');
        if (($levels[$level] ?? 0) < ($levels[$configLevel] ?? 1)) return;

        $ts = date('Y-m-d H:i:s');
        $line = "[{$ts}] [{$level}] {$message}" . PHP_EOL;
        $path = AINOC_ROOT . '/logs/' . $logFile;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'ok' => 'Operational',
            'degraded' => 'Degraded',
            'partial' => 'Partial Outage',
            'outage' => 'Major Outage',
            default => 'Unknown',
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'ok' => '#10b981',
            'degraded' => '#f59e0b',
            'partial' => '#f97316',
            'outage' => '#ef4444',
            default => '#6b7280',
        };
    }

    public static function generateId(int $length = 16): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function sanitizeSlug(string $str): string
    {
        return preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($str)));
    }

    public static function formatMs(float $ms): string
    {
        if ($ms <= 0) return '—';
        if ($ms < 1000) return round($ms) . 'ms';
        return round($ms / 1000, 2) . 's';
    }
}

// Global shortcut functions
function e(string $s): string { return Helpers::e($s); }
function config(string $k, mixed $d = null): mixed { return Helpers::config($k, $d); }
