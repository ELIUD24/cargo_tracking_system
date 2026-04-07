// ==================== MAIN.JS ====================
// Application Initialization & Page Navigation

let initializationComplete = false;

// ==================== PAGE NAVIGATION ====================
function showPage(pageName) {
    console.log('📄 Navigating to:', pageName);
    
    // Hide all pages
    document.querySelectorAll('.page-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show target page
    const targetPage = document.getElementById(`${pageName}Page`);
    if (targetPage) {
        targetPage.classList.add('active');
        window.scrollTo(0, 0);
        
        // Load page-specific data
        loadPageData(pageName);
    }
    
    console.log('✅ Page shown:', pageName);
}

function loadPageData(pageName) {
    switch(pageName) {
        case 'admin':
            if (typeof loadDashboardData === 'function') loadDashboardData();
            if (typeof loadPendingApprovals === 'function') loadPendingApprovals();
            break;
        case 'staff':
            if (typeof loadPendingClearances === 'function') loadPendingClearances();
            break;
        case 'customer':
            if (typeof loadShipments === 'function') loadShipments();
            break;
    }
}

// ==================== ADMIN SECTION NAVIGATION ====================
function showAdminSection(sectionName) {
    console.log('📂 Admin section:', sectionName);
    
    // Hide all admin sections
    document.querySelectorAll('.admin-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show target section
    const target = document.getElementById(`adminSection-${sectionName}`);
    if (target) target.classList.add('active');
    
    // Update sidebar active state
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active', 'bg-primary');
        if (link.dataset.section === sectionName) {
            link.classList.add('active', 'bg-primary');
        }
    });
    
    // Update page title
    const titles = {
        'dashboard': 'Dashboard',
        'shipments': 'Shipments Management',
        'customers': 'Customer Management',
        'clearances': 'Clearance Approvals',
        'fleet': 'Fleet Management',
        'reports': 'Reports & Analytics',
        'settings': 'Settings'
    };
    
    const titleEl = document.getElementById('adminPageTitle');
    if (titleEl) titleEl.textContent = titles[sectionName] || 'Dashboard';
    
    // Load section data
    loadAdminSectionData(sectionName);
    
    showNotification(`Navigated to ${titles[sectionName]}`, 'success');
}

function loadAdminSectionData(sectionName) {
    switch(sectionName) {
        case 'shipments':
            if (typeof loadAllShipments === 'function') loadAllShipments();
            break;
        case 'customers':
            if (typeof loadCustomers === 'function') loadCustomers();
            break;
        case 'clearances':
            if (typeof loadAdminClearances === 'function') loadAdminClearances('pending');
            break;
        case 'dashboard':
            if (typeof loadDashboardData === 'function') loadDashboardData();
            if (typeof loadPendingApprovals === 'function') loadPendingApprovals();
            break;
    }
}

// ==================== STAFF SECTION NAVIGATION ====================
function showStaffSection(sectionId) {
    console.log('📂 Staff section:', sectionId);
    
    // Hide all staff sections
    document.querySelectorAll('.staff-section').forEach(section => {
        section.classList.add('hidden');
        section.classList.remove('active');
    });
    
    // Show target section
    const target = document.getElementById(`staffSection-${sectionId}`);
    if (target) {
        target.classList.remove('hidden');
        target.classList.add('active');
    }
    
    // Update sidebar
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active', 'bg-primary');
        if (link.dataset.section === sectionId) {
            link.classList.add('active', 'bg-primary');
        }
    });
    
    // Update title
    const titles = {
        'dashboard': 'Dashboard',
        'clearance': 'Clearance Forms',
        'pending': 'Pending Clearances',
        'approved': 'Approved Clearances',
        'reports': 'Reports & Analytics'
    };
    
    const titleEl = document.getElementById('staffPageTitle');
    if (titleEl) titleEl.textContent = titles[sectionId] || 'Dashboard';
    
    // Load section data
    if (sectionId === 'pending' && typeof loadPendingClearances === 'function') {
        loadPendingClearances();
    }
    if (sectionId === 'approved' && typeof loadApprovedClearances === 'function') {
        loadApprovedClearances();
    }
    
    window.scrollTo(0, 0);
    showNotification(`Navigated to ${titles[sectionId]}`, 'success');
}

// ==================== MODAL FUNCTIONS ====================
function openLoginModal() {
    const modal = document.getElementById('loginModal');
    if (!modal) return;
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLoginModal() {
    const modal = document.getElementById('loginModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
    document.body.style.overflow = 'auto';
    
    // Reset form
    const form = document.getElementById('loginForm');
    if (form) form.reset();
    
    // Reset role selection
    document.querySelectorAll('.role-card').forEach(card => {
        card.classList.remove('selected');
        card.style.borderColor = 'transparent';
    });
    const roleInput = document.getElementById('selectedRole');
    if (roleInput) roleInput.value = '';
}

function selectRole(role, element) {
    document.querySelectorAll('.role-card').forEach(card => {
        card.classList.remove('selected');
        card.style.borderColor = 'transparent';
    });
    element.classList.add('selected');
    element.style.borderColor = '#2563eb';
    document.getElementById('selectedRole').value = role;
}

function openAddShipmentModal() {
    const modal = document.getElementById('addShipmentModal');
    if (!modal) return;
    modal.classList.add('active');
    modal.style.display = 'flex';
    
    // Set default delivery date
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 3);
    const dateInput = document.querySelector('input[name="estimated_delivery"]');
    if (dateInput) dateInput.valueAsDate = futureDate;
}

function closeAddShipmentModal() {
    const modal = document.getElementById('addShipmentModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
    document.getElementById('addShipmentForm')?.reset();
}

function openAddCustomerModal() {
    const modal = document.getElementById('addCustomerModal');
    if (!modal) return;
    modal.classList.add('active');
    modal.style.display = 'flex';
}

function closeAddCustomerModal() {
    const modal = document.getElementById('addCustomerModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
    document.getElementById('addCustomerForm')?.reset();
}

// ==================== TRACKING FORM ====================
function initTrackingForm() {
    const form = document.getElementById('trackingForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const trackingNumber = document.getElementById('trackingNumber').value.trim();
        
        if (trackingNumber) {
            const trackingNumEl = document.getElementById('trackingNum');
            if (trackingNumEl) trackingNumEl.textContent = trackingNumber;
            showPage('tracking');
        }
    });
}

// ==================== INITIALIZATION ====================
function initializeApp() {
    if (initializationComplete) return;
    initializationComplete = true;
    
    console.log('✅ CargoTrack Initializing...');
    
    // Restore session
    const token = getToken();
    const user = getUser();
    
    if (token && user) {
        updateAuthButtons(true, user);
        redirectToDashboard(user.role, user.full_name || user.username);
    } else {
        updateAuthButtons(false, null);
    }
    
    // Handle URL tracking parameter
    const urlParams = new URLSearchParams(window.location.search);
    const trackingId = urlParams.get('id');
    if (trackingId) {
        const input = document.getElementById('trackingNumber');
        if (input) {
            input.value = trackingId;
            setTimeout(() => {
                document.getElementById('trackingForm')?.dispatchEvent(new Event('submit'));
            }, 500);
        }
    }
    
    // Initialize tracking form
    initTrackingForm();
    
    // Setup modal close on outside click
    setupModalCloseHandlers();
    
    console.log('✅ CargoTrack Ready');
}

function setupModalCloseHandlers() {
    ['loginModal', 'addShipmentModal', 'addCustomerModal'].forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    const closeFn = {
                        'loginModal': closeLoginModal,
                        'addShipmentModal': closeAddShipmentModal,
                        'addCustomerModal': closeAddCustomerModal
                    }[modalId];
                    closeFn?.();
                }
            });
        }
    });
}

// Keyboard support
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLoginModal();
        closeAddShipmentModal();
        closeAddCustomerModal();
    }
});

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initializeApp);

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        showPage, showAdminSection, showStaffSection,
        openLoginModal, closeLoginModal, selectRole,
        openAddShipmentModal, closeAddShipmentModal,
        openAddCustomerModal, closeAddCustomerModal,
        initializeApp
    };
}
