<?php
$kind = (string) ($_GET["kind"] ?? "clients");
$from = (string) ($_GET["from"] ?? "");
$to = (string) ($_GET["to"] ?? "");
$format = (string) ($_GET["format"] ?? "");
$operational = ["clients", "suppliers", "transactions"];
$management = ["quantities", "purchasing"];
$allowed = $operational;
if (can("reports.management")) $allowed = array_merge($allowed, $management);
if (!in_array($kind, $allowed, true)) $kind = "clients";

function report_rows(string $kind, string $from, string $to): array {
    if ($kind === "clients") {
        $sql = "SELECT c.client_code AS code, c.name, COALESCE(l.name, \"\") AS location, c.status, c.id FROM clients c LEFT JOIN locations l ON l.id = c.location_id WHERE 1=1";
        $params = [];
        if ($from !== "") { $sql .= " AND c.created_at >= ?"; $params[] = $from . " 00:00:00"; }
        if ($to !== "") { $sql .= " AND c.created_at <= ?"; $params[] = $to . " 23:59:59"; }
        $sql .= " ORDER BY c.name";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt as $row) {
            $metrics = metrics_for(party_dates("clients", $row["id"]));
            $rows[] = ["code" => $row["code"], "name" => $row["name"], "location" => $row["location"], "status" => $row["status"], "frequency" => $metrics["frequency"], "totalKg" => $metrics["totalQuantityKg"]];
        }
        return $rows;
    }
    if ($kind === "suppliers") {
        $stmt = db()->query("SELECT s.supplier_code AS code, s.name, COALESCE(l.name, \"\") AS location, s.status, s.id FROM suppliers s LEFT JOIN locations l ON l.id = s.location_id ORDER BY s.name");
        $rows = [];
        foreach ($stmt as $row) {
            $metrics = metrics_for(party_dates("suppliers", $row["id"]));
            $rows[] = ["code" => $row["code"], "name" => $row["name"], "location" => $row["location"], "status" => $row["status"], "frequency" => $metrics["frequency"], "totalKg" => $metrics["totalQuantityKg"]];
        }
        return $rows;
    }
    if ($kind === "purchasing") {
        $clients = db()->query("SELECT id FROM clients")->fetchAll();
        $counts = ["MULTIPLE_PER_WEEK" => 0, "WEEKLY" => 0, "BIWEEKLY" => 0, "MONTHLY" => 0, "IRREGULAR" => 0, "INSUFFICIENT_DATA" => 0];
        foreach ($clients as $client) {
            $dates = [];
            $sql = "SELECT transaction_date FROM transactions WHERE client_id = ? AND status = \"RECORDED\" AND transaction_type = \"CLIENT_PURCHASE\"";
            $params = [$client["id"]];
            if ($from !== "") { $sql .= " AND transaction_date >= ?"; $params[] = $from; }
            if ($to !== "") { $sql .= " AND transaction_date <= ?"; $params[] = $to; }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt as $item) $dates[] = $item["transaction_date"];
            $counts[classify_frequency($dates)]++;
        }
        $rows = [];
        foreach ($counts as $frequency => $clientsCount) $rows[] = ["frequency" => $frequency, "clients" => $clientsCount];
        return $rows;
    }
    $sql = "SELECT t.transaction_code AS code, t.transaction_date AS date, t.transaction_type AS type, COALESCE(c.name, s.name, \"\") AS party, p.name AS product, t.quantity_kg AS quantityKg, t.input_unit AS unit, t.input_quantity AS inputQuantity, t.status, u.full_name AS recordedBy FROM transactions t JOIN products p ON p.id = t.product_id JOIN users u ON u.id = t.recorded_by_id LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN suppliers s ON s.id = t.supplier_id WHERE 1=1";
    $params = [];
    if ($from !== "") { $sql .= " AND t.transaction_date >= ?"; $params[] = $from; }
    if ($to !== "") { $sql .= " AND t.transaction_date <= ?"; $params[] = $to; }
    $sql .= " ORDER BY t.transaction_date DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function csv_body(array $rows): string {
    $handle = fopen("php://temp", "r+");
    if (!$rows) fputcsv($handle, ["message"]);
    else {
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($handle, $row);
    }
    rewind($handle);
    $body = stream_get_contents($handle);
    fclose($handle);
    return $body === false ? "" : $body;
}

function xls_body(array $rows): string {
    $xml = "<?xml version=\"1.0\"?><Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"><Worksheet ss:Name=\"Report\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"><Table>";
    if ($rows) {
        $xml .= "<Row>";
        foreach (array_keys($rows[0]) as $key) $xml .= "<Cell><Data ss:Type=\"String\">" . htmlspecialchars((string) $key) . "</Data></Cell>";
        $xml .= "</Row>";
        foreach ($rows as $row) {
            $xml .= "<Row>";
            foreach ($row as $value) $xml .= "<Cell><Data ss:Type=\"String\">" . htmlspecialchars((string) $value) . "</Data></Cell>";
            $xml .= "</Row>";
        }
    }
    return $xml . "</Table></Worksheet></Workbook>";
}

function pdf_body(string $title, array $rows): string {
    $lines = [$title];
    if (!$rows) $lines[] = "No rows.";
    else {
        $lines[] = implode(" | ", array_keys($rows[0]));
        foreach (array_slice($rows, 0, 40) as $row) $lines[] = implode(" | ", array_map(fn ($value) => (string) $value, $row));
    }
    $content = "BT /F1 11 Tf 40 800 Td 14 TL\n";
    foreach ($lines as $line) {
        $safe = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], substr($line, 0, 110));
        $content .= "(" . $safe . ") '\n";
    }
    $content .= "ET";
    $objects = [];
    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $objects[] = "2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj\n";
    $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n";
    $objects[] = "4 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj\n";
    $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }
    $start = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF";
    return $pdf;
}

$rows = report_rows($kind, $from, $to);
if (in_array($format, ["csv", "xls", "pdf"], true)) {
    if (!can("reports.export")) {
        http_response_code(403);
        exit("You do not have permission to export reports.");
    }
    audit(current_user()["id"], "REPORT_EXPORT", "Report", $kind, "Exported {$kind} report as {$format}.");
    $filename = $kind . "-report." . ($format === "xls" ? "xls" : $format);
    if ($format === "csv") { header("Content-Type: text/csv"); $body = csv_body($rows); }
    elseif ($format === "xls") { header("Content-Type: application/vnd.ms-excel"); $body = xls_body($rows); }
    else { header("Content-Type: application/pdf"); $body = pdf_body(ucfirst($kind) . " report", $rows); }
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo $body;
    exit;
}
$labels = ["code" => "Code", "name" => "Name", "location" => "Location", "status" => "Status", "frequency" => "Frequency", "totalKg" => "Total kg", "date" => "Date", "type" => "Type", "party" => "Party", "product" => "Product", "quantityKg" => "Quantity kg", "unit" => "Unit", "inputQuantity" => "Input quantity", "recordedBy" => "Recorded by", "clients" => "Clients"];
$statusKeys = ["status", "frequency", "type", "activity", "category", "role"];
$entityKeys = ["name", "party", "product"];
layout_start("reports");
echo "<section>";
echo page_header("Reports");
echo "<form method=\"get\" class=\"mb-4 grid gap-3 md:grid-cols-4\">";
echo field("Report", "<select class=\"" . input_class() . "\" name=\"kind\" onchange=\"this.form.submit()\">" . implode("", array_map(fn ($item) => "<option value=\"{$item}\"" . ($item === $kind ? " selected" : "") . ">" . e($item) . "</option>", $allowed)) . "</select>");
echo field("From", "<input class=\"" . input_class() . "\" type=\"date\" name=\"from\" value=\"" . e($from) . "\">");
echo field("To", "<input class=\"" . input_class() . "\" type=\"date\" name=\"to\" value=\"" . e($to) . "\">");
echo "<div class=\"flex items-end\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Apply</button></div></form>";
if (can("reports.export")) {
    $base = e(url("reports") . "?kind=" . urlencode($kind) . "&from=" . urlencode($from) . "&to=" . urlencode($to));
    echo "<div class=\"mb-4 flex gap-2\"><a class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\" href=\"{$base}&format=csv\">CSV</a><a class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\" href=\"{$base}&format=xls\">Excel</a><a class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\" href=\"{$base}&format=pdf\">PDF</a></div>";
}
if (!$rows) echo notice("This report has no rows yet.");
else {
    $headers = [];
    $keys = array_keys($rows[0]);
    foreach ($keys as $key) $headers[] = $labels[$key] ?? ucfirst(preg_replace("/([A-Z])/", " $1", $key) ?? $key);
    $body = "";
    foreach ($rows as $row) {
        $body .= "<tr>";
        foreach ($keys as $key) {
            $value = $row[$key];
            if ($value === null || $value === "") $cell = "—";
            elseif (in_array($key, $statusKeys, true)) $cell = status_text((string) $value);
            elseif (in_array($key, $entityKeys, true)) $cell = entity_cell((string) $value);
            elseif (str_contains(strtolower($key), "kg") && is_numeric($value)) $cell = "<span class=\"inline-flex rounded-md bg-[#e8f7ef] px-2.5 py-1 text-xs font-medium text-[#0f6b45]\">" . e(number_format((float) $value)) . " kg</span>";
            else $cell = e((string) $value);
            $body .= "<td>{$cell}</td>";
        }
        $body .= "</tr>";
    }
    echo data_panel(data_table($headers, $body));
}
echo "</section>";
layout_end();
