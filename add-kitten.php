<?php
session_start();

require_once 'config/i18n.php';
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

$kittenId = isset($_GET['kitten_id']) ? (int)$_GET['kitten_id'] : 0;
$editing = $kittenId > 0;
$kittenToEdit = null;

if ($editing) {
    // Zugriff prüfen
    if (!$kittenService->hasAccess($kittenId, $currentUser['id'])) {
        header('Location: dashboard.php');
        exit;
    }
    $kittenToEdit = $kittenService->getKittenById($kittenId);
    if (!$kittenToEdit) {
        header('Location: dashboard.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_kitten'])) {
        $name = trim($_POST['name']);
        $birth_date = $_POST['birth_date'];
        $color = trim($_POST['color']);
        $mother = trim($_POST['mother']);
        $found_location = trim($_POST['found_location']);
        $found_date = $_POST['found_date'];
        $tasso_id = trim($_POST['tasso_id']);
        $ear_tattoo = trim($_POST['ear_tattoo']);
        $postal_code = trim($_POST['postal_code']);
        $sex = $_POST['sex'] ?? 'unbekannt';
        
        // Validation
        if (empty($name)) {
            $error = 'Name ist ein Pflichtfeld.';
        } elseif (empty($birth_date)) {
            $error = 'Geburtsdatum ist ein Pflichtfeld.';
        } else {
            $kittenData = [
                'name' => $name,
                'birth_date' => $birth_date,
                'color' => $color,
                'mother' => $mother,
                'found_location' => $found_location,
                'found_date' => $found_date ?: null,
                'tasso_id' => $tasso_id,
                'ear_tattoo' => $ear_tattoo,
                'postal_code' => $postal_code,
                'sex' => $sex
            ];
            
            if ($editing) {
                $result = $kittenService->updateKitten($kittenId, $currentUser['id'], $kittenData);
            } else {
                $result = $kittenService->createKitten($currentUser['id'], $kittenData);
            }
            
            if ($result['success']) {
                $success = $editing ? 'Kätzchen wurde aktualisiert!' : 'Kätzchen wurde erfolgreich angelegt!';
                header("refresh:2;url=dashboard.php");
            } else {
                $error = $result['message'] ?? 'Fehler beim Speichern des Kätzchens.';
            }
        }
    }
}

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
<html lang="<?= htmlspecialchars(i18n_current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('app.name') ?> - <?= $editing ? __('kittens.edit') ?? 'Kätzchen bearbeiten' : __('dashboard.add_kitten') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-image: url('<?= $backgroundImage ?>'); background-size: cover; background-position: center; background-attachment: fixed; min-height: 100vh; font-family: 'Arial', sans-serif; color: #333; }
        .header { background: rgba(255, 255, 255, 0.95); padding: 15px 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8em; color: #ff6b6b; font-weight: bold; }
        .back-btn { background: linear-gradient(135deg, #74b9ff, #0984e3); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .back-btn:hover { background: linear-gradient(135deg, #0984e3, #74b9ff); transform: translateY(-1px); }
        .main-content { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .form-container { background: rgba(255, 255, 255, 0.95); border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); }
        .page-title { text-align: center; color: #ff6b6b; font-size: 2em; margin-bottom: 10px; }
        .subtle { text-align: center; color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .required { color: #ff6b6b; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: #ff6b6b; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert.error { background: #ffe6e6; color: #d63031; border: 1px solid #ff7979; }
        .alert.success { background: #e6ffe6; color: #00b894; border: 1px solid #00cec9; }
        .form-actions { display: flex; gap: 15px; justify-content: center; margin-top: 30px; flex-wrap: wrap; }
        .btn-primary { background: linear-gradient(135deg, #ff6b6b, #ff8e8e); color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; transition: all 0.3s; }
        .btn-primary:hover { background: linear-gradient(135deg, #ff5252, #ff7979); transform: translateY(-2px); }
        .btn-secondary { background: linear-gradient(135deg, #636e72, #2d3436); color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: linear-gradient(135deg, #2d3436, #636e72); transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .btn-danger:hover { background: linear-gradient(135deg, #c0392b, #e74c3c); }
        .btn-archive { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
        .btn-archive:hover { background: linear-gradient(135deg, #7f8c8d, #95a5a6); }
        .radio-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; cursor: pointer; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .form-actions { flex-direction: column; } .main-content { margin: 20px auto; padding: 0 15px; } .form-container { padding: 20px; } }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">🐱 CatControl</div>
        <a href="dashboard.php" class="back-btn">← Zurück zum Dashboard</a>
    </header>

    <main class="main-content">
        <div class="form-container">
            <h1 class="page-title"><?= $editing ? 'Kätzchen bearbeiten' : 'Neues Kätzchen anlegen' ?></h1>
            <?php if ($editing && !empty($kittenToEdit['is_archived'])): ?>
                <div class="subtle">Dieses Kätzchen ist aktuell <strong>archiviert</strong>.</div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="kitten_id" value="<?= $editing ? (int)$kittenId : '' ?>">
                <div class="form-group">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required 
                           value="<?= htmlspecialchars($editing ? ($kittenToEdit['name'] ?? '') : ($_POST['name'] ?? '')) ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="birth_date">Geschätztes Geburtsdatum <span class="required">*</span></label>
                        <input type="date" id="birth_date" name="birth_date" required 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['birth_date'] ?? '') : ($_POST['birth_date'] ?? '')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Farbe</label>
                        <input type="text" id="color" name="color" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['color'] ?? '') : ($_POST['color'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Geschlecht</label>
                    <?php $sexVal = $editing ? ($kittenToEdit['sex'] ?? 'unbekannt') : ($_POST['sex'] ?? 'unbekannt'); ?>
                    <div class="radio-group">
                        <label><input type="radio" name="sex" value="kater" <?= $sexVal === 'kater' ? 'checked' : '' ?>> Kater</label>
                        <label><input type="radio" name="sex" value="katze" <?= $sexVal === 'katze' ? 'checked' : '' ?>> Katze</label>
                        <label><input type="radio" name="sex" value="unbekannt" <?= $sexVal === 'unbekannt' ? 'checked' : '' ?>> noch unbekannt</label>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="mother">Mutter</label>
                        <input type="text" id="mother" name="mother" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['mother'] ?? '') : ($_POST['mother'] ?? '')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="found_location">Fundort</label>
                        <input type="text" id="found_location" name="found_location" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['found_location'] ?? '') : ($_POST['found_location'] ?? '')) ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="found_date">Funddatum</label>
                        <input type="date" id="found_date" name="found_date" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['found_date'] ?? '') : ($_POST['found_date'] ?? '')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="postal_code">Postleitzahl</label>
                        <input type="text" id="postal_code" name="postal_code" maxlength="5" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['postal_code'] ?? '') : ($_POST['postal_code'] ?? '')) ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tasso_id">TASSO ID</label>
                        <input type="text" id="tasso_id" name="tasso_id" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['tasso_id'] ?? '') : ($_POST['tasso_id'] ?? '')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="ear_tattoo">Ohrtätowierung</label>
                        <input type="text" id="ear_tattoo" name="ear_tattoo" 
                               value="<?= htmlspecialchars($editing ? ($kittenToEdit['ear_tattoo'] ?? '') : ($_POST['ear_tattoo'] ?? '')) ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_kitten" class="btn-primary"><?= $editing ? 'Änderungen speichern' : 'Kätzchen speichern' ?></button>
                    <a href="dashboard.php" class="btn-secondary">Abbrechen</a>
                    <?php if ($editing): ?>
                        <?php $isArchived = !empty($kittenToEdit['is_archived']); ?>
                        <button type="button" class="btn-archive" onclick="toggleArchive(<?= (int)$kittenId ?>, <?= $isArchived ? 'true' : 'false' ?>)">
                            <?= $isArchived ? 'Aus dem Archiv holen' : 'Archivieren' ?>
                        </button>
                        <button type="button" class="btn-danger" onclick="deleteKitten(<?= (int)$kittenId ?>)">Löschen</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>

    <script>
        function toggleArchive(kittenId, isCurrentlyArchived) {
            const confirmMsg = isCurrentlyArchived ? 'Kätzchen aus dem Archiv holen?' : 'Kätzchen archivieren? Es erscheint dann nicht mehr im Dashboard.';
            if (!confirm(confirmMsg)) return;
            fetch('ajax/toggle-archive.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ kitten_id: kittenId, is_archived: !isCurrentlyArchived })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    alert(data.message || 'Fehler beim Aktualisieren');
                }
            }).catch(() => alert('Fehler beim Aktualisieren'));
        }
        function deleteKitten(kittenId) {
            if (!confirm('Dieses Kätzchen und alle zugehörigen Daten dauerhaft löschen?')) return;
            fetch('ajax/delete-kitten.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ kitten_id: kittenId })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    alert('Kätzchen gelöscht');
                    window.location.href = 'dashboard.php';
                } else {
                    alert(data.message || 'Fehler beim Löschen');
                }
            }).catch(() => alert('Fehler beim Löschen'));
        }
    </script>
</body>
</html>