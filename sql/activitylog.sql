-- Create activity_types table
CREATE TABLE IF NOT EXISTS activity_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    severity ENUM('info', 'warning', 'security', 'critical') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_action (category, action)
);

-- Insert default activity types
INSERT IGNORE INTO activity_types (category, action, description, severity) VALUES
('auth', 'login_success', 'User logged in successfully', 'info'),
('auth', 'login_failed', 'Failed login attempt', 'warning'),
('auth', 'logout', 'User logged out', 'info'),
('auth', 'session_timeout', 'Session expired due to inactivity', 'warning'),
('auth', 'brute_force_attempt', 'Multiple failed login attempts detected', 'security'),
('incident', 'create', 'Incident report created', 'info'),
('incident', 'update', 'Incident report updated', 'info'),
('incident', 'delete', 'Incident report deleted', 'warning'),
('incident', 'status_change', 'Incident status changed', 'info'),
('file', 'upload', 'File uploaded', 'info'),
('file', 'delete', 'File deleted', 'info'),
('file', 'download', 'File downloaded', 'info'),
('user', 'create', 'User account created', 'security'),
('user', 'update', 'User account updated', 'security'),
('user', 'delete', 'User account deleted', 'security'),
('user', 'password_change', 'Password changed', 'security'),
('system', 'search', 'Search performed', 'info'),
('system', 'export', 'Data exported', 'info'),
('system', 'config_change', 'Configuration changed', 'warning'),
('security', 'access_denied', 'Access denied to resource', 'security'),
('security', 'unauthorized_access', 'Unauthorized access attempt', 'critical');

-- Create activity_log table
CREATE TABLE IF NOT EXISTS activity_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    activity_type_id INT,
    user_id INT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) DEFAULT '0.0.0.0',
    user_agent TEXT,
    action VARCHAR(100) NOT NULL,
    details JSON,
    incident_id INT,
    resource_type VARCHAR(50),
    resource_id INT,
    severity ENUM('info', 'warning', 'security', 'critical') DEFAULT 'info',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_severity (severity),
    INDEX idx_timestamp (timestamp),
    INDEX idx_incident_id (incident_id),
    INDEX idx_resource (resource_type, resource_id),
    FOREIGN KEY (activity_type_id) REFERENCES activity_types(id) ON DELETE SET NULL
);

-- Add session management table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
);