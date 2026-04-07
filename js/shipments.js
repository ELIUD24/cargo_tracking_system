// ==================== SHIPMENTS.JS ====================
// Shipment CRUD Operations

let allShipments = [];
let currentShipmentFilter = 'all';

// ==================== CREATE SHIPMENT ====================
async function createShipment(shipmentData) {
    if (!requireAuth(['customer', 'admin'])) return;
    
    try {
        const result = await apiRequest('create-shipment', 'POST', shipmentData);
        
        if (result.success) {
            showNotification('Shipment created successfully!', 'success');
            // Refresh shipment lists
            if (typeof loadShipments === 'function') loadShipments();
            if (typeof loadAllShipments === 'function') loadAllShipments();
        } else {
            showNotification(result.message || 'Failed to create shipment', 'error');
        }
        
        return result;
    } catch (error) {
        // Demo fallback
        const newShipment = {
            id: Date.now(),
            tracking_number: `CG${Date.now()}KE`,
            ...shipmentData,
            status: 'pending',
            created_at: new Date().toISOString()
        };
        
        allShipments.push(newShipment);
        storageSet('shipments', allShipments);
        
        showNotification('Shipment created (Demo mode)!', 'success');
        return { success: true, data: { shipment: newShipment } };
    }
}

// ==================== LOAD SHIPMENTS ====================
async function loadShipments(customerId = null) {
    const tbody = document.getElementById('shipmentsTable');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    
    try {
        const endpoint = customerId ? `customer-shipments?customer_id=${customerId}` : 'shipments';
        const result = await apiRequest(endpoint);
        
        if (result.success && result.data) {
            renderShipmentsTable(result.data, tbody);
        } else {
            // Use demo data
            renderShipmentsTable(getDemoData('shipments'), tbody);
        }
    } catch (error) {
        renderShipmentsTable(getDemoData('shipments'), tbody);
    }
}

// ==================== LOAD ALL SHIPMENTS (Admin) ====================
function loadAllShipments() {
    const tbody = document.getElementById('allShipmentsTable');
    const countEl = document.getElementById('allShipmentsCount');
    if (!tbody) return;
    
    // Load from storage or use demo
    allShipments = storageGet('shipments', getDemoData('shipments'));
    renderAllShipments(allShipments, tbody, countEl);
}

function renderAllShipments(data, tbody, countEl) {
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-inbox text-4xl mb-2"></i><p>No shipments found</p></td></tr>';
        if (countEl) countEl.textContent = '0';
        return;
    }
    
    const statusColors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'in_transit': 'bg-blue-100 text-blue-800',
        'delivered': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    
    tbody.innerHTML = data.map(s => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">${escapeHtml(s.tracking_number)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${escapeHtml(s.sender_name || 'N/A')}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHtml(s.receiver_name || 'N/A')}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[s.status] || 'bg-gray-100'}">
                    ${escapeHtml(s.status?.toUpperCase() || 'PENDING')}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <button onclick="viewShipment('${escapeHtml(s.tracking_number)}')" class="text-primary hover:text-secondary mr-3" title="View">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
    
    if (countEl) countEl.textContent = data.length;
}

function renderShipmentsTable(data, tbody) {
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No shipments found</td></tr>';
        return;
    }
    
    const statusColors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'in_transit': 'bg-blue-100 text-blue-800',
        'delivered': 'bg-green-100 text-green-800'
    };
    
    tbody.innerHTML = data.map(s => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 font-mono text-sm text-primary">${escapeHtml(s.tracking_number)}</td>
            <td class="px-6 py-4 text-sm">${escapeHtml(s.sender_name || 'N/A')}</td>
            <td class="px-6 py-4 text-sm">${escapeHtml(s.receiver_name || 'N/A')}</td>
            <td class="px-6 py-4">
                <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[s.status] || 'bg-gray-100'}">
                    ${escapeHtml((s.status || 'pending').replace('_', ' ').toUpperCase())}
                </span>
            </td>
            <td class="px-6 py-4 text-sm">
                <button onclick="viewShipment('${escapeHtml(s.tracking_number)}')" class="text-primary hover:text-secondary mr-2">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ==================== SEARCH & FILTER ====================
function searchShipments(searchTerm) {
    let filtered = allShipments;
    
    if (searchTerm) {
        const term = searchTerm.toLowerCase();
        filtered = allShipments.filter(s =>
            s.tracking_number?.toLowerCase().includes(term) ||
            s.sender_name?.toLowerCase().includes(term) ||
            s.receiver_name?.toLowerCase().includes(term)
        );
    }
    
    if (currentShipmentFilter !== 'all') {
        filtered = filtered.filter(s => s.status === currentShipmentFilter);
    }
    
    renderAllShipments(filtered, 
        document.getElementById('allShipmentsTable'),
        document.getElementById('allShipmentsCount')
    );
}

function filterShipmentsByStatus(status) {
    currentShipmentFilter = status;
    
    let filtered = allShipments;
    if (status && status !== 'all') {
        filtered = allShipments.filter(s => s.status === status);
    }
    
    renderAllShipments(filtered,
        document.getElementById('allShipmentsTable'),
        document.getElementById('allShipmentsCount')
    );
}

// ==================== VIEW SHIPMENT ====================
function viewShipment(trackingNumber) {
    const trackingInput = document.getElementById('trackingNumber');
    if (trackingInput) {
        trackingInput.value = trackingNumber;
        // Trigger tracking page navigation
        if (typeof showPage === 'function') {
            document.getElementById('trackingNum').textContent = trackingNumber;
            showPage('tracking');
        }
    }
}

// ==================== UPDATE SHIPMENT STATUS (Admin) ====================
async function updateShipmentStatus(shipmentId, status, notes = '') {
    if (!requireAuth(['admin'])) return;
    
    try {
        const result = await apiRequest('update-shipment-status', 'POST', {
            shipment_id: shipmentId,
            status,
            admin_notes: notes
        });
        
        if (result.success) {
            showNotification(`Shipment ${status} successfully!`, 'success');
            if (typeof loadAllShipments === 'function') loadAllShipments();
            if (typeof loadPending === 'function') loadPending();
        } else {
            showNotification(result.message || 'Update failed', 'error');
        }
        
        return result;
    } catch (error) {
        // Demo fallback
        const shipment = allShipments.find(s => s.id === shipmentId);
        if (shipment) {
            shipment.status = status;
            storageSet('shipments', allShipments);
            showNotification(`Shipment ${status} (Demo)!`, 'success');
            if (typeof loadAllShipments === 'function') loadAllShipments();
        }
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        createShipment, loadShipments, loadAllShipments, 
        searchShipments, filterShipmentsByStatus, viewShipment, updateShipmentStatus 
    };
}