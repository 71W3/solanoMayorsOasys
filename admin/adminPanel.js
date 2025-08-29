document.addEventListener('DOMContentLoaded', function() {
    // Set current date and year
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    document.getElementById('currentYear').textContent = now.getFullYear();
    document.getElementById('lastUpdated').textContent = now.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });

    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        });
    }

    // Close sidebar on window resize if mobile
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // Real chart data from PHP
    const chartData = {
        daily: {
            labels: window.dailyChartData ? window.dailyChartData.labels : [],
            datasets: [
                {
                    label: 'Total',
                    data: window.dailyChartData ? window.dailyChartData.total : [],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Completed',
                    data: window.dailyChartData ? window.dailyChartData.completed : [],
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Pending',
                    data: window.dailyChartData ? window.dailyChartData.pending : [],
                    borderColor: '#d97706',
                    backgroundColor: 'rgba(217, 119, 6, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Approved',
                    data: window.dailyChartData ? window.dailyChartData.approved : [],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.05)',
                    tension: 0.4
                }
            ]
        },
        weekly: {
            labels: window.weeklyChartData ? window.weeklyChartData.labels : [],
            datasets: [{
                label: 'Appointments',
                data: window.weeklyChartData ? window.weeklyChartData.counts : [],
                backgroundColor: '#2563eb',
                borderColor: '#1d4ed8',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        status: {
            labels: window.statusChartData ? window.statusChartData.labels : [],
            datasets: [{
                data: window.statusChartData ? window.statusChartData.data : [],
                backgroundColor: [
                    '#059669',
                    '#2563eb',
                    '#d97706'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        }
    };

    const ctx = document.getElementById('appointmentsChart');
    if (!ctx) {
        console.warn('Chart canvas not found');
        return;
    }

    let appointmentsChart = null;

    // Chart configurations
    const chartConfigs = {
        daily: {
            type: 'line',
            data: chartData.daily,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: {
                                size: 12
                            },
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        padding: 10
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: '#64748b',
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        },
        weekly: {
            type: 'bar',
            data: chartData.weekly,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        padding: 10
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: '#64748b',
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        },
        status: {
            type: 'doughnut',
            data: chartData.status,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: {
                                size: 12
                            },
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        }
    };

    // Initialize chart
    function initChart(type = 'daily') {
        if (appointmentsChart) {
            appointmentsChart.destroy();
        }
        
        try {
            // Check if data exists
            if (!chartData[type] || 
                (type === 'daily' && chartData[type].labels.length === 0) ||
                (type === 'weekly' && chartData[type].labels.length === 0) ||
                (type === 'status' && chartData[type].datasets[0].data.every(val => val === 0))) {
                
                // Show empty state
                const chartContainer = document.querySelector('.chart-container');
                if (chartContainer) {
                    chartContainer.innerHTML = `
                        <div class="chart-loading">
                            <div class="text-center">
                                <i class="bi bi-bar-chart text-muted mb-2" style="font-size: 2rem;"></i>
                                <p class="mb-0">No data available for ${type} view</p>
                                <small class="text-muted">Data will appear here once appointments are created</small>
                            </div>
                        </div>
                    `;
                }
                return;
            }
            
            // Restore canvas if it was replaced
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer && !chartContainer.querySelector('#appointmentsChart')) {
                chartContainer.innerHTML = '<canvas id="appointmentsChart"></canvas>';
            }
            
            const newCtx = document.getElementById('appointmentsChart');
            if (newCtx) {
                appointmentsChart = new Chart(newCtx, chartConfigs[type]);
            }
            
        } catch (error) {
            console.error('Error creating chart:', error);
            // Show error message in chart container
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer) {
                chartContainer.innerHTML = `
                    <div class="chart-loading">
                        <div class="text-center">
                            <i class="bi bi-exclamation-triangle text-warning mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">Error loading chart</p>
                            <small class="text-muted">Please try refreshing the page</small>
                        </div>
                    </div>
                `;
            }
        }
    }

    // Chart controls
    document.querySelectorAll('.chart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Update active state
            document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update chart
            const chartType = this.getAttribute('data-chart');
            initChart(chartType);
        });
    });

    // Initialize with daily chart
    initChart('daily');

    // Add click effects to stats cards
    document.querySelectorAll('.stats-card').forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            // Add click effect
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });

    // Smooth scroll for any anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add loading states to buttons
    document.querySelectorAll('button[type="submit"]').forEach(btn => {
        const originalText = btn.innerHTML;
        btn.setAttribute('data-original-text', originalText);
        
        btn.addEventListener('click', function(e) {
            if (this.form && this.form.checkValidity()) {
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading...';
                
                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = originalText;
                }, 3000);
            }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape to close modals/sidebar
        if (e.key === 'Escape') {
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // Handle chart responsiveness
    let resizeTimeout;
    window.addEventListener('resize', function() {
        if (resizeTimeout) {
            clearTimeout(resizeTimeout);
        }
        
        resizeTimeout = setTimeout(function() {
            if (appointmentsChart) {
                appointmentsChart.resize();
            }
        }, 250);
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (appointmentsChart) {
            appointmentsChart.destroy();
        }
    });

    // Initialize tooltips if Bootstrap is loaded
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    console.log('Dashboard initialized successfully with real data');
});
