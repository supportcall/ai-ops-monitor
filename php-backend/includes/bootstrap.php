<?php
/**
 * AI-NOC — Bootstrap
 * File: /includes/bootstrap.php
 */

declare(strict_types=1);

define('AINOC_ROOT', dirname(__DIR__));
define('AINOC_VERSION', '1.0.0');

// Check install lock
if (!file_exists(AINOC_ROOT . '/install/LOCK') && !defined('AINOC_INSTALLING')) {
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/install/index.php');
    exit;
}

// Load config
$configPath = AINOC_ROOT . '/config/config.php';
if (!file_exists($configPath)) {
    die('Configuration file not found. Please run the installer.');
}

$config = require $configPath;

// Timezone
date_default_timezone_set($config['TIMEZONE'] ?? 'UTC');

// Error reporting (production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', AINOC_ROOT . '/logs/app.log');

// Autoload includes
require_once AINOC_ROOT . '/includes/helpers.php';
require_once AINOC_ROOT . '/includes/crypto.php';
require_once AINOC_ROOT . '/includes/db.php';
require_once AINOC_ROOT . '/includes/csrf.php';
require_once AINOC_ROOT . '/includes/auth.php';

// Initialize database
$db = AiNoc\DB::getInstance($config);

// Session (only for web requests)
if (php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)($config['SESSION_LIFETIME'] ?? 3600));
    session_name($config['SESSION_NAME'] ?? 'ainoc_sid');
    session_start();
}
