<?php
session_start();

require_once 'classes/User.php';
require_once 'classes/Kitten.php';

$userService = new User();
$kittenService = new Kitten();

if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$error = '';
$success = '';

$kittenId = isset($_GET['kitten_id']) ? (int)$_GET['kitten_id'] : 0;
$recordId = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;

if (!$kittenId) {
    header('Location: dashboard.php');
    exit;
}

if (!$kittenService->hasAccess($kittenId, $currentUser['id'])) {
    header('Location: dashboard.php');
    exit;
}

$kitten = $kittenService->getKittenById($kittenId);
if (!$kitten) {
    header('Location: dashboard.php');
    exit;
}

// Helper to shorten text safely without requiring mbstring
if (!function_exists('shorten_text')) {
    function shorten_text($text, $max = 30) {
        $text = (string)$text;
        if ($max < 2) { return $text; }
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $max, '…', 'UTF-8');
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $max) {
                return $text;
            }
            return mb_substr($text, 0, $max - 1, 'UTF-8') . '…';
        }
        if (strlen($text) <= $max) {
            return $text;
        }
        return substr($text, 0, $max - 1) . '…';
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_vet'])) {
        $data = [
            'kitten_id' => $kittenId,
            'user_id' => $currentUser['id'],
            'visit_date' => $_POST['visit_date'] ?? date('Y-m-d'),
            'veterinarian_name' => trim($_POST['veterinarian_name'] ?? ''),
            'diagnosis' => trim($_POST['diagnosis'] ?? ''),
            'vaccination' => trim($_POST['vaccination'] ?? ''),
            'next_vaccination_date' => $_POST['next_vaccination_date'] ?: null,
            'deworming' => isset($_POST['deworming']) ? 1 : 0,
            'deworming_medication' => trim($_POST['deworming_medication'] ?? ''),
            'next_deworming_interval' => $_POST['next_deworming_interval'] ?: null,
            'tick_protection' => isset($_POST['tick_protection']) ? 1 : 0,
            'tick_protection_medication' => trim($_POST['tick_protection_medication'] ?? ''),
            'next_tick_protection_interval' => $_POST['next_tick_protection_interval'] ?: null,
            'next_visit_date' => $_POST['next_visit_date'] ?: null,
            'cost_eur' => $_POST['cost_eur'] !== '' ? $_POST['cost_eur'] : null,
        ];

        if (!empty($_POST['record_id'])) {
            $rid = (int)$_POST['record_id'];
            if ($kittenService->updateVeterinaryRecord($rid, $kittenId, $data)) {
                $success = 'Tierarztbesuch wurde aktualisiert.';
                $recordId = 0;
            } else {
                $error = 'Fehler beim Aktualisieren.';
            }
        } else {
            if ($kittenService->addVeterinaryRecord($data)) {
                $success = 'Tierarztbesuch wurde gespeichert!';
            } else {
                $error = 'Fehler beim Speichern.';
            }
        }
    }

    if (isset($_POST['delete_vet'])) {
        $rid = (int)$_POST['record_id'];
        if ($kittenService->deleteVeterinaryRecord($rid, $kittenId)) {
            $success = 'Eintrag wurde gelöscht.';
        } else {
            $error = 'Fehler beim Löschen.';
        }
    }
}

// Load record for editing if requested
$editRecord = null;
if ($recordId) {
    $editRecord = $kittenService->getVeterinaryRecordById($recordId);
    if (!$editRecord || (int)$editRecord['kitten_id'] !== (int)$kittenId) {
        $editRecord = null;
    }
}

// Get recent veterinary records
$vetRecords = $kittenService->getVeterinaryRecords($kittenId);
$lastVetName = $kittenService->getLastVeterinarianName($kittenId);

// Background image
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
    <title>CatControl - Tierarzt: <?= htmlspecialchars($kitten['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-image: url('<?= $backgroundImage ?>');
            background-size: cover; background-position: center; background-attachment: fixed;
            min-height: 100vh; font-family: 'Arial', sans-serif; color: #333;
        }
        .header { background: rgba(255,255,255,0.95); padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display:flex; justify-content: space-between; align-items:center; }
        .logo { font-size:1.8em; color:#ff6b6b; font-weight:bold; }
        .back-btn { background: linear-gradient(135deg,#74b9ff,#0984e3); color:#fff; border:none; padding:10px 20px; border-radius:5px; text-decoration:none; }
        .main-content { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .kitten-header, .form-container { background: rgba(255,255,255,0.95); border-radius: 15px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .kitten-name { font-size: 2em; color:#ff6b6b; margin-bottom: 10px; text-align:center; }
        .section-title { color:#ff6b6b; font-size: 1.5em; margin-bottom: 20px; text-align:center; }
        .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 10px; }
        .form-group { position: relative; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display:block; margin-bottom: 5px; font-weight:bold; color:#333; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:12px; border:2px solid #ddd; border-radius:8px; font-size:16px; }
        .checkbox-group { display:flex; align-items:center; gap:10px; }
        .btn-primary { background: linear-gradient(135deg,#ff6b6b,#ff8e8e); color:#fff; border:none; padding:12px 25px; border-radius:8px; cursor:pointer; font-size:16px; font-weight:bold; }
        .btn-secondary { background: linear-gradient(135deg,#636e72,#2d3436); color:#fff; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; font-size:14px; text-decoration:none; display:inline-block; }
        .records-table { width:100%; border-collapse: collapse; }
        .records-table th, .records-table td { padding: 10px; border-bottom: 1px solid #ddd; text-align:left; }
        .btn-small { padding:5px 10px; font-size:12px; margin:0 2px; }
        .btn-edit { background: linear-gradient(135deg,#00b894,#00cec9); color:#fff; border:none; border-radius:4px; cursor:pointer; }
        .btn-delete { background: linear-gradient(135deg,#e17055,#d63031); color:#fff; border:none; border-radius:4px; cursor:pointer; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">🐱 CatControl</div>
        <a href="dashboard.php" class="back-btn">← Zurück zum Dashboard</a>
    </header>

    <main class="main-content">
        <div class="kitten-header">
            <h1 class="kitten-name">🏥 Tierarzt: <?= htmlspecialchars($kitten['name']) ?></h1>
        </div>

        <?php if ($error): ?>
            <div class="alert error" style="background:#ffe6e6;color:#d63031;border:1px solid #ff7979; padding:12px; border-radius:8px; text-align:center; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success" style="background:#e6ffe6;color:#00b894;border:1px solid #00cec9; padding:12px; border-radius:8px; text-align:center; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2 class="section-title">Tierarztbesuch erfassen</h2>
            <form method="POST">
                <input type="hidden" name="record_id" value="<?= $editRecord['id'] ?? '' ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="visit_date">Datum</label>
                        <input type="date" id="visit_date" name="visit_date" value="<?= htmlspecialchars($editRecord['visit_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="veterinarian_name">Tierarzt (Name)</label>
                        <input type="text" id="veterinarian_name" name="veterinarian_name" maxlength="100" value="<?= htmlspecialchars($editRecord['veterinarian_name'] ?? ($lastVetName ?: '')) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label for="diagnosis">Befund</label>
                        <textarea id="diagnosis" name="diagnosis" rows="3"><?= htmlspecialchars($editRecord['diagnosis'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="vaccination">Impfung</label>
                        <textarea id="vaccination" name="vaccination" rows="3"><?= htmlspecialchars($editRecord['vaccination'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="next_vaccination_date">Datum nächste Impfung</label>
                        <input type="date" id="next_vaccination_date" name="next_vaccination_date" value="<?= htmlspecialchars($editRecord['next_vaccination_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Entwurmung</label>
                        <div class="checkbox-group"><input type="checkbox" id="deworming" name="deworming" <?= !empty($editRecord['deworming']) ? 'checked' : '' ?>><label for="deworming">Durchgeführt</label></div>
                    </div>
                    <div class="form-group">
                        <label for="deworming_medication">Entwurmung Medikament</label>
                        <input type="text" id="deworming_medication" name="deworming_medication" maxlength="60" value="<?= htmlspecialchars($editRecord['deworming_medication'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="next_deworming_interval">Nächste Entwurmung</label>
                        <select id="next_deworming_interval" name="next_deworming_interval">
                            <?php $intv = $editRecord['next_deworming_interval'] ?? ''; ?>
                            <option value="">Bitte wählen...</option>
                            <option value="1_week" <?= $intv==='1_week'?'selected':'' ?>>1 Woche</option>
                            <option value="2_weeks" <?= $intv==='2_weeks'?'selected':'' ?>>2 Wochen</option>
                            <option value="4_weeks" <?= $intv==='4_weeks'?'selected':'' ?>>4 Wochen</option>
                            <option value="2_months" <?= $intv==='2_months'?'selected':'' ?>>2 Monate</option>
                            <option value="3_months" <?= $intv==='3_months'?'selected':'' ?>>3 Monate</option>
                            <option value="4_months" <?= $intv==='4_months'?'selected':'' ?>>4 Monate</option>
                            <option value="6_months" <?= $intv==='6_months'?'selected':'' ?>>6 Monate</option>
                            <option value="1_year" <?= $intv==='1_year'?'selected':'' ?>>1 Jahr</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Zeckenschutz</label>
                        <div class="checkbox-group"><input type="checkbox" id="tick_protection" name="tick_protection" <?= !empty($editRecord['tick_protection']) ? 'checked' : '' ?>><label for="tick_protection">Durchgeführt</label></div>
                    </div>
                    <div class="form-group full-width">
                        <label for="tick_protection_medication">Zeckenschutz Medikament</label>
                        <textarea id="tick_protection_medication" name="tick_protection_medication" rows="2"><?= htmlspecialchars($editRecord['tick_protection_medication'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="next_tick_protection_interval">Nächster Zeckenschutz</label>
                        <select id="next_tick_protection_interval" name="next_tick_protection_interval">
                            <?php $tintv = $editRecord['next_tick_protection_interval'] ?? ''; ?>
                            <option value="">Bitte wählen...</option>
                            <option value="1_week" <?= $tintv==='1_week'?'selected':'' ?>>1 Woche</option>
                            <option value="2_weeks" <?= $tintv==='2_weeks'?'selected':'' ?>>2 Wochen</option>
                            <option value="4_weeks" <?= $tintv==='4_weeks'?'selected':'' ?>>4 Wochen</option>
                            <option value="2_months" <?= $tintv==='2_months'?'selected':'' ?>>2 Monate</option>
                            <option value="3_months" <?= $tintv==='3_months'?'selected':'' ?>>3 Monate</option>
                            <option value="4_months" <?= $tintv==='4_months'?'selected':'' ?>>4 Monate</option>
                            <option value="6_months" <?= $tintv==='6_months'?'selected':'' ?>>6 Monate</option>
                            <option value="1_year" <?= $tintv==='1_year'?'selected':'' ?>>1 Jahr</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="next_visit_date">Nächster geplanter Tierarztbesuch</label>
                        <input type="date" id="next_visit_date" name="next_visit_date" value="<?= htmlspecialchars($editRecord['next_visit_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="cost_eur">Kosten in EUR</label>
                        <input type="number" step="0.01" id="cost_eur" name="cost_eur" value="<?= htmlspecialchars($editRecord['cost_eur'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-actions" style="display:flex; gap:12px; justify-content:center; margin-top: 20px;">
                    <button type="submit" name="save_vet" class="btn-primary">Speichern</button>
                    <a href="dashboard.php" class="btn-secondary">Abbrechen</a>
                </div>
            </form>
        </div>

        <div class="form-container">
            <h2 class="section-title">Alle Tierarztbesuche</h2>
            <?php if (empty($vetRecords)): ?>
                <p style="text-align:center; color:#666; font-style:italic;">Noch keine Einträge vorhanden.</p>
            <?php else: ?>
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Tierarzt</th>
                            <th>Impfung</th>
                            <th>Nächste Impfung</th>
                            <th>Nächster Besuch</th>
                            <th>Kosten (EUR)</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vetRecords as $vr): ?>
                            <tr>
                                <td><?= date('d.m.Y', strtotime($vr['visit_date'])) ?></td>
                                <td><?= htmlspecialchars($vr['veterinarian_name'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(shorten_text($vr['vaccination'] ?: '-', 30)) ?></td>
                                <td><?= $vr['next_vaccination_date'] ? date('d.m.Y', strtotime($vr['next_vaccination_date'])) : '-' ?></td>
                                <td><?= $vr['next_visit_date'] ? date('d.m.Y', strtotime($vr['next_visit_date'])) : '-' ?></td>
                                <td><?= $vr['cost_eur'] !== null ? number_format((float)$vr['cost_eur'], 2, ',', '') : '-' ?></td>
                                <td>
                                    <button class="btn-small btn-edit" onclick="location.href='veterinary.php?kitten_id=<?= $kittenId ?>&record_id=<?= $vr['id'] ?>'">Ändern</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                        <input type="hidden" name="record_id" value="<?= $vr['id'] ?>">
                                        <button type="submit" name="delete_vet" class="btn-small btn-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>