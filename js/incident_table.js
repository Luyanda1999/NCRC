// js/incident_table.js

document.addEventListener('DOMContentLoaded', function() {
    // API endpoints
    const API_BASE_URL = '../api/';
    const INCIDENTS_API = API_BASE_URL + 'incidents.php';
    
    // Get DOM elements
    const reportsTableBody = document.getElementById('reportsTableBody');
    const emptyState = document.getElementById('emptyState');
    const statsContainer = document.getElementById('statsContainer');
    const searchInput = document.getElementById('searchInput');
    const filters = {
        status: document.getElementById('statusFilter'),
        priority: document.getElementById('priorityFilter'),
        category: document.getElementById('categoryFilter'),
        location: document.getElementById('locationFilter'),
        impact: document.getElementById('impactFilter'),
        sort: document.getElementById('sortFilter'),
        dateFrom: document.getElementById('dateFrom'),
        dateTo: document.getElementById('dateTo')
    };
    const rowsPerPage = document.getElementById('rowsPerPage');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const exportBtn = document.getElementById('exportBtn');
    const exportOptions = document.getElementById('exportOptions');
    const visibleCount = document.getElementById('visibleCount');
    const totalCount = document.getElementById('totalCount');
    const paginationElements = {
        firstPage: document.getElementById('firstPage'),
        prevPage: document.getElementById('prevPage'),
        nextPage: document.getElementById('nextPage'),
        lastPage: document.getElementById('lastPage'),
        currentPage: document.getElementById('currentPage'),
        totalPages: document.getElementById('totalPages')
    };
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // State variables
    let allIncidents = [];
    let filteredIncidents = [];
    let currentPage = 1;
    let rowsPerPageValue = 10;
    let sortOrder = 'newest';
    let searchTerm = '';
    
    // Initialize
    init();
    
    // Functions
    function init() {
        setupEventListeners();
        loadIncidents();
        updateTime();
        setInterval(updateTime, 1000);
    }
    
    function setupEventListeners() {
        // Search input
        searchInput.addEventListener('input', debounce(handleSearch, 300));
        
        // Filter changes
        Object.values(filters).forEach(filter => {
            if (filter) filter.addEventListener('change', handleFilterChange);
        });
        
        // Rows per page change
        rowsPerPage.addEventListener('change', handleRowsPerPageChange);
        
        // Clear filters
        clearFiltersBtn.addEventListener('click', clearFilters);
        
        // Pagination
        paginationElements.firstPage.addEventListener('click', () => goToPage(1));
        paginationElements.prevPage.addEventListener('click', () => goToPage(currentPage - 1));
        paginationElements.nextPage.addEventListener('click', () => goToPage(currentPage + 1));
        paginationElements.lastPage.addEventListener('click', () => goToPage(calculateTotalPages()));
        
        // Export
        exportBtn.addEventListener('click', toggleExportOptions);
        document.querySelectorAll('.export-option').forEach(option => {
            option.addEventListener('click', handleExport);
        });
        
        // Close export options when clicking elsewhere
        document.addEventListener('click', (e) => {
            if (!exportBtn.contains(e.target) && !exportOptions.contains(e.target)) {
                exportOptions.classList.remove('show');
            }
        });
    }
    
    async function loadIncidents() {
        showLoading();
        
        try {
            const response = await fetch(`${INCIDENTS_API}?action=get_reports`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                allIncidents = data.data;
                applyFilters();
                updateStats();
                updateNotificationBadges();
            } else {
                throw new Error(data.message || 'Error loading incidents');
            }
        } catch (error) {
            console.error('Error loading incidents:', error);
            showError('Failed to load incident reports. Please refresh the page.');
            allIncidents = [];
            applyFilters();
        } finally {
            hideLoading();
        }
    }
    
    function applyFilters() {
        filteredIncidents = [...allIncidents];
        
        // Apply search filter
        if (searchTerm) {
            const term = searchTerm.toLowerCase();
            filteredIncidents = filteredIncidents.filter(incident => 
                incident.title.toLowerCase().includes(term) ||
                incident.description?.toLowerCase().includes(term) ||
                incident.location.toLowerCase().includes(term) ||
                incident.reported_by.toLowerCase().includes(term) ||
                incident.type.toLowerCase().includes(term)
            );
        }
        
        // Apply dropdown filters
        if (filters.status.value !== 'all') {
            filteredIncidents = filteredIncidents.filter(incident => 
                incident.status === filters.status.value
            );
        }
        
        if (filters.priority.value !== 'all') {
            filteredIncidents = filteredIncidents.filter(incident => 
                incident.priority === filters.priority.value
            );
        }
        
        if (filters.category.value !== 'all') {
            filteredIncidents = filteredIncidents.filter(incident => 
                incident.type === filters.category.value
            );
        }
        
        if (filters.location.value !== 'all') {
            filteredIncidents = filteredIncidents.filter(incident => 
                incident.location.toLowerCase().includes(filters.location.value)
            );
        }
        
        if (filters.impact.value !== 'all') {
            filteredIncidents = filteredIncidents.filter(incident => 
                incident.impact_level === filters.impact.value
            );
        }
        
        // Apply date range filter
        if (filters.dateFrom.value) {
            const fromDate = new Date(filters.dateFrom.value);
            filteredIncidents = filteredIncidents.filter(incident => {
                const incidentDate = new Date(incident.incident_datetime);
                return incidentDate >= fromDate;
            });
        }
        
        if (filters.dateTo.value) {
            const toDate = new Date(filters.dateTo.value);
            toDate.setHours(23, 59, 59, 999);
            filteredIncidents = filteredIncidents.filter(incident => {
                const incidentDate = new Date(incident.incident_datetime);
                return incidentDate <= toDate;
            });
        }
        
        // Apply sorting
        applySorting();
        
        // Update counts and display
        updateCounts();
        renderTable();
        updatePagination();
    }
    
    function applySorting() {
        const sortBy = filters.sort.value || sortOrder;
        
        filteredIncidents.sort((a, b) => {
            switch (sortBy) {
                case 'newest':
                    return new Date(b.incident_datetime) - new Date(a.incident_datetime);
                case 'oldest':
                    return new Date(a.incident_datetime) - new Date(b.incident_datetime);
                case 'priority':
                    const priorityOrder = { critical: 4, high: 3, medium: 2, low: 1 };
                    return priorityOrder[b.priority] - priorityOrder[a.priority];
                case 'status':
                    return a.status.localeCompare(b.status);
                case 'location':
                    return a.location.localeCompare(b.location);
                default:
                    return new Date(b.incident_datetime) - new Date(a.incident_datetime);
            }
        });
    }
    
    function renderTable() {
        if (filteredIncidents.length === 0) {
            emptyState.style.display = 'block';
            reportsTableBody.innerHTML = '';
            return;
        }
        
        emptyState.style.display = 'none';
        
        // Calculate pagination
        const startIndex = (currentPage - 1) * rowsPerPageValue;
        const endIndex = Math.min(startIndex + rowsPerPageValue, filteredIncidents.length);
        const pageIncidents = filteredIncidents.slice(startIndex, endIndex);
        
        // Clear table
        reportsTableBody.innerHTML = '';
        
        // Add rows
        pageIncidents.forEach(incident => {
            const row = createTableRow(incident);
            reportsTableBody.appendChild(row);
        });
        
        // Add event listeners for action buttons
        addActionButtonListeners();
    }
    
    function createTableRow(incident) {
        const row = document.createElement('tr');
        row.dataset.id = incident.id;
        
        // Format date
        const incidentDate = new Date(incident.incident_datetime);
        const formattedDate = incidentDate.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        const formattedTime = incidentDate.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Get priority color
        const priorityClass = getPriorityClass(incident.priority);
        const priorityIcon = getPriorityIcon(incident.priority);
        
        // Get status badge
        const statusBadge = getStatusBadge(incident.status);
        
        // Get category display name
        const categoryName = getCategoryDisplayName(incident.type);
        
        row.innerHTML = `
            <td class="id-cell">#${incident.id.toString().padStart(4, '0')}</td>
            <td>
                <div class="date-time-cell">
                    <div class="date">${formattedDate}</div>
                    <div class="time">${formattedTime}</div>
                </div>
            </td>
            <td>
                <div class="title-cell">
                    <strong>${escapeHtml(incident.title)}</strong>
                    <div class="description-preview">${escapeHtml(incident.description?.substring(0, 60) || '')}...</div>
                </div>
            </td>
            <td>
                <span class="category-badge" data-category="${incident.type}">${categoryName}</span>
            </td>
            <td>${escapeHtml(incident.location)}</td>
            <td>
                <span class="priority-badge ${priorityClass}">
                    <i class="fas ${priorityIcon}"></i> ${incident.priority.charAt(0).toUpperCase() + incident.priority.slice(1)}
                </span>
            </td>
            <td>${statusBadge}</td>
            <td>
                <div class="reported-by-cell">
                    <div class="user-avatar-small">${getInitials(incident.reported_by)}</div>
                    <span>${escapeHtml(incident.reported_by)}</span>
                </div>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn view-btn" data-id="${incident.id}" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="action-btn edit-btn" data-id="${incident.id}" title="Edit Report">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn assign-btn" data-id="${incident.id}" title="Assign">
                        <i class="fas fa-user-check"></i>
                    </button>
                    <button class="action-btn close-btn" data-id="${incident.id}" title="Close Report">
                        <i class="fas fa-check-circle"></i>
                    </button>
                    <button class="action-btn delete-btn" data-id="${incident.id}" title="Delete Report">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        return row;
    }
    
    function addActionButtonListeners() {
        // View buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => viewReport(btn.dataset.id));
        });
        
        // Edit buttons
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => editReport(btn.dataset.id));
        });
        
        // Assign buttons
        document.querySelectorAll('.assign-btn').forEach(btn => {
            btn.addEventListener('click', () => assignReport(btn.dataset.id));
        });
        
        // Close buttons
        document.querySelectorAll('.close-btn').forEach(btn => {
            btn.addEventListener('click', () => closeReport(btn.dataset.id));
        });
        
        // Delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => deleteReport(btn.dataset.id));
        });
    }
    
    function viewReport(id) {
        showLoading();
        
        fetch(`${INCIDENTS_API}?action=get_report&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReportModal(data.data, data.files);
                } else {
                    showError('Failed to load report details');
                }
            })
            .catch(error => {
                console.error('Error loading report:', error);
                showError('Error loading report details');
            })
            .finally(() => hideLoading());
    }
    
    function displayReportModal(report, files) {
        const modal = document.getElementById('viewModal');
        const modalTitle = document.getElementById('viewModalTitle');
        const modalBody = document.getElementById('viewModalBody');
        const modalBadges = document.getElementById('viewModalBadges');
        
        // Format dates
        const incidentDate = new Date(report.incident_datetime);
        const createdDate = new Date(report.created_at);
        const updatedDate = new Date(report.updated_at);
        
        // Create badges
        modalBadges.innerHTML = `
            <span class="priority-badge ${getPriorityClass(report.priority)}">
                <i class="fas ${getPriorityIcon(report.priority)}"></i> ${report.priority.toUpperCase()}
            </span>
            <span class="status-badge ${report.status}">
                ${report.status.charAt(0).toUpperCase() + report.status.slice(1)}
            </span>
            <span class="impact-badge ${report.impact_level}">
                ${report.impact_level.toUpperCase()}
            </span>
        `;
        
        // Create modal content
        modalBody.innerHTML = `
            <div class="report-details">
                <div class="details-grid">
                    <div class="detail-section">
                        <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                        <div class="detail-row">
                            <span class="detail-label">Report ID:</span>
                            <span class="detail-value">#${report.id.toString().padStart(4, '0')}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Title:</span>
                            <span class="detail-value">${escapeHtml(report.title)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Category:</span>
                            <span class="detail-value">${getCategoryDisplayName(report.type)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value">${escapeHtml(report.location)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date/Time:</span>
                            <span class="detail-value">${incidentDate.toLocaleString()}</span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4><i class="fas fa-chart-line"></i> Classification</h4>
                        <div class="detail-row">
                            <span class="detail-label">Priority:</span>
                            <span class="detail-value priority-badge ${getPriorityClass(report.priority)}">
                                ${report.priority.charAt(0).toUpperCase() + report.priority.slice(1)}
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Impact Level:</span>
                            <span class="detail-value impact-badge ${report.impact_level}">
                                ${report.impact_level.charAt(0).toUpperCase() + report.impact_level.slice(1)}
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value status-badge ${report.status}">
                                ${report.status.charAt(0).toUpperCase() + report.status.slice(1)}
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Reported By:</span>
                            <span class="detail-value">${escapeHtml(report.reported_by)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Report Created:</span>
                            <span class="detail-value">${createdDate.toLocaleString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value">${updatedDate.toLocaleString()}</span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4><i class="fas fa-align-left"></i> Incident Description</h4>
                    <div class="description-box">
                        ${escapeHtml(report.description).replace(/\n/g, '<br>')}
                    </div>
                </div>
                
                ${report.evidence ? `
                <div class="detail-section">
                    <h4><i class="fas fa-clipboard-check"></i> Evidence & Supporting Information</h4>
                    <div class="description-box">
                        ${escapeHtml(report.evidence).replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                ${report.actions_taken ? `
                <div class="detail-section">
                    <h4><i class="fas fa-tasks"></i> Actions Taken</h4>
                    <div class="description-box">
                        ${escapeHtml(report.actions_taken).replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                ${files && files.length > 0 ? `
                <div class="detail-section">
                    <h4><i class="fas fa-paperclip"></i> Attachments (${files.length})</h4>
                    <div class="attachments-grid">
                        ${files.map(file => `
                            <div class="attachment-item">
                                <i class="fas ${getFileIcon(file.file_type)}"></i>
                                <div class="attachment-info">
                                    <div class="attachment-name">${escapeHtml(file.original_name)}</div>
                                    <div class="attachment-meta">
                                        ${formatFileSize(file.file_size)} • ${new Date(file.uploaded_at).toLocaleDateString()}
                                    </div>
                                </div>
                                <a href="api/download.php?file=${file.id}" class="download-btn" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        modalTitle.textContent = report.title;
        
        // Show modal
        modal.style.display = 'flex';
        
        // Add close event listener
        document.getElementById('closeViewModal').onclick = () => {
            modal.style.display = 'none';
        };
        
        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    function editReport(id) {
        // Redirect to edit page with ID
        window.location.href = `Incident_report.html?edit=${id}`;
    }
    
    function assignReport(id) {
        const modal = document.getElementById('assignModal');
        modal.style.display = 'flex';
        
        // Add close event listeners
        document.getElementById('cancelAssign').onclick = () => {
            modal.style.display = 'none';
        };
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Here you would implement the actual assignment logic
    }
    
    function closeReport(id) {
        const report = allIncidents.find(inc => inc.id == id);
        if (!report) return;
        
        const modal = document.getElementById('closeReportModal');
        
        // Set default resolution date to today
        document.getElementById('resolutionDate').valueAsDate = new Date();
        
        modal.style.display = 'flex';
        
        // Add event listeners
        document.getElementById('cancelClose').onclick = () => {
            modal.style.display = 'none';
        };
        
        document.getElementById('confirmClose').onclick = async () => {
            const status = document.getElementById('closureStatus').value;
            const resolutionDetails = document.getElementById('resolutionDetails').value;
            const resolutionDate = document.getElementById('resolutionDate').value;
            
            if (!resolutionDetails) {
                showError('Please provide resolution details');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'close_report');
                formData.append('id', id);
                formData.append('status', status);
                formData.append('resolution_details', resolutionDetails);
                formData.append('resolution_date', resolutionDate);
                
                const response = await fetch(INCIDENTS_API, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Report closed successfully');
                    modal.style.display = 'none';
                    loadIncidents(); // Refresh data
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showError('Error closing report: ' + error.message);
            }
        };
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    function deleteReport(id) {
        const report = allIncidents.find(inc => inc.id == id);
        if (!report) return;
        
        const modal = document.getElementById('deleteModal');
        
        // Populate modal with report info
        document.getElementById('deleteId').textContent = `#${report.id.toString().padStart(4, '0')}`;
        document.getElementById('deleteTitle').textContent = report.title;
        document.getElementById('deleteCategory').textContent = getCategoryDisplayName(report.type);
        document.getElementById('deleteDate').textContent = new Date(report.incident_datetime).toLocaleDateString();
        
        modal.style.display = 'flex';
        
        // Add event listeners
        document.getElementById('cancelDelete').onclick = () => {
            modal.style.display = 'none';
        };
        
        document.getElementById('confirmDelete').onclick = async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_report');
                formData.append('id', id);
                
                const response = await fetch(INCIDENTS_API, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Report deleted successfully');
                    modal.style.display = 'none';
                    loadIncidents(); // Refresh data
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showError('Error deleting report: ' + error.message);
            }
        };
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    function updateStats() {
        const stats = calculateStats();
        
        statsContainer.innerHTML = `
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">${stats.total}</div>
                    <div class="stat-label">Total Incidents</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">${stats.open}</div>
                    <div class="stat-label">Open Incidents</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-skull-crossbones"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">${stats.critical}</div>
                    <div class="stat-label">Critical Priority</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">${stats.today}</div>
                    <div class="stat-label">Today's Incidents</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">${stats.resolved}</div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        `;
    }
    
    function calculateStats() {
        const today = new Date().toDateString();
        const todayIncidents = allIncidents.filter(incident => 
            new Date(incident.incident_datetime).toDateString() === today
        );
        
        return {
            total: allIncidents.length,
            open: allIncidents.filter(i => i.status === 'open').length,
            critical: allIncidents.filter(i => i.priority === 'critical').length,
            today: todayIncidents.length,
            resolved: allIncidents.filter(i => i.status === 'resolved' || i.status === 'closed').length
        };
    }
    
    function updateCounts() {
        totalCount.textContent = allIncidents.length;
        visibleCount.textContent = filteredIncidents.length;
    }
    
    function updatePagination() {
        const totalPages = calculateTotalPages();
        
        paginationElements.currentPage.textContent = currentPage;
        paginationElements.totalPages.textContent = totalPages;
        
        // Update button states
        paginationElements.firstPage.disabled = currentPage === 1;
        paginationElements.prevPage.disabled = currentPage === 1;
        paginationElements.nextPage.disabled = currentPage === totalPages || totalPages === 0;
        paginationElements.lastPage.disabled = currentPage === totalPages || totalPages === 0;
    }
    
    function calculateTotalPages() {
        return Math.ceil(filteredIncidents.length / rowsPerPageValue);
    }
    
    function goToPage(page) {
        const totalPages = calculateTotalPages();
        
        if (page >= 1 && page <= totalPages) {
            currentPage = page;
            renderTable();
            updatePagination();
        }
    }
    
    function handleSearch(e) {
        searchTerm = e.target.value.trim();
        currentPage = 1;
        applyFilters();
    }
    
    function handleFilterChange() {
        currentPage = 1;
        applyFilters();
    }
    
    function handleRowsPerPageChange(e) {
        rowsPerPageValue = parseInt(e.target.value);
        currentPage = 1;
        applyFilters();
    }
    
    function clearFilters() {
        searchInput.value = '';
        searchTerm = '';
        
        Object.values(filters).forEach(filter => {
            if (filter.tagName === 'SELECT') {
                filter.value = 'all';
            } else if (filter.tagName === 'INPUT' && filter.type === 'date') {
                filter.value = '';
            }
        });
        
        currentPage = 1;
        applyFilters();
    }
    
    function toggleExportOptions() {
        exportOptions.classList.toggle('show');
    }
    
    async function handleExport(e) {
        e.preventDefault();
        const format = e.target.dataset.format;
        
        try {
            let url = `${INCIDENTS_API}?action=export&format=${format}`;
            
            // Add current filters to export
            const params = new URLSearchParams();
            if (searchTerm) params.append('search', searchTerm);
            if (filters.status.value !== 'all') params.append('status', filters.status.value);
            if (filters.priority.value !== 'all') params.append('priority', filters.priority.value);
            if (filters.category.value !== 'all') params.append('category', filters.category.value);
            
            const queryString = params.toString();
            if (queryString) url += `&${queryString}`;
            
            if (format === 'print') {
                window.print();
                return;
            }
            
            // For other formats, download the file
            window.location.href = url;
        } catch (error) {
            console.error('Export error:', error);
            showError('Error exporting data');
        }
        
        exportOptions.classList.remove('show');
    }
    
    function updateNotificationBadges() {
        const openCount = allIncidents.filter(i => i.status === 'open').length;
        const badge = document.getElementById('incidentCountBadge');
        const notificationCount = document.getElementById('notificationCount');
        
        if (badge) badge.textContent = openCount;
        if (notificationCount) notificationCount.textContent = openCount;
    }
    
    function updateTime() {
        const timeElement = document.getElementById('currentTime');
        const lastRefresh = document.getElementById('lastRefresh');
        
        if (timeElement) {
            const now = new Date();
            timeElement.textContent = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }) + ' CAT';
        }
        
        if (lastRefresh) {
            const now = new Date();
            lastRefresh.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
    
    function showLoading() {
        loadingOverlay.style.display = 'flex';
    }
    
    function hideLoading() {
        loadingOverlay.style.display = 'none';
    }
    
    function showError(message) {
        // Create a temporary error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <span>${message}</span>
        `;
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e63946;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(errorDiv);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
    
    function showSuccess(message) {
        // Create a temporary success message
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <span>${message}</span>
        `;
        successDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2ed573;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
            successDiv.remove();
        }, 5000);
    }
    
    // Utility functions
    function getPriorityClass(priority) {
        switch (priority) {
            case 'critical': return 'critical';
            case 'high': return 'high';
            case 'medium': return 'medium';
            case 'low': return 'low';
            default: return '';
        }
    }
    
    function getPriorityIcon(priority) {
        switch (priority) {
            case 'critical': return 'fa-skull-crossbones';
            case 'high': return 'fa-exclamation-circle';
            case 'medium': return 'fa-exclamation-triangle';
            case 'low': return 'fa-info-circle';
            default: return 'fa-circle';
        }
    }
    
    function getStatusBadge(status) {
        const statusText = status.charAt(0).toUpperCase() + status.slice(1);
        return `<span class="status-badge ${status}">${statusText}</span>`;
    }
    
    function getCategoryDisplayName(category) {
        const categoryMap = {
            'security_breach': 'Security Breach',
            'unauthorized_access': 'Unauthorized Access',
            'equipment_failure': 'Equipment Failure',
            'suspicious_activity': 'Suspicious Activity',
            'cyber_attack': 'Cyber Attack',
            'physical_altercation': 'Physical Altercation',
            'theft': 'Theft',
            'data_breach': 'Data Breach',
            'natural_disaster': 'Natural Disaster',
            'fire_safety': 'Fire Safety',
            'medical_emergency': 'Medical Emergency',
            'other': 'Other'
        };
        return categoryMap[category] || category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    function getFileIcon(fileType) {
        if (fileType.includes('image')) return 'fa-file-image';
        if (fileType.includes('pdf')) return 'fa-file-pdf';
        if (fileType.includes('word') || fileType.includes('document')) return 'fa-file-word';
        if (fileType.includes('excel') || fileType.includes('spreadsheet')) return 'fa-file-excel';
        if (fileType.includes('text')) return 'fa-file-alt';
        return 'fa-file';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});