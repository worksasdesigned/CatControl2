<?php
session_start();

require_once 'classes/User.php';

$userService = new User();
$userService->logout();

header('Location: index.php');
exit;
?>