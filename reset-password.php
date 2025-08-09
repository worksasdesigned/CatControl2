<?php
session_start();

require_once 'config/i18n.php';
require_once 'classes/User.php';

$userService = new User();

// Redirect if already logged in
if ($userService->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Ungültiger Reset-Link';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Bitte füllen Sie alle Felder aus';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwörter stimmen nicht überein';
    } else {
        $result = $userService->resetPassword($token, $newPassword);
        
        if ($result['success']) {
            $success = $result['message'] . ' Sie können sich jetzt mit Ihrem neuen Passwort anmelden.';
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
    <title><?= __('app.name') ?> - <?= __('reset_password.title') ?></title>
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
        
        .reset-container {
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
            .reset-container {
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
    <div class="reset-container">
        <div class="logo">🐱 CatControl</div>
        <div class="tagline"><?= __('reset_password.title') ?></div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <div class="links">
                <a href="index.php">Zur Anmeldung</a>
            </div>
        <?php elseif (!empty($token) && empty($error)): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label for="new_password"><?= __('reset_password.new') ?>:</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>Mindestens 8 Zeichen</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?= __('reset_password.confirm') ?>:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn"><?= __('reset_password.change') ?></button>
            </form>
        <?php else: ?>
            <div class="links">
                <a href="index.php">Zur Anmeldung</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Password strength validation
        document.getElementById('new_password')?.addEventListener('input', function() {
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