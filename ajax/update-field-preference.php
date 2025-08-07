<?php
session_start();

require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

$userService = new User();

if (!$userService->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$fieldName = $input['field_name'] ?? null;
$visible = isset($input['visible']) ? (bool)$input['visible'] : null;

if (!$fieldName || !is_bool($visible)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Eingabe']);
    exit;
}

$currentUser = $userService->getCurrentUser();
$result = $userService->updateFieldPreference($currentUser['id'], $fieldName, $visible);

if ($result['success']) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Fehler beim Speichern']);
}