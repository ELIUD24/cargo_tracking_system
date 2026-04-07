// ==================== UTILS.JS ====================
// API Configuration & Helper Functions

const API_CONFIG = {
    baseURL: window.location.pathname.includes('/pages/')
        ? '../php/cargo.php'
        : 'php/cargo.php',
    timeout: 10000
};

// ==================== API REQUEST HELPER ====================
async function apiRequest(endpoint, method = 'GET', body = null) {
    const config = {
        method,
        headers: { 'Content-Type': 'application/json' }
    };
    
    const token = getToken();
    if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
    }
    
    if (body && method !== 'GET') {
        config.body = JSON.stringify(body);
    }
    
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);
        
        const response = await fetch(`${API_CONFIG.baseURL}?endpoint=${endpoint}`, {
            ...config,
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`API Error: ${response.status} ${response.statusText}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('API Request Error:', error);
        throw error;
    }
}

// ==================== XSS PROTECTION ====================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function sanitizeInput(input) {
    return input.replace(/[<>\"'&]/g, char => ({
        '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '&': '&amp;'
    })[char]);
}

// ==================== NOTIFICATIONS ====================
function showNotification(message, type = 'success', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
    } text-white`;
    
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => notification.classList.remove('translate-x-full'), 100);
    
    // Animate out and remove
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

// ==================== LOADING STATES ====================
function setLoading(element, isLoading, text = '') {
    if (!element) return;
    
    if (isLoading) {
        element.dataset.originalContent = element.innerHTML;
        element.innerHTML = `
            <span class="flex items-center gap-2">
                <i class="fas fa-spinner fa-spin"></i>
                ${text || 'Loading...'}
            </span>
        `;
        element.disabled = true;
    } else {
        element.innerHTML = element.dataset.originalContent || text || 'Done';
        element.disabled = false;
    }
}

// ==================== DATE FORMATTING ====================
function formatDate(dateString, format = 'default') {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    
    switch(format) {
        case 'short':
            return date.toLocaleDateString('en-KE', { month: 'short', day: 'numeric' });
        case 'time':
            return date.toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' });
        case 'full':
            return date.toLocaleDateString('en-KE', { 
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        default:
            return date.toLocaleDateString('en-KE');
    }
}

// ==================== LOCAL STORAGE HELPERS ====================
function storageSet(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch (e) {
        console.error('Storage set error:', e);
        return false;
    }
}

function storageGet(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    } catch (e) {
        console.error('Storage get error:', e);
        return defaultValue;
    }
}

function storageRemove(key) {
    localStorage.removeItem(key);
}

// ==================== DEMO DATA FALLBACKS ====================
const DEMO_DATA = {
    shipments: [
        { id: 1, tracking_number: 'CG123456789KE', sender_name: 'John Kamau', receiver_name: 'Sarah Mwangi', status: 'in_transit', created_at: '2026-02-18' },
        { id: 2, tracking_number: 'CG987654321KE', sender_name: 'Peter Omondi', receiver_name: 'Mary Njeri', status: 'delivered', created_at: '2026-02-17' },
        { id: 3, tracking_number: 'CG456789123KE', sender_name: 'James Mutua', receiver_name: 'Grace Wanjiku', status: 'pending', created_at: '2026-02-16' }
    ],
    clearances: [
        { id: 1, tracking_number: 'CG123456789KE', customer_name: 'John Kamau', destination: 'Mombasa', status: 'pending', created_at: '2026-02-18', staff_name: 'Peter Omondi' },
        { id: 2, tracking_number: 'CG987654321KE', customer_name: 'Sarah Mwangi', destination: 'Kisumu', status: 'approved', created_at: '2026-02-17', staff_name: 'Mary Njeri' }
    ]
};

function getDemoData(type) {
    return DEMO_DATA[type] || [];
}

// Export for module usage (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { apiRequest, escapeHtml, showNotification, setLoading, formatDate, storageGet, storageSet, storageRemove, getDemoData };
}