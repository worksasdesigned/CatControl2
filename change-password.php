<?php
session_start();

require_once 'config/i18n.php';
require_once 'classes/User.php';

$userService = new User();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$isFirstLogin = isset($_GET['first_login']) && $_GET['first_login'] == '1';

$error = '';
$success = '';

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // For first login, we don't require current password verification
    if (!$isFirstLogin) {
        if (empty($currentPassword)) {
            $errors[] = 'Aktuelles Passwort ist erforderlich';
        } elseif (!password_verify($currentPassword, $currentUser['password_hash'])) {
            $errors[] = 'Aktuelles Passwort ist falsch';
        }
    }
    
    if (empty($newPassword)) {
        $errors[] = 'Neues Passwort ist erforderlich';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'Neues Passwort muss mindestens 8 Zeichen lang sein';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwörter stimmen nicht überein';
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        $result = $userService->updatePassword($currentUser['id'], $newPassword);
        
        if ($result['success']) {
            $success = $result['message'];
            
            // If this was first login, redirect to dashboard after a moment
            if ($isFirstLogin) {
                $success .= ' Sie werden zum Dashboard weitergeleitet...';
                echo '<script>setTimeout(function() { window.location.href = "dashboard.php"; }, 2000);</script>';
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('app.name') ?> - <?= __('menu.change_password') ?></title>
    <style>
        body {
            background-image: url('assets/images/background.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Arial', sans-serif;
        }
        
        .change-password-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5em;
            color: #ff6b6b;
            margin-bottom: 10px;
        }
        
        .tagline {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        
        .welcome-message {
            background: #e3f2fd;
            color: #1565c0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            border-color: #ff6b6b;
            outline: none;
        }
        
        .btn {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #ff5252, #ff7979);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
        }
        
        .links a {
            color: #ff6b6b;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 480px) {
            .change-password-container {
                padding: 20px;
                margin: 20px;
            }
            
            .logo {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <div class="logo">🐱 CatControl</div>
        <div class="tagline">
            <?php if ($isFirstLogin): ?>
                Willkommen! Bitte ändern Sie Ihr Passwort
            <?php else: ?>
                Passwort ändern
            <?php endif; ?>
        </div>
        
        <?php if ($isFirstLogin): ?>
            <div class="welcome-message">
                <strong>Willkommen, <?= htmlspecialchars($currentUser['username']) ?>!</strong><br>
                Aus Sicherheitsgründen müssen Sie bei der ersten Anmeldung Ihr Passwort ändern.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <?php if (!$isFirstLogin): ?>
                <div class="form-group">
                    <label for="current_password">Aktuelles Passwort:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="new_password">Neues Passwort:</label>
                <input type="password" id="new_password" name="new_password" required>
                <small>Mindestens 8 Zeichen</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Neues Passwort bestätigen:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" name="change_password" class="btn">Passwort ändern</button>
        </form>
        
        <?php if (!$isFirstLogin): ?>
            <div class="links">
                <a href="dashboard.php">Zurück zum Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Password strength validation
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            
            if (password.length > 0 && password.length < 8) {
                this.setCustomValidity('Passwort muss mindestens 8 Zeichen lang sein');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>