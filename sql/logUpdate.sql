CREATE TABLE IF NOT EXISTS activity_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    UNIQUE KEY unique_category_action (category, action)
);

-- Insert default activity types
INSERT IGNORE INTO activity_types (category, action, description) VALUES
('incident', 'create', 'Incident report created'),
('incident', 'update', 'Incident report updated'),
('incident', 'status_update', 'Incident status changed'),
('incident', 'close', 'Incident report closed'),
('incident', 'delete', 'Incident report deleted'),
('file', 'upload', 'File uploaded'),
('file', 'delete', 'File deleted'),
('auth', 'login', 'User logged in'),
('auth', 'login_failed', 'Failed login attempt'),
('auth', 'logout', 'User logged out'),
('user', 'create', 'User account created'),
('user', 'update', 'User account updated'),
('user', 'delete', 'User account deleted'),
('system', 'backup', 'System backup performed'),
('security', 'access_denied', 'Access denied'),
('security', 'brute_force', 'Brute force attempt detected');