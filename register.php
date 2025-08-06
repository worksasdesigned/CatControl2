<?php
session_start();

require_once 'classes/User.php';

$userService = new User();

// Redirect if already logged in
if ($userService->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $allowMessages = isset($_POST['allow_messages']);
    
    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Benutzername ist erforderlich';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Gültige E-Mail-Adresse ist erforderlich';
    }
    
    if (empty($password)) {
        $errors[] = 'Passwort ist erforderlich';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwörter stimmen nicht überein';
    }
    
    if (empty($country)) {
        $errors[] = 'Land ist erforderlich';
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        $result = $userService->register($username, $email, $password, $country, $city, $allowMessages);
        
        if ($result['success']) {
            $success = $result['message'] . ' Sie können sich jetzt anmelden.';
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatControl - Registrierung</title>
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
            padding: 20px 0;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            text-align: center;
            margin: 20px 0;
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
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #ff6b6b;
            outline: none;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .checkbox-group input {
            margin-right: 10px;
            margin-top: 5px;
            width: auto;
        }
        
        .checkbox-group label {
            font-size: 14px;
            line-height: 1.4;
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
        
        .required {
            color: #ff6b6b;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .register-container {
                padding: 20px;
                margin: 10px;
            }
            
            .logo {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">🐱 CatControl</div>
        <div class="tagline">Registrierung - Kätzchen verwalten leicht gemacht</div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="username">Benutzername: <span class="required">*</span></label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail-Adresse: <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Passwort: <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                    <small>Mindestens 8 Zeichen</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Passwort bestätigen: <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="country">Land: <span class="required">*</span></label>
                    <select id="country" name="country" required>
                        <option value="">Bitte wählen...</option>
                        <option value="Deutschland" <?= (($_POST['country'] ?? '') === 'Deutschland') ? 'selected' : '' ?>>Deutschland</option>
                        <option value="Österreich" <?= (($_POST['country'] ?? '') === 'Österreich') ? 'selected' : '' ?>>Österreich</option>
                        <option value="Schweiz" <?= (($_POST['country'] ?? '') === 'Schweiz') ? 'selected' : '' ?>>Schweiz</option>
                        <option value="Niederlande" <?= (($_POST['country'] ?? '') === 'Niederlande') ? 'selected' : '' ?>>Niederlande</option>
                        <option value="Belgien" <?= (($_POST['country'] ?? '') === 'Belgien') ? 'selected' : '' ?>>Belgien</option>
                        <option value="Frankreich" <?= (($_POST['country'] ?? '') === 'Frankreich') ? 'selected' : '' ?>>Frankreich</option>
                        <option value="Andere" <?= (($_POST['country'] ?? '') === 'Andere') ? 'selected' : '' ?>>Andere</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="city">Stadt (optional):</label>
                    <input type="text" id="city" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="allow_messages" name="allow_messages" <?= isset($_POST['allow_messages']) ? 'checked' : 'checked' ?>>
                <label for="allow_messages">Andere Benutzer dürfen mir Nachrichten schreiben<br>
                <small>(Ihre E-Mail-Adresse wird nicht veröffentlicht!)</small></label>
            </div>
            
            <button type="submit" name="register" class="btn">Registrieren</button>
        </form>
        
        <div class="links">
            <a href="index.php">Zurück zur Anmeldung</a>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            
            if (username.length > 0 && username.length < 3) {
                this.setCustomValidity('Benutzername muss mindestens 3 Zeichen lang sein');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Password strength validation
        document.getElementById('password').addEventListener('input', function() {
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