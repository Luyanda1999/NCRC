-- Create database

USE fidelity_ncrc;

-- Incident reports table
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_datetime (incident_datetime),
    INDEX idx_type (type)
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
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_incident_id (incident_id)
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(500) NOT NULL,
    incident_id INT,
    user VARCHAR(100) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL
);

-- Users table (for future authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'operator', 'viewer') DEFAULT 'operator',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Insert default user (password: admin123)
INSERT INTO users (username, password_hash, full_name, email, role) 
VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'Administrator', 'admin@fidelity-ncrc.com', 'admin');

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

-- Create stored procedure for monthly report
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
DELIMITER ;