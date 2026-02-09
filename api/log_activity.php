<?php
// log_activity.php - API for logging activities from frontend

require_once 'config.php';
require_once 'ActivityLogger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

// Authenticate request
if (!isAuthenticated()) {
    // Allow some public actions
    $publicActions = ['login_attempt', 'session_timeout'];
    if (!in_array($data['action'], $publicActions)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

try {
    $logger = new ActivityLogger();
    
    // Map frontend actions to activity types
    $actionMap = [
        'search_performed' => ['category' => 'system', 'action' => 'search'],
        'report_viewed' => ['category' => 'incident', 'action' => 'view'],
        'data_exported' => ['category' => 'incident', 'action' => 'export'],
        'dashboard_viewed' => ['category' => 'system', 'action' => 'page_view'],
        'settings_changed' => ['category' => 'system', 'action' => 'config_change']
    ];
    
    if (isset($actionMap[$data['action']])) {
        $mapping = $actionMap[$data['action']];
        $logger->log($mapping['category'], $mapping['action'], $data['details'] ?? []);
    } else {
        // Generic log
        $logger->log('system', $data['action'], $data['details'] ?? []);
    }
    
    echo json_encode(['success' => true, 'message' => 'Activity logged']);
    
} catch (Exception $e) {
    error_log("Activity logging error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>