<?php
/**
 * AI-NOC — Install Wizard
 * File: /install/index.php
 */

declare(strict_types=1);
define('AINOC_INSTALLING', true);
define('AINOC_ROOT', dirname(__DIR__));
define('AINOC_VERSION', '1.0.0');

// Check if already installed
if (file_exists(__DIR__ . '/LOCK')) {
    die('AI-NOC is already installed. Delete /install/LOCK to re-run installer.');
}

session_start();

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// Step 1: Environment Check
if ($step === 1) {
    $checks = [
        'PHP Version ≥ 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO SQLite' => extension_loaded('pdo_sqlite'),
        'cURL Extension' => extension_loaded('curl'),
        'JSON Extension' => extension_loaded('json'),
        'mbstring Extension' => extension_loaded('mbstring'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'data/ writable' => is_writable(AINOC_ROOT . '/data') || @mkdir(AINOC_ROOT . '/data', 0750, true),
        'logs/ writable' => is_writable(AINOC_ROOT . '/logs') || @mkdir(AINOC_ROOT . '/logs', 0750, true),
        'config/ writable' => is_writable(AINOC_ROOT . '/config'),
    ];
    $allPassed = !in_array(false, $checks, true);
    $hasSodium = extension_loaded('sodium');
}

// Step 2: Database Setup
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['install'] = $_SESSION['install'] ?? [];
    $_SESSION['install']['db_driver'] = $_POST['db_driver'] ?? 'sqlite';
    $_SESSION['install']['db_mysql_host'] = $_POST['db_mysql_host'] ?? '';
    $_SESSION['install']['db_mysql_port'] = $_POST['db_mysql_port'] ?? '3306';
    $_SESSION['install']['db_mysql_name'] = $_POST['db_mysql_name'] ?? '';
    $_SESSION['install']['db_mysql_user'] = $_POST['db_mysql_user'] ?? '';
    $_SESSION['install']['db_mysql_pass'] = $_POST['db_mysql_pass'] ?? '';
    
    header('Location: index.php?step=3');
    exit;
}

// Step 3: Admin Account
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $timezone = $_POST['timezone'] ?? 'Australia/Hobart';

    if (!$email) { $error = 'Valid email required.'; }
    elseif (strlen($password) < 8) { $error = 'Password must be at least 8 characters.'; }
    elseif ($password !== $confirm) { $error = 'Passwords do not match.'; }
    elseif (empty($baseUrl)) { $error = 'Base URL is required.'; }
    else {
        $_SESSION['install']['admin_email'] = $email;
        $_SESSION['install']['admin_password'] = $password;
        $_SESSION['install']['base_url'] = $baseUrl;
        $_SESSION['install']['timezone'] = $timezone;
        header('Location: index.php?step=4');
        exit;
    }
}

// Step 4: Finalize
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $inst = $_SESSION['install'];
        $appKey = base64_encode(random_bytes(32));

        // Generate config file
        $configContent = "<?php\n// AI-NOC Configuration — Generated " . date('Y-m-d H:i:s T') . "\nreturn [\n"
            . "    'APP_KEY' => " . var_export($appKey, true) . ",\n"
            . "    'BASE_URL' => " . var_export($inst['base_url'], true) . ",\n"
            . "    'DB_DRIVER' => " . var_export($inst['db_driver'], true) . ",\n"
            . "    'DB_SQLITE_PATH' => __DIR__ . '/../data/ai-noc.sqlite',\n"
            . "    'DB_MYSQL_HOST' => " . var_export($inst['db_mysql_host'] ?? '', true) . ",\n"
            . "    'DB_MYSQL_PORT' => " . var_export((int)($inst['db_mysql_port'] ?? 3306), true) . ",\n"
            . "    'DB_MYSQL_NAME' => " . var_export($inst['db_mysql_name'] ?? '', true) . ",\n"
            . "    'DB_MYSQL_USER' => " . var_export($inst['db_mysql_user'] ?? '', true) . ",\n"
            . "    'DB_MYSQL_PASS' => " . var_export($inst['db_mysql_pass'] ?? '', true) . ",\n"
            . "    'SESSION_LIFETIME' => 3600,\n"
            . "    'SESSION_NAME' => 'ainoc_sid',\n"
            . "    'LOGIN_MAX_ATTEMPTS' => 5,\n"
            . "    'LOGIN_LOCKOUT_MINS' => 15,\n"
            . "    'TIMEZONE' => " . var_export($inst['timezone'], true) . ",\n"
            . "    'LOG_LEVEL' => 'info',\n"
            . "    'PUBLIC_DASHBOARD' => true,\n"
            . "    'PUBLIC_STATUS_JSON' => true,\n"
            . "    'DEFAULT_CHECK_INTERVAL' => 300,\n"
            . "    'DEFAULT_TIMEOUT_MS' => 10000,\n"
            . "    'INCIDENT_CONFIRM_CHECKS' => 3,\n"
            . "    'INCIDENT_RESOLVE_CHECKS' => 3,\n"
            . "    'RETENTION_RAW_CHECKS' => 90,\n"
            . "    'RETENTION_INCIDENTS' => 365,\n"
            . "    'SMTP_ENABLED' => false,\n"
            . "    'SMTP_HOST' => '',\n"
            . "    'SMTP_PORT' => 587,\n"
            . "    'SMTP_USER' => '',\n"
            . "    'SMTP_PASS' => '',\n"
            . "    'SMTP_FROM' => 'noc@localhost',\n"
            . "    'SMTP_FROM_NAME' => 'AI-NOC',\n"
            . "    'SMTP_ENCRYPTION' => 'tls',\n"
            . "    'WEBHOOK_ENABLED' => false,\n"
            . "    'WEBHOOK_URL' => '',\n"
            . "    'DIGEST_TIME' => '08:00',\n"
            . "    'ALERT_COOLDOWN_MINS' => 30,\n"
            . "];\n";

        // Write config
        $configPath = AINOC_ROOT . '/config/config.php';
        if (file_put_contents($configPath, $configContent) === false) {
            throw new \RuntimeException('Could not write config file');
        }
        chmod($configPath, 0640);

        // Load config and init DB
        $config = require $configPath;
        date_default_timezone_set($config['TIMEZONE']);

        require_once AINOC_ROOT . '/includes/helpers.php';
        require_once AINOC_ROOT . '/includes/crypto.php';
        require_once AINOC_ROOT . '/includes/db.php';

        $db = \AiNoc\DB::getInstance($config);
        $db->runMigrations();
        $db->seedDefaultProviders();

        // Create admin user
        require_once AINOC_ROOT . '/includes/auth.php';
        \AiNoc\Auth::createUser($inst['admin_email'], $inst['admin_password']);

        // Create LOCK file
        file_put_contents(__DIR__ . '/LOCK', 'Installed on ' . date('Y-m-d H:i:s T'));

        // Create .htaccess files for security
        $denyAll = "Order Deny,Allow\nDeny from all\n";
        @file_put_contents(AINOC_ROOT . '/data/.htaccess', $denyAll);
        @file_put_contents(AINOC_ROOT . '/config/.htaccess', $denyAll);
        @file_put_contents(AINOC_ROOT . '/logs/.htaccess', $denyAll);

        // Clear install session
        unset($_SESSION['install']);

        $success = 'Installation complete!';
    } catch (\Throwable $e) {
        $error = 'Installation failed: ' . $e->getMessage();
    }
}

$autoBaseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — AI-NOC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', monospace; background: #0a0e14; color: #c5cbd3; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .installer { max-width: 600px; width: 100%; background: #131920; border: 1px solid #1e2730; border-radius: 8px; padding: 40px; }
        h1 { color: #10b981; font-size: 24px; margin-bottom: 8px; }
        h2 { color: #e2e8f0; font-size: 18px; margin: 20px 0 12px; }
        .subtitle { color: #6b7280; font-size: 13px; margin-bottom: 24px; }
        .steps { display: flex; gap: 8px; margin-bottom: 24px; }
        .step-dot { width: 32px; height: 4px; border-radius: 2px; background: #1e2730; }
        .step-dot.active { background: #10b981; }
        .step-dot.done { background: #065f46; }
        .check-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1e2730; font-size: 14px; }
        .pass { color: #10b981; } .fail { color: #ef4444; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #9ca3af; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px; background: #0a0e14; border: 1px solid #1e2730; border-radius: 4px; color: #e2e8f0; font-size: 14px; font-family: monospace; }
        input:focus, select:focus { outline: none; border-color: #10b981; }
        .btn { display: inline-block; padding: 10px 24px; background: #10b981; color: #0a0e14; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; margin-top: 8px; }
        .btn:hover { background: #059669; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; }
        .alert-error { background: #7f1d1d33; border: 1px solid #ef4444; color: #fca5a5; }
        .alert-success { background: #065f4633; border: 1px solid #10b981; color: #6ee7b7; }
        .note { font-size: 12px; color: #6b7280; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="installer">
        <h1>🖥 AI-NOC Installer</h1>
        <p class="subtitle">AI Provider Network Operations Center — v<?= AINOC_VERSION ?></p>

        <div class="steps">
            <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
            <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
            <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>"></div>
            <div class="step-dot <?= $step >= 4 ? 'active' : '' ?>"></div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if ($step === 1): ?>
            <h2>Step 1: Environment Check</h2>
            <?php foreach ($checks as $label => $pass): ?>
                <div class="check-row">
                    <span><?= $label ?></span>
                    <span class="<?= $pass ? 'pass' : 'fail' ?>"><?= $pass ? '✓ Pass' : '✗ Fail' ?></span>
                </div>
            <?php endforeach; ?>
            <div class="check-row">
                <span>Sodium Extension <span class="note">(optional, recommended)</span></span>
                <span class="<?= $hasSodium ? 'pass' : '' ?>"><?= $hasSodium ? '✓ Available' : '— Using OpenSSL fallback' ?></span>
            </div>
            <br>
            <?php if ($allPassed): ?>
                <a href="index.php?step=2" class="btn">Continue →</a>
            <?php else: ?>
                <p class="fail">Fix the failing checks above before continuing.</p>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <h2>Step 2: Database</h2>
            <form method="post">
                <div class="form-group">
                    <label>Database Driver</label>
                    <select name="db_driver" id="dbDriver" onchange="document.getElementById('mysqlFields').style.display=this.value==='mysql'?'block':'none'">
                        <option value="sqlite">SQLite (Recommended)</option>
                        <option value="mysql">MySQL</option>
                    </select>
                    <p class="note">SQLite requires no configuration. Data stored in /data/ai-noc.sqlite</p>
                </div>
                <div id="mysqlFields" style="display:none">
                    <div class="form-group"><label>MySQL Host</label><input name="db_mysql_host" value="127.0.0.1"></div>
                    <div class="form-group"><label>MySQL Port</label><input name="db_mysql_port" value="3306"></div>
                    <div class="form-group"><label>Database Name</label><input name="db_mysql_name"></div>
                    <div class="form-group"><label>Username</label><input name="db_mysql_user"></div>
                    <div class="form-group"><label>Password</label><input type="password" name="db_mysql_pass"></div>
                </div>
                <button type="submit" class="btn">Continue →</button>
            </form>

        <?php elseif ($step === 3): ?>
            <h2>Step 3: Admin Account & Settings</h2>
            <form method="post">
                <div class="form-group"><label>Base URL</label><input name="base_url" value="<?= htmlspecialchars($autoBaseUrl) ?>" required><p class="note">The public URL of your AI-NOC installation</p></div>
                <div class="form-group">
                    <label>Timezone</label>
                    <select name="timezone">
                        <option value="Australia/Hobart" selected>Australia/Hobart</option>
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New_York</option>
                        <option value="America/Los_Angeles">America/Los_Angeles</option>
                        <option value="Europe/London">Europe/London</option>
                        <option value="Asia/Tokyo">Asia/Tokyo</option>
                        <option value="Asia/Singapore">Asia/Singapore</option>
                    </select>
                </div>
                <div class="form-group"><label>Admin Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Password (min 8 chars)</label><input type="password" name="password" required minlength="8"></div>
                <div class="form-group"><label>Confirm Password</label><input type="password" name="password_confirm" required></div>
                <button type="submit" class="btn">Continue →</button>
            </form>

        <?php elseif ($step === 4): ?>
            <?php if ($success): ?>
                <h2>✓ Installation Complete!</h2>
                <p>AI-NOC is ready. 20 providers with synthetic endpoints are pre-configured.</p>
                <h2>Next Steps:</h2>
                <ol style="padding-left:20px;line-height:2">
                    <li>Set up cron jobs (see <code>/docs/CPANEL_CRON.md</code>)</li>
                    <li>Configure SMTP for alerts in Admin → Settings</li>
                    <li>Run initial checks manually: <code>php cron/run_checks.php</code></li>
                </ol>
                <br>
                <a href="../index.php" class="btn">Go to Dashboard →</a>
                <a href="../admin/settings.php" class="btn" style="background:#1e2730;color:#e2e8f0">Admin Settings</a>
            <?php else: ?>
                <h2>Step 4: Finalize Installation</h2>
                <p>Ready to install. This will:</p>
                <ul style="padding-left:20px;line-height:2;margin:12px 0">
                    <li>Generate encryption key & write config</li>
                    <li>Create database schema</li>
                    <li>Seed 20 AI provider templates with endpoints</li>
                    <li>Create admin account</li>
                    <li>Lock installer</li>
                </ul>
                <form method="post">
                    <button type="submit" class="btn">Install AI-NOC →</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
