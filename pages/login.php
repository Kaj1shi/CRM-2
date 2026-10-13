<?php
if (current_user()) {
    redirect("");
}
$error = "";
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    csrf_check();
    $email = strtolower(trim((string) ($_POST["email"] ?? "")));
    $password = (string) ($_POST["password"] ?? "");
    $stmt = db()->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $invalid = "The email or password is incorrect.";
    if (!$user || $user["status"] !== "ACTIVE") {
        $error = $invalid;
    } elseif ($user["locked_until"] && strtotime($user["locked_until"]) > time()) {
        $error = "This account is temporarily locked. Try again later.";
    } elseif (!password_verify($password, $user["password_hash"])) {
        $attempts = (int) $user["failed_login_attempts"] + 1;
        $locked = $attempts >= 5 ? date("Y-m-d H:i:s", time() + 15 * 60) : null;
        db()->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?")->execute([$attempts, $locked, $user["id"]]);
        $error = $invalid;
    } else {
        db()->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?")->execute([$user["id"]]);
        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        audit($user["id"], "LOGIN", "User", $user["id"], "User signed in.");
        redirect("");
    }
}
guest_start("Sign in");
?>
<main class="grid min-h-dvh place-items-center px-4 py-16">
  <form method="post" class="w-full max-w-md">
    <?= csrf_field() ?>
    <p class="text-xs tracking-[0.16em] text-slate-500 uppercase">Kampala industrial area</p>
    <h1 class="mt-3 text-5xl font-medium tracking-tight">Factory CRM</h1>
    <p class="mt-3 max-w-sm text-sm text-slate-600">Sign in to record a purchase or a supply.</p>
    <label class="mt-8 block text-sm font-medium" for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="username" class="mt-1 w-full rounded-md border border-slate-300 bg-[#fffcf8] px-3 py-2" value="<?= e($_POST["email"] ?? "") ?>">
    <label class="mt-4 block text-sm font-medium" for="password">Password</label>
    <div class="relative mt-1">
      <input id="password" name="password" type="password" required minlength="8" autocomplete="current-password" class="w-full rounded-md border border-slate-300 bg-[#fffcf8] px-3 py-2 pr-10">
      <button type="button" class="absolute inset-y-0 right-0 grid w-10 place-items-center text-slate-500" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><?= icon("eye") ?></button>
    </div>
    <?= error_text($error) ?>
    <button class="mt-8 w-full rounded-md bg-emerald-600 px-4 py-2.5 text-white hover:bg-emerald-700">Sign in</button>
    <a class="mt-4 inline-block text-sm text-emerald-700 underline-offset-4 hover:underline" href="<?= e(url("forgot-password")) ?>">Forgot password</a>
  </form>
</main>
<?php guest_end();
