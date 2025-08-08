<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailService.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($username, $email, $password, $country, $city = null, $allowMessages = true) {
        // Check if username exists
        if ($this->getUserByUsername($username)) {
            return ['success' => false, 'message' => 'Benutzername bereits vergeben'];
        }
        
        // Check if email exists
        if ($this->getUserByEmail($email)) {
            return ['success' => false, 'message' => 'E-Mail-Adresse bereits registriert'];
        }
        
        // Validate password
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein'];
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, country, city, allow_messages) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $this->db->execute($sql, [$username, $email, $passwordHash, $country, $city, $allowMessages ? 1 : 0]);
            return ['success' => true, 'message' => 'Registrierung erfolgreich'];
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registrierung fehlgeschlagen'];
        }
    }
    
    public function login($username, $password) {
        $user = $this->getUserByUsername($username);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Ungültige Anmeldedaten'];
        }
        
        // Start session and store user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_login'] = $user['first_login'];
        
        return ['success' => true, 'user' => $user, 'first_login' => $user['first_login']];
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }
    
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->getUserById($_SESSION['user_id']);
    }
    
    public function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function getUserByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ?";
        return $this->db->fetch($sql, [$username]);
    }
    
    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        return $this->db->fetch($sql, [$email]);
    }
    
    public function updatePassword($userId, $newPassword) {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein'];
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ?, first_login = 0 WHERE id = ?";
        
        try {
            $this->db->execute($sql, [$passwordHash, $userId]);
            return ['success' => true, 'message' => 'Passwort erfolgreich geändert'];
        } catch (Exception $e) {
            error_log("Password update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Passwort konnte nicht geändert werden'];
        }
    }
    
    public function updateProfile($userId, $data) {
        $allowedFields = ['email', 'country', 'city', 'allow_messages', 'custom_background'];
        $updateFields = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            return ['success' => false, 'message' => 'Keine gültigen Felder zum Aktualisieren'];
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        try {
            $this->db->execute($sql, $params);
            return ['success' => true, 'message' => 'Profil erfolgreich aktualisiert'];
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Profil konnte nicht aktualisiert werden'];
        }
    }

    // Ergänzung: Allgemeine Update-Methode, wie sie in profile.php erwartet wird
    public function updateUser($userId, array $data) {
        $allowedFields = ['username', 'email', 'country', 'city', 'allow_messages', 'custom_background'];
        $updateFields = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields, true)) {
                $updateFields[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("updateUser error: " . $e->getMessage());
            return false;
        }
    }
    
    public function requestPasswordReset($email) {
        $user = $this->getUserByEmail($email);
        if (!$user) {
            // Don't reveal if email exists or not
            return ['success' => true, 'message' => 'Falls die E-Mail-Adresse registriert ist, wurde eine E-Mail gesendet'];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
        
        try {
            $this->db->execute($sql, [$user['id'], $token, $expiresAt]);
            
            // Send email
            $emailService = new EmailService();
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
            
            $subject = "CatControl - Passwort zurücksetzen";
            $message = "
                <h2>Passwort zurücksetzen</h2>
                <p>Hallo {$user['username']},</p>
                <p>Sie haben eine Passwort-Zurücksetzung für Ihr CatControl-Konto angefordert.</p>
                <p>Klicken Sie auf den folgenden Link, um Ihr Passwort zurückzusetzen:</p>
                <p><a href='$resetLink'>Passwort zurücksetzen</a></p>
                <p>Dieser Link ist 1 Stunde gültig.</p>
                <p>Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.</p>
                <br>
                <p>Ihr CatControl Team</p>
            ";
            
            $emailService->sendEmail($user['email'], $subject, $message);
            
            return ['success' => true, 'message' => 'Falls die E-Mail-Adresse registriert ist, wurde eine E-Mail gesendet'];
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Fehler beim Senden der E-Mail'];
        }
    }
    
    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein'];
        }
        
        $sql = "SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() AND used = 0";
        $resetToken = $this->db->fetch($sql, [$token]);
        
        if (!$resetToken) {
            return ['success' => false, 'message' => 'Ungültiger oder abgelaufener Token'];
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $this->db->beginTransaction();
            
            // Update password
            $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
            $this->db->execute($sql, [$passwordHash, $resetToken['user_id']]);
            
            // Mark token as used
            $sql = "UPDATE password_reset_tokens SET used = 1 WHERE id = ?";
            $this->db->execute($sql, [$resetToken['id']]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Passwort erfolgreich zurückgesetzt'];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Fehler beim Zurücksetzen des Passworts'];
        }
    }
    
    public function getFieldPreferences($userId) {
        $sql = "SELECT field_name, visible FROM user_field_preferences WHERE user_id = ?";
        $preferences = $this->db->fetchAll($sql, [$userId]);
        
        $result = [];
        foreach ($preferences as $pref) {
            $result[$pref['field_name']] = (bool)$pref['visible'];
        }
        
        return $result;
    }
    
    public function updateFieldPreference($userId, $fieldName, $visible) {
        $sql = "INSERT INTO user_field_preferences (user_id, field_name, visible) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE visible = ?";
        
        try {
            $this->db->execute($sql, [$userId, $fieldName, $visible ? 1 : 0, $visible ? 1 : 0]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Field preference update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Einstellung konnte nicht gespeichert werden'];
        }
    }
    
    public function getAllUsers($excludeUserId = null, $onlyAllowMessages = false) {
        $sql = "SELECT id, username, email, country, city, allow_messages FROM users";
        $params = [];
        $conditions = [];
        
        if ($excludeUserId) {
            $conditions[] = "id != ?";
            $params[] = $excludeUserId;
        }
        
        if ($onlyAllowMessages) {
            $conditions[] = "allow_messages = 1";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY username";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function blockUser($userId, $blockedUserId) {
        $sql = "INSERT IGNORE INTO user_blacklist (user_id, blocked_user_id) VALUES (?, ?)";
        
        try {
            $this->db->execute($sql, [$userId, $blockedUserId]);
            return ['success' => true, 'message' => 'Benutzer wurde blockiert'];
        } catch (Exception $e) {
            error_log("Block user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Benutzer konnte nicht blockiert werden'];
        }
    }
    
    public function unblockUser($userId, $blockedUserId) {
        $sql = "DELETE FROM user_blacklist WHERE user_id = ? AND blocked_user_id = ?";
        
        try {
            $this->db->execute($sql, [$userId, $blockedUserId]);
            return ['success' => true, 'message' => 'Benutzer wurde entsperrt'];
        } catch (Exception $e) {
            error_log("Unblock user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Benutzer konnte nicht entsperrt werden'];
        }
    }
    
    public function getBlockedUsers($userId) {
        $sql = "SELECT u.id, u.username, u.email, bl.blocked_at 
                FROM user_blacklist bl 
                JOIN users u ON bl.blocked_user_id = u.id 
                WHERE bl.user_id = ? 
                ORDER BY bl.blocked_at DESC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    public function isUserBlocked($userId, $otherUserId) {
        $sql = "SELECT COUNT(*) as count FROM user_blacklist WHERE user_id = ? AND blocked_user_id = ?";
        $result = $this->db->fetch($sql, [$userId, $otherUserId]);
        return $result['count'] > 0;
    }
}