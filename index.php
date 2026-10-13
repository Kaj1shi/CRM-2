<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/domain.php';
require __DIR__ . '/includes/ui.php';
require __DIR__ . '/includes/layout.php';

$path = route_path();

if (!in_array($path, ['login', 'forgot-password', 'reset-password'], true)) {
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $exception) {
        header('Location: ' . url('install.php'));
        exit;
    }
}

if ($path === 'logout') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_check();
        $user = current_user();
        if ($user) {
            audit($user['id'], 'LOGOUT', 'User', $user['id'], 'User signed out.');
        }
    }
    $_SESSION = [];
    session_destroy();
    redirect('login');
}

if ($path === 'search') {
    require_login();
    $q = trim((string) ($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $clients = [];
    $suppliers = [];
    $transactions = [];
    if (strlen($q) >= 2) {
        $stmt = db()->prepare('SELECT id, name FROM clients WHERE name LIKE ? OR client_code LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name LIMIT 8');
        $stmt->execute([$like, $like, $like, $like]);
        $clients = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT id, name FROM suppliers WHERE name LIKE ? OR supplier_code LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name LIMIT 8');
        $stmt->execute([$like, $like, $like, $like]);
        $suppliers = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT id, transaction_code AS transactionCode FROM transactions WHERE transaction_code LIKE ? OR reference_number LIKE ? ORDER BY created_at DESC LIMIT 8');
        $stmt->execute([$like, $like]);
        $transactions = $stmt->fetchAll();
    }
    header('Content-Type: application/json');
    echo json_encode(['clients' => $clients, 'suppliers' => $suppliers, 'transactions' => $transactions]);
    exit;
}

if ($path === 'login') {
    render('login');
    exit;
}
if ($path === 'forgot-password') {
    render('forgot');
    exit;
}
if ($path === 'reset-password') {
    render('reset');
    exit;
}
if ($path === '' || $path === 'dashboard') {
    require_permission('dashboard.view');
    render('dashboard');
    exit;
}
if ($path === 'clients' || $path === 'suppliers') {
    $kind = $path;
    require_permission($kind . '.view');
    render('parties', ['kind' => $kind]);
    exit;
}
if (preg_match('#^(clients|suppliers)/([0-9a-fA-F-]{36})$#', $path, $match)) {
    $kind = $match[1];
    require_permission($kind . '.view');
    render('party', ['kind' => $kind, 'id' => $match[2]]);
    exit;
}
if ($path === 'products') {
    require_permission('products.view');
    render('products');
    exit;
}
if ($path === 'transactions') {
    require_permission('transactions.view');
    render('transactions');
    exit;
}
if ($path === 'reports') {
    require_permission('reports.operational');
    render('reports');
    exit;
}
if ($path === 'users') {
    require_permission('users.manage');
    render('users');
    exit;
}
if ($path === 'audit') {
    require_permission('audit.view');
    render('audit');
    exit;
}
if ($path === 'settings') {
    require_permission('settings.manage');
    render('settings');
    exit;
}

http_response_code(404);
require_login();
layout_start($path);
echo '<h1 class="text-4xl font-medium">Page not found</h1>';
layout_end();
