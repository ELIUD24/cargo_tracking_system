// ==================== ADMIN.JS ====================
// Admin Dashboard & Approval Functions

// ==================== LOAD DASHBOARD STATS ====================
async function loadDashboardData() {
    try {
        const result = await apiRequest('stats');
        
        if (result.success && result.data) {
            // Update stat cards
            const mappings = {
                'totalShipments': result.data.total_shipments,
                'inTransit': result.data.in_transit,
                'delivered': result.data.delivered,
                'pending': result.data.pending_approval || result.data.pending
            };
            
            Object.entries(mappings).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el) el.textContent = (value || 0).toLocaleString();
            });
        }
    } catch (error) {
        // Fallback to demo numbers
        const demoStats = { totalShipments: 1284, inTransit: 342, delivered: 892, pending: 50 };
        Object.entries(demoStats).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value.toLocaleString();
        });
    }
}

// ==================== LOAD PENDING SHIPMENTS ====================
async function loadPending() {
    const container = document.getElementById('pending');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl"></i><p class="mt-2">Loading...</p></div>';
    
    try {
        const result = await apiRequest('pending-shipments');
        
        if (result.success && result.data?.length > 0) {
            renderPendingShipments(result.data, container);
        } else {
            container.innerHTML = '<p class="text-center text-gray-500 py-8">No pending shipments</p>';
        }
    } catch (error) {
        // Demo fallback
        const demoPending = getDemoData('shipments').filter(s => s.status === 'pending');
        renderPendingShipments(demoPending, container);
    }
}

function renderPendingShipments(shipments, container) {
    if (!shipments?.length) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">No pending shipments</p>';
        return;
    }
    
    container.innerHTML = shipments.map(s => `
        <div class="border rounded-lg p-4 hover:shadow-md transition bg-white">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-mono text-primary font-semibold">${escapeHtml(s.tracking_number)}</p>
                    <p class="text-sm text-gray-600 mt-1">
                        <i class="fas fa-user mr-1"></i>${escapeHtml(s.sender_name)} → ${escapeHtml(s.receiver_name)}
                    </p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">PENDING</span>
            </div>
            <div class="flex gap-2">
                <button onclick="updateShipmentStatus(${s.id}, 'approved')" 
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-check mr-1"></i>Approve
                </button>
                <button onclick="updateShipmentStatus(${s.id}, 'rejected')" 
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-times mr-1"></i>Reject
                </button>
            </div>
        </div>
    `).join('');
}

// ==================== CLEARANCE APPROVALS (Admin) ====================
async function loadAdminClearances(filter = 'pending') {
    const container = document.getElementById('adminClearancesList');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-primary mb-4"></i><p class="text-gray-600">Loading clearances...</p></div>';
    
    try {
        const result = await apiRequest(`clearances?filter=${filter}`);
        
        if (result.success && result.data?.length > 0) {
            renderAdminClearances(result.data, container);
        } else {
            renderAdminClearances(getDemoData('clearances').filter(c => filter === 'all' || c.status === filter), container);
        }
    } catch (error) {
        renderAdminClearances(getDemoData('clearances'), container);
    }
}

function renderAdminClearances(clearances, container) {
    if (!clearances?.length) {
        container.innerHTML = '<div class="text-center py-12 text-gray-500"><i class="fas fa-inbox text-4xl mb-4"></i><p>No clearances found</p></div>';
        return;
    }
    
    const statusConfig = {
        pending: { class: 'bg-yellow-100 text-yellow-800', label: 'PENDING' },
        approved: { class: 'bg-green-100 text-green-800', label: 'APPROVED' },
        rejected: { class: 'bg-red-100 text-red-800', label: 'REJECTED' }
    };
    
    container.innerHTML = clearances.map(c => {
        const status = statusConfig[c.status] || statusConfig.pending;
        return `
        <div class="border rounded-lg p-4 hover:shadow-md transition bg-white">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-mono text-primary font-semibold">${escapeHtml(c.tracking_number)}</p>
                    <p class="text-sm text-gray-600 mt-1"><i class="fas fa-user mr-2"></i>${escapeHtml(c.customer_name)}</p>
                    <p class="text-sm text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>${escapeHtml(c.destination)}</p>
                    ${c.driver_name ? `<p class="text-sm text-gray-600"><i class="fas fa-truck mr-2"></i>${escapeHtml(c.driver_name)} - ${escapeHtml(c.vehicle_reg)}</p>` : ''}
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full ${status.class}">${status.label}</span>
            </div>
            ${c.status === 'pending' ? `
            <div class="flex gap-2 mt-4">
                <button onclick="adminApproveClearance(${c.id})" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-check mr-2"></i>Approve
                </button>
                <button onclick="adminRejectClearance(${c.id})" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-times mr-2"></i>Reject
                </button>
            </div>
            ` : ''}
            ${c.approved_at ? `<p class="text-xs text-gray-500 mt-2"><i class="fas fa-check-circle mr-1"></i>Approved: ${formatDate(c.approved_at)}</p>` : ''}
            ${c.rejection_reason ? `<p class="text-xs text-red-500 mt-2"><i class="fas fa-times-circle mr-1"></i>Rejected: ${escapeHtml(c.rejection_reason)}</p>` : ''}
        </div>`;
    }).join('');
}

async function adminApproveClearance(id) {
    if (!confirm('Approve this clearance?')) return;
    
    try {
        const result = await apiRequest('approve-clearance', 'POST', { clearance_id: id });
        
        if (result.success) {
            showNotification('Clearance approved!', 'success');
            loadAdminClearances('pending');
            if (typeof loadPendingClearances === 'function') loadPendingClearances();
        } else {
            showNotification(result.message || 'Approval failed', 'error');
        }
    } catch (error) {
        // Demo fallback
        let clearances = storageGet('clearances', getDemoData('clearances'));
        const idx = clearances.findIndex(c => c.id === id);
        if (idx !== -1) {
            clearances[idx].status = 'approved';
            clearances[idx].approved_at = new Date().toISOString();
            storageSet('clearances', clearances);
            showNotification('Clearance approved (Demo)!', 'success');
            loadAdminClearances('pending');
        }
    }
}

async function adminRejectClearance(id) {
    const reason = prompt('Enter rejection reason:');
    if (!reason?.trim()) {
        showNotification('Rejection reason required', 'error');
        return;
    }
    
    try {
        const result = await apiRequest('reject-clearance', 'POST', { 
            clearance_id: id, 
            rejection_reason: reason.trim() 
        });
        
        if (result.success) {
            showNotification('Clearance rejected', 'success');
            loadAdminClearances('pending');
        } else {
            showNotification(result.message || 'Rejection failed', 'error');
        }
    } catch (error) {
        // Demo fallback
        let clearances = storageGet('clearances', getDemoData('clearances'));
        const idx = clearances.findIndex(c => c.id === id);
        if (idx !== -1) {
            clearances[idx].status = 'rejected';
            clearances[idx].rejection_reason = reason;
            clearances[idx].rejected_at = new Date().toISOString();
            storageSet('clearances', clearances);
            showNotification('Clearance rejected (Demo)!', 'success');
            loadAdminClearances('pending');
        }
    }
}

// ==================== LOAD PENDING APPROVALS (Admin Dashboard) ====================
function loadPendingApprovals() {
    const tbody = document.getElementById('pendingApprovalsTable');
    const countEl = document.getElementById('pendingApprovalsCount');
    if (!tbody) return;
    
    let pending = storageGet('clearances', getDemoData('clearances')).filter(c => c.status === 'pending');
    
    if (!pending.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-check-circle text-green-500 text-2xl"></i><p class="mt-2">No pending approvals</p></td></tr>';
        countEl.textContent = '0 Pending';
        return;
    }
    
    countEl.textContent = `${pending.length} Pending`;
    
    tbody.innerHTML = pending.map(c => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">${escapeHtml(c.tracking_number)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${escapeHtml(c.staff_name || 'Staff')}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHtml(c.customer_name)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHtml(c.destination)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${formatDate(c.created_at, 'short')}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <button onclick="adminApproveClearance(${c.id})" class="text-green-600 hover:text-green-800 mr-3" title="Approve">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button onclick="adminRejectClearance(${c.id})" class="text-red-600 hover:text-red-800" title="Reject">
                    <i class="fas fa-times"></i> Reject
                </button>
            </td>
        </tr>
    `).join('');
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        loadDashboardData, loadPending, loadAdminClearances, 
        adminApproveClearance, adminRejectClearance, loadPendingApprovals 
    };
}