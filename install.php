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
        
        // Environment-based defaults for Docker
        $envDbHost = getenv('DB_HOST') ?: 'db';
        $envDbName = getenv('DB_NAME') ?: 'catcontrol';
        $envDbUser = getenv('DB_USER') ?: 'phpuser';
        $envDbPassword = getenv('DB_PASSWORD') ?: '';
        $envAdminEmail = getenv('ADMIN_EMAIL') ?: '';
        $envSmtpHost = getenv('SMTP_HOST') ?: '';
        $envSmtpUsername = getenv('SMTP_USERNAME') ?: '';
        $envSmtpPassword = getenv('SMTP_PASSWORD') ?: '';
        $allowInstall = (getenv('ALLOW_INSTALL') === '1');

        // Check if already installed
        $isInstalled = false;
        if (file_exists($config_file)) {
            try {
                $existingConfig = @include $config_file;
                // Consider it installed if a non-placeholder password is set
                if (is_array($existingConfig) && isset($existingConfig['password']) && $existingConfig['password'] !== 'changeme123' && $existingConfig['password'] !== '') {
                    $isInstalled = true;
                }
            } catch (Throwable $t) {
                // If config is broken, treat as not installed to allow recovery
                $isInstalled = false;
            }
        }

        if ($isInstalled && !$allowInstall) {
            echo '<div class="warning">⚠️ CatControl scheint bereits installiert zu sein. Wenn Sie neu installieren möchten, löschen Sie bitte die Datei <code>config/database.php</code> oder setzen Sie die Umgebungsvariable <code>ALLOW_INSTALL=1</code>.</div>';
            echo '<p><a href="index.php" class="btn">Zur Anwendung</a></p>';
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db_host = $_POST['db_host'] ?? $envDbHost;
            $db_name = $_POST['db_name'] ?? $envDbName;
            $db_user = $_POST['db_user'] ?? $envDbUser;
            $db_password = $_POST['db_password'] ?? $envDbPassword;
            
            $errors = [];
            
            // Validate input
            if (empty($db_password)) {
                $errors[] = "Datenbankpasswort ist erforderlich";
            }

            
            if (empty($errors)) {
                try {
                    // Create config directory if it doesn't exist
                    if (!is_dir($config_dir)) {
                        mkdir($config_dir, 0755, true);
                    }
                    
                    // Test database connection - prefer TCP and include dbname, with fallback
                    $dsnWithDb = "mysql:host={$db_host};dbname={$db_name};port=3306;charset=utf8mb4";
                    $dsnNoDb   = "mysql:host={$db_host};port=3306;charset=utf8mb4";
                    
                    try {
                        $pdo = new PDO($dsnWithDb, $db_user, $db_password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    } catch (PDOException $primaryException) {
                        // Fallback: connect without database and try to create/select it
                        $pdo = new PDO($dsnNoDb, $db_user, $db_password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        try {
                            $safeDbName = str_replace('`', '``', $db_name);
                            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            $pdo->exec("USE `{$safeDbName}`");
                        } catch (PDOException $e2) {
                            // If we cannot create or select the DB, bubble up the original error for clarity
                            throw $primaryException;
                        }
                    }
                    
                    // Check if core table exists; if not, initialize schema
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users'");
                    $stmt->execute([$db_name]);
                    $usersTableExists = (int)$stmt->fetchColumn() > 0;
                    
                    if (!$usersTableExists) {
                        // Read and execute SQL file (filter out user/privilege management statements)
                        $sql = file_get_contents('database.sql');
                        if ($sql === false) {
                            throw new Exception("Kann database.sql nicht lesen");
                        }
                        
                        // Replace defaults
                        $sql = str_replace("IDENTIFIED BY 'changeme123'", "IDENTIFIED BY '{$db_password}'", $sql);
                        
                        // Remove statements that require elevated privileges or are redundant
                        $filteredSql = $sql;
                        $filteredSql = preg_replace('/^\s*CREATE\s+DATABASE[\s\S]*?;\s*$/mi', '', $filteredSql);
                        $filteredSql = preg_replace('/^\s*USE\s+.+?;\s*$/mi', '', $filteredSql);
                        $filteredSql = preg_replace('/^\s*CREATE\s+USER[\s\S]*?;\s*$/mi', '', $filteredSql);
                        $filteredSql = preg_replace('/^\s*GRANT[\s\S]*?;\s*$/mi', '', $filteredSql);
                        $filteredSql = preg_replace('/^\s*FLUSH\s+PRIVILEGES\s*;\s*$/mi', '', $filteredSql);
                        
                        // Execute remaining statements
                        $statements = explode(';', $filteredSql);
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if ($statement === '') {
                                continue;
                            }
                            try {
                                $pdo->exec($statement);
                            } catch (PDOException $e) {
                                $message = $e->getMessage();
                                // Ignore idempotency errors like "already exists"
                                if (preg_match('/already exists|exists/i', $message)) {
                                    continue;
                                }
                                throw $e;
                            }
                        }
                    }
                    
                    // Create config file
                    $config_content = "<?php
// CatControl Database Configuration
// Generated by install.php on " . date('Y-m-d H:i:s') . "

return [
    'host' => '{$db_host}',
    'database' => '{$db_name}',
    'username' => '{$db_user}',
    'password' => '{$db_password}',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
    'smtp' => null
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
                    
                    echo '<div class="success">
                        ✅ <strong>Installation erfolgreich!</strong><br>
                        • Datenbank wurde verbunden' . (!$usersTableExists ? ' und initialisiert' : '') . '<br>
                        • Konfigurationsdatei wurde erstellt<br>
                        • Upload-Verzeichnisse wurden erstellt<br>
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
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? $envDbHost, ENT_QUOTES); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Datenbankname:</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? $envDbName, ENT_QUOTES); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Datenbank-Benutzer:</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? $envDbUser, ENT_QUOTES); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_password">Datenbank-Passwort:</label>
                    <input type="password" id="db_password" name="db_password" value="<?php echo htmlspecialchars($_POST['db_password'] ?? $envDbPassword, ENT_QUOTES); ?>" required>
                    <small>Passwort für den Datenbankbenutzer 'phpuser'</small>
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