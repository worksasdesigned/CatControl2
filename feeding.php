<?php
session_start();

require_once 'classes/User.php';
require_once 'classes/Kitten.php';

$userService = new User();
$kittenService = new Kitten();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$error = '';
$success = '';

// Get kitten ID from URL
$kittenId = isset($_GET['kitten_id']) ? (int)$_GET['kitten_id'] : 0;

if (!$kittenId) {
    header('Location: dashboard.php');
    exit;
}

// Get kitten details
$kitten = $kittenService->getKittenById($kittenId);

if (!$kitten || !$kittenService->hasKittenAccess($currentUser['id'], $kittenId)) {
    header('Location: dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_feeding'])) {
        $feedingData = [
            'kitten_id' => $kittenId,
            'date_time' => $_POST['date_time'],
            'weight' => (int)$_POST['weight'],
            'food_amount' => (int)$_POST['food_amount'],
            'food_type' => $_POST['food_type'],
            'heat_bottle_refilled' => isset($_POST['heat_bottle_refilled']) ? 1 : 0,
            'bowel_movement' => $_POST['bowel_movement'] ?? null,
            'stool_consistency' => $_POST['stool_consistency'] ?? null,
            'stool_color' => $_POST['stool_color'],
            'stool_color_other' => $_POST['stool_color_other'] ?? null,
            'fitness_level' => (int)$_POST['fitness_level'],
            'notes' => trim($_POST['notes'])
        ];
        
        if ($kittenService->addFeedingRecord($feedingData)) {
            $success = 'Fütterungsdaten wurden erfolgreich gespeichert!';
        } else {
            $error = 'Fehler beim Speichern der Fütterungsdaten.';
        }
    }
    
    if (isset($_POST['delete_feeding'])) {
        $feedingId = (int)$_POST['feeding_id'];
        if ($kittenService->deleteFeedingRecord($feedingId, $kittenId)) {
            $success = 'Fütterungsdatensatz wurde gelöscht.';
        } else {
            $error = 'Fehler beim Löschen des Datensatzes.';
        }
    }
}

// Get recent feeding records
$showAll = isset($_GET['show_all']);
$feedingRecords = $kittenService->getFeedingRecords($kittenId, $showAll ? 0 : 20);

// Get user preferences for field visibility
$fieldPreferences = $userService->getFieldPreferences($currentUser['id'], 'feeding');

// Get custom background if set
$backgroundImage = 'assets/images/background.png';
if (!empty($currentUser['custom_background'])) {
    $customBg = 'uploads/backgrounds/' . $currentUser['custom_background'];
    if (file_exists($customBg)) {
        $backgroundImage = $customBg;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatControl - Fütterung: <?= htmlspecialchars($kitten['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-image: url('<?= $backgroundImage ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8em;
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .back-btn {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            transform: translateY(-1px);
        }
        
        .main-content {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .kitten-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .kitten-name {
            font-size: 2em;
            color: #ff6b6b;
            margin-bottom: 10px;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            color: #ff6b6b;
            font-size: 1.5em;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #ff6b6b;
            outline: none;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }
        
        .radio-group input[type="radio"] {
            width: auto;
            margin: 0;
        }
        
        .slider-container {
            margin-top: 10px;
        }
        
        .slider {
            width: 100%;
            height: 6px;
            border-radius: 5px;
            background: #ddd;
            outline: none;
            -webkit-appearance: none;
        }
        
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ff6b6b;
            cursor: pointer;
        }
        
        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ff6b6b;
            cursor: pointer;
            border: none;
        }
        
        .slider-value {
            text-align: center;
            font-weight: bold;
            color: #ff6b6b;
            margin-top: 5px;
        }
        
        .hide-toggle {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ddd;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hide-toggle:hover {
            background: #ccc;
        }
        
        .form-group.hidden {
            display: none;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert.error {
            background: #ffe6e6;
            color: #d63031;
            border: 1px solid #ff7979;
        }
        
        .alert.success {
            background: #e6ffe6;
            color: #00b894;
            border: 1px solid #00cec9;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff5252, #ff7979);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #636e72, #2d3436);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #2d3436, #636e72);
            transform: translateY(-1px);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .records-table th,
        .records-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .records-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .records-table tr:hover {
            background: #f8f9fa;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0 2px;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e17055, #d63031);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .main-content {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .records-table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">🐱 CatControl</div>
        <a href="dashboard.php" class="back-btn">← Zurück zum Dashboard</a>
    </header>

    <main class="main-content">
        <div class="kitten-header">
            <h1 class="kitten-name">🍼 Fütterung: <?= htmlspecialchars($kitten['name']) ?></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <!-- Feeding Form -->
        <div class="form-container">
            <h2 class="section-title">Neue Fütterung erfassen</h2>
            
            <form method="POST">
                <div class="form-grid">
                    <!-- Always visible fields -->
                    <div class="form-group">
                        <label for="date_time">Datum / Uhrzeit:</label>
                        <input type="datetime-local" id="date_time" name="date_time" 
                               value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="weight">Gewicht (in Gramm):</label>
                        <input type="number" id="weight" name="weight" min="0" required>
                    </div>
                    
                    <!-- Hideable fields -->
                    <div class="form-group <?= isset($fieldPreferences['food_amount']) && !$fieldPreferences['food_amount'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <label for="food_amount">Fütterung (in Gramm):</label>
                        <input type="number" id="food_amount" name="food_amount" min="0">
                    </div>
                    
                    <div class="form-group <?= isset($fieldPreferences['food_type']) && !$fieldPreferences['food_type'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <label>Futter:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="food_type" value="milk" checked> Katzenmilch</label>
                            <label><input type="radio" name="food_type" value="mixed"> Mischfutter</label>
                            <label><input type="radio" name="food_type" value="wet"> Nassfutter</label>
                            <label><input type="radio" name="food_type" value="dry"> Trockenfutter</label>
                        </div>
                    </div>
                    
                    <div class="form-group <?= isset($fieldPreferences['heat_bottle']) && !$fieldPreferences['heat_bottle'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <div class="checkbox-group">
                            <input type="checkbox" id="heat_bottle_refilled" name="heat_bottle_refilled">
                            <label for="heat_bottle_refilled">Wärmeflasche nachgefüllt</label>
                        </div>
                    </div>
                    
                    <div class="form-group <?= isset($fieldPreferences['bowel_movement']) && !$fieldPreferences['bowel_movement'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <label>Stuhlgang:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="bowel_movement" value="urine"> Urin</label>
                            <label><input type="radio" name="bowel_movement" value="stool"> Kot</label>
                            <label><input type="radio" name="bowel_movement" value="both"> Beides</label>
                        </div>
                    </div>
                    
                    <div class="form-group <?= isset($fieldPreferences['stool_consistency']) && !$fieldPreferences['stool_consistency'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <label>Zustand Stuhlgang:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="stool_consistency" value="firm"> Fest</label>
                            <label><input type="radio" name="stool_consistency" value="liquid"> Flüssig</label>
                        </div>
                    </div>
                    
                    <div class="form-group <?= isset($fieldPreferences['stool_color']) && !$fieldPreferences['stool_color'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <label for="stool_color">Farbe Stuhlgang:</label>
                        <select id="stool_color" name="stool_color" onchange="toggleOtherColor(this)">
                            <option value="">Bitte wählen...</option>
                            <option value="brown">Braun</option>
                            <option value="black">Schwarz</option>
                            <option value="orange">Orange</option>
                            <option value="red">Rot</option>
                            <option value="gray">Grau</option>
                            <option value="other">Sonstiges</option>
                        </select>
                        <input type="text" id="stool_color_other" name="stool_color_other" 
                               placeholder="Andere Farbe..." style="margin-top: 10px; display: none;">
                    </div>
                    
                    <div class="form-group <?= isset($fieldPreferences['fitness_level']) && !$fieldPreferences['fitness_level'] ? 'hidden' : '' ?>">
                        <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                        <label for="fitness_level">Fitnesslevel (0-10):</label>
                        <div class="slider-container">
                            <input type="range" id="fitness_level" name="fitness_level" 
                                   min="0" max="10" value="5" class="slider" 
                                   oninput="updateSliderValue(this)">
                            <div class="slider-value" id="fitness_value">5</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group full-width <?= isset($fieldPreferences['notes']) && !$fieldPreferences['notes'] ? 'hidden' : '' ?>">
                    <button type="button" class="hide-toggle" onclick="toggleField(this)">👁️</button>
                    <label for="notes">Bemerkungen:</label>
                    <textarea id="notes" name="notes" rows="3" cols="40"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_feeding" class="btn-primary">Speichern</button>
                    <a href="dashboard.php" class="btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>
        
        <!-- Recent Records -->
        <div class="form-container">
            <h2 class="section-title">Letzte Fütterungen</h2>
            
            <?php if (empty($feedingRecords)): ?>
                <p style="text-align: center; color: #666; font-style: italic;">Noch keine Fütterungsdaten erfasst.</p>
            <?php else: ?>
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Datum/Zeit</th>
                            <th>Gewicht</th>
                            <th>Futter</th>
                            <th>Fitness</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedingRecords as $record): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i', strtotime($record['date_time'])) ?></td>
                                <td><?= $record['weight'] ?>g</td>
                                <td><?= $record['food_amount'] ?>g (<?= ucfirst($record['food_type']) ?>)</td>
                                <td><?= $record['fitness_level'] ?>/10</td>
                                <td>
                                    <button class="btn-small btn-edit" onclick="editRecord(<?= $record['id'] ?>)">Ändern</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="feeding_id" value="<?= $record['id'] ?>">
                                        <button type="submit" name="delete_feeding" class="btn-small btn-delete" 
                                                onclick="return confirm('Datensatz löschen?')">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (!$showAll && count($feedingRecords) >= 20): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="?kitten_id=<?= $kittenId ?>&show_all=1" class="btn-secondary">Alle Datensätze anzeigen</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        function updateSliderValue(slider) {
            document.getElementById('fitness_value').textContent = slider.value;
        }
        
        function toggleOtherColor(select) {
            const otherInput = document.getElementById('stool_color_other');
            if (select.value === 'other') {
                otherInput.style.display = 'block';
                otherInput.required = true;
            } else {
                otherInput.style.display = 'none';
                otherInput.required = false;
            }
        }
        
        function toggleField(button) {
            const formGroup = button.parentElement;
            formGroup.classList.toggle('hidden');
            
            // Save preference (you would implement this with AJAX)
            const fieldName = formGroup.querySelector('input, select, textarea').name;
            const isVisible = !formGroup.classList.contains('hidden');
            
            // Simple implementation - could be enhanced with AJAX
            localStorage.setItem('field_' + fieldName, isVisible);
        }
        
        function editRecord(recordId) {
            // Implement edit functionality
            alert('Edit functionality would be implemented here for record ' + recordId);
        }
        
        // Load field preferences from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                const input = group.querySelector('input, select, textarea');
                if (input && input.name) {
                    const isVisible = localStorage.getItem('field_' + input.name);
                    if (isVisible === 'false') {
                        group.classList.add('hidden');
                    }
                }
            });
        });
    </script>
</body>
</html>