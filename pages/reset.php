<?php
$token = (string) ($_GET["token"] ?? $_POST["token"] ?? "");
$message = "";
$error = "";
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    csrf_check();
    $password = (string) ($_POST["password"] ?? "");
    $problem = valid_password($password);
    $stmt = db()->prepare("SELECT * FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute([hash("sha256", $token)]);
    $row = $stmt->fetch();
    if ($problem) {
        $error = $problem;
    } elseif (!$row) {
        $error = "This reset link is invalid or has expired.";
    } else {
        db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, failed_login_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row["user_id"]]);
        db()->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")->execute([$row["id"]]);
        audit($row["user_id"], "PASSWORD_CHANGE", "User", $row["user_id"], "Password reset completed.");
        $message = "Password updated. Please sign in.";
    }
}
guest_start("Choose a new password");
?>
<main class="grid min-h-dvh place-items-center px-4 py-16">
  <form method="post" class="w-full max-w-md">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <h1 class="text-4xl font-medium tracking-tight">Choose a new password</h1>
    <label class="mt-4 block text-sm" for="new-password">New password</label>
    <input id="new-password" name="password" type="password" required minlength="8" class="mt-1 w-full rounded-md border border-slate-300 bg-[#fffcf8] px-3 py-2">
    <?php if ($message): ?><p class="mt-3 text-sm" role="status"><?= e($message) ?></p><?php endif; ?>
    <?= error_text($error) ?>
    <button class="mt-6 rounded-md bg-emerald-600 px-4 py-2.5 text-white hover:bg-emerald-700">Update password</button>
    <a class="mt-4 block text-sm text-emerald-700" href="<?= e(url("login")) ?>">Back to sign in</a>
  </form>
</main>
<?php guest_end();
