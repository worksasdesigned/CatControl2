<?php
session_start();

require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Kitten.php';

header('Content-Type: text/html; charset=UTF-8');

$userService = new User();
$kittenService = new Kitten();

if (!$userService->isLoggedIn()) {
    http_response_code(403);
    echo '<p>Nicht autorisiert.</p>';
    exit;
}

$currentUser = $userService->getCurrentUser();
$kittenId = isset($_REQUEST['kitten_id']) ? (int)$_REQUEST['kitten_id'] : 0;
if (!$kittenId) {
    http_response_code(400);
    echo '<p>Ungültige Anfrage.</p>';
    exit;
}

// POST actions: share / unshare
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'share') {
        $shareWithUserId = (int)($_POST['user_id'] ?? 0);
        $result = $kittenService->shareKitten($kittenId, $currentUser['id'], $shareWithUserId);
        echo json_encode($result);
        exit;
    }
    if ($action === 'unshare') {
        $unshareFromUserId = (int)($_POST['user_id'] ?? 0);
        $result = $kittenService->unshareKitten($kittenId, $currentUser['id'], $unshareFromUserId);
        echo json_encode($result);
        exit;
    }
}

// Render HTML content for modal
$kitten = $kittenService->getKittenById($kittenId);
if (!$kitten || (int)$kitten['owner_id'] !== (int)$currentUser['id']) {
    echo '<p>Nur der Besitzer kann das Kätzchen teilen.</p>';
    exit;
}

$sharedUsers = $kittenService->getSharedUsers($kittenId);
$allUsers = $userService->getAllUsers($currentUser['id']);

// Filter out users already shared
$sharedUserIds = array_column($sharedUsers, 'id');
$availableUsers = array_values(array_filter($allUsers, function($u) use ($sharedUserIds, $currentUser) {
    if ($u['id'] == $currentUser['id']) { return false; }
    return !in_array($u['id'], $sharedUserIds, true);
}));
?>
<div>
    <h3>Mit Benutzer teilen</h3>
    <div style="display:flex; gap:10px; align-items:center; margin-bottom:15px;">
        <select id="shareUserSelect" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:6px;">
            <option value="">Benutzer wählen…</option>
            <?php foreach ($availableUsers as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="shareWithSelected()" style="padding:8px 14px; border:none; border-radius:6px; background:#ff6b6b; color:#fff; cursor:pointer;">Hinzufügen</button>
    </div>

    <h3>Geteilte Benutzer</h3>
    <?php if (empty($sharedUsers)): ?>
        <p style="color:#666; font-style:italic;">Noch nicht geteilt.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0; margin:0;">
            <?php foreach ($sharedUsers as $su): ?>
                <li style="display:flex; justify-content:space-between; align-items:center; padding:8px; border:1px solid #eee; border-radius:6px; margin-bottom:8px;">
                    <span><?= htmlspecialchars($su['username']) ?> <span style="color:#999; font-size:12px;">(seit <?= htmlspecialchars(date('d.m.Y H:i', strtotime($su['granted_at']))) ?>)</span></span>
                    <button onclick="unshareUser(<?= (int)$su['id'] ?>)" style="padding:6px 10px; border:none; border-radius:6px; background:#636e72; color:#fff; cursor:pointer;">Entfernen</button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<script>
function shareWithSelected() {
    const select = document.getElementById('shareUserSelect');
    const userId = parseInt(select.value, 10);
    if (!userId) { return; }
    fetch('ajax/share-kitten.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'share', kitten_id: '<?= $kittenId ?>', user_id: String(userId) })
    }).then(r => r.json()).then(data => {
        if (data.success) { location.reload(); }
        else { alert(data.message || 'Fehler beim Teilen'); }
    }).catch(() => alert('Fehler beim Teilen'));
}

function unshareUser(userId) {
    if (!confirm('Zugriff wirklich entfernen?')) return;
    fetch('ajax/share-kitten.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'unshare', kitten_id: '<?= $kittenId ?>', user_id: String(userId) })
    }).then(r => r.json()).then(data => {
        if (data.success) { location.reload(); }
        else { alert(data.message || 'Fehler beim Entfernen'); }
    }).catch(() => alert('Fehler beim Entfernen'));
}
</script>