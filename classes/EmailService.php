<?php

require_once __DIR__ . '/Database.php';

class EmailService {
    private $config;
    
    public function __construct() {
        $db = Database::getInstance();
        $this->config = $db->getConfig('smtp');
    }
    
    public function sendEmail($to, $subject, $message, $isHtml = true) {
        if (empty($this->config['host']) || empty($this->config['username'])) {
            error_log("SMTP not configured, cannot send email");
            return false;
        }
        
        try {
            // Create headers
            $headers = [];
            $headers[] = "MIME-Version: 1.0";
            $headers[] = $isHtml ? "Content-type: text/html; charset=UTF-8" : "Content-type: text/plain; charset=UTF-8";
            $headers[] = "From: {$this->config['from_name']} <{$this->config['from_email']}>";
            $headers[] = "Reply-To: {$this->config['from_email']}";
            $headers[] = "X-Mailer: CatControl";
            
            // Use PHPMailer if available, otherwise fall back to mail()
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendWithPHPMailer($to, $subject, $message, $isHtml);
            } else {
                // Simple fallback using mail() function
                $headerString = implode("\r\n", $headers);
                return mail($to, $subject, $message, $headerString);
            }
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendWithPHPMailer($to, $subject, $message, $isHtml) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port = $this->config['port'];
            $mail->CharSet = 'UTF-8';
            
            // Recipients
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendAppointmentReminder($userEmail, $username, $kittenName, $reminderType, $reminderDate) {
        $reminderTypes = [
            'vaccination' => 'Impfung',
            'deworming' => 'Entwurmung',
            'tick_protection' => 'Zeckenschutz',
            'vet_visit' => 'Tierarztbesuch'
        ];
        
        $reminderText = $reminderTypes[$reminderType] ?? $reminderType;
        
        $subject = "CatControl - Erinnerung: $reminderText für $kittenName";
        $message = "
            <h2>Terminenrinnerung</h2>
            <p>Hallo $username,</p>
            <p>Dies ist eine Erinnerung für Ihr Kätzchen <strong>$kittenName</strong>:</p>
            <p><strong>$reminderText</strong> ist fällig am: <strong>" . date('d.m.Y', strtotime($reminderDate)) . "</strong></p>
            <p>Bitte loggen Sie sich in CatControl ein, um weitere Details zu sehen.</p>
            <br>
            <p>Ihr CatControl Team</p>
        ";
        
        return $this->sendEmail($userEmail, $subject, $message);
    }
    
    public function sendKittenShareNotification($userEmail, $username, $kittenName, $sharedBy) {
        $subject = "CatControl - Kätzchen wurde mit Ihnen geteilt";
        $message = "
            <h2>Kätzchen geteilt</h2>
            <p>Hallo $username,</p>
            <p><strong>$sharedBy</strong> hat das Kätzchen <strong>$kittenName</strong> mit Ihnen geteilt.</p>
            <p>Sie können jetzt auf die Daten dieses Kätzchens zugreifen und Fütterungen sowie Tierarztbesuche erfassen.</p>
            <p>Loggen Sie sich in CatControl ein, um das Kätzchen zu sehen.</p>
            <br>
            <p>Ihr CatControl Team</p>
        ";
        
        return $this->sendEmail($userEmail, $subject, $message);
    }
    
    public function testEmailConfiguration() {
        if (empty($this->config['host']) || empty($this->config['username'])) {
            return ['success' => false, 'message' => 'SMTP-Konfiguration unvollständig'];
        }
        
        $testMessage = "
            <h2>CatControl E-Mail Test</h2>
            <p>Dies ist eine Test-E-Mail von CatControl.</p>
            <p>Ihre E-Mail-Konfiguration funktioniert korrekt!</p>
            <p>Gesendet am: " . date('d.m.Y H:i:s') . "</p>
        ";
        
        $result = $this->sendEmail(
            $this->config['from_email'],
            'CatControl - E-Mail Test',
            $testMessage
        );
        
        if ($result) {
            return ['success' => true, 'message' => 'Test-E-Mail erfolgreich gesendet'];
        } else {
            return ['success' => false, 'message' => 'Fehler beim Senden der Test-E-Mail'];
        }
    }
}