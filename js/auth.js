// ==================== AUTH.JS ====================
// Authentication & User Management

let currentUser = null;
let authToken = null;

// ==================== TOKEN HANDLING ====================
function saveToken(token) {
    if (!token) return false;
    try {
        localStorage.setItem('authToken', token);
        authToken = token;
        return true;
    } catch (e) {
        console.error('Token save error:', e);
        return false;
    }
}

function getToken() {
    if (authToken) return authToken;
    authToken = localStorage.getItem('authToken');
    return authToken;
}

function clearToken() {
    localStorage.removeItem('authToken');
    authToken = null;
}

// ==================== USER SESSION ====================
function saveUser(user) {
    if (!user) return false;
    try {
        localStorage.setItem('currentUser', JSON.stringify(user));
        currentUser = user;
        return true;
    } catch (e) {
        console.error('User save error:', e);
        return false;
    }
}

function getUser() {
    if (currentUser) return currentUser;
    try {
        const user = localStorage.getItem('currentUser');
        currentUser = user ? JSON.parse(user) : null;
        return currentUser;
    } catch (e) {
        console.error('User get error:', e);
        return null;
    }
}

function clearUser() {
    localStorage.removeItem('currentUser');
    currentUser = null;
}

function isAuthenticated() {
    return !!getToken() && !!getUser();
}

function getUserRole() {
    const user = getUser();
    return user?.role || null;
}

// ==================== LOGIN/LOGOUT ====================
async function login(email, password, role) {
    // Demo mode for testing
    if (email === 'admin@cargotrack.co.ke' && password === 'admin123' && role === 'admin') {
        saveToken('demo-token-admin');
        saveUser({ role: 'admin', username: 'admin', full_name: 'Admin User', email });
        showNotification('Welcome, Admin!', 'success');
        return { success: true, data: { token: 'demo-token-admin', user: getUser() } };
    }
    
    if (email === 'staff@cargotrack.co.ke' && password === 'staff123' && role === 'staff') {
        saveToken('demo-token-staff');
        saveUser({ role: 'staff', username: 'staff', full_name: 'Staff Member', email });
        showNotification('Welcome, Staff!', 'success');
        return { success: true, data: { token: 'demo-token-staff', user: getUser() } };
    }
    
    // Real API call
    try {
        const result = await apiRequest('login', 'POST', { username: email, password });
        
        if (result.success) {
            saveToken(result.data.token);
            saveUser(result.data.user);
            showNotification(`Welcome, ${result.data.user.full_name || result.data.user.username}!`, 'success');
        }
        
        return result;
    } catch (error) {
        showNotification('Connection error. Please try again.', 'error');
        return { success: false, message: 'Connection failed' };
    }
}

function logout() {
    clearToken();
    clearUser();
    updateAuthButtons(false, null);
    showNotification('You have been logged out', 'success');
    
    // Redirect to home
    if (typeof showPage === 'function') {
        showPage('home');
    } else {
        window.location.href = 'index.html';
    }
}

// ==================== AUTH UI UPDATES ====================
function updateAuthButtons(isLoggedIn, user) {
    const authButtons = document.getElementById('authButtons');
    if (!authButtons) return;
    
    if (isLoggedIn && user) {
        const name = (user.full_name || user.username || 'User').split(' ')[0];
        authButtons.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600 hidden sm:inline">Welcome, ${escapeHtml(name)}</span>
                <button onclick="logout()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium transition flex items-center gap-2">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>`;
    } else {
        authButtons.innerHTML = `
            <button onclick="openLoginModal()" class="bg-primary hover:bg-secondary text-white px-5 py-2 rounded-md text-sm font-medium transition duration-150 flex items-center gap-2">
                <i class="fas fa-user"></i> Sign In
            </button>`;
    }
}

function redirectToDashboard(role, name) {
    const userName = name ? name.split(' ')[0] : 'User';
    
    // Update name displays
    const nameElements = {
        'admin': document.getElementById('adminName'),
        'staff': document.getElementById('staffNameDisplay'),
        'customer': document.getElementById('customerName')
    };
    
    if (nameElements[role]) {
        nameElements[role].textContent = userName;
    }
    
    // Navigate to appropriate page
    const pageMap = {
        'admin': 'admin',
        'staff': 'staff', 
        'customer': 'customer'
    };
    
    if (typeof showPage === 'function') {
        showPage(pageMap[role] || 'home');
        
        // Load role-specific data
        if (role === 'admin' && typeof loadDashboardData === 'function') {
            loadDashboardData();
        }
    }
}

// ==================== ROLE CHECKS ====================
function requireAuth(allowedRoles = []) {
    if (!isAuthenticated()) {
        openLoginModal?.();
        return false;
    }
    
    const userRole = getUserRole();
    if (allowedRoles.length > 0 && !allowedRoles.includes(userRole)) {
        showNotification('Access denied. Insufficient permissions.', 'error');
        return false;
    }
    
    return true;
}

function isAdmin() { return getUserRole() === 'admin'; }
function isStaff() { return getUserRole() === 'staff'; }
function isCustomer() { return getUserRole() === 'customer'; }

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        login, logout, saveToken, getToken, clearToken,
        saveUser, getUser, clearUser, isAuthenticated, getUserRole,
        updateAuthButtons, redirectToDashboard, requireAuth, isAdmin, isStaff, isCustomer
    };
}