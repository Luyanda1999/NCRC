<?php
// session_monitor.php - Monitor user sessions and log activity

class SessionMonitor {
    private $conn;
    private $inactiveTimeout = 1800; // 30 minutes in seconds
    private $checkInterval = 300;    // Check every 5 minutes
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->createSessionTable();
    }
    
    private function createSessionTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            session_id VARCHAR(128) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            logout_time TIMESTAMP NULL,
            duration_seconds INT,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_active (is_active),
            INDEX idx_last_activity (last_activity)
        )";
        
        $this->conn->exec($sql);
    }
    
    public function recordLogin($user_id, $session_id) {
        $sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, is_active) 
                VALUES (:user_id, :session_id, :ip_address, :user_agent, TRUE)
                ON DUPLICATE KEY UPDATE 
                login_time = NOW(),
                last_activity = NOW(),
                logout_time = NULL,
                is_active = TRUE";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':session_id' => $session_id,
            ':ip_address' => $_SERVER['REMOTE_ADDR'],
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Log this activity
        $logger = new ActivityLogger();
        $logger->logAuth('session_started', $_SESSION['username'], [
            'session_id' => substr($session_id, 0, 10) . '...',
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    public function updateActivity($session_id) {
        $sql = "UPDATE user_sessions SET last_activity = NOW() WHERE session_id = :session_id AND is_active = TRUE";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':session_id' => $session_id]);
    }
    
    public function recordLogout($session_id) {
        $sql = "UPDATE user_sessions 
                SET logout_time = NOW(), 
                    is_active = FALSE,
                    duration_seconds = TIMESTAMPDIFF(SECOND, login_time, NOW())
                WHERE session_id = :session_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':session_id' => $session_id]);
    }
    
    public function cleanupInactiveSessions() {
        $sql = "UPDATE user_sessions 
                SET logout_time = NOW(), 
                    is_active = FALSE,
                    duration_seconds = TIMESTAMPDIFF(SECOND, login_time, NOW())
                WHERE is_active = TRUE 
                AND last_activity < DATE_SUB(NOW(), INTERVAL :timeout SECOND)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':timeout' => $this->inactiveTimeout]);
        
        $affected = $stmt->rowCount();
        
        if ($affected > 0) {
            // Log forced logouts
            $logger = new ActivityLogger();
            $logger->logAuth('session_timeout', 'System', [
                'sessions_terminated' => $affected,
                'timeout_seconds' => $this->inactiveTimeout
            ]);
        }
        
        return $affected;
    }
    
    public function getActiveSessions() {
        $sql = "SELECT us.*, u.username, u.full_name, u.role 
                FROM user_sessions us 
                JOIN users u ON us.user_id = u.id 
                WHERE us.is_active = TRUE 
                ORDER BY us.last_activity DESC";
        
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserSessionHistory($user_id, $limit = 50) {
        $sql = "SELECT * FROM user_sessions 
                WHERE user_id = :user_id 
                ORDER BY login_time DESC 
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Usage in other files:

// In auth.php after successful login:
$sessionMonitor = new SessionMonitor();
$sessionMonitor->recordLogin($user['id'], session_id());

// In logout.php:
$sessionMonitor = new SessionMonitor();
$sessionMonitor->recordLogout(session_id());

// Create a cron job or scheduled task to run cleanup
// */5 * * * * php /path/to/session_cleanup.php
?>