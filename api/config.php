<?php
// API Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'fidelity_ncrc');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_name('NCRC_SESSION');
session_start();

// Database connection
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Enhanced authentication check
function isAuthenticated() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
        return false;
    }
    
    // Check session expiry (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    
    return $_SESSION['authenticated'] === true && $_SESSION['user_id'] > 0;
}

// Get current user info
function getCurrentUser() {
    if (isAuthenticated()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'email' => $_SESSION['email'] ?? null,
            'department' => $_SESSION['department'] ?? null
        ];
    }
    return null;
}

// Require authentication for specific roles
function requireAuth($requiredRoles = []) {
    if (!isAuthenticated()) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $user = getCurrentUser();
    
    if (!empty($requiredRoles) && !in_array($user['role'], $requiredRoles)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    return $user;
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// Prevent session fixation
session_regenerate_id(true);
?>