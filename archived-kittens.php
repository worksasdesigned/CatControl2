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

// Get archived kittens for this user
$archivedKittens = $kittenService->getArchivedKittensForUser($currentUser['id']);

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
    <title><?= __('app.name') ?> - <?= __('menu.archived_kittens') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-image: url('<?= $backgroundImage ?>'); background-size: cover; background-position: center; background-attachment: fixed; min-height: 100vh; font-family: 'Arial', sans-serif; color: #333; }
        .header { background: rgba(255, 255, 255, 0.95); padding: 15px 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8em; color: #ff6b6b; font-weight: bold; }
        .back-btn { background: linear-gradient(135deg, #74b9ff, #0984e3); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .back-btn:hover { background: linear-gradient(135deg, #0984e3, #74b9ff); transform: translateY(-1px); }
        .main-content { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { text-align: center; color: #ff6b6b; font-size: 2.2em; margin-bottom: 30px; }
        .kittens-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .kitten-card { background: rgba(255, 255, 255, 0.95); border-radius: 15px; padding: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); transition: all 0.3s; position: relative; }
        .kitten-card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); }
        .kitten-profile-image { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin: 0 auto 15px; display: block; border: 4px solid #7f8c8d; cursor: pointer; }
        .kitten-name { font-size: 1.6em; font-weight: bold; color: #333; text-align: center; margin-bottom: 10px; }
        .kitten-info { color: #666; font-size: 14px; text-align: center; }
        .no-kittens { text-align: center; color: #666; font-size: 1.1em; margin-top: 50px; padding: 40px; background: rgba(255, 255, 255, 0.9); border-radius: 15px; }
        .unarchive-btn { margin-top: 12px; background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; display: block; width: 100%; }
        .unarchive-btn:hover { background: linear-gradient(135deg, #7f8c8d, #95a5a6); }
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
        <h1 class="page-title">📦 <?= __('menu.archived_kittens') ?></h1>
        <?php if (empty($archivedKittens)): ?>
            <div class="no-kittens"><?= __('archived_kittens.none') ?></div>
        <?php else: ?>
            <div class="kittens-grid">
                <?php foreach ($archivedKittens as $kitten): ?>
                    <?php
                        $age = $kittenService->getKittenAge($kitten['birth_date']);
                    ?>
                    <div class="kitten-card">
                        <?php if ($kitten['profile_image']): ?>
                            <img src="uploads/kitten_images/<?= htmlspecialchars($kitten['profile_image']) ?>" alt="<?= htmlspecialchars($kitten['name']) ?>" class="kitten-profile-image" onclick="location.href='add-kitten.php?kitten_id=<?= $kitten['id'] ?>'">
                        <?php else: ?>
                            <div class="kitten-profile-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 2.2em;" onclick="location.href='add-kitten.php?kitten_id=<?= $kitten['id'] ?>'">🐱</div>
                        <?php endif; ?>
                        <div class="kitten-name"><?= htmlspecialchars($kitten['name']) ?></div>
                        <div class="kitten-info"><?= __('archived_kittens.age') ?>: <?= $age['weeks'] ?>w <?= $age['days'] ?>t</div>
                        <button class="unarchive-btn" onclick="unarchive(<?= (int)$kitten['id'] ?>)"><?= __('archived_kittens.unarchive') ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function unarchive(kittenId) {
            fetch('ajax/toggle-archive.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ kitten_id: kittenId, is_archived: false })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || '<?= __('errors.update_generic') ?>');
                }
            }).catch(() => alert('<?= __('errors.update_generic') ?>'));
        }
    </script>
</body>
</html>