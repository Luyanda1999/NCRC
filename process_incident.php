<?php
// process_incident.php
// Handle incident report form submission

// Database configuration
$host = 'localhost';
$dbname = 'fidelity_ncrc';
$username = 'root';
$password = '';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Create response array
$response = array(
    'success' => false,
    'message' => '',
    'report_id' => '',
    'redirect_url' => 'Incident_table.html'
);

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? '';
    $incident_datetime = $_POST['datetime'] ?? '';
    $location = $_POST['location'] ?? '';
    $specific_location = $_POST['specific_location'] ?? '';
    $reported_by = $_POST['reported_by'] ?? '';
    $description = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $impact_level = $_POST['impact_level'] ?? '';
    $evidence = $_POST['evidence'] ?? '';
    $status = $_POST['status'] ?? 'open';
    $assigned_to = $_POST['assigned_to'] ?? '';
    $actions = $_POST['actions'] ?? '';
    
    // Get username from reported_by field (assuming format "John Doe")
    $username = 'jdoe'; // Default or extract from reported_by
    if ($reported_by === 'John Doe') {
        $username = 'jdoe';
    }
    
    // Validate required fields
    $required_fields = [
        'title' => 'Incident Title',
        'type' => 'Incident Category',
        'datetime' => 'Date and Time',
        'location' => 'Location',
        'reported_by' => 'Reported By',
        'description' => 'Incident Description',
        'priority' => 'Priority Level',
        'impact_level' => 'Impact Level',
        'status' => 'Current Status'
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $field => $field_name) {
        if (empty($$field)) {
            $missing_fields[] = $field_name;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }
    
    // Create database connection
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Generate report ID
    $year = date('Y');
    $report_id = 'INC-' . $year . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    
    // Check if report ID already exists (unlikely but just in case)
    $check_sql = "SELECT report_id FROM incident_reports WHERE report_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $report_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Try another ID
        $report_id = 'INC-' . $year . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    }
    $check_stmt->close();
    
    // Prepare SQL statement
    $sql = "INSERT INTO incident_reports (
        report_id,
        title,
        description,
        incident_datetime,
        incident_type,
        location,
        specific_location,
        priority,
        status,
        assigned_to,
        reported_by,
        username,
        impact_level,
        evidence,
        actions_taken,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare SQL statement: ' . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param(
        'sssssssssssssss',
        $report_id,
        $title,
        $description,
        $incident_datetime,
        $type,
        $location,
        $specific_location,
        $priority,
        $status,
        $assigned_to,
        $reported_by,
        $username,
        $impact_level,
        $evidence,
        $actions
    );
    
    // Execute statement
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Incident report submitted successfully';
        $response['report_id'] = $report_id;
        
        // Handle file uploads if any
        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            handleFileUploads($conn, $stmt->insert_id, $report_id);
        }
        
    } else {
        throw new Exception('Failed to execute SQL statement: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Incident report error: ' . $e->getMessage());
}

// Return JSON response
echo json_encode($response);

// Function to handle file uploads
function handleFileUploads($conn, $incident_id, $report_id) {
    $upload_dir = 'uploads/incidents/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['files']['name'][$key];
        $file_size = $_FILES['files']['size'][$key];
        $file_error = $_FILES['files']['error'][$key];
        $file_type = $_FILES['files']['type'][$key];
        
        // Check for upload errors
        if ($file_error !== UPLOAD_ERR_OK) {
            error_log("File upload error for $file_name: $file_error");
            continue;
        }
        
        // Validate file size (10MB max)
        if ($file_size > 10 * 1024 * 1024) {
            error_log("File too large: $file_name");
            continue;
        }
        
        // Validate file type
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            error_log("Invalid file type: $file_name");
            continue;
        }
        
        // Generate unique filename
        $unique_name = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.\_\-]/', '', $file_name);
        $upload_path = $upload_dir . $unique_name;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $upload_path)) {
            // Save file info to database
            $sql = "INSERT INTO incident_files (
                incident_id,
                report_id,
                file_name,
                file_path,
                file_type,
                file_size,
                uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'issssi',
                $incident_id,
                $report_id,
                $file_name,
                $upload_path,
                $file_type,
                $file_size
            );
            
            if ($stmt->execute()) {
                // File saved successfully
            } else {
                error_log("Failed to save file info to database: " . $stmt->error);
            }
            
            $stmt->close();
        } else {
            error_log("Failed to move uploaded file: $file_name");
        }
    }
}

// Function to create database tables if they don't exist
function createDatabaseTables($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    $conn->query($sql);
    $conn->select_db($dbname);
    
    // Create incident_reports table
    $sql = "CREATE TABLE IF NOT EXISTS incident_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id VARCHAR(50) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        incident_datetime DATETIME NOT NULL,
        incident_type VARCHAR(100) NOT NULL,
        location VARCHAR(100) NOT NULL,
        specific_location VARCHAR(255),
        priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
        status ENUM('open', 'investigating', 'contained', 'resolved', 'closed') DEFAULT 'open',
        assigned_to VARCHAR(100),
        reported_by VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL,
        impact_level ENUM('minimal', 'minor', 'moderate', 'major', 'severe') NOT NULL,
        evidence TEXT,
        actions_taken TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_type (incident_type),
        INDEX idx_location (location),
        INDEX idx_datetime (incident_datetime)
    )";
    
    $conn->query($sql);
    
    // Create incident_files table
    $sql = "CREATE TABLE IF NOT EXISTS incident_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        incident_id INT NOT NULL,
        report_id VARCHAR(50) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100),
        file_size INT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (incident_id) REFERENCES incident_reports(id) ON DELETE CASCADE
    )";
    
    $conn->query($sql);
    
    $conn->close();
}
?>