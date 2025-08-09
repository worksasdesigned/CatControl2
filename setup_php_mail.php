<?php
declare(strict_types=1);

// Setup-Script zur nachträglichen Installation von PHPMailer basierend auf config/database.php

header('Content-Type: text/html; charset=UTF-8');

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$projectRoot = __DIR__;
$configPath = __DIR__ . '/config/database.php';

$status = [];

// 1) Config laden
if (!file_exists($configPath)) {
    $status[] = '❌ Konfiguration nicht gefunden: ' . e($configPath);
    echo implode('<br>', $status);
    exit;
}

$config = include $configPath;
$smtp = $config['smtp'] ?? [];

// 2) composer / PHPMailer installieren
try {
    $canShell = function_exists('shell_exec') && stripos((string)ini_get('disable_functions'), 'shell_exec') === false;
    if (!$canShell) {
        $status[] = '⚠️ PHP shell_exec() ist deaktiviert. Bitte aktivieren Sie es oder installieren Sie PHPMailer manuell: "composer require phpmailer/phpmailer:^6.9"';
    } else {
        // Bereits vorhanden?
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $composerCmd = trim((string)@shell_exec('command -v composer'));
            if (empty($composerCmd)) {
                // composer.phar lokal bereitstellen
                $installerPath = $projectRoot . '/composer-setup.php';
                $installerCode = @file_get_contents('https://getcomposer.org/installer');
                if ($installerCode !== false) {
                    @file_put_contents($installerPath, $installerCode);
                    @shell_exec('php ' . escapeshellarg($installerPath) . ' --install-dir=' . escapeshellarg($projectRoot) . ' --filename=composer.phar 2>&1');
                    @unlink($installerPath);
                    $composerCmd = 'php ' . escapeshellarg($projectRoot . '/composer.phar');
                } else {
                    // Fallback via curl
                    @shell_exec('curl -sS https://getcomposer.org/installer | php');
                    if (file_exists($projectRoot . '/composer.phar')) {
                        $composerCmd = 'php ' . escapeshellarg($projectRoot . '/composer.phar');
                    }
                }
            }

            if (!empty($composerCmd)) {
                chdir($projectRoot);
                $output = @shell_exec($composerCmd . ' require phpmailer/phpmailer:^6.9 --no-interaction --no-dev 2>&1');
                $status[] = '🧰 Composer-Ausgabe: <pre>' . e((string)$output) . '</pre>';
            } else {
                $status[] = '⚠️ Composer nicht verfügbar. Bitte installieren Sie Composer auf dem Server oder führen Sie: "php composer.phar require phpmailer/phpmailer:^6.9" im Projektverzeichnis aus.';
            }
        }

        if (file_exists($projectRoot . '/vendor/autoload.php')) {
            $status[] = '✅ PHPMailer ist installiert (vendor/autoload.php vorhanden)';
        } else {
            $status[] = '❌ PHPMailer nicht gefunden. Installation fehlgeschlagen oder nicht ausgeführt.';
        }
    }
} catch (Throwable $t) {
    $status[] = '❌ Fehler bei der PHPMailer-Installation: ' . e($t->getMessage());
}

// 3) SMTP Test optional durchführen
$testInfo = [];
$testInfo[] = 'SMTP Host: ' . e((string)($smtp['host'] ?? '')); 
$testInfo[] = 'SMTP User: ' . e((string)($smtp['username'] ?? '')); 
$testInfo[] = 'From: ' . e((string)($smtp['from_email'] ?? '')) . ' (' . e((string)($smtp['from_name'] ?? '')) . ')';

$canTest = !empty($smtp['host']) && !empty($smtp['username']) && !empty($smtp['from_email']);
if ($canTest && file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/EmailService.php';
    try {
        $emailService = new EmailService();
        $result = $emailService->testEmailConfiguration();
        $status[] = $result['success'] ? '✅ Test-E-Mail erfolgreich gesendet' : '❌ Test-E-Mail fehlgeschlagen: ' . e($result['message'] ?? 'Unbekannter Fehler');
    } catch (Throwable $t) {
        $status[] = '❌ Fehler beim Senden der Testmail: ' . e($t->getMessage());
    }
} else {
    $status[] = 'ℹ️ SMTP-Test übersprungen (unvollständige SMTP-Konfiguration oder PHPMailer nicht installiert).';
}

// 4) Ausgabe
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CatControl - PHPMailer Setup</title>
<style>
body{font-family:Arial, sans-serif; background:#f5f5f5; padding:20px}
.container{max-width:900px;margin:0 auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
h1{margin-top:0}
pre{white-space:pre-wrap;word-break:break-word;background:#f0f0f0;padding:10px;border-radius:6px}
.badge{display:inline-block;padding:4px 8px;border-radius:4px;background:#e9ecef;margin-right:6px}
</style>
</head>
<body>
<div class="container">
    <h1>🐱 PHPMailer Setup</h1>
    <h3>Status</h3>
    <div><?php echo implode('<br>', $status); ?></div>
    <h3>SMTP-Infos</h3>
    <div>
        <?php foreach ($testInfo as $line) { echo '<span class="badge">' . $line . '</span>'; } ?>
    </div>
    <p>
        <strong>Hinweis:</strong> Wenn die Installation nicht automatisch klappt, führen Sie im Projektverzeichnis folgenden Befehl aus:
    </p>
    <pre>composer require phpmailer/phpmailer:^6.9</pre>
</div>
</body>
</html>