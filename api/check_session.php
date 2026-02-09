<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isAuthenticated()) {
    $user = getCurrentUser();
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => $user
    ]);
} else {
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
}
?>