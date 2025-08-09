<?php
session_start();

require_once 'config/i18n.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    try {
        $newHash = password_hash('katze', PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ?, first_login = 1 WHERE LOWER(username) = 'admin'";
        $db->execute($sql, [$newHash]);
        $message = 'Admin-Passwort wurde auf "katze" zurückgesetzt. Beim nächsten Login muss es geändert werden.';
    } catch (Exception $e) {
        $error = 'Fehler beim Zurücksetzen: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('app.name') ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 15px; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .btn { background: #d63031; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Admin-Passwort zurücksetzen</h1>
        <p class="warning"><strong>Wichtig:</strong> Diese Datei ist nur für Notfälle gedacht. Bitte löschen Sie <code>resetAdmin.php</code> nach der Verwendung aus Sicherheitsgründen.</p>
        <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <form method="post" onsubmit="return confirm('Admin-Passwort wirklich auf \"katze\" zurücksetzen?');">
            <button type="submit" name="reset" class="btn">Passwort zurücksetzen</button>
        </form>
    </div>
</body>
</html>