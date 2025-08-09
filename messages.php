<?php
session_start();

require_once 'config/i18n.php';
require_once 'classes/User.php';
require_once 'classes/MessageService.php';

$userService = new User();
$messageService = new MessageService();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentUser = $userService->getCurrentUser();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $recipientId = (int)$_POST['recipient_id'];
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        
        if (empty($subject)) {
            $error = 'Betreff ist erforderlich.';
        } elseif (empty($message)) {
            $error = 'Nachricht ist erforderlich.';
        } else {
            $result = $messageService->sendMessage($currentUser['id'], $recipientId, $subject, $message);
            if (!empty($result['success'])) {
                $success = $result['message'] ?? 'Nachricht wurde erfolgreich gesendet!';
            } else {
                $error = $result['message'] ?? 'Fehler beim Senden der Nachricht.';
            }
        }
    }
    
    if (isset($_POST['reply_message_submit'])) {
        $originalMessageId = (int)$_POST['original_message_id'];
        $replyMessage = trim($_POST['reply_message']);
        
        if (empty($replyMessage)) {
            $error = 'Antwort ist erforderlich.';
        } else {
            $result = $messageService->replyToMessage($originalMessageId, $currentUser['id'], $replyMessage);
            if (!empty($result['success'])) {
                $success = $result['message'] ?? 'Antwort wurde erfolgreich gesendet!';
            } else {
                $error = $result['message'] ?? 'Fehler beim Senden der Antwort.';
            }
        }
    }
    
    if (isset($_POST['delete_message'])) {
        $messageId = (int)$_POST['message_id'];
        $result = $messageService->deleteMessage($messageId, $currentUser['id']);
        if (!empty($result['success'])) {
            $success = $result['message'] ?? 'Nachricht wurde gelöscht.';
        } else {
            $error = $result['message'] ?? __('errors.update_generic');
        }
    }
    
    if (isset($_POST['block_user'])) {
        $blockUserId = (int)$_POST['block_user_id'];
        $result = $userService->blockUser($currentUser['id'], $blockUserId);
        if (!empty($result['success'])) {
            $success = $result['message'] ?? 'Benutzer wurde blockiert.';
        } else {
            $error = $result['message'] ?? 'Fehler beim Blockieren des Benutzers.';
        }
    }
    
    if (isset($_POST['unblock_user'])) {
        $unblockUserId = (int)$_POST['unblock_user_id'];
        $result = $userService->unblockUser($currentUser['id'], $unblockUserId);
        if (!empty($result['success'])) {
            $success = $result['message'] ?? 'Benutzer wurde entsperrt.';
        } else {
            $error = $result['message'] ?? 'Fehler beim Entsperren des Benutzers.';
        }
    }
    
    if (isset($_POST['mark_read'])) {
        $messageId = (int)$_POST['message_id'];
        $messageService->markMessageAsRead($messageId, $currentUser['id']);
    }
}

// Get all messages for the current user
$messages = $messageService->getUserMessages($currentUser['id']);

// Get users that can receive messages (allow_messages = 1 and not blocked)
// Build from User service and filter blocked/blocked-by
$allUsers = $userService->getAllUsers($currentUser['id'], true);
$availableUsers = [];
foreach ($allUsers as $u) {
    if ($userService->isUserBlocked($currentUser['id'], $u['id'])) { // I block them
        continue;
    }
    if ($userService->isUserBlocked($u['id'], $currentUser['id'])) { // they block me
        continue;
    }
    $availableUsers[] = $u;
}

// Get blocked users
$blockedUsers = $userService->getBlockedUsers($currentUser['id']);

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
    <title><?= __('app.name') ?> - <?= __('messages.title') ?></title>
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
        
        .messages-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .messages-section, .compose-section {
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
        
        .message-item {
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 15px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .message-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .message-item.unread {
            background: #f8f9ff;
            border-left: 4px solid #ff6b6b;
        }
        
        .message-item.appointment {
            background: #fff8e1;
            border-left: 4px solid #ffa726;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .message-from {
            font-weight: bold;
            color: #333;
        }
        
        .message-date {
            color: #666;
            font-size: 14px;
        }
        
        .message-subject {
            font-weight: bold;
            margin-bottom: 10px;
            color: #ff6b6b;
        }
        
        .message-content {
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-reply {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
        }
        
        .btn-reply:hover {
            background: linear-gradient(135deg, #00cec9, #00b894);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e17055, #d63031);
            color: white;
        }
        
        .btn-delete:hover {
            background: linear-gradient(135deg, #d63031, #e17055);
        }
        
        .btn-block {
            background: linear-gradient(135deg, #636e72, #2d3436);
            color: white;
        }
        
        .btn-block:hover {
            background: linear-gradient(135deg, #2d3436, #636e72);
        }
        
        .reply-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: none;
        }
        
        .reply-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
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
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        
        .blocked-users {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        
        .blocked-user {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .btn-unblock {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .no-messages {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        
        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
            }
            
            .message-actions {
                justify-content: center;
            }
            
            .main-content {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .messages-section, .compose-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">🐱 <?= __('app.name') ?></div>
        <div class="back-btn">
            <?= __('menu.language') ?>:
            <a href="<?= i18n_url_with_lang('de') ?>">DE</a>
            <a href="<?= i18n_url_with_lang('en') ?>">EN</a>
            <a href="<?= i18n_url_with_lang('fr') ?>">FR</a>
            &nbsp;|&nbsp;
            <a href="dashboard.php" class="back-btn">← <?= __('menu.back_to_dashboard') ?></a>
        </div>
    </header>

    <main class="main-content">
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <div class="messages-container">
            <!-- Messages List -->
            <div class="messages-section">
                <h2 class="section-title">📬 <?= __('messages.title') ?></h2>
                
                                    <?php if (empty($messages)): ?>
                        <div class="no-messages"><?= __('messages.none') ?></div>
                    <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?= $message['is_read'] ? '' : 'unread' ?> <?= $message['message_type'] === 'appointment_reminder' ? 'appointment' : '' ?>">
                            <div class="message-header">
                                <span class="message-from">
                                    <?= $message['message_type'] !== 'user_message' ? 'System' : htmlspecialchars($message['sender_username'] ?? 'Unbekannt') ?>
                                </span>
                                <span class="message-date"><?= date('d.m.Y H:i', strtotime($message['created_at'])) ?></span>
                            </div>
                            
                            <div class="message-subject"><?= htmlspecialchars($message['subject']) ?></div>
                            <div class="message-content"><?= nl2br(htmlspecialchars($message['content'])) ?></div>
                            
                            <div class="message-actions">
                                <?php if (!$message['is_read']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <button type="submit" name="mark_read" class="btn-small btn-reply"><?= __('messages.mark_as_read') ?? 'Als gelesen markieren' ?></button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($message['message_type'] === 'user_message' && $message['sender_id'] !== $currentUser['id']): ?>
                                                                            <button onclick="toggleReply(<?= $message['id'] ?>)" class="btn-small btn-reply"><?= __('messages.reply') ?? 'Antworten' ?></button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="block_user_id" value="<?= $message['sender_id'] ?>">
                                        <button type="submit" name="block_user" class="btn-small btn-block" 
                                                onclick="return confirm('Benutzer blockieren?')"><?= __('messages.block') ?></button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <button type="submit" name="delete_message" class="btn-small btn-delete" 
                                            onclick="return confirm('Nachricht löschen?')">❌</button>
                                </form>
                            </div>
                            
                            <?php if ($message['message_type'] === 'user_message' && $message['sender_id'] !== $currentUser['id']): ?>
                                <div id="reply-<?= $message['id'] ?>" class="reply-form">
                                    <form method="POST">
                                        <input type="hidden" name="original_message_id" value="<?= $message['id'] ?>">
                                        <div class="form-group">
                                                                                            <label><?= __('messages.reply_label') ?? 'Antwort:' ?></label>
                                                <textarea name="reply_message" required placeholder="<?= __('messages.reply_placeholder') ?? 'Ihre Antwort...' ?>"></textarea>
                                            </div>
                                            <button type="submit" name="reply_message_submit" class="btn-primary"><?= __('messages.reply_send') ?? 'Antwort senden' ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Compose Message -->
            <div class="compose-section">
                <h2 class="section-title">✉️ <?= __('messages.new') ?? 'Neue Nachricht' ?></h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="recipient_id"><?= __('messages.recipient') ?? 'Empfänger:' ?></label>
                        <select id="recipient_id" name="recipient_id" required>
                            <option value=""><?= __('messages.recipient_placeholder') ?? 'Empfänger wählen...' ?></option>
                            <?php foreach ($availableUsers as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject"><?= __('messages.subject') ?? 'Betreff:' ?></label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message"><?= __('messages.message') ?? 'Nachricht:' ?></label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn-primary"><?= __('messages.send') ?? 'Nachricht senden' ?></button>
                </form>
                
                <?php if (!empty($blockedUsers)): ?>
                    <div class="blocked-users">
                        <h3 style="color: #666; margin-bottom: 15px;">🚫 <?= __('messages.blocked_users') ?></h3>
                        <?php foreach ($blockedUsers as $blockedUser): ?>
                            <div class="blocked-user">
                                <span><?= htmlspecialchars($blockedUser['username']) ?></span>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="unblock_user_id" value="<?= $blockedUser['id'] ?>">
                                                                         <button type="submit" name="unblock_user" class="btn-unblock"><?= __('messages.unblock') ?? 'Entsperren' ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        function toggleReply(messageId) {
            const replyForm = document.getElementById('reply-' + messageId);
            if (replyForm.classList.contains('active')) {
                replyForm.classList.remove('active');
            } else {
                // Hide all other reply forms
                document.querySelectorAll('.reply-form').forEach(form => {
                    form.classList.remove('active');
                });
                replyForm.classList.add('active');
            }
        }
    </script>
</body>
</html>