<?php
if (current_user()) redirect("");
$message = "";
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    csrf_check();
    $email = strtolower(trim((string) ($_POST["email"] ?? "")));
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND status = \"ACTIVE\"");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $message = "If an account exists for that email, password reset instructions have been recorded.";
    if ($user) {
        $token = bin2hex(random_bytes(24));
        db()->prepare("INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at) VALUES (?, ?, ?, ?)")
            ->execute([uuid(), $user["id"], hash("sha256", $token), date("Y-m-d H:i:s", time() + 3600)]);
        $link = url("reset-password") . "?token=" . urlencode($token);
        $message .= " Local reset link: " . $link;
    }
}
guest_start("Reset password");
?>
<main class="grid min-h-dvh place-items-center px-4 py-16">
  <form method="post" class="w-full max-w-md">
    <?= csrf_field() ?>
    <h1 class="text-4xl font-medium tracking-tight">Reset password</h1>
    <label class="mt-4 block text-sm" for="reset-email">Email</label>
    <input id="reset-email" name="email" type="email" required class="mt-1 w-full rounded-md border border-slate-300 bg-[#fffcf8] px-3 py-2">
    <?php if ($message): ?><p class="mt-3 text-sm"><?= e($message) ?></p><?php endif; ?>
    <button class="mt-6 rounded-md bg-emerald-600 px-4 py-2.5 text-white hover:bg-emerald-700">Send reset link</button>
    <a class="mt-4 block text-sm text-emerald-700" href="<?= e(url("login")) ?>">Back to sign in</a>
  </form>
</main>
<?php guest_end();
