<?php
session_start();
// Add this at the top of your PHP file after include statements
include "connect.php";
include "function.php";
include "adminPanel_functions.php";

// Initialize admin panel with timezone, admin info, and dashboard stats
$adminData = initializeAdminPanel($con);

// Extract variables from the admin data array
$admin_name = $adminData['admin_name'];
$admin_role = $adminData['admin_role'];
$totalAppointments = $adminData['totalAppointments'];
$completedAppointments = $adminData['completedAppointments'];
$pendingAppointments = $adminData['pendingAppointments'];
$approvedAppointments = $adminData['approvedAppointments'];
$registeredUsers = $adminData['registeredUsers'];
$todayAppointments = $adminData['todayAppointments'];
$mayorsAppointments = $adminData['mayorsAppointments'];
$recentActivity = $adminData['recentActivity'];
$dailyStats = $adminData['dailyStats'];
$weeklyStats = $adminData['weeklyStats'];
$dailyChartData = $adminData['dailyChartData'];
$weeklyChartData = $adminData['weeklyChartData'];
$statusChartData = $adminData['statusChartData'];
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
    <link rel="stylesheet" href="adminPanel.css">
</head>
<body>
    <div class="overlay" id="overlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <img src="../image/logo.png" alt="Logo" style="width: 32px; height: 32px; object-fit: contain;">
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
                <a href="announcement.php">
                    <i class="bi bi-megaphone"></i>
                    Announcement
                </a>
                <a href="history.php">
                    <i class="bi bi-clock-history"></i>
                    History
                </a>
                <a href="adminRegister.php">
                    <i class="bi bi-person-plus"></i>
                    Admin Registration
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
                        <div class="d-none d-sm-inline me-2">
                            <div class="text-muted small"><?= htmlspecialchars($admin_name) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($admin_role) ?></div>
                        </div>
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
                                    <button class="chart-btn active" data-chart="daily">Daily</button>
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
    
    <!-- Pass PHP data to JavaScript -->
    <script>
        window.dailyChartData = <?= json_encode($dailyChartData) ?>;
        window.weeklyChartData = <?= json_encode($weeklyChartData) ?>;
        window.statusChartData = <?= json_encode($statusChartData) ?>;
    </script>
    
    <script src="adminPanel.js"></script>
</body>
</html>