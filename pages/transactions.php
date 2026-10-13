<?php
$error = "";
$warning = "";
$old = $_POST;
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["form"] ?? "") === "create") {
    csrf_check();
    if (!can("transactions.create")) {
        $error = "You do not have permission to record a transaction.";
    } else {
        $type = (string) ($_POST["transaction_type"] ?? "");
        $clientId = trim((string) ($_POST["client_id"] ?? ""));
        $supplierId = trim((string) ($_POST["supplier_id"] ?? ""));
        $productId = trim((string) ($_POST["product_id"] ?? ""));
        $quantity = (float) ($_POST["quantity"] ?? 0);
        $unit = (string) ($_POST["unit"] ?? "KG");
        $date = (string) ($_POST["transaction_date"] ?? "");
        if (!in_array($type, ["CLIENT_PURCHASE", "SUPPLIER_SUPPLY"], true)) $error = "Choose a transaction type.";
        elseif (!in_array($unit, ["KG", "TONNE", "PIECE", "OTHER"], true)) $error = "Choose a unit.";
        elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) $error = "Choose a date.";
        elseif ($type === "CLIENT_PURCHASE" && $clientId === "") $error = "A client is required for a purchase.";
        elseif ($type === "SUPPLIER_SUPPLY" && $supplierId === "") $error = "A supplier is required for a supply.";
        else {
            $product = db()->prepare("SELECT * FROM products WHERE id = ?");
            $product->execute([$productId]);
            $product = $product->fetch();
            if (!$product || $product["status"] !== "ACTIVE") {
                $error = "Select an active product.";
            } else {
                try {
                    $stored = stored_quantity($quantity, $unit);
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                    $stored = null;
                }
                if ($stored) {
                    $limit = setting_number("max_quantity_kg_warning");
                    if ($stored["quantity_kg"] !== null && $stored["quantity_kg"] > $limit && empty($_POST["acknowledge_large"])) {
                        $warning = "This quantity is unusually large. Confirm it before saving. Quantities above {$limit} kg need confirmation.";
                    } else {
                        $pdo = db();
                        $pdo->beginTransaction();
                        $id = uuid();
                        $code = next_code($pdo, "transaction");
                        $pdo->prepare("INSERT INTO transactions (id, transaction_code, transaction_type, client_id, supplier_id, product_id, quantity_kg, input_quantity, input_unit, transaction_date, reference_number, notes, recorded_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                            ->execute([
                                $id, $code, $type,
                                $type === "CLIENT_PURCHASE" ? $clientId : null,
                                $type === "SUPPLIER_SUPPLY" ? $supplierId : null,
                                $productId,
                                $stored["quantity_kg"],
                                $stored["input_quantity"],
                                $unit,
                                $date,
                                trim((string) ($_POST["reference_number"] ?? "")) ?: null,
                                trim((string) ($_POST["notes"] ?? "")) ?: null,
                                current_user()["id"],
                            ]);
                        $pdo->commit();
                        audit(current_user()["id"], "CREATE", "Transaction", $id, "Transaction recorded.", null, ["transactionCode" => $code, "transactionType" => $type, "quantityKg" => $stored["quantity_kg"]]);
                        redirect("transactions");
                    }
                }
            }
        }
    }
}
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["form"] ?? "") === "reverse") {
    csrf_check();
    if (!can("transactions.reverse")) exit("You do not have permission to reverse a transaction.");
    $id = (string) ($_POST["id"] ?? "");
    $stmt = db()->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) $error = "Transaction was not found.";
    elseif ($row["status"] === "REVERSED") $error = "This transaction has already been reversed.";
    else {
        db()->prepare("UPDATE transactions SET status = \"REVERSED\" WHERE id = ?")->execute([$id]);
        audit(current_user()["id"], "UPDATE", "Transaction", $id, "Transaction reversed and excluded from totals.", ["status" => $row["status"]], ["status" => "REVERSED"]);
        redirect("transactions");
    }
}
$type = (string) ($_GET["type"] ?? "");
$where = "";
$params = [];
if (in_array($type, ["CLIENT_PURCHASE", "SUPPLIER_SUPPLY"], true)) {
    $where = "WHERE t.transaction_type = ?";
    $params[] = $type;
}
$stmt = db()->prepare("SELECT t.*, c.name AS client_name, s.name AS supplier_name, p.name AS product_name, u.full_name FROM transactions t JOIN products p ON p.id = t.product_id JOIN users u ON u.id = t.recorded_by_id LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN suppliers s ON s.id = t.supplier_id {$where} ORDER BY t.transaction_date DESC, t.created_at DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$clients = db()->query("SELECT id, name FROM clients WHERE status = \"ACTIVE\" ORDER BY name")->fetchAll();
$suppliers = db()->query("SELECT id, name FROM suppliers WHERE status = \"ACTIVE\" ORDER BY name")->fetchAll();
$products = db()->query("SELECT id, name FROM products WHERE status = \"ACTIVE\" ORDER BY name")->fetchAll();
$open = isset($_GET["create"]) || $error !== "" || $warning !== "";
layout_start("transactions");
echo "<section>";
echo page_header("Transactions", can("transactions.create") ? primary_link(url("transactions?create=1"), "Record transaction") : "");
echo "<form method=\"get\"><select aria-label=\"Transaction type\" name=\"type\" class=\"" . input_class() . " mb-3 max-w-xs\" onchange=\"this.form.submit()\"><option value=\"\">All types</option>";
foreach (["CLIENT_PURCHASE" => "Client purchase", "SUPPLIER_SUPPLY" => "Supplier supply"] as $value => $label) {
    $selected = $type === $value ? " selected" : "";
    echo "<option value=\"{$value}\"{$selected}>{$label}</option>";
}
echo "</select></form>";
echo error_text($error);
if (!$rows) echo notice("No transactions have been recorded yet.");
else {
    $body = "";
    foreach ($rows as $row) {
        $party = $row["client_name"] ?? $row["supplier_name"] ?? "—";
        $body .= "<tr><td>" . e(format_date($row["transaction_date"])) . "</td><td class=\"font-medium text-slate-700\">" . e($row["transaction_code"]) . "</td><td>" . entity_cell($party) . "</td><td>" . status_text($row["transaction_type"]) . "</td><td>" . e($row["product_name"]) . "</td><td>" . qty_badge($row["quantity_kg"], $row["input_unit"], $row["input_quantity"]) . "</td><td>" . status_text($row["status"]) . "</td><td>" . e($row["full_name"]) . "</td><td>";
        if (can("transactions.reverse") && $row["status"] === "RECORDED") {
            $body .= "<form method=\"post\" onsubmit=\"return confirm(" . htmlspecialchars(json_encode("Reverse {$row["transaction_code"]}? It stays in history and leaves the totals."), ENT_QUOTES) . ")\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"reverse\"><input type=\"hidden\" name=\"id\" value=\"" . e($row["id"]) . "\"><button class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\">Reverse</button></form>";
        } else $body .= "—";
        $body .= "</td></tr>";
    }
    echo data_panel(data_table(["Date", "Code", "Party", "Type", "Product", "Quantity", "Status", "Recorded by", "Correction"], $body));
}
if ($open && can("transactions.create")) {
    $cls = input_class();
    $clientOptions = "<option value=\"\">Select client</option>";
    foreach ($clients as $item) $clientOptions .= "<option value=\"" . e($item["id"]) . "\">" . e($item["name"]) . "</option>";
    $supplierOptions = "<option value=\"\">Select supplier</option>";
    foreach ($suppliers as $item) $supplierOptions .= "<option value=\"" . e($item["id"]) . "\">" . e($item["name"]) . "</option>";
    $productOptions = "<option value=\"\">Select product</option>";
    foreach ($products as $item) $productOptions .= "<option value=\"" . e($item["id"]) . "\">" . e($item["name"]) . "</option>";
    $today = date("Y-m-d");
    $form = "<form method=\"post\" class=\"grid gap-3 md:grid-cols-2\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"create\">";
    $form .= field("Type", "<select id=\"transaction-type\" class=\"{$cls}\" name=\"transaction_type\"><option value=\"CLIENT_PURCHASE\">Client purchase</option><option value=\"SUPPLIER_SUPPLY\"" . ((($old["transaction_type"] ?? "") === "SUPPLIER_SUPPLY") ? " selected" : "") . ">Supplier supply</option></select>");
    $form .= "<div data-party=\"client\">" . field("Client", "<select class=\"{$cls}\" name=\"client_id\">{$clientOptions}</select>") . "</div>";
    $form .= "<div data-party=\"supplier\" hidden>" . field("Supplier", "<select class=\"{$cls}\" name=\"supplier_id\">{$supplierOptions}</select>") . "</div>";
    $form .= field("Product", "<select class=\"{$cls}\" name=\"product_id\">{$productOptions}</select>");
    $form .= field("Quantity", "<input class=\"{$cls}\" type=\"number\" min=\"0.001\" step=\"0.001\" name=\"quantity\" required value=\"" . e((string) ($old["quantity"] ?? "1")) . "\">");
    $form .= field("Unit", "<select class=\"{$cls}\" name=\"unit\"><option value=\"KG\">Kilogram</option><option value=\"TONNE\">Tonne</option><option value=\"PIECE\">Piece</option><option value=\"OTHER\">Other</option></select>");
    $form .= field("Date", "<input class=\"{$cls}\" type=\"date\" name=\"transaction_date\" required value=\"" . e($old["transaction_date"] ?? $today) . "\">");
    $form .= field("Reference", "<input class=\"{$cls}\" name=\"reference_number\" value=\"" . e($old["reference_number"] ?? "") . "\">");
    $form .= field("Notes", "<textarea class=\"{$cls}\" name=\"notes\">" . e($old["notes"] ?? "") . "</textarea>");
    $form .= "<p class=\"text-sm text-slate-500 md:col-span-2\">1 tonne = 1,000 kg</p>";
    if ($warning) $form .= "<div class=\"md:col-span-2\">" . notice(e($warning) . " <label class=\"ml-2\"><input type=\"checkbox\" name=\"acknowledge_large\" value=\"1\"> Confirm this quantity</label>") . "</div>";
    $form .= "<div class=\"md:col-span-2\">" . error_text($warning ? "" : $error) . "</div>";
    $form .= "<div class=\"flex gap-2 md:col-span-2\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Save</button><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . e(url("transactions")) . "\">Cancel</a></div></form>";
    echo modal("Record transaction", $form, url("transactions"));
}
echo "</section>";
layout_end();
