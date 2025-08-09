<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class MessageService {
    private $db;
    private $userService;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userService = new User();
    }
    
    public function sendMessage($senderId, $recipientId, $subject, $content, $messageType = 'user_message') {
        // Check if sender is blocked by recipient
        if ($this->userService->isUserBlocked($recipientId, $senderId)) {
            return ['success' => false, 'message' => __('messages.cannot_send') ?? 'Sie können diesem Benutzer keine Nachrichten senden'];
        }
        
        $sql = "INSERT INTO messages (sender_id, recipient_id, subject, content, message_type) 
                VALUES (?, ?, ?, ?, ?)";
        
        try {
            $this->db->execute($sql, [$senderId, $recipientId, $subject, $content, $messageType]);
            return ['success' => true, 'message' => 'Nachricht erfolgreich gesendet'];
        } catch (Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Nachricht konnte nicht gesendet werden'];
        }
    }
    
    public function sendSystemNotification($recipientId, $subject, $content) {
        return $this->sendMessage(null, $recipientId, $subject, $content, 'system_notification');
    }
    
    public function sendAppointmentReminder($recipientId, $subject, $content) {
        return $this->sendMessage(null, $recipientId, $subject, $content, 'appointment_reminder');
    }
    
    public function getUserMessages($userId, $limit = 50, $offset = 0) {
        $sql = "SELECT m.*, u.username as sender_username 
                FROM messages m 
                LEFT JOIN users u ON m.sender_id = u.id 
                WHERE m.recipient_id = ? 
                ORDER BY m.created_at DESC 
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }
    
    public function getUnreadMessageCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return $result['count'];
    }
    
    public function markMessageAsRead($messageId, $userId) {
        $sql = "UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?";
        
        try {
            $this->db->execute($sql, [$messageId, $userId]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Mark message as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Nachricht konnte nicht als gelesen markiert werden'];
        }
    }
    
    public function markAllMessagesAsRead($userId) {
        $sql = "UPDATE messages SET is_read = 1 WHERE recipient_id = ?";
        
        try {
            $this->db->execute($sql, [$userId]);
            return ['success' => true, 'message' => 'Alle Nachrichten als gelesen markiert'];
        } catch (Exception $e) {
            error_log("Mark all messages as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Nachrichten konnten nicht als gelesen markiert werden'];
        }
    }
    
    public function deleteMessage($messageId, $userId) {
        $sql = "DELETE FROM messages WHERE id = ? AND recipient_id = ?";
        
        try {
            $result = $this->db->execute($sql, [$messageId, $userId]);
            if ($result > 0) {
                return ['success' => true, 'message' => 'Nachricht gelöscht'];
            } else {
                return ['success' => false, 'message' => 'Nachricht nicht gefunden'];
            }
        } catch (Exception $e) {
            error_log("Delete message error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Nachricht konnte nicht gelöscht werden'];
        }
    }
    
    public function getMessage($messageId, $userId) {
        $sql = "SELECT m.*, u.username as sender_username 
                FROM messages m 
                LEFT JOIN users u ON m.sender_id = u.id 
                WHERE m.id = ? AND m.recipient_id = ?";
        
        return $this->db->fetch($sql, [$messageId, $userId]);
    }
    
    public function replyToMessage($messageId, $userId, $replyContent) {
        // Get original message
        $originalMessage = $this->getMessage($messageId, $userId);
        if (!$originalMessage || !$originalMessage['sender_id']) {
            return ['success' => false, 'message' => 'Auf diese Nachricht kann nicht geantwortet werden'];
        }
        
        $subject = "Re: " . $originalMessage['subject'];
        $content = $replyContent . "\n\n--- Ursprüngliche Nachricht ---\n" . $originalMessage['content'];
        
        return $this->sendMessage($userId, $originalMessage['sender_id'], $subject, $content);
    }
    
    public function getMessageThread($userId, $otherUserId, $limit = 20) {
        $sql = "SELECT m.*, u.username as sender_username 
                FROM messages m 
                LEFT JOIN users u ON m.sender_id = u.id 
                WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
                AND m.message_type = 'user_message'
                ORDER BY m.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$userId, $otherUserId, $otherUserId, $userId, $limit]);
    }
    
    public function cleanupOldMessages($daysOld = 90) {
        $sql = "DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = 1";
        
        try {
            $result = $this->db->execute($sql, [$daysOld]);
            return ['success' => true, 'message' => "$result alte Nachrichten gelöscht"];
        } catch (Exception $e) {
            error_log("Cleanup messages error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Fehler beim Löschen alter Nachrichten'];
        }
    }
    
    public function getMessageStatistics($userId) {
        $stats = [];
        
        // Total messages
        $sql = "SELECT COUNT(*) as count FROM messages WHERE recipient_id = ?";
        $result = $this->db->fetch($sql, [$userId]);
        $stats['total_messages'] = $result['count'];
        
        // Unread messages
        $sql = "SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0";
        $result = $this->db->fetch($sql, [$userId]);
        $stats['unread_messages'] = $result['count'];
        
        // Messages by type
        $sql = "SELECT message_type, COUNT(*) as count 
                FROM messages 
                WHERE recipient_id = ? 
                GROUP BY message_type";
        $results = $this->db->fetchAll($sql, [$userId]);
        
        $stats['by_type'] = [];
        foreach ($results as $row) {
            $stats['by_type'][$row['message_type']] = $row['count'];
        }
        
        return $stats;
    }
    
    // Create appointment reminders based on veterinary records
    public function createAppointmentReminders() {
        $sql = "SELECT vr.*, k.name as kitten_name, k.owner_id, u.email, u.username
                FROM veterinary_records vr
                JOIN kittens k ON vr.kitten_id = k.id
                JOIN users u ON k.owner_id = u.id
                WHERE (
                    (vr.next_vaccination_date IS NOT NULL AND vr.next_vaccination_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY)) OR
                    (vr.next_visit_date IS NOT NULL AND vr.next_visit_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY))
                )";
        
        $upcomingAppointments = $this->db->fetchAll($sql);
        $remindersCreated = 0;
        
        foreach ($upcomingAppointments as $appointment) {
            // Check if reminder already exists
            $checkSql = "SELECT COUNT(*) as count FROM reminder_notifications 
                        WHERE kitten_id = ? AND user_id = ? AND reminder_date = ? AND sent = 0";
            
            $reminderDate = $appointment['next_vaccination_date'] ?: $appointment['next_visit_date'];
            $result = $this->db->fetch($checkSql, [$appointment['kitten_id'], $appointment['owner_id'], $reminderDate]);
            
            if ($result['count'] == 0) {
                // Create reminder notification
                $reminderType = $appointment['next_vaccination_date'] ? 'vaccination' : 'vet_visit';
                $reminderText = $appointment['next_vaccination_date'] ? 'Impfung' : 'Tierarztbesuch';
                
                $subject = "Erinnerung: $reminderText für " . $appointment['kitten_name'];
                $content = "Ihr Kätzchen " . $appointment['kitten_name'] . " hat in 3 Tagen einen Termin für: $reminderText\n\n";
                $content .= "Datum: " . date('d.m.Y', strtotime($reminderDate)) . "\n";
                if ($appointment['veterinarian_name']) {
                    $content .= "Tierarzt: " . $appointment['veterinarian_name'] . "\n";
                }
                
                // Interne Nachricht senden
                $this->sendAppointmentReminder($appointment['owner_id'], $subject, $content);
                
                // Create reminder record
                $insertSql = "INSERT INTO reminder_notifications (kitten_id, user_id, reminder_type, reminder_date) 
                             VALUES (?, ?, ?, ?)";
                $this->db->execute($insertSql, [$appointment['kitten_id'], $appointment['owner_id'], $reminderType, $reminderDate]);
                
                $remindersCreated++;
            }
        }
        
        return $remindersCreated;
    }
    
    // Process deworming and tick protection reminders
    public function createMedicationReminders() {
        $intervalMap = [
            '1_week' => 7,
            '2_weeks' => 14,
            '4_weeks' => 28,
            '2_months' => 60,
            '3_months' => 90,
            '4_months' => 120,
            '6_months' => 180,
            '1_year' => 365
        ];
        
        $sql = "SELECT vr.*, k.name as kitten_name, k.owner_id, u.email, u.username
                FROM veterinary_records vr
                JOIN kittens k ON vr.kitten_id = k.id
                JOIN users u ON k.owner_id = u.id
                WHERE vr.next_deworming_interval IS NOT NULL OR vr.next_tick_protection_interval IS NOT NULL
                ORDER BY vr.visit_date DESC";
        
        $records = $this->db->fetchAll($sql);
        $remindersCreated = 0;
        
        foreach ($records as $record) {
            // Process deworming reminders
                            if ($record['next_deworming_interval'] && isset($intervalMap[$record['next_deworming_interval']])) {
                    $nextDate = date('Y-m-d', strtotime($record['visit_date'] . ' +' . $intervalMap[$record['next_deworming_interval']] . ' days'));
                    $reminderDate = date('Y-m-d', strtotime($nextDate . ' -3 days'));
                    
                    if ($reminderDate == date('Y-m-d')) {
                        $subject = "Erinnerung: Entwurmung für " . $record['kitten_name'];
                        $content = "Ihr Kätzchen " . $record['kitten_name'] . " sollte in 3 Tagen entwurmt werden.\n\n";
                        $content .= "Geplantes Datum: " . date('d.m.Y', strtotime($nextDate)) . "\n";
                        if ($record['deworming_medication']) {
                            $content .= "Medikament: " . $record['deworming_medication'] . "\n";
                        }
                        
                        $this->sendAppointmentReminder($record['owner_id'], $subject, $content);
                        $remindersCreated++;
                    }
                }
            
            // Process tick protection reminders
            if ($record['next_tick_protection_interval'] && isset($intervalMap[$record['next_tick_protection_interval']])) {
                $nextDate = date('Y-m-d', strtotime($record['visit_date'] . ' +' . $intervalMap[$record['next_tick_protection_interval']] . ' days'));
                $reminderDate = date('Y-m-d', strtotime($nextDate . ' -3 days'));
                
                if ($reminderDate == date('Y-m-d')) {
                    $subject = "Erinnerung: Zeckenschutz für " . $record['kitten_name'];
                    $content = "Ihr Kätzchen " . $record['kitten_name'] . " benötigt in 3 Tagen neuen Zeckenschutz.\n\n";
                    $content .= "Geplantes Datum: " . date('d.m.Y', strtotime($nextDate)) . "\n";
                    if ($record['tick_protection_medication']) {
                        $content .= "Medikament: " . $record['tick_protection_medication'] . "\n";
                    }
                    
                    $this->sendAppointmentReminder($record['owner_id'], $subject, $content);
                    $remindersCreated++;
                }
            }
        }
        
        return $remindersCreated;
    }
}