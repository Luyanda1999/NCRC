<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Initialize ActivityLogger
    require_once 'ActivityLogger.php';
    $activityLogger = new ActivityLogger();
    
    // First, check if user exists
    $stmt = $conn->prepare("SELECT id, username, is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $userExists = $stmt->fetch();
    
    if (!$userExists) {
        // User doesn't exist - log and return generic message
        $activityLogger->logAuth('login_failed', $username, [
            'reason' => 'user_not_found',
            'ip_address' => $ip_address
        ]);
        
        sleep(2); // Delay to prevent timing attacks
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid credentials'
        ]);
        exit;
    }
    
    // Check if account is locked
    $stmt = $conn->prepare("SELECT account_locked_until FROM users WHERE id = ?");
    $stmt->execute([$userExists['id']]);
    $lockStatus = $stmt->fetch();
    
    if ($lockStatus && $lockStatus['account_locked_until']) {
        $lockTime = new DateTime($lockStatus['account_locked_until']);
        $now = new DateTime();
        
        if ($lockTime > $now) {
            // Account is still locked
            $activityLogger->logAuth('login_failed', $username, [
                'reason' => 'account_locked',
                'ip_address' => $ip_address,
                'locked_until' => $lockStatus['account_locked_until']
            ]);
            
            echo json_encode([
                'success' => false, 
                'message' => 'Account is temporarily locked. Please try again later.'
            ]);
            exit;
        } else {
            // Lock has expired, reset it
            $stmt = $conn->prepare("UPDATE users SET account_locked_until = NULL, failed_attempts = 0 WHERE id = ?");
            $stmt->execute([$userExists['id']]);
        }
    }
    
    // Check for brute force attempts
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM activity_log 
        WHERE (username = :username OR ip_address = :ip_address)
        AND action IN ('login_failed', 'brute_force_attempt')
        AND timestamp > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([
        ':username' => $username,
        ':ip_address' => $ip_address
    ]);
    $result = $stmt->fetch();
    
    if ($result['attempts'] >= 10) {
        // Lock the account for 15 minutes
        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $stmt = $conn->prepare("UPDATE users SET account_locked_until = ? WHERE username = ?");
        $stmt->execute([$lockUntil, $username]);
        
        // Log brute force attempt
        $activityLogger->logSecurity('brute_force_attempt', [
            'username' => $username,
            'ip_address' => $ip_address,
            'attempts' => $result['attempts'],
            'account_locked_until' => $lockUntil
        ]);
        
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'message' => 'Too many failed attempts. Account locked for 15 minutes.'
        ]);
        exit;
    }
    
    // Get full user details with password hash
    // Try to select all columns dynamically
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User exists but is not active
        $activityLogger->logAuth('login_failed', $username, [
            'reason' => 'account_inactive',
            'ip_address' => $ip_address
        ]);
        
        sleep(2);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid credentials'
        ]);
        exit;
    }
    
    // Verify password
    if (password_verify($password, $user['password_hash'])) {
        // Check if password needs rehash
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
        }
        
        // Reset failed attempts and update login info
        // Use dynamic column checking
        $updateFields = [
            'last_login = NOW()',
            'login_count = IFNULL(login_count, 0) + 1'
        ];
        
        // Check if failed_attempts column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_attempts'")->fetch();
        if ($columnCheck) {
            $updateFields[] = 'failed_attempts = 0';
        }
        
        // Check if account_locked_until column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'account_locked_until'")->fetch();
        if ($columnCheck) {
            $updateFields[] = 'account_locked_until = NULL';
        }
        
        $updateSql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->execute([$user['id']]);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'viewer';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['department'] = $user['department'] ?? '';
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Generate session token for API calls
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
        
        // Log successful login
        $activityLogger->logAuth('login_success', $user['username'], [
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ]);
        
        // If using SessionMonitor
        if (class_exists('SessionMonitor')) {
            try {
                $sessionMonitor = new SessionMonitor();
                $sessionMonitor->recordLogin($user['id'], session_id());
            } catch (Exception $e) {
                error_log("SessionMonitor error: " . $e->getMessage());
            }
        }
        
        // Prepare user data for response
        $userData = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'] ?? $user['username'],
            'role' => $user['role'] ?? 'viewer'
        ];
        
        // Add optional fields if they exist
        if (isset($user['email'])) $userData['email'] = $user['email'];
        if (isset($user['department'])) $userData['department'] = $user['department'];
        if (isset($user['phone'])) $userData['phone'] = $user['phone'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => $userData,
            'session_token' => $_SESSION['session_token']
        ]);
        
    } else {
        // Increment failed attempts if column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_attempts'")->fetch();
        if ($columnCheck) {
            $stmt = $conn->prepare("UPDATE users SET failed_attempts = IFNULL(failed_attempts, 0) + 1 WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Check if we should lock the account (5 failed attempts)
            $stmt = $conn->prepare("SELECT failed_attempts FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $attempts = $stmt->fetch();
            
            if ($attempts['failed_attempts'] >= 5) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'account_locked_until'")->fetch();
                if ($columnCheck) {
                    $stmt = $conn->prepare("UPDATE users SET account_locked_until = ? WHERE id = ?");
                    $stmt->execute([$lockUntil, $user['id']]);
                }
                
                $activityLogger->logSecurity('account_locked', [
                    'username' => $user['username'],
                    'ip_address' => $ip_address,
                    'failed_attempts' => $attempts['failed_attempts'],
                    'locked_until' => $lockUntil
                ]);
            }
        }
        
        // Log failed attempt
        $activityLogger->logAuth('login_failed', $user['username'], [
            'reason' => 'invalid_password',
            'ip_address' => $ip_address
        ]);
        
        sleep(2); // Delay to prevent timing attacks
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid credentials'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>