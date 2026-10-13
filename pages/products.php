<?php
$error = "";
$old = $_POST;
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["form"] ?? "") === "create") {
    csrf_check();
    if (!can("products.create")) {
        $error = "You do not have permission to add a product.";
    } else {
        $name = trim((string) ($_POST["name"] ?? ""));
        $category = (string) ($_POST["category"] ?? "FINISHED_PRODUCT");
        $unit = (string) ($_POST["unit"] ?? "KG");
        $map = ["FINISHED_PRODUCT" => "product_finished", "RAW_MATERIAL" => "product_raw", "OTHER" => "product_other"];
        $units = ["KG", "TONNE", "PIECE", "OTHER"];
        if ($name === "" || !isset($map[$category]) || !in_array($unit, $units, true)) {
            $error = "Check the product name, category, and unit.";
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            $id = uuid();
            $code = next_code($pdo, $map[$category]);
            $pdo->prepare("INSERT INTO products (id, product_code, name, category, description, unit) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$id, $code, $name, $category, trim((string) ($_POST["description"] ?? "")) ?: null, $unit]);
            $pdo->commit();
            audit(current_user()["id"], "CREATE", "Product", $id, "Product created.", null, ["code" => $code, "name" => $name]);
            redirect("products");
        }
    }
}
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["form"] ?? "") === "status") {
    csrf_check();
    if (!can("products.deactivate")) exit("You do not have permission to change this product.");
    $id = (string) ($_POST["id"] ?? "");
    $next = ($_POST["status"] ?? "") === "ACTIVE" ? "ACTIVE" : "INACTIVE";
    $current = db()->prepare("SELECT status, name FROM products WHERE id = ?");
    $current->execute([$id]);
    $row = $current->fetch();
    if ($row) {
        db()->prepare("UPDATE products SET status = ? WHERE id = ?")->execute([$next, $id]);
        audit(current_user()["id"], $next === "ACTIVE" ? "REACTIVATE" : "DEACTIVATE", "Product", $id, "Product status updated.", ["status" => $row["status"]], ["status" => $next]);
    }
    redirect("products");
}
$search = trim((string) ($_GET["search"] ?? ""));
$params = [];
$where = "";
if ($search !== "") {
    $where = "WHERE name LIKE ? OR product_code LIKE ? OR description LIKE ?";
    $like = "%" . $search . "%";
    $params = [$like, $like, $like];
}
$stmt = db()->prepare("SELECT * FROM products {$where} ORDER BY name");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$open = isset($_GET["create"]) || $error !== "";
layout_start("products");
echo "<section>";
echo page_header("Products", can("products.create") ? primary_link(url("products?create=1"), "Add product") : "");
echo "<form method=\"get\"><input aria-label=\"Search products\" name=\"search\" class=\"" . input_class() . " mb-3 max-w-sm\" placeholder=\"Search name or code\" value=\"" . e($search) . "\"></form>";
if (!$rows) {
    echo notice("No products have been registered yet.");
} else {
    $body = "";
    foreach ($rows as $row) {
        $body .= "<tr><td class=\"font-medium text-slate-700\">" . e($row["product_code"]) . "</td><td>" . entity_cell($row["name"]) . "</td><td>" . status_text($row["category"]) . "</td><td>" . e($row["unit"]) . "</td><td>" . status_text($row["status"]) . "</td><td>";
        if (can("products.deactivate")) {
            $next = $row["status"] === "ACTIVE" ? "INACTIVE" : "ACTIVE";
            $label = $row["status"] === "ACTIVE" ? "Deactivate" : "Reactivate";
            $confirm = $next === "INACTIVE" ? " onsubmit=\"return confirm(" . htmlspecialchars(json_encode("Deactivate {$row["name"]}?"), ENT_QUOTES) . ")\"" : "";
            $body .= "<form method=\"post\" class=\"inline\"{$confirm}>" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"status\"><input type=\"hidden\" name=\"id\" value=\"" . e($row["id"]) . "\"><input type=\"hidden\" name=\"status\" value=\"{$next}\"><button class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\">{$label}</button></form>";
        }
        $body .= "</td></tr>";
    }
    echo data_panel(data_table(["Code", "Name", "Category", "Unit", "Status", "Action"], $body));
}
if ($open && can("products.create")) {
    $cls = input_class();
    $form = "<form method=\"post\" class=\"grid gap-3 md:grid-cols-2\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"create\">";
    $form .= field("Name", "<input class=\"{$cls}\" name=\"name\" required value=\"" . e($old["name"] ?? "") . "\">");
    $form .= field("Category", "<select class=\"{$cls}\" name=\"category\"><option value=\"FINISHED_PRODUCT\">Finished product</option><option value=\"RAW_MATERIAL\">Raw material</option><option value=\"OTHER\">Other</option></select>");
    $form .= field("Unit", "<select class=\"{$cls}\" name=\"unit\"><option value=\"KG\">Kilogram</option><option value=\"TONNE\">Tonne</option><option value=\"PIECE\">Piece</option><option value=\"OTHER\">Other</option></select>");
    $form .= field("Description", "<textarea class=\"{$cls}\" name=\"description\">" . e($old["description"] ?? "") . "</textarea>");
    $form .= "<div class=\"md:col-span-2\">" . error_text($error) . "</div>";
    $form .= "<div class=\"flex gap-2 md:col-span-2\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Save</button><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . e(url("products")) . "\">Cancel</a></div></form>";
    echo modal("Add product", $form, url("products"));
}
echo "</section>";
layout_end();
