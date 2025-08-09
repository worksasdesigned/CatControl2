<?php
session_start();

require_once 'config/i18n.php';
require_once 'classes/User.php';

$userService = new User();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $country = trim($_POST['country']);
        $city = trim($_POST['city']);
        $allow_messages = isset($_POST['allow_messages']) ? 1 : 0;
        
        // Validation
        if (empty($username)) {
            $error = 'Benutzername ist erforderlich.';
        } elseif (empty($email)) {
            $error = 'E-Mail-Adresse ist erforderlich.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } else {
            // Check if username is already taken by another user
            if ($username !== $currentUser['username'] && $userService->usernameExists($username)) {
                $error = 'Benutzername ist bereits vergeben.';
            } else {
                $updateData = [
                    'username' => $username,
                    'email' => $email,
                    'country' => $country,
                    'city' => $city,
                    'allow_messages' => $allow_messages
                ];
                
                if ($userService->updateUser($currentUser['id'], $updateData)) {
                    $success = 'Profil wurde erfolgreich aktualisiert!';
                    $currentUser = $userService->getCurrentUser(); // Refresh user data
                } else {
                    $error = 'Fehler beim Aktualisieren des Profils.';
                }
            }
        }
    }
    
    // Handle background image upload
    if (isset($_POST['upload_background']) && isset($_FILES['background_image'])) {
        $uploadDir = 'uploads/backgrounds/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['background_image'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'Nur JPG, PNG, GIF und WebP Dateien sind erlaubt.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'Datei ist zu groß. Maximum 5MB.';
            } else {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bg_' . $currentUser['id'] . '_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Delete old background if exists
                    if (!empty($currentUser['custom_background'])) {
                        $oldFile = $uploadDir . $currentUser['custom_background'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    // Update user record
                    if ($userService->updateUser($currentUser['id'], ['custom_background' => $filename])) {
                        $success = 'Hintergrundbild wurde erfolgreich hochgeladen!';
                        $currentUser = $userService->getCurrentUser(); // Refresh user data
                    } else {
                        $error = 'Fehler beim Speichern des Hintergrundbildes.';
                    }
                } else {
                    $error = 'Fehler beim Hochladen der Datei.';
                }
            }
        } else {
            $error = 'Fehler beim Hochladen der Datei.';
        }
    }
    
    // Handle background removal
    if (isset($_POST['remove_background'])) {
        if (!empty($currentUser['custom_background'])) {
            $oldFile = 'uploads/backgrounds/' . $currentUser['custom_background'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
            
            if ($userService->updateUser($currentUser['id'], ['custom_background' => null])) {
                $success = 'Hintergrundbild wurde entfernt.';
                $currentUser = $userService->getCurrentUser(); // Refresh user data
            } else {
                $error = 'Fehler beim Entfernen des Hintergrundbildes.';
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
    <title><?= __('app.name') ?> - <?= __('profile.title') ?></title>
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
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            color: #ff6b6b;
            font-size: 1.5em;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
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
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            grid-column: 1 / -1;
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
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff5252, #ff7979);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e17055, #d63031);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #d63031, #e17055);
            transform: translateY(-1px);
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
            transition: border-color 0.3s;
        }
        
        .file-upload:hover {
            border-color: #ff6b6b;
        }
        
        .file-upload input[type="file"] {
            margin: 10px 0;
        }
        
        .current-background {
            width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .navigation-links {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }
        
        .nav-link {
            background: linear-gradient(135deg, #636e72, #2d3436);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .nav-link:hover {
            background: linear-gradient(135deg, #2d3436, #636e72);
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .navigation-links {
                flex-direction: column;
            }
            
            .main-content {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .form-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">🐱 <?= __('app.name') ?></div>
        <div>
            <span style="margin-right:10px;">
                <?= __('menu.language') ?>:
                <a href="<?= i18n_url_with_lang('de') ?>">DE</a>
                <a href="<?= i18n_url_with_lang('en') ?>">EN</a>
                <a href="<?= i18n_url_with_lang('fr') ?>">FR</a>
            </span>
            <a href="dashboard.php" class="back-btn">← <?= __('menu.back_to_dashboard') ?></a>
        </div>
    </header>

    <main class="main-content">
        <div class="profile-container">
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="navigation-links">
                <a href="change-password.php" class="nav-link"><?= __('menu.change_password') ?></a>
                <a href="messages.php" class="nav-link"><?= __('menu.messages') ?></a>
                <a href="public-kittens.php" class="nav-link"><?= __('menu.public_kittens') ?></a>
            </div>
            
            <!-- Profile Information -->
            <div class="form-section">
                <h2 class="section-title"><?= __('profile.title') ?></h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username"><?= __('profile.username') ?></label>
                        <input type="text" id="username" name="username" required 
                               value="<?= htmlspecialchars($currentUser['username']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><?= __('profile.email') ?></label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($currentUser['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="country"><?= __('profile.country') ?></label>
                        <input type="text" id="country" name="country" 
                               value="<?= htmlspecialchars($currentUser['country']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="city"><?= __('profile.city') ?></label>
                        <input type="text" id="city" name="city" 
                               value="<?= htmlspecialchars($currentUser['city']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="allow_messages" name="allow_messages" 
                                   <?= $currentUser['allow_messages'] ? 'checked' : '' ?>>
                            <label for="allow_messages"><?= __('profile.allow_messages') ?></label>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary"><?= __('profile.update') ?></button>
                </form>
            </div>
            
            <!-- Background Image -->
            <div class="form-section">
                <h2 class="section-title">Hintergrundbild</h2>
                
                <?php if (!empty($currentUser['custom_background'])): ?>
                    <img src="uploads/backgrounds/<?= htmlspecialchars($currentUser['custom_background']) ?>" 
                         alt="Aktueller Hintergrund" class="current-background">
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="file-upload">
                        <p>Neues Hintergrundbild hochladen</p>
                        <p style="font-size: 14px; color: #666; margin-bottom: 10px;">
                            Erlaubte Formate: JPG, PNG, GIF, WebP (max. 5MB)
                        </p>
                        <input type="file" name="background_image" accept="image/*" required>
                        <button type="submit" name="upload_background" class="btn-secondary">Hochladen</button>
                    </div>
                </form>
                
                <?php if (!empty($currentUser['custom_background'])): ?>
                    <form method="POST">
                        <button type="submit" name="remove_background" class="btn-danger" 
                                onclick="return confirm('Hintergrundbild wirklich entfernen?')">
                            Hintergrundbild entfernen
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>