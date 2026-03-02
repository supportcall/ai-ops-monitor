<?php
/**
 * AI-NOC — Database Layer (SQLite + MySQL)
 * File: /includes/db.php
 */

declare(strict_types=1);

namespace AiNoc;

class DB
{
    private static ?self $instance = null;
    private \PDO $pdo;
    private string $driver;

    private function __construct(array $config)
    {
        $this->driver = $config['DB_DRIVER'] ?? 'sqlite';

        if ($this->driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['DB_MYSQL_HOST'], $config['DB_MYSQL_PORT'] ?? 3306, $config['DB_MYSQL_NAME']);
            $this->pdo = new \PDO($dsn, $config['DB_MYSQL_USER'], $config['DB_MYSQL_PASS'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            $dbPath = $config['DB_SQLITE_PATH'] ?? AINOC_ROOT . '/data/ai-noc.sqlite';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            $this->pdo = new \PDO('sqlite:' . $dbPath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA busy_timeout=5000');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        }
    }

    public static function getInstance(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function insert(string $table, array $data): string
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $stmt = $this->query("UPDATE {$table} SET {$set} WHERE {$where}", [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public function runMigrations(): void
    {
        $autoIncrement = $this->driver === 'mysql' ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
        $intPrimary = $this->driver === 'mysql'
            ? 'INT PRIMARY KEY AUTO_INCREMENT'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $textType = 'TEXT';
        $now = $this->driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
            version INT NOT NULL DEFAULT 0,
            applied_at TEXT NOT NULL
        )");

        $ver = (int)($this->fetchOne("SELECT MAX(version) as v FROM schema_version")['v'] ?? 0);

        if ($ver < 1) {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id {$intPrimary},
                email TEXT NOT NULL UNIQUE,
                pass_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                created_at TEXT NOT NULL,
                last_login_at TEXT,
                failed_logins INT NOT NULL DEFAULT 0,
                locked_until TEXT
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value_encrypted TEXT,
                updated_at TEXT NOT NULL
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS providers (
                id {$intPrimary},
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                enabled INT NOT NULL DEFAULT 1,
                official_status_url TEXT,
                official_status_type TEXT DEFAULT 'json',
                metadata_json TEXT DEFAULT '{}',
                check_interval INT NOT NULL DEFAULT 300,
                latency_threshold_ms INT NOT NULL DEFAULT 5000,
                created_at TEXT NOT NULL
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS endpoints (
                id {$intPrimary},
                provider_id INT NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'http',
                url TEXT NOT NULL,
                method TEXT NOT NULL DEFAULT 'GET',
                headers_json TEXT DEFAULT '{}',
                body TEXT,
                timeout_ms INT NOT NULL DEFAULT 10000,
                expect_code INT DEFAULT 200,
                expect_contains TEXT,
                enabled INT NOT NULL DEFAULT 1,
                FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS checks (
                id {$intPrimary},
                endpoint_id INT NOT NULL,
                ts TEXT NOT NULL,
                success INT NOT NULL,
                http_code INT,
                dns_ms REAL DEFAULT 0,
                tls_ms REAL DEFAULT 0,
                ttfb_ms REAL DEFAULT 0,
                total_ms REAL DEFAULT 0,
                bytes INT DEFAULT 0,
                error_text TEXT,
                FOREIGN KEY (endpoint_id) REFERENCES endpoints(id) ON DELETE CASCADE
            )");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_checks_endpoint_ts ON checks(endpoint_id, ts)");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS provider_state (
                id {$intPrimary},
                provider_id INT NOT NULL,
                ts TEXT NOT NULL,
                state TEXT NOT NULL DEFAULT 'unknown',
                reason TEXT,
                source TEXT NOT NULL DEFAULT 'synthetic',
                FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
            )");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_pstate_provider_ts ON provider_state(provider_id, ts)");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS incidents (
                id {$intPrimary},
                provider_id INT NOT NULL,
                start_ts TEXT NOT NULL,
                end_ts TEXT,
                severity TEXT NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                source TEXT NOT NULL DEFAULT 'synthetic',
                dedupe_key TEXT,
                FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
            )");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_incidents_provider_ts ON incidents(provider_id, start_ts)");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS alerts (
                id {$intPrimary},
                incident_id INT,
                channel TEXT NOT NULL,
                sent_ts TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                response_text TEXT,
                FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS cron_runs (
                id {$intPrimary},
                script TEXT NOT NULL,
                started_at TEXT NOT NULL,
                finished_at TEXT,
                status TEXT NOT NULL DEFAULT 'running',
                details TEXT
            )");

            $this->query("INSERT INTO schema_version (version, applied_at) VALUES (?, ?)", [1, date('Y-m-d H:i:s')]);
        }
    }

    public function seedDefaultProviders(): void
    {
        $count = (int)($this->fetchOne("SELECT COUNT(*) as c FROM providers")['c'] ?? 0);
        if ($count > 0) return;

        $providers = [
            ['OpenAI', 'openai', 'https://status.openai.com', 'json', '{"api_base":"https://api.openai.com"}'],
            ['Anthropic', 'anthropic', 'https://status.anthropic.com', 'json', '{"api_base":"https://api.anthropic.com"}'],
            ['Google Gemini', 'google-gemini', 'https://status.cloud.google.com', 'json', '{"api_base":"https://generativelanguage.googleapis.com"}'],
            ['Azure OpenAI', 'azure-openai', 'https://status.azure.com', 'rss', '{"api_base":"https://management.azure.com"}'],
            ['AWS Bedrock', 'aws-bedrock', 'https://health.aws.amazon.com', 'json', '{"api_base":"https://bedrock.us-east-1.amazonaws.com"}'],
            ['Cohere', 'cohere', 'https://status.cohere.com', 'json', '{"api_base":"https://api.cohere.ai"}'],
            ['Mistral AI', 'mistral', 'https://status.mistral.ai', 'json', '{"api_base":"https://api.mistral.ai"}'],
            ['Groq', 'groq', 'https://status.groq.com', 'json', '{"api_base":"https://api.groq.com"}'],
            ['Perplexity', 'perplexity', null, null, '{"api_base":"https://api.perplexity.ai"}'],
            ['xAI (Grok)', 'xai', null, null, '{"api_base":"https://api.x.ai"}'],
            ['Hugging Face', 'huggingface', 'https://status.huggingface.co', 'json', '{"api_base":"https://huggingface.co"}'],
            ['Replicate', 'replicate', 'https://status.replicate.com', 'json', '{"api_base":"https://api.replicate.com"}'],
            ['Stability AI', 'stability', null, null, '{"api_base":"https://api.stability.ai"}'],
            ['Together AI', 'together', 'https://status.together.ai', 'json', '{"api_base":"https://api.together.xyz"}'],
            ['DeepSeek', 'deepseek', null, null, '{"api_base":"https://api.deepseek.com"}'],
            ['Meta Llama (via API)', 'meta-llama', null, null, '{}'],
            ['AI21 Labs', 'ai21', null, null, '{"api_base":"https://api.ai21.com"}'],
            ['Fireworks AI', 'fireworks', 'https://status.fireworks.ai', 'json', '{"api_base":"https://api.fireworks.ai"}'],
            ['Anyscale', 'anyscale', null, null, '{"api_base":"https://api.endpoints.anyscale.com"}'],
            ['Databricks DBRX', 'databricks', 'https://status.databricks.com', 'json', '{}'],
            ['Claude Code', 'claude-code', 'https://status.anthropic.com', 'json', '{"api_base":"https://api.anthropic.com","service":"claude-code"}'],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($providers as $p) {
            $provId = $this->insert('providers', [
                'name' => $p[0],
                'slug' => $p[1],
                'enabled' => 1,
                'official_status_url' => $p[2],
                'official_status_type' => $p[3] ?? 'json',
                'metadata_json' => $p[4],
                'check_interval' => 300,
                'latency_threshold_ms' => 5000,
                'created_at' => $now,
            ]);

            // Default synthetic endpoints
            $meta = json_decode($p[4], true);
            $apiBase = $meta['api_base'] ?? null;

            if ($apiBase) {
                $this->insert('endpoints', [
                    'provider_id' => (int)$provId,
                    'name' => $p[0] . ' API',
                    'type' => 'http',
                    'url' => $apiBase,
                    'method' => 'HEAD',
                    'headers_json' => '{}',
                    'body' => null,
                    'timeout_ms' => 10000,
                    'expect_code' => null,
                    'expect_contains' => null,
                    'enabled' => 1,
                ]);
            }

            // Homepage check
            $homepage = 'https://' . str_replace(['(via api)', '(grok)', ' '], ['', '', ''], strtolower($p[0])) . '.com';
            if (in_array($p[1], ['google-gemini'])) $homepage = 'https://ai.google.dev';
            if ($p[1] === 'azure-openai') $homepage = 'https://azure.microsoft.com/en-us/products/ai-services/openai-service';
            if ($p[1] === 'aws-bedrock') $homepage = 'https://aws.amazon.com/bedrock/';
            if ($p[1] === 'xai') $homepage = 'https://x.ai';
            if ($p[1] === 'huggingface') $homepage = 'https://huggingface.co';
            if ($p[1] === 'together') $homepage = 'https://together.ai';
            if ($p[1] === 'meta-llama') $homepage = 'https://llama.meta.com';
            if ($p[1] === 'fireworks') $homepage = 'https://fireworks.ai';
            if ($p[1] === 'anyscale') $homepage = 'https://anyscale.com';

            $this->insert('endpoints', [
                'provider_id' => (int)$provId,
                'name' => $p[0] . ' Homepage',
                'type' => 'http',
                'url' => $homepage,
                'method' => 'HEAD',
                'headers_json' => '{}',
                'body' => null,
                'timeout_ms' => 10000,
                'expect_code' => 200,
                'expect_contains' => null,
                'enabled' => 1,
            ]);
        }
    }
}
