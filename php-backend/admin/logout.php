<?php
/**
 * AI-NOC — Admin Logout
 * File: /admin/logout.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

\AiNoc\Auth::logout();
\AiNoc\Helpers::redirect(\AiNoc\Helpers::baseUrl('admin/login.php'));
