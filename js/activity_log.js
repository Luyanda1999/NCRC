// activity_log.js - Frontend script for fetching and displaying activity logs
class ActivityLogManager {
    constructor() {
        this.currentPage = 1;
        this.logsPerPage = 20;
        this.totalLogs = 0;
        this.totalPages = 1;
        this.filters = {
            severity: 'all',
            username: '',
            action: 'all',
            start_date: '',
            end_date: '',
            resource_type: 'all'
        };
        
        this.initializeEventListeners();
        this.loadActivityLogs();
        this.autoRefresh();
    }
    
    initializeEventListeners() {
        // Filter change listeners
        document.getElementById('severityFilter')?.addEventListener('change', (e) => {
            this.filters.severity = e.target.value;
            this.currentPage = 1;
            this.loadActivityLogs();
        });
        
        document.getElementById('userFilter')?.addEventListener('input', (e) => {
            this.filters.username = e.target.value;
            this.debouncedSearch();
        });
        
        document.getElementById('actionFilter')?.addEventListener('change', (e) => {
            this.filters.action = e.target.value;
            this.currentPage = 1;
            this.loadActivityLogs();
        });
        
        document.getElementById('dateFromFilter')?.addEventListener('change', (e) => {
            this.filters.start_date = e.target.value;
            this.currentPage = 1;
            this.loadActivityLogs();
        });
        
        document.getElementById('dateToFilter')?.addEventListener('change', (e) => {
            this.filters.end_date = e.target.value;
            this.currentPage = 1;
            this.loadActivityLogs();
        });
        
        // Resource type filter if exists
        const resourceTypeFilter = document.getElementById('resourceTypeFilter');
        if (resourceTypeFilter) {
            resourceTypeFilter.addEventListener('change', (e) => {
                this.filters.resource_type = e.target.value;
                this.currentPage = 1;
                this.loadActivityLogs();
            });
        }
        
        // Apply filters button
        const applyBtn = document.querySelector('button[onclick="loadActivityLogs()"]');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                this.currentPage = 1;
                this.loadActivityLogs();
            });
        }
        
        // Clear filters button
        const clearBtn = document.querySelector('button[onclick="clearFilters()"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }
    }
    
    debouncedSearch() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.currentPage = 1;
            this.loadActivityLogs();
        }, 500);
    }
    
    async loadActivityLogs() {
        try {
            // Show loading state
            this.showLoading(true);
            
            // Build query parameters
            const params = new URLSearchParams({
                page: this.currentPage,
                limit: this.logsPerPage
            });
            
            // Add filters (only if not 'all' or empty)
            if (this.filters.severity && this.filters.severity !== 'all') {
                params.append('severity', this.filters.severity);
            }
            
            if (this.filters.username) {
                params.append('username', this.filters.username);
            }
            
            if (this.filters.action && this.filters.action !== 'all') {
                params.append('action', this.filters.action);
            }
            
            if (this.filters.start_date) {
                params.append('start_date', this.filters.start_date);
            }
            
            if (this.filters.end_date) {
                params.append('end_date', this.filters.end_date);
            }
            
            if (this.filters.resource_type && this.filters.resource_type !== 'all') {
                params.append('resource_type', this.filters.resource_type);
            }
            
            // Fetch logs from API
            const response = await fetch(`get_activity_logs.php?${params}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.displayActivityLogs(data.logs || []);
                this.totalLogs = data.total || 0;
                this.totalPages = data.pages || Math.ceil(this.totalLogs / this.logsPerPage);
                this.displayPagination();
                this.displayStats(data.stats || {});
            } else {
                throw new Error(data.message || 'Failed to load logs');
            }
            
        } catch (error) {
            console.error('Error loading activity logs:', error);
            this.displayError('Failed to load activity logs. Please try again.');
        } finally {
            this.showLoading(false);
        }
    }
    
    displayActivityLogs(logs) {
        const container = document.getElementById('activityLog');
        if (!container) {
            console.error('Activity log container not found');
            return;
        }
        
        container.innerHTML = '';
        
        if (!logs || logs.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>No activity logs found with the current filters</p>
                    <p style="font-size: 0.9rem; margin-top: 10px;">Try adjusting your filter criteria</p>
                </div>
            `;
            return;
        }
        
        logs.forEach(log => {
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';
            
            const severity = log.severity || 'info';
            const iconMap = {
                'info': { icon: 'fa-info-circle', color: '#0a74da' },
                'warning': { icon: 'fa-exclamation-triangle', color: '#ffd700' },
                'error': { icon: 'fa-exclamation-circle', color: '#e63946' },
                'security': { icon: 'fa-shield-alt', color: '#00ff9d' }
            };
            
            const iconInfo = iconMap[severity] || iconMap.info;
            
            // Parse details if it's a JSON string
            let details = log.details;
            if (typeof details === 'string') {
                try {
                    details = JSON.parse(details);
                } catch (e) {
                    // Keep as string if not valid JSON
                }
            }
            
            // Format details for display
            let detailsHtml = '';
            if (details) {
                if (typeof details === 'object') {
                    detailsHtml = Object.entries(details)
                        .map(([key, value]) => {
                            if (key === 'ip_address' || key === 'user_agent') return '';
                            return `<div><strong>${key}:</strong> ${this.formatDetailValue(value)}</div>`;
                        })
                        .filter(html => html)
                        .join('');
                } else {
                    detailsHtml = `<div>${details}</div>`;
                }
            }
            
            // Check if action is in the action filter
            const actionText = log.action || 'Unknown';
            const actionClass = actionText.toLowerCase().includes('fail') ? 'action-failed' : '';
            
            logEntry.innerHTML = `
                <div class="log-icon ${severity}" style="background: rgba(${this.hexToRgb(iconInfo.color)}, 0.2); color: ${iconInfo.color};">
                    <i class="fas ${iconInfo.icon}"></i>
                </div>
                <div class="log-content">
                    <div class="log-header">
                        <span class="log-user">${this.escapeHtml(log.username || 'System')}</span>
                        <span class="log-action ${actionClass}">${this.escapeHtml(actionText)}</span>
                        ${log.incident_id ? `<span class="log-incident">Incident #${log.incident_id}</span>` : ''}
                    </div>
                    <div class="log-timestamp">
                        ${this.formatTimestamp(log.timestamp)}
                        ${log.resource_type ? ` • ${log.resource_type}` : ''}
                        ${log.ip_address ? ` • IP: ${log.ip_address}` : ''}
                    </div>
                    ${detailsHtml ? `
                    <div class="log-details">
                        ${detailsHtml}
                    </div>
                    ` : ''}
                    <div class="log-meta">
                        ${log.user_agent ? `<small>${this.truncateText(log.user_agent, 100)}</small>` : ''}
                    </div>
                    <button class="view-details-btn" onclick="this.parentElement.querySelector('.full-details').classList.toggle('show'); this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up')">
                        <i class="fas fa-chevron-down"></i> View Full Details
                    </button>
                    <div class="full-details">
                        <pre>${this.escapeHtml(JSON.stringify(log, null, 2))}</pre>
                    </div>
                </div>
            `;
            
            container.appendChild(logEntry);
        });
        
        // Add CSS for new elements if not already added
        this.addStyles();
    }
    
    displayStats(stats) {
        const container = document.getElementById('statsContainer');
        if (!container) return;
        
        // Calculate security events
        let securityEvents = 0;
        if (stats.by_severity) {
            const securityStat = stats.by_severity.find(s => s.severity === 'security');
            securityEvents = securityStat ? securityStat.count : 0;
        }
        
        // Calculate top users count
        let uniqueUsers = 0;
        if (stats.top_users) {
            uniqueUsers = stats.top_users.length;
        }
        
        // Get most frequent action
        let topAction = 'N/A';
        if (stats.top_actions && stats.top_actions.length > 0) {
            topAction = stats.top_actions[0].action;
        }
        
        container.innerHTML = `
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div class="stat-card">
                    <div class="stat-number">${stats.total || 0}</div>
                    <div class="stat-label">Total Logs Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${securityEvents}</div>
                    <div class="stat-label">Security Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${uniqueUsers}</div>
                    <div class="stat-label">Active Users Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${this.currentPage}</div>
                    <div class="stat-label">Current Page</div>
                </div>
            </div>
        `;
    }
    
    displayPagination() {
        const container = document.getElementById('paginationControls');
        if (!container) return;
        
        container.innerHTML = `
            <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                <button class="btn-pagination" ${this.currentPage <= 1 ? 'disabled' : ''} onclick="activityLogManager.changePage(${this.currentPage - 1})">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span style="color: var(--text-muted);">
                    Page ${this.currentPage} of ${this.totalPages} (${this.totalLogs} total logs)
                </span>
                <button class="btn-pagination" ${this.currentPage >= this.totalPages ? 'disabled' : ''} onclick="activityLogManager.changePage(${this.currentPage + 1})">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
                <select class="page-size-select" onchange="activityLogManager.changePageSize(this.value)" style="margin-left: 20px;">
                    <option value="10" ${this.logsPerPage === 10 ? 'selected' : ''}>10 per page</option>
                    <option value="20" ${this.logsPerPage === 20 ? 'selected' : ''}>20 per page</option>
                    <option value="50" ${this.logsPerPage === 50 ? 'selected' : ''}>50 per page</option>
                    <option value="100" ${this.logsPerPage === 100 ? 'selected' : ''}>100 per page</option>
                </select>
            </div>
        `;
    }
    
    changePage(page) {
        if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
            this.currentPage = page;
            this.loadActivityLogs();
            // Scroll to top of logs
            document.getElementById('activityLog')?.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    changePageSize(size) {
        this.logsPerPage = parseInt(size);
        this.currentPage = 1;
        this.loadActivityLogs();
    }
    
    clearFilters() {
        this.filters = {
            severity: 'all',
            username: '',
            action: 'all',
            start_date: '',
            end_date: '',
            resource_type: 'all'
        };
        
        // Reset form elements
        const severityFilter = document.getElementById('severityFilter');
        const userFilter = document.getElementById('userFilter');
        const actionFilter = document.getElementById('actionFilter');
        const dateFromFilter = document.getElementById('dateFromFilter');
        const dateToFilter = document.getElementById('dateToFilter');
        const resourceTypeFilter = document.getElementById('resourceTypeFilter');
        
        if (severityFilter) severityFilter.value = 'all';
        if (userFilter) userFilter.value = '';
        if (actionFilter) actionFilter.value = 'all';
        if (dateFromFilter) dateFromFilter.value = '';
        if (dateToFilter) dateToFilter.value = '';
        if (resourceTypeFilter) resourceTypeFilter.value = 'all';
        
        this.currentPage = 1;
        this.loadActivityLogs();
    }
    
    showLoading(show) {
        const logContainer = document.getElementById('activityLog');
        if (!logContainer) return;
        
        if (show) {
            logContainer.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="
                        width: 40px;
                        height: 40px;
                        border: 3px solid rgba(0, 255, 157, 0.3);
                        border-top-color: #00ff9d;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin: 0 auto 20px;
                    "></div>
                    <p style="color: var(--text-muted);">Loading activity logs...</p>
                </div>
            `;
            
            // Add spin animation
            if (!document.querySelector('style[data-spinner]')) {
                const style = document.createElement('style');
                style.setAttribute('data-spinner', '');
                style.textContent = `
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
    }
    
    displayError(message) {
        const container = document.getElementById('activityLog');
        if (container) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e63946;">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>${this.escapeHtml(message)}</p>
                    <button onclick="activityLogManager.loadActivityLogs()" style="
                        background: rgba(230, 57, 70, 0.1);
                        border: 1px solid #e63946;
                        color: #e63946;
                        padding: 10px 20px;
                        border-radius: 6px;
                        margin-top: 15px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    ">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </div>
            `;
        }
    }
    
    autoRefresh() {
        // Auto-refresh every 30 seconds
        this.refreshInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.loadActivityLogs();
            }
        }, 30000);
    }
    
    addStyles() {
        // Add CSS for new elements if not already added
        if (!document.querySelector('style[data-activity-log]')) {
            const style = document.createElement('style');
            style.setAttribute('data-activity-log', '');
            style.textContent = `
                .log-incident {
                    background: rgba(0, 255, 157, 0.1);
                    color: #00ff9d;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 0.85rem;
                    margin-left: 10px;
                }
                .action-failed {
                    color: #e63946 !important;
                }
                .view-details-btn {
                    background: transparent;
                    border: 1px solid rgba(0, 255, 157, 0.3);
                    color: var(--neon-green);
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 0.85rem;
                    cursor: pointer;
                    margin-top: 10px;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    transition: all 0.3s ease;
                }
                .view-details-btn:hover {
                    background: rgba(0, 255, 157, 0.1);
                }
                .full-details {
                    display: none;
                    margin-top: 10px;
                    padding: 10px;
                    background: rgba(0, 0, 0, 0.2);
                    border-radius: 4px;
                    font-size: 0.85rem;
                    max-height: 300px;
                    overflow-y: auto;
                    border: 1px solid rgba(0, 255, 157, 0.1);
                }
                .full-details.show {
                    display: block;
                    animation: slideDown 0.3s ease;
                }
                .full-details pre {
                    margin: 0;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    font-size: 0.85rem;
                    color: var(--text-muted);
                }
                .btn-pagination {
                    background: rgba(0, 255, 157, 0.1);
                    border: 1px solid rgba(0, 255, 157, 0.3);
                    color: var(--neon-green);
                    padding: 8px 16px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                    transition: all 0.3s ease;
                }
                .btn-pagination:hover:not(:disabled) {
                    background: rgba(0, 255, 157, 0.2);
                    transform: translateY(-2px);
                }
                .btn-pagination:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                .page-size-select {
                    background: rgba(10, 25, 41, 0.8);
                    border: 1px solid rgba(0, 255, 157, 0.3);
                    color: var(--text-light);
                    padding: 8px 12px;
                    border-radius: 6px;
                }
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
            `;
            document.head.appendChild(style);
        }
    }
    
    // Utility methods
    formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    
    formatDetailValue(value) {
        if (value === null || value === undefined) return 'N/A';
        if (typeof value === 'object') return JSON.stringify(value);
        return this.escapeHtml(String(value));
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return this.escapeHtml(text);
        return this.escapeHtml(text.substring(0, maxLength)) + '...';
    }
    
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? 
            `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` :
            '0, 255, 157';
    }
    
    // Clean up on page unload
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize the activity log manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.activityLogManager = new ActivityLogManager();
});

// Global functions for HTML onclick handlers (for compatibility)
window.loadActivityLogs = function() {
    if (window.activityLogManager) {
        window.activityLogManager.loadActivityLogs();
    }
};

window.clearFilters = function() {
    if (window.activityLogManager) {
        window.activityLogManager.clearFilters();
    }
};

window.changePage = function(page) {
    if (window.activityLogManager) {
        window.activityLogManager.changePage(page);
    }
};

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    if (window.activityLogManager) {
        window.activityLogManager.destroy();
    }
});