<?php
// index.php - Main frontend file for CargoTrack with Role-Based Auth
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CargoTrack - Track Your Shipment</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>
tailwind.config = {
theme: {
extend: {
fontFamily: { sans: ['Inter', 'sans-serif'] },
colors: { primary: '#2563eb', secondary: '#1e40af' }
}
}
}
</script>
<style>
.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.page-section { display: none !important; }
.page-section.active { display: block !important; }
.loading-spinner {
border: 3px solid #f3f3f3; border-top: 3px solid #2563eb;
border-radius: 50%; width: 20px; height: 20px;
animation: spin 1s linear infinite;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.staff-section { display: block; }
.staff-section.hidden { display: none; }
.role-card { cursor: pointer; transition: all 0.2s; }
.role-card:hover { transform: translateY(-2px); }
.role-card.selected { border-color: #2563eb; background-color: #eff6ff; }
.modal-overlay {
display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 1000;
align-items: center; justify-content: center;
}
.modal-overlay.active { display: flex; }
</style>
</head>
<body class="font-sans antialiased bg-gray-50">

<!-- ==================== ADD SHIPMENT MODAL ==================== -->
<div id="addShipmentModal" class="modal-overlay">
<div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
<div class="gradient-bg px-6 py-4 flex justify-between items-center sticky top-0">
<h3 class="text-xl font-bold text-white">Add New Shipment</h3>
<button onclick="closeAddShipmentModal()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<form id="addShipmentForm" class="space-y-6">
<div class="bg-gray-50 p-4 rounded-lg">
<h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-user text-primary"></i> Sender Information</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">Sender Name *</label><input type="text" name="sender_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Sender Phone *</label><input type="tel" name="sender_phone" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Sender Address *</label><textarea name="sender_address" required rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea></div>
</div>
</div>
<div class="bg-gray-50 p-4 rounded-lg">
<h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-user-check text-green-600"></i> Receiver Information</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">Receiver Name *</label><input type="text" name="receiver_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Receiver Phone *</label><input type="tel" name="receiver_phone" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Receiver Address *</label><textarea name="receiver_address" required rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">City *</label><input type="text" name="receiver_city" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Country *</label><input type="text" name="receiver_country" required value="Kenya" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
</div>
</div>
<div class="bg-gray-50 p-4 rounded-lg">
<h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-box text-purple-600"></i> Shipment Details</h4>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg) *</label><input type="number" name="weight" required step="0.1" min="0.1" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Service Type *</label><select name="service_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"><option value="Standard">Standard</option><option value="Express">Express</option><option value="Same Day">Same Day</option><option value="International">International</option></select></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Price (KES) *</label><input type="number" name="price" required step="0.01" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Estimated Delivery *</label><input type="date" name="estimated_delivery" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label><select name="payment_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><option value="unpaid">Unpaid</option><option value="paid">Paid</option><option value="partial">Partial</option></select></div>
<div class="md:col-span-3"><label class="block text-sm font-medium text-gray-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea></div>
</div>
</div>
<div class="flex gap-4 pt-4">
<button type="submit" class="flex-1 bg-primary hover:bg-secondary text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2"><i class="fas fa-plus"></i> Create Shipment</button>
<button type="button" onclick="closeAddShipmentModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-lg transition">Cancel</button>
</div>
</form>
</div>
</div>
</div>

<!-- HOME PAGE -->
<div id="homePage" class="page-section active">
<nav class="bg-white shadow-md fixed w-full z-50">
<div class="max-w-7xl mx-auto px-4 py-4 flex justify-between">
<div class="flex items-center gap-2">
<i class="fas fa-box-open text-3xl text-primary"></i>
<span class="font-bold text-2xl">CargoTrack</span>
</div>
<div class="flex items-center gap-4">
<button onclick="showPage('adminPage')" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md text-sm transition">Admin Dashboard</button>
</div>
</div>
</nav>
<section class="gradient-bg pt-32 pb-20 px-4">
<div class="max-w-5xl mx-auto text-center">
<h1 class="text-4xl md:text-6xl font-bold text-white mb-6">Track Your Shipment<br/>In Real-Time</h1>
<p class="text-xl text-blue-100 mb-10">Fast, reliable cargo delivery tracking</p>
</div>
</section>
</div>

<!-- ADMIN PAGE -->
<div id="adminPage" class="page-section">
<div class="flex h-screen bg-gray-100">
<aside class="w-64 bg-gray-900 text-white hidden md:block">
<div class="p-6">
<div class="flex items-center gap-2 mb-8">
<i class="fas fa-box-open text-2xl text-primary"></i>
<span class="font-bold text-xl">CargoTrack Admin</span>
</div>
<nav class="space-y-2">
<button onclick="showAdminSection('dashboard')" class="sidebar-link w-full text-left flex items-center gap-3 px-4 py-3 bg-primary rounded-lg transition" data-section="dashboard">
<i class="fas fa-tachometer-alt"></i>Dashboard
</button>
<button onclick="showAdminSection('shipments')" class="sidebar-link w-full text-left flex items-center gap-3 px-4 py-3 hover:bg-gray-800 rounded-lg transition" data-section="shipments">
<i class="fas fa-box"></i>Shipments
</button>
</nav>
</div>
</aside>

<div class="flex-1 overflow-auto">
<header class="bg-white shadow-sm">
<div class="flex justify-between items-center px-8 py-4">
<h1 class="text-2xl font-bold" id="adminPageTitle">Dashboard</h1>
<button onclick="openAddShipmentModal()" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm">
<i class="fas fa-plus mr-2"></i>Add Shipment
</button>
</div>
</header>

<main class="p-8">
<!-- Dashboard Section -->
<div id="adminSection-dashboard" class="admin-section active">
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="text-gray-600 text-sm">Total Shipments</div>
<div class="text-3xl font-bold" id="totalShipments">0</div>
</div>
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="text-gray-600 text-sm">In Transit</div>
<div class="text-3xl font-bold text-blue-600" id="inTransit">0</div>
</div>
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="text-gray-600 text-sm">Delivered</div>
<div class="text-3xl font-bold text-green-600" id="delivered">0</div>
</div>
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="text-gray-600 text-sm">Pending</div>
<div class="text-3xl font-bold text-yellow-600" id="pending">0</div>
</div>
</div>
</div>

<!-- Shipments Section -->
<div id="adminSection-shipments" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-xl font-bold mb-6">All Shipments</h2>
<div class="overflow-x-auto">
<table class="w-full">
<thead class="bg-gray-50">
<tr>
<th class="px-6 py-3 text-left text-sm">Tracking No.</th>
<th class="px-6 py-3 text-left text-sm">Sender</th>
<th class="px-6 py-3 text-left text-sm">Receiver</th>
<th class="px-6 py-3 text-left text-sm">Status</th>
<th class="px-6 py-3 text-left text-sm">Actions</th>
</tr>
</thead>
<tbody id="allShipmentsTable">
<tr><td colspan="5" class="px-6 py-4 text-center">Loading shipments...</td></tr>
</tbody>
</table>
</div>
</div>
</div>
</main>
</div>
</div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// API Configuration
const API_URL = '../api/cargo.php';
let allShipments = [];

// Page Navigation
function showPage(pageName) {
document.querySelectorAll('.page-section').forEach(section => {
section.classList.remove('active');
});
const selectedPage = document.getElementById(pageName);
if (selectedPage) {
selectedPage.classList.add('active');
window.scrollTo(0, 0);
}
}

// Admin Section Navigation
function showAdminSection(sectionName) {
document.querySelectorAll('.admin-section').forEach(section => {
section.classList.remove('active');
});
const targetSection = document.getElementById('adminSection-' + sectionName);
if (targetSection) {
targetSection.classList.add('active');
}
document.querySelectorAll('.sidebar-link').forEach(link => {
link.classList.remove('bg-primary');
if (link.getAttribute('data-section') === sectionName) {
link.classList.add('bg-primary');
}
});
const titles = {
'dashboard': 'Dashboard',
'shipments': 'Shipments Management'
};
document.getElementById('adminPageTitle').textContent = titles[sectionName] || 'Dashboard';
if (sectionName === 'shipments') {
loadAllShipments();
}
}

// Modal Functions
function openAddShipmentModal() {
const modal = document.getElementById('addShipmentModal');
modal.classList.add('active');
modal.style.display = 'flex';
document.body.style.overflow = 'hidden';
const futureDate = new Date();
futureDate.setDate(futureDate.getDate() + 3);
const dateInput = document.querySelector('input[name="estimated_delivery"]');
if (dateInput) dateInput.valueAsDate = futureDate;
}

function closeAddShipmentModal() {
const modal = document.getElementById('addShipmentModal');
modal.classList.remove('active');
setTimeout(() => { modal.style.display = 'none'; }, 200);
document.body.style.overflow = 'auto';
document.getElementById('addShipmentForm').reset();
}

// Form Submission Handler
document.getElementById('addShipmentForm').addEventListener('submit', async function(e) {
e.preventDefault();
const formData = new FormData(this);
const data = Object.fromEntries(formData.entries());

const submitBtn = e.target.querySelector('button[type="submit"]');
const originalText = submitBtn.innerHTML;
submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
submitBtn.disabled = true;

try {
const response = await fetch(API_URL + '?endpoint=create-shipment', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(data)
});
const result = await response.json();

if (result.success) {
showNotification('Shipment created successfully!', 'success');
closeAddShipmentModal();
// Navigate to shipments page and refresh
showPage('adminPage');
showAdminSection('shipments');
loadAllShipments();
} else {
showNotification('Error: ' + result.message, 'error');
}
} catch (error) {
console.error('Error:', error);
// Demo mode fallback
const newShipment = {
id: Date.now(),
tracking_number: 'CG' + Date.now() + 'KE',
...data,
status: 'pending',
created_at: new Date().toISOString()
};
allShipments.push(newShipment);
localStorage.setItem('shipments', JSON.stringify(allShipments));
showNotification('Shipment created successfully! (Demo mode)', 'success');
closeAddShipmentModal();
showPage('adminPage');
showAdminSection('shipments');
loadAllShipments();
} finally {
submitBtn.innerHTML = originalText;
submitBtn.disabled = false;
}
});

// Load All Shipments
function loadAllShipments() {
const tbody = document.getElementById('allShipmentsTable');
if (!tbody) return;

// Try to load from localStorage first
const stored = localStorage.getItem('shipments');
if (stored) {
allShipments = JSON.parse(stored);
}

if (allShipments.length === 0) {
tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No shipments found</td></tr>';
updateStats();
return;
}

const statusColors = {
'pending': 'bg-yellow-100 text-yellow-800',
'in_transit': 'bg-blue-100 text-blue-800',
'delivered': 'bg-green-100 text-green-800'
};

tbody.innerHTML = allShipments.map(s => `
<tr class="hover:bg-gray-50">
<td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">${s.tracking_number}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${s.sender_name || 'N/A'}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${s.receiver_name || 'N/A'}</td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[s.status] || 'bg-gray-100'}">
${(s.status || 'pending').toUpperCase()}
</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
<button onclick="viewShipment('${s.tracking_number}')" class="text-primary hover:text-secondary mr-3" title="View">
<i class="fas fa-eye"></i>
</button>
</td>
</tr>
`).join('');

updateStats();
}

// Update Statistics
function updateStats() {
const total = allShipments.length;
const inTransit = allShipments.filter(s => s.status === 'in_transit').length;
const delivered = allShipments.filter(s => s.status === 'delivered').length;
const pending = allShipments.filter(s => s.status === 'pending').length;

document.getElementById('totalShipments').textContent = total;
document.getElementById('inTransit').textContent = inTransit;
document.getElementById('delivered').textContent = delivered;
document.getElementById('pending').textContent = pending;
}

// View Shipment
function viewShipment(trackingNumber) {
showNotification('Viewing shipment: ' + trackingNumber, 'success');
}

// Notification
function showNotification(message, type = 'success') {
const notification = document.createElement('div');
notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white`;
notification.textContent = message;
document.body.appendChild(notification);
setTimeout(() => { notification.classList.remove('translate-x-full'); }, 100);
setTimeout(() => {
notification.classList.add('translate-x-full');
setTimeout(() => { notification.remove(); }, 300);
}, 3000);
}

// Modal Close on Outside Click
document.getElementById('addShipmentModal').addEventListener('click', function(e) {
if (e.target === this) {
closeAddShipmentModal();
}
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
console.log('✅ CargoTrack Initialized');
loadAllShipments();
});
</script>
</body>
</html>