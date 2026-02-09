-- Replace existing activity_log table with enhanced version
DROP TABLE IF EXISTS activity_log;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    activity_type_id INT,
    user_id INT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    action VARCHAR(500) NOT NULL,
    details JSON,
    incident_id INT,
    resource_id VARCHAR(100),
    resource_type VARCHAR(50),
    severity ENUM('info', 'warning', 'error', 'security') DEFAULT 'info',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (activity_type_id) REFERENCES activity_types(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_timestamp (timestamp),
    INDEX idx_severity (severity),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_activity_type (activity_type_id),
    INDEX idx_ip_address (ip_address)
);