<?php
// Add this at the top of your PHP file after include statements
include "connect.php";
include "function.php";

// Set timezone to match your location (Philippines)
date_default_timezone_set('Asia/Manila');

// Initialize variables to prevent undefined variable errors
$totalAppointments = 0;
$completedAppointments = 0;
$pendingAppointments = 0;
$approvedAppointments = 0;
$registeredUsers = 0;
$todayAppointments = null;
$mayorsAppointments = null;
$recentActivity = null;
$monthlyStats = null;
$weeklyStats = null;

// Chart data arrays
$monthlyChartData = [];
$weeklyChartData = [];
$statusChartData = [];

// Check if database connection exists
if (!isset($con) || !$con) {
    die("Database connection failed. Please check your connection settings.");
}

try {
    // Fetch data for the stats cards with error handling
    $result = $con->query("SELECT COUNT(*) FROM appointments");
    if ($result) {
        $totalAppointments = $result->fetch_row()[0];
    }

    $result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'completed'");
    if ($result) {
        $completedAppointments = $result->fetch_row()[0];
    }

    $result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'pending'");
    if ($result) {
        $pendingAppointments = $result->fetch_row()[0];
    }

    $result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'approved'");
    if ($result) {
        $approvedAppointments = $result->fetch_row()[0];
    }

    $result = $con->query("SELECT COUNT(*) FROM users");
    if ($result) {
        $registeredUsers = $result->fetch_row()[0];
    }

    // Fetch only today's APPROVED appointments
    $today = date('Y-m-d');
    $todayAppointments = $con->query("
        SELECT a.id, u.name AS resident_name, a.purpose, a.time, a.date, a.status_enum as status
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.date = '{$today}' AND a.status_enum = 'approved'
        ORDER BY a.time ASC
    ");

    // Check if the query failed
    if (!$todayAppointments) {
        // Create an empty result object to prevent errors
        $todayAppointments = new stdClass();
        $todayAppointments->num_rows = 0;
    }

    // Fetch upcoming mayor's appointments (check if table exists first)
    $tableCheck = $con->query("SHOW TABLES LIKE 'mayors_appointment'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $mayorsAppointments = $con->query("SELECT * FROM mayors_appointment WHERE date >= CURDATE() ORDER BY date ASC, time ASC LIMIT 5");
    } else {
        // Create empty result if table doesn't exist
        $mayorsAppointments = new stdClass();
        $mayorsAppointments->num_rows = 0;
    }

    // Fetch recent activity with better time handling
    $recentActivity = $con->query("
        SELECT a.purpose, u.name AS resident_name, a.status_enum as status, 
               COALESCE(a.updated_at, a.created_at, NOW()) as last_updated
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        ORDER BY COALESCE(a.updated_at, a.created_at, NOW()) DESC
        LIMIT 5
    ");

    if (!$recentActivity) {
        $recentActivity = new stdClass();
        $recentActivity->num_rows = 0;
    }

    // Fetch monthly appointment statistics for charts
    $monthlyStats = $con->query("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            DATE_FORMAT(date, '%M %Y') as month_name,
            COUNT(*) as total,
            SUM(CASE WHEN status_enum = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status_enum = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status_enum = 'approved' THEN 1 ELSE 0 END) as approved
        FROM appointments 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%M %Y')
        ORDER BY month ASC
    ");

    if ($monthlyStats && $monthlyStats->num_rows > 0) {
        $monthlyLabels = [];
        $monthlyTotal = [];
        $monthlyCompleted = [];
        $monthlyPending = [];
        $monthlyApproved = [];
        
        while ($row = $monthlyStats->fetch_assoc()) {
            $monthlyLabels[] = $row['month_name'];
            $monthlyTotal[] = (int)$row['total'];
            $monthlyCompleted[] = (int)$row['completed'];
            $monthlyPending[] = (int)$row['pending'];
            $monthlyApproved[] = (int)$row['approved'];
        }
        
        $monthlyChartData = [
            'labels' => $monthlyLabels,
            'total' => $monthlyTotal,
            'completed' => $monthlyCompleted,
            'pending' => $monthlyPending,
            'approved' => $monthlyApproved
        ];
    } else {
        $monthlyChartData = [
            'labels' => [],
            'total' => [],
            'completed' => [],
            'pending' => [],
            'approved' => []
        ];
    }

    // Fetch weekly appointment statistics for charts
    $weeklyStats = $con->query("
        SELECT 
            DAYNAME(date) as day_name,
            WEEKDAY(date) as day_order,
            COUNT(*) as count
        FROM appointments 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DAYNAME(date), WEEKDAY(date)
        ORDER BY day_order ASC
    ");

    if ($weeklyStats && $weeklyStats->num_rows > 0) {
        $weeklyLabels = [];
        $weeklyCounts = [];
        
        while ($row = $weeklyStats->fetch_assoc()) {
            $weeklyLabels[] = $row['day_name'];
            $weeklyCounts[] = (int)$row['count'];
        }
        
        $weeklyChartData = [
            'labels' => $weeklyLabels,
            'counts' => $weeklyCounts
        ];
    } else {
        $weeklyChartData = [
            'labels' => [],
            'counts' => []
        ];
    }

    // Prepare status chart data
    $statusChartData = [
        'labels' => ['Completed', 'Approved', 'Pending'],
        'data' => [$completedAppointments, $approvedAppointments, $pendingAppointments]
    ];

} catch (Exception $e) {
    // Log error and set default values
    error_log("Dashboard query error: " . $e->getMessage());
    
    // Initialize with safe defaults
    $totalAppointments = 0;
    $completedAppointments = 0;
    $pendingAppointments = 0;
    $approvedAppointments = 0;
    $registeredUsers = 0;
    
    // Create empty result objects
    $todayAppointments = new stdClass();
    $todayAppointments->num_rows = 0;
    
    $mayorsAppointments = new stdClass();
    $mayorsAppointments->num_rows = 0;
    
    $recentActivity = new stdClass();
    $recentActivity->num_rows = 0;
    
    $monthlyChartData = ['labels' => [], 'total' => [], 'completed' => [], 'pending' => [], 'approved' => []];
    $weeklyChartData = ['labels' => [], 'counts' => []];
    $statusChartData = ['labels' => [], 'data' => []];
}

// Improved time elapsed function with timezone consideration
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        if (empty($datetime)) {
            return 'Unknown time';
        }
        
        try {
            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
            $diff = $now->diff($ago);

            $diff->w = floor($diff->d / 7);
            $diff->d -= $diff->w * 7;

            $string = array(
                'y' => 'year',
                'm' => 'month',
                'w' => 'week',
                'd' => 'day',
                'h' => 'hour',
                'i' => 'minute',
                's' => 'second',
            );
            
            foreach ($string as $k => &$v) {
                if ($diff->$k) {
                    $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
                } else {
                    unset($string[$k]);
                }
            }

            if (!$full) $string = array_slice($string, 0, 1);
            return $string ? implode(', ', $string) . ' ago' : 'just now';
        } catch (Exception $e) {
            return 'Recently';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SOLAR Appointment System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminStyles/adminPanel.css">

</head>
<body>
    <div class="overlay" id="overlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="bi bi-sun"></i>
                    </div>
                    <div>
                        <h5>OASYS Admin</h5>
                        <div class="version">Municipality of Solano</div>
                    </div>
                </div>
            </div>
             <nav class="sidebar-nav">
                <a href="adminPanel.php" class="active">
                    <i class="bi bi-house"></i>
                    Dashboard
                </a>
                <a href="appointment.php">
                    <i class="bi bi-calendar-check"></i>
                    Appointments
                </a>
                <a href="walk_in.php">
                    <i class="bi bi-person-walking"></i>
                    Walk-ins
                </a>
                <a href="queue.php">
                    <i class="bi bi-list-ol"></i>
                    Queue
                </a>
                <a href="schedule.php">
                    <i class="bi bi-calendar"></i>
                    Schedule
                </a>
                <a href="#">
                    <i class="bi bi-graph-up"></i>
                    Reports
                </a>
                <a href="#">
                    <i class="bi bi-gear"></i>
                    Settings
                </a>
                <a href="logout.php" class="text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </nav>
        </div>

        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="mobile-menu-btn d-md-none me-3" id="mobileMenuBtn">
                            <i class="bi bi-list"></i>
                        </button>
                        <div>
                            <h1 class="page-title">Dashboard</h1>
                            <p class="text-muted mb-0 small d-none d-sm-block">Manage and track all appointments</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-2 d-none d-sm-inline">Admin</span>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $totalAppointments ?></div>
                                <div class="stats-label">Total Appointments</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(5, 150, 105, 0.1); color: var(--success);">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $completedAppointments ?></div>
                                <div class="stats-label">Completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--accent);">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $approvedAppointments ?></div>
                                <div class="stats-label">Approved</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(217, 119, 6, 0.1); color: var(--warning);">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $pendingAppointments ?></div>
                                <div class="stats-label">Pending</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(100, 116, 139, 0.1); color: var(--secondary);">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $registeredUsers ?></div>
                                <div class="stats-label">Registered Users</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <h2 class="section-title mb-2 mb-md-0">Appointments Overview</h2>
                                <div class="chart-controls">
                                    <button class="chart-btn active" data-chart="monthly">Monthly</button>
                                    <button class="chart-btn" data-chart="weekly">Weekly</button>
                                    <button class="chart-btn" data-chart="status">By Status</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="appointmentsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2 class="section-title mb-0">Recent Activity</h2>
                                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="activity-list">
    <?php if ($recentActivity && isset($recentActivity->num_rows) && $recentActivity->num_rows > 0): ?>
        <?php while ($row = $recentActivity->fetch_assoc()): ?>
            <?php
                $status = strtolower($row['status']);
                $icon = match($status) {
                    'completed' => 'bi-check-circle',
                    'approved' => 'bi-person-check',
                    'pending' => 'bi-hourglass-split',
                    'cancelled' => 'bi-x-circle',
                    default => 'bi-info-circle',
                };
                $timeAgo = isset($row['last_updated']) ? 
                    time_elapsed_string($row['last_updated']) : 
                    'Recently';
            ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?= ucfirst($status) ?> Appointment</div>
                    <div class="activity-info">
                        <?= htmlspecialchars($row['purpose']) ?> for <?= htmlspecialchars($row['resident_name']) ?>
                    </div>
                    <div class="activity-time"><?= $timeAgo ?></div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="bi bi-clock-history display-6 text-muted"></i>
            <p class="text-muted mt-2 mb-0">No recent activity</p>
        </div>
    <?php endif; ?>
</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <h2 class="section-title mb-2 mb-md-0">Today's Approved Appointments</h2>
                                <div class="text-muted" id="currentDate"></div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Resident</th>
                                                <th class="d-none d-md-table-cell">Purpose</th>
                                                <th>Time</th>
                                                <th class="d-none d-lg-table-cell">Status</th>
                                            </tr>
                                        </thead>
                                       <tbody id="todayAppointments">
                                            <?php if ($todayAppointments && $todayAppointments->num_rows > 0): ?>
                                                <?php while ($row = $todayAppointments->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-secondary">#<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="user-avatar-sm">
                                                                    <?= strtoupper(substr($row['resident_name'], 0, 1)) ?>
                                                                </div>
                                                                <span class="fw-medium"><?= htmlspecialchars($row['resident_name']) ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($row['purpose']) ?>">
                                                                <?= htmlspecialchars($row['purpose']) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium"><?= date('g:i A', strtotime($row['time'])) ?></span>
                                                        </td>
                                                        <td class="d-none d-lg-table-cell">
                                                            <?php
                                                                $status = strtolower($row['status']);
                                                                $badgeClass = match($status) {
                                                                    'pending' => 'status-pending',
                                                                    'approved' => 'status-approved', 
                                                                    'completed' => 'status-completed',
                                                                    'cancelled' => 'status-cancelled',
                                                                    default => 'bg-secondary',
                                                                };
                                                            ?>
                                                            <span class="status-badge <?= $badgeClass ?>">
                                                                <?= ucfirst($status) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5">
                                                        <div class="empty-state">
                                                            <i class="bi bi-calendar-x"></i>
                                                            <h6>No approved appointments for today</h6>
                                                            <p>Approved appointments will appear here.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="section-title mb-0">Upcoming Mayor's Appointments</h2>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="2">
                                                    <div class="empty-state">
                                                        <i class="bi bi-calendar-plus"></i>
                                                        <h6>No upcoming appointments</h6>
                                                        <p>Mayor's appointments will appear here.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            &copy; <span id="currentYear"></span> SOLAR Appointment System - Solano Municipality. All Rights Reserved.
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="me-3">v2.5.1</span>
                            <span>Last updated: <span id="lastUpdated"></span></span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
                monthly: {
                    labels: <?= json_encode($monthlyChartData['labels']) ?>,
                    datasets: [
                        {
                            label: 'Total',
                            data: <?= json_encode($monthlyChartData['total']) ?>,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Completed',
                            data: <?= json_encode($monthlyChartData['completed']) ?>,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Pending',
                            data: <?= json_encode($monthlyChartData['pending']) ?>,
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217, 119, 6, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Approved',
                            data: <?= json_encode($monthlyChartData['approved']) ?>,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.05)',
                            tension: 0.4
                        }
                    ]
                },
                weekly: {
                    labels: <?= json_encode($weeklyChartData['labels']) ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?= json_encode($weeklyChartData['counts']) ?>,
                        backgroundColor: '#2563eb',
                        borderColor: '#1d4ed8',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                status: {
                    labels: <?= json_encode($statusChartData['labels']) ?>,
                    datasets: [{
                        data: <?= json_encode($statusChartData['data']) ?>,
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

            const ctx = document.getElementById('appointmentsChart').getContext('2d');
            let appointmentsChart = null;

            // Chart configurations
            const chartConfigs = {
                monthly: {
                    type: 'line',
                    data: chartData.monthly,
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
            function initChart(type = 'monthly') {
                if (appointmentsChart) {
                    appointmentsChart.destroy();
                }
                
                try {
                    // Check if data exists
                    if (!chartData[type] || 
                        (type === 'monthly' && chartData[type].labels.length === 0) ||
                        (type === 'weekly' && chartData[type].labels.length === 0) ||
                        (type === 'status' && chartData[type].datasets[0].data.every(val => val === 0))) {
                        
                        // Show empty state
                        const chartContainer = document.querySelector('.chart-container');
                        chartContainer.innerHTML = `
                            <div class="chart-loading">
                                <div class="text-center">
                                    <i class="bi bi-bar-chart text-muted mb-2" style="font-size: 2rem;"></i>
                                    <p class="mb-0">No data available for ${type} view</p>
                                    <small class="text-muted">Data will appear here once appointments are created</small>
                                </div>
                            </div>
                        `;
                        return;
                    }
                    
                    // Restore canvas if it was replaced
                    const chartContainer = document.querySelector('.chart-container');
                    if (!chartContainer.querySelector('#appointmentsChart')) {
                        chartContainer.innerHTML = '<canvas id="appointmentsChart"></canvas>';
                    }
                    
                    const newCtx = document.getElementById('appointmentsChart').getContext('2d');
                    appointmentsChart = new Chart(newCtx, chartConfigs[type]);
                    
                } catch (error) {
                    console.error('Error creating chart:', error);
                    // Show error message in chart container
                    const chartContainer = document.querySelector('.chart-container');
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

            // Initialize with monthly chart
            initChart('monthly');

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
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
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
    </script>
</body>
</html>