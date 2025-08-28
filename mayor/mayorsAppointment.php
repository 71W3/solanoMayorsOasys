<?php
include 'conn.php';
// Get today's date
$today = date('Y-m-d');

// Get selected date range (default to next 3 weeks, but never show past dates)
$default_start = $today; // Start from today, not past dates
$default_end = date('Y-m-d', strtotime('+3 weeks'));

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end;

// Ensure we never show appointments before today
if ($start_date < $today) {
    $start_date = $today;
}

// Determine current filter for display
$current_filter = 'Custom Range';
if ($start_date == $today && $end_date == $today) {
    $current_filter = 'Today';
} elseif ($start_date == date('Y-m-d', strtotime('monday this week')) && $end_date == date('Y-m-d', strtotime('sunday this week'))) {
    $current_filter = 'This Week';
} elseif ($start_date == date('Y-m-01') && $end_date == date('Y-m-t')) {
    $current_filter = 'This Month';
} elseif ($start_date == date('Y-m-d', strtotime('monday next week')) && $end_date == date('Y-m-d', strtotime('sunday next week'))) {
    $current_filter = 'Next Week';
}

// Get sorting parameters
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_time';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'asc';

// Build ORDER BY clause based on sorting parameters
$order_clause = "";
switch ($sort_by) {
    case 'date_time':
        $order_clause = "ORDER BY a.date " . ($sort_order === 'asc' ? 'ASC' : 'DESC') . ", a.time " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'name':
        $order_clause = "ORDER BY u.name " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'purpose':
        $order_clause = "ORDER BY a.purpose " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'status':
        $order_clause = "ORDER BY a.status_enum " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'created':
        $order_clause = "ORDER BY a.created_at " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
        break;
    default:
        $order_clause = "ORDER BY a.date ASC, a.time ASC";
}

// Get approved appointments for selected date range from schedule table
$stmt = $conn->prepare("
    SELECT 
        s.sched_id,
        a.id,
        a.purpose,
        a.date,
        a.time,
        a.status_enum,
        a.created_at,
        a.updated_at,
        u.name,
        u.email,
        u.phone,
        u.message as admin_notes,
        s.note as schedule_note
    FROM schedule s
    JOIN appointments a ON s.app_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE a.date BETWEEN ? AND ?
    AND a.status_enum IN ('approved', 'completed')
    $order_clause
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$scheduled_appointments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Mayor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="dashboard-styles.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #dbeafe;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 0.75rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

        .main-content {
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1d4ed8 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .current-filter-indicator {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            margin-top: 1rem;
        }

        .filters-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }

        .date-filters {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1.5rem;
            align-items: end;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-control {
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .quick-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            padding: 1.5rem 0 0 0;
            border-top: 1px solid var(--gray-200);
        }

        .btn-outline-primary {
            border: 2px solid var(--gray-200);
            color: var(--gray-700);
            background: white;
            font-size: 0.875rem;
            padding: 0.75rem;
            position: relative;
            text-align: center;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-primary.active {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .appointments-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .appointments-header {
            background: linear-gradient(135deg, var(--gray-800) 0%, var(--gray-700) 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .appointments-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sort-info {
            font-size: 0.875rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }

        .sort-filter-dropdown {
            position: relative;
        }

        .sort-filter-dropdown .btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
        }

        .sort-filter-dropdown .btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .sort-filter-dropdown .btn:focus,
        .sort-filter-dropdown .btn.show {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1);
        }

        .sort-filter-dropdown .dropdown-menu {
            background: white;
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-lg);
            padding: 0.5rem 0;
            min-width: 280px;
            margin-top: 0.5rem;
        }

        .sort-filter-dropdown .dropdown-header {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            padding: 0.75rem 1rem 0.5rem 1rem;
            margin-bottom: 0;
        }

        .sort-filter-dropdown .dropdown-item {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        .sort-filter-dropdown .dropdown-item:hover {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .sort-filter-dropdown .dropdown-item.active {
            background: var(--primary-color);
            color: white;
        }

        .sort-filter-dropdown .dropdown-item.active:hover {
            background: #1d4ed8;
            color: white;
        }

        .sort-filter-dropdown .dropdown-divider {
            margin: 0.5rem 0;
            border-color: var(--gray-200);
        }

        .sort-label {
            font-weight: 500;
        }

        .stats-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 0;
            padding: 1rem 2rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            flex-wrap: wrap;
        }

        .stat-item {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            flex: 1;
            min-width: 120px;
            text-align: center;
        }

        .stat-number {
            font-weight: 700;
            color: var(--primary-color);
            display: block;
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .schedule-date-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 1.5rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: sticky;
            top: 0;
            z-index: 10;
            border-top: 1px solid var(--gray-200);
        }

        .appointment-item {
            border-bottom: 1px solid var(--gray-200);
            padding: 2rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .appointment-item:hover {
            background-color: var(--gray-50);
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .appointment-id {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-800);
            background: var(--gray-100);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-approved {
            background: #dcfce7;
            color: var(--success-color);
        }

        .status-completed {
            background: #dbeafe;
            color: var(--primary-color);
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-800);
            background: var(--gray-50);
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--gray-200);
            word-break: break-word;
        }

        .notes-section {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .notes-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .notes-content {
            color: #78350f;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .schedule-notes {
            background: #dbeafe !important;
            border-color: var(--primary-color) !important;
        }

        .schedule-notes .notes-header {
            color: #1e40af !important;
        }

        .schedule-notes .notes-content {
            color: #1e3a8a !important;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h4 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }

        .empty-state p {
            font-size: 1.1rem;
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .current-filter-indicator {
                margin-top: 0.5rem;
            }
            
            .date-filters {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .quick-filters {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .btn-outline-primary {
                font-size: 0.8rem;
                padding: 0.625rem 0.5rem;
            }
            
            .appointments-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .appointments-title {
                font-size: 1.1rem;
            }
            
            .sort-filter-dropdown .btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }
            
            .sort-filter-dropdown .dropdown-menu {
                min-width: 250px;
                font-size: 0.85rem;
            }
            
            .sort-filter-dropdown .dropdown-item {
                padding: 0.625rem 0.875rem;
                font-size: 0.8rem;
            }
            
            .stats-bar {
                padding: 1rem;
                gap: 0.5rem;
            }
            
            .stat-item {
                min-width: 100px;
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .stat-number {
                font-size: 1.1rem;
            }
            
            .appointment-item {
                padding: 1.5rem;
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .appointment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .schedule-date-header {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .quick-filters {
                grid-template-columns: 1fr;
            }
            
            .btn-outline-primary {
                font-size: 0.875rem;
                padding: 0.75rem;
            }
            
            .stats-bar {
                flex-direction: column;
            }
            
            .stat-item {
                text-align: left;
            }
            
            .appointments-header {
                align-items: stretch;
            }
            
            .sort-filter-dropdown .dropdown-menu {
                min-width: 100%;
                left: 0 !important;
                right: 0 !important;
            }
        }

        /* Loading states */
        .btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <?php include 'topbar.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-calendar-event"></i>
                Scheduled Appointments
            </h1>
            <p class="page-subtitle">
                Manage and view your scheduled appointments for <?= date('M j', strtotime($start_date)) ?> - <?= date('M j, Y', strtotime($end_date)) ?>
            </p>
            <div class="current-filter-indicator">
                <i class="bi bi-funnel"></i>
                Currently Showing: <strong><?= $current_filter ?></strong>
                <?php if ($current_filter != 'Custom Range'): ?>
                    <small style="opacity: 0.8; margin-left: 0.5rem;">
                        (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="filters-card">
            <form method="GET" class="date-filters" id="dateFilterForm">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>" min="<?= $today ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>" min="<?= $today ?>">
                </div>
                <button type="submit" class="btn btn-primary" id="applyFilterBtn">
                    <i class="bi bi-search"></i>
                    Apply Filter
                </button>
            </form>

            <div class="quick-filters">
                <button type="button" class="btn btn-outline-primary <?= $current_filter == 'Today' ? 'active' : '' ?>" onclick="setDateRange('today')">
                    <i class="bi bi-calendar-day"></i>
                    Today
                </button>
                <button type="button" class="btn btn-outline-primary <?= $current_filter == 'This Week' ? 'active' : '' ?>" onclick="setDateRange('week')">
                    <i class="bi bi-calendar-week"></i>
                    This Week
                </button>
                <button type="button" class="btn btn-outline-primary <?= $current_filter == 'Next Week' ? 'active' : '' ?>" onclick="setDateRange('next-week')">
                    <i class="bi bi-calendar-plus"></i>
                    Next Week
                </button>
                <button type="button" class="btn btn-outline-primary <?= $current_filter == 'This Month' ? 'active' : '' ?>" onclick="setDateRange('month')">
                    <i class="bi bi-calendar-month"></i>
                    This Month
                </button>
            </div>
        </div>

        <!-- Appointments Container -->
        <div class="appointments-container">
            <?php if (count($scheduled_appointments) > 0): ?>
                <div class="appointments-header">
                    <div class="appointments-title">
                        <i class="bi bi-list-check"></i>
                        Appointments List
                    </div>
                    <div class="sort-filter-dropdown">
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-sort-down-alt"></i>
                                <span class="sort-label">
                                    <?php
                                    $sort_labels = [
                                        'date_time' => 'Date & Time',
                                        'name' => 'Resident Name',
                                        'purpose' => 'Purpose',
                                        'status' => 'Status',
                                        'created' => 'Date Created'
                                    ];
                                    echo $sort_labels[$sort_by] ?? 'Date & Time';
                                    echo ' (' . ($sort_order === 'asc' ? 'A-Z' : 'Z-A') . ')';
                                    ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                                <li><h6 class="dropdown-header"><i class="bi bi-sort-alpha-down me-2"></i>Sort by</h6></li>
                                <li><a class="dropdown-item <?= $sort_by === 'date_time' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'date_time', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-calendar-event me-2"></i>Date & Time (Earliest First)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'date_time' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'date_time', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-calendar-event me-2"></i>Date & Time (Latest First)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'name' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'name', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-person me-2"></i>Resident Name (A-Z)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'name' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'name', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-person me-2"></i>Resident Name (Z-A)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'purpose' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'purpose', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-clipboard-check me-2"></i>Purpose (A-Z)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'purpose' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'purpose', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-clipboard-check me-2"></i>Purpose (Z-A)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'status' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'status', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-check-circle me-2"></i>Status (A-Z)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'status' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'status', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-check-circle me-2"></i>Status (Z-A)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'created' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'created', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-clock me-2"></i>Date Created (Oldest First)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'created' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'created', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-clock me-2"></i>Date Created (Newest First)
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="stats-bar">
                    <div class="stat-item">
                        <span class="stat-number"><?= count($scheduled_appointments) ?></span>
                        Total Appointments
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= count(array_filter($scheduled_appointments, fn($a) => $a['status_enum'] === 'approved')) ?></span>
                        Approved
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= count(array_filter($scheduled_appointments, fn($a) => $a['status_enum'] === 'completed')) ?></span>
                        Completed
                    </div>
                </div>
                
                <?php 
                $current_date = '';
                foreach ($scheduled_appointments as $appointment): 
                    $appointment_date = $appointment['date'];
                    if ($appointment_date !== $current_date):
                        $current_date = $appointment_date;
                ?>
                    <div class="schedule-date-header">
                        <i class="bi bi-calendar-date"></i>
                        <?= date('l, F j, Y', strtotime($appointment_date)) ?>
                    </div>
                <?php endif; ?>
                    <div class="appointment-item">
                        <div class="appointment-header">
                            <span class="appointment-id">#APP-<?= $appointment['id'] ?></span>
                            <span class="status-badge status-<?= $appointment['status_enum'] ?>">
                                <i class="bi bi-<?= $appointment['status_enum'] === 'approved' ? 'check-circle' : 'check-circle-fill' ?>"></i>
                                <?= ucfirst($appointment['status_enum']) ?>
                            </span>
                        </div>
                        
                        <div class="appointment-details">
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="bi bi-clipboard-check"></i>
                                    Purpose
                                </span>
                                <span class="detail-value"><?= htmlspecialchars($appointment['purpose']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="bi bi-clock"></i>
                                    Time
                                </span>
                                <span class="detail-value"><?= date('g:i A', strtotime($appointment['time'])) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="bi bi-person"></i>
                                    Resident
                                </span>
                                <span class="detail-value"><?= htmlspecialchars($appointment['name']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="bi bi-telephone"></i>
                                    Contact
                                </span>
                                <span class="detail-value">
                                    <a href="tel:<?= htmlspecialchars($appointment['phone']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($appointment['phone']) ?>
                                    </a>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="bi bi-envelope"></i>
                                    Email
                                </span>
                                <span class="detail-value">
                                    <a href="mailto:<?= htmlspecialchars($appointment['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($appointment['email']) ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($appointment['admin_notes'])): ?>
                            <div class="notes-section">
                                <div class="notes-header">
                                    <i class="bi bi-sticky"></i>
                                    Admin Notes
                                </div>
                                <div class="notes-content">
                                    <?= nl2br(htmlspecialchars($appointment['admin_notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($appointment['schedule_note'])): ?>
                            <div class="notes-section schedule-notes">
                                <div class="notes-header">
                                    <i class="bi bi-calendar-event"></i>
                                    Schedule Notes
                                </div>
                                <div class="notes-content">
                                    <?= nl2br(htmlspecialchars($appointment['schedule_note'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="appointments-header">
                    <div class="appointments-title">
                        <i class="bi bi-calendar-x"></i>
                        No Appointments Found
                    </div>
                    <div class="sort-filter-dropdown">
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" id="sortDropdownEmpty" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-sort-down-alt"></i>
                                <span class="sort-label">
                                    <?php
                                    echo $sort_labels[$sort_by] ?? 'Date & Time';
                                    echo ' (' . ($sort_order === 'asc' ? 'A-Z' : 'Z-A') . ')';
                                    ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdownEmpty">
                                <li><h6 class="dropdown-header"><i class="bi bi-sort-alpha-down me-2"></i>Sort by</h6></li>
                                <li><a class="dropdown-item <?= $sort_by === 'date_time' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'date_time', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-calendar-event me-2"></i>Date & Time (Earliest First)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'date_time' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'date_time', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-calendar-event me-2"></i>Date & Time (Latest First)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'name' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'name', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-person me-2"></i>Resident Name (A-Z)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'name' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'name', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-person me-2"></i>Resident Name (Z-A)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'purpose' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'purpose', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-clipboard-check me-2"></i>Purpose (A-Z)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'purpose' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'purpose', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-clipboard-check me-2"></i>Purpose (Z-A)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'status' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'status', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-check-circle me-2"></i>Status (A-Z)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'status' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'status', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-check-circle me-2"></i>Status (Z-A)
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?= $sort_by === 'created' && $sort_order === 'asc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'created', 'sort_order' => 'asc'])) ?>">
                                    <i class="bi bi-clock me-2"></i>Date Created (Oldest First)
                                </a></li>
                                <li><a class="dropdown-item <?= $sort_by === 'created' && $sort_order === 'desc' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'created', 'sort_order' => 'desc'])) ?>">
                                    <i class="bi bi-clock me-2"></i>Date Created (Newest First)
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h4>No Scheduled Appointments</h4>
                    <p>There are no approved appointments scheduled for <strong><?= $current_filter ?></strong>. Try adjusting your date filters or check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="dashboard-scripts.js"></script>
    <script>
        function setDateRange(range) {
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            let startDate, endDate;

            // Add loading state to clicked button
            const clickedButton = event.target.closest('button');
            clickedButton.classList.add('loading');
            const originalHTML = clickedButton.innerHTML;
            clickedButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading...';

            switch (range) {
                case 'today':
                    startDate = endDate = todayStr;
                    break;
                case 'week':
                    // This week: from today to Saturday (no past dates)
                    const currentDay = today.getDay(); // 0 = Sunday, 1 = Monday, etc.
                    let weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - currentDay); // Go back to Sunday
                    
                    // If week start is before today, use today instead
                    if (weekStart.toISOString().split('T')[0] < todayStr) {
                        weekStart = today;
                    }
                    
                    const weekEnd = new Date(today);
                    weekEnd.setDate(today.getDate() + (6 - currentDay)); // Go to Saturday
                    
                    startDate = weekStart.toISOString().split('T')[0];
                    endDate = weekEnd.toISOString().split('T')[0];
                    break;
                case 'month':
                    // This month: from today to end of month (no past dates)
                    let monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                    
                    // If month start is before today, use today instead
                    if (monthStart.toISOString().split('T')[0] < todayStr) {
                        monthStart = today;
                    }
                    
                    const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    startDate = monthStart.toISOString().split('T')[0];
                    endDate = monthEnd.toISOString().split('T')[0];
                    break;
                case 'next-week':
                    // Next week: Sunday to Saturday of next week
                    const nextWeekStart = new Date(today);
                    const daysUntilNextSunday = 7 - today.getDay();
                    nextWeekStart.setDate(today.getDate() + (daysUntilNextSunday === 0 ? 7 : daysUntilNextSunday));
                    const nextWeekEnd = new Date(nextWeekStart);
                    nextWeekEnd.setDate(nextWeekStart.getDate() + 6);
                    startDate = nextWeekStart.toISOString().split('T')[0];
                    endDate = nextWeekEnd.toISOString().split('T')[0];
                    break;
                default:
                    clickedButton.classList.remove('loading');
                    clickedButton.innerHTML = originalHTML;
                    return;
            }

            // Ensure we never go to past dates
            if (startDate < todayStr) {
                startDate = todayStr;
            }

            // Redirect to same page with new parameters
            window.location.href = `mayorsAppointment.php?start_date=${startDate}&end_date=${endDate}`;
        }

        // Enhanced form handling and loading states
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission loading state
            const form = document.getElementById('dateFilterForm');
            const applyBtn = document.getElementById('applyFilterBtn');
            
            form.addEventListener('submit', function(e) {
                applyBtn.classList.add('loading');
                applyBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Applying...';
                applyBtn.disabled = true;
            });

            // Add smooth animations to appointment items
            const appointmentItems = document.querySelectorAll('.appointment-item');
            appointmentItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('fade-in');
            });

            // Auto-focus on first input for better UX
            const firstInput = document.querySelector('input[name="start_date"]');
            if (firstInput) {
                firstInput.addEventListener('focus', function() {
                    this.showPicker && this.showPicker();
                });
            }

            // Add keyboard navigation for quick filters
            const quickFilterButtons = document.querySelectorAll('.btn-outline-primary');
            quickFilterButtons.forEach((btn, index) => {
                btn.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowRight' && index < quickFilterButtons.length - 1) {
                        quickFilterButtons[index + 1].focus();
                    } else if (e.key === 'ArrowLeft' && index > 0) {
                        quickFilterButtons[index - 1].focus();
                    }
                });
            });

            // Add swipe gesture support for mobile date navigation
            let startX = null;
            let startY = null;
            
            document.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            });

            document.addEventListener('touchend', function(e) {
                if (!startX || !startY) return;
                
                let endX = e.changedTouches[0].clientX;
                let endY = e.changedTouches[0].clientY;
                
                let diffX = startX - endX;
                let diffY = startY - endY;
                
                // Check if it's a horizontal swipe
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                    const appointmentsContainer = document.querySelector('.appointments-container');
                    if (appointmentsContainer && appointmentsContainer.contains(e.target)) {
                        if (diffX > 0) {
                            // Swiped left - next period
                            navigateDate('next');
                        } else {
                            // Swiped right - previous period
                            navigateDate('previous');
                        }
                    }
                }
                
                startX = null;
                startY = null;
            });
        });

        function navigateDate(direction) {
            const currentStart = new Date(document.querySelector('input[name="start_date"]').value);
            const currentEnd = new Date(document.querySelector('input[name="end_date"]').value);
            const diffDays = Math.ceil((currentEnd - currentStart) / (1000 * 60 * 60 * 24));
            
            let newStart, newEnd;
            
            if (direction === 'next') {
                newStart = new Date(currentEnd);
                newStart.setDate(newStart.getDate() + 1);
                newEnd = new Date(newStart);
                newEnd.setDate(newEnd.getDate() + diffDays);
            } else {
                newEnd = new Date(currentStart);
                newEnd.setDate(newEnd.getDate() - 1);
                newStart = new Date(newEnd);
                newStart.setDate(newStart.getDate() - diffDays);
            }
            
            const startDateStr = newStart.toISOString().split('T')[0];
            const endDateStr = newEnd.toISOString().split('T')[0];
            
            window.location.href = `mayorsAppointment.php?start_date=${startDateStr}&end_date=${endDateStr}`;
        }

        // Add CSS animation classes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .fade-in {
                animation: fadeIn 0.6s ease-out forwards;
            }
            
            .appointment-item {
                opacity: 0;
            }
            
            .appointment-item.fade-in {
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>