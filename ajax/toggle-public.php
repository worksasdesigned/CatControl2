<?php
session_start();

require_once '../classes/User.php';
require_once '../classes/Kitten.php';

header('Content-Type: application/json');

$userService = new User();
$kittenService = new Kitten();

// Check if user is logged in
if (!$userService->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$currentUser = $userService->getCurrentUser();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['kitten_id']) || !isset($input['is_public'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

$kittenId = (int)$input['kitten_id'];
$isPublic = (bool)$input['is_public'];

// Check if user has access to this kitten
if (!$kittenService->hasAccess($kittenId, $currentUser['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

// Update kitten
$result = $kittenService->updateKitten($kittenId, $currentUser['id'], ['is_public' => $isPublic ? 1 : 0]);

echo json_encode($result);
?>