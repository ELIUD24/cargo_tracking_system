<?php
// index.php - Main frontend file for CargoTrack with Role-Based Auth
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CargoTrack - Track Your Shipment | Cargo Delivery Tracking Portal</title>
<meta name="description" content="Track your cargo delivery in real-time with CargoTrack. Fast, reliable shipment tracking service.">
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Leaflet.js CSS (Free Maps) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Leaflet.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Chart.js for Reports -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>
tailwind.config = {
theme: {
extend: {
fontFamily: { sans: ['Inter', 'sans-serif'] },
colors: { primary: '#2563eb', secondary: '#1e40af', accent: '#3b82f6' }
}
}
}
</script>
<style>
/* ===== LAYOUT FIXES (CRITICAL FOR h-screen) ===== */
html, body {
height: 100%;
margin: 0;
padding: 0;
overflow-x: hidden;
}
.page-section {
display: none;
min-height: 100vh;
width: 100%;
}
.page-section.active {
display: block !important;
animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
from { opacity: 0; transform: translateY(10px); }
to { opacity: 1; transform: translateY(0); }
}
.admin-section {
display: none !important;
}
.admin-section.active {
display: block !important;
}
.staff-section {
display: block;
}
.staff-section.hidden {
display: none !important;
}
/* Fix admin/staff page height */
#adminPage .flex.h-screen,
#staffPage .flex.h-screen {
height: 100vh !important;
min-height: 100vh;
}
/* ===== YOUR EXISTING STYLES ===== */
.gradient-bg {
background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%),
url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
background-size: cover;
background-position: center;
background-attachment: fixed;
}
.tracking-input:focus {
box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}
.feature-card:hover {
transform: translateY(-5px);
box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}
.pulse-dot {
animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
0%, 100% { opacity: 1; }
50% { opacity: .7; }
}
.modal-overlay {
display: none;
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background: rgba(0, 0, 0, 0.6);
backdrop-filter: blur(4px);
z-index: 1000;
align-items: center;
justify-content: center;
}
.modal-overlay.active {
display: flex;
}
.role-card {
transition: all 0.3s ease;
cursor: pointer;
border: 3px solid transparent;
}
.role-card:hover, .role-card.selected {
border-color: #2563eb;
transform: translateY(-3px);
box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
}
.role-card.selected {
background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}
.sidebar-link {
transition: all 0.3s ease;
}
.sidebar-link:hover, .sidebar-link.active {
background-color: #2563eb;
}
.loading-spinner {
border: 3px solid #f3f3f3;
border-top: 3px solid #2563eb;
border-radius: 50%;
width: 20px;
height: 20px;
animation: spin 1s linear infinite;
}
@keyframes spin {
0% { transform: rotate(0deg); }
100% { transform: rotate(360deg); }
}
#deliveryMap, #trackingMap, #journeyMap {
min-height: 400px;
background: #f3f4f6;
z-index: 1;
}
@media (max-width: 768px) {
#deliveryMap, #trackingMap, #journeyMap {
min-height: 300px;
}
}
.stat-card {
cursor: pointer;
transition: all 0.3s ease;
}
.stat-card:hover {
transform: translateY(-5px);
box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.stat-card:active {
transform: translateY(-2px);
}
/* ✅ ADDED: Modal Styles */
#createShipmentModal.hidden,
#trackingMapModal.hidden,
#journeyMapModal.hidden {
display: none !important;
}
.chart-container {
position: relative;
height: 300px;
width: 100%;
}
/* Blinking button animation */
@keyframes blink {
0%, 100% { opacity: 1; transform: scale(1); }
50% { opacity: 0.7; transform: scale(0.95); }
}
.blinking-btn {
animation: blink 2s infinite;
}
</style>
</head>
<body class="font-sans antialiased bg-gray-50">

<!-- ==================== LOGIN MODAL ==================== -->
<div id="loginModal" class="modal-overlay">
<div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
<div class="gradient-bg px-6 py-4 flex justify-between items-center">
<h3 class="text-xl font-bold text-white">Sign In to CargoTrack</h3>
<button onclick="closeLoginModal()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<div class="mb-6">
<label class="block text-sm font-medium text-gray-700 mb-3">I am a:</label>
<div class="grid grid-cols-3 gap-3">
<div class="role-card bg-gray-50 rounded-xl p-4 text-center" onclick="selectRole('customer', this)">
<i class="fas fa-user text-2xl text-primary mb-2"></i><div class="font-medium text-gray-900">Customer</div>
</div>
<div class="role-card bg-gray-50 rounded-xl p-4 text-center" onclick="selectRole('staff', this)">
<i class="fas fa-user-tie text-2xl text-purple-600 mb-2"></i><div class="font-medium text-gray-900">Staff</div>
</div>
<div class="role-card bg-gray-50 rounded-xl p-4 text-center" onclick="selectRole('admin', this)">
<i class="fas fa-user-shield text-2xl text-green-600 mb-2"></i><div class="font-medium text-gray-900">Admin</div>
</div>
</div>
<input type="hidden" id="selectedRole" value="">
</div>
<form id="loginForm" class="space-y-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
<input type="email" id="loginEmail" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary tracking-input" placeholder="you@example.com" required>
</div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
<input type="password" id="loginPassword" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary tracking-input" placeholder="••••••••" required>
</div>
<div class="flex items-center justify-between">
<label class="flex items-center"><input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary"><span class="ml-2 text-sm text-gray-600">Remember me</span></label>
<a href="#" class="text-sm text-primary hover:text-secondary">Forgot password?</a>
</div>
<button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2">
<span id="loginBtnText">Sign In</span><div id="loginSpinner" class="loading-spinner hidden"></div>
</button>
</form>
<p class="text-center text-sm text-gray-600 mt-6">Don't have an account? <a href="#" onclick="showRegisterModal()" class="text-primary font-medium hover:text-secondary">Create one</a></p>
<p class="text-center text-xs text-gray-500 mt-4">Demo: admin@cargotrack.co.ke / admin123 | staff@cargotrack.co.ke / staff123</p>
</div>
</div>
</div>

<!-- ==================== REGISTER MODAL ==================== -->
<div id="registerModal" class="modal-overlay">
<div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
<div class="gradient-bg px-6 py-4 flex justify-between items-center">
<h3 class="text-xl font-bold text-white">Create Account</h3>
<button onclick="closeRegisterModal()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<form id="registerForm" class="space-y-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
<input type="text" id="registerName" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="John Doe" required>
</div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
<input type="email" id="registerEmail" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="you@example.com" required>
</div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
<input type="tel" id="registerPhone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="+254 700 123456" required>
</div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
<input type="password" id="registerPassword" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••" required>
</div>
<button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2">
<span>Create Account</span>
</button>
</form>
<p class="text-center text-sm text-gray-600 mt-6">Already have an account? <a href="#" onclick="closeRegisterModal(); openLoginModal();" class="text-primary font-medium hover:text-secondary">Sign In</a></p>
</div>
</div>
</div>

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

<!-- ==================== ADD CUSTOMER MODAL ==================== -->
<div id="addCustomerModal" class="modal-overlay">
<div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
<div class="gradient-bg px-6 py-4 flex justify-between items-center">
<h3 class="text-xl font-bold text-white">Add New Customer</h3>
<button onclick="closeAddCustomerModal()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<form id="addCustomerForm" class="space-y-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label><input type="text" name="name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label><input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label><input type="tel" name="phone" required class="w-full px-4 py-3 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Address</label><textarea name="address" rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-lg"></textarea></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="block text-sm font-medium text-gray-700 mb-1">City</label><input type="text" name="city" class="w-full px-4 py-3 border border-gray-300 rounded-lg"></div>
<div><label class="block text-sm font-medium text-gray-700 mb-1">Country</label><input type="text" name="country" value="Kenya" class="w-full px-4 py-3 border border-gray-300 rounded-lg"></div>
</div>
<div class="flex gap-4 pt-4">
<button type="submit" class="flex-1 bg-primary hover:bg-secondary text-white font-semibold py-3 rounded-lg transition flex items-center justify-center gap-2"><i class="fas fa-user-plus"></i> Add Customer</button>
<button type="button" onclick="closeAddCustomerModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-lg transition">Cancel</button>
</div>
</form>
</div>
</div>
</div>

<!-- ==================== TRACKING MAP MODAL ==================== -->
<div id="trackingMapModal" class="modal-overlay">
<div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-hidden">
<div class="gradient-bg px-6 py-4 flex justify-between items-center">
<h3 class="text-xl font-bold text-white">Track Shipment</h3>
<button onclick="closeTrackingMapModal()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<div id="mapShipmentInfo" class="mb-4 p-4 bg-gray-50 rounded-lg"></div>
<div id="trackingMap" class="w-full h-96 rounded-lg border border-gray-200 mb-6"></div>
<h3 class="text-lg font-semibold text-gray-900 mb-4">Tracking History</h3>
<div id="trackingTimeline" class="space-y-4"></div>
</div>
</div>
</div>

<!-- ==================== JOURNEY MAP MODAL ==================== -->
<div id="journeyMapModal" class="modal-overlay">
<div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-hidden">
<div class="gradient-bg px-6 py-4 flex justify-between items-center">
<h3 class="text-xl font-bold text-white"><i class="fas fa-map-marked-alt mr-2"></i>Live Journey Tracking</h3>
<button onclick="closeJourneyMap()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<div id="mapJourneyInfo" class="mb-4 p-4 bg-blue-50 rounded-lg"></div>
<div id="journeyMap" class="w-full h-96 rounded-lg border border-gray-200 mb-6"></div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<div class="border rounded-lg p-4">
<div class="text-sm text-gray-600">Status</div>
<div class="font-semibold text-blue-600">In Transit</div>
</div>
<div class="border rounded-lg p-4">
<div class="text-sm text-gray-600">Driver</div>
<div class="font-semibold" id="mapDriverName">-</div>
</div>
<div class="border rounded-lg p-4">
<div class="text-sm text-gray-600">Vehicle</div>
<div class="font-semibold" id="mapVehicleReg">-</div>
</div>
</div>
</div>
</div>
</div>

<!-- ==================== HOME PAGE SECTION ==================== -->
<div id="homePage" class="page-section active">
<nav class="bg-white/95 backdrop-blur-sm shadow-md fixed w-full z-50">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between h-16">
<div class="flex items-center">
<div class="flex-shrink-0 flex items-center gap-2"><i class="fas fa-box-open text-3xl text-primary"></i><span class="font-bold text-2xl text-gray-800">CargoTrack</span></div>
<div class="hidden md:ml-10 md:flex md:space-x-8">
<a href="#" onclick="showPage('home')" class="border-primary text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Home</a>
<a href="#track" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Track Shipment</a>
<a href="#services" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Services</a>
<a href="#contact" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Contact</a>
</div>
</div>
<div class="flex items-center gap-4">
<a href="#" class="text-gray-500 hover:text-gray-700 text-sm font-medium hidden sm:flex"><i class="fas fa-phone mr-2"></i>+254 700 123456</a>
<div id="authButtons">
<button onclick="openLoginModal()" class="bg-primary hover:bg-secondary text-white px-5 py-2 rounded-md text-sm font-medium transition duration-150 flex items-center gap-2"><i class="fas fa-user"></i> Sign In</button>
</div>
</div>
</div>
</div>
</nav>

<section class="gradient-bg pt-32 pb-20 px-4">
<div class="max-w-5xl mx-auto text-center">
<h1 class="text-4xl md:text-6xl font-bold text-white mb-6">Track Your Shipment <br/>In Real-Time</h1>
<p class="text-xl text-blue-100 mb-10 max-w-2xl mx-auto">Fast, reliable, and secure cargo delivery tracking. Know where your package is at every step of the journey.</p>
<div class="bg-white rounded-2xl shadow-2xl p-8 max-w-3xl mx-auto">
<form id="trackingForm" class="space-y-4">
<div class="flex flex-col md:flex-row gap-4">
<div class="flex-1 relative">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400 text-xl"></i></div>
<input type="text" id="trackingNumber" class="tracking-input block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-lg text-lg focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition" placeholder="Enter Tracking Number (e.g., CG123456789KE)" required>
</div>
<button type="submit" class="bg-primary hover:bg-secondary text-white font-semibold px-8 py-4 rounded-lg text-lg transition duration-150 flex items-center justify-center gap-2 whitespace-nowrap blinking-btn"><i class="fas fa-search"></i>Track Now</button>
</div>
</form>
<div class="mt-6 pt-6 border-t border-gray-200">
<p class="text-gray-600 text-sm mb-3">Popular Tracking Numbers:</p>
<div class="flex flex-wrap justify-center gap-2">
<button onclick="fillTracking('CG123456789KE')" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition">CG123456789KE</button>
<button onclick="fillTracking('CG987654321KE')" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition">CG987654321KE</button>
<button onclick="fillTracking('CG456789123KE')" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-sm text-gray-700 transition">CG456789123KE</button>
</div>
</div>
</div>
</div>
</section>

<!-- Services, How It Works, Stats, Contact, Footer sections - ALL PRESERVED -->
<section id="services" class="py-20 bg-white">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="text-center mb-16"><h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Why Choose CargoTrack?</h2><p class="text-lg text-gray-600 max-w-2xl mx-auto">We provide comprehensive logistics solutions with cutting-edge tracking technology</p></div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
<div class="feature-card bg-gray-50 rounded-xl p-8 transition duration-300"><div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6"><i class="fas fa-map-marked-alt text-3xl text-primary"></i></div><h3 class="text-xl font-semibold text-gray-900 mb-3">Real-Time Tracking</h3><p class="text-gray-600">Track your shipment location in real-time with GPS integration and live updates.</p></div>
<div class="feature-card bg-gray-50 rounded-xl p-8 transition duration-300"><div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6"><i class="fas fa-shield-alt text-3xl text-green-600"></i></div><h3 class="text-xl font-semibold text-gray-900 mb-3">Secure Delivery</h3><p class="text-gray-600">Your cargo is protected with insurance coverage and secure handling procedures.</p></div>
<div class="feature-card bg-gray-50 rounded-xl p-8 transition duration-300"><div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6"><i class="fas fa-bolt text-3xl text-purple-600"></i></div><h3 class="text-xl font-semibold text-gray-900 mb-3">Fast Delivery</h3><p class="text-gray-600">Express delivery options available with guaranteed on-time delivery.</p></div>
<div class="feature-card bg-gray-50 rounded-xl p-8 transition duration-300"><div class="w-16 h-16 bg-orange-100 rounded-lg flex items-center justify-center mb-6"><i class="fas fa-headset text-3xl text-orange-600"></i></div><h3 class="text-xl font-semibold text-gray-900 mb-3">24/7 Support</h3><p class="text-gray-600">Our customer support team is available round the clock to assist you.</p></div>
</div>
</div>
</section>

<section class="py-20 bg-gray-50">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="text-center mb-16"><h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">How It Works</h2><p class="text-lg text-gray-600">Simple steps to track your shipment</p></div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
<div class="text-center"><div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">1</div><h3 class="text-xl font-semibold text-gray-900 mb-3">Enter Tracking Number</h3><p class="text-gray-600">Input your unique tracking number provided at the time of shipment.</p></div>
<div class="text-center"><div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">2</div><h3 class="text-xl font-semibold text-gray-900 mb-3">Get Instant Results</h3><p class="text-gray-600">View current status, location, and estimated delivery time.</p></div>
<div class="text-center"><div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center mx-auto mb-6 text-white text-3xl font-bold">3</div><h3 class="text-xl font-semibold text-gray-900 mb-3">Receive Updates</h3><p class="text-gray-600">Get SMS and email notifications at every stage of delivery.</p></div>
</div>
</div>
</section>

<section class="py-20 bg-primary">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
<div><div class="text-4xl md:text-5xl font-bold text-white mb-2">50K+</div><div class="text-blue-200">Deliveries Completed</div></div>
<div><div class="text-4xl md:text-5xl font-bold text-white mb-2">99%</div><div class="text-blue-200">On-Time Delivery</div></div>
<div><div class="text-4xl md:text-5xl font-bold text-white mb-2">24/7</div><div class="text-blue-200">Customer Support</div></div>
<div><div class="text-4xl md:text-5xl font-bold text-white mb-2">100+</div><div class="text-blue-200">Countries Served</div></div>
</div>
</div>
</section>

<section id="contact" class="py-20 bg-white">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-1 md:grid-cols-2 gap-12">
<div>
<h2 class="text-3xl font-bold text-gray-900 mb-6">Get In Touch</h2>
<p class="text-gray-600 mb-8">Have questions? We're here to help. Contact us for any inquiries about your shipment.</p>
<div class="space-y-6">
<div class="flex items-start gap-4">
<div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-map-marker-alt text-primary text-xl"></i></div>
<div><h4 class="font-semibold text-gray-900 mb-1">Address</h4><p class="text-gray-600">Nairobi, Kenya<br/>Mombasa Road</p></div>
</div>
<div class="flex items-start gap-4">
<div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-phone text-green-600 text-xl"></i></div>
<div><h4 class="font-semibold text-gray-900 mb-1">Phone</h4><p class="text-gray-600">+254 700 123456<br/>+254 733 789012</p></div>
</div>
<div class="flex items-start gap-4">
<div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-envelope text-purple-600 text-xl"></i></div>
<div><h4 class="font-semibold text-gray-900 mb-1">Email</h4><p class="text-gray-600">info@cargotrack.co.ke<br/>support@cargotrack.co.ke</p></div>
</div>
</div>
</div>
<div class="bg-gray-50 rounded-xl p-8">
<form id="contactForm" class="space-y-6">
<div><label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label><input type="text" id="contactName" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="John Doe" required></div>
<div><label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label><input type="email" id="contactEmail" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="john@example.com" required></div>
<div><label class="block text-sm font-medium text-gray-700 mb-2">Message</label><textarea id="contactMessage" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Your message..." required></textarea></div>
<button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 rounded-lg transition duration-150">Send Message</button>
</form>
</div>
</div>
</div>
</section>

<footer class="bg-gray-900 text-white py-12">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
<div><div class="flex items-center gap-2 mb-4"><i class="fas fa-box-open text-2xl text-primary"></i><span class="font-bold text-xl">CargoTrack</span></div><p class="text-gray-400 text-sm">Reliable cargo delivery and tracking services across Kenya and beyond.</p></div>
<div><h4 class="font-semibold mb-4">Quick Links</h4><ul class="space-y-2 text-sm text-gray-400"><li><a href="#" class="hover:text-white transition">Track Shipment</a></li><li><a href="#" class="hover:text-white transition">Our Services</a></li><li><a href="#" class="hover:text-white transition">About Us</a></li><li><a href="#" class="hover:text-white transition">Contact</a></li></ul></div>
<div><h4 class="font-semibold mb-4">Services</h4><ul class="space-y-2 text-sm text-gray-400"><li><a href="#" class="hover:text-white transition">Local Delivery</a></li><li><a href="#" class="hover:text-white transition">International Shipping</a></li><li><a href="#" class="hover:text-white transition">Warehousing</a></li><li><a href="#" class="hover:text-white transition">Express Delivery</a></li></ul></div>
<div><h4 class="font-semibold mb-4">Follow Us</h4><div class="flex gap-4"><a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition"><i class="fab fa-facebook-f"></i></a><a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition"><i class="fab fa-twitter"></i></a><a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition"><i class="fab fa-instagram"></i></a><a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition"><i class="fab fa-linkedin-in"></i></a></div></div>
</div>
<div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
<p class="text-sm text-gray-400">&copy; 2026 CargoTrack. All rights reserved.</p>
<div class="flex gap-6 text-sm text-gray-400"><a href="#" class="hover:text-white transition">Privacy Policy</a><a href="#" class="hover:text-white transition">Terms of Service</a></div>
</div>
</div>
</footer>
</div>

<!-- ==================== TRACKING PAGE SECTION ==================== -->
<div id="trackingPage" class="page-section">
<nav class="bg-white shadow-md">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between h-16">
<div class="flex items-center gap-2">
<a href="#" onclick="showPage('home')" class="flex items-center gap-2">
<i class="fas fa-box-open text-2xl text-primary"></i>
<span class="font-bold text-xl text-gray-800">CargoTrack</span>
</a>
</div>
<div class="flex items-center gap-4">
<a href="#" onclick="showPage('home')" class="text-gray-600 hover:text-gray-900">
<i class="fas fa-home mr-2"></i>Back to Home
</a>
</div>
</div>
</div>
</nav>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
<div>
<h1 class="text-2xl font-bold text-gray-900 mb-1">Shipment Details</h1>
<p class="text-gray-600">Tracking Number: <span class="font-mono font-semibold text-primary" id="trackingNum">CG123456789KE</span></p>
</div>
<div class="flex gap-2">
<button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2 transition">
<i class="fas fa-print"></i> <span class="hidden sm:inline">Print</span>
</button>
<button onclick="shareTracking()" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
<i class="fas fa-share-alt"></i> <span class="hidden sm:inline">Share</span>
</button>
</div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
<div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
<div class="flex items-center gap-2 mb-2">
<div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></div>
<div class="text-sm text-blue-700 font-medium">Status</div>
</div>
<div class="font-bold text-blue-900" id="trackingStatus">In Transit</div>
</div>
<div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
<div class="text-sm text-green-700 font-medium mb-1">Est. Delivery</div>
<div class="font-bold text-green-900" id="estDelivery">Feb 20, 2026</div>
</div>
<div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
<div class="text-sm text-purple-700 font-medium mb-1">Weight</div>
<div class="font-bold text-purple-900" id="shipmentWeight">15.5 kg</div>
</div>
<div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-4 border border-orange-200">
<div class="text-sm text-orange-700 font-medium mb-1">Service</div>
<div class="font-bold text-orange-900" id="serviceType">Express</div>
</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
<div class="flex items-center gap-2 mb-2">
<i class="fas fa-user text-primary"></i>
<h3 class="font-semibold text-gray-900">Sender</h3>
</div>
<p id="senderName" class="text-gray-900 font-medium">John Kamau</p>
<p id="senderAddress" class="text-sm text-gray-600">Nairobi, Kenya</p>
<p id="senderPhone" class="text-sm text-gray-600">+254 712 345678</p>
</div>
<div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
<div class="flex items-center gap-2 mb-2">
<i class="fas fa-user-check text-green-600"></i>
<h3 class="font-semibold text-gray-900">Receiver</h3>
</div>
<p id="receiverName" class="text-gray-900 font-medium">Sarah Mwangi</p>
<p id="receiverAddress" class="text-sm text-gray-600">Mombasa, Kenya</p>
<p id="receiverPhone" class="text-sm text-gray-600">+254 723 456789</p>
</div>
</div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
<h2 class="text-xl font-bold text-gray-900 mb-6">Tracking History</h2>
<div class="relative">
<div id="trackingHistoryList" class="space-y-6">
<div class="relative flex gap-4">
<div class="flex flex-col items-center">
<div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white z-10 ring-4 ring-blue-100">
<i class="fas fa-truck"></i>
</div>
<div class="w-0.5 h-full bg-blue-600 mt-2"></div>
</div>
<div class="flex-1 pb-6">
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
<div>
<h3 class="font-bold text-gray-900">In Transit</h3>
<p class="text-sm text-gray-600 mt-1">Package is on the way to destination</p>
<p class="text-xs text-gray-500 mt-1"><i class="fas fa-map-marker-alt mr-1"></i>Nakuru Distribution Center, Kenya</p>
</div>
<div class="text-right">
<div class="text-sm font-semibold text-gray-900">Feb 18, 2026</div>
<div class="text-xs text-gray-500">10:30 AM</div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- ✅ LIVE MAP AT THE BOTTOM -->
<div class="bg-white rounded-xl shadow-lg p-6">
<h2 class="text-xl font-bold text-gray-900 mb-4">
<i class="fas fa-map-marked-alt text-primary mr-2"></i>
Live Tracking Map
</h2>
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
<div id="deliveryMap" class="w-full h-96"></div>
<div class="p-4 bg-gray-50 border-t border-gray-200">
<div class="flex flex-wrap gap-4 text-sm">
<div class="flex items-center gap-2">
<div class="w-3 h-3 bg-blue-600 rounded-full"></div>
<span class="text-gray-700">Pickup Location</span>
</div>
<div class="flex items-center gap-2">
<div class="w-3 h-3 bg-green-600 rounded-full"></div>
<span class="text-gray-700">Delivery Location</span>
</div>
<div class="flex items-center gap-2">
<div class="w-3 h-3 bg-purple-600 rounded-full animate-pulse"></div>
<span class="text-gray-700">Current Position</span>
</div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- ==================== ADMIN PAGE SECTION ==================== -->
<div id="adminPage" class="page-section">
<div class="flex h-screen bg-gray-100">
<aside class="w-64 bg-gray-900 text-white hidden md:block flex-shrink-0">
<div class="p-6">
<div class="flex items-center gap-2 mb-8"><i class="fas fa-box-open text-2xl text-primary"></i><span class="font-bold text-xl">CargoTrack Admin</span></div>
<nav class="space-y-2">
<a href="#" onclick="showAdminSection('dashboard')" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg" data-section="dashboard"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
<a href="#" onclick="showAdminSection('shipments')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="shipments"><i class="fas fa-box"></i>Shipments</a>
<a href="#" onclick="showAdminSection('customers')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="customers"><i class="fas fa-users"></i>Customers</a>
<a href="#" onclick="showAdminSection('clearances')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="clearances"><i class="fas fa-file-alt"></i>Clearances</a>
<a href="#" onclick="showAdminSection('fleet')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="fleet"><i class="fas fa-truck"></i>Fleet</a>
<a href="#" onclick="showAdminSection('reports')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="reports"><i class="fas fa-chart-bar"></i>Reports</a>
<a href="#" onclick="showAdminSection('messages')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="messages"><i class="fas fa-comments"></i>Messages</a>
<a href="#" onclick="showAdminSection('settings')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg" data-section="settings"><i class="fas fa-cog"></i>Settings</a>
</nav>
</div>
<div class="absolute bottom-0 w-64 p-6 border-t border-gray-800">
<button onclick="logout()" class="flex items-center gap-3 text-gray-400 hover:text-white transition w-full">
<i class="fas fa-sign-out-alt"></i>Logout
</button>
</div>
</aside>

<div class="flex-1 overflow-auto">
<header class="bg-white shadow-sm">
<div class="flex justify-between items-center px-8 py-4">
<div class="flex items-center gap-4">
<button class="md:hidden text-gray-600"><i class="fas fa-bars text-xl"></i></button>
<h1 class="text-2xl font-bold text-gray-900" id="adminPageTitle">Dashboard</h1>
</div>
<div class="flex items-center gap-6">
<div class="flex items-center gap-3">
<div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white"><i class="fas fa-user"></i></div>
<div>
<div class="font-semibold text-gray-900" id="adminName">Admin User</div>
<div class="text-sm text-gray-500">admin@cargotrack.co.ke</div>
</div>
</div>
<button onclick="logout()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
<i class="fas fa-sign-out-alt"></i> Logout
</button>
</div>
</div>
</header>

<main class="p-8">
<!-- Dashboard Section -->
<div id="adminSection-dashboard" class="admin-section active">
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showAdminSection('shipments', 'all')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">Total Shipments</div>
<div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-box text-primary"></i></div>
</div>
<div class="text-3xl font-bold text-gray-900" id="totalShipments">1,284</div>
<div class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>12% from last month</div>
<div class="text-xs text-gray-500 mt-1">Click to view all</div>
</div>
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showAdminSection('shipments', 'in_transit')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">In Transit</div>
<div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center"><i class="fas fa-truck text-yellow-600"></i></div>
</div>
<div class="text-3xl font-bold text-gray-900" id="inTransit">342</div>
<div class="text-sm text-gray-500 mt-2">Active deliveries</div>
<div class="text-xs text-gray-500 mt-1">Click to view</div>
</div>
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showAdminSection('shipments', 'delivered')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">Delivered</div>
<div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center"><i class="fas fa-check-circle text-green-600"></i></div>
</div>
<div class="text-3xl font-bold text-gray-900" id="delivered">892</div>
<div class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>8% from last month</div>
<div class="text-xs text-gray-500 mt-1">Click to view</div>
</div>
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showAdminSection('shipments', 'pending')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">Pending</div>
<div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center"><i class="fas fa-clock text-red-600"></i></div>
</div>
<div class="text-3xl font-bold text-gray-900" id="pending">50</div>
<div class="text-sm text-red-600 mt-2">Requires attention</div>
<div class="text-xs text-gray-500 mt-1">Click to view</div>
</div>
</div>

<!-- Recent Activity -->
<div class="bg-white rounded-xl shadow-sm">
<div class="p-6 border-b border-gray-200">
<h2 class="text-xl font-bold text-gray-900">Recent Activity</h2>
</div>
<div class="p-6">
<div class="space-y-4">
<div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
<div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center"><i class="fas fa-box text-primary"></i></div>
<div class="flex-1">
<p class="font-medium text-gray-900">New shipment created</p>
<p class="text-sm text-gray-600">CG123456789KE - Nairobi to Mombasa</p>
</div>
<span class="text-xs text-gray-500">2 hours ago</span>
</div>
<div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
<div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"><i class="fas fa-check-circle text-green-600"></i></div>
<div class="flex-1">
<p class="font-medium text-gray-900">Clearance approved by admin</p>
<p class="text-sm text-gray-600">CG987654321KE - Staff: John Kamau</p>
</div>
<span class="text-xs text-gray-500">5 hours ago</span>
</div>
<div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
<div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center"><i class="fas fa-user-plus text-purple-600"></i></div>
<div class="flex-1">
<p class="font-medium text-gray-900">New customer registered</p>
<p class="text-sm text-gray-600">Sarah Mwangi - sarah@example.com</p>
</div>
<span class="text-xs text-gray-500">1 day ago</span>
</div>
</div>
</div>
</div>
</div>

<!-- Fleet Section -->
<div id="adminSection-fleet" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-xl font-bold mb-6">Fleet Management - Cleared Shipments</h2>
<p class="text-gray-600 mb-6">These are shipments that have been cleared by staff and are ready for dispatch</p>
<div id="fleetList" class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="text-center py-12 text-gray-500 col-span-3">
<i class="fas fa-spinner fa-spin text-4xl mb-4"></i>
<p>Loading fleet data...</p>
</div>
</div>
</div>
</div>

<!-- Reports Section with Charts -->
<div id="adminSection-reports" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-xl font-bold mb-6">Reports & Analytics</h2>
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-2 text-gray-600">Monthly Revenue</h3>
<div class="text-3xl font-bold text-primary" id="monthlyRevenue">KES 1,245,000</div>
<p class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>15% from last month</p>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-2 text-gray-600">Delivery Success Rate</h3>
<div class="text-3xl font-bold text-green-600" id="deliverySuccess">98.5%</div>
<p class="text-sm text-gray-600 mt-2">Based on 1,284 deliveries</p>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-2 text-gray-600">Total Clearances</h3>
<div class="text-3xl font-bold text-purple-600" id="totalClearances">24</div>
<p class="text-sm text-gray-600 mt-2">This month</p>
</div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-4">Shipment Status Distribution</h3>
<div class="chart-container">
<canvas id="shipmentStatusChart"></canvas>
</div>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-4">Monthly Performance</h3>
<div class="chart-container">
<canvas id="monthlyPerformanceChart"></canvas>
</div>
</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-4">Clearance Status</h3>
<div class="chart-container">
<canvas id="clearanceStatusChart"></canvas>
</div>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-4">Revenue by Service Type</h3>
<div class="chart-container">
<canvas id="revenueByServiceChart"></canvas>
</div>
</div>
</div>
</div>
</div>

<!-- ✅ Messages Section -->
<div id="adminSection-messages" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="flex justify-between items-center mb-6">
<h2 class="text-xl font-bold">Messages from Contact Form</h2>
<button onclick="loadMessages()" class="text-primary hover:text-secondary">
<i class="fas fa-sync-alt"></i> Refresh
</button>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
<div class="bg-green-50 p-4 rounded-lg">
<div class="text-2xl font-bold text-green-600" id="sentMessages">0</div>
<div class="text-sm text-gray-600">Sent</div>
</div>
<div class="bg-blue-50 p-4 rounded-lg">
<div class="text-2xl font-bold text-blue-600" id="pendingMessages">0</div>
<div class="text-sm text-gray-600">Pending</div>
</div>
<div class="bg-red-50 p-4 rounded-lg">
<div class="text-2xl font-bold text-red-600" id="failedMessages">0</div>
<div class="text-sm text-gray-600">Failed</div>
</div>
</div>
<div id="messagesList" class="space-y-4">
<div class="text-center py-12 text-gray-500">
<i class="fas fa-spinner fa-spin text-4xl mb-4"></i>
<p>Loading messages...</p>
</div>
</div>
</div>
</div>

<!-- ✅ Settings Section -->
<div id="adminSection-settings" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-xl font-bold mb-6">System Settings</h2>
<form id="settingsForm" class="space-y-6">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium mb-2">Company Name</label>
<input type="text" id="settingCompanyName" value="CargoTrack" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium mb-2">Contact Email</label>
<input type="email" id="settingContactEmail" value="info@cargotrack.co.ke" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium mb-2">Phone Number</label>
<input type="tel" id="settingPhone" value="+254 700 123456" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium mb-2">Address</label>
<input type="text" id="settingAddress" value="Nairobi, Kenya" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
</div>
<div class="border-t pt-6">
<h3 class="font-semibold mb-4">Notification Settings</h3>
<div class="space-y-3">
<label class="flex items-center">
<input type="checkbox" id="notifyNewShipment" checked class="rounded text-primary focus:ring-primary">
<span class="ml-2 text-sm">Notify on new shipment creation</span>
</label>
<label class="flex items-center">
<input type="checkbox" id="notifyClearance" checked class="rounded text-primary focus:ring-primary">
<span class="ml-2 text-sm">Notify on clearance approval</span>
</label>
<label class="flex items-center">
<input type="checkbox" id="notifyDelivery" checked class="rounded text-primary focus:ring-primary">
<span class="ml-2 text-sm">Notify on delivery completion</span>
</label>
</div>
</div>
<div class="flex gap-4 pt-4">
<button type="button" onclick="saveSettings()" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-lg transition">Save Changes</button>
<button type="reset" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg transition">Reset</button>
</div>
</form>
</div>
</div>

<!-- Other admin sections preserved -->
<div id="adminSection-clearances" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="flex justify-between items-center mb-6">
<h2 class="text-xl font-bold">Clearance Approvals</h2>
<div class="flex gap-2">
<button onclick="loadAdminClearances('all')" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">All</button>
<button onclick="loadAdminClearances('pending')" class="px-4 py-2 bg-gray-200 rounded-lg text-sm">Pending</button>
<button onclick="loadAdminClearances('approved')" class="px-4 py-2 bg-gray-200 rounded-lg text-sm">Approved</button>
</div>
</div>
<div id="adminClearancesList" class="space-y-4">
<div class="text-center py-12 text-gray-500">
<i class="fas fa-spinner fa-spin text-4xl mb-4"></i>
<p>Loading clearances...</p>
</div>
</div>
</div>
</div>

<div id="adminSection-shipments" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="flex justify-between items-center mb-6">
<h2 class="text-xl font-bold">All Shipments</h2>
<button onclick="openAddShipmentModal()" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm">
<i class="fas fa-plus mr-2"></i>Add Shipment
</button>
</div>
<div class="flex gap-4 mb-6">
<input type="text" id="shipmentSearch" placeholder="Search by tracking number..."
class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
onkeyup="searchAllShipments()">
<select id="shipmentStatusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" onchange="filterAllShipmentsByStatus()">
<option value="">All Status</option>
<option value="pending">Pending</option>
<option value="in_transit">In Transit</option>
<option value="delivered">Delivered</option>
</select>
</div>
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
<div class="mt-4 flex justify-between items-center">
<div class="text-sm text-gray-600">
Showing <span id="allShipmentsCount">0</span> shipments
</div>
</div>
</div>
</div>

<div id="adminSection-customers" class="admin-section">
<div class="bg-white rounded-xl shadow-sm p-6">
<div class="flex justify-between items-center mb-6">
<h2 class="text-xl font-bold">Customer Management</h2>
<button onclick="openAddCustomerModal()" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm">
<i class="fas fa-plus mr-2"></i>Add Customer
</button>
</div>
<table class="w-full">
<thead class="bg-gray-50">
<tr>
<th class="px-6 py-3 text-left text-sm">Name</th>
<th class="px-6 py-3 text-left text-sm">Email</th>
<th class="px-6 py-3 text-left text-sm">Phone</th>
<th class="px-6 py-3 text-left text-sm">City</th>
<th class="px-6 py-3 text-left text-sm">Actions</th>
</tr>
</thead>
<tbody id="customersTableBody">
<tr><td colspan="5" class="px-6 py-4 text-center">Loading customers...</td></tr>
</tbody>
</table>
</div>
</div>
</main>
</div>
</div>
</div>

<!-- ==================== STAFF PAGE SECTION ==================== -->
<div id="staffPage" class="page-section">
<div class="flex h-screen bg-gray-100">
<aside class="w-64 bg-gray-900 text-white hidden md:block flex-shrink-0">
<div class="p-6">
<div class="flex items-center gap-2 mb-8">
<i class="fas fa-box-open text-2xl text-primary"></i>
<span class="font-bold text-xl">CargoTrack Staff</span>
</div>
<nav class="space-y-2">
<a href="#" onclick="showStaffSection('dashboard')" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg bg-primary" data-section="dashboard"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
<a href="#" onclick="showStaffSection('clearance')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-800" data-section="clearance"><i class="fas fa-file-alt"></i>Clearance Forms</a>
<a href="#" onclick="showStaffSection('pending')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-800" data-section="pending"><i class="fas fa-clock"></i>Pending</a>
<a href="#" onclick="showStaffSection('approved')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-800" data-section="approved"><i class="fas fa-check-circle"></i>Approved</a>
<a href="#" onclick="showStaffSection('reports')" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-800" data-section="reports"><i class="fas fa-chart-bar"></i>Reports</a>
</nav>
</div>
<div class="absolute bottom-0 w-64 p-6 border-t border-gray-800">
<button onclick="logout()" class="flex items-center gap-3 text-gray-400 hover:text-white transition w-full">
<i class="fas fa-sign-out-alt"></i>Logout
</button>
</div>
</aside>

<div class="flex-1 overflow-auto">
<header class="bg-white shadow-sm">
<div class="flex justify-between items-center px-8 py-4">
<h1 class="text-2xl font-bold text-gray-900" id="staffPageTitle">Dashboard</h1>
<div class="flex items-center gap-3">
<div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center text-white">
<i class="fas fa-user-tie"></i>
</div>
<div>
<div class="font-semibold text-gray-900" id="staffNameDisplay">Staff Member</div>
<div class="text-sm text-gray-500">staff@cargotrack.co.ke</div>
</div>
<button onclick="logout()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 ml-4">
<i class="fas fa-sign-out-alt"></i> Logout
</button>
</div>
</div>
</header>

<main class="p-8">
<div id="staffSection-dashboard" class="staff-section active">
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showFilteredClearances('all')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">Total Clearances</div>
<div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
<i class="fas fa-file-alt text-primary"></i>
</div>
</div>
<div class="text-3xl font-bold text-gray-900">24</div>
<div class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>12% this month</div>
<div class="text-xs text-gray-500 mt-1">Click to view all</div>
</div>
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showFilteredClearances('pending')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">Pending Approval</div>
<div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
<i class="fas fa-clock text-yellow-600"></i>
</div>
</div>
<div class="text-3xl font-bold text-gray-900">8</div>
<div class="text-sm text-gray-500 mt-2">Awaiting review</div>
<div class="text-xs text-gray-500 mt-1">Click to view</div>
</div>
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showFilteredClearances('today')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">Approved Today</div>
<div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
<i class="fas fa-check-circle text-green-600"></i>
</div>
</div>
<div class="text-3xl font-bold text-gray-900">5</div>
<div class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>2 from yesterday</div>
<div class="text-xs text-gray-500 mt-1">Click to view</div>
</div>
<div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="showFilteredClearances('week')">
<div class="flex items-center justify-between mb-4">
<div class="text-gray-600 text-sm">This Week</div>
<div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
<i class="fas fa-calendar-week text-purple-600"></i>
</div>
</div>
<div class="text-3xl font-bold text-gray-900">18</div>
<div class="text-sm text-gray-500 mt-2">Total processed</div>
<div class="text-xs text-gray-500 mt-1">Click to view</div>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-xl font-bold text-gray-900 mb-6">Recent Clearance Requests</h2>
<div class="overflow-x-auto">
<table class="w-full">
<thead class="bg-gray-50">
<tr>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking No.</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-200">
<tr>
<td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">CG123456789KE</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">John Kamau</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Mombasa</td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Feb 18, 2026</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
<button class="text-primary hover:text-secondary mr-3"><i class="fas fa-eye"></i></button>
<button class="text-green-600 hover:text-green-800 mr-3"><i class="fas fa-check"></i></button>
<button class="text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button>
</td>
</tr>
<tr>
<td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">CG987654321KE</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Sarah Mwangi</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Kisumu</td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Feb 17, 2026</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
<button class="text-primary hover:text-secondary mr-3"><i class="fas fa-eye"></i></button>
<button class="text-gray-400 cursor-not-allowed"><i class="fas fa-check"></i></button>
</td>
</tr>
</tbody>
</table>
</div>
</div>
</div>

<div id="staffSection-clearance" class="staff-section hidden">
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-2xl font-bold text-gray-900 mb-6">New Clearance Request</h2>
<form id="clearanceRequestForm" class="space-y-6">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Customer Full Name *</label>
<input type="text" name="customer_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Customer ID/Passport *</label>
<input type="text" name="customer_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
<input type="tel" name="customer_phone" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
<input type="email" name="customer_email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
</div>
<div class="border-t pt-6">
<h3 class="text-lg font-semibold text-gray-900 mb-4">Shipment Information</h3>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Tracking Number *</label>
<input type="text" name="tracking_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" placeholder="CG123456789KE">
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Departure Date & Time *</label>
<input type="datetime-local" name="departure_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Destination *</label>
<input type="text" name="destination" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
</div>
</div>
<div class="border-t pt-6">
<h3 class="text-lg font-semibold text-gray-900 mb-4">Driver & Vehicle</h3>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Driver Name *</label>
<input type="text" name="driver_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Registration *</label>
<input type="text" name="vehicle_reg" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" placeholder="KCA 123A">
</div>
</div>
</div>
<div class="border-t pt-6">
<h3 class="text-lg font-semibold text-gray-900 mb-4">Goods Details</h3>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
<textarea name="goods_description" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Total Weight (kg) *</label>
<input type="number" name="total_weight" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
</div>
</div>
</div>
<div class="flex gap-4 pt-6">
<button type="submit" class="bg-primary hover:bg-secondary text-white px-6 py-3 rounded-lg transition flex items-center gap-2">
<i class="fas fa-save"></i> Submit Clearance
</button>
<button type="reset" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg transition">
Reset
</button>
</div>
</form>
</div>
</div>

<div id="staffSection-pending" class="staff-section hidden"><div id="pendingClearancesList"></div></div>
<div id="staffSection-approved" class="staff-section hidden"><div id="approvedClearancesList"></div></div>

<!-- ✅ STAFF REPORTS SECTION WITH GRAPHS -->
<div id="staffSection-reports" class="staff-section hidden">
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-2xl font-bold text-gray-900 mb-6">Clearance Reports & Analytics</h2>
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-2 text-gray-600">This Month</h3>
<div class="text-3xl font-bold text-primary">24</div>
<p class="text-sm text-gray-600 mt-1">Total clearances processed</p>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-2 text-gray-600">Approval Rate</h3>
<div class="text-3xl font-bold text-green-600">95%</div>
<p class="text-sm text-gray-600 mt-1">Success rate this month</p>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-2 text-gray-600">Pending Review</h3>
<div class="text-3xl font-bold text-yellow-600">8</div>
<p class="text-sm text-gray-600 mt-1">Awaiting approval</p>
</div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-4">Clearance Status Distribution</h3>
<div class="chart-container">
<canvas id="staffClearanceStatusChart"></canvas>
</div>
</div>
<div class="border rounded-lg p-6">
<h3 class="font-semibold mb-4">Monthly Clearance Activity</h3>
<div class="chart-container">
<canvas id="staffMonthlyActivityChart"></canvas>
</div>
</div>
</div>
</div>
</div>
</main>
</div>
</div>
</div>

<!-- ==================== CUSTOMER PAGE SECTION ==================== -->
<div id="customerPage" class="page-section">
<div class="min-h-screen bg-gray-50">
<nav class="bg-white shadow-md">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between h-16 items-center">
<div class="flex items-center gap-2"><i class="fas fa-box-open text-2xl text-primary"></i><span class="font-bold text-xl text-gray-800">CargoTrack</span></div>
<div class="flex items-center gap-4">
<span class="text-gray-600 hidden sm:inline">Welcome, <span id="customerName" class="font-semibold">Customer</span></span>
<button onclick="logout()" class="text-red-500 hover:text-red-700 font-medium"><i class="fas fa-sign-out-alt mr-2"></i>Logout</button>
</div>
</div>
</div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-12">
<div class="bg-white rounded-xl shadow-lg p-8">
<div class="flex items-center gap-4 mb-6">
<div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center"><i class="fas fa-user text-2xl text-primary"></i></div>
<div>
<h1 class="text-2xl font-bold text-gray-900">My Dashboard</h1>
<p class="text-gray-600">Manage your shipments and track deliveries</p>
</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
<div class="bg-blue-50 rounded-lg p-4"><div class="text-3xl font-bold text-primary" data-stat="active">3</div><div class="text-sm text-gray-600">Active Shipments</div></div>
<div class="bg-green-50 rounded-lg p-4"><div class="text-3xl font-bold text-green-600" data-stat="delivered">12</div><div class="text-sm text-gray-600">Delivered</div></div>
<div class="bg-purple-50 rounded-lg p-4"><div class="text-3xl font-bold text-purple-600" data-stat="pending">1</div><div class="text-sm text-gray-600">Pending Pickup</div></div>
</div>

<h3 class="font-semibold text-gray-900 mb-4">Recent Shipments</h3>
<div id="recentShipmentsList" class="space-y-3">
<div class="border border-gray-200 rounded-lg p-4 text-center text-gray-500">Loading shipments...</div>
</div>
</div>
</div>
</div>
</div>

<!-- Role-based action buttons -->
<div id="customerActions" class="fixed bottom-4 left-4" style="display: none;"></div>
<div id="adminActions" class="fixed bottom-4 left-4" style="display: none;">
<button onclick="showAdminSection('dashboard')" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2">
<i class="fas fa-tachometer-alt"></i> Admin Dashboard
</button>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// ===== API CONFIGURATION =====
const API_URL = 'api/cargo.php';
const AFRICAS_TALKING_USERNAME = 'sandbox';
const AFRICAS_TALKING_API_KEY = 'atsk_ae9e5b40a7388f9ff494af9c36081af784b9240a14c9791aa10e9d67fad24ce3504a196f';

let currentUser = null;
let authToken = null;
let initializationComplete = false;
let deliveryMap = null;
let trackingMap = null;
let journeyMap = null;

// Demo data
let allShipments = [];
let filteredShipments = [];
let currentShipmentFilter = 'all';
let allClearances = [];
let filteredClearances = [];
let currentClearanceFilter = 'all';

// Chart instances
let shipmentStatusChart = null;
let monthlyPerformanceChart = null;
let clearanceStatusChart = null;
let revenueByServiceChart = null;
let staffClearanceStatusChart = null;
let staffMonthlyActivityChart = null;

// ===== PAGE NAVIGATION =====
function showPage(pageName) {
console.log('📄 Navigating to:', pageName);
document.querySelectorAll('.page-section').forEach(section => {
section.classList.remove('active');
});
const selectedPage = document.getElementById(pageName + 'Page');
if (selectedPage) {
selectedPage.classList.add('active');
window.scrollTo(0, 0);
console.log('✅ Page shown:', pageName);
if (pageName === 'tracking') {
setTimeout(initDeliveryMap, 300);
}
}
}

// ===== ADMIN SECTION NAVIGATION =====
function showAdminSection(sectionName, filter = 'all') {
console.log('📂 Switching admin to:', sectionName, 'with filter:', filter);
document.querySelectorAll('.admin-section').forEach(section => {
section.classList.remove('active');
});
const targetSection = document.getElementById('adminSection-' + sectionName);
if (targetSection) {
targetSection.classList.add('active');
}
document.querySelectorAll('.sidebar-link').forEach(link => {
link.classList.remove('active', 'bg-primary');
if (link.getAttribute('data-section') === sectionName) {
link.classList.add('active', 'bg-primary');
}
});
const titles = {
'dashboard': 'Dashboard',
'shipments': 'Shipments Management',
'customers': 'Customer Management',
'clearances': 'Clearance Approvals',
'fleet': 'Fleet Management',
'reports': 'Reports & Analytics',
'messages': 'Messages',
'settings': 'Settings'
};
const titleEl = document.getElementById('adminPageTitle');
if (titleEl) titleEl.textContent = titles[sectionName] || 'Dashboard';

if (sectionName === 'shipments') {
currentShipmentFilter = filter;
loadAllShipments();
const statusFilter = document.getElementById('shipmentStatusFilter');
if (statusFilter) {
statusFilter.value = filter === 'all' ? '' : filter;
}
}
else if (sectionName === 'dashboard') {
loadDashboardData();
loadPendingApprovals();
}
else if (sectionName === 'customers') loadCustomers();
else if (sectionName === 'clearances') loadAdminClearances('pending');
else if (sectionName === 'fleet') loadFleetData();
else if (sectionName === 'reports') loadReports();
else if (sectionName === 'messages') loadMessages();
else if (sectionName === 'settings') loadSettings();

showNotification('Navigated to ' + titles[sectionName], 'success');
}

// ===== STAFF SECTION NAVIGATION =====
function showStaffSection(sectionId) {
console.log('📂 Switching staff to:', sectionId);
document.querySelectorAll('.staff-section').forEach(section => {
section.classList.add('hidden');
section.classList.remove('active');
});
const target = document.getElementById('staffSection-' + sectionId);
if (target) {
target.classList.remove('hidden');
target.classList.add('active');
}
document.querySelectorAll('.sidebar-link').forEach(link => {
link.classList.remove('active', 'bg-primary');
if (link.getAttribute('data-section') === sectionId) {
link.classList.add('active', 'bg-primary');
}
});
const titles = {
'dashboard': 'Dashboard',
'clearance': 'Clearance Forms',
'pending': 'Pending Clearances',
'approved': 'Approved Clearances',
'reports': 'Reports & Analytics',
'filtered': 'Filtered Clearances'
};
const titleEl = document.getElementById('staffPageTitle');
if (titleEl) titleEl.textContent = titles[sectionId] || 'Dashboard';

if (sectionId === 'pending') loadPendingClearances();
if (sectionId === 'approved') loadApprovedClearances();
if (sectionId === 'reports') loadStaffReports();

window.scrollTo(0, 0);
showNotification('Navigated to ' + titles[sectionId], 'success');
}

// ===== MODAL FUNCTIONS =====
function openLoginModal() {
const modal = document.getElementById('loginModal');
modal.classList.add('active');
modal.style.display = 'flex';
document.body.style.overflow = 'hidden';
}

function closeLoginModal() {
const modal = document.getElementById('loginModal');
modal.classList.remove('active');
setTimeout(() => { modal.style.display = 'none'; }, 200);
document.body.style.overflow = 'auto';
document.getElementById('loginForm').reset();
document.querySelectorAll('.role-card').forEach(card => {
card.classList.remove('selected');
card.style.borderColor = 'transparent';
});
document.getElementById('selectedRole').value = '';
}

function showRegisterModal() {
closeLoginModal();
const modal = document.getElementById('registerModal');
modal.classList.add('active');
modal.style.display = 'flex';
document.body.style.overflow = 'hidden';
}

function closeRegisterModal() {
const modal = document.getElementById('registerModal');
modal.classList.remove('active');
setTimeout(() => { modal.style.display = 'none'; }, 200);
document.body.style.overflow = 'auto';
document.getElementById('registerForm').reset();
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
modal.classList.add('active');
modal.style.display = 'flex';
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

function openAddCustomerModal() {
const modal = document.getElementById('addCustomerModal');
modal.classList.add('active');
modal.style.display = 'flex';
}

function closeAddCustomerModal() {
const modal = document.getElementById('addCustomerModal');
modal.classList.remove('active');
setTimeout(() => { modal.style.display = 'none'; }, 200);
document.body.style.overflow = 'auto';
document.getElementById('addCustomerForm').reset();
}

function closeTrackingMapModal() {
const modal = document.getElementById('trackingMapModal');
modal.classList.remove('active');
setTimeout(() => { modal.style.display = 'none'; }, 200);
if (trackingMap) {
trackingMap.remove();
trackingMap = null;
}
}

function closeJourneyMap() {
const modal = document.getElementById('journeyMapModal');
if (modal) {
modal.classList.remove('active');
setTimeout(() => { modal.style.display = 'none'; }, 200);
}
if (journeyMap) {
journeyMap.remove();
journeyMap = null;
}
}

// ===== AUTH FUNCTIONS =====
function updateAuthButtons(isLoggedIn, user) {
const authButtons = document.getElementById('authButtons');
if (!authButtons) return;

if (isLoggedIn && user) {
const name = (user.full_name || user.username || 'User').split(' ')[0];
authButtons.innerHTML = `
<div class="flex items-center gap-3">
<span class="text-sm text-gray-600 hidden sm:inline">Welcome, ${name}</span>
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
updateRoleBasedUI();
}

function updateRoleBasedUI() {
const customerActions = document.getElementById('customerActions');
const adminActions = document.getElementById('adminActions');

if (currentUser) {
if (customerActions) customerActions.style.display = currentUser.role === 'customer' ? 'block' : 'none';
if (adminActions) adminActions.style.display = currentUser.role === 'admin' ? 'block' : 'none';
} else {
if (customerActions) customerActions.style.display = 'none';
if (adminActions) adminActions.style.display = 'none';
}
}

function redirectToDashboard(role, name) {
const userName = name ? name.split(' ')[0] : 'User';
console.log('🎯 Redirecting to:', role, 'dashboard');

switch(role) {
case 'admin':
const adminNameEl = document.getElementById('adminName');
if (adminNameEl) adminNameEl.textContent = userName;
showPage('admin');
loadDashboardData();
loadPendingApprovals();
break;
case 'staff':
const staffNameEl = document.getElementById('staffNameDisplay');
if (staffNameEl) staffNameEl.textContent = userName;
showPage('staff');
break;
case 'customer':
const customerNameEl = document.getElementById('customerName');
if (customerNameEl) customerNameEl.textContent = userName;
showPage('customer');
loadCustomerShipments();
break;
default:
showPage('home');
}
}

// ===== DATA LOADING FUNCTIONS =====
function loadDashboardData() {
document.getElementById('totalShipments').textContent = '1,284';
document.getElementById('inTransit').textContent = '342';
document.getElementById('delivered').textContent = '892';
document.getElementById('pending').textContent = '50';
}

// ===== LOAD ALL SHIPMENTS =====
function loadAllShipments() {
const tbody = document.getElementById('allShipmentsTable');
const countEl = document.getElementById('allShipmentsCount');
if (!tbody) return;

// Get from localStorage or use demo data
let shipments = JSON.parse(localStorage.getItem('shipments') || '[]');
if (shipments.length === 0) {
shipments = [
{ id: 1, tracking_number: 'CG123456789KE', sender_name: 'John Kamau', receiver_name: 'Sarah Mwangi', status: 'in_transit', created_at: '2026-02-18' },
{ id: 2, tracking_number: 'CG987654321KE', sender_name: 'Peter Omondi', receiver_name: 'Mary Njeri', status: 'delivered', created_at: '2026-02-17' },
{ id: 3, tracking_number: 'CG456789123KE', sender_name: 'James Mutua', receiver_name: 'Grace Wanjiku', status: 'pending', created_at: '2026-02-16' }
];
}

allShipments = shipments;

// ✅ NEW: Apply filter based on currentShipmentFilter
let filteredShipments = shipments;
if (currentShipmentFilter && currentShipmentFilter !== 'all') {
filteredShipments = shipments.filter(s => s.status === currentShipmentFilter);
}

renderAllShipments(filteredShipments);
}

function renderAllShipments(data) {
const tbody = document.getElementById('allShipmentsTable');
const countEl = document.getElementById('allShipmentsCount');
if (!tbody) return;

if (!data || data.length === 0) {
tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-inbox text-4xl mb-2"></i><p>No shipments found</p></td></tr>';
if (countEl) countEl.textContent = '0';
return;
}

const statusColors = {
'pending': 'bg-yellow-100 text-yellow-800',
'in_transit': 'bg-blue-100 text-blue-800',
'delivered': 'bg-green-100 text-green-800'
};

tbody.innerHTML = data.map(s => `
<tr class="hover:bg-gray-50">
<td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">${s.tracking_number}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${s.sender_name}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${s.receiver_name}</td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[s.status] || 'bg-gray-100'}">
${s.status.toUpperCase()}
</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
<button onclick="viewShipment('${s.tracking_number}')" class="text-primary hover:text-secondary mr-3" title="View">
<i class="fas fa-eye"></i>
</button>
</td>
</tr>
`).join('');

if (countEl) countEl.textContent = data.length;
}

function searchAllShipments() {
const searchTerm = document.getElementById('shipmentSearch').value.toLowerCase();
let filtered = allShipments;

if (searchTerm) {
filtered = allShipments.filter(s =>
s.tracking_number.toLowerCase().includes(searchTerm) ||
s.sender_name.toLowerCase().includes(searchTerm) ||
s.receiver_name.toLowerCase().includes(searchTerm)
);
}

if (currentShipmentFilter !== 'all') {
filtered = filtered.filter(s => s.status === currentShipmentFilter);
}

renderAllShipments(filtered);
}

function filterAllShipmentsByStatus() {
const status = document.getElementById('shipmentStatusFilter').value;
let filtered = allShipments;

if (status) {
filtered = allShipments.filter(s => s.status === status);
}

renderAllShipments(filtered);
}

function loadCustomers() {
const tbody = document.getElementById('customersTableBody');
if (!tbody) return;

const registeredCustomers = JSON.parse(localStorage.getItem('customers') || '[]');

if (registeredCustomers.length === 0) {
tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No customers found</td></tr>';
return;
}

tbody.innerHTML = registeredCustomers.map(customer => `
<tr class="hover:bg-gray-50">
<td class="px-6 py-4 whitespace-nowrap">
<div class="flex items-center">
<div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
${customer.name.charAt(0).toUpperCase()}
</div>
<div class="ml-4">
<div class="text-sm font-medium text-gray-900">${customer.name}</div>
<div class="text-sm text-gray-500">${customer.email}</div>
</div>
</div>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${customer.phone}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${customer.city || 'N/A'}, ${customer.country || 'Kenya'}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
<button class="text-primary hover:text-secondary mr-3"><i class="fas fa-eye"></i></button>
</td>
</tr>
`).join('');
}

// ✅ FIXED: Removed duplicate loadPendingClearances function
function loadPendingClearances() {
const container = document.getElementById('pendingClearancesList');
if (!container) return;

container.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-primary mb-4"></i><p class="text-gray-600">Loading pending clearances...</p></div>';

setTimeout(() => {
let clearances = JSON.parse(localStorage.getItem('clearances') || '[]').filter(c => c.status === 'pending');

if (clearances.length === 0) {
clearances = [
{ id: 1, tracking_number: 'CG123456789KE', customer_name: 'John Kamau', customer_id: 'ID123456', customer_phone: '+254 712 345678', customer_email: 'john@example.com', destination: 'Mombasa', departure_time: '2026-02-20 08:00', driver_name: 'Peter Omondi', vehicle_reg: 'KCA 123A', goods_description: 'Electronics', total_weight: '150.5', status: 'pending', created_at: '2026-02-18 10:30:00' }
];
}

if (clearances.length === 0) {
container.innerHTML = '<div class="text-center py-12 text-gray-500"><i class="fas fa-check-circle text-4xl mb-4 text-green-500"></i><p>No pending clearances</p></div>';
return;
}

container.innerHTML = clearances.map(c => `
<div class="border rounded-lg p-4 hover:shadow-md transition bg-white">
<div class="flex justify-between items-start mb-3">
<div>
<p class="font-mono text-primary font-semibold text-lg">${c.tracking_number}</p>
<p class="text-sm text-gray-600 mt-1"><i class="fas fa-user mr-2"></i>${c.customer_name}</p>
<p class="text-sm text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>${c.destination}</p>
</div>
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending Review</span>
</div>
<div class="grid grid-cols-2 gap-2 text-sm text-gray-600 mb-3">
<div><i class="fas fa-id-card mr-2"></i>ID: ${c.customer_id || 'N/A'}</div>
<div><i class="fas fa-phone mr-2"></i>${c.customer_phone || 'N/A'}</div>
<div><i class="fas fa-truck mr-2"></i>${c.vehicle_reg || 'N/A'}</div>
<div><i class="fas fa-user mr-2"></i>${c.driver_name || 'N/A'}</div>
</div>
<div class="flex gap-2 mt-4">
<button onclick="viewClearanceDetails(${c.id})" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
<i class="fas fa-eye mr-2"></i>View Details
</button>
<button class="flex-1 bg-gray-300 text-gray-500 px-4 py-2 rounded-lg text-sm font-medium cursor-not-allowed" disabled>
<i class="fas fa-lock mr-2"></i>Awaiting Admin Approval
</button>
</div>
</div>
`).join('');
}, 500);
}

// ===== LOAD APPROVED CLEARANCES (STAFF) =====
function loadApprovedClearances() {
const container = document.getElementById('approvedClearancesList');
if (!container) return;

container.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-primary mb-4"></i><p class="text-gray-600">Loading approved clearances...</p></div>';

setTimeout(() => {
let clearances = JSON.parse(localStorage.getItem('clearances') || '[]').filter(c => c.status === 'approved');

if (clearances.length === 0) {
clearances = [
{
id: 2,
tracking_number: 'CG987654321KE',
customer_name: 'Peter Omondi',
customer_phone: '+254712345678',
destination: 'Nakuru',
driver_name: 'James Mutua',
vehicle_reg: 'KCC 789C',
status: 'approved',
approved_at: '2026-02-17 11:00:00',
approved_by: 'admin',
created_at: '2026-02-16 09:00:00'
}
];
}

if (clearances.length === 0) {
container.innerHTML = '<div class="text-center py-12 text-gray-500"><i class="fas fa-inbox text-4xl mb-4"></i><p>No approved clearances</p></div>';
return;
}

container.innerHTML = clearances.map(c => `
<div class="border rounded-lg p-4 hover:shadow-md transition bg-green-50">
<div class="flex justify-between items-start">
<div>
<p class="font-mono text-primary font-semibold text-lg">${c.tracking_number}</p>
<p class="text-sm text-gray-600 mt-1"><i class="fas fa-user mr-2"></i>${c.customer_name}</p>
<p class="text-sm text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>${c.destination}</p>
${c.driver_name ? `<p class="text-sm text-gray-600"><i class="fas fa-truck mr-2"></i>${c.driver_name} - ${c.vehicle_reg}</p>` : ''}
${c.customer_phone ? `<p class="text-sm text-gray-600"><i class="fas fa-phone mr-2"></i>${c.customer_phone}</p>` : ''}
</div>
<span class="px-3 py-1 text-xs font-semibold rounded-full ${c.status === 'in_transit' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
${c.status === 'in_transit' ? 'In Transit' : 'Approved'}
</span>
</div>
${c.approved_at ? `<p class="text-xs text-gray-500 mt-2"><i class="fas fa-check-circle mr-1"></i>Approved: ${c.approved_at} by ${c.approved_by}</p>` : ''}
${c.status === 'approved' && !c.journey_started ? `
<div class="mt-4 pt-4 border-t border-green-200">
<button onclick="beginJourney(${c.id}, '${c.tracking_number}', '${c.customer_name}', '${c.customer_phone}', '${c.destination}', '${c.driver_name || ''}', '${c.vehicle_reg || ''}')"
class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
<i class="fas fa-play"></i> Begin Journey & Notify Customer
</button>
</div>
` : ''}
${c.journey_started ? `
<div class="mt-4 pt-4 border-t border-blue-200">
<div class="flex items-center gap-2 text-blue-800 text-sm mb-2">
<i class="fas fa-check-circle"></i>
<span>Journey started: ${c.journey_started_at}</span>
</div>
<p class="text-xs text-gray-600 mb-2">Customer notified via SMS to ${c.customer_phone}</p>
<button onclick="viewJourneyMap(${c.id})" class="w-full bg-blue-100 hover:bg-blue-200 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
<i class="fas fa-map-marked-alt"></i> View Live Map
</button>
</div>
` : ''}
</div>
`).join('');
}, 500);
}

function loadPendingApprovals() {
const tbody = document.getElementById('pendingApprovalsTable');
const countEl = document.getElementById('pendingApprovalsCount');
if (!tbody) return;

let pendingApprovals = JSON.parse(localStorage.getItem('clearances') || '[]').filter(c => c.status === 'pending');

if (pendingApprovals.length === 0) {
pendingApprovals = [
{ id: 1, tracking_number: 'CG123456789KE', staff_name: 'Peter Omondi', customer_name: 'John Kamau', destination: 'Mombasa', created_at: '2026-02-18' },
{ id: 4, tracking_number: 'CG789123456KE', staff_name: 'Sarah Mwangi', customer_name: 'Mary Njeri', destination: 'Eldoret', created_at: '2026-02-16' }
];
}

if (pendingApprovals.length === 0) {
tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-check-circle text-4xl mb-2 text-green-500"></i><p>No pending approvals</p></td></tr>';
if (countEl) countEl.textContent = '0 Pending';
return;
}

if (countEl) countEl.textContent = pendingApprovals.length + ' Pending';

tbody.innerHTML = pendingApprovals.map(c => `
<tr class="hover:bg-gray-50">
<td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">${c.tracking_number}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${c.staff_name || 'Staff'}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${c.customer_name}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${c.destination}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${c.created_at}</td>
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

// ✅ REMOVED: Test code (const c = {...} and renderStatus function)

function adminApproveClearance(id) {
if (!confirm('Approve this clearance?')) return;

let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');
const index = clearances.findIndex(c => c.id === id);

if (index !== -1) {
clearances[index].status = 'approved';
clearances[index].approved_at = new Date().toISOString().replace('T', ' ').substr(0, 19);
clearances[index].approved_by = currentUser?.username || 'admin';
localStorage.setItem('clearances', JSON.stringify(clearances));

showNotification('Clearance approved successfully!', 'success');
loadPendingApprovals();
loadAdminClearances('pending');
loadFilteredClearances();
}
}

function adminRejectClearance(id) {
const reason = prompt('Enter rejection reason:');
if (!reason) return;

let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');
const index = clearances.findIndex(c => c.id === id);

if (index !== -1) {
clearances[index].status = 'rejected';
clearances[index].rejected_at = new Date().toISOString().replace('T', ' ').substr(0, 19);
clearances[index].rejected_by = currentUser?.username || 'admin';
clearances[index].rejection_reason = reason;
localStorage.setItem('clearances', JSON.stringify(clearances));

showNotification('Clearance rejected', 'success');
loadPendingApprovals();
loadAdminClearances('pending');
loadFilteredClearances();
}
}

function loadAdminClearances(filter = 'all') {
const container = document.getElementById('adminClearancesList');
if (!container) return;

container.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-primary mb-4"></i><p class="text-gray-600">Loading clearances...</p></div>';

setTimeout(() => {
let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');

if (clearances.length === 0) {
clearances = [
{ id: 1, tracking_number: 'CG123456789KE', customer_name: 'John Kamau', destination: 'Mombasa', status: 'pending', created_at: '2026-02-18', driver_name: 'Peter Omondi', vehicle_reg: 'KCA 123A' },
{ id: 2, tracking_number: 'CG987654321KE', customer_name: 'Sarah Mwangi', destination: 'Kisumu', status: 'approved', created_at: '2026-02-17', driver_name: 'Mary Njeri', vehicle_reg: 'KCB 456B', approved_at: '2026-02-17 15:30:00', approved_by: 'admin' }
];
}

let filteredData = clearances;
if (filter === 'pending') {
filteredData = clearances.filter(c => c.status === 'pending');
} else if (filter === 'approved') {
filteredData = clearances.filter(c => c.status === 'approved');
}

renderAdminClearances(filteredData);
}, 500);
}

function renderAdminClearances(clearances) {
const container = document.getElementById('adminClearancesList');
if (!container) return;

if (!clearances || clearances.length === 0) {
container.innerHTML = '<div class="text-center py-12 text-gray-500"><i class="fas fa-inbox text-4xl mb-4"></i><p>No clearances found</p></div>';
return;
}

const statusColors = {
'pending': 'bg-yellow-100 text-yellow-800',
'approved': 'bg-green-100 text-green-800',
'rejected': 'bg-red-100 text-red-800'
};

container.innerHTML = clearances.map(c => `
<div class="border rounded-lg p-4 hover:shadow-md transition bg-white">
<div class="flex justify-between items-start mb-3">
<div>
<p class="font-mono text-primary font-semibold">${c.tracking_number}</p>
<p class="text-sm text-gray-600 mt-1"><i class="fas fa-user mr-2"></i>${c.customer_name}</p>
<p class="text-sm text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>${c.destination}</p>
${c.driver_name ? `<p class="text-sm text-gray-600"><i class="fas fa-truck mr-2"></i>${c.driver_name} - ${c.vehicle_reg}</p>` : ''}
</div>
<span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[c.status] || 'bg-gray-100'}">
${c.status.toUpperCase()}
</span>
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
${c.approved_at ? `<p class="text-xs text-gray-500 mt-2"><i class="fas fa-check-circle mr-1"></i>Approved: ${c.approved_at} by ${c.approved_by}</p>` : ''}
${c.rejection_reason ? `<p class="text-xs text-red-500 mt-2"><i class="fas fa-times-circle mr-1"></i>Rejected: ${c.rejection_reason}</p>` : ''}
</div>
`).join('');
}

function showFilteredClearances(filterType) {
currentClearanceFilter = filterType;
const titles = {
'all': 'All Clearances',
'pending': 'Pending Approval',
'today': 'Approved Today',
'week': 'This Week'
};
document.getElementById('filteredClearancesTitle').textContent = titles[filterType] || 'Clearances';
loadFilteredClearances(filterType);
showStaffSection('filtered');
}

function loadFilteredClearances(filterType = currentClearanceFilter) {
const tbody = document.getElementById('filteredClearancesTable');
if (!tbody) return;

tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center"><i class="fas fa-spinner fa-spin text-2xl text-primary"></i><p class="mt-2 text-gray-600">Loading clearances...</p></td></tr>';

setTimeout(() => {
let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');

if (clearances.length === 0) {
clearances = [
{ id: 1, tracking_number: 'CG123456789KE', customer_name: 'John Kamau', destination: 'Mombasa', status: 'pending', created_at: '2026-02-18', staff_name: 'Peter Omondi' },
{ id: 2, tracking_number: 'CG987654321KE', customer_name: 'Sarah Mwangi', destination: 'Kisumu', status: 'approved', created_at: '2026-02-17', staff_name: 'Mary Njeri', approved_at: '2026-02-17 15:30:00', approved_by: 'admin' },
{ id: 3, tracking_number: 'CG456789123KE', customer_name: 'Peter Omondi', destination: 'Nakuru', status: 'approved', created_at: '2026-02-18', staff_name: 'John Kamau', approved_at: '2026-02-18 09:15:00', approved_by: 'admin' },
{ id: 4, tracking_number: 'CG789123456KE', customer_name: 'Mary Njeri', destination: 'Eldoret', status: 'pending', created_at: '2026-02-16', staff_name: 'Sarah Mwangi' },
{ id: 5, tracking_number: 'CG321654987KE', customer_name: 'James Mutua', destination: 'Thika', status: 'rejected', created_at: '2026-02-15', staff_name: 'Peter Omondi', rejected_at: '2026-02-15 14:20:00', rejected_by: 'admin', rejection_reason: 'Incomplete documentation' }
];
localStorage.setItem('clearances', JSON.stringify(clearances));
}

allClearances = clearances;
let filtered = allClearances;

if (filterType === 'pending') {
filtered = allClearances.filter(c => c.status === 'pending');
} else if (filterType === 'today') {
const today = new Date().toISOString().split('T')[0];
filtered = allClearances.filter(c => c.status === 'approved' && c.created_at.startsWith(today));
} else if (filterType === 'week') {
const weekAgo = new Date();
weekAgo.setDate(weekAgo.getDate() - 7);
filtered = allClearances.filter(c => new Date(c.created_at) >= weekAgo);
}

filteredClearances = filtered;
renderFilteredClearances(filtered);
}, 500);
}

function renderFilteredClearances(data) {
const tbody = document.getElementById('filteredClearancesTable');
const countEl = document.getElementById('filteredClearancesCount');
if (!tbody) return;

if (!data || data.length === 0) {
tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500"><i class="fas fa-inbox text-4xl mb-2"></i><p>No clearances found</p></td></tr>';
if (countEl) countEl.textContent = '0';
return;
}

const statusColors = {
'pending': 'bg-yellow-100 text-yellow-800',
'approved': 'bg-green-100 text-green-800',
'rejected': 'bg-red-100 text-red-800'
};

tbody.innerHTML = data.map(c => `
<tr class="hover:bg-gray-50">
<td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-primary">${c.tracking_number}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${c.customer_name}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${c.destination}</td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[c.status] || 'bg-gray-100'}">
${c.status.toUpperCase()}
</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${c.created_at}</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
<button onclick="viewClearanceDetails(${c.id})" class="text-primary hover:text-secondary mr-3" title="View">
<i class="fas fa-eye"></i>
</button>
${c.status === 'pending' ? `
<button onclick="approveClearance(${c.id})" class="text-green-600 hover:text-green-800 mr-3" title="Approve">
<i class="fas fa-check"></i>
</button>
<button onclick="rejectClearance(${c.id})" class="text-red-600 hover:text-red-800" title="Reject">
<i class="fas fa-times"></i>
</button>
` : ''}
</td>
</tr>
`).join('');

if (countEl) countEl.textContent = data.length;
}

function searchClearances() {
const searchTerm = document.getElementById('clearanceSearch').value.toLowerCase();
let filtered = allClearances;

if (searchTerm) {
filtered = allClearances.filter(c =>
c.tracking_number.toLowerCase().includes(searchTerm) ||
c.customer_name.toLowerCase().includes(searchTerm) ||
c.destination.toLowerCase().includes(searchTerm)
);
}

if (currentClearanceFilter === 'pending') {
filtered = filtered.filter(c => c.status === 'pending');
} else if (currentClearanceFilter === 'today') {
const today = new Date().toISOString().split('T')[0];
filtered = filtered.filter(c => c.status === 'approved' && c.created_at.startsWith(today));
} else if (currentClearanceFilter === 'week') {
const weekAgo = new Date();
weekAgo.setDate(weekAgo.getDate() - 7);
filtered = filtered.filter(c => new Date(c.created_at) >= weekAgo);
}

filteredClearances = filtered;
renderFilteredClearances(filtered);
}

function filterClearancesByStatus() {
const status = document.getElementById('clearanceStatusFilter').value;
let filtered = allClearances;

if (status) {
filtered = allClearances.filter(c => c.status === status);
}

filteredClearances = filtered;
renderFilteredClearances(filtered);
}

// ===== FORM HANDLERS =====
document.getElementById('clearanceRequestForm')?.addEventListener('submit', function(e) {
e.preventDefault();
const formData = new FormData(this);
const data = Object.fromEntries(formData.entries());

const submitBtn = e.target.querySelector('button[type="submit"]');
const originalText = submitBtn.innerHTML;
submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
submitBtn.disabled = true;

try {
let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');
const newClearance = {
id: Date.now(),
tracking_number: data.tracking_number,
customer_name: data.customer_name,
customer_id: data.customer_id,
customer_phone: data.customer_phone,
customer_email: data.customer_email,
destination: data.destination,
departure_time: data.departure_time,
driver_name: data.driver_name,
vehicle_reg: data.vehicle_reg,
goods_description: data.goods_description,
total_weight: data.total_weight,
status: 'pending',
created_at: new Date().toISOString().replace('T', ' ').substr(0, 19),
staff_name: currentUser?.full_name || 'Staff'
};

clearances.push(newClearance);
localStorage.setItem('clearances', JSON.stringify(clearances));

showNotification('Clearance submitted successfully!', 'success');
this.reset();
showStaffSection('pending');
loadPendingClearances();
} catch (error) {
console.error('Clearance submission error:', error);
showNotification('Connection error. Please try again.', 'error');
} finally {
submitBtn.innerHTML = originalText;
submitBtn.disabled = false;
}
});

document.getElementById('addShipmentForm')?.addEventListener('submit', function(e) {
e.preventDefault();
const formData = new FormData(this);
const data = Object.fromEntries(formData.entries());

// ✅ FIX: Properly handle customer_id to avoid FK constraint errors
const customerId = data.customer_ref_id || data.customer_id;
if (!customerId || customerId === '' || customerId === '0') {
delete data.customer_ref_id;
delete data.customer_id;
data.customer_id = null;
} else {
data.customer_id = parseInt(customerId);
}

const submitBtn = e.target.querySelector('button[type="submit"]');
const originalText = submitBtn.innerHTML;
submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
submitBtn.disabled = true;

try {
let shipments = JSON.parse(localStorage.getItem('shipments') || '[]');
const trackingNumber = 'CG' + Date.now().toString().substr(-9) + 'KE';

const newShipment = {
id: Date.now(),
tracking_number: trackingNumber,
sender_name: data.sender_name,
sender_phone: data.sender_phone,
sender_address: data.sender_address,
receiver_name: data.receiver_name,
receiver_phone: data.receiver_phone,
receiver_address: data.receiver_address,
receiver_city: data.receiver_city,
receiver_country: data.receiver_country,
weight: data.weight,
service_type: data.service_type,
price: data.price,
estimated_delivery: data.estimated_delivery,
payment_status: data.payment_status || 'unpaid',
notes: data.notes || '',
status: 'pending',
created_at: new Date().toISOString().replace('T', ' ').substr(0, 19),
customer_id: data.customer_id || null
};

shipments.push(newShipment);
localStorage.setItem('shipments', JSON.stringify(shipments));

showNotification('Shipment created! Tracking: ' + trackingNumber, 'success');
closeAddShipmentModal();
loadAllShipments();
} catch (error) {
console.error('Shipment creation error:', error);
showNotification('Error creating shipment. Please try again.', 'error');
} finally {
submitBtn.innerHTML = originalText;
submitBtn.disabled = false;
}
});

document.getElementById('addCustomerForm')?.addEventListener('submit', function(e) {
e.preventDefault();
const formData = new FormData(this);
const data = Object.fromEntries(formData.entries());

let registeredCustomers = JSON.parse(localStorage.getItem('customers') || '[]');

if (registeredCustomers.find(c => c.email === data.email)) {
showNotification('Email already registered.', 'error');
return;
}

const tempPassword = 'temp' + Math.random().toString(36).substr(-6);
const newCustomer = {
id: Date.now(),
name: data.name,
email: data.email,
phone: data.phone,
password: tempPassword,
address: data.address || '',
city: data.city || '',
country: data.country || 'Kenya',
created_at: new Date().toISOString()
};

registeredCustomers.push(newCustomer);
localStorage.setItem('customers', JSON.stringify(registeredCustomers));

showNotification('Customer added! Temp password: ' + tempPassword, 'success');
closeAddCustomerModal();
loadCustomers();
});

// ✅ FIXED: Removed extra closing brace
function viewShipment(trackingNumber) {
const input = document.getElementById('trackingNumber');
if (input) input.value = trackingNumber;
showPage('tracking');
fetchTrackingData(trackingNumber);
}

function viewClearanceDetails(id) {
alert('Clearance ID: ' + id + '\nView details functionality to be implemented');
}

function approveClearance(id) {
if (!confirm('Approve this clearance?')) return;

let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');
const index = clearances.findIndex(c => c.id === id);

if (index !== -1) {
clearances[index].status = 'approved';
clearances[index].approved_at = new Date().toISOString().replace('T', ' ').substr(0, 19);
clearances[index].approved_by = currentUser?.username || 'staff';
localStorage.setItem('clearances', JSON.stringify(clearances));

showNotification('Clearance approved successfully!', 'success');
loadPendingClearances();
loadFilteredClearances();
}
}

function rejectClearance(id) {
const reason = prompt('Enter rejection reason:');
if (!reason) return;

let clearances = JSON.parse(localStorage.getItem('clearances') || '[]');
const index = clearances.findIndex(c => c.id === id);

if (index !== -1) {
clearances[index].status = 'rejected';
clearances[index].rejected_at = new Date().toISOString().replace('T', ' ').substr(0, 19);
clearances[index].rejected_by = currentUser?.username || 'staff';
clearances[index].rejection_reason = reason;
localStorage.setItem('clearances', JSON.stringify(clearances));

showNotification('Clearance rejected', 'success');
loadPendingClearances();
loadFilteredClearances();
}
}

function logout() {
currentUser = null;
authToken = null;
localStorage.removeItem('authToken');
localStorage.removeItem('currentUser');
updateAuthButtons(false, null);
showPage('home');
showNotification('You have been logged out', 'success');
}

function fillTracking(number) {
const input = document.getElementById('trackingNumber');
if (input) {
input.value = number;
input.focus();
}
}

function shareTracking() {
const trackingNumEl = document.getElementById('trackingNum');
if (!trackingNumEl) return;
const trackingNum = trackingNumEl.textContent;
if (!trackingNum) return;
const url = window.location.href + '?id=' + encodeURIComponent(trackingNum);
navigator.clipboard.writeText(url);
showNotification('Link copied to clipboard!', 'success');
}

function showNotification(message, type = 'success') {
const notification = document.createElement('div');
notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full ' +
(type === 'success' ? 'bg-green-500' : 'bg-red-500') + ' text-white';
notification.textContent = message;
document.body.appendChild(notification);

setTimeout(() => { notification.classList.remove('translate-x-full'); }, 100);
setTimeout(() => {
notification.classList.add('translate-x-full');
setTimeout(() => { notification.remove(); }, 300);
}, 3000);
}

// ✅ FIXED: Single tracking form handler (removed duplicate)
const trackingForm = document.getElementById('trackingForm');
if (trackingForm) {
trackingForm.addEventListener('submit', async function(e) {
e.preventDefault();
const trackingInput = document.getElementById('trackingNumber');
const trackingNumber = trackingInput ? trackingInput.value.trim() : '';

if (trackingNumber) {
showPage('tracking');
await fetchTrackingData(trackingNumber);
} else {
showNotification('Please enter a tracking number', 'error');
}
});
}

function initDeliveryMap() {
const mapElement = document.getElementById('deliveryMap');
if (!mapElement || typeof L === 'undefined') return;

if (deliveryMap) {
deliveryMap.remove();
}

deliveryMap = L.map('deliveryMap').setView([-1.2921, 36.8219], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
attribution: '© OpenStreetMap contributors'
}).addTo(deliveryMap);

L.marker([-1.2921, 36.8219]).addTo(deliveryMap).bindPopup('Nairobi - Origin');
L.marker([-4.0435, 39.6682]).addTo(deliveryMap).bindPopup('Mombasa - Destination');

const bounds = L.latLngBounds([[-1.2921, 36.8219], [-4.0435, 39.6682]]);
deliveryMap.fitBounds(bounds);
}

function fetchTrackingData(trackingNumber) {
// Demo data - replace with actual API call
const demoShipment = {
tracking_number: trackingNumber,
status: 'in_transit',
estimated_delivery: '2026-02-20',
weight: '15.5',
service_type: 'Express',
sender_name: 'John Kamau',
sender_address: 'Nairobi, Kenya',
sender_phone: '+254 712 345678',
receiver_name: 'Sarah Mwangi',
receiver_address: 'Mombasa, Kenya',
receiver_phone: '+254 723 456789',
created_at: '2026-02-18 10:30:00'
};

document.getElementById('trackingNum').textContent = demoShipment.tracking_number;
document.getElementById('trackingStatus').textContent = demoShipment.status.replace('_', ' ').toUpperCase();
document.getElementById('estDelivery').textContent = demoShipment.estimated_delivery;
document.getElementById('shipmentWeight').textContent = demoShipment.weight + ' kg';
document.getElementById('serviceType').textContent = demoShipment.service_type;
document.getElementById('senderName').textContent = demoShipment.sender_name;
document.getElementById('senderAddress').textContent = demoShipment.sender_address;
document.getElementById('senderPhone').textContent = demoShipment.sender_phone;
document.getElementById('receiverName').textContent = demoShipment.receiver_name;
document.getElementById('receiverAddress').textContent = demoShipment.receiver_address;
document.getElementById('receiverPhone').textContent = demoShipment.receiver_phone;

setTimeout(() => {
initDeliveryMap();
}, 300);
}

function loadCustomerShipments() {
const shipments = JSON.parse(localStorage.getItem('shipments') || '[]');
const currentUserData = JSON.parse(localStorage.getItem('currentUser'));

const userShipments = shipments.filter(s => s.customer_email === currentUserData?.email);

const activeEl = document.querySelector('[data-stat="active"]');
const deliveredEl = document.querySelector('[data-stat="delivered"]');
const pendingEl = document.querySelector('[data-stat="pending"]');

if (activeEl) activeEl.textContent = userShipments.filter(s => s.status === 'in_transit').length;
if (deliveredEl) deliveredEl.textContent = userShipments.filter(s => s.status === 'delivered').length;
if (pendingEl) pendingEl.textContent = userShipments.filter(s => s.status === 'pending').length;

const recentList = document.getElementById('recentShipmentsList');
if (recentList && userShipments.length > 0) {
recentList.innerHTML = userShipments.slice(0, 5).map(shipment => `
<div class="border border-gray-200 rounded-lg p-4 flex justify-between items-center hover:shadow-md transition">
<div>
<p class="font-mono font-medium text-primary">${shipment.tracking_number}</p>
<p class="text-sm text-gray-600">${shipment.sender_address || 'Nairobi'} → ${shipment.receiver_address || 'Mombasa'}</p>
</div>
<span class="px-3 py-1 text-xs font-semibold rounded-full ${shipment.status === 'in_transit' ? 'bg-blue-100 text-blue-800' : shipment.status === 'delivered' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
${shipment.status.toUpperCase()}
</span>
</div>
`).join('');
} else if (recentList) {
recentList.innerHTML = '<div class="border border-gray-200 rounded-lg p-4 text-center text-gray-500">No shipments found</div>';
}
}

function loadReports() {
document.getElementById('monthlyRevenue').textContent = 'KES 1,245,000';
document.getElementById('deliverySuccess').textContent = '98.5%';
document.getElementById('totalClearances').textContent = '24';

setTimeout(() => {
initShipmentStatusChart();
initMonthlyPerformanceChart();
initClearanceStatusChart();
initRevenueByServiceChart();
}, 100);
}

function initShipmentStatusChart() {
const ctx = document.getElementById('shipmentStatusChart');
if (!ctx) return;
if (shipmentStatusChart) shipmentStatusChart.destroy();

shipmentStatusChart = new Chart(ctx, {
type: 'doughnut',
data: {
labels: ['Pending', 'In Transit', 'Delivered', 'Cancelled'],
datasets: [{
data: [50, 342, 892, 0],
backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#ef4444']
}]
},
options: { responsive: true, maintainAspectRatio: false }
});
}

function initMonthlyPerformanceChart() {
const ctx = document.getElementById('monthlyPerformanceChart');
if (!ctx) return;
if (monthlyPerformanceChart) monthlyPerformanceChart.destroy();

monthlyPerformanceChart = new Chart(ctx, {
type: 'line',
data: {
labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
datasets: [{
label: 'Shipments',
data: [65, 78, 90, 85, 95, 110],
borderColor: '#2563eb',
backgroundColor: 'rgba(37, 99, 235, 0.1)',
fill: true
}]
},
options: { responsive: true, maintainAspectRatio: false }
});
}

function initClearanceStatusChart() {
const ctx = document.getElementById('clearanceStatusChart');
if (!ctx) return;
if (clearanceStatusChart) clearanceStatusChart.destroy();

clearanceStatusChart = new Chart(ctx, {
type: 'pie',
data: {
labels: ['Pending', 'Approved', 'Rejected'],
datasets: [{
data: [8, 15, 1],
backgroundColor: ['#fbbf24', '#10b981', '#ef4444']
}]
},
options: { responsive: true, maintainAspectRatio: false }
});
}

function initRevenueByServiceChart() {
const ctx = document.getElementById('revenueByServiceChart');
if (!ctx) return;
if (revenueByServiceChart) revenueByServiceChart.destroy();

revenueByServiceChart = new Chart(ctx, {
type: 'bar',
data: {
labels: ['Standard', 'Express', 'Same Day', 'International'],
datasets: [{
label: 'Revenue (KES)',
data: [450000, 380000, 215000, 200000],
backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6']
}]
},
options: { responsive: true, maintainAspectRatio: false }
});
}

function loadStaffReports() {
setTimeout(() => {
initStaffClearanceStatusChart();
initStaffMonthlyActivityChart();
}, 100);
}

function initStaffClearanceStatusChart() {
const ctx = document.getElementById('staffClearanceStatusChart');
if (!ctx) return;
if (staffClearanceStatusChart) staffClearanceStatusChart.destroy();

staffClearanceStatusChart = new Chart(ctx, {
type: 'pie',
data: {
labels: ['Pending', 'Approved', 'Rejected'],
datasets: [{
data: [8, 15, 1],
backgroundColor: ['#fbbf24', '#10b981', '#ef4444']
}]
},
options: { responsive: true, maintainAspectRatio: false }
});
}

function initStaffMonthlyActivityChart() {
const ctx = document.getElementById('staffMonthlyActivityChart');
if (!ctx) return;
if (staffMonthlyActivityChart) staffMonthlyActivityChart.destroy();

staffMonthlyActivityChart = new Chart(ctx, {
type: 'bar',
data: {
labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
datasets: [{
label: 'Clearances',
data: [5, 8, 6, 9],
backgroundColor: '#2563eb'
}]
},
options: { responsive: true, maintainAspectRatio: false }
});
}

function loadFleetData() {
const container = document.getElementById('fleetList');
if (!container) return;

let clearances = JSON.parse(localStorage.getItem('clearances') || '[]').filter(c => c.status === 'approved');

if (clearances.length === 0) {
clearances = [
{ id: 1, tracking_number: 'CG123456789KE', customer_name: 'John Kamau', destination: 'Mombasa', driver_name: 'Peter Omondi', vehicle_reg: 'KCA 123A', goods_description: 'Electronics', total_weight: '150.5', approved_at: '2026-02-18 10:30:00', status: 'cleared' },
{ id: 2, tracking_number: 'CG987654321KE', customer_name: 'Sarah Mwangi', destination: 'Kisumu', driver_name: 'Mary Njeri', vehicle_reg: 'KCB 456B', goods_description: 'Textiles', total_weight: '200.0', approved_at: '2026-02-17 15:30:00', status: 'cleared' },
{ id: 3, tracking_number: 'CG456789123KE', customer_name: 'Peter Omondi', destination: 'Nakuru', driver_name: 'James Mutua', vehicle_reg: 'KCC 789C', goods_description: 'Machinery', total_weight: '500.0', approved_at: '2026-02-18 09:15:00', status: 'in_transit' }
];
}

if (clearances.length === 0) {
container.innerHTML = '<div class="text-center py-12 text-gray-500 col-span-3"><i class="fas fa-inbox text-4xl mb-4"></i><p>No cleared shipments</p></div>';
return;
}

const statusColors = {
'cleared': 'bg-green-100 text-green-800',
'in_transit': 'bg-blue-100 text-blue-800',
'delivered': 'bg-gray-100 text-gray-800'
};

container.innerHTML = clearances.map(c => `
<div class="border rounded-lg p-4 hover:shadow-md transition bg-white">
<div class="flex justify-between items-start mb-3">
<div>
<p class="font-mono text-primary font-semibold">${c.tracking_number}</p>
<p class="text-sm text-gray-600 mt-1"><i class="fas fa-user mr-2"></i>${c.customer_name}</p>
<p class="text-sm text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>${c.destination}</p>
</div>
<span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[c.status] || 'bg-gray-100'}">
${c.status.toUpperCase().replace('_', ' ')}
</span>
</div>
<div class="border-t pt-3 mt-3">
<p class="text-sm text-gray-600"><i class="fas fa-truck mr-2"></i>${c.vehicle_reg} - ${c.driver_name}</p>
<p class="text-sm text-gray-600 mt-1"><i class="fas fa-box mr-2"></i>${c.goods_description} (${c.total_weight} kg)</p>
<p class="text-xs text-gray-500 mt-2"><i class="fas fa-clock mr-1"></i>Cleared: ${c.approved_at}</p>
</div>
</div>
`).join('');
}

function loadMessages() {
const container = document.getElementById('messagesList');
if (!container) return;

let messages = JSON.parse(localStorage.getItem('contactMessages') || '[]');

if (messages.length === 0) {
messages = [
{ id: 1, name: 'John Doe', email: 'john@example.com', message: 'I need information about international shipping rates', date: '2026-02-18 10:30:00', replied: false },
{ id: 2, name: 'Sarah Mwangi', email: 'sarah@example.com', message: 'When will my shipment CG123456789KE arrive?', date: '2026-02-17 15:20:00', replied: true }
];
}

if (messages.length === 0) {
container.innerHTML = '<div class="text-center py-12 text-gray-500"><i class="fas fa-inbox text-4xl mb-4"></i><p>No messages yet</p></div>';
return;
}

const sentCount = messages.filter(m => m.replied).length;
const pendingCount = messages.filter(m => !m.replied).length;

document.getElementById('sentMessages').textContent = sentCount;
document.getElementById('pendingMessages').textContent = pendingCount;
document.getElementById('failedMessages').textContent = '0';

container.innerHTML = messages.map(msg => `
<div class="border rounded-lg p-4 hover:shadow-md transition bg-white">
<div class="flex justify-between items-start mb-2">
<div>
<p class="font-semibold text-gray-900">${msg.name}</p>
<p class="text-sm text-gray-600">${msg.email}</p>
</div>
<div class="flex items-center gap-2">
<span class="text-xs text-gray-500">${msg.date}</span>
${msg.replied ? '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Replied</span>' : '<span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">New</span>'}
</div>
</div>
<p class="text-gray-700 mb-3">${msg.message}</p>
<div class="flex gap-2">
<button onclick="replyToMessage(${msg.id}, '${msg.email}')" class="text-primary hover:text-secondary text-sm font-medium">
<i class="fas fa-reply mr-1"></i>Reply
</button>
<button onclick="markAsReplied(${msg.id})" class="text-green-600 hover:text-green-800 text-sm font-medium">
<i class="fas fa-check mr-1"></i>Mark as Replied
</button>
</div>
</div>
`).join('');
}

function replyToMessage(id, email) {
const subject = 'Re: Your CargoTrack Inquiry';
const body = 'Dear Customer,\n\nThank you for contacting us. We will get back to you soon.\n\nBest regards,\nCargoTrack Team';
window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

function markAsReplied(id) {
let messages = JSON.parse(localStorage.getItem('contactMessages') || '[]');
const index = messages.findIndex(m => m.id === id);

if (index !== -1) {
messages[index].replied = true;
localStorage.setItem('contactMessages', JSON.stringify(messages));
loadMessages();
showNotification('Message marked as replied', 'success');
}
}

function loadSettings() {
const settings = JSON.parse(localStorage.getItem('systemSettings') || '{}');
document.getElementById('settingCompanyName').value = settings.companyName || 'CargoTrack';
document.getElementById('settingContactEmail').value = settings.contactEmail || 'info@cargotrack.co.ke';
document.getElementById('settingPhone').value = settings.phone || '+254 700 123456';
document.getElementById('settingAddress').value = settings.address || 'Nairobi, Kenya';
document.getElementById('notifyNewShipment').checked = settings.notifyNewShipment !== false;
document.getElementById('notifyClearance').checked = settings.notifyClearance !== false;
document.getElementById('notifyDelivery').checked = settings.notifyDelivery !== false;
}

function saveSettings() {
const settings = {
companyName: document.getElementById('settingCompanyName').value,
contactEmail: document.getElementById('settingContactEmail').value,
phone: document.getElementById('settingPhone').value,
address: document.getElementById('settingAddress').value,
notifyNewShipment: document.getElementById('notifyNewShipment').checked,
notifyClearance: document.getElementById('notifyClearance').checked,
notifyDelivery: document.getElementById('notifyDelivery').checked
};

localStorage.setItem('systemSettings', JSON.stringify(settings));
showNotification('Settings saved successfully!', 'success');
}

// ✅ FIXED: Contact form handler
document.getElementById('contactForm')?.addEventListener('submit', function(e) {
e.preventDefault();
const name = document.getElementById('contactName').value;
const email = document.getElementById('contactEmail').value;
const message = document.getElementById('contactMessage').value;

let messages = JSON.parse(localStorage.getItem('contactMessages') || '[]');
messages.push({
id: Date.now(),
name: name,
email: email,
message: message,
date: new Date().toISOString().replace('T', ' ').substr(0, 19),
replied: false
});
localStorage.setItem('contactMessages', JSON.stringify(messages));

showNotification('Message sent successfully! We will get back to you soon.', 'success');
this.reset();
});

// ===== LOGIN HANDLER =====
document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
e.preventDefault();
const role = document.getElementById('selectedRole').value;
const email = document.getElementById('loginEmail').value;
const password = document.getElementById('loginPassword').value;

if (!role) {
showNotification('Please select your role', 'error');
return;
}

const btnText = document.getElementById('loginBtnText');
const spinner = document.getElementById('loginSpinner');
btnText.textContent = 'Logging in...';
spinner.classList.remove('hidden');
spinner.style.display = 'inline-block';

try {
const response = await fetch(`${API_URL}?endpoint=login`, {
method: 'POST',
headers: {
'Content-Type': 'application/json'
},
body: JSON.stringify({
username: email,
password: password
})
});

const result = await response.json();

if (result.success && result.data) {
authToken = result.data.token;
currentUser = result.data.user;
currentUser.role = role;

localStorage.setItem('authToken', authToken);
localStorage.setItem('currentUser', JSON.stringify(currentUser));

closeLoginModal();
redirectToDashboard(currentUser.role, currentUser.full_name);
updateAuthButtons(true, currentUser);

showNotification('Welcome, ' + currentUser.full_name + '!', 'success');
} else {
showNotification(result.message || 'Invalid credentials', 'error');
}
} catch (error) {
console.error('Login error:', error);
showNotification('Connection error. Please try again.', 'error');
} finally {
btnText.textContent = 'Sign In';
spinner.classList.add('hidden');
}
});

// ===== REGISTER HANDLER =====
document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
e.preventDefault();
const name = document.getElementById('registerName').value;
const email = document.getElementById('registerEmail').value;
const phone = document.getElementById('registerPhone').value;
const password = document.getElementById('registerPassword').value;
const role = document.getElementById('selectedRole').value || 'customer';

if (!name || !email || !password) {
showNotification('Please fill in all required fields', 'error');
return;
}

const submitBtn = this.querySelector('button[type="submit"]');
const originalText = submitBtn.innerHTML;
submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
submitBtn.disabled = true;

try {
const response = await fetch(`${API_URL}?endpoint=register`, {
method: 'POST',
headers: {
'Content-Type': 'application/json'
},
body: JSON.stringify({
name: name,
email: email,
phone: phone,
password: password,
role: role
})
});

const result = await response.json();

if (result.success) {
showNotification(result.message, 'success');
this.reset();

// If customer, auto-login
if (role === 'customer') {
closeRegisterModal();
openLoginModal();
document.getElementById('loginEmail').value = email;
selectRole('customer', document.querySelector('[onclick="selectRole(\'customer\', this)"]'));
} else {
showNotification('Account created! Waiting for admin approval.', 'success');
closeRegisterModal();
}
} else {
showNotification(result.message || 'Registration failed', 'error');
}
} catch (error) {
console.error('Registration error:', error);
showNotification('Connection error. Please try again.', 'error');
} finally {
submitBtn.innerHTML = originalText;
submitBtn.disabled = false;
}
});

// ===== VIEW JOURNEY MAP =====
function viewJourneyMap(clearanceId) {
const clearances = JSON.parse(localStorage.getItem('clearances') || '[]');
const clearance = clearances.find(c => c.id === clearanceId);

if (!clearance) {
showNotification('Clearance not found', 'error');
return;
}

// Create or update map modal
let mapModal = document.getElementById('journeyMapModal');
if (!mapModal) {
mapModal = document.createElement('div');
mapModal.id = 'journeyMapModal';
mapModal.className = 'modal-overlay';
mapModal.innerHTML = `
<div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-hidden">
<div class="gradient-bg px-6 py-4 flex justify-between items-center">
<h3 class="text-xl font-bold text-white"><i class="fas fa-map-marked-alt mr-2"></i>Live Journey Tracking</h3>
<button onclick="closeJourneyMap()" class="text-white hover:text-gray-200"><i class="fas fa-times text-xl"></i></button>
</div>
<div class="p-6">
<div id="mapJourneyInfo" class="mb-4 p-4 bg-blue-50 rounded-lg"></div>
<div id="journeyMap" class="w-full h-96 rounded-lg border border-gray-200 mb-6"></div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<div class="border rounded-lg p-4">
<div class="text-sm text-gray-600">Status</div>
<div class="font-semibold text-blue-600">In Transit</div>
</div>
<div class="border rounded-lg p-4">
<div class="text-sm text-gray-600">Driver</div>
<div class="font-semibold" id="mapDriverName">-</div>
</div>
<div class="border rounded-lg p-4">
<div class="text-sm text-gray-600">Vehicle</div>
<div class="font-semibold" id="mapVehicleReg">-</div>
</div>
</div>
</div>
</div>
`;
document.body.appendChild(mapModal);
}

// Populate info
document.getElementById('mapJourneyInfo').innerHTML = `
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<div class="text-sm text-gray-600">Tracking Number</div>
<div class="font-semibold">${clearance.tracking_number}</div>
</div>
<div>
<div class="text-sm text-gray-600">Destination</div>
<div class="font-semibold">${clearance.destination}</div>
</div>
<div>
<div class="text-sm text-gray-600">Customer</div>
<div class="font-semibold">${clearance.customer_name}</div>
</div>
<div>
<div class="text-sm text-gray-600">Journey Started</div>
<div class="font-semibold">${clearance.journey_started_at || 'N/A'}</div>
</div>
</div>
`;

document.getElementById('mapDriverName').textContent = clearance.driver_name || 'Not assigned';
document.getElementById('mapVehicleReg').textContent = clearance.vehicle_reg || 'Not assigned';

// Show modal
mapModal.classList.add('active');
mapModal.style.display = 'flex';

// Initialize map after modal is shown
setTimeout(() => {
initJourneyMap(clearance);
}, 100);
}

function initJourneyMap(clearance) {
if (typeof L === 'undefined') {
showNotification('Map library not loaded', 'error');
return;
}

// Destroy existing map
if (journeyMap) {
journeyMap.remove();
}

// Create new map
journeyMap = L.map('journeyMap').setView([-1.2921, 36.8219], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
attribution: '© OpenStreetMap contributors'
}).addTo(journeyMap);

// Get coordinates
const originCoords = [-1.2921, 36.8219];
const destCoords = [-4.0435, 39.6682];

// Add markers
L.marker(originCoords).addTo(journeyMap)
.bindPopup('<b>Origin:</b> Nairobi');

L.marker(destCoords).addTo(journeyMap)
.bindPopup(`<b>Destination:</b> ${clearance.destination}`);

// Draw route
const latlngs = [originCoords, destCoords];
L.polyline(latlngs, {color: 'blue', weight: 3, dashArray: '10, 10'}).addTo(journeyMap);

// Fit bounds
journeyMap.fitBounds(latlngs);
}

// ===== MODAL CLOSE ON OUTSIDE CLICK =====
['loginModal', 'registerModal', 'addShipmentModal', 'addCustomerModal', 'createShipmentModal', 'trackingMapModal', 'journeyMapModal'].forEach(id => {
const modal = document.getElementById(id);
if (modal) {
modal.addEventListener('click', function(e) {
if (e.target === this) {
if (id === 'loginModal') closeLoginModal();
else if (id === 'registerModal') closeRegisterModal();
else if (id === 'addShipmentModal') closeAddShipmentModal();
else if (id === 'addCustomerModal') closeAddCustomerModal();
else if (id === 'createShipmentModal') closeCreateShipmentModal();
else if (id === 'trackingMapModal') closeTrackingMapModal();
else if (id === 'journeyMapModal') closeJourneyMap();
}
});
}
});

// ===== ESC KEY TO CLOSE MODALS =====
document.addEventListener('keydown', function(e) {
if (e.key === 'Escape') {
closeLoginModal();
closeRegisterModal();
closeAddShipmentModal();
closeAddCustomerModal();
closeCreateShipmentModal();
closeTrackingMapModal();
closeJourneyMap();
}
});

// ===== DOM READY =====
document.addEventListener('DOMContentLoaded', function() {
if (initializationComplete) return;
initializationComplete = true;

console.log('✅ DOM Loaded - Initializing CargoTrack');

// Check for saved auth
const savedUser = localStorage.getItem('currentUser');
const savedToken = localStorage.getItem('authToken');

if (savedUser && savedToken) {
try {
currentUser = JSON.parse(savedUser);
authToken = savedToken;
updateAuthButtons(true, currentUser);
redirectToDashboard(currentUser.role, currentUser.full_name || currentUser.username);
} catch (e) {
console.error('Auth error:', e);
localStorage.removeItem('currentUser');
localStorage.removeItem('authToken');
}
}

// Check URL params for tracking
const urlParams = new URLSearchParams(window.location.search);
const trackingId = urlParams.get('id');

if (trackingId) {
const trackingInput = document.getElementById('trackingNumber');
if (trackingInput) {
trackingInput.value = trackingId;
setTimeout(() => {
const form = document.getElementById('trackingForm');
if (form) form.dispatchEvent(new Event('submit'));
}, 500);
}
}

// Initialize map if on tracking page
if (typeof initDeliveryMap === 'function' && document.getElementById('deliveryMap')) {
setTimeout(initDeliveryMap, 300);
}
});
</script>
</body>
</html>