class AuthHandler {
    constructor() {
        this.apiBase = '../api'; // Corrected: Points to API directory
        this.isAuthenticated = this.checkAuthStatus();
    }
    
    async login(username, password) {
        try {
            const response = await fetch(`${this.apiBase}/auth.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Store user info in localStorage or sessionStorage
                sessionStorage.setItem('user', JSON.stringify(data.user));
                sessionStorage.setItem('authenticated', 'true');
                
                // Redirect to dashboard after a brief delay
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 1000);
            }
            
            return data;
            
        } catch (error) {
            console.error('Login error:', error);
            return {
                success: false,
                message: 'Network error. Please try again.'
            };
        }
    }
    
    async logout() {
        try {
            const response = await fetch(`${this.apiBase}/logout.php`);
            const data = await response.json();
            
            if (data.success) {
                // Clear storage
                sessionStorage.removeItem('user');
                sessionStorage.removeItem('authenticated');
                
                // Redirect to login page
                window.location.href = 'login.html';
            }
            
            return data;
            
        } catch (error) {
            console.error('Logout error:', error);
            // Still redirect even if API call fails
            sessionStorage.clear();
            window.location.href = 'login.html';
            return { success: true };
        }
    }
    
    checkAuthStatus() {
        const authenticated = sessionStorage.getItem('authenticated');
        const user = sessionStorage.getItem('user');
        
        if (authenticated === 'true' && user) {
            try {
                const userData = JSON.parse(user);
                return {
                    authenticated: true,
                    user: userData
                };
            } catch (e) {
                return { authenticated: false };
            }
        }
        
        return { authenticated: false };
    }
    
    getCurrentUser() {
        const authStatus = this.checkAuthStatus();
        return authStatus.authenticated ? authStatus.user : null;
    }
    
    // Middleware to protect pages
    requireAuth(redirectTo = 'login.html') {
        const authStatus = this.checkAuthStatus();
        if (!authStatus.authenticated) {
            window.location.href = redirectTo;
            return false;
        }
        return true;
    }
    
    // Check if user has specific role
    hasRole(requiredRole) {
        const user = this.getCurrentUser();
        return user && user.role === requiredRole;
    }
}

// Initialize auth handler globally
const auth = new AuthHandler();

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AuthHandler, auth };
}