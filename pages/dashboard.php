<?php
$monthStart = date("Y-m-01");
$clients = (int) db()->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$activeClients = (int) db()->query("SELECT COUNT(*) FROM clients WHERE status = \"ACTIVE\"")->fetchColumn();
$suppliers = (int) db()->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$activeSuppliers = (int) db()->query("SELECT COUNT(*) FROM suppliers WHERE status = \"ACTIVE\"")->fetchColumn();
$products = (int) db()->query("SELECT COUNT(*) FROM products WHERE status = \"ACTIVE\"")->fetchColumn();
$monthStmt = db()->prepare("SELECT transaction_type, quantity_kg, input_unit FROM transactions WHERE status = \"RECORDED\" AND transaction_date >= ?");
$monthStmt->execute([$monthStart]);
$monthRows = $monthStmt->fetchAll();
$purchased = 0.0;
$supplied = 0.0;
foreach ($monthRows as $row) {
    if (!in_array($row["input_unit"], ["KG", "TONNE"], true)) continue;
    $qty = (float) ($row["quantity_kg"] ?? 0);
    if ($row["transaction_type"] === "CLIENT_PURCHASE") $purchased += $qty;
    else $supplied += $qty;
}
$trendRows = db()->query("SELECT transaction_date, transaction_type, quantity_kg, input_unit FROM transactions WHERE status = \"RECORDED\"")->fetchAll();
$months = [];
foreach ($trendRows as $row) {
    $key = substr((string) $row["transaction_date"], 0, 7);
    if (!isset($months[$key])) $months[$key] = ["label" => $key, "purchasedKg" => 0, "suppliedKg" => 0, "transactions" => 0];
    $months[$key]["transactions"]++;
    if (in_array($row["input_unit"], ["KG", "TONNE"], true)) {
        $qty = (float) ($row["quantity_kg"] ?? 0);
        if ($row["transaction_type"] === "CLIENT_PURCHASE") $months[$key]["purchasedKg"] += $qty;
        else $months[$key]["suppliedKg"] += $qty;
    }
}
ksort($months);
$monthList = array_values($months);
$locRows = db()->query("SELECT l.name, COUNT(*) AS count FROM transactions t JOIN clients c ON c.id = t.client_id JOIN locations l ON l.id = c.location_id WHERE t.status = \"RECORDED\" AND t.transaction_type = \"CLIENT_PURCHASE\" GROUP BY l.name ORDER BY count DESC")->fetchAll();
$locations = array_map(fn ($row) => ["label" => $row["name"], "count" => (int) $row["count"]], $locRows);
$recent = db()->query("SELECT t.*, c.name AS client_name, s.name AS supplier_name, p.name AS product_name, u.full_name FROM transactions t JOIN products p ON p.id = t.product_id JOIN users u ON u.id = t.recorded_by_id LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN suppliers s ON s.id = t.supplier_id ORDER BY t.created_at DESC LIMIT 8")->fetchAll();
$inactiveAfter = (int) setting_number("inactive_after_days");
$cutoff = date("Y-m-d", time() - $inactiveAfter * 86400);
$alertClients = db()->query("SELECT c.id, c.name, c.client_code, (SELECT MAX(transaction_date) FROM transactions t WHERE t.client_id = c.id AND t.status = \"RECORDED\" AND t.transaction_type = \"CLIENT_PURCHASE\") AS last_purchase FROM clients c WHERE c.status = \"ACTIVE\"")->fetchAll();
$inactive = [];
foreach ($alertClients as $client) {
    if ($client["last_purchase"] === null || $client["last_purchase"] < $cutoff) $inactive[] = $client;
    if (count($inactive) >= 5) break;
}
function chart_canvas(string $kind, string $key, string $color, array $rows): string {
    $payload = htmlspecialchars(json_encode(["kind" => $kind, "key" => $key, "color" => $color, "rows" => $rows], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8");
    return "<div class=\"h-[240px] w-full\"><canvas class=\"chart\" data-chart=\"{$payload}\"></canvas></div>";
}
function dash_card(string $tone, string $icon, string $label, string $value, string $detail = "", bool $featured = false): string {
    $tones = [
        "emerald" => ["border-emerald-200/80 bg-gradient-to-br from-emerald-50 to-[#fffcf8]", "bg-emerald-600 text-white", "text-emerald-800", "text-emerald-950", "text-emerald-700/80"],
        "teal" => ["border-teal-200/80 bg-gradient-to-br from-teal-50 to-[#fffcf8]", "bg-teal-600 text-white", "text-teal-800", "text-teal-950", "text-teal-700/80"],
        "lime" => ["border-lime-200/80 bg-gradient-to-br from-lime-50 to-[#fffcf8]", "bg-lime-600 text-white", "text-lime-800", "text-lime-950", "text-lime-700/80"],
        "sky" => ["border-sky-200/80 bg-gradient-to-br from-sky-50 to-[#fffcf8]", "bg-sky-600 text-white", "text-sky-800", "text-sky-950", "text-sky-700/80"],
        "amber" => ["border-amber-200/80 bg-gradient-to-br from-amber-50 to-[#fffcf8]", "bg-amber-500 text-white", "text-amber-900", "text-amber-950", "text-amber-800/80"],
        "stone" => ["border-stone-200 bg-gradient-to-br from-stone-100 to-[#fffcf8]", "bg-stone-600 text-white", "text-stone-700", "text-stone-900", "text-stone-600"],
    ];
    [$panel, $iconBg, $labelClass, $valueClass, $detailClass] = $tones[$tone];
    $span = $featured ? " sm:col-span-2" : "";
    $size = $featured ? "text-5xl" : "text-3xl";
    $detailHtml = $detail !== "" ? "<p class=\"mt-2 text-sm {$detailClass}\">{$detail}</p>" : "";
    return "<article class=\"rounded-xl border p-4 {$panel}{$span}\"><div class=\"flex items-start justify-between gap-3\"><p class=\"text-xs tracking-[0.12em] uppercase {$labelClass}\">{$label}</p><span class=\"grid h-9 w-9 shrink-0 place-items-center rounded-lg {$iconBg}\">" . icon($icon) . "</span></div><p class=\"mt-3 font-medium tracking-tight {$valueClass} {$size}\">{$value}</p>{$detailHtml}</article>";
}
layout_start("");
echo "<section><h1 class=\"text-4xl font-medium tracking-tight\">Dashboard</h1>";
echo "<div class=\"mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4\">";
echo dash_card("emerald", "check", "Purchased this month", e(format_kg($purchased)), "", true);
echo dash_card("teal", "up", "Supplied this month", e(format_kg($supplied)));
echo dash_card("lime", "receipt", "Transactions this month", e((string) count($monthRows)));
echo dash_card("sky", "users", "Total clients", e((string) $clients), e($activeClients . " active"));
echo dash_card("amber", "truck", "Suppliers", e((string) $suppliers), e($activeSuppliers . " active"));
echo dash_card("stone", "package", "Products", e((string) $products));
echo "</div><div class=\"mt-6 grid gap-4 lg:grid-cols-2\">";
foreach ([
    ["Quantity purchased over time", "line", "purchasedKg", "#059669", $monthList],
    ["Quantity supplied over time", "line", "suppliedKg", "#0d9488", $monthList],
    ["Transactions by month", "bar", "transactions", "#65a30d", $monthList],
    ["Clients by location", "bar", "count", "#0284c7", $locations],
] as $chart) {
    echo "<article class=\"overflow-hidden rounded-xl border border-slate-200 bg-[#fffcf8] p-4\"><h2 class=\"mb-3 text-sm font-medium text-slate-700\">" . e($chart[0]) . "</h2>" . chart_canvas($chart[1], $chart[2], $chart[3], $chart[4]) . "</article>";
}
echo "</div><div class=\"mt-6\">";
$body = "";
foreach ($recent as $index => $row) {
    $party = $row["client_name"] ?? $row["supplier_name"] ?? "—";
    $body .= "<tr><td class=\"text-slate-500\">" . ($index + 1) . "</td><td>" . entity_cell($party, $row["transaction_code"]) . "</td><td class=\"font-medium text-slate-700\">" . e($row["transaction_code"]) . "</td><td>" . status_text($row["transaction_type"]) . "</td><td>" . e($row["product_name"]) . "</td><td>" . qty_badge($row["quantity_kg"], $row["input_unit"], $row["input_quantity"]) . "</td><td>" . e(format_date($row["transaction_date"])) . "</td><td>" . e($row["full_name"]) . "</td></tr>";
}
if ($body === "") $body = "<tr><td colspan=\"8\">No transactions have been recorded yet.</td></tr>";
echo data_panel(data_table(["#", "Party", "Code", "Type", "Product", "Quantity", "Date", "Recorded by"], $body), "Recent transactions");
echo "</div><aside class=\"mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm\"><h2 class=\"font-medium\">Alerts</h2><p class=\"mt-1 text-slate-700\">Activity is a system classification based on transaction history.</p><ul class=\"mt-2 list-disc pl-5\">";
foreach ($inactive as $item) {
    echo "<li>" . e($item["name"]) . " (" . e($item["client_code"]) . ") has no recent purchase.</li>";
}
echo "</ul></aside></section>";
layout_end();
