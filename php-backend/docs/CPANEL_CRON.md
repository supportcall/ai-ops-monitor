# AI-NOC — cPanel Cron Setup Guide

## Overview

AI-NOC uses 5 cron scripts that should be scheduled via cPanel's Cron Jobs interface.

Replace `/home/USER/public_html/ai-noc` with your actual installation path.

---

## Recommended Cron Schedule

### 1. Run Synthetic Checks (every minute)
```
* * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/run_checks.php >/dev/null 2>&1
```
The script internally respects each provider's check interval (1m/5m/etc.), so running every minute is safe — it will only check providers that are due.

### 2. Pull Official Status Feeds (every 5 minutes)
```
*/5 * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/pull_official.php >/dev/null 2>&1
```

### 3. Evaluate Incidents (every 2 minutes)
```
*/2 * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/evaluate_incidents.php >/dev/null 2>&1
```

### 4. Send Alerts (every minute)
```
* * * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/send_alerts.php >/dev/null 2>&1
```

### 5. Cleanup Old Data (daily at 3:00 AM)
```
0 3 * * * php -d detect_unicode=0 /home/USER/public_html/ai-noc/cron/cleanup.php >/dev/null 2>&1
```

---

## How to Set Up in cPanel

1. Log in to cPanel
2. Find **Cron Jobs** under the **Advanced** section
3. For each script above:
   - Set the schedule using the dropdowns or paste the cron expression
   - Paste the full command into the **Command** field
   - Click **Add New Cron Job**

---

## Verify Cron is Working

1. Visit **Admin → Diagnostics** in AI-NOC
2. Check the **Recent Cron Runs** table
3. Each script should show entries with timestamps
4. Status should be "done" — "error" entries indicate problems

---

## Troubleshooting

### Cron not running?
- Verify PHP CLI path: `which php` on SSH (usually `/usr/local/bin/php` or `/opt/cpanel/ea-php82/root/usr/bin/php`)
- Use full PHP path in cron if needed

### Permission errors?
- Ensure `data/` and `logs/` directories are writable by the cron user
- Run: `chmod 750 data/ logs/ config/`

### DB locked errors?
- SQLite uses WAL mode with busy_timeout — this should handle concurrent access
- If persistent, increase interval between cron runs

### Scripts timing out?
- Default PHP CLI has no time limit
- If cPanel sets one, add `-d max_execution_time=300` to the PHP command

---

## Manual Run (Testing)

SSH into your server and run any script directly:
```bash
cd /home/USER/public_html/ai-noc
php cron/run_checks.php
php cron/pull_official.php
php cron/evaluate_incidents.php
```

Check output and `logs/cron.log` for results.
