<?php
// ActivityLogger.php - Comprehensive activity logging system

require_once 'config.php';

class ActivityLogger {
    private $conn;
    private $user_id;
    private $username;
    private $ip_address;
    private $user_agent;
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->ip_address = $this->getClientIP();
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Get current user from session
        if (isset($_SESSION['user_id'])) {
            $this->user_id = $_SESSION['user_id'];
            $this->username = $_SESSION['username'] ?? 'Unknown';
        } else {
            $this->user_id = null;
            $this->username = 'System';
        }
    }
    
    /**
     * Log user authentication activity
     */
    public function logAuth($action, $username = null, $details = []) {
        $logData = [
            'activity_type' => 'auth',
            'action' => $action,
            'username' => $username ?: $this->username,
            'details' => array_merge($details, [
                'ip_address' => $this->ip_address,
                'user_agent' => substr($this->user_agent, 0, 500)
            ]),
            'severity' => in_array($action, ['login_failed', 'brute_force']) ? 'warning' : 'info'
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Log incident-related activities
     */
    public function logIncident($action, $incident_id, $details = []) {
        $logData = [
            'activity_type' => 'incident',
            'action' => $action,
            'username' => $this->username,
            'incident_id' => $incident_id,
            'details' => $details,
            'resource_type' => 'incident',
            'resource_id' => $incident_id
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Log file operations
     */
    public function logFile($action, $file_id, $incident_id = null, $details = []) {
        $logData = [
            'activity_type' => 'file',
            'action' => $action,
            'username' => $this->username,
            'incident_id' => $incident_id,
            'details' => array_merge($details, [
                'file_id' => $file_id
            ]),
            'resource_type' => 'file',
            'resource_id' => $file_id
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Log user management activities
     */
    public function logUser($action, $target_user_id = null, $details = []) {
        $logData = [
            'activity_type' => 'user',
            'action' => $action,
            'username' => $this->username,
            'details' => array_merge($details, [
                'target_user_id' => $target_user_id
            ]),
            'severity' => 'security',
            'resource_type' => 'user',
            'resource_id' => $target_user_id
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Log system activities
     */
    public function logSystem($action, $details = []) {
        $logData = [
            'activity_type' => 'system',
            'action' => $action,
            'username' => $this->username,
            'details' => $details
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Log security events
     */
    public function logSecurity($action, $details = []) {
        $logData = [
            'activity_type' => 'security',
            'action' => $action,
            'username' => $this->username ?: 'Unknown',
            'details' => array_merge($details, [
                'ip_address' => $this->ip_address
            ]),
            'severity' => 'security'
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Generic log method
     */
    public function log($activity_type, $action, $details = []) {
        $logData = [
            'activity_type' => $activity_type,
            'action' => $action,
            'username' => $this->username,
            'details' => $details
        ];
        
        $this->saveLog($logData);
    }
    
    /**
     * Save log to database
     */
    private function saveLog($logData) {
        if (!$this->conn) {
            error_log("ActivityLogger: Database connection failed");
            return;
        }
        
        try {
            // Get activity type ID
            $typeId = null;
            if (isset($logData['activity_type']) && isset($logData['action'])) {
                $stmt = $this->conn->prepare(
                    "SELECT id FROM activity_types WHERE category = ? AND action = ?"
                );
                $stmt->execute([$logData['activity_type'], $logData['action']]);
                $type = $stmt->fetch();
                $typeId = $type ? $type['id'] : null;
            }
            
            // Insert log entry
            $sql = "INSERT INTO activity_log (
                activity_type_id,
                user_id,
                username,
                ip_address,
                user_agent,
                action,
                details,
                incident_id,
                resource_type,
                resource_id,
                severity,
                timestamp
            ) VALUES (
                :type_id,
                :user_id,
                :username,
                :ip_address,
                :user_agent,
                :action,
                :details,
                :incident_id,
                :resource_type,
                :resource_id,
                :severity,
                NOW()
            )";
            
            $stmt = $this->conn->prepare($sql);
            
            $stmt->execute([
                ':type_id' => $typeId,
                ':user_id' => $this->user_id,
                ':username' => $logData['username'],
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':action' => $logData['action'],
                ':details' => json_encode($logData['details'] ?? [], JSON_UNESCAPED_UNICODE),
                ':incident_id' => $logData['incident_id'] ?? null,
                ':resource_type' => $logData['resource_type'] ?? null,
                ':resource_id' => $logData['resource_id'] ?? null,
                ':severity' => $logData['severity'] ?? 'info'
            ]);
            
        } catch (PDOException $e) {
            error_log("ActivityLogger error: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Get activity logs with filters
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        if (!$this->conn) return [];
        
        try {
            $sql = "SELECT al.*, at.category, at.description as type_description 
                    FROM activity_log al 
                    LEFT JOIN activity_types at ON al.activity_type_id = at.id 
                    WHERE 1=1";
            
            $params = [];
            $conditions = [];
            
            // Apply filters
            if (!empty($filters['user_id'])) {
                $conditions[] = "al.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['username'])) {
                $conditions[] = "al.username LIKE :username";
                $params[':username'] = '%' . $filters['username'] . '%';
            }
            
            if (!empty($filters['action'])) {
                $conditions[] = "al.action LIKE :action";
                $params[':action'] = '%' . $filters['action'] . '%';
            }
            
            if (!empty($filters['severity'])) {
                $conditions[] = "al.severity = :severity";
                $params[':severity'] = $filters['severity'];
            }
            
            if (!empty($filters['start_date'])) {
                $conditions[] = "al.timestamp >= :start_date";
                $params[':start_date'] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $conditions[] = "al.timestamp <= :end_date";
                $params[':end_date'] = $filters['end_date'];
            }
            
            if (!empty($filters['resource_type'])) {
                $conditions[] = "al.resource_type = :resource_type";
                $params[':resource_type'] = $filters['resource_type'];
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY al.timestamp DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($sql);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON details
            foreach ($logs as &$log) {
                if (!empty($log['details'])) {
                    $log['details'] = json_decode($log['details'], true);
                }
            }
            
            return $logs;
            
        } catch (PDOException $e) {
            error_log("Get logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get statistics
     */
    public function getStatistics($period = 'today') {
        if (!$this->conn) return [];
        
        try {
            $dateCondition = "";
            switch ($period) {
                case 'today':
                    $dateCondition = "WHERE DATE(timestamp) = CURDATE()";
                    break;
                case 'week':
                    $dateCondition = "WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $dateCondition = "WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
            
            $stats = [];
            
            // Total logs
            $sql = "SELECT COUNT(*) as total FROM activity_log $dateCondition";
            $stmt = $this->conn->query($sql);
            $stats['total'] = $stmt->fetch()['total'];
            
            // Logs by severity
            $sql = "SELECT severity, COUNT(*) as count FROM activity_log $dateCondition GROUP BY severity";
            $stmt = $this->conn->query($sql);
            $stats['by_severity'] = $stmt->fetchAll();
            
            // Logs by user
            $sql = "SELECT username, COUNT(*) as count FROM activity_log $dateCondition GROUP BY username ORDER BY count DESC LIMIT 10";
            $stmt = $this->conn->query($sql);
            $stats['top_users'] = $stmt->fetchAll();
            
            // Logs by action
            $sql = "SELECT action, COUNT(*) as count FROM activity_log $dateCondition GROUP BY action ORDER BY count DESC LIMIT 10";
            $stmt = $this->conn->query($sql);
            $stats['top_actions'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [];
        }
    }
}

// Create global instance
$activityLogger = new ActivityLogger();
?>