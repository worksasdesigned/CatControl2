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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($username) || empty($password)) {
            $error = 'Bitte füllen Sie alle Felder aus';
        } else {
            $result = $userService->login($username, $password);
            
            if ($result['success']) {
                // Set remember me cookie if requested and cookies are accepted
                if ($rememberMe && isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted') {
                    setcookie('remembered_username', $username, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                }
                
                // Only force password change for default admin on first login
                if (strtolower($username) === 'admin' && !empty($result['first_login'])) {
                    header('Location: change-password.php?first_login=1');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['forgot_password'])) {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Bitte geben Sie Ihre E-Mail-Adresse ein';
        } else {
            $result = $userService->requestPasswordReset($email);
            $success = $result['message'];
        }
    }
}

// Get remembered username from cookie
$rememberedUsername = '';
if (isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted' && isset($_COOKIE['remembered_username'])) {
    $rememberedUsername = $_COOKIE['remembered_username'];
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatControl - Anmeldung</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        
        .login-container {
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            margin-right: 10px;
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
        
        .forgot-password-form {
            display: none;
        }
        
        /* Cookie Consent Banner */
        .cookie-consent {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 20px;
            text-align: center;
            z-index: 1000;
            display: none;
        }
        
        .cookie-consent.show {
            display: block;
        }
        
        .cookie-consent p {
            margin-bottom: 15px;
        }
        
        .cookie-consent button {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 5px;
        }
        
        .cookie-consent button:hover {
            background: #ff5252;
        }
        
        @media (max-width: 480px) {
            .login-container {
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
    <div class="login-container">
        <div class="logo">🐱 CatControl</div>
        <div class="tagline">Die einfache Seite um junge Kätzchen zu verwalten</div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="post" id="loginForm">
            <div class="form-group">
                <label for="username">Benutzername:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($rememberedUsername) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Benutzername merken</label>
            </div>
            
            <button type="submit" name="login" class="btn">Anmelden</button>
        </form>
        
        <!-- Forgot Password Form (Hidden by default) -->
        <form method="post" id="forgotPasswordForm" class="forgot-password-form">
            <div class="form-group">
                <label for="email">E-Mail-Adresse:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <button type="submit" name="forgot_password" class="btn">Passwort zurücksetzen</button>
            <button type="button" class="btn btn-secondary" onclick="showLoginForm()">Zurück zur Anmeldung</button>
        </form>
        
        <div class="links">
            <a href="#" onclick="showForgotPasswordForm()">Passwort vergessen?</a>
            <a href="register.php">Registrieren</a>
        </div>
    </div>
    
    <!-- Cookie Consent Banner -->
    <div id="cookieConsent" class="cookie-consent">
        <p>Diese Website verwendet Cookies, um Ihnen die beste Erfahrung zu bieten. Durch die weitere Nutzung stimmen Sie der Verwendung von Cookies zu.</p>
        <button onclick="acceptCookies()">Alle Cookies akzeptieren</button>
        <button onclick="rejectCookies()">Nur notwendige Cookies</button>
    </div>
    
    <script>
        // Cookie Consent Management
        function checkCookieConsent() {
            const consent = getCookie('cookie_consent');
            if (!consent) {
                document.getElementById('cookieConsent').classList.add('show');
            }
        }
        
        function acceptCookies() {
            setCookie('cookie_consent', 'accepted', 365);
            document.getElementById('cookieConsent').classList.remove('show');
        }
        
        function rejectCookies() {
            setCookie('cookie_consent', 'rejected', 365);
            document.getElementById('cookieConsent').classList.remove('show');
            // Clear any existing non-essential cookies
            deleteCookie('remembered_username');
        }
        
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        }
        
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
        
        function deleteCookie(name) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/;';
        }
        
        // Form switching
        function showForgotPasswordForm() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('forgotPasswordForm').style.display = 'block';
        }
        
        function showLoginForm() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('forgotPasswordForm').style.display = 'none';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkCookieConsent();
        });
    </script>
</body>
</html>