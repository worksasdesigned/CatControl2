<?php
session_start();

require_once 'classes/User.php';
require_once 'classes/Kitten.php';
require_once 'classes/MessageService.php';

$userService = new User();
$kittenService = new Kitten();
$messageService = new MessageService();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$kittens = $kittenService->getUserKittens($currentUser['id']);
$unreadMessages = $messageService->getUnreadMessageCount($currentUser['id']);

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
    <title>CatControl - Dashboard</title>
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
            flex-wrap: wrap;
        }
        
        .logo {
            font-size: 1.8em;
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .messages-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.5em;
            color: #666;
            transition: color 0.3s;
        }
        
        .messages-icon:hover {
            color: #ff6b6b;
        }
        
        .message-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-menu {
            position: relative;
            display: inline-block;
        }
        
        .user-menu-btn {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .user-menu-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 5px;
            z-index: 1000;
        }
        
        .user-menu-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            border-bottom: 1px solid #eee;
        }
        
        .user-menu-content a:hover {
            background-color: #f1f1f1;
        }
        
        .user-menu-content a:last-child {
            border-bottom: none;
        }
        
        .user-menu:hover .user-menu-content {
            display: block;
        }
        
        .main-content {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-message {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .kittens-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .kitten-tile {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .kitten-tile:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .add-kitten-tile {
            background: rgba(255, 107, 107, 0.1);
            border: 2px dashed #ff6b6b;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            cursor: pointer;
            text-decoration: none;
            color: #ff6b6b;
        }
        
        .add-kitten-tile:hover {
            background: rgba(255, 107, 107, 0.2);
        }
        
        .add-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        
        .kitten-profile-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            cursor: pointer;
            border: 3px solid #ff6b6b;
        }
        
        .kitten-name {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .kitten-name a { color: inherit; text-decoration: none; }
        .sex-icon { font-size: 1.1em; margin-left: 6px; }
        
        .kitten-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .kitten-age {
            font-weight: bold;
            color: #ff6b6b;
        }
        
        .kitten-weight {
            color: #0984e3;
        }
        
        .appointment-alert {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .kitten-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            flex: 1;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff5252, #ff7979);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            flex: 1;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            transform: translateY(-1px);
        }
        
        .kitten-features {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .feature-icon {
            font-size: 1.2em;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
            position: relative;
        }
        
        .feature-icon:hover {
            color: #ff6b6b;
        }
        
        .tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 1000;
        }
        
        .feature-icon:hover .tooltip {
            opacity: 1;
        }
        
        .development-info {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #2196f3;
        }
        
        .development-stage {
            font-weight: bold;
            color: #1565c0;
            margin-bottom: 5px;
        }
        
        .development-description {
            font-size: 12px;
            color: #666;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }
            
            .kittens-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 10px;
            }
            
            .user-info {
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">🐱 CatControl</div>
        <div class="user-info">
            <div class="messages-icon" onclick="openMessages()">
                📧
                <?php if ($unreadMessages > 0): ?>
                    <span class="message-badge"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </div>
            
            <div class="user-menu">
                <button class="user-menu-btn"><?= htmlspecialchars($currentUser['username']) ?> ▼</button>
                <div class="user-menu-content">
                    <a href="profile.php">Profil bearbeiten</a>
                    <a href="change-password.php">Passwort ändern</a>
                    <a href="messages.php">Nachrichten</a>
                    <a href="public-kittens.php">Öffentliche Kätzchen</a>
                    <a href="logout.php">Abmelden</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="welcome-message">
            <h1>Willkommen, <?= htmlspecialchars($currentUser['username']) ?>!</h1>
            <p>Verwalten Sie Ihre Kätzchen einfach und übersichtlich</p>
        </div>

        <div class="kittens-grid">
            <!-- Add new kitten tile -->
            <a href="add-kitten.php" class="kitten-tile add-kitten-tile">
                <div class="add-icon">➕</div>
                <div>Neues Kätzchen anlegen</div>
            </a>

            <!-- Kitten tiles -->
            <?php foreach ($kittens as $kitten): ?>
                <?php
                $age = $kittenService->getKittenAge($kitten['birth_date']);
                $weight = $kittenService->getLatestWeight($kitten['id']);
                $appointments = $kittenService->getUpcomingAppointments($kitten['id']);
                $developmentInfo = $kittenService->getKittenDevelopmentInfo($age['total_days']);
                $sex = $kitten['sex'] ?? 'unbekannt';
                $sexIcon = $sex === 'kater' ? '♂️' : ($sex === 'katze' ? '♀️' : '❓');
                ?>
                <div class="kitten-tile">
                    <?php if (!empty($appointments)): ?>
                        <div class="appointment-alert" title="Termin in den nächsten 3 Tagen!">⚠️</div>
                    <?php endif; ?>
                    
                    <?php if ($kitten['profile_image']): ?>
                        <img src="uploads/kitten_images/<?= htmlspecialchars($kitten['profile_image']) ?>" 
                             alt="<?= htmlspecialchars($kitten['name']) ?>" 
                             class="kitten-profile-image"
                             onclick="location.href='add-kitten.php?kitten_id=<?= $kitten['id'] ?>'">
                    <?php else: ?>
                        <div class="kitten-profile-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 2em;" onclick="location.href='add-kitten.php?kitten_id=<?= $kitten['id'] ?>'">🐱</div>
                    <?php endif; ?>
                    
                    <div class="kitten-name"><a href="add-kitten.php?kitten_id=<?= $kitten['id'] ?>"><?= htmlspecialchars($kitten['name']) ?></a><span class="sex-icon"><?= $sexIcon ?></span></div>
                    
                    <div class="kitten-info">
                        <div class="kitten-age">
                            <?= $age['weeks'] ?> Wochen, <?= $age['days'] ?> Tage alt
                        </div>
                        <?php if ($weight): ?>
                            <div class="kitten-weight">Gewicht: <?= $weight ?>g</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="kitten-actions">
                        <button class="btn-primary" onclick="location.href='feeding.php?kitten_id=<?= $kitten['id'] ?>'">
                            Füttern
                        </button>
                        <button class="btn-secondary" onclick="location.href='veterinary.php?kitten_id=<?= $kitten['id'] ?>'">
                            Tierarzt
                        </button>
                    </div>
                    
                    <div class="kitten-features">
                        <span class="feature-icon" onclick="location.href='gallery.php?kitten_id=<?= $kitten['id'] ?>'">
                            🖼️
                            <div class="tooltip">Bildergalerie</div>
                        </span>
                        <span class="feature-icon" onclick="location.href='export.php?kitten_id=<?= $kitten['id'] ?>'">
                            📤
                            <div class="tooltip">Export</div>
                        </span>
                        <span class="feature-icon" onclick="location.href='statistics.php?kitten_id=<?= $kitten['id'] ?>'">
                            📊
                            <div class="tooltip">Gewichtsstatistik</div>
                        </span>
                        <span class="feature-icon" onclick="location.href='add-kitten.php?kitten_id=<?= $kitten['id'] ?>'">
                            ✏️
                            <div class="tooltip">Kätzchen bearbeiten</div>
                        </span>
                        <span class="feature-icon" onclick="showShareModal(<?= $kitten['id'] ?>, '<?= htmlspecialchars($kitten['name']) ?>')">
                            👥
                            <div class="tooltip">Benutzer hinzufügen</div>
                        </span>
                        <span class="feature-icon" onclick="togglePublic(<?= $kitten['id'] ?>, <?= $kitten['is_public'] ? 'true' : 'false' ?>)">
                            <?= $kitten['is_public'] ? '🌍' : '🔒' ?>
                            <div class="tooltip"><?= $kitten['is_public'] ? 'Öffentlich' : 'Privat' ?></div>
                        </span>
                    </div>
                    
                    <div class="development-info">
                        <div class="development-stage"><?= htmlspecialchars($developmentInfo['stage']) ?></div>
                        <div class="development-description"><?= htmlspecialchars($developmentInfo['info']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content" style="text-align: center; max-width: 800px;">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeShareModal()">&times;</span>
            <h2>Kätzchen teilen</h2>
            <div id="shareContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        function showImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function openMessages() {
            window.location.href = 'messages.php';
        }

        let currentShareKittenId = null;
        function showShareModal(kittenId, kittenName) {
            currentShareKittenId = kittenId;
            document.getElementById('shareModal').style.display = 'block';
            
            // Load share content via AJAX
            fetch(`ajax/share-kitten.php?kitten_id=${kittenId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('shareContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('shareContent').innerHTML = '<p>Fehler beim Laden der Benutzer.</p>';
                });
        }

        function shareWithSelected() {
            const select = document.getElementById('shareUserSelect');
            if (!select || !currentShareKittenId) return;
            const userId = parseInt(select.value, 10);
            if (!userId) return;
            fetch('ajax/share-kitten.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'share', kitten_id: String(currentShareKittenId), user_id: String(userId) })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    // Reload modal content to update lists
                    return fetch(`ajax/share-kitten.php?kitten_id=${currentShareKittenId}`)
                        .then(r => r.text())
                        .then(html => { document.getElementById('shareContent').innerHTML = html; });
                } else {
                    alert(data.message || 'Fehler beim Teilen');
                }
            }).catch(() => alert('Fehler beim Teilen'));
        }

        function unshareUser(userId) {
            if (!confirm('Zugriff wirklich entfernen?')) return;
            if (!currentShareKittenId) return;
            fetch('ajax/share-kitten.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'unshare', kitten_id: String(currentShareKittenId), user_id: String(userId) })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    // Reload modal content to update lists
                    return fetch(`ajax/share-kitten.php?kitten_id=${currentShareKittenId}`)
                        .then(r => r.text())
                        .then(html => { document.getElementById('shareContent').innerHTML = html; });
                } else {
                    alert(data.message || 'Fehler beim Entfernen');
                }
            }).catch(() => alert('Fehler beim Entfernen'));
        }

        function closeShareModal() {
            document.getElementById('shareModal').style.display = 'none';
        }

        function togglePublic(kittenId, isCurrentlyPublic) {
            const action = isCurrentlyPublic ? 'private' : 'public';
            const confirmMessage = isCurrentlyPublic 
                ? 'Kätzchen privat machen?' 
                : 'Kätzchen öffentlich machen? Alle Daten werden für andere Benutzer sichtbar.';
            
            if (confirm(confirmMessage)) {
                fetch('ajax/toggle-public.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        kitten_id: kittenId,
                        is_public: !isCurrentlyPublic
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Fehler: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Fehler beim Aktualisieren');
                });
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const imageModal = document.getElementById('imageModal');
            const shareModal = document.getElementById('shareModal');
            
            if (event.target == imageModal) {
                closeImageModal();
            }
            if (event.target == shareModal) {
                closeShareModal();
            }
        }

        // Handle escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
                closeShareModal();
            }
        });
    </script>
</body>
</html>