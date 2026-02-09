<?php
// logout.php - Enhanced version
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// Get user info before destroying session
$username = $_SESSION['username'] ?? 'Unknown';
$userId = $_SESSION['user_id'] ?? 0;

// Log logout action
if ($userId) {
    try {
        $conn = getDBConnection();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO activity_log (action, user, user_id) VALUES (?, ?, ?)");
            $stmt->execute(["User logged out", $username, $userId]);
            
            // Also update users table with last logout time
            $stmt = $conn->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        }
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Destroy session completely
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Return success
echo json_encode([
    'success' => true, 
    'message' => 'Logged out successfully',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>