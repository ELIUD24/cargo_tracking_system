// ===== CHART FUNCTIONS =====
let charts = {};

function initDashboardCharts() {
    // Shipment Status Distribution
    const statusCtx = document.getElementById('shipmentStatusChart');
    if (statusCtx && !charts.status) {
        charts.status = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Transit', 'Delivered', 'Cancelled'],
                datasets: [{
                    data: [12, 45, 38, 5],
                    backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
    
    // Monthly Performance
    const perfCtx = document.getElementById('monthlyPerformanceChart');
    if (perfCtx && !charts.performance) {
        charts.performance = new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Shipments',
                    data: [65, 78, 90, 85, 95, 110],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
}

// Update charts with real data
async function updateDashboardCharts() {
    try {
        const shipments = await API.getShipments();
        const data = shipments.data || [];
        
        // Calculate status counts
        const statusCounts = {
            pending: data.filter(s => s.status === 'pending').length,
            in_transit: data.filter(s => s.status === 'in_transit').length,
            delivered: data.filter(s => s.status === 'delivered').length,
            cancelled: data.filter(s => ['failed', 'cancelled'].includes(s.status)).length
        };
        
        // Update status chart if exists
        if (charts.status) {
            charts.status.data.datasets[0].data = [
                statusCounts.pending,
                statusCounts.in_transit,
                statusCounts.delivered,
                statusCounts.cancelled
            ];
            charts.status.update();
        }
        
    } catch (error) {
        console.error('Failed to update charts:', error);
    }
}