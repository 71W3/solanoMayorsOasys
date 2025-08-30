<?php
session_start();
include 'connect.php';

// Get admin info from session or database
$admin_name = "Admin";
$admin_role = "Administrator";

// Check for admin_id (from admin login) or user_id (from user login for admin users)
$admin_id = null;
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'mayor'])) {
    $admin_id = $_SESSION['user_id'];
}

if ($admin_id) {
    $stmt = $con->prepare("SELECT name, role FROM users WHERE id = ? AND role IN ('admin', 'mayor')");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($admin = $result->fetch_assoc()) {
        $admin_name = $admin['name'];
        $admin_role = ucfirst($admin['role']);
    }
}

// Initialize variables for filtering
$history_type = isset($_GET['history_type']) ? $_GET['history_type'] : 'all';
$time_range = isset($_GET['time_range']) ? $_GET['time_range'] : 'today';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build WHERE clauses for filtering
$where_online = "WHERE a.status_enum = 'completed'"; // Modified to only show completed appointments
$where_walkin = "WHERE 1=1";

// Apply time range filters
switch ($time_range) {
    case 'today':
        $where_online .= " AND DATE(a.date) = CURDATE()";
        $where_walkin .= " AND DATE(w.created_at) = CURDATE()";
        break;
    case 'week':
        $where_online .= " AND YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1)";
        $where_walkin .= " AND YEARWEEK(w.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $where_online .= " AND YEAR(a.date) = YEAR(CURDATE()) AND MONTH(a.date) = MONTH(CURDATE())";
        $where_walkin .= " AND YEAR(w.created_at) = YEAR(CURDATE()) AND MONTH(w.created_at) = MONTH(CURDATE())";
        break;
    case 'year':
        $where_online .= " AND YEAR(a.date) = YEAR(CURDATE())";
        $where_walkin .= " AND YEAR(w.created_at) = YEAR(CURDATE())";
        break;
    case 'custom':
        if (!empty($start_date)) {
            $where_online .= " AND DATE(a.date) >= '$start_date'";
            $where_walkin .= " AND DATE(w.created_at) >= '$start_date'";
        }
        if (!empty($end_date)) {
            $where_online .= " AND DATE(a.date) <= '$end_date'";
            $where_walkin .= " AND DATE(w.created_at) <= '$end_date'";
        }
        break;
}

// Apply history type filter
if ($history_type == 'online') {
    $where_walkin .= " AND 1=0"; // Exclude walk-ins
} elseif ($history_type == 'walkin') {
    $where_online .= " AND 1=0"; // Exclude online
}

// Fetch online appointment history with simplified query
$online_query = "SELECT u.name, a.date, a.time, 
                        CASE 
                            WHEN a.purpose LIKE '%other%' THEN a.other_details
                            ELSE a.purpose
                        END as display_purpose
                 FROM appointments a
                 JOIN users u ON a.user_id = u.id
                 $where_online
                 ORDER BY a.date DESC, a.time DESC";
$online_result = mysqli_query($con, $online_query);

// Fetch walk-in appointment history with simplified query
// Changed w.created_at to just created_at since we're not joining tables
$walkin_query = "SELECT name, DATE(created_at) as date, TIME(created_at) as time, purpose
                 FROM walk_in w
                 $where_walkin
                 ORDER BY created_at DESC";
$walkin_result = mysqli_query($con, $walkin_query);

// Count total records
$total_online = mysqli_num_rows($online_result);
$total_walkin = mysqli_num_rows($walkin_result);
$total_records = $total_online + $total_walkin;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - SOLAR Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #64748b;
            --accent: #2563eb;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --light: #f8fafc;
            --lighter: #f1f5f9;
            --dark: #0f172a;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--lighter);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--primary);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-header .logo-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .sidebar-header h5 {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }

        .sidebar-header .version {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            background: transparent;
            position: relative;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: var(--lighter);
            color: var(--accent);
        }

        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--accent);
        }

        .sidebar-nav a i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            min-height: 100vh;
            width: calc(100% - 260px);
        }

        .topbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 500;
        }

        .content {
            padding: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: white;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 1.5rem 0;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--accent);
            border-radius: 3px;
        }

        .filter-section {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }

        .nav-pills .nav-link.active {
            background-color: var(--accent);
        }

        .nav-pills .nav-link {
            color: var(--text-secondary);
            border-radius: var(--radius);
        }

        .badge-online {
            background-color: var(--success);
        }

        .badge-walkin {
            background-color: var(--warning);
        }

        .history-table th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: var(--light);
            border-bottom: 2px solid var(--border);
        }

        .history-table td {
            border-bottom: 1px solid var(--border);
            padding: 15px;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(37, 99, 235, 0.05);
        }

        .dataTables_filter input {
            border-radius: var(--radius);
            padding: 8px 15px;
            border: 1px solid var(--border);
        }

        .dataTables_length select {
            border-radius: var(--radius);
            padding: 5px 10px;
            border: 1px solid var(--border);
        }

        .btn-filter {
            background-color: var(--accent);
            color: white;
            border-radius: var(--radius);
            padding: 10px 20px;
            font-weight: 500;
            border: none;
        }

        .btn-filter:hover {
            background-color: #1d4ed8;
            color: white;
        }

        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
        }

        .form-control,
        .form-select {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .table {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin: 0;
        }

        .table thead th {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
            padding: 1rem 0.75rem;
            border-top: none;
        }

        .table tbody td {
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--lighter);
        }

        .table-responsive {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .dataTables_filter input {
            border-radius: var(--radius);
            padding: 8px 15px;
            border: 1px solid var(--border);
        }

        .dataTables_length select {
            border-radius: var(--radius);
            padding: 5px 10px;
            border: 1px solid var(--border);
        }

        .btn {
            border-radius: var(--radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #b45309;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border);
            color: var(--text-secondary);
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--light);
            border-color: var(--secondary);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .topbar {
                padding: 1rem;
            }

            .content {
                padding: 1rem;
            }

            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.25rem;
                color: var(--text-primary);
                cursor: pointer;
                padding: 0.25rem;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .card-body {
                padding: 1rem;
            }
        }

        @media (min-width: 769px) {
            .mobile-menu-btn {
                display: none;
            }
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .overlay.show {
            display: block;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .main-content, .main-content * {
                visibility: visible;
            }
            .main-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .filter-section, .nav-pills, .card-header {
                display: none;
            }
            table {
                width: 100% !important;
            }
            .history-table th {
                background-color: #f8f9fa !important;
                color: #212529 !important;
            }
        }
    </style>
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
                <a href="adminPanel.php">
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
                <a href="history.php" class="active">
                    <i class="bi bi-clock-history"></i>
                    History
                </a>
                <a href="adminRegister.php">
                    <i class="bi bi-person-plus"></i>
                    Admin Registration
                </a>
                <a href="logoutAdmin.php" class="text-danger">
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
                            <h1 class="page-title">Appointment History</h1>
                            <p class="text-muted mb-0 small d-none d-sm-block">View completed appointments and walk-ins</p>
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
                <form method="GET" action="history.php">
                    <div class="filter-section">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="historyType" class="form-label fw-bold">History Type</label>
                                <select class="form-select" id="historyType" name="history_type">
                                    <option value="all" <?= $history_type == 'all' ? 'selected' : '' ?>>All History</option>
                                    <option value="online" <?= $history_type == 'online' ? 'selected' : '' ?>>Online Appointments</option>
                                    <option value="walkin" <?= $history_type == 'walkin' ? 'selected' : '' ?>>Walk-In Appointments</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="timeRange" class="form-label fw-bold">Time Range</label>
                                <select class="form-select" id="timeRange" name="time_range">
                                    <option value="today" <?= $time_range == 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="week" <?= $time_range == 'week' ? 'selected' : '' ?>>This Week</option>
                                    <option value="month" <?= $time_range == 'month' ? 'selected' : '' ?>>This Month</option>
                                    <option value="year" <?= $time_range == 'year' ? 'selected' : '' ?>>This Year</option>
                                    <option value="custom" <?= $time_range == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-filter me-2">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-filter me-2" onclick="printRecords()">
                                    <i class="bi bi-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mt-3" id="customDateRange" style="display: <?= $time_range == 'custom' ? 'block' : 'none' ?>;">
                            <div class="col-md-6 mb-3">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" name="start_date" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" name="end_date" value="<?= $end_date ?>">
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Appointment Records</h5>
                                <span class="badge bg-primary">Total: <?= $total_records ?></span>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-pills mb-4" id="history-tab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="online-tab" data-bs-toggle="pill" data-bs-target="#online-history" type="button" role="tab">
                                            Online Appointments <span class="badge bg-primary ms-1"><?= $total_online ?></span>
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="walkin-tab" data-bs-toggle="pill" data-bs-target="#walkin-history" type="button" role="tab">
                                            Walk-In Appointments <span class="badge bg-primary ms-1"><?= $total_walkin ?></span>
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="history-tabContent">
                                    <div class="tab-pane fade show active" id="online-history" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover history-table" id="onlineTable">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Date</th>
                                                        <th>Time</th>
                                                        <th>Purpose</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($row = mysqli_fetch_assoc($online_result)): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                                        <td><?= date('h:i A', strtotime($row['time'])) ?></td>
                                                        <td><?= htmlspecialchars($row['display_purpose']) ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    
                                    <div class="tab-pane fade" id="walkin-history" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover history-table" id="walkinTable">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Date</th>
                                                        <th>Time</th>
                                                        <th>Purpose</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($row = mysqli_fetch_assoc($walkin_result)): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                                        <td><?= date('h:i A', strtotime($row['time'])) ?></td>
                                                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalDetailsContent">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Mobile menu functionality
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

            // Close sidebar on window resize if desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });

            // Initialize DataTables
            $('#onlineTable').DataTable();
            $('#walkinTable').DataTable();
            
            // Show/hide custom date range
            $('#timeRange').change(function() {
                if ($(this).val() === 'custom') {
                    $('#customDateRange').show();
                } else {
                    $('#customDateRange').hide();
                }
            });
            
            // View details button click handler
            $('.view-details').click(function() {
                const id = $(this).data('id');
                const type = $(this).data('type');
                
                // Show loading spinner
                $('#modalDetailsContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                
                // Load details via AJAX
                $.ajax({
                    url: 'get_appointment_details.php',
                    type: 'GET',
                    data: { id: id, type: type },
                    success: function(response) {
                        $('#modalDetailsContent').html(response);
                    },
                    error: function() {
                        $('#modalDetailsContent').html('<div class="alert alert-danger">Failed to load details. Please try again.</div>');
                    }
                });
                
                // Show modal
                $('#detailsModal').modal('show');
            });
            
            // Export button functionality
            $('#exportBtn').click(function() {
                // Get current filter values
                const historyType = $('#historyType').val();
                const timeRange = $('#timeRange').val();
                const startDate = $('#startDate').val();
                const endDate = $('#endDate').val();
                
                // Build export URL
                let exportUrl = 'export_history.php?history_type=' + historyType + '&time_range=' + timeRange;
                if (timeRange === 'custom') {
                    exportUrl += '&start_date=' + startDate + '&end_date=' + endDate;
                }
                
                // Open export URL in new tab
                window.open(exportUrl, '_blank');
            });
        });

        function printRecords() {
        // Get the active tab content
        const activeTab = document.querySelector('.tab-pane.active');
        
        // Create a print window
        const printWindow = window.open('', '_blank');
        
        // Get the current date for the report title
        const today = new Date();
        const dateString = today.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Build the HTML content for printing
        let printContent = `
            <html>
                <head>
                    <title>Appointment History Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .print-title {
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        .print-date {
                            font-size: 14px;
                            color: #666;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                        }
                        th {
                            background-color: #f2f2f2;
                            text-align: left;
                            padding: 8px;
                            border: 1px solid #ddd;
                        }
                        td {
                            padding: 8px;
                            border: 1px solid #ddd;
                        }
                        .total-records {
                            margin-top: 20px;
                            font-weight: bold;
                        }
                        @page {
                            size: auto;
                            margin: 10mm;
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <div class="print-title">Appointment History Report</div>
                        <div class="print-date">Generated on: ${dateString}</div>
                    </div>
        `;
        
        // Add the active tab's content
        const table = activeTab.querySelector('table');
        const tableClone = table.cloneNode(true);
        
        // Remove DataTables classes and styles
        tableClone.classList.remove('dataTable');
        tableClone.querySelectorAll('*').forEach(el => {
            el.removeAttribute('style');
            el.classList.remove('sorting', 'sorting_asc', 'sorting_desc');
        });
        
        printContent += tableClone.outerHTML;
        
        // Add total records
        const totalRecords = document.querySelector('.card-header .badge').textContent;
        printContent += `
            <div class="total-records">${totalRecords} records found</div>
            </body>
            </html>
        `;
        
        // Write and print the content
        printWindow.document.open();
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load before printing
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    }
    </script>
</body>
</html>