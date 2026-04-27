<?php
// admin/dashboard.php - Admin Dashboard
// This page should be accessed only by admin users
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CargoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link.active { background-color: #2563eb; color: white; }
        .stat-card { cursor: pointer; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .chart-container { position: relative; height: 300px; width: 100%; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white flex-shrink-0">
        <div class="p-6 flex flex-col h-full">
            <div class="flex items-center gap-2 mb-8">
                <i class="fas fa-box-open text-2xl text-primary"></i>
                <span class="font-bold text-xl">CargoTrack Admin</span>
            </div>
            <nav class="space-y-2 flex-1">
                <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                <a href="pending-requests.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-pending_actions"></i>Pending Requests</a>
                <a href="shipments.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-box"></i>Shipments</a>
                <a href="customers.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-users"></i>Customers</a>
                <a href="clearances.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-file-alt"></i>Clearances</a>
                <a href="fleet.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-truck"></i>Fleet</a>
                <a href="reports.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-chart-bar"></i>Reports</a>
                <a href="messages.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-comments"></i>Messages</a>
                <a href="settings.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg"><i class="fas fa-cog"></i>Settings</a>
            </nav>
            <div class="border-t border-gray-800 pt-4">
                <a href="../index.php" onclick="logout()" class="flex items-center gap-3 text-gray-400 hover:text-white transition">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <header class="bg-white shadow-sm">
            <div class="flex justify-between items-center px-8 py-4">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email'] ?? 'admin@cargotrack.co.ke'); ?></div>
                        </div>
                    </div>
                    <a href="../index.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <main class="p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="location.href='shipments.php'">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-600 text-sm">Total Shipments</div>
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-box text-primary"></i></div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="totalShipments">1,284</div>
                    <div class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>12% from last month</div>
                </div>
                <div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="location.href='shipments.php?status=in_transit'">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-600 text-sm">In Transit</div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center"><i class="fas fa-truck text-yellow-600"></i></div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="inTransit">342</div>
                    <div class="text-sm text-gray-500 mt-2">Active deliveries</div>
                </div>
                <div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="location.href='shipments.php?status=delivered'">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-600 text-sm">Delivered</div>
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center"><i class="fas fa-check-circle text-green-600"></i></div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="delivered">892</div>
                    <div class="text-sm text-green-600 mt-2"><i class="fas fa-arrow-up mr-1"></i>8% from last month</div>
                </div>
                <div class="stat-card bg-white rounded-xl shadow-sm p-6" onclick="location.href='pending-requests.php'">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-gray-600 text-sm">Pending Clearances</div>
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center"><i class="fas fa-clock text-red-600"></i></div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="pending">50</div>
                    <div class="text-sm text-red-600 mt-2">Requires attention</div>
                </div>
            </div>

            <!-- Recent Activity & Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Recent Activity</h2>
                    <div class="space-y-4" id="recentActivity">
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
                                <p class="font-medium text-gray-900">Clearance approved</p>
                                <p class="text-sm text-gray-600">CG987654321KE - Staff: John Kamau</p>
                            </div>
                            <span class="text-xs text-gray-500">5 hours ago</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="shipments.php" class="flex flex-col items-center p-6 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                            <i class="fas fa-box text-3xl text-primary mb-3"></i>
                            <span class="font-medium text-gray-900">Manage Shipments</span>
                        </a>
                        <a href="pending-requests.php" class="flex flex-col items-center p-6 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                            <i class="fas fa-clock text-3xl text-yellow-600 mb-3"></i>
                            <span class="font-medium text-gray-900">Review Clearances</span>
                        </a>
                        <a href="reports.php" class="flex flex-col items-center p-6 bg-green-50 rounded-lg hover:bg-green-100 transition">
                            <i class="fas fa-chart-bar text-3xl text-green-600 mb-3"></i>
                            <span class="font-medium text-gray-900">View Reports</span>
                        </a>
                        <a href="messages.php" class="flex flex-col items-center p-6 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                            <i class="fas fa-envelope text-3xl text-purple-600 mb-3"></i>
                            <span class="font-medium text-gray-900">Check Messages</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Charts Preview -->
            <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Analytics Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold mb-4">Shipment Status Distribution</h3>
                        <div class="chart-container">
                            <canvas id="shipmentStatusChart"></canvas>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold mb-4">Monthly Performance</h3>
                        <div class="chart-container">
                            <canvas id="monthlyPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const API_BASE = '../api';
    const API_ENDPOINT = API_BASE + '/cargo.php';
    let authToken = localStorage.getItem('authToken');

    // Load dashboard data
    async function loadDashboardData() {
        try {
            const response = await fetch(`${API_ENDPOINT}?endpoint=get_dashboard_stats`, {
                headers: { 'Authorization': 'Bearer ' + authToken }
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById('totalShipments').textContent = data.stats.total_shipments?.toLocaleString() || '0';
                document.getElementById('inTransit').textContent = data.stats.in_transit?.toLocaleString() || '0';
                document.getElementById('delivered').textContent = data.stats.delivered?.toLocaleString() || '0';
                document.getElementById('pending').textContent = data.stats.pending?.toLocaleString() || '0';
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    // Initialize Charts
    function initCharts() {
        // Shipment Status Chart
        const ctx1 = document.getElementById('shipmentStatusChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Transit', 'Delivered'],
                datasets: [{
                    data: [50, 342, 892],
                    backgroundColor: ['#fbbf24', '#3b82f6', '#10b981']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Monthly Performance Chart
        const ctx2 = document.getElementById('monthlyPerformanceChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Shipments',
                    data: [120, 190, 300, 250, 200, 280],
                    backgroundColor: '#3b82f6'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Load on page ready
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardData();
        initCharts();
    });
</script>

</body>
</html>
