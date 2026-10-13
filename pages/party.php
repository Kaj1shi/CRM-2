<?php
$isClient = $kind === "clients";
$codeCol = $isClient ? "client_code" : "supplier_code";
$fk = $isClient ? "client_id" : "supplier_id";
$permStatus = $isClient ? "clients.deactivate" : "suppliers.deactivate";
$stmt = db()->prepare("SELECT p.*, l.name AS location_name FROM {$kind} p LEFT JOIN locations l ON l.id = p.location_id WHERE p.id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    layout_start($kind);
    echo "<p>Record was not found.</p>";
    layout_end();
    return;
}
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    csrf_check();
    if (!can($permStatus)) {
        http_response_code(403);
        exit("You do not have permission to change this status.");
    }
    $next = ($_POST["status"] ?? "") === "ACTIVE" ? "ACTIVE" : "INACTIVE";
    db()->prepare("UPDATE {$kind} SET status = ? WHERE id = ?")->execute([$next, $id]);
    audit(current_user()["id"], $next === "ACTIVE" ? "REACTIVATE" : "DEACTIVATE", $isClient ? "Client" : "Supplier", $id, ($isClient ? "Client" : "Supplier") . " status set to {$next}.", ["status" => $row["status"]], ["status" => $next]);
    redirect($kind . "/" . $id);
}
$metrics = metrics_for(party_dates($kind, $id));
$tx = db()->prepare("SELECT t.transaction_date, t.transaction_code, t.quantity_kg, t.input_quantity, t.input_unit, t.status, p.name AS product_name FROM transactions t JOIN products p ON p.id = t.product_id WHERE t.{$fk} = ? ORDER BY t.transaction_date DESC");
$tx->execute([$id]);
$history = $tx->fetchAll();
layout_start($kind . "/" . $id);
echo "<section class=\"space-y-4\">";
echo page_header($row["name"], "<a class=\"text-sm text-blue-700\" href=\"" . e(url($kind)) . "\">Back to list</a>");
echo "<p class=\"text-sm text-slate-600\">" . e($row[$codeCol]) . " · " . e($row["location_name"] ?? "No location") . " · " . e($row["distance_km"] === null ? "—" : (string) $row["distance_km"]) . " km</p>";
echo "<div class=\"flex flex-wrap gap-2\">" . status_text($row["status"]) . status_text($metrics["activity"]) . status_text($metrics["frequency"]) . "</div>";
echo notice("Activity is a system classification based on transaction history. Stored status stays separate from this activity label.");
echo "<div class=\"grid gap-3 sm:grid-cols-3\">";
echo "<article class=\"rounded border bg-white p-3\"><p class=\"text-xs text-slate-500\">Total quantity</p><p class=\"text-xl\">" . e(format_kg($metrics["totalQuantityKg"])) . "</p></article>";
echo "<article class=\"rounded border bg-white p-3\"><p class=\"text-xs text-slate-500\">Transactions</p><p class=\"text-xl\">" . e((string) $metrics["transactionCount"]) . "</p></article>";
echo "<article class=\"rounded border bg-white p-3\"><p class=\"text-xs text-slate-500\">This year</p><p class=\"text-xl\">" . e(format_kg($metrics["quantityThisYearKg"])) . "</p></article>";
echo "</div>";
echo "<p class=\"text-sm\">Average " . e(format_kg($metrics["averageQuantityKg"])) . " · This month " . e(format_kg($metrics["quantityThisMonthKg"])) . " · Last " . e(format_date($metrics["lastTransactionDate"])) . "</p>";
if (can($permStatus) && $row["status"] !== "INACTIVE") {
    echo "<form method=\"post\" class=\"inline\" onsubmit=\"return confirm(" . json_encode("Deactivate {$row["name"]}? The record stays in history.") . ")\">" . csrf_field() . "<input type=\"hidden\" name=\"status\" value=\"INACTIVE\"><button class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\">Deactivate</button></form> ";
}
if (can($permStatus) && $row["status"] !== "ACTIVE") {
    echo "<form method=\"post\" class=\"inline\">" . csrf_field() . "<input type=\"hidden\" name=\"status\" value=\"ACTIVE\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Reactivate</button></form>";
}
$body = "";
foreach ($history as $item) {
    $body .= "<tr><td>" . e(format_date($item["transaction_date"])) . "</td><td class=\"font-medium\">" . e($item["transaction_code"]) . "</td><td>" . e($item["product_name"]) . "</td><td>" . qty_badge($item["quantity_kg"], $item["input_unit"], $item["input_quantity"]) . "</td><td>" . status_text($item["status"]) . "</td></tr>";
}
if ($body === "") $body = "<tr><td colspan=\"5\">No transactions have been recorded yet.</td></tr>";
echo "<div class=\"mt-4\">" . data_panel(data_table(["Date", "Code", "Product", "Quantity", "Status"], $body), "Transaction history") . "</div></section>";
layout_end();
