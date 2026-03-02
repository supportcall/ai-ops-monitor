# AI-NOC — Complete Documentation

## AI Provider Network Operations Center

**Version:** 1.0.0  
**Platform:** PHP 8.1+ / Apache / cPanel  
**Database:** SQLite (default) or MySQL  
**License:** MIT

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Configuration Reference](#configuration-reference)
6. [Provider Registry](#provider-registry)
7. [Monitoring Engine](#monitoring-engine)
8. [Incident Engine](#incident-engine)
9. [Alert System](#alert-system)
10. [Database Schema](#database-schema)
11. [Cron Jobs](#cron-jobs)
12. [Admin Panel](#admin-panel)
13. [Public API](#public-api)
14. [Security](#security)
15. [Troubleshooting](#troubleshooting)
16. [Extending AI-NOC](#extending-ai-noc)
17. [File Reference](#file-reference)

---

## 1. Overview

AI-NOC is a self-contained, production-grade dashboard that monitors outages and degradation across major AI/LLM providers. It combines:

- **Official status feeds** — JSON (Statuspage.io), RSS/Atom, and generic HTML parsing from provider status pages
- **Active synthetic checks** — HTTP HEAD/GET, TLS handshake timing, DNS resolution timing against provider endpoints
- **Incident detection** — Automatic incident creation when failures persist across configurable thresholds
- **Alerting** — Email (SMTP) and webhook (generic POST) notifications with cooldown deduplication
- **Visualization** — Real-time dashboard with latency charts (Chart.js), 90-day uptime heatmaps, and incident timelines

### Design Principles

| Principle | Implementation |
|-----------|---------------|
| Self-contained | No Node.js, no Docker, no external build steps. Pure PHP + HTML/CSS/JS |
| Security-first | Encrypted secrets, CSRF, rate limiting, session hardening, `.htaccess` deny rules |
| cPanel-native | All background tasks via cron CLI scripts; SQLite requires zero DB configuration |
| Production-ready | Error handling, prepared statements, indexed queries, parallel HTTP via `curl_multi` |

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER BROWSER                             │
│  Dashboard (index.php) │ Provider Detail │ Admin Panel          │
└─────────────────┬───────────────────────────────────────────────┘
                  │ HTTP
┌─────────────────▼───────────────────────────────────────────────┐
│                      APACHE + PHP 8.1+                          │
│                                                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────────────┐  │
│  │ Public   │  │ Admin    │  │ API      │  │ Cron Scripts   │  │
│  │ Pages    │  │ Pages    │  │ Endpoint │  │ (CLI PHP)      │  │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └───────┬────────┘  │
│       │              │             │                │            │
│  ┌────▼──────────────▼─────────────▼────────────────▼────────┐  │
│  │                    INCLUDES LAYER                          │  │
│  │  bootstrap.php → db.php → auth.php → csrf.php             │  │
│  │  http.php → status_parsers.php → incident_engine.php      │  │
│  │  alert_engine.php → crypto.php → helpers.php              │  │
│  └────────────────────────┬──────────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼──────────────────────────────────┐  │
│  │              DATABASE (SQLite / MySQL)                     │  │
│  │  providers │ endpoints │ checks │ provider_state           │  │
│  │  incidents │ alerts │ users │ settings │ cron_runs         │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                  │
                  │ curl_multi (parallel)
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    EXTERNAL PROVIDERS                           │
│  OpenAI │ Anthropic │ Google │ Azure │ AWS │ Cohere │ ...      │
│  (API endpoints + Official status pages)                       │
└─────────────────────────────────────────────────────────────────┘
```

### Request Flow

1. **Cron → run_checks.php** — Queries DB for due providers, batches endpoints, runs parallel HTTP checks via `curl_multi`, writes results to `checks` table, computes provider state
2. **Cron → pull_official.php** — Fetches official status page JSON/RSS for providers that have one, parses into normalized state, writes to `provider_state`
3. **Cron → evaluate_incidents.php** — Reads `provider_state` history, creates/resolves incidents based on consecutive non-OK states, queues alerts
4. **Cron → send_alerts.php** — Dispatches pending email/webhook alerts, handles daily digest
5. **Browser → index.php** — Reads latest `provider_state`, `checks`, `incidents` and renders dashboard
6. **Browser → provider.php** — Reads provider-specific data including latency time-series and uptime history

---

## 3. Requirements

### Required

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.1 | 8.2+ |
| Extensions | `pdo`, `pdo_sqlite`, `curl`, `json`, `mbstring`, `openssl` | All required + optional |
| Web Server | Apache 2.4+ with `mod_rewrite` | — |
| Hosting | cPanel with Cron Jobs | WHM/cPanel VPS |

### Optional Extensions

| Extension | Purpose |
|-----------|---------|
| `sodium` | Stronger encryption (libsodium vs OpenSSL fallback) |
| `pdo_mysql` | MySQL database support |
| `simplexml` | RSS/Atom feed parsing |

### Disk Space

- Application files: ~500 KB
- SQLite database: grows ~1 MB per day with 20 providers at 5-min intervals
- After 90-day retention cleanup: typically 50-100 MB

### Performance

- Handles 200+ checks/minute on a modest 1-core VPS
- `curl_multi` runs up to 50 endpoints in parallel per batch
- Indexed time-series queries for fast dashboard rendering
- SQLite WAL mode for concurrent read/write

---

## 4. Installation

### Step-by-Step

#### 1. Upload Files

Upload the entire `ai-noc/` folder to your web root:
```
/home/USERNAME/public_html/ai-noc/
```

#### 2. Download Chart.js

```bash
mkdir -p assets/vendor
curl -o assets/vendor/chart.min.js \
  https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js
```

Or download manually from [https://www.chartjs.org/](https://www.chartjs.org/) and place at `assets/vendor/chart.min.js`.

#### 3. Set Directory Permissions

```bash
cd /home/USERNAME/public_html/ai-noc
chmod 750 data/ logs/ config/
chmod 640 config/config.sample.php
```

#### 4. Run Install Wizard

Visit `https://your-domain.com/ai-noc/install/` and complete the 4 steps:

**Step 1 — Environment Check**
- Verifies PHP version, required extensions, directory write permissions
- Shows optional extension availability (sodium, MySQL, simplexml)
- All required checks must pass to continue

**Step 2 — Database**
- Choose SQLite (recommended, zero-config) or MySQL
- For MySQL: provide host, port, database name, username, password
- SQLite stores data at `data/ai-noc.sqlite`

**Step 3 — Admin Account & Settings**
- Set your base URL (auto-detected)
- Choose timezone
- Create admin email and password (min 8 characters, hashed with Argon2id)

**Step 4 — Finalize**
- Generates random 32-byte APP_KEY for encryption
- Writes `config/config.php` with permissions locked to 0640
- Runs database migrations (creates all tables + indexes)
- Seeds 20 default AI providers with synthetic endpoints
- Creates admin user account
- Writes `install/LOCK` file to prevent re-running
- Creates `.htaccess` deny rules for `data/`, `config/`, `logs/`

#### 5. Set Up Cron Jobs

See [Cron Jobs](#cron-jobs) section below for exact commands.

#### 6. Initial Check Run

```bash
php cron/run_checks.php
php cron/pull_official.php
php cron/evaluate_incidents.php
```

#### 7. Verify

- Visit your dashboard at `https://your-domain.com/ai-noc/`
- Log in at `https://your-domain.com/ai-noc/admin/login.php`
- Check **Admin → Diagnostics** to verify everything is operational

### Post-Install Security Hardening

After successful installation:

1. **Verify LOCK file exists:** `install/LOCK` should be present
2. **Optionally delete install files:** `rm -rf install/` (the LOCK file blocks access anyway)
3. **Verify .htaccess rules:** Try accessing `https://your-domain.com/ai-noc/data/` — should return 403
4. **Set config permissions:** `chmod 640 config/config.php`
5. **Configure SMTP:** Go to Admin → Settings → SMTP for alert emails

---

## 5. Configuration Reference

Configuration is stored in `config/config.php` (generated by installer). Runtime settings are stored encrypted in the `settings` DB table.

### config.php Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `APP_KEY` | string | (generated) | 32-byte base64-encoded encryption key. **Never share this.** |
| `BASE_URL` | string | — | Full URL to AI-NOC root, no trailing slash |
| `DB_DRIVER` | string | `sqlite` | `sqlite` or `mysql` |
| `DB_SQLITE_PATH` | string | `data/ai-noc.sqlite` | Path to SQLite file |
| `DB_MYSQL_HOST` | string | `127.0.0.1` | MySQL host |
| `DB_MYSQL_PORT` | int | `3306` | MySQL port |
| `DB_MYSQL_NAME` | string | — | MySQL database name |
| `DB_MYSQL_USER` | string | — | MySQL username |
| `DB_MYSQL_PASS` | string | — | MySQL password |
| `SESSION_LIFETIME` | int | `3600` | Session duration in seconds |
| `SESSION_NAME` | string | `ainoc_sid` | PHP session cookie name |
| `LOGIN_MAX_ATTEMPTS` | int | `5` | Failed logins before lockout |
| `LOGIN_LOCKOUT_MINS` | int | `15` | Lockout duration in minutes |
| `TIMEZONE` | string | `Australia/Hobart` | PHP timezone identifier |
| `LOG_LEVEL` | string | `info` | Minimum log level: `debug`, `info`, `warning`, `error` |
| `PUBLIC_DASHBOARD` | bool | `true` | Allow unauthenticated dashboard access |
| `PUBLIC_STATUS_JSON` | bool | `true` | Enable `/status.json` API endpoint |
| `DEFAULT_CHECK_INTERVAL` | int | `300` | Default seconds between checks per provider |
| `DEFAULT_TIMEOUT_MS` | int | `10000` | HTTP timeout for synthetic checks (ms) |
| `INCIDENT_CONFIRM_CHECKS` | int | `3` | Consecutive non-OK checks to open incident |
| `INCIDENT_RESOLVE_CHECKS` | int | `3` | Consecutive OK checks to close incident |
| `RETENTION_RAW_CHECKS` | int | `90` | Days to keep raw check data |
| `RETENTION_INCIDENTS` | int | `365` | Days to keep resolved incidents |
| `SMTP_ENABLED` | bool | `false` | Enable SMTP email alerts |
| `SMTP_HOST` | string | — | SMTP server hostname |
| `SMTP_PORT` | int | `587` | SMTP port |
| `SMTP_USER` | string | — | SMTP auth username |
| `SMTP_PASS` | string | — | SMTP auth password |
| `SMTP_FROM` | string | `noc@localhost` | Sender email address |
| `SMTP_FROM_NAME` | string | `AI-NOC` | Sender display name |
| `SMTP_ENCRYPTION` | string | `tls` | `tls`, `ssl`, or `none` |
| `WEBHOOK_ENABLED` | bool | `false` | Enable webhook POST alerts |
| `WEBHOOK_URL` | string | — | Webhook endpoint URL |
| `DIGEST_TIME` | string | `08:00` | Daily digest send time (24h, in configured timezone) |
| `ALERT_COOLDOWN_MINS` | int | `30` | Minutes to suppress duplicate alerts per incident |

### Runtime Settings (DB-stored, encrypted)

These can be changed in **Admin → Settings** without editing config files:

- `public_dashboard` — Toggle public access
- `public_status_json` — Toggle status API
- `default_check_interval` — Global check frequency
- `incident_confirm_checks` / `incident_resolve_checks` — Incident thresholds
- `retention_raw_checks` — Data retention period
- All SMTP fields
- `webhook_url`

---

## 6. Provider Registry

### Pre-configured Providers (20)

| # | Provider | Slug | Official Status Feed | Feed Type |
|---|----------|------|---------------------|-----------|
| 1 | OpenAI | `openai` | https://status.openai.com | Statuspage.io JSON |
| 2 | Anthropic | `anthropic` | https://status.anthropic.com | Statuspage.io JSON |
| 3 | Google Gemini | `google-gemini` | https://status.cloud.google.com | JSON |
| 4 | Azure OpenAI | `azure-openai` | https://status.azure.com | RSS |
| 5 | AWS Bedrock | `aws-bedrock` | https://health.aws.amazon.com | JSON |
| 6 | Cohere | `cohere` | https://status.cohere.com | Statuspage.io JSON |
| 7 | Mistral AI | `mistral` | https://status.mistral.ai | Statuspage.io JSON |
| 8 | Groq | `groq` | https://status.groq.com | Statuspage.io JSON |
| 9 | Perplexity | `perplexity` | *(none — synthetic only)* | — |
| 10 | xAI (Grok) | `xai` | *(none — synthetic only)* | — |
| 11 | Hugging Face | `huggingface` | https://status.huggingface.co | Statuspage.io JSON |
| 12 | Replicate | `replicate` | https://status.replicate.com | Statuspage.io JSON |
| 13 | Stability AI | `stability` | *(none — synthetic only)* | — |
| 14 | Together AI | `together` | https://status.together.ai | Statuspage.io JSON |
| 15 | DeepSeek | `deepseek` | *(none — synthetic only)* | — |
| 16 | Meta Llama | `meta-llama` | *(none — synthetic only)* | — |
| 17 | AI21 Labs | `ai21` | *(none — synthetic only)* | — |
| 18 | Fireworks AI | `fireworks` | https://status.fireworks.ai | Statuspage.io JSON |
| 19 | Anyscale | `anyscale` | *(none — synthetic only)* | — |
| 20 | Databricks DBRX | `databricks` | https://status.databricks.com | Statuspage.io JSON |
| 21 | Claude Code | `claude-code` | https://status.anthropic.com | Statuspage.io JSON |

### Default Endpoints Per Provider

Each provider is seeded with two synthetic endpoints:

1. **API Endpoint** — `HEAD` request to the provider's API base URL (e.g., `https://api.openai.com`)
2. **Homepage** — `HEAD` request to the provider's public website

These do **not** require API keys. They measure reachability and latency.

### Adding a Custom Provider

Via **Admin → Providers → Add Provider**:

1. **Name** — Display name (e.g., "My Custom AI Service")
2. **Slug** — URL-safe identifier, auto-generated from name (e.g., `my-custom-ai-service`)
3. **Official Status URL** — If the provider has a status page, enter its URL
4. **Status Feed Type** — `JSON` (Statuspage.io), `RSS`, `Atom`, or `Generic` (HTML keyword matching)
5. **Check Interval** — Seconds between checks (60–3600)

Then add endpoints:
- **Name** — Descriptive label
- **URL** — Full URL to check
- **Type** — `HTTP`, `TLS`, or `DNS`
- **Method** — `HEAD` (fastest), `GET`, or `POST`

### Provider Metadata (metadata_json)

Advanced per-provider configuration stored as JSON:

```json
{
  "api_base": "https://api.example.com",
  "json_status_path": "data.status.indicator",
  "json_status_mapping": {
    "none": "ok",
    "minor": "degraded",
    "major": "outage"
  }
}
```

| Field | Purpose |
|-------|---------|
| `api_base` | Base URL for auto-generated API endpoint |
| `json_status_path` | Dot-notation path to extract status from custom JSON feeds |
| `json_status_mapping` | Map feed-specific values to AI-NOC states |

---

## 7. Monitoring Engine

### Synthetic Checks (`http.php`)

The HTTP client uses PHP's `curl_multi` for parallel execution:

```
run_checks.php
  ├── Query DB: which providers are due? (based on check_interval)
  ├── Collect all enabled endpoints for due providers
  ├── Batch into groups of 50
  └── For each batch:
      ├── curl_multi_init()
      ├── Add all handles with configured method/headers/timeout
      ├── Execute in parallel
      └── Collect timing data per endpoint:
          ├── dns_ms     (DNS resolution time)
          ├── tls_ms     (TLS handshake time)
          ├── ttfb_ms    (Time to first byte)
          ├── total_ms   (Total request time)
          ├── http_code   (Response status code)
          ├── bytes      (Response size)
          └── error_text (curl error string, if any)
```

#### Check Success Criteria

A check is considered **successful** if:
1. No cURL errors (connection timeout, DNS failure, etc.)
2. HTTP status code is > 0 and < 500
3. If `expect_code` is configured, response code matches exactly
4. If `expect_contains` is configured, response body contains the string

#### Timing Breakdown

| Metric | What It Measures |
|--------|-----------------|
| `dns_ms` | Time to resolve hostname to IP (`CURLINFO_NAMELOOKUP_TIME`) |
| `tls_ms` | Time for TLS handshake (`CURLINFO_APPCONNECT_TIME - CURLINFO_CONNECT_TIME`) |
| `ttfb_ms` | Time to first byte from server (`CURLINFO_STARTTRANSFER_TIME`) |
| `total_ms` | Total request duration (`CURLINFO_TOTAL_TIME`) |

### Official Status Feeds (`status_parsers.php`)

Supports three parser types:

#### Statuspage.io JSON (Most Common)

Used by: OpenAI, Anthropic, Cohere, Mistral, Groq, Hugging Face, Replicate, Together AI, Fireworks AI, Databricks

Parses the standard Statuspage.io API format:
```json
{
  "status": {
    "indicator": "none|minor|major|critical",
    "description": "All Systems Operational"
  },
  "incidents": [
    {
      "name": "Incident Title",
      "resolved_at": null,
      "incident_updates": [{"body": "Details..."}]
    }
  ]
}
```

Mapping:
| Statuspage Indicator | AI-NOC State |
|---------------------|-------------|
| `none` | `ok` |
| `minor` | `degraded` |
| `major` | `partial` |
| `critical` | `outage` |

#### RSS/Atom

Used by: Azure (RSS feed)

Parses standard RSS `<item>` or Atom `<entry>` elements:
- Reads the latest entry
- If published within the last 24 hours, infers state from title/content keywords
- If no recent entries, assumes `ok`

#### Generic HTML

Fallback parser that scans the response body for status keywords:
- `major outage`, `fully down`, `critical` → `outage`
- `partial outage`, `some services` → `partial`
- `degraded`, `elevated`, `slow` → `degraded`
- `resolved`, `operational`, `all systems` → `ok`

#### Custom JSON Mapping

For non-standard JSON feeds, configure via `metadata_json`:
```json
{
  "json_status_path": "health.overall",
  "json_status_mapping": {
    "healthy": "ok",
    "impaired": "degraded",
    "failing": "outage"
  }
}
```

### State Computation (`incident_engine.php::computeState`)

After each check cycle, provider state is computed:

```
For provider P with N enabled endpoints:
  failed = endpoints where latest check failed
  degraded = endpoints where latency > threshold
  
  if failed/N >= 50%  → OUTAGE
  if failed/N > 0%    → PARTIAL
  if degraded > 0     → DEGRADED
  if all OK           → OK
  
  if no recent data (2x interval) → UNKNOWN
```

### State Merging (Synthetic + Official)

When both synthetic and official data are available:
- The **more severe** state wins
- Official can escalate synthetic state upward but not downward
- Source is recorded as `synthetic` or `official` for transparency

---

## 8. Incident Engine

### Lifecycle

```
                    N consecutive non-OK states
                    ┌───────────────────────┐
                    ▼                       │
  ┌──────┐    ┌──────────┐    ┌──────────┐
  │ None │───▶│ Tracking │───▶│ INCIDENT │
  └──────┘    └──────────┘    │ (Open)   │
                              └────┬─────┘
                                   │ M consecutive OK states
                                   ▼
                              ┌──────────┐
                              │ INCIDENT │
                              │ (Closed) │
                              └──────────┘
```

### Configuration

| Parameter | Default | Description |
|-----------|---------|-------------|
| `INCIDENT_CONFIRM_CHECKS` (N) | 3 | Consecutive non-OK states required to open an incident |
| `INCIDENT_RESOLVE_CHECKS` (M) | 3 | Consecutive OK states required to close an incident |

These can be configured globally and will apply to all providers.

### Deduplication

Incidents use a `dedupe_key` composed of `{provider_id}-{YYYY-MM-DD-HH}` to prevent duplicate incidents within the same hour.

### Severity Escalation

If an open incident's severity worsens (e.g., `degraded` → `outage`), the incident record is updated to reflect the higher severity.

### Incident Fields

| Field | Description |
|-------|-------------|
| `provider_id` | Which provider |
| `start_ts` | When the first non-OK state was recorded |
| `end_ts` | When resolved (NULL if still active) |
| `severity` | `degraded`, `partial`, or `outage` |
| `title` | Auto-generated from severity label |
| `description` | Reason from state computation |
| `source` | `synthetic` or `official` |
| `dedupe_key` | Prevents duplicate creation |

---

## 9. Alert System

### Channels

| Channel | Configuration | Payload |
|---------|--------------|---------|
| **Email** | SMTP settings in Admin → Settings | Subject line with severity + provider name; body with full details |
| **Webhook** | URL in Admin → Settings | JSON POST with event, provider, severity, title, description, timestamp |
| **Daily Digest** | Automatic at configured time | Summary of all incidents in last 24h, provider counts |

### Email Format

```
Subject: [AI-NOC] outage: Stability AI — Major Outage detected

Provider: Stability AI
Severity: Major Outage
Issue: Major Outage detected
Details: 2/2 endpoints failing

— AI-NOC https://your-domain.com/ai-noc
```

### Webhook Payload

```json
{
  "event": "incident",
  "provider": "Stability AI",
  "severity": "outage",
  "title": "Major Outage detected",
  "description": "2/2 endpoints failing",
  "timestamp": "2024-01-15T10:30:00+11:00",
  "dashboard_url": "https://your-domain.com/ai-noc"
}
```

Compatible with: Slack (via webhook URL), Discord (via webhook URL), PagerDuty, Opsgenie, or any HTTP endpoint.

### Cooldown

After an alert is sent for an incident, no duplicate alerts are sent for `ALERT_COOLDOWN_MINS` (default: 30 minutes). This prevents alert storms during prolonged outages.

### Daily Digest

Sent automatically at `DIGEST_TIME` (default: 08:00 in configured timezone):
- Lists all incidents from the past 24 hours with status (Active/Resolved)
- Shows count of operational vs non-operational providers
- Sent to all admin email addresses
- Only sent once per day (tracked via `alerts` table with `channel = 'digest'`)

---

## 10. Database Schema

### Entity-Relationship

```
users ──────────────────────────────────
settings ───────────────────────────────
providers ──┬── endpoints ──── checks
            ├── provider_state
            └── incidents ──── alerts
cron_runs ──────────────────────────────
```

### Table Details

#### `users`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `email` | TEXT UNIQUE | Login email |
| `pass_hash` | TEXT | Argon2id/bcrypt hash |
| `role` | TEXT | `admin` (expandable) |
| `created_at` | TEXT | ISO datetime |
| `last_login_at` | TEXT | Last successful login |
| `failed_logins` | INT | Counter for rate limiting |
| `locked_until` | TEXT | Lockout expiry datetime |

#### `settings`
| Column | Type | Description |
|--------|------|-------------|
| `key` | TEXT PK | Setting identifier |
| `value_encrypted` | TEXT | Encrypted value (sodium/openssl) |
| `updated_at` | TEXT | Last update time |

#### `providers`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `name` | TEXT | Display name |
| `slug` | TEXT UNIQUE | URL-safe identifier |
| `enabled` | INT | 0 or 1 |
| `official_status_url` | TEXT NULL | Status page URL |
| `official_status_type` | TEXT | `json`, `rss`, `atom`, `generic` |
| `metadata_json` | TEXT | Extended config JSON |
| `check_interval` | INT | Seconds between checks |
| `latency_threshold_ms` | INT | High latency threshold |
| `created_at` | TEXT | Creation datetime |

#### `endpoints`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `provider_id` | INT FK | References `providers.id` |
| `name` | TEXT | Descriptive label |
| `type` | TEXT | `http`, `tls`, `dns` |
| `url` | TEXT | Target URL |
| `method` | TEXT | `GET`, `HEAD`, `POST` |
| `headers_json` | TEXT | Custom headers as JSON |
| `body` | TEXT NULL | POST body |
| `timeout_ms` | INT | Request timeout |
| `expect_code` | INT NULL | Expected HTTP status |
| `expect_contains` | TEXT NULL | Expected response substring |
| `enabled` | INT | 0 or 1 |

#### `checks`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `endpoint_id` | INT FK | References `endpoints.id` |
| `ts` | TEXT | Check timestamp |
| `success` | INT | 0 or 1 |
| `http_code` | INT | HTTP response code |
| `dns_ms` | REAL | DNS resolution time |
| `tls_ms` | REAL | TLS handshake time |
| `ttfb_ms` | REAL | Time to first byte |
| `total_ms` | REAL | Total request time |
| `bytes` | INT | Response size |
| `error_text` | TEXT NULL | Error message |

**Indexes:** `idx_checks_endpoint_ts (endpoint_id, ts)`

#### `provider_state`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `provider_id` | INT FK | References `providers.id` |
| `ts` | TEXT | State timestamp |
| `state` | TEXT | `ok`, `degraded`, `partial`, `outage`, `unknown` |
| `reason` | TEXT | Human-readable explanation |
| `source` | TEXT | `synthetic` or `official` |

**Indexes:** `idx_pstate_provider_ts (provider_id, ts)`

#### `incidents`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `provider_id` | INT FK | References `providers.id` |
| `start_ts` | TEXT | Incident start time |
| `end_ts` | TEXT NULL | Resolution time (NULL = active) |
| `severity` | TEXT | `degraded`, `partial`, `outage` |
| `title` | TEXT | Incident title |
| `description` | TEXT | Details |
| `source` | TEXT | `synthetic` or `official` |
| `dedupe_key` | TEXT | Deduplication hash |

**Indexes:** `idx_incidents_provider_ts (provider_id, start_ts)`

#### `alerts`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `incident_id` | INT FK NULL | References `incidents.id` |
| `channel` | TEXT | `email`, `webhook`, `digest` |
| `sent_ts` | TEXT | Dispatch timestamp |
| `status` | TEXT | `pending`, `sent`, `failed` |
| `response_text` | TEXT NULL | Delivery response |

#### `cron_runs`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER PK | Auto-increment |
| `script` | TEXT | Script name |
| `started_at` | TEXT | Start time |
| `finished_at` | TEXT NULL | End time |
| `status` | TEXT | `running`, `done`, `error` |
| `details` | TEXT NULL | Summary/error message |

### Schema Versioning

Migrations are tracked via the `schema_version` table. The `DB::runMigrations()` method checks the current version and applies pending migrations. Currently at version 1.

---

## 11. Cron Jobs

### Scripts

| Script | Purpose | Recommended Schedule |
|--------|---------|---------------------|
| `run_checks.php` | Execute synthetic HTTP checks | Every minute (`* * * * *`) |
| `pull_official.php` | Fetch official status feeds | Every 5 minutes (`*/5 * * * *`) |
| `evaluate_incidents.php` | Create/resolve incidents + queue alerts | Every 2 minutes (`*/2 * * * *`) |
| `send_alerts.php` | Dispatch pending alerts + daily digest | Every minute (`* * * * *`) |
| `cleanup.php` | Purge old data, vacuum DB | Daily at 3 AM (`0 3 * * *`) |

### cPanel Setup

```bash
# Synthetic checks (every minute — respects per-provider intervals internally)
* * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/run_checks.php >/dev/null 2>&1

# Official status feeds (every 5 minutes)
*/5 * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/pull_official.php >/dev/null 2>&1

# Incident evaluation (every 2 minutes)
*/2 * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/evaluate_incidents.php >/dev/null 2>&1

# Alert dispatch (every minute)
* * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/send_alerts.php >/dev/null 2>&1

# Data cleanup (daily at 3:00 AM server time)
0 3 * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/cleanup.php >/dev/null 2>&1
```

### Concurrency Safety

All cron scripts are safe to run concurrently:
- SQLite WAL mode allows concurrent readers with one writer
- `busy_timeout=5000` handles brief write conflicts
- Each script logs its start/finish to `cron_runs` table
- `run_checks.php` skips providers that aren't due based on their last check timestamp

### Monitoring Cron Health

Visit **Admin → Diagnostics** to see:
- Last 20 cron runs with status, timing, and details
- Any "error" status indicates a problem that needs investigation
- "running" status older than 5 minutes may indicate a stuck process

---

## 12. Admin Panel

### Pages

| Page | URL | Purpose |
|------|-----|---------|
| Login | `/admin/login.php` | Secure admin authentication |
| Settings | `/admin/settings.php` | General, SMTP, webhook configuration |
| Providers | `/admin/providers.php` | CRUD providers and endpoints |
| Alerts | `/admin/alerts.php` | Alert dispatch history |
| Diagnostics | `/admin/diagnostics.php` | System health and cron status |
| Logout | `/admin/logout.php` | End session |

### Settings Page Sections

1. **General** — Public access toggles, check intervals, incident thresholds, retention
2. **SMTP** — Mail server configuration for email alerts
3. **Webhook** — URL for generic POST notifications

### Provider Management

- **Add Provider** — Name, slug, official status URL, feed type, check interval
- **Toggle** — Enable/disable without deleting
- **Delete** — Removes provider and all associated data (cascading)
- **Add Endpoint** — Name, URL, type (HTTP/TLS/DNS), method (HEAD/GET/POST)
- **Delete Endpoint** — Remove individual monitoring targets

### Diagnostics

Displays:
- PHP version, loaded extensions (required + optional), server info
- Database driver, row counts for providers/checks/incidents
- File permission status for `data/`, `logs/`, `config/`
- Recent cron run history with status

---

## 13. Public API

### GET /status.json

When `PUBLIC_STATUS_JSON` is enabled, returns machine-readable status:

```bash
curl https://your-domain.com/ai-noc/status.json.php
```

Response:
```json
{
  "generated_at": "2024-01-15T10:30:00+11:00",
  "version": "1.0.0",
  "providers": [
    {
      "name": "OpenAI",
      "slug": "openai",
      "status": "ok",
      "source": "official",
      "last_checked": "2024-01-15 10:29:45",
      "latency_ms": 245,
      "open_incidents": 0,
      "official_status_url": "https://status.openai.com"
    },
    {
      "name": "Stability AI",
      "slug": "stability",
      "status": "outage",
      "source": "synthetic",
      "last_checked": "2024-01-15 10:28:30",
      "latency_ms": null,
      "open_incidents": 1,
      "official_status_url": null
    }
  ]
}
```

### Status Values

| Value | Meaning |
|-------|---------|
| `ok` | All systems operational |
| `degraded` | Elevated latency or minor issues |
| `partial` | Some endpoints failing |
| `outage` | Major outage (≥50% endpoints down) |
| `unknown` | No recent data or unable to determine |

---

## 14. Security

### Authentication

| Feature | Implementation |
|---------|---------------|
| Password hashing | Argon2id (bcrypt fallback on older PHP) |
| Session hardening | `HttpOnly`, `Secure`, `SameSite=Strict` cookies |
| Session regeneration | New session ID on login to prevent fixation |
| Rate limiting | 5 failed attempts → 15-minute lockout |
| CSRF protection | Per-session random token, verified on all POST requests |

### Encryption

| Feature | Implementation |
|---------|---------------|
| Primary | libsodium `crypto_secretbox` (XSalsa20-Poly1305) |
| Fallback | OpenSSL AES-256-GCM with random IV |
| Key | 32 random bytes, base64-encoded in `config.php` |
| Scope | All values in `settings` table (SMTP passwords, webhook URLs, etc.) |

### Access Control

| Path | Protection |
|------|-----------|
| `/data/` | `.htaccess` deny all |
| `/config/` | `.htaccess` deny all |
| `/logs/` | `.htaccess` deny all |
| `/admin/*` | PHP session check (`Auth::requireLogin()`) |
| `/install/` | Blocked by LOCK file after installation |

### Input Validation

- All user inputs sanitized before database insertion (prepared statements)
- All outputs escaped with `htmlspecialchars()` for XSS prevention
- URL inputs validated with `filter_var(FILTER_VALIDATE_URL)`
- Slugs sanitized to `[a-z0-9\-]` only
- Numeric inputs bounded with `min()`/`max()`

---

## 15. Troubleshooting

### Common Issues

#### Dashboard shows "No checks yet" for all providers

**Cause:** Cron jobs not running.

**Fix:**
1. Verify cron is configured: `crontab -l`
2. Check PHP CLI path: `which php`
3. Run manually: `php cron/run_checks.php`
4. Check `logs/cron.log` for errors
5. Visit Admin → Diagnostics → Recent Cron Runs

#### "Database is locked" errors

**Cause:** Multiple cron scripts writing simultaneously to SQLite.

**Fix:**
- SQLite WAL mode and `busy_timeout=5000` should handle this
- If persistent, increase cron intervals (e.g., run checks every 2 minutes instead of 1)
- Consider switching to MySQL for high-concurrency setups

#### Official status feed shows "unknown" even though provider is up

**Cause:** Feed URL unreachable or feed format doesn't match expected parser.

**Fix:**
1. Test the URL manually: `curl -I https://status.openai.com/api/v2/status.json`
2. Some status pages require `/api/v2/status.json` appended to the base URL
3. Check if the provider uses Statuspage.io (most common) — use the JSON API endpoint
4. For non-standard feeds, configure `metadata_json` with custom path/mapping

#### Emails not sending

**Cause:** SMTP configuration incorrect.

**Fix:**
1. Verify SMTP settings in Admin → Settings
2. Check that your host allows outbound SMTP (some block port 25/587)
3. Try SSL (port 465) instead of TLS (port 587)
4. Check `logs/app.log` for SMTP error messages
5. Verify sender email is authorized by your SMTP provider

#### Install wizard keeps redirecting

**Cause:** `install/LOCK` file missing but `config.php` exists.

**Fix:**
- If installed: `touch install/LOCK`
- If need to reinstall: delete `config/config.php` and `install/LOCK`

#### High memory usage

**Cause:** Too many endpoints checked in parallel.

**Fix:**
- Checks are batched in groups of 50 (configurable in `run_checks.php`)
- Reduce batch size if memory-constrained
- Increase PHP CLI memory limit: `php -d memory_limit=256M cron/run_checks.php`

---

## 16. Extending AI-NOC

### Adding a Custom Status Feed Parser

In `includes/status_parsers.php`, add a new parser method:

```php
private static function parseCustomFormat(string $body, array $metadata): ?array
{
    // Parse your custom format
    $data = json_decode($body, true);
    
    // Return normalized result
    return [
        'state' => 'ok', // ok|degraded|partial|outage|unknown
        'title' => 'All systems go',
        'description' => 'Parsed from custom feed'
    ];
}
```

Then add to the `parse()` method's match expression:

```php
'custom' => self::parseCustomFormat($response['body'], $metadata),
```

### Adding a New Alert Channel

In `includes/alert_engine.php`:

1. Add a new `sendXxx()` method (e.g., `sendSlack()`, `sendTelegram()`)
2. Queue alerts with the new channel name in `queueAlerts()`
3. Handle dispatch in `sendPending()`

### Adding Authenticated API Checks

Endpoints support custom headers via `headers_json`:

```json
{
  "Authorization": "Bearer sk-your-api-key",
  "Content-Type": "application/json"
}
```

Configure via Admin → Providers → endpoint's headers field. API keys should ideally be stored encrypted via the settings table and injected at runtime.

### MySQL Migration

The schema is designed to work with both SQLite and MySQL. To switch:

1. Create a MySQL database in cPanel
2. Update `config.php` to `DB_DRIVER => 'mysql'` with credentials
3. The app will create tables on first load (via `runMigrations()`)
4. Use `mysqldump` / `sqlite3 .dump` to migrate existing data if needed

---

## 17. File Reference

```
php-backend/
├── index.php                     # Dashboard (public or auth-gated)
├── provider.php                  # Provider detail with charts & incidents
├── incidents.php                 # Global incident list with filters
├── status.json.php               # Machine-readable status API
│
├── assets/
│   ├── css/
│   │   └── app.css               # Complete NOC-themed stylesheet
│   ├── js/
│   │   ├── app.js                # Clock, tooltips, auto-refresh
│   │   └── charts.js             # Chart.js latency chart rendering
│   └── vendor/
│       └── chart.min.js          # Chart.js library (download separately)
│
├── includes/
│   ├── bootstrap.php             # App init, config loading, session setup
│   ├── auth.php                  # Login, logout, session management
│   ├── csrf.php                  # CSRF token generation & verification
│   ├── db.php                    # PDO wrapper, migrations, provider seeding
│   ├── crypto.php                # Encryption/decryption (sodium/openssl)
│   ├── http.php                  # Parallel HTTP client (curl_multi)
│   ├── status_parsers.php        # JSON/RSS/Atom/generic feed parsers
│   ├── incident_engine.php       # State computation, incident lifecycle
│   ├── alert_engine.php          # Email SMTP, webhook POST, daily digest
│   └── helpers.php               # Escaping, logging, formatting utilities
│
├── admin/
│   ├── login.php                 # Admin authentication form
│   ├── logout.php                # Session termination
│   ├── settings.php              # General, SMTP, webhook configuration
│   ├── providers.php             # Provider + endpoint CRUD management
│   ├── alerts.php                # Alert dispatch history table
│   └── diagnostics.php           # System health, extensions, cron status
│
├── install/
│   └── index.php                 # 4-step install wizard (self-contained)
│
├── cron/
│   ├── run_checks.php            # Synthetic HTTP/TLS/DNS checks
│   ├── pull_official.php         # Official status feed fetching
│   ├── evaluate_incidents.php    # Incident creation/resolution engine
│   ├── send_alerts.php           # Alert dispatch + daily digest
│   └── cleanup.php               # Data retention purge + DB vacuum
│
├── config/
│   ├── config.sample.php         # Configuration template
│   └── .htaccess                 # Deny web access
│
├── data/
│   └── .htaccess                 # Deny web access
│
├── logs/
│   └── .htaccess                 # Deny web access
│
└── docs/
    ├── README.md                 # Quick start guide
    └── CPANEL_CRON.md            # Cron setup instructions
```
