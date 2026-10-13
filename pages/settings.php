<?php
$saved = false;
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    csrf_check();
    $before = settings_all();
    $stmt = db()->prepare("INSERT INTO settings (`key`, value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)");
    foreach (DEFAULT_SETTINGS as $key => $default) {
        if (!array_key_exists($key, $_POST)) continue;
        $raw = trim((string) $_POST[$key]);
        $value = is_int($default) || is_float($default) ? (is_numeric($raw) ? $raw + 0 : $default) : $raw;
        $stmt->execute([$key, json_encode($value), current_user()["id"]]);
    }
    audit(current_user()["id"], "UPDATE", "Setting", null, "System settings updated.", $before, settings_all());
    $saved = true;
}
$current = settings_all();
layout_start("settings");
echo "<section>";
echo page_header("Settings");
if ($saved) echo notice("Settings saved.");
echo "<form method=\"post\" class=\"grid max-w-xl gap-3\">" . csrf_field();
foreach ($current as $key => $value) {
    if (!array_key_exists($key, DEFAULT_SETTINGS)) continue;
    echo field(str_replace("_", " ", $key), "<input class=\"" . input_class() . "\" name=\"" . e($key) . "\" value=\"" . e(is_scalar($value) ? (string) $value : json_encode($value)) . "\">");
}
echo "<button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Save settings</button></form></section>";
layout_end();
