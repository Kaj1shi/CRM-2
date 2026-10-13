<?php
$action = (string) ($_GET["action"] ?? "");
$actions = ["LOGIN","LOGOUT","CREATE","UPDATE","DEACTIVATE","REACTIVATE","ROLE_CHANGE","PASSWORD_CHANGE","REPORT_EXPORT"];
$sql = "SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id";
$params = [];
if (in_array($action, $actions, true)) {
    $sql .= " WHERE a.action = ?";
    $params[] = $action;
}
$sql .= " ORDER BY a.created_at DESC LIMIT 50";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$total = (int) db()->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
layout_start("audit");
echo "<section>";
echo page_header("Audit logs");
echo "<form method=\"get\"><select aria-label=\"Audit action\" name=\"action\" class=\"" . input_class() . " mb-3 max-w-xs\" onchange=\"this.form.submit()\"><option value=\"\">All actions</option>";
foreach ($actions as $item) {
    $selected = $action === $item ? " selected" : "";
    echo "<option{$selected}>{$item}</option>";
}
echo "</select></form>";
echo "<p class=\"mb-2 text-sm text-slate-600\">{$total} records</p>";
$body = "";
foreach ($rows as $row) {
    $body .= "<tr><td>" . e(format_datetime($row["created_at"])) . "</td><td>" . e($row["full_name"] ?? "—") . "</td><td>" . status_text($row["action"]) . "</td><td>" . e($row["entity"]) . "</td><td>" . e($row["details"] ?? "") . "</td></tr>";
}
if ($body === "") $body = "<tr><td colspan=\"5\">No audit records yet.</td></tr>";
echo data_panel(data_table(["When", "User", "Action", "Entity", "Details"], $body));
echo "</section>";
layout_end();
