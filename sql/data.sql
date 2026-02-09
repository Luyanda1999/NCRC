-- Enhanced activity types table
CREATE TABLE IF NOT EXISTS activity_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    severity ENUM('info', 'warning', 'error', 'security') DEFAULT 'info',
    UNIQUE KEY unique_action (category, action)
);

-- Insert common activity types
INSERT INTO activity_types (category, action, description, severity) VALUES
-- Authentication
('auth', 'login', 'User logged into the system', 'info'),
('auth', 'logout', 'User logged out of the system', 'info'),
('auth', 'login_failed', 'Failed login attempt', 'warning'),
('auth', 'password_changed', 'Password changed', 'security'),
('auth', 'session_expired', 'Session expired', 'info'),

-- Incident Management
('incident', 'create', 'Incident report created', 'info'),
('incident', 'update', 'Incident report updated', 'info'),
('incident', 'delete', 'Incident report deleted', 'warning'),
('incident', 'status_change', 'Incident status changed', 'info'),
('incident', 'assign', 'Incident assigned to user', 'info'),
('incident', 'close', 'Incident closed/resolved', 'info'),
('incident', 'export', 'Incident data exported', 'info'),

-- File Operations
('file', 'upload', 'File uploaded to incident', 'info'),
('file', 'download', 'File downloaded from incident', 'info'),
('file', 'delete', 'File deleted from incident', 'warning'),

-- User Management
('user', 'create', 'User account created', 'security'),
('user', 'update', 'User account updated', 'security'),
('user', 'deactivate', 'User account deactivated', 'security'),
('user', 'activate', 'User account activated', 'security'),
('user', 'role_change', 'User role changed', 'security'),

-- System Operations
('system', 'config_change', 'System configuration changed', 'warning'),
('system', 'backup', 'System backup performed', 'info'),
('system', 'maintenance', 'System maintenance performed', 'info'),
('system', 'error', 'System error occurred', 'error'),

-- Security Events
('security', 'access_denied', 'Access denied to resource', 'warning'),
('security', 'brute_force', 'Brute force attempt detected', 'error'),
('security', 'suspicious_activity', 'Suspicious activity detected', 'warning');