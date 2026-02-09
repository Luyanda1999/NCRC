<?php
// auth_middleware.php - Centralized authentication middleware

require_once 'config.php';
require_once 'ActivityLogger.php';

class AuthMiddleware {
    private $logger;
    
    public function __construct() {
        $this->logger = new ActivityLogger();
    }
    
    public function requireAuthentication($requiredRole = null) {
        if (!isAuthenticated()) {
            $this->logger->logSecurity('access_denied', [
                'reason' => 'not_authenticated',
                'path' => $_SERVER['REQUEST_URI'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
            
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'redirect' => 'login.html'
            ]);
            exit;
        }
        
        $user = getCurrentUser();
        
        if ($requiredRole && $user['role'] !== $requiredRole) {
            $this->logger->logSecurity('access_denied', [
                'reason' => 'insufficient_permissions',
                'user_id' => $user['id'],
                'user_role' => $user['role'],
                'required_role' => $requiredRole,
                'path' => $_SERVER['REQUEST_URI']
            ]);
            
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient permissions',
                'required_role' => $requiredRole
            ]);
            exit;
        }
        
        return $user;
    }
    
    public function validateCSRFToken($token) {
        if (!validateCSRFToken($token)) {
            $this->logger->logSecurity('csrf_attempt', [
                'provided_token' => substr($token, 0, 10) . '...',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'path' => $_SERVER['REQUEST_URI']
            ]);
            
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid security token'
            ]);
            exit;
        }
        return true;
    }
    
    public function logActivity($category, $action, $details = []) {
        $user = getCurrentUser();
        $this->logger->log($category, $action, array_merge($details, [
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? 'System'
        ]));
    }
}

// Create global instance
$authMiddleware = new AuthMiddleware();

// Helper functions
function requireAuth($role = null) {
    global $authMiddleware;
    return $authMiddleware->requireAuthentication($role);
}

function validateCSRF($token) {
    global $authMiddleware;
    return $authMiddleware->validateCSRFToken($token);
}

function logAction($category, $action, $details = []) {
    global $authMiddleware;
    $authMiddleware->logActivity($category, $action, $details);
}
?>