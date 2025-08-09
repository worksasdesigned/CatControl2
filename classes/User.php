<?php

require_once __DIR__ . '/Database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($username, $email, $password, $country, $city = null, $allowMessages = true) {
        // Check if username exists
        if ($this->getUserByUsername($username)) {
            return ['success' => false, 'message' => __('user.register.username_taken') ?? 'Benutzername bereits vergeben'];
        }
        
        // Email optional: Verwende Dummy/Null; Prüfe nur, wenn gesetzt
        if (!empty($email) && $this->getUserByEmail($email)) {
            return ['success' => false, 'message' => __('user.register.email_taken') ?? 'E-Mail-Adresse bereits registriert'];
        }
        
        // Validate password
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => __('user.password.too_short') ?? 'Passwort muss mindestens 8 Zeichen lang sein'];
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Defaults für entfernte Felder
        $emailToStore = $email ?: ($username . '@local');
        $countryToStore = $country ?: 'n/a';
        $cityToStore = $city ?: null;
        
        $sql = "INSERT INTO users (username, email, password_hash, country, city, allow_messages, first_login) 
                VALUES (?, ?, ?, ?, ?, ?, 0)";
        
        try {
            $this->db->execute($sql, [$username, $emailToStore, $passwordHash, $countryToStore, $cityToStore, $allowMessages ? 1 : 0]);
            return ['success' => true, 'message' => __('user.register.success') ?? 'Registrierung erfolgreich'];
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => __('user.register.failed') ?? 'Registrierung fehlgeschlagen'];
        }
    }
    
    public function login($username, $password) {
        $user = $this->getUserByUsername($username);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => __('user.login.invalid_credentials') ?? 'Ungültige Anmeldedaten'];
        }
        
        // Start session and store user data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'] ?? null;
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
            return ['success' => false, 'message' => __('user.password.too_short') ?? 'Passwort muss mindestens 8 Zeichen lang sein'];
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ?, first_login = 0 WHERE id = ?";
        
        try {
            $this->db->execute($sql, [$passwordHash, $userId]);
            return ['success' => true, 'message' => __('user.password.changed') ?? 'Passwort erfolgreich geändert'];
        } catch (Exception $e) {
            error_log("Password update error: " . $e->getMessage());
            return ['success' => false, 'message' => __('user.password.change_failed') ?? 'Passwort konnte nicht geändert werden'];
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
            return ['success' => false, 'message' => __('profile.update_failed') ?? 'Profil konnte nicht aktualisiert werden'];
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
    
    // Neues Reset-Verfahren ohne E-Mail-Token
    public function verifyUserWithKitten(string $username, string $kittenName): ?array {
        $user = $this->getUserByUsername($username);
        if (!$user) return null;
        // Prüfe Besitz oder geteilten Zugriff
        $sql = "SELECT COUNT(*) AS cnt FROM kittens k WHERE k.name = ? AND (k.owner_id = ? OR k.id IN (SELECT kitten_id FROM kitten_users WHERE user_id = ?))";
        $row = $this->db->fetch($sql, [$kittenName, $user['id'], $user['id']]);
        if (!empty($row) && (int)$row['cnt'] > 0) {
            return $user;
        }
        return null;
    }
    
    public function setNewPasswordByUsername(string $username, string $newPassword): array {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => __('user.password.too_short') ?? 'Passwort muss mindestens 8 Zeichen lang sein'];
        }
        $user = $this->getUserByUsername($username);
        if (!$user) {
            return ['success' => false, 'message' => __('reset_password.validation_failed') ?? 'Benutzer oder Kätzchenzuordnung nicht gefunden'];
        }
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        try {
            $this->db->execute("UPDATE users SET password_hash = ?, first_login = 0 WHERE id = ?", [$passwordHash, $user['id']]);
            return ['success' => true, 'message' => __('user.password.changed') ?? 'Passwort erfolgreich geändert'];
        } catch (Exception $e) {
            error_log('setNewPasswordByUsername error: ' . $e->getMessage());
            return ['success' => false, 'message' => __('user.password.change_failed') ?? 'Passwort konnte nicht geändert werden'];
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