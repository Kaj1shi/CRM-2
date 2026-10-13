<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
$GLOBALS['config'] = $config;

date_default_timezone_set('Africa/Kampala');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path' => base_path() === '' ? '/' : base_path(),
    ]);
    session_start();
}

function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($dir === '/' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = base_path();
    return ($base === '' ? '' : $base) . '/' . $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = $GLOBALS['config']['db'];
    $pdo = new PDO(
        "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
        $c['user'],
        $c['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('The form expired. Go back and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

function client_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

const PERMISSIONS = [
    'dashboard.view' => 'View the dashboard',
    'clients.view' => 'View clients',
    'clients.create' => 'Create clients',
    'clients.edit' => 'Edit clients',
    'clients.deactivate' => 'Deactivate and reactivate clients',
    'suppliers.view' => 'View suppliers',
    'suppliers.create' => 'Create suppliers',
    'suppliers.edit' => 'Edit suppliers',
    'suppliers.deactivate' => 'Deactivate and reactivate suppliers',
    'products.view' => 'View products',
    'products.create' => 'Create products',
    'products.edit' => 'Edit products',
    'products.deactivate' => 'Deactivate and reactivate products',
    'transactions.view' => 'View transactions',
    'transactions.create' => 'Record transactions',
    'transactions.reverse' => 'Reverse a recorded transaction',
    'reports.operational' => 'View operational reports',
    'reports.management' => 'View management reports and analytics',
    'reports.export' => 'Export reports',
    'users.manage' => 'Manage user accounts',
    'roles.assign' => 'Assign roles',
    'settings.manage' => 'Manage system settings',
    'audit.view' => 'View audit logs',
];

function role_permissions(string $role): array
{
    $staff = [
        'dashboard.view', 'clients.view', 'clients.create', 'clients.edit',
        'suppliers.view', 'suppliers.create', 'suppliers.edit', 'products.view',
        'transactions.view', 'transactions.create', 'reports.operational', 'reports.export',
    ];
    $all = array_keys(PERMISSIONS);
    if ($role === 'ADMIN') {
        return $all;
    }
    if ($role === 'MANAGER') {
        return array_values(array_filter($all, fn ($key) => !in_array($key, ['users.manage', 'roles.assign', 'settings.manage'], true)));
    }
    return $staff;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $stmt = db()->prepare(
        'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'ACTIVE') {
        $_SESSION = [];
        $user = null;
        return null;
    }
    $row['permissions'] = role_permissions($row['role_name']);
    $user = $row;
    return $user;
}

function can(string $permission): bool
{
    $user = current_user();
    return $user !== null && in_array($permission, $user['permissions'], true);
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login');
    }
    return $user;
}

function require_permission(string $permission): array
{
    $user = require_login();
    if (!can($permission)) {
        http_response_code(403);
        render('forbidden', ['message' => 'You do not have permission to view this page.']);
        exit;
    }
    return $user;
}

function audit(?string $userId, string $action, string $entity, ?string $entityId, string $details, $old = null, $new = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (id, user_id, action, entity, entity_id, details, old_value, new_value, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        uuid(),
        $userId,
        $action,
        $entity,
        $entityId,
        $details,
        $old === null ? null : json_encode($old),
        $new === null ? null : json_encode($new),
        client_ip(),
        client_agent(),
    ]);
}

function valid_password(string $password): ?string
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Use at least 8 characters with a letter and a number.';
    }
    return null;
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $zone = new DateTimeZone('Africa/Kampala');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $dt = new DateTime($value . ' 00:00:00', $zone);
    } else {
        $dt = new DateTime($value);
        $dt->setTimezone($zone);
    }
    return $dt->format('d M Y');
}

function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $dt = new DateTime($value);
    $dt->setTimezone(new DateTimeZone('Africa/Kampala'));
    return $dt->format('d M Y, H:i:s');
}

function format_kg($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $number = (float) $value;
    if ($number >= 1000) {
        $tonnes = round($number / 1000, 3);
        return rtrim(rtrim(number_format($tonnes, 3, '.', ''), '0'), '.') . ' tonnes';
    }
    $text = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    return $text . ' kg';
}

function round_qty(float $value): float
{
    return round($value, 3);
}

function route_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return trim($path, '/');
}
