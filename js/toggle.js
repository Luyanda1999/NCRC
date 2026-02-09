// toggle.js - Enhanced Sidebar, Time, and Search Management Class

class NCRCDashboard {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.mainContent = document.getElementById('mainContent');
        this.toggleSidebarBtn = document.getElementById('toggleSidebar');
        this.currentTimeElement = document.getElementById('currentTime');
        this.logoutBtn = document.getElementById('logoutBtn');
        this.notificationBell = document.getElementById('notificationBell');
        this.searchInput = document.getElementById('searchInput');
        this.incidentForm = document.getElementById('incidentForm');
        
        this.apiBase = '../api'; // Added API base for logout
        this.initialize();
    }
    
    initialize() {
        // Initialize sidebar
        this.initSidebar();
        
        // Initialize time
        this.initTime();
        
        // Initialize event listeners
        this.initEventListeners();
        
        // Initialize search
        this.initSearch();
        
        // Handle responsive behavior
        this.handleResponsive();
        
        // Setup navigation active states
        this.setupNavigation();
        
        // Setup auto-logout (optional - 30 minutes inactivity)
        this.setupAutoLogout(30);
    }
    
    // ===== SIDEBAR MANAGEMENT =====
    initSidebar() {
        if (!this.sidebar || !this.toggleSidebarBtn) return;
        
        const toggleIcon = this.toggleSidebarBtn.querySelector('i');
        
        this.toggleSidebarBtn.addEventListener('click', () => this.toggleSidebar());
        
        // Load saved sidebar state
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            this.collapseSidebar();
        }
    }
    
    toggleSidebar() {
        if (!this.sidebar || !this.toggleSidebarBtn) return;
        
        const toggleIcon = this.toggleSidebarBtn.querySelector('i');
        const isCollapsed = this.sidebar.classList.contains('collapsed');
        
        if (isCollapsed) {
            this.expandSidebar();
        } else {
            this.collapseSidebar();
        }
        
        // Save state
        localStorage.setItem('sidebarCollapsed', !isCollapsed);
    }
    
    collapseSidebar() {
        this.sidebar.classList.add('collapsed');
        this.mainContent.classList.add('expanded');
        
        const toggleIcon = this.toggleSidebarBtn?.querySelector('i');
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        }
    }
    
    expandSidebar() {
        this.sidebar.classList.remove('collapsed');
        this.mainContent.classList.remove('expanded');
        
        const toggleIcon = this.toggleSidebarBtn?.querySelector('i');
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
    }
    
    // ===== TIME MANAGEMENT =====
    initTime() {
        if (!this.currentTimeElement) return;
        
        // Update immediately
        this.updateTime();
        
        // Update every second
        setInterval(() => this.updateTime(), 1000);
    }
    
    updateTime() {
        if (!this.currentTimeElement) return;
        
        const now = new Date();
        const timezone = 'CAT';
        const timezoneOffset = 2; // CAT is UTC+2
        
        // Calculate CAT time (UTC+2)
        const catTime = new Date(now.getTime() + (timezoneOffset * 60 * 60 * 1000));
        
        // Format the time using UTC methods since we already added the offset
        const hours = catTime.getUTCHours().toString().padStart(2, '0');
        const minutes = catTime.getUTCMinutes().toString().padStart(2, '0');
        const seconds = catTime.getUTCSeconds().toString().padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;
        
        // Update display
        this.currentTimeElement.textContent = `${timeString} ${timezone}`;
        this.currentTimeElement.setAttribute('title', 'Central Africa Time (UTC+2) - Auto-updating');
    }
    
    // ===== SEARCH FUNCTIONALITY =====
    initSearch() {
        if (!this.searchInput) return;
        
        // Add search icon if not present
        if (!this.searchInput.previousElementSibling?.classList.contains('search-icon')) {
            const searchContainer = this.searchInput.parentElement;
            const searchIcon = document.createElement('i');
            searchIcon.className = 'fas fa-search search-icon';
            searchContainer.insertBefore(searchIcon, this.searchInput);
        }
        
        // Handle search input
        this.searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        
        // Add keyboard shortcut (Ctrl+F)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                this.searchInput?.focus();
            }
        });
        
        // Add clear button
        this.addClearSearchButton();
    }
    
    addClearSearchButton() {
        if (!this.searchInput) return;
        
        const searchContainer = this.searchInput.parentElement;
        const clearBtn = document.createElement('button');
        clearBtn.className = 'clear-search-btn';
        clearBtn.innerHTML = '<i class="fas fa-times"></i>';
        clearBtn.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #aaa;
            cursor: pointer;
            display: none;
        `;
        
        clearBtn.addEventListener('click', () => {
            this.searchInput.value = '';
            this.searchInput.focus();
            this.handleSearch('');
            clearBtn.style.display = 'none';
        });
        
        this.searchInput.addEventListener('input', () => {
            clearBtn.style.display = this.searchInput.value ? 'block' : 'none';
        });
        
        searchContainer.style.position = 'relative';
        searchContainer.appendChild(clearBtn);
    }
    
    handleSearch(query) {
        const trimmedQuery = query.trim().toLowerCase();
        
        // Different search behaviors based on page
        if (window.location.pathname.includes('Incident_table.html')) {
            this.searchIncidentTable(trimmedQuery);
        } else if (window.location.pathname.includes('dashboard.html')) {
            this.searchDashboard(trimmedQuery);
        } else if (window.location.pathname.includes('Incident_report.html')) {
            this.searchIncidentForm(trimmedQuery);
        } else {
            this.generalSearch(trimmedQuery);
        }
    }
    
    searchIncidentTable(query) {
        if (!query) {
            // Reset all rows visibility if query is empty
            document.querySelectorAll('#reportsTableBody tr').forEach(row => {
                row.style.display = '';
            });
            return;
        }
        
        let matchCount = 0;
        document.querySelectorAll('#reportsTableBody tr').forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const matches = rowText.includes(query);
            row.style.display = matches ? '' : 'none';
            if (matches) matchCount++;
        });
        
        // Update visible count if element exists
        const visibleCountElement = document.getElementById('visibleCount');
        if (visibleCountElement) {
            visibleCountElement.textContent = matchCount;
        }
        
        // Show/hide empty state
        const emptyState = document.getElementById('emptyState');
        if (emptyState) {
            emptyState.style.display = matchCount === 0 ? 'block' : 'none';
        }
    }
    
    searchDashboard(query) {
        // Search dashboard cards and feeds
        const searchables = document.querySelectorAll(
            '.status-card, .feed-card, .activity-item, .action-btn'
        );
        
        if (!query) {
            // Reset all items
            searchables.forEach(item => {
                item.style.opacity = '1';
                item.style.border = '';
            });
            return;
        }
        
        searchables.forEach(item => {
            const itemText = item.textContent.toLowerCase();
            const matches = itemText.includes(query);
            
            if (matches) {
                item.style.opacity = '1';
                item.style.border = '2px solid var(--neon-green)';
                // Scroll into view smoothly
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                item.style.opacity = '0.5';
                item.style.border = '';
            }
        });
    }
    
    searchIncidentForm(query) {
        if (!query || !this.incidentForm) return;
        
        // Highlight form fields that contain the search term
        const formElements = this.incidentForm.querySelectorAll(
            'input, select, textarea, label, .form-note'
        );
        
        let foundMatch = false;
        formElements.forEach(element => {
            const text = element.textContent || element.value || element.placeholder || '';
            if (text.toLowerCase().includes(query)) {
                // Highlight the element
                element.style.backgroundColor = 'rgba(0, 255, 157, 0.1)';
                element.style.borderColor = 'var(--neon-green)';
                
                // Scroll to element
                if (!foundMatch) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    foundMatch = true;
                }
                
                // Clear highlight after 3 seconds
                setTimeout(() => {
                    element.style.backgroundColor = '';
                    element.style.borderColor = '';
                }, 3000);
            }
        });
        
        // Show notification if no matches found
        if (!foundMatch && query.length > 2) {
            this.showNotification(`No matches found for "${query}"`, 'info');
        }
    }
    
    generalSearch(query) {
        // Generic search for other pages
        const allTextElements = document.querySelectorAll('p, h1, h2, h3, h4, li, span:not(.fas)');
        
        if (!query) {
            // Reset all elements
            allTextElements.forEach(el => {
                el.style.backgroundColor = '';
            });
            return;
        }
        
        let matchCount = 0;
        allTextElements.forEach(el => {
            const text = el.textContent.toLowerCase();
            if (text.includes(query)) {
                el.style.backgroundColor = 'rgba(0, 255, 157, 0.2)';
                matchCount++;
                
                // Scroll to first match
                if (matchCount === 1) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                el.style.backgroundColor = '';
            }
        });
        
        if (matchCount === 0 && query.length > 2) {
            this.showNotification(`No results found for "${query}"`, 'info');
        }
    }
    
    // ===== EVENT LISTENERS =====
    initEventListeners() {
        // Logout button
        if (this.logoutBtn) {
            this.logoutBtn.addEventListener('click', (e) => this.handleLogout(e));
        }
        
        // Notification bell
        if (this.notificationBell) {
            this.notificationBell.addEventListener('click', () => this.handleNotifications());
        }
        
        // Responsive resize
        window.addEventListener('resize', () => this.handleResponsive());
        
        // Navigation clicks
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => this.handleNavClick(e));
        });
        
        // Escape key to clear search
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.searchInput) {
                this.searchInput.value = '';
                this.handleSearch('');
                this.searchInput.blur();
            }
        });
    }
    
    // ===== ENHANCED LOGOUT FUNCTIONALITY =====
    async handleLogout(e) {
        e.preventDefault();
        const pageName = document.title.split('|')[1]?.trim() || 'Fidelity NCRC System';
        
        if (confirm(`Are you sure you want to log out of ${pageName}?`)) {
            // Visual feedback
            const originalHTML = this.logoutBtn.innerHTML;
            this.logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
            this.logoutBtn.disabled = true;
            
            try {
                // Try to use global auth handler first
                if (typeof auth !== 'undefined' && auth.logout) {
                    await auth.logout();
                } else {
                    // Fallback: direct API call
                    await this.performLogout();
                }
            } catch (error) {
                console.error('Logout failed:', error);
                // Force logout on client side
                this.clearSession();
                window.location.href = 'login.html';
            }
        }
    }
    
    async performLogout() {
        try {
            const response = await fetch(`${this.apiBase}/logout.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.clearSession();
                window.location.href = 'login.html';
            } else {
                throw new Error('Logout API failed');
            }
        } catch (error) {
            // Even if API fails, clear session and redirect
            this.clearSession();
            window.location.href = 'login.html';
        }
    }
    
    clearSession() {
        // Clear all session storage
        sessionStorage.clear();
        
        // Clear all localStorage items related to auth
        const authKeys = ['user', 'authenticated', 'token', 'sidebarCollapsed'];
        authKeys.forEach(key => localStorage.removeItem(key));
        
        // Clear any cookies (optional)
        document.cookie.split(";").forEach(function(c) {
            document.cookie = c.replace(/^ +/, "")
                .replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
        });
    }
    
    setupAutoLogout(minutes) {
        if (!this.logoutBtn) return; // Only setup if on a page with logout
        
        let timeout;
        const logoutTime = minutes * 60 * 1000;
        let warningTimeout;
        
        const resetTimer = () => {
            clearTimeout(timeout);
            clearTimeout(warningTimeout);
            
            timeout = setTimeout(() => {
                // Show warning 1 minute before auto-logout
                warningTimeout = setTimeout(() => {
                    if (sessionStorage.getItem('authenticated') === 'true') {
                        this.showAutoLogoutWarning();
                    }
                }, logoutTime - 60000); // 1 minute warning
            }, 100); // Small delay to avoid immediate execution
        };
        
        // Events that reset the timer
        const events = ['click', 'mousemove', 'keypress', 'scroll', 'touchstart', 'mousedown'];
        events.forEach(event => {
            document.addEventListener(event, resetTimer);
        });
        
        // Start the timer
        resetTimer();
    }
    
    showAutoLogoutWarning() {
        // Create warning modal
        const warningModal = document.createElement('div');
        warningModal.className = 'logout-warning-modal';
        warningModal.innerHTML = `
            <div class="modal-content">
                <h3><i class="fas fa-clock"></i> Session Expiring</h3>
                <p>Your session will expire due to inactivity in 1 minute.</p>
                <p>Do you want to stay logged in?</p>
                <div class="modal-buttons">
                    <button id="stayLoggedIn" class="btn btn-success">
                        <i class="fas fa-check"></i> Stay Logged In
                    </button>
                    <button id="logoutNow" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout Now
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(warningModal);
        
        // Add button functionality
        warningModal.querySelector('#stayLoggedIn').addEventListener('click', () => {
            warningModal.remove();
            // Reset the auto-logout timer
            this.setupAutoLogout(30);
        });
        
        warningModal.querySelector('#logoutNow').addEventListener('click', () => {
            this.clearSession();
            window.location.href = 'login.html';
        });
        
        // Auto-logout after 60 seconds if no response
        setTimeout(() => {
            if (document.contains(warningModal)) {
                warningModal.remove();
                this.clearSession();
                window.location.href = 'login.html';
            }
        }, 60000);
    }
    
    handleNotifications() {
        const notificationCount = this.notificationBell?.querySelector('.alert-badge');
        if (notificationCount && notificationCount.textContent !== '0') {
            // Show notification modal or dropdown
            this.showNotificationModal(notificationCount.textContent);
            
            // Mark as read
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
        } else {
            this.showNotification('No new notifications', 'info');
        }
    }
    
    handleNavClick(e) {
        const link = e.currentTarget;
        
        // Add visual feedback
        link.classList.add('clicking');
        setTimeout(() => {
            link.classList.remove('clicking');
        }, 300);
        
        // Update active states
        this.setupNavigation();
    }
    
    // ===== NAVIGATION MANAGEMENT =====
    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link');
        const currentPage = window.location.pathname.split('/').pop() || 'dashboard.html';
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            
            const linkPage = link.getAttribute('href');
            const isReportPage = currentPage === 'Incident_report.html';
            const isTablePage = currentPage === 'Incident_table.html';
            const isDashboardPage = currentPage === 'dashboard.html';
            
            if ((linkPage === currentPage) || 
                (isReportPage && linkPage === 'Incident_report.html') ||
                (isTablePage && linkPage === 'Incident_table.html') ||
                (isDashboardPage && (linkPage === 'dashboard.html' || linkPage === '' || currentPage === ''))) {
                link.classList.add('active');
            }
        });
    }
    
    // ===== RESPONSIVE HANDLING =====
    handleResponsive() {
        if (window.innerWidth <= 1200 && this.sidebar && !this.sidebar.classList.contains('collapsed')) {
            this.collapseSidebar();
        }
        
        if (window.innerWidth <= 768 && this.sidebar && !this.sidebar.classList.contains('collapsed')) {
            this.collapseSidebar();
        }
        
        // Adjust search input width on mobile
        if (this.searchInput) {
            if (window.innerWidth <= 768) {
                this.searchInput.style.width = '180px';
            } else if (window.innerWidth <= 992) {
                this.searchInput.style.width = '250px';
            } else {
                this.searchInput.style.width = '300px';
            }
        }
    }
    
    // ===== UTILITY METHODS =====
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Style the notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'rgba(0, 255, 157, 0.9)' : 
                        type === 'error' ? 'rgba(230, 57, 70, 0.9)' : 
                        'rgba(10, 116, 218, 0.9)'};
            color: white;
            padding: 15px 20px;
            border-radius: 4px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    showNotificationModal(count) {
        // Create modal for notifications
        const modal = document.createElement('div');
        modal.className = 'notification-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-bell"></i> Notifications (${count} new)</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="notification-item">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>System Alert</strong>
                            <p>Perimeter breach detected at East Gate</p>
                            <small>2 minutes ago</small>
                        </div>
                    </div>
                    <div class="notification-item">
                        <i class="fas fa-user-check"></i>
                        <div>
                            <strong>Access Log</strong>
                            <p>Security Manager logged in</p>
                            <small>5 minutes ago</small>
                        </div>
                    </div>
                    <div class="notification-item">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>System Update</strong>
                            <p>All security systems operational</p>
                            <small>10 minutes ago</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal styles
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        `;
        
        modal.querySelector('.close-modal').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        document.body.appendChild(modal);
    }
    
    // ===== PUBLIC METHODS =====
    getTime() {
        const now = new Date();
        const timezone = 'CAT';
        const offset = 2; // CAT is UTC+2
        const catTime = new Date(now.getTime() + (offset * 60 * 60 * 1000));
        return {
            hours: catTime.getUTCHours().toString().padStart(2, '0'),
            minutes: catTime.getUTCMinutes().toString().padStart(2, '0'),
            seconds: catTime.getUTCSeconds().toString().padStart(2, '0'),
            timezone: timezone
        };
    }
    
    isSidebarCollapsed() {
        return this.sidebar?.classList.contains('collapsed') || false;
    }
    
    // Quick logout method
    async quickLogout() {
        await this.performLogout();
    }
    
    // ===== STATIC METHODS =====
    static formatTime(date, timezone = 'CAT') {
        // Convert to CAT (UTC+2)
        const catDate = new Date(date.getTime() + (2 * 60 * 60 * 1000));
        const hours = catDate.getUTCHours().toString().padStart(2, '0');
        const minutes = catDate.getUTCMinutes().toString().padStart(2, '0');
        const seconds = catDate.getUTCSeconds().toString().padStart(2, '0');
        return `${hours}:${minutes}:${seconds} ${timezone}`;
    }
    
    static getCurrentPage() {
        return window.location.pathname.split('/').pop() || 'dashboard.html';
    }
}

// Initialize the dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.ncrcdashboard = new NCRCDashboard();
    
    // Add global CSS for animations and enhancements
    if (!document.querySelector('#dashboard-animations')) {
        const style = document.createElement('style');
        style.id = 'dashboard-animations';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            .nav-link.clicking {
                transform: scale(0.98);
                transition: transform 0.1s;
            }
            
            .notification-item {
                display: flex;
                gap: 15px;
                padding: 15px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            
            .notification-item:last-child {
                border-bottom: none;
            }
            
            .notification-item i {
                font-size: 1.2rem;
                color: var(--neon-green);
            }
            
            .modal-content {
                background: var(--card-bg);
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                border: 1px solid var(--neon-green);
            }
            
            .modal-header {
                padding: 20px;
                border-bottom: 1px solid rgba(0,255,157,0.2);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                color: white;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .close-modal {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }
            
            .modal-body {
                padding: 20px;
                max-height: 400px;
                overflow-y: auto;
            }
            
            /* Logout warning modal styles */
            .logout-warning-modal .modal-content {
                background: var(--card-bg);
                padding: 30px;
                border-radius: 8px;
                max-width: 400px;
                width: 90%;
                border: 2px solid var(--neon-green);
                text-align: center;
            }
            
            .logout-warning-modal h3 {
                color: #ffd700;
                margin-bottom: 15px;
            }
            
            .logout-warning-modal p {
                margin: 10px 0;
                color: #ccc;
            }
            
            .modal-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin-top: 20px;
            }
            
            .modal-buttons .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: bold;
            }
            
            .btn-success {
                background: linear-gradient(90deg, #067521, #06923b);
                color: white;
            }
            
            .btn-danger {
                background: linear-gradient(90deg, #e63946, #c1121f);
                color: white;
            }
            
            /* Clear search button hover effect */
            .clear-search-btn:hover {
                color: #fff !important;
                background: rgba(255,255,255,0.1) !important;
                border-radius: 50% !important;
                width: 24px !important;
                height: 24px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            /* Search highlight animation */
            .search-highlight {
                animation: pulse 1s infinite;
                background-color: rgba(0, 255, 157, 0.3) !important;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Add console greeting
    console.log('%cFidelity NCRC Dashboard Initialized', 'color: #00ff9d; font-size: 16px; font-weight: bold;');
    console.log('%cEnhanced Sidebar, Time, Search, and Logout functionality ready', 'color: #ffd700; font-size: 14px;');
    console.log('%cTimezone: Central Africa Time (CAT - UTC+2)', 'color: #0a74da; font-size: 12px;');
    
    // Add global logout function
    window.forceLogout = function() {
        if (window.ncrcdashboard) {
            window.ncrcdashboard.clearSession();
            window.location.href = 'login.html';
        }
    };
});