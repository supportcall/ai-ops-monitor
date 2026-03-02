<?php
/**
 * AI-NOC — Admin Providers CRUD
 * File: /admin/providers.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

use AiNoc\{Auth, CSRF, Helpers};

Auth::requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = Helpers::sanitizeSlug($_POST['slug'] ?? $name);
        $statusUrl = filter_var(trim($_POST['official_status_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;
        $statusType = in_array($_POST['status_type'] ?? '', ['json', 'rss', 'atom', 'generic']) ? $_POST['status_type'] : 'json';
        $interval = max(60, min(3600, (int)($_POST['check_interval'] ?? 300)));

        if (empty($name) || empty($slug)) {
            $error = 'Name and slug are required.';
        } else {
            $existing = $db->fetchOne("SELECT id FROM providers WHERE slug = ?", [$slug]);
            if ($existing) {
                $error = 'Slug already exists.';
            } else {
                $db->insert('providers', [
                    'name' => $name,
                    'slug' => $slug,
                    'enabled' => 1,
                    'official_status_url' => $statusUrl,
                    'official_status_type' => $statusType,
                    'metadata_json' => '{}',
                    'check_interval' => $interval,
                    'latency_threshold_ms' => 5000,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $success = "Provider '{$name}' added.";
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['provider_id'] ?? 0);
        $enabled = (int)($_POST['enabled'] ?? 0);
        $db->update('providers', ['enabled' => $enabled ? 0 : 1], 'id = ?', [$id]);
        $success = 'Provider toggled.';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['provider_id'] ?? 0);
        $db->query("DELETE FROM providers WHERE id = ?", [$id]);
        $success = 'Provider deleted.';
    }

    if ($action === 'add_endpoint') {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $epName = trim($_POST['ep_name'] ?? '');
        $epUrl = trim($_POST['ep_url'] ?? '');
        $epType = in_array($_POST['ep_type'] ?? '', ['http', 'tls', 'dns']) ? $_POST['ep_type'] : 'http';
        $epMethod = in_array($_POST['ep_method'] ?? '', ['GET', 'HEAD', 'POST']) ? $_POST['ep_method'] : 'GET';

        if ($providerId && $epName && $epUrl) {
            $db->insert('endpoints', [
                'provider_id' => $providerId,
                'name' => $epName,
                'type' => $epType,
                'url' => $epUrl,
                'method' => $epMethod,
                'headers_json' => '{}',
                'body' => null,
                'timeout_ms' => 10000,
                'expect_code' => null,
                'expect_contains' => null,
                'enabled' => 1,
            ]);
            $success = "Endpoint added.";
        } else {
            $error = 'Name, URL, and provider are required for endpoint.';
        }
    }

    if ($action === 'delete_endpoint') {
        $id = (int)($_POST['endpoint_id'] ?? 0);
        $db->query("DELETE FROM endpoints WHERE id = ?", [$id]);
        $success = 'Endpoint deleted.';
    }
}

$providers = $db->fetchAll("SELECT * FROM providers ORDER BY name");

// Load endpoints per provider
foreach ($providers as &$p) {
    $p['endpoints'] = $db->fetchAll("SELECT * FROM endpoints WHERE provider_id = ? ORDER BY name", [$p['id']]);
}
unset($p);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Providers — AI-NOC Admin</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <header class="noc-header">
        <div class="container">
            <div class="header-row">
                <div class="header-brand"><span class="header-dot pulse"></span><h1>AI-NOC Admin</h1></div>
                <nav class="header-nav">
                    <a href="../index.php">Dashboard</a>
                    <a href="settings.php">Settings</a>
                    <a href="providers.php" class="active">Providers</a>
                    <a href="alerts.php">Alerts</a>
                    <a href="diagnostics.php">Diagnostics</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container admin-main">
        <h1 class="page-title">Providers & Endpoints</h1>

        <?php if ($success): ?><div class="alert alert-success"><?= Helpers::e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= Helpers::e($error) ?></div><?php endif; ?>

        <!-- Add Provider -->
        <section class="admin-section">
            <h2>Add Provider</h2>
            <form method="post">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Slug</label><input type="text" name="slug" placeholder="auto-generated"></div>
                    <div class="form-group"><label>Official Status URL</label><input type="url" name="official_status_url"></div>
                    <div class="form-group">
                        <label>Status Feed Type</label>
                        <select name="status_type">
                            <option value="json">JSON (Statuspage.io)</option>
                            <option value="rss">RSS</option>
                            <option value="atom">Atom</option>
                            <option value="generic">Generic HTML</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Check Interval (sec)</label><input type="number" name="check_interval" value="300" min="60" max="3600"></div>
                </div>
                <button type="submit" class="btn btn-primary">Add Provider</button>
            </form>
        </section>

        <!-- Provider List -->
        <section class="admin-section">
            <h2>Providers (<?= count($providers) ?>)</h2>
            <?php foreach ($providers as $p): ?>
            <div class="provider-admin-card <?= $p['enabled'] ? '' : 'disabled' ?>">
                <div class="pac-header">
                    <strong><?= Helpers::e($p['name']) ?></strong>
                    <span class="mono dim"><?= Helpers::e($p['slug']) ?></span>
                    <?php if ($p['official_status_url']): ?>
                        <a href="<?= Helpers::e($p['official_status_url']) ?>" target="_blank" class="meta-link">Official ↗</a>
                    <?php endif; ?>
                    <div class="pac-actions">
                        <form method="post" class="inline-form">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="enabled" value="<?= $p['enabled'] ?>">
                            <button type="submit" class="btn btn-sm"><?= $p['enabled'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this provider and all its data?')">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>

                <!-- Endpoints -->
                <div class="pac-endpoints">
                    <h4>Endpoints (<?= count($p['endpoints']) ?>)</h4>
                    <?php foreach ($p['endpoints'] as $ep): ?>
                    <div class="endpoint-row">
                        <span><?= Helpers::e($ep['name']) ?></span>
                        <span class="mono dim"><?= Helpers::e($ep['url']) ?></span>
                        <span class="mono upper"><?= Helpers::e($ep['type']) ?> / <?= Helpers::e($ep['method']) ?></span>
                        <form method="post" class="inline-form">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="delete_endpoint">
                            <input type="hidden" name="endpoint_id" value="<?= $ep['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">×</button>
                        </form>
                    </div>
                    <?php endforeach; ?>

                    <!-- Add endpoint form -->
                    <form method="post" class="add-endpoint-form">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="add_endpoint">
                        <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                        <input type="text" name="ep_name" placeholder="Endpoint name" required>
                        <input type="url" name="ep_url" placeholder="https://..." required>
                        <select name="ep_type"><option value="http">HTTP</option><option value="tls">TLS</option><option value="dns">DNS</option></select>
                        <select name="ep_method"><option value="HEAD">HEAD</option><option value="GET">GET</option><option value="POST">POST</option></select>
                        <button type="submit" class="btn btn-sm">+ Add</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
