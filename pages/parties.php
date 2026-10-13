<?php
$isClient = $kind === "clients";
$title = $isClient ? "Clients" : "Suppliers";
$codeCol = $isClient ? "client_code" : "supplier_code";
$typeCol = $isClient ? "client_type" : "supplier_type";
$permCreate = $isClient ? "clients.create" : "suppliers.create";
$types = $isClient
    ? ["INDIVIDUAL", "BUSINESS", "DISTRIBUTOR", "WHOLESALER", "RETAILER", "OTHER"]
    : ["INDIVIDUAL", "BUSINESS", "FARMER", "DISTRIBUTOR", "MANUFACTURER", "OTHER"];
$error = "";
$warnings = [];
$old = $_POST;
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["form"] ?? "") === "create") {
    csrf_check();
    if (!can($permCreate)) {
        $error = "You do not have permission to add this record.";
    } else {
        $name = trim((string) ($_POST["name"] ?? ""));
        $email = strtolower(trim((string) ($_POST["email"] ?? "")));
        $phone = trim((string) ($_POST["phone"] ?? ""));
        $locationId = trim((string) ($_POST["location_id"] ?? ""));
        $newLocation = trim((string) ($_POST["new_location"] ?? ""));
        $distance = trim((string) ($_POST["distance_km"] ?? ""));
        $partyType = (string) ($_POST["party_type"] ?? "BUSINESS");
        if (!in_array($partyType, $types, true)) $partyType = "BUSINESS";
        if ($name === "") {
            $error = "Name is required.";
        } else {
            if ($locationId === "" && $newLocation !== "") {
                $locationId = uuid();
                db()->prepare("INSERT INTO locations (id, name) VALUES (?, ?)")->execute([$locationId, $newLocation]);
            }
            if ($locationId === "") $locationId = null;
            $warnings = find_party_duplicates($kind, ["name" => $name, "phone" => $phone, "email" => $email]);
            if ($warnings && empty($_POST["confirm_duplicate"])) {
                $error = $warnings[0];
            } else {
                $id = uuid();
                $pdo = db();
                $pdo->beginTransaction();
                $code = next_code($pdo, $isClient ? "client" : "supplier");
                $sql = "INSERT INTO {$kind} (id, {$codeCol}, name, contact_person, phone, email, location_id, distance_km, {$typeCol}, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $id, $code, $name,
                    trim((string) ($_POST["contact_person"] ?? "")) ?: null,
                    $phone ?: null,
                    $email ?: null,
                    $locationId,
                    $distance === "" ? null : $distance,
                    $partyType,
                    trim((string) ($_POST["notes"] ?? "")) ?: null,
                ]);
                $pdo->commit();
                audit(current_user()["id"], "CREATE", $isClient ? "Client" : "Supplier", $id, ($isClient ? "Client" : "Supplier") . " created.", null, ["code" => $code, "name" => $name]);
                redirect($kind);
            }
        }
    }
}
$search = trim((string) ($_GET["search"] ?? ""));
$status = (string) ($_GET["status"] ?? "");
$page = max(1, (int) ($_GET["page"] ?? 1));
$pageSize = 25;
$where = [];
$params = [];
if ($search !== "") {
    $where[] = "(name LIKE ? OR {$codeCol} LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like);
}
if ($status !== "") {
    $where[] = "status = ?";
    $params[] = $status;
}
$sqlWhere = $where ? "WHERE " . implode(" AND ", $where) : "";
$countStmt = db()->prepare("SELECT COUNT(*) FROM {$kind} {$sqlWhere}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pageCount = max(1, (int) ceil($total / $pageSize));
$qualified = $sqlWhere;
$qualified = str_replace(["(name ", " {$codeCol}", " phone ", " email ", "status ="], ["(p.name ", " p.{$codeCol}", " p.phone ", " p.email ", "p.status ="], $qualified);
$listStmt = db()->prepare("SELECT p.*, l.name AS location_name FROM {$kind} p LEFT JOIN locations l ON l.id = p.location_id {$qualified} ORDER BY p.name LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize));
$listStmt->execute($params);
$rows = $listStmt->fetchAll();
$open = isset($_GET["create"]) || $error !== "";
$locations = locations_list();
layout_start($kind);
echo "<section>";
echo page_header($title, can($permCreate) ? primary_link(url($kind . "?create=1"), $isClient ? "Add client" : "Add supplier") : "");
echo "<form method=\"get\" class=\"mb-3 flex flex-wrap gap-2\">";
echo "<input aria-label=\"Search {$title}\" name=\"search\" class=\"" . input_class() . " max-w-sm\" placeholder=\"Search name, code, phone, or email\" value=\"" . e($search) . "\">";
echo "<select aria-label=\"Status\" name=\"status\" class=\"" . input_class() . " max-w-40\" onchange=\"this.form.submit()\"><option value=\"\">All statuses</option>";
foreach (array_filter(["ACTIVE", "INACTIVE", $isClient ? "DORMANT" : ""]) as $option) {
    $selected = $status === $option ? " selected" : "";
    echo "<option value=\"" . e($option) . "\"{$selected}>" . e(ucfirst(strtolower($option))) . "</option>";
}
echo "</select></form>";
if (!$rows) {
    $empty = $isClient ? "No clients have been registered yet." : "No suppliers have been registered yet.";
    echo notice(e($empty) . (can($permCreate) ? " Use the button above to add the first record." : ""));
} else {
    $body = "";
    foreach ($rows as $row) {
        $metrics = metrics_for(party_dates($kind, $row["id"]));
        $body .= "<tr><td><a class=\"font-medium text-blue-700\" href=\"" . e(url($kind . "/" . $row["id"])) . "\">" . e($row[$codeCol]) . "</a></td>";
        $body .= "<td>" . entity_cell($row["name"], $row["location_name"] ?? null) . "</td><td>" . e($row["location_name"] ?? "—") . "</td>";
        $body .= "<td>" . status_text($row["status"]) . "</td><td>" . status_text($metrics["activity"]) . "</td><td>" . status_text($metrics["frequency"]) . "</td><td>" . e(format_date($metrics["lastTransactionDate"])) . "</td></tr>";
    }
    echo data_panel(data_table(["Code", "Name", "Location", "Status", "Activity", "Frequency", "Last transaction"], $body));
}
if ($pageCount > 1) {
    $prev = max(1, $page - 1);
    $next = min($pageCount, $page + 1);
    $qs = fn ($n) => e(url($kind) . "?page={$n}&search=" . urlencode($search) . "&status=" . urlencode($status));
    echo "<div class=\"mt-3 flex gap-2\"><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . $qs($prev) . "\">Previous</a><span class=\"self-center text-sm\">Page {$page} of {$pageCount}</span><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . $qs($next) . "\">Next</a></div>";
}
if ($open && can($permCreate)) {
    $typeOptions = "";
    foreach ($types as $type) {
        $selected = (($old["party_type"] ?? "BUSINESS") === $type) ? " selected" : "";
        $typeOptions .= "<option value=\"" . e($type) . "\"{$selected}>" . e($type) . "</option>";
    }
    $locOptions = "<option value=\"\">Select a location</option>";
    foreach ($locations as $location) {
        $selected = (($old["location_id"] ?? "") === $location["id"]) ? " selected" : "";
        $locOptions .= "<option value=\"" . e($location["id"]) . "\"{$selected}>" . e($location["name"]) . "</option>";
    }
    $cls = input_class();
    $form = "<form method=\"post\" class=\"grid gap-3 md:grid-cols-2\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"create\">";
    $form .= field("Name", "<input class=\"{$cls}\" name=\"name\" required value=\"" . e($old["name"] ?? "") . "\">");
    $form .= field("Contact person", "<input class=\"{$cls}\" name=\"contact_person\" value=\"" . e($old["contact_person"] ?? "") . "\">");
    $form .= field("Phone", "<input class=\"{$cls}\" name=\"phone\" value=\"" . e($old["phone"] ?? "") . "\">");
    $form .= field("Email", "<input class=\"{$cls}\" type=\"email\" name=\"email\" value=\"" . e($old["email"] ?? "") . "\">");
    $form .= field("Location", "<select class=\"{$cls}\" name=\"location_id\">{$locOptions}</select>");
    $form .= field("New location", "<input class=\"{$cls}\" name=\"new_location\" value=\"" . e($old["new_location"] ?? "") . "\" placeholder=\"Used when no location is selected\">");
    $form .= field("Distance (km)", "<input class=\"{$cls}\" type=\"number\" min=\"0\" step=\"0.1\" name=\"distance_km\" value=\"" . e($old["distance_km"] ?? "") . "\">");
    $form .= field($isClient ? "Client type" : "Supplier type", "<select class=\"{$cls}\" name=\"party_type\">{$typeOptions}</select>");
    $form .= field("Notes", "<textarea class=\"{$cls}\" name=\"notes\">" . e($old["notes"] ?? "") . "</textarea>");
    $form .= "<div class=\"md:col-span-2\">" . error_text($error) . "</div>";
    if ($warnings) {
        $form .= "<div class=\"md:col-span-2\">" . notice(e($warnings[0]) . " <label class=\"ml-2\"><input type=\"checkbox\" name=\"confirm_duplicate\" value=\"1\"> Save anyway</label>") . "</div>";
    }
    $form .= "<div class=\"flex gap-2 md:col-span-2\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white hover:bg-emerald-700\">Save</button><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . e(url($kind)) . "\">Cancel</a></div></form>";
    echo modal($isClient ? "Add client" : "Add supplier", $form, url($kind));
}
echo "</section>";
layout_end();
