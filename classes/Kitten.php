<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailService.php';

class Kitten {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function createKitten($ownerId, $data) {
        $requiredFields = ['name', 'birth_date'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => 'Pflichtfelder fehlen'];
            }
        }
        
        $sql = "INSERT INTO kittens (owner_id, name, birth_date, color, mother, found_location, 
                found_date, tasso_id, ear_tattoo, postal_code) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $this->db->execute($sql, [
                $ownerId,
                $data['name'],
                $data['birth_date'],
                $data['color'] ?? null,
                $data['mother'] ?? null,
                $data['found_location'] ?? null,
                $data['found_date'] ?? null,
                $data['tasso_id'] ?? null,
                $data['ear_tattoo'] ?? null,
                $data['postal_code'] ?? null
            ]);
            
            $kittenId = $this->db->lastInsertId();
            
            return ['success' => true, 'message' => 'Kätzchen erfolgreich angelegt', 'kitten_id' => $kittenId];
        } catch (Exception $e) {
            error_log("Create kitten error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Kätzchen konnte nicht angelegt werden'];
        }
    }
    
    public function updateKitten($kittenId, $userId, $data) {
        // Check if user has access to this kitten
        if (!$this->hasAccess($kittenId, $userId)) {
            return ['success' => false, 'message' => 'Keine Berechtigung'];
        }
        
        $allowedFields = ['name', 'birth_date', 'color', 'mother', 'found_location', 
                         'found_date', 'tasso_id', 'ear_tattoo', 'postal_code', 'is_public'];
        
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
        
        $params[] = $kittenId;
        $sql = "UPDATE kittens SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        try {
            $this->db->execute($sql, $params);
            return ['success' => true, 'message' => 'Kätzchen erfolgreich aktualisiert'];
        } catch (Exception $e) {
            error_log("Update kitten error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Kätzchen konnte nicht aktualisiert werden'];
        }
    }
    
    public function deleteKitten($kittenId, $userId) {
        // Check if user is the owner
        $kitten = $this->getKittenById($kittenId);
        if (!$kitten || $kitten['owner_id'] != $userId) {
            return ['success' => false, 'message' => 'Keine Berechtigung'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Delete all related data (CASCADE should handle this, but let's be explicit)
            $this->db->execute("DELETE FROM feeding_records WHERE kitten_id = ?", [$kittenId]);
            $this->db->execute("DELETE FROM veterinary_records WHERE kitten_id = ?", [$kittenId]);
            $this->db->execute("DELETE FROM kitten_images WHERE kitten_id = ?", [$kittenId]);
            $this->db->execute("DELETE FROM kitten_users WHERE kitten_id = ?", [$kittenId]);
            $this->db->execute("DELETE FROM reminder_notifications WHERE kitten_id = ?", [$kittenId]);
            
            // Delete the kitten
            $this->db->execute("DELETE FROM kittens WHERE id = ?", [$kittenId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Kätzchen und alle zugehörigen Daten wurden gelöscht'];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Delete kitten error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Kätzchen konnte nicht gelöscht werden'];
        }
    }
    
    public function getKittenById($kittenId) {
        $sql = "SELECT * FROM kittens WHERE id = ?";
        return $this->db->fetch($sql, [$kittenId]);
    }
    
    public function getUserKittens($userId) {
        $sql = "SELECT k.*, 
                       COALESCE(
                            (SELECT filename FROM kitten_images WHERE kitten_id = k.id AND is_profile_image = 1 LIMIT 1),
                            (SELECT filename FROM kitten_images WHERE kitten_id = k.id ORDER BY upload_date DESC LIMIT 1)
                        ) as profile_image
                 FROM kittens k 
                 WHERE k.owner_id = ? 
                    OR k.id IN (SELECT kitten_id FROM kitten_users WHERE user_id = ?)
                 ORDER BY k.created_at DESC";
         
         return $this->db->fetchAll($sql, [$userId, $userId]);
     }
    
    public function getPublicKittens($limit = 20, $offset = 0) {
        $sql = "SELECT k.*, u.username as owner_username,
                       COALESCE(
                            (SELECT filename FROM kitten_images WHERE kitten_id = k.id AND is_profile_image = 1 LIMIT 1),
                            (SELECT filename FROM kitten_images WHERE kitten_id = k.id ORDER BY upload_date DESC LIMIT 1)
                        ) as profile_image
                FROM kittens k 
                JOIN users u ON k.owner_id = u.id
                WHERE k.is_public = 1 
                ORDER BY k.created_at DESC 
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$limit, $offset]);
    }
    
    public function hasAccess($kittenId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM kittens k 
                WHERE k.id = ? AND (k.owner_id = ? OR k.id IN (
                    SELECT kitten_id FROM kitten_users WHERE user_id = ?
                ))";
        
        $result = $this->db->fetch($sql, [$kittenId, $userId, $userId]);
        return $result['count'] > 0;
    }
    
    public function shareKitten($kittenId, $ownerId, $shareWithUserId) {
        // Check if user is the owner
        $kitten = $this->getKittenById($kittenId);
        if (!$kitten || $kitten['owner_id'] != $ownerId) {
            return ['success' => false, 'message' => 'Keine Berechtigung'];
        }
        
        // Check if already shared
        $sql = "SELECT COUNT(*) as count FROM kitten_users WHERE kitten_id = ? AND user_id = ?";
        $result = $this->db->fetch($sql, [$kittenId, $shareWithUserId]);
        
        if ($result['count'] > 0) {
            return ['success' => false, 'message' => 'Kätzchen wurde bereits mit diesem Benutzer geteilt'];
        }
        
        try {
            $sql = "INSERT INTO kitten_users (kitten_id, user_id, granted_by) VALUES (?, ?, ?)";
            $this->db->execute($sql, [$kittenId, $shareWithUserId, $ownerId]);
            
            // Send notification email
            $shareWithUser = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$shareWithUserId]);
            $owner = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$ownerId]);
            
            if ($shareWithUser && $owner) {
                $emailService = new EmailService();
                $emailService->sendKittenShareNotification(
                    $shareWithUser['email'],
                    $shareWithUser['username'],
                    $kitten['name'],
                    $owner['username']
                );
            }
            
            return ['success' => true, 'message' => 'Kätzchen erfolgreich geteilt'];
        } catch (Exception $e) {
            error_log("Share kitten error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Kätzchen konnte nicht geteilt werden'];
        }
    }
    
    public function unshareKitten($kittenId, $ownerId, $unshareFromUserId) {
        // Check if user is the owner
        $kitten = $this->getKittenById($kittenId);
        if (!$kitten || $kitten['owner_id'] != $ownerId) {
            return ['success' => false, 'message' => 'Keine Berechtigung'];
        }
        
        try {
            $sql = "DELETE FROM kitten_users WHERE kitten_id = ? AND user_id = ?";
            $result = $this->db->execute($sql, [$kittenId, $unshareFromUserId]);
            
            if ($result > 0) {
                return ['success' => true, 'message' => 'Zugriff erfolgreich entfernt'];
            } else {
                return ['success' => false, 'message' => 'Kein geteilter Zugriff gefunden'];
            }
        } catch (Exception $e) {
            error_log("Unshare kitten error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Zugriff konnte nicht entfernt werden'];
        }
    }
    
    public function getSharedUsers($kittenId) {
        $sql = "SELECT u.id, u.username, u.email, ku.granted_at 
                FROM kitten_users ku 
                JOIN users u ON ku.user_id = u.id 
                WHERE ku.kitten_id = ?";
        
        return $this->db->fetchAll($sql, [$kittenId]);
    }
    
    public function getKittenAge($birthDate) {
        $birth = new DateTime($birthDate);
        $now = new DateTime();
        $diff = $now->diff($birth);
        
        $weeks = floor($diff->days / 7);
        $days = $diff->days % 7;
        
        return [
            'weeks' => $weeks,
            'days' => $days,
            'total_days' => $diff->days
        ];
    }
    
    public function getLatestWeight($kittenId) {
        $sql = "SELECT weight_grams FROM feeding_records 
                WHERE kitten_id = ? AND weight_grams IS NOT NULL 
                ORDER BY feeding_date DESC LIMIT 1";
        
        $result = $this->db->fetch($sql, [$kittenId]);
        return $result ? $result['weight_grams'] : null;
    }
    
    public function getUpcomingAppointments($kittenId) {
        $sql = "SELECT * FROM veterinary_records 
                WHERE kitten_id = ? 
                AND (
                    (next_vaccination_date IS NOT NULL AND next_vaccination_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)) OR
                    (next_visit_date IS NOT NULL AND next_visit_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY))
                )
                ORDER BY COALESCE(next_vaccination_date, next_visit_date) ASC";
        
        return $this->db->fetchAll($sql, [$kittenId]);
    }
    
    public function getKittenDevelopmentInfo($ageInDays) {
        $stages = [
            ['min' => 0, 'max' => 7, 'stage' => 'Neugeborenes', 'info' => 'Augen und Ohren geschlossen, nur Muttermilch'],
            ['min' => 8, 'max' => 14, 'stage' => 'Augen öffnen sich', 'info' => 'Augen beginnen sich zu öffnen, noch auf Muttermilch angewiesen'],
            ['min' => 15, 'max' => 21, 'stage' => 'Hören entwickelt sich', 'info' => 'Ohren öffnen sich, erste Gehversuche'],
            ['min' => 22, 'max' => 28, 'stage' => 'Erste Schritte', 'info' => 'Laufen lernen, Spielverhalten beginnt'],
            ['min' => 29, 'max' => 35, 'stage' => 'Sozialisierung', 'info' => 'Intensive Sozialisierungsphase, Katzenklo-Training möglich'],
            ['min' => 36, 'max' => 49, 'stage' => 'Entwöhnung', 'info' => 'Übergang zu festem Futter, weniger Muttermilch'],
            ['min' => 50, 'max' => 63, 'stage' => 'Selbstständigkeit', 'info' => 'Weitgehend selbstständig, bereit für neue Familien'],
            ['min' => 64, 'max' => 84, 'stage' => 'Jungtier', 'info' => 'Vollständig entwöhnt, erste Impfungen fällig'],
            ['min' => 85, 'max' => 365, 'stage' => 'Heranwachsend', 'info' => 'Regelmäßige Tierarztbesuche, Kastration planen']
        ];
        
        foreach ($stages as $stage) {
            if ($ageInDays >= $stage['min'] && $ageInDays <= $stage['max']) {
                return $stage;
            }
        }
        
        return ['stage' => 'Erwachsen', 'info' => 'Regelmäßige Gesundheitschecks und Impfungen'];
    }
    
    public function updateProfileImage($kittenId, $userId, $filename) {
        if (!$this->hasAccess($kittenId, $userId)) {
            return ['success' => false, 'message' => 'Keine Berechtigung'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Remove current profile image flag
            $this->db->execute("UPDATE kitten_images SET is_profile_image = 0 WHERE kitten_id = ?", [$kittenId]);
            
            // Set new profile image
            $this->db->execute("UPDATE kitten_images SET is_profile_image = 1 WHERE kitten_id = ? AND filename = ?", 
                             [$kittenId, $filename]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Profilbild erfolgreich aktualisiert'];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Update profile image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Profilbild konnte nicht aktualisiert werden'];
        }
    }
    
    public function searchKittens($query, $isPublicOnly = false) {
        $sql = "SELECT k.*, u.username as owner_username,
                       COALESCE(
                            (SELECT filename FROM kitten_images WHERE kitten_id = k.id AND is_profile_image = 1 LIMIT 1),
                            (SELECT filename FROM kitten_images WHERE kitten_id = k.id ORDER BY upload_date DESC LIMIT 1)
                        ) as profile_image
                 FROM kittens k 
                 JOIN users u ON k.owner_id = u.id
                 WHERE (k.name LIKE ? OR k.color LIKE ? OR k.mother LIKE ?)";
         
         if ($isPublicOnly) {
             $sql .= " AND k.is_public = 1";
         }
         
         $sql .= " ORDER BY k.name ASC";
         
         $searchTerm = "%$query%";
         return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm]);
     }

    // Feeding records API
    public function addFeedingRecord(array $feedingData) {
        $requiredKeys = ['kitten_id', 'user_id', 'date_time'];
        foreach ($requiredKeys as $key) {
            if (!isset($feedingData[$key]) || $feedingData[$key] === '' || $feedingData[$key] === null) {
                return false;
            }
        }

        // Convert and map incoming fields to DB schema
        $feedingDate = date('Y-m-d H:i:s', strtotime($feedingData['date_time']));
        $weightGrams = isset($feedingData['weight']) && $feedingData['weight'] !== '' ? (int)$feedingData['weight'] : null;
        $foodAmountGrams = isset($feedingData['food_amount']) && $feedingData['food_amount'] !== '' ? (int)$feedingData['food_amount'] : null;
        $foodType = $this->mapFoodType($feedingData['food_type'] ?? null);
        $heatingPadRefilled = isset($feedingData['heat_bottle_refilled']) ? (int)(bool)$feedingData['heat_bottle_refilled'] : 0;
        $stoolType = $this->mapStoolType($feedingData['bowel_movement'] ?? null);
        $stoolConsistency = $this->mapStoolConsistency($feedingData['stool_consistency'] ?? null);
        $stoolColor = $this->mapStoolColor($feedingData['stool_color'] ?? null);
        $stoolColorOther = $feedingData['stool_color_other'] ?? null;
        $fitnessLevel = isset($feedingData['fitness_level']) && $feedingData['fitness_level'] !== '' ? (int)$feedingData['fitness_level'] : null;
        $notes = isset($feedingData['notes']) ? trim($feedingData['notes']) : null;

        $sql = "INSERT INTO feeding_records (
                    kitten_id, user_id, feeding_date, weight_grams, food_amount_grams,
                    food_type, heating_pad_refilled, stool_type, stool_consistency,
                    stool_color, stool_color_other, fitness_level, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->db->execute($sql, [
                (int)$feedingData['kitten_id'],
                (int)$feedingData['user_id'],
                $feedingDate,
                $weightGrams,
                $foodAmountGrams,
                $foodType,
                $heatingPadRefilled,
                $stoolType,
                $stoolConsistency,
                $stoolColor,
                $stoolColorOther,
                $fitnessLevel,
                $notes
            ]);
            return true;
        } catch (Exception $e) {
            error_log('addFeedingRecord error: ' . $e->getMessage());
            return false;
        }
    }

         public function getFeedingRecords(int $kittenId, int $limit = 20) {
         $params = [$kittenId];
         $limitSql = '';
         if ($limit > 0) {
             $limitSql = ' LIMIT ' . (int)$limit;
         }

         $sql = "SELECT 
                     id,
                     feeding_date AS date_time,
                     weight_grams AS weight,
                     food_amount_grams AS food_amount,
                     food_type,
                     fitness_level
                 FROM feeding_records
                 WHERE kitten_id = ?
                 ORDER BY feeding_date DESC" . $limitSql;
         
         try {
             return $this->db->fetchAll($sql, $params);
         } catch (Exception $e) {
             error_log('getFeedingRecords error: ' . $e->getMessage());
             return [];
         }
     }

    public function deleteFeedingRecord(int $feedingId, int $kittenId) {
        try {
            $rows = $this->db->execute(
                "DELETE FROM feeding_records WHERE id = ? AND kitten_id = ?",
                [$feedingId, $kittenId]
            );
            return $rows > 0;
        } catch (Exception $e) {
            error_log('deleteFeedingRecord error: ' . $e->getMessage());
            return false;
        }
    }

    public function getFeedingRecordById(int $recordId) {
        $sql = "SELECT * FROM feeding_records WHERE id = ?";
        try {
            return $this->db->fetch($sql, [$recordId]);
        } catch (Exception $e) {
            error_log('getFeedingRecordById error: ' . $e->getMessage());
            return null;
        }
    }

    public function updateFeedingRecord(int $recordId, int $kittenId, array $data) {
        // Map incoming form-like fields to DB columns similar to addFeedingRecord
        $feedingDate = date('Y-m-d H:i:s', strtotime($data['date_time']));
        $weightGrams = isset($data['weight']) && $data['weight'] !== '' ? (int)$data['weight'] : null;
        $foodAmountGrams = isset($data['food_amount']) && $data['food_amount'] !== '' ? (int)$data['food_amount'] : null;
        $foodType = $this->mapFoodType($data['food_type'] ?? null);
        $heatingPadRefilled = isset($data['heat_bottle_refilled']) ? (int)(bool)$data['heat_bottle_refilled'] : 0;
        $stoolType = $this->mapStoolType($data['bowel_movement'] ?? null);
        $stoolConsistency = $this->mapStoolConsistency($data['stool_consistency'] ?? null);
        $stoolColor = $this->mapStoolColor($data['stool_color'] ?? null);
        $stoolColorOther = $data['stool_color_other'] ?? null;
        $fitnessLevel = isset($data['fitness_level']) && $data['fitness_level'] !== '' ? (int)$data['fitness_level'] : null;
        $notes = isset($data['notes']) ? trim($data['notes']) : null;

        $sql = "UPDATE feeding_records SET 
                    feeding_date = ?,
                    weight_grams = ?,
                    food_amount_grams = ?,
                    food_type = ?,
                    heating_pad_refilled = ?,
                    stool_type = ?,
                    stool_consistency = ?,
                    stool_color = ?,
                    stool_color_other = ?,
                    fitness_level = ?,
                    notes = ?
                WHERE id = ? AND kitten_id = ?";
        try {
            $this->db->execute($sql, [
                $feedingDate,
                $weightGrams,
                $foodAmountGrams,
                $foodType,
                $heatingPadRefilled,
                $stoolType,
                $stoolConsistency,
                $stoolColor,
                $stoolColorOther,
                $fitnessLevel,
                $notes,
                $recordId,
                $kittenId
            ]);
            return true;
        } catch (Exception $e) {
            error_log('updateFeedingRecord error: ' . $e->getMessage());
            return false;
        }
    }

    // === Veterinary records API ===
    public function addVeterinaryRecord(array $data) {
        $required = ['kitten_id', 'user_id', 'visit_date'];
        foreach ($required as $key) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
                return false;
            }
        }

        $sql = "INSERT INTO veterinary_records (
                    kitten_id, user_id, visit_date, veterinarian_name, diagnosis,
                    vaccination, next_vaccination_date, deworming, deworming_medication,
                    next_deworming_interval, tick_protection, tick_protection_medication,
                    next_tick_protection_interval, next_visit_date, cost_eur
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->db->execute($sql, [
                (int)$data['kitten_id'],
                (int)$data['user_id'],
                $data['visit_date'],
                $data['veterinarian_name'] ?? null,
                $data['diagnosis'] ?? null,
                $data['vaccination'] ?? null,
                $data['next_vaccination_date'] ?: null,
                isset($data['deworming']) ? (int)(bool)$data['deworming'] : 0,
                $data['deworming_medication'] ?? null,
                $data['next_deworming_interval'] ?? null,
                isset($data['tick_protection']) ? (int)(bool)$data['tick_protection'] : 0,
                $data['tick_protection_medication'] ?? null,
                $data['next_tick_protection_interval'] ?? null,
                $data['next_visit_date'] ?: null,
                $data['cost_eur'] !== '' ? $data['cost_eur'] : null
            ]);
            return true;
        } catch (Exception $e) {
            error_log('addVeterinaryRecord error: ' . $e->getMessage());
            return false;
        }
    }

    public function getVeterinaryRecords(int $kittenId) {
        $sql = "SELECT * FROM veterinary_records WHERE kitten_id = ? ORDER BY visit_date DESC, created_at DESC";
        try {
            return $this->db->fetchAll($sql, [$kittenId]);
        } catch (Exception $e) {
            error_log('getVeterinaryRecords error: ' . $e->getMessage());
            return [];
        }
    }

    public function getVeterinaryRecordById(int $recordId) {
        $sql = "SELECT * FROM veterinary_records WHERE id = ?";
        return $this->db->fetch($sql, [$recordId]);
    }

    public function getLastVeterinarianName(int $kittenId) {
        $sql = "SELECT veterinarian_name FROM veterinary_records WHERE kitten_id = ? AND veterinarian_name IS NOT NULL AND veterinarian_name != '' ORDER BY visit_date DESC, id DESC LIMIT 1";
        try {
            $row = $this->db->fetch($sql, [$kittenId]);
            return $row ? $row['veterinarian_name'] : null;
        } catch (Exception $e) {
            error_log('getLastVeterinarianName error: ' . $e->getMessage());
            return null;
        }
    }

    public function updateVeterinaryRecord(int $recordId, int $kittenId, array $data) {
        $sql = "UPDATE veterinary_records SET 
                    visit_date = ?,
                    veterinarian_name = ?,
                    diagnosis = ?,
                    vaccination = ?,
                    next_vaccination_date = ?,
                    deworming = ?,
                    deworming_medication = ?,
                    next_deworming_interval = ?,
                    tick_protection = ?,
                    tick_protection_medication = ?,
                    next_tick_protection_interval = ?,
                    next_visit_date = ?,
                    cost_eur = ?
                WHERE id = ? AND kitten_id = ?";
        try {
            $this->db->execute($sql, [
                $data['visit_date'],
                $data['veterinarian_name'] ?? null,
                $data['diagnosis'] ?? null,
                $data['vaccination'] ?? null,
                $data['next_vaccination_date'] ?: null,
                isset($data['deworming']) ? (int)(bool)$data['deworming'] : 0,
                $data['deworming_medication'] ?? null,
                $data['next_deworming_interval'] ?? null,
                isset($data['tick_protection']) ? (int)(bool)$data['tick_protection'] : 0,
                $data['tick_protection_medication'] ?? null,
                $data['next_tick_protection_interval'] ?? null,
                $data['next_visit_date'] ?: null,
                $data['cost_eur'] !== '' ? $data['cost_eur'] : null,
                $recordId,
                $kittenId
            ]);
            return true;
        } catch (Exception $e) {
            error_log('updateVeterinaryRecord error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteVeterinaryRecord(int $recordId, int $kittenId) {
        try {
            $rows = $this->db->execute(
                "DELETE FROM veterinary_records WHERE id = ? AND kitten_id = ?",
                [$recordId, $kittenId]
            );
            return $rows > 0;
        } catch (Exception $e) {
            error_log('deleteVeterinaryRecord error: ' . $e->getMessage());
            return false;
        }
    }

    private function mapFoodType($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $map = [
            'milk' => 'katzenmilch',
            'mixed' => 'mischfutter',
            'wet' => 'nassfutter',
            'dry' => 'trockenfutter'
        ];
        return $map[$value] ?? null;
    }

    private function mapStoolType($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $map = [
            'urine' => 'urin',
            'stool' => 'kot',
            'both' => 'beides'
        ];
        return $map[$value] ?? null;
    }

    private function mapStoolConsistency($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $map = [
            'firm' => 'fest',
            'liquid' => 'fluessig'
        ];
        return $map[$value] ?? null;
    }

    private function mapStoolColor($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $map = [
            'brown' => 'braun',
            'black' => 'schwarz',
            'orange' => 'orange',
            'red' => 'rot',
            'gray' => 'grau',
            'other' => 'sonstiges'
        ];
        return $map[$value] ?? null;
    }
}