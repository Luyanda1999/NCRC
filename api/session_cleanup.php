<?php
// session_cleanup.php - Run as cron job every 5 minutes

require_once 'config.php';
require_once 'ActivityLogger.php';

class SessionCleanup {
    private $conn;
    private $logger;
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->logger = new ActivityLogger();
    }
    
    public function cleanupInactiveSessions($timeoutMinutes = 30) {
        if (!$this->conn) return 0;
        
        try {
            // Clean up user_sessions table
            $sql = "UPDATE user_sessions 
                    SET logout_time = NOW(), 
                        is_active = FALSE,
                        duration_seconds = TIMESTAMPDIFF(SECOND, login_time, NOW())
                    WHERE is_active = TRUE 
                    AND last_activity < DATE_SUB(NOW(), INTERVAL :timeout MINUTE)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':timeout' => $timeoutMinutes]);
            $affected = $stmt->rowCount();
            
            if ($affected > 0) {
                $this->logger->logAuth('session_timeout', 'System', [
                    'sessions_terminated' => $affected,
                    'timeout_minutes' => $timeoutMinutes
                ]);
            }
            
            return $affected;
            
        } catch (PDOException $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function cleanupOldLogs($daysToKeep = 90) {
        if (!$this->conn) return 0;
        
        try {
            $sql = "DELETE FROM activity_log 
                    WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':days' => $daysToKeep]);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Log cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function cleanupFailedLogins($daysToKeep = 7) {
        if (!$this->conn) return 0;
        
        try {
            $sql = "DELETE FROM activity_log 
                    WHERE action LIKE 'login_failed%' 
                    AND timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':days' => $daysToKeep]);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Failed login cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}

// Run cleanup if executed directly
if (php_sapi_name() === 'cli') {
    $cleanup = new SessionCleanup();
    
    echo "Starting session cleanup...\n";
    $sessions = $cleanup->cleanupInactiveSessions(30);
    echo "Cleaned up $sessions inactive sessions\n";
    
    $logs = $cleanup->cleanupOldLogs(90);
    echo "Cleaned up $logs old activity logs\n";
    
    $failedLogins = $cleanup->cleanupFailedLogins(7);
    echo "Cleaned up $failedLogins failed login records\n";
    
    echo "Cleanup complete.\n";
}
?>