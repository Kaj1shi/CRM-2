<?php

declare(strict_types=1);

const DEFAULT_SETTINGS = [
    'inactive_after_days' => 30,
    'dormant_after_days' => 90,
    'max_quantity_kg_warning' => 100000,
    'factory_name' => 'Kampala Industrial Area',
    'timezone' => 'Africa/Kampala',
    'client_code_prefix' => 'CL',
    'supplier_code_prefix' => 'SUP',
    'transaction_code_prefix' => 'TXN',
];

const ROLE_IDS = [
    'ADMIN' => '11111111-1111-4111-8111-111111111111',
    'MANAGER' => '22222222-2222-4222-8222-222222222222',
    'STAFF' => '33333333-3333-4333-8333-333333333333',
];

function settings_all(): array
{
    $values = DEFAULT_SETTINGS;
    foreach (db()->query('SELECT `key`, value FROM settings') as $row) {
        $decoded = json_decode((string) $row['value'], true);
        $values[$row['key']] = $decoded === null ? $row['value'] : $decoded;
    }
    return $values;
}

function setting_number(string $key): float
{
    $values = settings_all();
    $value = $values[$key] ?? DEFAULT_SETTINGS[$key] ?? 0;
    return is_numeric($value) ? (float) $value : (float) DEFAULT_SETTINGS[$key];
}

function next_code(PDO $pdo, string $sequenceId): string
{
    $stmt = $pdo->prepare('SELECT prefix, next_value FROM code_sequences WHERE id = ? FOR UPDATE');
    $stmt->execute([$sequenceId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Missing code sequence ' . $sequenceId);
    }
    $pdo->prepare('UPDATE code_sequences SET next_value = next_value + 1 WHERE id = ?')->execute([$sequenceId]);
    return $row['prefix'] . '-' . str_pad((string) $row['next_value'], 6, '0', STR_PAD_LEFT);
}

function classify_frequency(array $dates): string
{
    $days = [];
    foreach ($dates as $date) {
        if ($date === null || $date === '') {
            continue;
        }
        $days[substr((string) $date, 0, 10)] = true;
    }
    $unique = array_keys($days);
    sort($unique);
    if (count($unique) < 3) {
        return 'INSUFFICIENT_DATA';
    }
    $gaps = [];
    for ($i = 1; $i < count($unique); $i++) {
        $prev = strtotime($unique[$i - 1] . ' UTC');
        $curr = strtotime($unique[$i] . ' UTC');
        $gaps[] = (int) round(($curr - $prev) / 86400);
    }
    sort($gaps);
    $middle = intdiv(count($gaps), 2);
    $gap = count($gaps) % 2 === 0 ? ($gaps[$middle - 1] + $gaps[$middle]) / 2 : $gaps[$middle];
    if ($gap <= 3) return 'MULTIPLE_PER_WEEK';
    if ($gap <= 9) return 'WEEKLY';
    if ($gap <= 18) return 'BIWEEKLY';
    if ($gap <= 40) return 'MONTHLY';
    return 'IRREGULAR';
}

function classify_activity(?string $lastDate, int $inactiveAfter, int $dormantAfter): string
{
    if ($lastDate === null || $lastDate === '') {
        return 'DORMANT';
    }
    $elapsed = (time() - strtotime(substr($lastDate, 0, 10) . ' 00:00:00')) / 86400;
    if ($elapsed <= $inactiveAfter) return 'ACTIVE';
    if ($elapsed <= $dormantAfter) return 'AT_RISK';
    return 'DORMANT';
}

function normalize_name(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return mb_strtolower($value);
}

function normalize_phone(?string $value): string
{
    return preg_replace('/\D/', '', (string) $value) ?? '';
}

function duplicate_warnings(array $candidate, array $existing): array
{
    $name = normalize_name($candidate['name'] ?? '');
    $phone = normalize_phone($candidate['phone'] ?? '');
    $email = mb_strtolower(trim((string) ($candidate['email'] ?? '')));
    $warnings = [];
    foreach ($existing as $row) {
        $sameEmail = $email !== '' && mb_strtolower(trim((string) ($row['email'] ?? ''))) === $email;
        $samePhone = strlen($phone) >= 7 && normalize_phone($row['phone'] ?? '') === $phone;
        $sameName = normalize_name((string) $row['name']) === $name;
        if ($sameEmail || $samePhone || $sameName) {
            $warnings[] = 'Possible duplicate found: ' . $row['code'] . ' ' . $row['name'] . '.';
        }
    }
    return array_values(array_unique($warnings));
}

function stored_quantity(float $quantity, string $unit): array
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Quantity must be greater than zero.');
    }
    $input = round_qty($quantity);
    if ($unit === 'KG') {
        return ['quantity_kg' => $input, 'input_quantity' => $input, 'weight' => true];
    }
    if ($unit === 'TONNE') {
        return ['quantity_kg' => round_qty($input * 1000), 'input_quantity' => $input, 'weight' => true];
    }
    return ['quantity_kg' => null, 'input_quantity' => $input, 'weight' => false];
}

function party_dates(string $table, string $id): array
{
    $column = $table === 'clients' ? 'client_id' : 'supplier_id';
    $type = $table === 'clients' ? 'CLIENT_PURCHASE' : 'SUPPLIER_SUPPLY';
    $stmt = db()->prepare(
        "SELECT transaction_date, quantity_kg, input_quantity, input_unit, status
         FROM transactions WHERE {$column} = ? AND transaction_type = ? ORDER BY transaction_date"
    );
    $stmt->execute([$id, $type]);
    return $stmt->fetchAll();
}

function metrics_for(array $rows): array
{
    $inactive = (int) setting_number('inactive_after_days');
    $dormant = (int) setting_number('dormant_after_days');
    $recorded = array_values(array_filter($rows, fn ($row) => $row['status'] === 'RECORDED'));
    $weightRows = array_values(array_filter($recorded, fn ($row) => in_array($row['input_unit'], ['KG', 'TONNE'], true)));
    $total = 0.0;
    $month = 0.0;
    $year = 0.0;
    $nowMonth = date('Y-m');
    $nowYear = date('Y');
    foreach ($weightRows as $row) {
        $qty = (float) ($row['quantity_kg'] ?? 0);
        $total += $qty;
        $day = substr((string) $row['transaction_date'], 0, 10);
        if (str_starts_with($day, $nowYear)) {
            $year += $qty;
        }
        if (str_starts_with($day, $nowMonth)) {
            $month += $qty;
        }
    }
    $dates = array_map(fn ($row) => substr((string) $row['transaction_date'], 0, 10), $recorded);
    sort($dates);
    $last = $dates === [] ? null : $dates[count($dates) - 1];
    $count = count($weightRows);
    return [
        'totalQuantityKg' => round($total, 3),
        'transactionCount' => count($recorded),
        'averageQuantityKg' => $count ? round($total / $count, 3) : 0,
        'lastTransactionDate' => $last,
        'quantityThisMonthKg' => round($month, 3),
        'quantityThisYearKg' => round($year, 3),
        'frequency' => classify_frequency($dates),
        'activity' => classify_activity($last, $inactive, $dormant),
    ];
}

function locations_list(): array
{
    return db()->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();
}

function find_party_duplicates(string $kind, array $candidate): array
{
    $table = $kind === 'clients' ? 'clients' : 'suppliers';
    $code = $kind === 'clients' ? 'client_code' : 'supplier_code';
    $rows = db()->query("SELECT name, phone, email, {$code} AS code FROM {$table}")->fetchAll();
    return duplicate_warnings($candidate, $rows);
}
