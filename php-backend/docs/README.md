# AI-NOC — AI Provider Network Operations Center

## Overview

AI-NOC is a self-contained, production-ready dashboard for monitoring outages and degradation across 20+ major AI/LLM providers. It runs entirely on PHP 8.1+ with SQLite (or MySQL) on standard cPanel/Apache hosting.

## Features

- **20 pre-configured AI providers** with synthetic monitoring endpoints
- **Official status feed integration** (Statuspage.io JSON, RSS/Atom, generic HTML)
- **Parallel HTTP checks** using `curl_multi` for high throughput
- **Incident engine** with configurable thresholds for creation/resolution
- **Email + Webhook alerting** with cooldown deduplication
- **Daily digest emails** with 24h summary
- **90-day uptime heatmap** and latency charts (Chart.js)
- **Admin panel** for providers, endpoints, settings, and diagnostics
- **Public dashboard** with optional `/status.json` API
- **Encrypted secrets** (libsodium preferred, OpenSSL AES-256-GCM fallback)
- **Install wizard** with environment checks

## Requirements

- PHP 8.1+ (8.2 recommended)
- Extensions: `pdo`, `pdo_sqlite`, `curl`, `json`, `mbstring`, `openssl`
- Optional: `sodium` (stronger encryption), `pdo_mysql` (MySQL support), `simplexml` (RSS feeds)
- Apache with `.htaccess` support
- cPanel with Cron Jobs access

## Quick Start

1. Upload the `ai-noc/` folder to your `public_html/` directory
2. Ensure `data/`, `logs/`, and `config/` directories are writable:
   ```bash
   chmod 750 data/ logs/ config/
   ```
3. Visit `https://your-domain.com/ai-noc/install/` in your browser
4. Follow the 4-step install wizard
5. Set up cron jobs per `docs/CPANEL_CRON.md`
6. Run initial checks: `php cron/run_checks.php`

## File Structure

```
ai-noc/
├── index.php              # Public dashboard
├── provider.php           # Provider detail page
├── incidents.php          # Global incident list
├── status.json.php        # Machine-readable status API
├── assets/
│   ├── css/app.css        # All styles
│   ├── js/app.js          # Core JS (clock, tooltips)
│   ├── js/charts.js       # Chart.js rendering
│   └── vendor/chart.min.js # Chart.js library (download separately)
├── includes/
│   ├── bootstrap.php      # App initialization
│   ├── auth.php           # Authentication
│   ├── csrf.php           # CSRF protection
│   ├── db.php             # Database layer + migrations
│   ├── crypto.php         # Encryption (sodium/openssl)
│   ├── http.php           # Parallel HTTP client
│   ├── status_parsers.php # Official feed parsers
│   ├── incident_engine.php # State evaluation + incidents
│   ├── alert_engine.php   # Email + webhook alerting
│   └── helpers.php        # Utilities
├── admin/
│   ├── login.php          # Admin login
│   ├── logout.php         # Logout
│   ├── settings.php       # General, SMTP, webhook config
│   ├── providers.php      # Provider + endpoint CRUD
│   ├── alerts.php         # Alert history
│   └── diagnostics.php    # System health checks
├── install/
│   └── index.php          # 4-step install wizard
├── cron/
│   ├── run_checks.php     # Synthetic HTTP checks
│   ├── pull_official.php  # Official status feeds
│   ├── evaluate_incidents.php # Incident creation/resolution
│   ├── send_alerts.php    # Alert dispatch + daily digest
│   └── cleanup.php        # Data retention cleanup
├── config/
│   ├── config.sample.php  # Sample configuration
│   └── .htaccess          # Deny web access
├── data/
│   ├── ai-noc.sqlite      # SQLite database (created on install)
│   └── .htaccess          # Deny web access
├── logs/
│   └── .htaccess          # Deny web access
└── docs/
    ├── README.md           # This file
    └── CPANEL_CRON.md      # Cron setup guide
```

## Security

- **Admin login** with session hardening, CSRF protection, and rate limiting
- **Password hashing** with Argon2id (bcrypt fallback)
- **Encrypted settings** in database using libsodium or AES-256-GCM
- **`.htaccess` deny rules** on `data/`, `config/`, `logs/`
- **Input validation** on all forms; output escaping for XSS prevention
- **Session cookies** set with `HttpOnly`, `Secure`, and `SameSite=Strict`
- **Installer auto-locks** after completion

## Chart.js

Download Chart.js and place it at `assets/vendor/chart.min.js`:
```bash
curl -o assets/vendor/chart.min.js https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js
```

Charts gracefully degrade to a text message if JavaScript is disabled.

## Adding Custom Providers

1. Go to **Admin → Providers**
2. Fill in name, slug, and optional official status URL
3. Add synthetic endpoints (HTTP/HEAD checks against known URLs)
4. Set check interval and save
5. Checks will start on the next cron cycle

## API

When enabled, `GET /status.json` returns:
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
      "latency_ms": 245,
      "open_incidents": 0
    }
  ]
}
```

## License

MIT — Use freely for internal or commercial monitoring.
