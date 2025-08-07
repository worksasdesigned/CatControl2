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

// Get all public kittens
$publicKittens = $kittenService->getPublicKittens();

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
    <title>CatControl - Öffentliche Kätzchen</title>
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-title {
            text-align: center;
            color: #ff6b6b;
            font-size: 2.5em;
            margin-bottom: 40px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .kittens-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .kitten-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            position: relative;
        }
        
        .kitten-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .kitten-profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            border: 4px solid #ff6b6b;
            cursor: pointer;
        }
        
        .kitten-name {
            font-size: 1.8em;
            font-weight: bold;
            color: #ff6b6b;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .kitten-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .owner-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .owner-name {
            font-weight: bold;
            color: #ff6b6b;
            margin-bottom: 5px;
        }
        
        .owner-location {
            color: #666;
            font-size: 14px;
        }
        
        .development-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #2196f3;
        }
        
        .development-stage {
            font-weight: bold;
            color: #1565c0;
            margin-bottom: 8px;
        }
        
        .development-description {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }
        
        .no-kittens {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            margin-top: 50px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
        }
        
        .kitten-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-box {
            background: #fff;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #eee;
        }
        
        .stat-value {
            font-size: 1.2em;
            font-weight: bold;
            color: #ff6b6b;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
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
            text-align: center;
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
            .kittens-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .kitten-card {
                padding: 20px;
            }
            
            .page-title {
                font-size: 2em;
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
        <h1 class="page-title">🌍 Öffentliche Kätzchen</h1>
        
        <?php if (empty($publicKittens)): ?>
            <div class="no-kittens">
                <p>Derzeit sind keine Kätzchen öffentlich sichtbar.</p>
                <p style="margin-top: 10px; font-size: 14px;">Kätzchen werden hier angezeigt, wenn andere Benutzer sie als öffentlich markieren.</p>
            </div>
        <?php else: ?>
            <div class="kittens-grid">
                <?php foreach ($publicKittens as $kitten): ?>
                    <?php
                    $age = $kittenService->getKittenAge($kitten['birth_date']);
                    $weight = $kittenService->getLatestWeight($kitten['id']);
                    $developmentInfo = $kittenService->getKittenDevelopmentInfo($age['total_days']);
                    ?>
                    <div class="kitten-card">
                        <?php if ($kitten['profile_image']): ?>
                            <img src="uploads/kitten_images/<?= htmlspecialchars($kitten['profile_image']) ?>" 
                                 alt="<?= htmlspecialchars($kitten['name']) ?>" 
                                 class="kitten-profile-image"
                                 onclick="showImageModal('uploads/kitten_images/<?= htmlspecialchars($kitten['profile_image']) ?>')">
                        <?php else: ?>
                            <div class="kitten-profile-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 3em;">🐱</div>
                        <?php endif; ?>
                        
                        <div class="kitten-name"><?= htmlspecialchars($kitten['name']) ?></div>
                        
                        <div class="owner-info">
                            <div class="owner-name">Besitzer: <?= htmlspecialchars($kitten['owner_username']) ?></div>
                            <?php if ($kitten['owner_city'] && $kitten['owner_country']): ?>
                                <div class="owner-location">
                                    📍 <?= htmlspecialchars($kitten['owner_city']) ?>, <?= htmlspecialchars($kitten['owner_country']) ?>
                                </div>
                            <?php elseif ($kitten['owner_country']): ?>
                                <div class="owner-location">📍 <?= htmlspecialchars($kitten['owner_country']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="kitten-stats">
                            <div class="stat-box">
                                <div class="stat-value"><?= $age['weeks'] ?>w <?= $age['days'] ?>t</div>
                                <div class="stat-label">Alter</div>
                            </div>
                            <?php if ($weight): ?>
                                <div class="stat-box">
                                    <div class="stat-value"><?= $weight ?>g</div>
                                    <div class="stat-label">Gewicht</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="kitten-info">
                            <?php if ($kitten['color']): ?>
                                <div class="info-row">
                                    <span class="info-label">Farbe:</span>
                                    <span class="info-value"><?= htmlspecialchars($kitten['color']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($kitten['mother']): ?>
                                <div class="info-row">
                                    <span class="info-label">Mutter:</span>
                                    <span class="info-value"><?= htmlspecialchars($kitten['mother']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($kitten['found_location']): ?>
                                <div class="info-row">
                                    <span class="info-label">Fundort:</span>
                                    <span class="info-value"><?= htmlspecialchars($kitten['found_location']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($kitten['postal_code']): ?>
                                <div class="info-row">
                                    <span class="info-label">PLZ:</span>
                                    <span class="info-value"><?= htmlspecialchars($kitten['postal_code']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="development-info">
                            <div class="development-stage"><?= htmlspecialchars($developmentInfo['stage']) ?></div>
                            <div class="development-description"><?= htmlspecialchars($developmentInfo['info']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeImageModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>
</html>