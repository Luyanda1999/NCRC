-- Drop existing tables if they exist (use with caution in production)
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS incident_files;
DROP TABLE IF EXISTS incidents;
DROP TABLE IF EXISTS users;

-- Create database
CREATE DATABASE IF NOT EXISTS fidelity_ncrc;
USE fidelity_ncrc;

-- Users table (for authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'operator', 'viewer') DEFAULT 'operator',
    department VARCHAR(100),
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_active (is_active),
    INDEX idx_username (username)
);

-- Incidents table
CREATE TABLE IF NOT EXISTS incidents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    incident_datetime DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    reported_by VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    impact_level ENUM('minimal', 'minor', 'moderate', 'major', 'severe') NOT NULL,
    evidence TEXT,
    status ENUM('open', 'investigating', 'contained', 'resolved', 'closed') DEFAULT 'open',
    actions_taken TEXT,
    assigned_to VARCHAR(100),
    resolution_details TEXT,
    resolution_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_datetime (incident_datetime),
    INDEX idx_type (type),
    INDEX idx_location (location),
    INDEX idx_reported_by (reported_by),
    INDEX idx_assigned_to (assigned_to),
    FULLTEXT idx_search (title, description, location, reported_by)
);

-- Incident files table
CREATE TABLE IF NOT EXISTS incident_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    uploaded_by VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_incident_id (incident_id),
    INDEX idx_uploaded_at (uploaded_at)
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(500) NOT NULL,
    incident_id INT,
    user VARCHAR(100) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    INDEX idx_user (user),
    INDEX idx_timestamp (timestamp),
    INDEX idx_incident_id (incident_id)
);

-- Download log table
CREATE TABLE IF NOT EXISTS download_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    incident_id INT NOT NULL,
    downloaded_by VARCHAR(100),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES incident_files(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_downloaded_at (downloaded_at),
    INDEX idx_downloaded_by (downloaded_by)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password_hash, full_name, email, role) 
VALUES ('admin', '$2y$10$DzdBpDI0Lz4m6Ixi1vZQ.uLqT.nv.guUaGWsQVz5FtzecbG9CxHju', 'Administrator', 'admin@fidelity-ncrc.com', 'admin');

-- Insert default operator user (password: operator123)
INSERT INTO users (username, password_hash, full_name, email, role) 
VALUES ('jdoe', '$2y$10$k8YQJZ7nT6fV2M9wLxR3.uLqT.nv.guUaGWsQVz5FtzecbG9CxHju', 'John Doe', 'jdoe@fidelity-ncrc.com', 'operator');

-- Insert sample incident data
INSERT INTO incidents (title, type, incident_datetime, location, reported_by, description, priority, impact_level, status) VALUES
('Unauthorized Access Attempt', 'unauthorized_access', '2024-01-15 14:30:00', 'Main Server Room', 'John Doe', 'Attempted unauthorized access to server room detected by security cameras.', 'high', 'moderate', 'investigating'),
('Network Connectivity Issue', 'equipment_failure', '2024-01-16 09:15:00', 'IT Department', 'Jane Smith', 'Network switch failure causing intermittent connectivity issues.', 'medium', 'minor', 'resolved'),
('Suspicious Package Found', 'suspicious_activity', '2024-01-17 11:45:00', 'Main Entrance', 'Security Team', 'Unattended package found at main entrance. Police notified.', 'critical', 'major', 'contained'),
('Data Backup Failure', 'equipment_failure', '2024-01-18 03:00:00', 'Data Center', 'System Admin', 'Scheduled backup failed due to storage array issue.', 'high', 'moderate', 'open'),
('Phishing Email Campaign', 'cyber_attack', '2024-01-19 08:30:00', 'Company-wide', 'IT Security', 'Multiple employees received phishing emails requesting credentials.', 'critical', 'severe', 'investigating');

-- Create views for reporting
CREATE VIEW incident_summary AS
SELECT 
    DATE(incident_datetime) as incident_date,
    COUNT(*) as total_incidents,
    SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical_count,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count
FROM incidents
GROUP BY DATE(incident_datetime)
ORDER BY incident_date DESC;

CREATE VIEW category_summary AS
SELECT 
    type,
    COUNT(*) as total,
    AVG(CASE 
        WHEN priority = 'critical' THEN 4
        WHEN priority = 'high' THEN 3
        WHEN priority = 'medium' THEN 2
        WHEN priority = 'low' THEN 1
    END) as avg_severity
FROM incidents
GROUP BY type
ORDER BY total DESC;

-- Create stored procedures
DELIMITER //

CREATE PROCEDURE GetMonthlyReport(IN year_month VARCHAR(7))
BEGIN
    SELECT 
        type,
        COUNT(*) as count,
        AVG(CASE 
            WHEN priority = 'critical' THEN 4
            WHEN priority = 'high' THEN 3
            WHEN priority = 'medium' THEN 2
            WHEN priority = 'low' THEN 1
        END) as avg_severity
    FROM incidents
    WHERE DATE_FORMAT(incident_datetime, '%Y-%m') = year_month
    GROUP BY type
    ORDER BY count DESC;
END //

CREATE PROCEDURE GetUserActivity(IN user_name VARCHAR(100), IN days INT)
BEGIN
    SELECT 
        action,
        incident_id,
        timestamp
    FROM activity_log
    WHERE user = user_name 
    AND timestamp >= DATE_SUB(NOW(), INTERVAL days DAY)
    ORDER BY timestamp DESC;
END //

CREATE PROCEDURE GetIncidentTrends(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        DATE(incident_datetime) as date,
        COUNT(*) as total,
        SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high
    FROM incidents
    WHERE DATE(incident_datetime) BETWEEN start_date AND end_date
    GROUP BY DATE(incident_datetime)
    ORDER BY date;
END //

DELIMITER ;

-- Create triggers
DELIMITER //

CREATE TRIGGER update_incident_timestamp
BEFORE UPDATE ON incidents
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END //

CREATE TRIGGER log_incident_creation
AFTER INSERT ON incidents
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (action, incident_id, user, timestamp)
    VALUES ('Incident created', NEW.id, NEW.reported_by, NOW());
END //

DELIMITER ;