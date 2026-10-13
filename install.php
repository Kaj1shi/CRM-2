<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$c = $config['db'];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $server = new PDO(
            "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $name = str_replace('`', '', $c['name']);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $server->exec("USE `{$name}`");
        $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $server->exec($statement);
        }
        $roles = [
            ['11111111-1111-4111-8111-111111111111', 'ADMIN', 'Full access, including users, roles, and settings.'],
            ['22222222-2222-4222-8222-222222222222', 'MANAGER', 'Operations access without user or settings control.'],
            ['33333333-3333-4333-8333-333333333333', 'STAFF', 'Record clients, suppliers, and transactions.'],
        ];
        $roleStmt = $server->prepare('INSERT IGNORE INTO roles (id, name, description) VALUES (?, ?, ?)');
        foreach ($roles as $role) {
            $roleStmt->execute($role);
        }
        $permissions = [
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
        $staff = ['dashboard.view','clients.view','clients.create','clients.edit','suppliers.view','suppliers.create','suppliers.edit','products.view','transactions.view','transactions.create','reports.operational','reports.export'];
        $managerSkip = ['users.manage','roles.assign','settings.manage'];
        $permStmt = $server->prepare('INSERT IGNORE INTO permissions (id, `key`, description) VALUES (?, ?, ?)');
        $linkStmt = $server->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($permissions as $key => $description) {
            $id = sprintf('%08x-0000-4000-8000-%012x', crc32($key), crc32($key . '-perm'));
            $permStmt->execute([$id, $key, $description]);
            $linkStmt->execute([$roles[0][0], $id]);
            if (!in_array($key, $managerSkip, true)) {
                $linkStmt->execute([$roles[1][0], $id]);
            }
            if (in_array($key, $staff, true)) {
                $linkStmt->execute([$roles[2][0], $id]);
            }
        }
        $settings = [
            'inactive_after_days' => 30,
            'dormant_after_days' => 90,
            'max_quantity_kg_warning' => 100000,
            'factory_name' => 'Kampala Industrial Area',
            'timezone' => 'Africa/Kampala',
            'client_code_prefix' => 'CL',
            'supplier_code_prefix' => 'SUP',
            'transaction_code_prefix' => 'TXN',
        ];
        $settingStmt = $server->prepare('INSERT IGNORE INTO settings (`key`, value) VALUES (?, ?)');
        foreach ($settings as $key => $value) {
            $settingStmt->execute([$key, json_encode($value)]);
        }
        $seq = $server->prepare('INSERT IGNORE INTO code_sequences (id, prefix, next_value) VALUES (?, ?, 1)');
        foreach ([['client','CL'],['supplier','SUP'],['product_finished','PRD'],['product_raw','MAT'],['product_other','OTH'],['transaction','TXN']] as $row) {
            $seq->execute($row);
        }
        $count = (int) $server->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $hash = password_hash('smith001', PASSWORD_DEFAULT);
            $server->prepare('INSERT INTO users (id, full_name, email, password_hash, role_id, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, 1)')
                ->execute([
                    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                    'Admin',
                    'piimasmith@gmail.com',
                    $hash,
                    $roles[0][0],
                    'ACTIVE',
                ]);
            $message = 'Database ready. Sign in with the administrator account.';
        } else {
            $message = 'Database already has accounts. No extra users were added.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install Factory CRM</title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <main class="grid min-h-dvh place-items-center px-4 py-16">
    <form method="post" class="w-full max-w-md">
      <p class="text-xs tracking-[0.16em] uppercase" style="color:#78716c">Kampala industrial area</p>
      <h1 style="margin-top:0.75rem;font-size:2.5rem;font-weight:500">Install Factory CRM</h1>
      <p style="margin-top:0.75rem;color:#57534e">Creates the local database and the administrator account. No sample clients, products, or transactions are added.</p>
      <?php if ($message): ?><p style="margin-top:1rem" role="status"><?= h($message) ?> <a href="login">Sign in</a></p><?php endif; ?>
      <?php if ($error): ?><p style="margin-top:1rem;color:#9f2f2d" role="alert"><?= h($error) ?></p><?php endif; ?>
      <button style="margin-top:1.5rem;width:100%;border:0;border-radius:0.375rem;background:#059669;color:white;padding:0.7rem 1rem">Create database</button>
    </form>
  </main>
</body>
</html>
