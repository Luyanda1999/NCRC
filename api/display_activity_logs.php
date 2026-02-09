<?php
// display_activity_logs.php - Display activity logs with detailed information
require_once 'config.php';
require_once 'ActivityLogger.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied. Admin privileges required.');
}

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'username' => $_GET['username'] ?? null,
    'action' => $_GET['action'] ?? null,
    'severity' => $_GET['severity'] ?? null,
    'start_date' => $_GET['start_date'] ?? null,
    'end_date' => $_GET['end_date'] ?? null,
    'resource_type' => $_GET['resource_type'] ?? null,
    'incident_id' => $_GET['incident_id'] ?? null
];

// Remove empty filters
$filters = array_filter($filters);

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = intval($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

// Initialize logger
$logger = new ActivityLogger();

// Get logs
$logs = $logger->getLogs($filters, $limit, $offset);

// Get total count for pagination
if ($limit > 0) {
    $totalLogs = $logger->getLogs($filters, 0, 0);
    $totalPages = ceil(count($totalLogs) / $limit);
} else {
    $totalPages = 1;
}

// Get statistics
$stats = $logger->getStatistics('today');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log Dashboard - Fidelity NCRC</title>
    <style>
        :root {
            --primary-color: #00ff9d;
            --bg-dark: #0a1929;
            --card-bg: rgba(10, 25, 41, 0.9);
            --text-light: #e6f1ff;
            --text-muted: #8892b0;
            --warning-color: #ffd700;
            --error-color: #e63946;
            --info-color: #0a74da;
            --security-color: #00ff9d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a1929 0%, #0c1e32 100%);
            color: var(--text-light);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 255, 157, 0.3);
        }
        
        h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            text-shadow: 0 0 10px rgba(0, 255, 157, 0.3);
        }
        
        .subtitle {
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 157, 0.2);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            background: rgba(10, 25, 41, 0.8);
            border: 1px solid rgba(0, 255, 157, 0.3);
            color: var(--text-light);
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 255, 157, 0.2);
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, rgba(10, 25, 41, 0.8) 100%);
            border: 1px solid rgba(0, 255, 157, 0.2);
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 255, 157, 0.1);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        /* Logs Table */
        .logs-container {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 157, 0.2);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .table-header {
            background: rgba(0, 255, 157, 0.1);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0, 255, 157, 0.2);
        }
        
        .table-header h2 {
            color: var(--primary-color);
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .logs-table th {
            background: rgba(10, 25, 41, 0.9);
            color: var(--primary-color);
            padding: 18px 15px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid rgba(0, 255, 157, 0.3);
            user-select: none;
        }
        
        .logs-table th:hover {
            background: rgba(0, 255, 157, 0.1);
            cursor: pointer;
        }
        
        .logs-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: top;
        }
        
        .logs-table tr:hover {
            background: rgba(0, 255, 157, 0.05);
        }
        
        /* Severity Badges */
        .severity-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .severity-info {
            background: rgba(10, 116, 218, 0.2);
            color: var(--info-color);
            border: 1px solid var(--info-color);
        }
        
        .severity-warning {
            background: rgba(255, 215, 0, 0.2);
            color: var(--warning-color);
            border: 1px solid var(--warning-color);
        }
        
        .severity-error {
            background: rgba(230, 57, 70, 0.2);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }
        
        .severity-security {
            background: rgba(0, 255, 157, 0.2);
            color: var(--security-color);
            border: 1px solid var(--security-color);
        }
        
        /* Details Panel */
        .details-panel {
            background: rgba(10, 25, 41, 0.95);
            border: 1px solid rgba(0, 255, 157, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-top: 10px;
            display: none;
        }
        
        .details-panel.active {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-label {
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .detail-value {
            color: var(--text-light);
            font-size: 0.95rem;
            word-break: break-word;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 25px;
            background: rgba(10, 25, 41, 0.8);
            border-top: 1px solid rgba(0, 255, 157, 0.2);
        }
        
        .pagination button {
            background: rgba(0, 255, 157, 0.1);
            border: 1px solid rgba(0, 255, 157, 0.3);
            color: var(--primary-color);
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .pagination button:hover:not(:disabled) {
            background: rgba(0, 255, 157, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-info {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .logs-table {
                display: block;
                overflow-x: auto;
            }
            
            .pagination {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        /* Action buttons */
        .btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, #00cc7a 100%);
            border: none;
            color: var(--bg-dark);
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 157, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .btn-export {
            background: linear-gradient(135deg, #0a74da 0%, #095ab2 100%);
        }
        
        /* Log details toggle */
        .details-toggle {
            background: none;
            border: 1px solid rgba(0, 255, 157, 0.3);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .details-toggle:hover {
            background: rgba(0, 255, 157, 0.1);
        }
        
        /* Loading spinner */
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 255, 157, 0.3);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1><i class="fas fa-clipboard-list"></i> Activity Log Dashboard</h1>
                <p class="subtitle">Monitor all system activities and security events</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-export" onclick="exportLogs()">
                    <i class="fas fa-download"></i> Export Logs
                </button>
            </div>
        </header>
        
        <!-- Filter Section -->
        <section class="filter-section">
            <h3><i class="fas fa-filter"></i> Filter Logs</h3>
            <form id="filterForm" onsubmit="applyFilters(event)">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" placeholder="Filter by username" 
                               value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="action"><i class="fas fa-play"></i> Action</label>
                        <input type="text" id="action" name="action" placeholder="Filter by action" 
                               value="<?php echo htmlspecialchars($_GET['action'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="severity"><i class="fas fa-exclamation-circle"></i> Severity</label>
                        <select id="severity" name="severity">
                            <option value="">All Severities</option>
                            <option value="info" <?php echo ($_GET['severity'] ?? '') === 'info' ? 'selected' : ''; ?>>Info</option>
                            <option value="warning" <?php echo ($_GET['severity'] ?? '') === 'warning' ? 'selected' : ''; ?>>Warning</option>
                            <option value="error" <?php echo ($_GET['severity'] ?? '') === 'error' ? 'selected' : ''; ?>>Error</option>
                            <option value="security" <?php echo ($_GET['severity'] ?? '') === 'security' ? 'selected' : ''; ?>>Security</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="resource_type"><i class="fas fa-cube"></i> Resource Type</label>
                        <select id="resource_type" name="resource_type">
                            <option value="">All Types</option>
                            <option value="incident" <?php echo ($_GET['resource_type'] ?? '') === 'incident' ? 'selected' : ''; ?>>Incident</option>
                            <option value="file" <?php echo ($_GET['resource_type'] ?? '') === 'file' ? 'selected' : ''; ?>>File</option>
                            <option value="user" <?php echo ($_GET['resource_type'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="start_date"><i class="fas fa-calendar-alt"></i> Date From</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date"><i class="fas fa-calendar-alt"></i> Date To</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="button" class="btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </section>
        
        <!-- Statistics Section -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Logs Today</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    if (isset($stats['by_severity'])) {
                        foreach ($stats['by_severity'] as $severity) {
                            if ($severity['severity'] === 'security') {
                                echo $severity['count'];
                                break;
                            }
                        }
                    } else {
                        echo '0';
                    }
                    ?>
                </div>
                <div class="stat-label">Security Events</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number">
                    <?php echo count($stats['top_users'] ?? []); ?>
                </div>
                <div class="stat-label">Active Users Today</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">
                    <?php echo $limit; ?>
                </div>
                <div class="stat-label">Logs Per Page</div>
            </div>
        </section>
        
        <!-- Logs Table -->
        <section class="logs-container">
            <div class="table-header">
                <h2><i class="fas fa-history"></i> Activity Logs</h2>
                <span class="total-count"><?php echo count($totalLogs ?? []); ?> total records</span>
            </div>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Loading activity logs...</p>
            </div>
            
            <table class="logs-table" id="logsTable">
                <thead>
                    <tr>
                        <th onclick="sortTable('timestamp')">Timestamp <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('username')">User <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('action')">Action <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('severity')">Severity <i class="fas fa-sort"></i></th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px; display: block;"></i>
                                <p>No activity logs found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                    <?php if ($log['user_id']): ?>
                                        <br><small>ID: <?php echo $log['user_id']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['action']); ?>
                                    <?php if (!empty($log['type_description'])): ?>
                                        <br><small><?php echo htmlspecialchars($log['type_description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $severity = $log['severity'] ?? 'info';
                                    $badgeClass = "severity-" . $severity;
                                    ?>
                                    <span class="severity-badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($severity); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($log['details'])) {
                                        $details = $log['details'];
                                        if (is_string($details)) {
                                            $details = json_decode($details, true);
                                        }
                                        if (is_array($details)) {
                                            echo '<small>';
                                            foreach ($details as $key => $value) {
                                                if ($key === 'ip_address' || $key === 'user_agent') continue;
                                                echo htmlspecialchars($key) . ': ' . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . '<br>';
                                            }
                                            echo '</small>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <button class="details-toggle" onclick="toggleDetails(this)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <div class="details-panel">
                                        <div class="details-grid">
                                            <div class="detail-item">
                                                <span class="detail-label">Activity Type:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($log['category'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">User Agent:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?></span>
                                            </div>
                                            <?php if (!empty($log['incident_id'])): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Incident ID:</span>
                                                    <span class="detail-value"><?php echo htmlspecialchars($log['incident_id']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($log['resource_type'])): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Resource Type:</span>
                                                    <span class="detail-value"><?php echo htmlspecialchars($log['resource_type']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($log['resource_id'])): ?>
                                                <div class="detail-item">
                                                    <span class="detail-label">Resource ID:</span>
                                                    <span class="detail-value"><?php echo htmlspecialchars($log['resource_id']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($log['details'])): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Full Details:</span>
                                                <pre class="detail-value" style="
                                                    background: rgba(0,0,0,0.3); 
                                                    padding: 10px; 
                                                    border-radius: 4px; 
                                                    overflow-x: auto; 
                                                    font-size: 0.85rem;
                                                    max-height: 200px;
                                                    overflow-y: auto;
                                                ">
<?php 
$details = $log['details'];
if (is_string($details)) {
    $details = json_decode($details, true);
}
echo htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)); 
?>
                                                </pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <button onclick="changePage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    
                    <div class="page-info">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        (<?php echo count($logs); ?> logs shown)
                    </div>
                    
                    <button onclick="changePage(<?php echo $page + 1; ?>)" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
        let currentSortColumn = 'timestamp';
        let currentSortDirection = 'desc';
        
        // Apply filters
        function applyFilters(event) {
            event.preventDefault();
            const form = event.target;
            const params = new URLSearchParams();
            
            // Add form values to params
            new FormData(form).forEach((value, key) => {
                if (value) params.append(key, value);
            });
            
            // Add pagination
            params.append('page', '1');
            params.append('limit', '<?php echo $limit; ?>');
            
            // Reload page with filters
            window.location.href = '?' + params.toString();
        }
        
        // Clear filters
        function clearFilters() {
            window.location.href = window.location.pathname;
        }
        
        // Change page
        function changePage(page) {
            const params = new URLSearchParams(window.location.search);
            params.set('page', page);
            window.location.href = '?' + params.toString();
        }
        
        // Export logs
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            params.delete('page');
            params.delete('limit');
            
            window.location.href = '?' + params.toString();
        }
        
        // Toggle details panel
        function toggleDetails(button) {
            const detailsPanel = button.nextElementSibling;
            detailsPanel.classList.toggle('active');
            
            const icon = button.querySelector('i');
            if (detailsPanel.classList.contains('active')) {
                icon.className = 'fas fa-eye-slash';
                button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Details';
            } else {
                icon.className = 'fas fa-eye';
                button.innerHTML = '<i class="fas fa-eye"></i> View Details';
            }
        }
        
        // Sort table
        function sortTable(column) {
            const params = new URLSearchParams(window.location.search);
            
            if (currentSortColumn === column) {
                currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc';
            }
            
            params.set('sort', column);
            params.set('order', currentSortDirection);
            
            window.location.href = '?' + params.toString();
        }
        
        // Auto-refresh every 60 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 60000);
        
        // Show loading spinner when navigating
        document.querySelectorAll('a, button[onclick^="changePage"], button[type="submit"]').forEach(element => {
            element.addEventListener('click', () => {
                document.getElementById('loading').classList.add('active');
            });
        });
    </script>
</body>
</html>