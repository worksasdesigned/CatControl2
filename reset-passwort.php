<?php
session_start();

require_once __DIR__ . '/config/i18n.php';
require_once __DIR__ . '/classes/User.php';

$userService = new User();

if ($userService->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$step = 1;
$verifiedUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify'])) {
        $username = trim($_POST['username'] ?? '');
        $kittenName = trim($_POST['kitten_name'] ?? '');
        if ($username === '' || $kittenName === '') {
            $error = __('login.error.fill_all');
        } else {
            $user = $userService->verifyUserWithKitten($username, $kittenName);
            if ($user) {
                $step = 2;
                $verifiedUsername = $username;
            } else {
                $error = __('reset_password.validation_failed');
            }
        }
    } elseif (isset($_POST['set_password'])) {
        $verifiedUsername = trim($_POST['username_verified'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if ($verifiedUsername === '' || $newPassword === '' || $confirmPassword === '') {
            $error = __('login.error.fill_all');
            $step = 1;
        } elseif ($newPassword !== $confirmPassword) {
            $error = __('user.password.change_failed') ?? 'Passwort konnte nicht geändert werden';
            $step = 2;
        } else {
            $result = $userService->setNewPasswordByUsername($verifiedUsername, $newPassword);
            if ($result['success']) {
                $success = $result['message'];
                $step = 0; // done
            } else {
                $error = $result['message'] ?? __('user.password.change_failed');
                $step = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('app.name') ?> - <?= __('reset_password.title') ?></title>
    <style>
        body { background-image: url('assets/images/background.png'); background-size: cover; background-position: center; background-attachment: fixed; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; font-family: 'Arial', sans-serif; }
        .container { background: rgba(255,255,255,0.95); padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-width: 480px; width: 90%; }
        h1 { margin-top: 0; text-align: center; color: #ff6b6b; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display:block; font-weight: bold; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        .form-group input:focus { border-color: #ff6b6b; outline: none; }
        .btn { background: linear-gradient(135deg,#ff6b6b,#ff8e8e); color:#fff; border:none; padding:12px 30px; border-radius:8px; cursor:pointer; width:100%; margin-top: 5px; }
        .btn:hover { background: linear-gradient(135deg,#ff5252,#ff7979); }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 15px; }
        .alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .links { text-align:center; margin-top: 10px; }
        .links a { color:#ff6b6b; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <h1>🐱 <?= __('reset_password.title') ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <div class="links"><a href="index.php"><?= __('login.back_to_login') ?></a></div>
    <?php elseif ($step === 1): ?>
        <form method="post">
            <div class="form-group">
                <label for="username"><?= __('reset_password.username') ?>:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="kitten_name"><?= __('reset_password.kitten_name') ?>:</label>
                <input type="text" id="kitten_name" name="kitten_name" required>
            </div>
            <button type="submit" name="verify" class="btn"><?= __('reset_password.verify') ?></button>
        </form>
    <?php elseif ($step === 2): ?>
        <form method="post">
            <input type="hidden" name="username_verified" value="<?= htmlspecialchars($verifiedUsername) ?>">
            <div class="form-group">
                <label for="new_password"><?= __('reset_password.new') ?>:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password"><?= __('reset_password.confirm') ?>:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="set_password" class="btn"><?= __('reset_password.set_new') ?></button>
        </form>
    <?php endif; ?>

    <div class="links"><a href="index.php"><?= __('login.back_to_login') ?></a></div>
</div>
<script>
// Client-side checks
const newPasswordEl = document.getElementById('new_password');
const confirmEl = document.getElementById('confirm_password');
newPasswordEl && newPasswordEl.addEventListener('input', function(){
  if (this.value.length>0 && this.value.length<8){ this.setCustomValidity('<?= addslashes(__('user.password.too_short')) ?>'); } else { this.setCustomValidity(''); }
});
confirmEl && confirmEl.addEventListener('input', function(){
  const pw = newPasswordEl ? newPasswordEl.value : '';
  if (this.value && pw !== this.value){ this.setCustomValidity('<?= addslashes(__('user.password.change_failed')) ?>'); } else { this.setCustomValidity(''); }
});
</script>
</body>
</html>