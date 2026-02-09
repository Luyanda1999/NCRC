<?php
// incidents.php - Complete Incident Reporting API

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'fidelity_ncrc');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', '../uploads/incidents/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0777, true)) {
        error_log("Failed to create upload directory: " . UPLOAD_DIR);
    }
}

// Allowed file types
$allowedFileTypes = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'text/plain' => 'txt'
];

// Create database connection
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Debug: Log all incoming requests
error_log("API Request - Action: $action, Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Validate authentication (in production, use proper session/auth)
function authenticate() {
    // For demo purposes, allow all requests
    return true;
}

// Check if required tables exist
function checkTablesExist($conn) {
    $tables = ['incidents', 'incident_files'];
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if (!$stmt->fetch()) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    return true;
}

// Create tables if they don't exist
function createTables($conn) {
    $sql = [];
    
    // Incidents table
    $sql[] = "CREATE TABLE IF NOT EXISTS incidents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(100) NOT NULL,
        incident_datetime DATETIME NOT NULL,
        location VARCHAR(255) NOT NULL,
        reported_by VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
        impact_level ENUM('minimal', 'minor', 'moderate', 'major', 'severe') NOT NULL,
        evidence TEXT,
        status ENUM('open', 'investigating', 'contained', 'resolved', 'closed') NOT NULL DEFAULT 'open',
        actions_taken TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_type (type),
        INDEX idx_datetime (incident_datetime)
    )";
    
    // Incident files table
    $sql[] = "CREATE TABLE IF NOT EXISTS incident_files (
        id INT PRIMARY KEY AUTO_INCREMENT,
        incident_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_incident_id (incident_id)
    )";
    
    foreach ($sql as $query) {
        try {
            $conn->exec($query);
        } catch (PDOException $e) {
            error_log("Failed to create table: " . $e->getMessage());
            return false;
        }
    }
    return true;
}

// Main API router
if (!authenticate()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check and create tables on first use
if ($action === 'submit_report' || $action === 'test_connection') {
    $conn = getDBConnection();
    if ($conn && !checkTablesExist($conn)) {
        if (!createTables($conn)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create database tables']);
            exit();
        }
    }
}

switch ($action) {
    case 'submit_report':
        handleSubmitReport();
        break;
    case 'get_reports':
        handleGetReports();
        break;
    case 'get_report':
        handleGetReport();
        break;
    case 'update_status':
        handleUpdateStatus();
        break;
    case 'update_report':
        handleUpdateReport();
        break;
    case 'delete_report':
        handleDeleteReport();
        break;
    case 'close_report':
        handleCloseReport();
        break;
    case 'export':
        handleExport();
        break;
    case 'get_stats':
        handleGetStats();
        break;
    case 'get_categories':
        handleGetCategories();
        break;
    case 'upload_file':
        handleUploadFile();
        break;
    case 'delete_file':
        handleDeleteFile();
        break;
    case 'search':
        handleSearch();
        break;
    case 'get_recent':
        handleGetRecent();
        break;
    case 'test_connection':
        handleTestConnection();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
        break;
}

// Test database connection
function handleTestConnection() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed',
            'db_config' => [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER
            ]
        ]);
        return;
    }
    
    try {
        // Test connection and table existence
        $tablesExist = checkTablesExist($conn);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Database connection successful',
            'tables_exist' => $tablesExist,
            'upload_dir' => [
                'path' => UPLOAD_DIR,
                'exists' => file_exists(UPLOAD_DIR),
                'writable' => is_writable(UPLOAD_DIR)
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

// Submit new incident report - FIXED VERSION
function handleSubmitReport() {
    global $allowedFileTypes;
    
    // Debug logging
    error_log("=== SUBMIT REPORT START ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    // Check if data is coming from FormData or URL encoded
    if (empty($_POST) && ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/x-www-form-urlencoded') {
        parse_str(file_get_contents('php://input'), $_POST);
    }
    
    // Validate required fields
    $requiredFields = ['title', 'type', 'datetime', 'location', 'reported_by', 'description', 'priority', 'impact_level', 'status'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        error_log("Missing required fields: " . implode(', ', $missingFields));
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $missingFields),
            'missing_fields' => $missingFields
        ]);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Format datetime for MySQL
        $incidentDatetime = date('Y-m-d H:i:s', strtotime($_POST['datetime']));
        
        // Insert incident report
        $sql = "INSERT INTO incidents (
            title, 
            type, 
            incident_datetime, 
            location, 
            reported_by, 
            description, 
            priority, 
            impact_level, 
            evidence, 
            status, 
            actions_taken,
            created_at,
            updated_at
        ) VALUES (
            :title, 
            :type, 
            :datetime, 
            :location, 
            :reported_by, 
            :description, 
            :priority, 
            :impact_level, 
            :evidence, 
            :status, 
            :actions_taken,
            NOW(),
            NOW()
        )";
        
        error_log("SQL: $sql");
        
        $stmt = $conn->prepare($sql);
        
        // Prepare parameters
        $params = [
            ':title' => htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8'),
            ':type' => htmlspecialchars(trim($_POST['type']), ENT_QUOTES, 'UTF-8'),
            ':datetime' => $incidentDatetime,
            ':location' => htmlspecialchars(trim($_POST['location']), ENT_QUOTES, 'UTF-8'),
            ':reported_by' => htmlspecialchars(trim($_POST['reported_by']), ENT_QUOTES, 'UTF-8'),
            ':description' => htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8'),
            ':priority' => $_POST['priority'],
            ':impact_level' => $_POST['impact_level'],
            ':evidence' => isset($_POST['evidence']) ? htmlspecialchars(trim($_POST['evidence']), ENT_QUOTES, 'UTF-8') : null,
            ':status' => $_POST['status'],
            ':actions_taken' => isset($_POST['actions_taken']) ? htmlspecialchars(trim($_POST['actions_taken']), ENT_QUOTES, 'UTF-8') : null
        ];
        
        error_log("Params: " . print_r($params, true));
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new PDOException("SQL execution failed: " . $errorInfo[2]);
        }
        
        $incidentId = $conn->lastInsertId();
        error_log("Incident created with ID: $incidentId");
        
        // Handle file uploads
        $uploadedFiles = [];
        if (!empty($_FILES)) {
            error_log("Processing " . count($_FILES) . " uploaded files");
            
            // Ensure upload directory exists and is writable
            if (!file_exists(UPLOAD_DIR)) {
                if (!mkdir(UPLOAD_DIR, 0777, true)) {
                    throw new Exception("Failed to create upload directory: " . UPLOAD_DIR);
                }
            }
            
            if (!is_writable(UPLOAD_DIR)) {
                throw new Exception("Upload directory is not writable: " . UPLOAD_DIR);
            }
            
            // Process each uploaded file
            foreach ($_FILES as $fileKey => $file) {
                error_log("Processing file: " . $file['name']);
                
                if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                    
                    // Validate file size
                    if ($file['size'] > MAX_FILE_SIZE) {
                        error_log("File too large: " . $file['name'] . " (" . $file['size'] . " bytes)");
                        throw new Exception("File '{$file['name']}' exceeds maximum size of 10MB");
                    }
                    
                    // Validate file type
                    $fileType = mime_content_type($file['tmp_name']);
                    if (!array_key_exists($fileType, $allowedFileTypes)) {
                        error_log("Invalid file type: $fileType for " . $file['name']);
                        throw new Exception("File type '$fileType' not allowed for '{$file['name']}'");
                    }
                    
                    // Generate unique filename
                    $fileExtension = $allowedFileTypes[$fileType];
                    $uniqueFilename = uniqid() . '_' . time() . '.' . $fileExtension;
                    $uploadPath = UPLOAD_DIR . $uniqueFilename;
                    
                    error_log("Moving file to: $uploadPath");
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        // Save file info to database
                        $fileSql = "INSERT INTO incident_files (
                            incident_id,
                            filename,
                            original_name,
                            file_path,
                            file_size,
                            file_type,
                            uploaded_at
                        ) VALUES (
                            :incident_id,
                            :filename,
                            :original_name,
                            :file_path,
                            :file_size,
                            :file_type,
                            NOW()
                        )";
                        
                        $fileStmt = $conn->prepare($fileSql);
                        $fileResult = $fileStmt->execute([
                            ':incident_id' => $incidentId,
                            ':filename' => $uniqueFilename,
                            ':original_name' => htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
                            ':file_path' => $uploadPath,
                            ':file_size' => $file['size'],
                            ':file_type' => $fileType
                        ]);
                        
                        if (!$fileResult) {
                            $errorInfo = $fileStmt->errorInfo();
                            throw new PDOException("File SQL execution failed: " . $errorInfo[2]);
                        }
                        
                        $fileId = $conn->lastInsertId();
                        
                        $uploadedFiles[] = [
                            'id' => $fileId,
                            'original_name' => $file['name'],
                            'filename' => $uniqueFilename,
                            'file_type' => $fileType,
                            'file_size' => $file['size']
                        ];
                        
                        error_log("File uploaded successfully: " . $file['name']);
                    } else {
                        error_log("Failed to move uploaded file: " . $file['name']);
                        throw new Exception("Failed to save file '{$file['name']}'");
                    }
                } else {
                    error_log("File upload error for " . $file['name'] . ": " . $file['error']);
                }
            }
        }
        
        // Log the activity
        logActivity("New incident reported: {$_POST['title']}", $incidentId, $_POST['reported_by']);
        
        // Commit transaction
        $conn->commit();
        
        error_log("=== SUBMIT REPORT SUCCESS ===");
        
        echo json_encode([
            'success' => true,
            'message' => 'Incident report submitted successfully',
            'incident_id' => $incidentId,
            'files_uploaded' => count($uploadedFiles),
            'data' => [
                'id' => $incidentId,
                'title' => $_POST['title'],
                'status' => $_POST['status'],
                'priority' => $_POST['priority'],
                'datetime' => $incidentDatetime
            ]
        ]);
        
    } catch (PDOException $e) {
        if (isset($conn)) $conn->rollBack();
        error_log("Database error in handleSubmitReport: " . $e->getMessage());
        error_log("Error code: " . $e->getCode());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollBack();
        error_log("Error in handleSubmitReport: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get all incident reports with filters - SIMPLIFIED VERSION
function handleGetReports() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Simple query to get all incidents first
        $sql = "SELECT 
            id,
            title,
            type,
            incident_datetime,
            location,
            reported_by,
            description,
            priority,
            impact_level,
            evidence,
            status,
            actions_taken,
            created_at,
            updated_at
        FROM incidents 
        ORDER BY incident_datetime DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $incidents = $stmt->fetchAll();
        
        // Get files for each incident
        foreach ($incidents as &$incident) {
            $filesSql = "SELECT id, filename, original_name, file_type, file_size, uploaded_at 
                        FROM incident_files 
                        WHERE incident_id = :incident_id";
            $filesStmt = $conn->prepare($filesSql);
            $filesStmt->execute([':incident_id' => $incident['id']]);
            $incident['files'] = $filesStmt->fetchAll();
            
            // Format dates for better readability
            $incident['formatted_date'] = date('M j, Y', strtotime($incident['incident_datetime']));
            $incident['formatted_time'] = date('g:i A', strtotime($incident['incident_datetime']));
            $incident['created_date'] = date('M j, Y', strtotime($incident['created_at']));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $incidents,
            'total' => count($incidents)
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in handleGetReports: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving reports: ' . $e->getMessage()]);
    }
}

// Get single incident report
function handleGetReport() {
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Report ID required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Get incident details
        $sql = "SELECT * FROM incidents WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $_GET['id']]);
        $incident = $stmt->fetch();
        
        if (!$incident) {
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            return;
        }
        
        // Get associated files
        $fileSql = "SELECT * FROM incident_files WHERE incident_id = :id ORDER BY uploaded_at DESC";
        $fileStmt = $conn->prepare($fileSql);
        $fileStmt->execute([':id' => $_GET['id']]);
        $files = $fileStmt->fetchAll();
        
        // Format dates
        $incident['formatted_datetime'] = date('F j, Y g:i A', strtotime($incident['incident_datetime']));
        $incident['created_date'] = date('F j, Y', strtotime($incident['created_at']));
        $incident['updated_date'] = date('F j, Y g:i A', strtotime($incident['updated_at']));
        
        echo json_encode([
            'success' => true,
            'data' => $incident,
            'files' => $files
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in handleGetReport: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving report']);
    }
}

// Update incident status
function handleUpdateStatus() {
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        echo json_encode(['success' => false, 'message' => 'Report ID and status required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $sql = "UPDATE incidents SET status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $_POST['id'],
            ':status' => $_POST['status']
        ]);
        
        if ($stmt->rowCount() > 0) {
            // Log the status change
            $reportingUser = $_POST['user'] ?? 'System';
            logActivity("Status changed to: " . $_POST['status'], $_POST['id'], $reportingUser);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Status updated successfully',
                'new_status' => $_POST['status']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found or no changes made']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleUpdateStatus: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
    }
}

// Update incident report
function handleUpdateReport() {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Report ID required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Build update query based on provided fields
        $updateFields = [];
        $params = [':id' => $_POST['id']];
        
        $allowedFields = ['title', 'type', 'incident_datetime', 'location', 'description', 
                         'priority', 'impact_level', 'evidence', 'status', 'actions_taken'];
        
        foreach ($allowedFields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = htmlspecialchars(trim($_POST[$field]), ENT_QUOTES, 'UTF-8');
            }
        }
        
        if (empty($updateFields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        
        $updateFields[] = "updated_at = NOW()";
        
        $sql = "UPDATE incidents SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            // Log the update
            $reportingUser = $_POST['user'] ?? 'System';
            logActivity("Report updated", $_POST['id'], $reportingUser);
            
            echo json_encode([
                'success' => true,
                'message' => 'Report updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found or no changes made']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleUpdateReport: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating report']);
    }
}

// Close incident report
function handleCloseReport() {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Report ID required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $sql = "UPDATE incidents SET status = 'closed', updated_at = NOW() WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $_POST['id']]);
        
        if ($stmt->rowCount() > 0) {
            // Log the close action
            $reportingUser = $_POST['user'] ?? 'System';
            logActivity("Incident report closed", $_POST['id'], $reportingUser);
            
            echo json_encode([
                'success' => true,
                'message' => 'Report closed successfully',
                'closed_id' => $_POST['id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found or already closed']);
        }
        
    } catch (PDOException $e) {
        error_log("Close error in handleCloseReport: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error closing report']);
    }
}

// Delete incident report
function handleDeleteReport() {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Report ID required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Get incident info for logging
        $sql = "SELECT title, reported_by FROM incidents WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $_POST['id']]);
        $incident = $stmt->fetch();
        
        if (!$incident) {
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            return;
        }
        
        // Get file paths before deletion
        $filesSql = "SELECT file_path FROM incident_files WHERE incident_id = :id";
        $filesStmt = $conn->prepare($filesSql);
        $filesStmt->execute([':id' => $_POST['id']]);
        $files = $filesStmt->fetchAll();
        
        // Delete files from disk
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                @unlink($file['file_path']);
            }
        }
        
        // Delete incident files from database
        $deleteFilesSql = "DELETE FROM incident_files WHERE incident_id = :id";
        $deleteFilesStmt = $conn->prepare($deleteFilesSql);
        $deleteFilesStmt->execute([':id' => $_POST['id']]);
        
        // Delete incident from database
        $deleteIncidentSql = "DELETE FROM incidents WHERE id = :id";
        $deleteIncidentStmt = $conn->prepare($deleteIncidentSql);
        $deleteIncidentStmt->execute([':id' => $_POST['id']]);
        
        // Log the deletion
        logActivity("Incident report deleted: " . $incident['title'], $_POST['id'], $_POST['user'] ?? 'System');
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Report deleted successfully',
            'deleted_id' => $_POST['id']
        ]);
        
    } catch (PDOException $e) {
        if (isset($conn)) $conn->rollBack();
        error_log("Delete error in handleDeleteReport: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting report: ' . $e->getMessage()]);
    }
}

// Export reports
function handleExport() {
    $format = $_GET['format'] ?? 'csv';
    $conn = getDBConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Simple query for export
        $sql = "SELECT 
            id,
            title,
            type as category,
            incident_datetime,
            location,
            reported_by,
            description,
            priority,
            impact_level,
            evidence,
            status,
            actions_taken,
            created_at,
            updated_at
        FROM incidents 
        ORDER BY incident_datetime DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $incidents = $stmt->fetchAll();
        
        switch ($format) {
            case 'csv':
                exportCSV($incidents);
                break;
            case 'excel':
                exportExcel($incidents);
                break;
            case 'json':
                exportJSON($incidents);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid export format']);
        }
        
    } catch (PDOException $e) {
        error_log("Export error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error exporting data']);
    }
}

// Export to CSV
function exportCSV($incidents) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=incident_reports_' . date('Y-m-d_H-i') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Header row
    fputcsv($output, [
        'ID', 'Title', 'Category', 'Date/Time', 'Location', 
        'Priority', 'Impact Level', 'Status', 'Reported By', 
        'Description', 'Evidence', 'Actions Taken', 'Created At', 'Updated At'
    ]);
    
    // Data rows
    foreach ($incidents as $incident) {
        fputcsv($output, [
            $incident['id'],
            $incident['title'],
            $incident['category'],
            $incident['incident_datetime'],
            $incident['location'],
            $incident['priority'],
            $incident['impact_level'],
            $incident['status'],
            $incident['reported_by'],
            strip_tags($incident['description']),
            strip_tags($incident['evidence'] ?? ''),
            strip_tags($incident['actions_taken'] ?? ''),
            $incident['created_at'],
            $incident['updated_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// Export to Excel (CSV with .xls extension)
function exportExcel($incidents) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=incident_reports_' . date('Y-m-d_H-i') . '.xls');
    
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    
    // Header row
    echo '<tr>';
    echo '<th>ID</th><th>Title</th><th>Category</th><th>Date/Time</th><th>Location</th>';
    echo '<th>Priority</th><th>Impact Level</th><th>Status</th><th>Reported By</th>';
    echo '<th>Description</th><th>Evidence</th><th>Actions Taken</th><th>Created At</th><th>Updated At</th>';
    echo '</tr>';
    
    // Data rows
    foreach ($incidents as $incident) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($incident['id']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['title']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['category']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['incident_datetime']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['location']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['priority']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['impact_level']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['status']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['reported_by']) . '</td>';
        echo '<td>' . htmlspecialchars(strip_tags($incident['description'])) . '</td>';
        echo '<td>' . htmlspecialchars(strip_tags($incident['evidence'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars(strip_tags($incident['actions_taken'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($incident['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($incident['updated_at']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    exit;
}

// Export to JSON
function exportJSON($incidents) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=incident_reports_' . date('Y-m-d_H-i') . '.json');
    
    echo json_encode([
        'export_date' => date('Y-m-d H:i:s'),
        'total_records' => count($incidents),
        'data' => $incidents
    ], JSON_PRETTY_PRINT);
    exit;
}

// Get statistics
function handleGetStats() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $stats = [];
        
        // Total incidents
        $totalSql = "SELECT COUNT(*) as total FROM incidents";
        $totalStmt = $conn->query($totalSql);
        $stats['total'] = $totalStmt->fetch()['total'];
        
        // Incidents by status
        $statusSql = "SELECT status, COUNT(*) as count FROM incidents GROUP BY status";
        $statusStmt = $conn->query($statusSql);
        $stats['by_status'] = $statusStmt->fetchAll();
        
        // Incidents by priority
        $prioritySql = "SELECT priority, COUNT(*) as count FROM incidents GROUP BY priority";
        $priorityStmt = $conn->query($prioritySql);
        $stats['by_priority'] = $priorityStmt->fetchAll();
        
        // Incidents by category
        $categorySql = "SELECT type, COUNT(*) as count FROM incidents GROUP BY type ORDER BY count DESC";
        $categoryStmt = $conn->query($categorySql);
        $stats['by_category'] = $categoryStmt->fetchAll();
        
        // Today's incidents
        $todaySql = "SELECT COUNT(*) as count FROM incidents WHERE DATE(incident_datetime) = CURDATE()";
        $todayStmt = $conn->query($todaySql);
        $stats['today'] = $todayStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving statistics']);
    }
}

// Get incident categories
function handleGetCategories() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $sql = "SELECT DISTINCT type FROM incidents ORDER BY type";
        $stmt = $conn->query($sql);
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
        
    } catch (PDOException $e) {
        error_log("Categories error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving categories']);
    }
}

// Upload file to existing incident
function handleUploadFile() {
    global $allowedFileTypes;
    
    if (!isset($_POST['incident_id']) || empty($_FILES)) {
        echo json_encode(['success' => false, 'message' => 'Incident ID and file required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Verify incident exists
        $checkSql = "SELECT id FROM incidents WHERE id = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':id' => $_POST['incident_id']]);
        
        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Incident not found']);
            return;
        }
        
        $uploadedFiles = [];
        
        foreach ($_FILES as $fileKey => $file) {
            if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                
                // Validate file size
                if ($file['size'] > MAX_FILE_SIZE) {
                    throw new Exception("File {$file['name']} exceeds maximum size of 10MB");
                }
                
                // Validate file type
                $fileType = mime_content_type($file['tmp_name']);
                if (!array_key_exists($fileType, $allowedFileTypes)) {
                    throw new Exception("File type not allowed for {$file['name']}");
                }
                
                // Generate unique filename
                $fileExtension = $allowedFileTypes[$fileType];
                $uniqueFilename = uniqid() . '_' . time() . '.' . $fileExtension;
                $uploadPath = UPLOAD_DIR . $uniqueFilename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    // Save file info to database
                    $fileSql = "INSERT INTO incident_files (
                        incident_id,
                        filename,
                        original_name,
                        file_path,
                        file_size,
                        file_type,
                        uploaded_at
                    ) VALUES (
                        :incident_id,
                        :filename,
                        :original_name,
                        :file_path,
                        :file_size,
                        :file_type,
                        NOW()
                    )";
                    
                    $fileStmt = $conn->prepare($fileSql);
                    $fileStmt->execute([
                        ':incident_id' => $_POST['incident_id'],
                        ':filename' => $uniqueFilename,
                        ':original_name' => htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
                        ':file_path' => $uploadPath,
                        ':file_size' => $file['size'],
                        ':file_type' => $fileType
                    ]);
                    
                    $fileId = $conn->lastInsertId();
                    
                    $uploadedFiles[] = [
                        'id' => $fileId,
                        'original_name' => $file['name'],
                        'filename' => $uniqueFilename,
                        'file_type' => $fileType,
                        'file_size' => $file['size']
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'File(s) uploaded successfully',
            'files' => $uploadedFiles
        ]);
        
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Delete file
function handleDeleteFile() {
    if (!isset($_POST['file_id'])) {
        echo json_encode(['success' => false, 'message' => 'File ID required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Get file info
        $sql = "SELECT * FROM incident_files WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $_POST['file_id']]);
        $file = $stmt->fetch();
        
        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            return;
        }
        
        // Delete file from disk
        if (file_exists($file['file_path'])) {
            @unlink($file['file_path']);
        }
        
        // Delete from database
        $deleteSql = "DELETE FROM incident_files WHERE id = :id";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->execute([':id' => $_POST['file_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'File deleted successfully',
            'deleted_file' => $file['original_name']
        ]);
        
    } catch (PDOException $e) {
        error_log("Delete file error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting file']);
    }
}

// Search incidents
function handleSearch() {
    if (!isset($_GET['q']) || empty($_GET['q'])) {
        echo json_encode(['success' => false, 'message' => 'Search query required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $searchTerm = '%' . $_GET['q'] . '%';
        
        $sql = "SELECT 
            id,
            title,
            type,
            incident_datetime,
            location,
            reported_by,
            priority,
            status
        FROM incidents 
        WHERE title LIKE :search 
           OR description LIKE :search 
           OR location LIKE :search 
           OR reported_by LIKE :search
        ORDER BY incident_datetime DESC 
        LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':search' => $searchTerm]);
        $results = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'query' => $_GET['q'],
            'count' => count($results),
            'results' => $results
        ]);
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error performing search']);
    }
}

// Get recent incidents
function handleGetRecent() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $sql = "SELECT 
            id,
            title,
            type,
            incident_datetime,
            location,
            priority,
            status,
            reported_by
        FROM incidents 
        ORDER BY incident_datetime DESC 
        LIMIT :limit";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $incidents = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'count' => count($incidents),
            'data' => $incidents
        ]);
        
    } catch (PDOException $e) {
        error_log("Recent incidents error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving recent incidents']);
    }
}

// Simple log activity function
function logActivity($action, $incidentId, $user) {
    // Simple logging - you can expand this as needed
    error_log("Activity: $action - Incident: $incidentId - User: $user");
}

// Handle invalid or missing action
if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'No action specified',
        'available_actions' => [
            'submit_report',
            'get_reports',
            'get_report',
            'update_status',
            'update_report',
            'delete_report',
            'close_report',
            'export',
            'get_stats',
            'get_categories',
            'upload_file',
            'delete_file',
            'search',
            'get_recent',
            'test_connection'
        ]
    ]);
}
?>