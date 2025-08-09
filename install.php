<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatControl Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .step {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        .step h3 {
            margin-top: 0;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐱 CatControl Installation</h1>
        
        <?php
        $config_file = 'config/database.php';
        $config_dir = 'config';
        
        // Check if already installed
        if (file_exists($config_file)) {
            echo '<div class="warning">⚠️ CatControl scheint bereits installiert zu sein. Wenn Sie neu installieren möchten, löschen Sie bitte die Datei config/database.php</div>';
            echo '<p><a href="index.php" class="btn">Zur Anwendung</a></p>';
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? 'catcontrol';
            $db_user = $_POST['db_user'] ?? 'phpuser';
            $db_password = $_POST['db_password'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            $smtp_host = $_POST['smtp_host'] ?? '';
            $smtp_username = $_POST['smtp_username'] ?? '';
            $smtp_password = $_POST['smtp_password'] ?? '';
            
            $errors = [];
            
            // Validate input
            if (empty($db_password)) {
                $errors[] = "Datenbankpasswort ist erforderlich";
            }
            if (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Gültige Admin-E-Mail-Adresse ist erforderlich";
            }
            
            if (empty($errors)) {
                try {
                    // Create config directory if it doesn't exist
                    if (!is_dir($config_dir)) {
                        mkdir($config_dir, 0755, true);
                    }
                    
                    // Test database connection
                    $dsn = "mysql:host=$db_host;charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Read and execute SQL file
                    $sql = file_get_contents('database.sql');
                    if ($sql === false) {
                        throw new Exception("Kann database.sql nicht lesen");
                    }
                    
                    // Replace default password in SQL
                    $sql = str_replace("IDENTIFIED BY 'changeme123'", "IDENTIFIED BY '$db_password'", $sql);
                    $sql = str_replace("'admin@localhost'", "'$admin_email'", $sql);
                    
                    // Execute SQL statements
                    $statements = explode(';', $sql);
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    
                    // Create config file
                    $config_content = "<?php
// CatControl Database Configuration
// Generated by install.php on " . date('Y-m-d H:i:s') . "

return [
    'host' => '$db_host',
    'database' => '$db_name',
    'username' => '$db_user',
    'password' => '$db_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
    'smtp' => [
        'host' => '$smtp_host',
        'port' => 587,
        'username' => '$smtp_username',
        'password' => '$smtp_password',
        'encryption' => 'tls',
        'from_email' => '$admin_email',
        'from_name' => 'CatControl'
    ]
];
";
                    
                    if (file_put_contents($config_file, $config_content) === false) {
                        throw new Exception("Kann Konfigurationsdatei nicht erstellen");
                    }
                    
                    // Create upload directories
                    $upload_dirs = ['uploads', 'uploads/kittens', 'uploads/profiles', 'uploads/backgrounds'];
                    foreach ($upload_dirs as $dir) {
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                    }

                    // PHPMailer automatisch installieren (Composer), falls noch nicht vorhanden
                    $mailerInstalled = false;
                    $mailerMessage = '';
                    $projectRoot = __DIR__;
                    $vendorAutoload = $projectRoot . '/vendor/autoload.php';

                    if (!file_exists($vendorAutoload)) {
                        if (function_exists('shell_exec')) {
                            // Versuche System-Composer zu finden
                            $composerBin = trim((string) shell_exec('command -v composer || which composer 2>/dev/null'));
                            if (!empty($composerBin)) {
                                // Composer nutzen, um PHPMailer zu installieren
                                shell_exec(escapeshellcmd($composerBin) . ' require phpmailer/phpmailer:^6 --no-interaction --no-progress --no-ansi 2>&1');
                            } else {
                                // Fallback: lokalen Composer (composer.phar) verwenden
                                shell_exec('php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');" 2>&1');
                                shell_exec('php composer-setup.php --quiet 2>&1');
                                @unlink('composer-setup.php');
                                if (file_exists($projectRoot . '/composer.phar')) {
                                    shell_exec('php composer.phar require phpmailer/phpmailer:^6 --no-interaction --no-progress --no-ansi 2>&1');
                                }
                            }
                        }
                    }

                    if (file_exists($vendorAutoload)) {
                        $mailerInstalled = class_exists('PHPMailer\\PHPMailer\\PHPMailer') || strpos((string) @file_get_contents($projectRoot . '/composer.json'), 'phpmailer/phpmailer') !== false;
                    }

                    if ($mailerInstalled) {
                        $mailerMessage = '• PHPMailer wurde automatisch installiert und aktiviert';
                    } else {
                        $mailerMessage = '• Hinweis: PHPMailer konnte nicht automatisch installiert werden. Führen Sie im Projektverzeichnis folgenden Befehl aus: <code>composer require phpmailer/phpmailer:^6</code>';
                    }

                    echo '<div class="success">
                        ✅ <strong>Installation erfolgreich!</strong><br>
                        • Datenbank wurde erstellt und initialisiert<br>
                        • Konfigurationsdatei wurde erstellt<br>
                        • Upload-Verzeichnisse wurden erstellt<br>
                        ' . $mailerMessage . '<br>
                        • Standard-Admin-Benutzer: admin / katze<br><br>
                        <strong>Wichtig:</strong> Bitte löschen Sie diese install.php Datei aus Sicherheitsgründen!
                    </div>';
                    
                    echo '<p><a href="index.php" class="btn">Zur Anwendung</a></p>';
                    
                } catch (Exception $e) {
                    echo '<div class="error">❌ <strong>Installationsfehler:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="error">❌ <strong>Fehler:</strong><br>' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
            }
        }
        
        if (!isset($_POST['install'])) {
        ?>
        
        <div class="step">
            <h3>📋 Voraussetzungen</h3>
            <p>Stellen Sie sicher, dass folgende Voraussetzungen erfüllt sind:</p>
            <ul>
                <li>✅ PHP 8.2 oder höher</li>
                <li>✅ MariaDB/MySQL Server</li>
                <li>✅ Apache2 Webserver</li>
                <li>✅ PHP Extensions: PDO, PDO_MySQL, GD, Fileinfo</li>
                <li>✅ Schreibrechte für den Webserver im Projektverzeichnis</li>
            </ul>
        </div>
        
        <form method="post">
            <div class="step">
                <h3>🗄️ Datenbank-Konfiguration</h3>
                
                <div class="form-group">
                    <label for="db_host">Datenbank-Host:</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Datenbankname:</label>
                    <input type="text" id="db_name" name="db_name" value="catcontrol" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Datenbank-Benutzer:</label>
                    <input type="text" id="db_user" name="db_user" value="phpuser" required>
                </div>
                
                <div class="form-group">
                    <label for="db_password">Datenbank-Passwort:</label>
                    <input type="password" id="db_password" name="db_password" required>
                    <small>Passwort für den Datenbankbenutzer 'phpuser'</small>
                </div>
            </div>
            
            <div class="step">
                <h3>📧 E-Mail-Konfiguration</h3>
                
                <div class="form-group">
                    <label for="admin_email">Administrator E-Mail:</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                    <small>Diese E-Mail wird für System-Benachrichtigungen verwendet</small>
                </div>
                
                <div class="form-group">
                    <label for="smtp_host">SMTP-Host (für Gmail: smtp.gmail.com):</label>
                    <input type="text" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com">
                </div>
                
                <div class="form-group">
                    <label for="smtp_username">SMTP-Benutzername:</label>
                    <input type="text" id="smtp_username" name="smtp_username" placeholder="ihre-email@gmail.com">
                </div>
                
                <div class="form-group">
                    <label for="smtp_password">SMTP-Passwort (App-Passwort):</label>
                    <input type="password" id="smtp_password" name="smtp_password">
                    <small>Für Gmail: Verwenden Sie ein App-Passwort, nicht Ihr normales Passwort</small>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" name="install" class="btn">🚀 CatControl installieren</button>
            </div>
        </form>
        
        <div class="warning">
            <strong>⚠️ Sicherheitshinweis:</strong><br>
            Diese Installation ist für den Einsatz in einem sicheren Heimnetzwerk konzipiert. 
            Wenn Sie die Anwendung über das Internet zugänglich machen möchten, beachten Sie bitte 
            die Sicherheitshinweise in der README.md Datei.
        </div>
        
        <?php } ?>
    </div>
</body>
</html>