<?php
$error = "";
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    csrf_check();
    $form = (string) ($_POST["form"] ?? "");
    $actor = current_user();
    if ($form === "create") {
        $name = trim((string) ($_POST["full_name"] ?? ""));
        $email = strtolower(trim((string) ($_POST["email"] ?? "")));
        $password = (string) ($_POST["password"] ?? "");
        $role = (string) ($_POST["role"] ?? "STAFF");
        $problem = valid_password($password);
        if (strlen($name) < 2) $error = "Enter the full name.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Enter a valid email.";
        elseif ($problem) $error = $problem;
        elseif (!isset(ROLE_IDS[$role]) || ($role !== "STAFF" && !can("roles.assign") && $role !== $actor["role_name"])) $error = "Choose a valid role.";
        else {
            $exists = db()->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) $error = "That email is already in use.";
            else {
                $id = uuid();
                db()->prepare("INSERT INTO users (id, full_name, email, password_hash, role_id, must_change_password, email_verified) VALUES (?, ?, ?, ?, ?, 1, 1)")
                    ->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), ROLE_IDS[$role]]);
                audit($actor["id"], "CREATE", "User", $id, "User account created.", null, ["email" => $email, "role" => $role]);
                redirect("users");
            }
        }
    }
    if ($form === "role" && can("roles.assign")) {
        $id = (string) ($_POST["id"] ?? "");
        $role = (string) ($_POST["role"] ?? "");
        if (isset(ROLE_IDS[$role]) && $id !== $actor["id"]) {
            $before = db()->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
            $before->execute([$id]);
            $old = $before->fetchColumn();
            db()->prepare("UPDATE users SET role_id = ? WHERE id = ?")->execute([ROLE_IDS[$role], $id]);
            audit($actor["id"], "ROLE_CHANGE", "User", $id, "Role changed.", ["role" => $old], ["role" => $role]);
        }
        redirect("users");
    }
    if ($form === "status") {
        $id = (string) ($_POST["id"] ?? "");
        $next = ($_POST["status"] ?? "") === "ACTIVE" ? "ACTIVE" : "INACTIVE";
        if ($id === $actor["id"]) $error = "You cannot change your own account status here.";
        else {
            db()->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$next, $id]);
            audit($actor["id"], $next === "ACTIVE" ? "REACTIVATE" : "DEACTIVATE", "User", $id, "User status updated.", null, ["status" => $next]);
            redirect("users");
        }
    }
    if ($form === "password") {
        $id = (string) ($_POST["id"] ?? "");
        $password = (string) ($_POST["password"] ?? "");
        $problem = valid_password($password);
        if ($problem) $error = $problem;
        else {
            db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            audit($actor["id"], "PASSWORD_CHANGE", "User", $id, "Temporary password set.");
            redirect("users");
        }
    }
}
$users = db()->query("SELECT u.id, u.full_name, u.email, u.status, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.full_name")->fetchAll();
$open = isset($_GET["create"]) || ($error !== "" && (($_POST["form"] ?? "") === "create"));
$resetId = (string) ($_GET["reset"] ?? (($_POST["form"] ?? "") === "password" ? ($_POST["id"] ?? "") : ""));
$resetUser = null;
foreach ($users as $user) {
    if ($user["id"] === $resetId) {
        $resetUser = $user;
    }
}
layout_start("users");
echo "<section>";
echo page_header("Users", primary_link(url("users?create=1"), "Add user"));
if ($error !== "" && !$resetUser && !$open) {
    echo error_text($error);
}
$body = "";
foreach ($users as $user) {
    $body .= "<tr><td>" . entity_cell($user["full_name"]) . "</td><td>" . e($user["email"]) . "</td><td>";
    if (can("roles.assign")) {
        $body .= "<form method=\"post\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"role\"><input type=\"hidden\" name=\"id\" value=\"" . e($user["id"]) . "\"><select aria-label=\"Role for " . e($user["full_name"]) . "\" class=\"rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm\" name=\"role\" onchange=\"this.form.submit()\">";
        foreach (["ADMIN", "MANAGER", "STAFF"] as $role) {
            $body .= "<option" . ($user["role_name"] === $role ? " selected" : "") . ">{$role}</option>";
        }
        $body .= "</select></form>";
    } else $body .= status_text($user["role_name"]);
    $next = $user["status"] === "ACTIVE" ? "INACTIVE" : "ACTIVE";
    $label = $user["status"] === "ACTIVE" ? "Deactivate" : "Activate";
    $body .= "</td><td>" . status_text($user["status"]) . "</td><td class=\"whitespace-nowrap\"><div class=\"flex items-center gap-2\">";
    $body .= "<form method=\"post\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"status\"><input type=\"hidden\" name=\"id\" value=\"" . e($user["id"]) . "\"><input type=\"hidden\" name=\"status\" value=\"{$next}\"><button class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm hover:bg-slate-50\">{$label}</button></form>";
    $body .= "<a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm hover:bg-slate-50\" href=\"" . e(url("users?reset=" . $user["id"])) . "\">Reset password</a>";
    $body .= "</div></td></tr>";
}
echo data_panel(data_table(["Name", "Email", "Role", "Status", "Actions"], $body));
if ($open) {
    $cls = input_class();
    $form = "<form method=\"post\" class=\"grid gap-3 md:grid-cols-2\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"create\">";
    $form .= field("Full name", "<input class=\"{$cls}\" name=\"full_name\" required value=\"" . e($_POST["full_name"] ?? "") . "\">");
    $form .= field("Email", "<input class=\"{$cls}\" type=\"email\" name=\"email\" required value=\"" . e($_POST["email"] ?? "") . "\">");
    $form .= field("Temporary password", "<input class=\"{$cls}\" type=\"password\" name=\"password\" required minlength=\"8\">");
    $form .= field("Role", "<select class=\"{$cls}\" name=\"role\"><option value=\"STAFF\">Staff</option><option value=\"MANAGER\">Manager</option><option value=\"ADMIN\">Admin</option></select>");
    $form .= "<div class=\"md:col-span-2\">" . error_text($error) . "</div>";
    $form .= "<div class=\"flex gap-2 md:col-span-2\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Create user</button><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . e(url("users")) . "\">Cancel</a></div></form>";
    echo modal("Add user", $form, url("users"));
}
if ($resetUser) {
    $cls = input_class();
    $form = "<form method=\"post\" class=\"grid max-w-md gap-3\">" . csrf_field() . "<input type=\"hidden\" name=\"form\" value=\"password\"><input type=\"hidden\" name=\"id\" value=\"" . e($resetUser["id"]) . "\">";
    $form .= "<p class=\"text-sm text-slate-600\">Set a temporary password for " . e($resetUser["full_name"]) . ". Use at least 8 characters with a letter and a number.</p>";
    $form .= field("Temporary password", "<input class=\"{$cls}\" type=\"password\" name=\"password\" required minlength=\"8\" autocomplete=\"new-password\">");
    $form .= error_text(($_POST["form"] ?? "") === "password" ? $error : "");
    $form .= "<div class=\"flex gap-2\"><button class=\"rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white\">Save password</button><a class=\"rounded-md border border-slate-300 bg-[#fffcf8] px-3.5 py-2 text-sm\" href=\"" . e(url("users")) . "\">Cancel</a></div></form>";
    echo modal("Reset password", $form, url("users"));
}
echo "</section>";
layout_end();
