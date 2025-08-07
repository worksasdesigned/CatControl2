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
$kitten_id = isset($_GET['kitten_id']) ? (int)$_GET['kitten_id'] : 0;

if (!$kitten_id) {
    header('Location: dashboard.php');
    exit;
}

// Check if user has access to this kitten
if (!$kittenService->hasAccess($kitten_id, $currentUser['id'])) {
    header('Location: dashboard.php');
    exit;
}

$kitten = $kittenService->getKitten($kitten_id);
if (!$kitten) {
    header('Location: dashboard.php');
    exit;
}

// Handle image upload
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        $fileType = $_FILES['image']['type'];
        $fileSize = $_FILES['image']['size'];
        $caption = trim($_POST['caption'] ?? '');
        
        if (!in_array($fileType, $allowedTypes)) {
            $uploadMessage = '<div class="message error">Nur JPEG, PNG, GIF und WebP Dateien sind erlaubt.</div>';
        } elseif ($fileSize > $maxSize) {
            $uploadMessage = '<div class="message error">Die Datei ist zu groß. Maximum: 10MB.</div>';
        } else {
            $uploadDir = 'uploads/kitten_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('kitten_' . $kitten_id . '_') . '.' . $fileExtension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                // Save to database
                try {
                    $config = require 'config/database.php';
                    $pdo = new PDO(
                        "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
                        $config['username'],
                        $config['password'],
                        $config['options']
                    );
                    
                    $stmt = $pdo->prepare("INSERT INTO kitten_images (kitten_id, filename, original_name, caption) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$kitten_id, $filename, $_FILES['image']['name'], $caption]);
                    
                    $uploadMessage = '<div class="message success">Bild erfolgreich hochgeladen!</div>';
                } catch (PDOException $e) {
                    unlink($filepath); // Remove uploaded file on database error
                    $uploadMessage = '<div class="message error">Datenbankfehler beim Speichern des Bildes.</div>';
                }
            } else {
                $uploadMessage = '<div class="message error">Fehler beim Hochladen der Datei.</div>';
            }
        }
    } else {
        $uploadMessage = '<div class="message error">Bitte wählen Sie eine Datei aus.</div>';
    }
}

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $imageId = (int)$_POST['image_id'];
    
    try {
        $config = require 'config/database.php';
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
            $config['username'],
            $config['password'],
            $config['options']
        );
        
        // Get image info before deletion
        $stmt = $pdo->prepare("SELECT filename FROM kitten_images WHERE id = ? AND kitten_id = ?");
        $stmt->execute([$imageId, $kitten_id]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM kitten_images WHERE id = ? AND kitten_id = ?");
            $stmt->execute([$imageId, $kitten_id]);
            
            // Delete file
            $filepath = 'uploads/kitten_images/' . $image['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            $uploadMessage = '<div class="message success">Bild erfolgreich gelöscht!</div>';
        }
    } catch (PDOException $e) {
        $uploadMessage = '<div class="message error">Fehler beim Löschen des Bildes.</div>';
    }
}

// Get all images for this kitten
$images = [];
try {
    $config = require 'config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );
    
    $stmt = $pdo->prepare("SELECT * FROM kitten_images WHERE kitten_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$kitten_id]);
    $images = $stmt->fetchAll();
} catch (PDOException $e) {
    $images = [];
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
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildergalerie - <?= htmlspecialchars($kitten['name']) ?> - CatControl</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .overlay {
            background-color: rgba(255, 255, 255, 0.9);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .header p {
            text-align: center;
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .back-button {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .back-button:hover {
            background: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }
        
        .upload-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .upload-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input[type="file"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #ff6b6b;
        }
        
        .btn-danger:hover {
            background: #ff5252;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .image-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .image-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .image-card img:hover {
            transform: scale(1.05);
        }
        
        .image-info {
            padding: 20px;
        }
        
        .image-info h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .image-info p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .image-actions {
            display: flex;
            gap: 10px;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .no-images {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .no-images h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .no-images p {
            color: #666;
            font-size: 1.1em;
        }
        
        /* Modal for fullscreen image view */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }
        
        .modal-content {
            position: relative;
            margin: auto;
            display: block;
            width: 90%;
            max-width: 900px;
            max-height: 90%;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .modal img {
            width: 100%;
            height: auto;
            border-radius: 10px;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
        }
        
        .close:hover {
            color: #ff6b6b;
        }
        
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            padding: 10px;
            user-select: none;
            z-index: 1001;
        }
        
        .nav-arrow:hover {
            color: #667eea;
        }
        
        .prev {
            left: 20px;
        }
        
        .next {
            right: 20px;
        }
        
        @media (max-width: 768px) {
            .gallery {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .container {
                padding: 10px;
            }
            
            .overlay {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="overlay">
        <div class="container">
            <a href="dashboard.php" class="back-button">← Zurück zum Dashboard</a>
            
            <div class="header">
                <h1>🖼️ Bildergalerie</h1>
                <p>Kätzchen: <?= htmlspecialchars($kitten['name']) ?></p>
            </div>
            
            <?= $uploadMessage ?>
            
            <div class="upload-section">
                <h2>📸 Neues Bild hochladen</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="image">Bild auswählen (JPEG, PNG, GIF, WebP - max. 10MB):</label>
                        <input type="file" name="image" id="image" accept="image/*" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="caption">Bildunterschrift (optional):</label>
                        <input type="text" name="caption" id="caption" placeholder="Beschreibung des Bildes...">
                    </div>
                    
                    <button type="submit" name="upload_image" class="btn">📸 Bild hochladen</button>
                </form>
            </div>
            
            <?php if (empty($images)): ?>
                <div class="no-images">
                    <h3>📷 Noch keine Bilder vorhanden</h3>
                    <p>Laden Sie das erste Bild von <?= htmlspecialchars($kitten['name']) ?> hoch!</p>
                </div>
            <?php else: ?>
                <div class="gallery">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="image-card">
                            <img 
                                src="uploads/kitten_images/<?= htmlspecialchars($image['filename']) ?>" 
                                alt="<?= htmlspecialchars($image['caption'] ?: $image['original_name']) ?>"
                                onclick="openModal(<?= $index ?>)"
                            >
                            <div class="image-info">
                                <h3><?= htmlspecialchars($image['original_name']) ?></h3>
                                <?php if ($image['caption']): ?>
                                    <p><?= htmlspecialchars($image['caption']) ?></p>
                                <?php endif; ?>
                                <p><small>Hochgeladen: <?= date('d.m.Y H:i', strtotime($image['upload_date'])) ?></small></p>
                                
                                <div class="image-actions">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Sind Sie sicher, dass Sie dieses Bild löschen möchten?')">
                                        <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                                        <button type="submit" name="delete_image" class="btn btn-danger">🗑️ Löschen</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for fullscreen view -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <span class="nav-arrow prev" onclick="changeImage(-1)">&#10094;</span>
        <span class="nav-arrow next" onclick="changeImage(1)">&#10095;</span>
        <div class="modal-content">
            <img id="modalImage" src="" alt="">
        </div>
    </div>
    
    <script>
        const images = <?= json_encode($images) ?>;
        let currentImageIndex = 0;
        
        function openModal(index) {
            currentImageIndex = index;
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImg.src = 'uploads/kitten_images/' + images[index].filename;
            modalImg.alt = images[index].caption || images[index].original_name;
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        function changeImage(direction) {
            currentImageIndex += direction;
            
            if (currentImageIndex >= images.length) {
                currentImageIndex = 0;
            } else if (currentImageIndex < 0) {
                currentImageIndex = images.length - 1;
            }
            
            const modalImg = document.getElementById('modalImage');
            modalImg.src = 'uploads/kitten_images/' + images[currentImageIndex].filename;
            modalImg.alt = images[currentImageIndex].caption || images[currentImageIndex].original_name;
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            } else if (e.key === 'ArrowLeft') {
                changeImage(-1);
            } else if (e.key === 'ArrowRight') {
                changeImage(1);
            }
        });
        
        // Close modal when clicking outside the image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>