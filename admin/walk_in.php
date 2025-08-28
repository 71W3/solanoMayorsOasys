<?php
include 'connect.php';

$success = false;
$success_message = '';

// Handle form submission for new walk-in
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_walk_in'])) {
    $name = $con->real_escape_string($_POST['name']);
    $address = $con->real_escape_string($_POST['address']);
    $purpose = $con->real_escape_string($_POST['purpose']);
    
    // Generate appointment number
    $last_id_query = "SELECT MAX(id) as max_id FROM walk_in";
    $result = $con->query($last_id_query);
    $row = $result->fetch_assoc();
    $next_id = $row['max_id'] + 1;
    $appointment_number = '#' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
    
    $insert_query = "INSERT INTO walk_in (appointment_number, name, address, purpose, created_at, status) 
                     VALUES ('$appointment_number', '$name', '$address', '$purpose', NOW(), 'waiting')";
    
    if ($con->query($insert_query)) {
        $success = true;
        $success_message = "Walk-in added successfully!";
    } else {
        $success = true;
        $success_message = "Error adding walk-in: " . $con->error;
    }
}

// Handle sending to queue with transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_to_queue'])) {
    if (!empty($_POST['selected_walkins'])) {
        $con->autocommit(FALSE); // Start transaction
        $success = true;
        $sent_appointments = [];
        $errors = [];
        $today = date('Y-m-d');
        
        foreach ($_POST['selected_walkins'] as $walk_in_id) {
            $walk_in_id = $con->real_escape_string($walk_in_id);
            
            // Verify walk-in exists and is waiting
            $check_query = "SELECT id, appointment_number FROM walk_in WHERE id = '$walk_in_id' AND status = 'waiting'";
            $check_result = $con->query($check_query);
            
            if ($check_result && $check_result->num_rows > 0) {
                $walk_in = $check_result->fetch_assoc();
                $sent_appointments[] = $walk_in['appointment_number'];
                
                // Insert into queue
                $insert_queue_query = "INSERT INTO queue (walk_in_id, created_at) VALUES ('$walk_in_id', NOW())";
                if (!$con->query($insert_queue_query)) {
                    $errors[] = "Failed to add to queue: " . $con->error;
                }
                
                // Update status to complete
                $update_status_query = "UPDATE walk_in SET status = 'complete' WHERE id = '$walk_in_id'";
                if (!$con->query($update_status_query)) {
                    $errors[] = "Failed to update status: " . $con->error;
                }
                
                // Add to walkin_history
                $insert_history_query = "INSERT INTO walkin_history (walk_in_id, date) VALUES ('$walk_in_id', '$today')";
                if (!$con->query($insert_history_query)) {
                    $errors[] = "Failed to add to history: " . $con->error;
                }
            } else {
                $errors[] = "Walk-in ID $walk_in_id not found or not in waiting status";
            }
        }
        
        if (empty($errors)) {
            $con->commit();
            $success_message = "Appointments sent to queue: " . implode(", ", $sent_appointments);
        } else {
            $con->rollback();
            $success_message = "Errors occurred: " . implode("; ", $errors);
        }
        
        $con->autocommit(TRUE); // End transaction
    } else {
        $success = true;
        $success_message = "Please select at least one walk-in to send to queue.";
    }
}

// Fetch all waiting walk-in appointments (only those not in queue and with waiting status)
$walk_ins_query = "SELECT * FROM walk_in WHERE id NOT IN (SELECT walk_in_id FROM queue) AND status = 'waiting' ORDER BY created_at ASC";
$walk_ins_result = $con->query($walk_ins_query);



// Fetch waiting walk-ins
$walk_ins_query = "SELECT * FROM walk_in WHERE status = 'waiting' ORDER BY created_at ASC";
$walk_ins_result = mysqli_query($con, $walk_ins_query);

// Get stats
$total_walkins = 0;
$waiting_walkins = 0;
$complete_walkins = 0;
$today_walkins = 0;
$in_queue = 0;

$result = mysqli_query($con, "SELECT COUNT(*) as count FROM walk_in");
if ($result) $total_walkins = mysqli_fetch_row($result)[0];

$result = mysqli_query($con, "SELECT COUNT(*) as count FROM walk_in WHERE status = 'waiting'");
if ($result) $waiting_walkins = mysqli_fetch_row($result)[0];

$result = mysqli_query($con, "SELECT COUNT(*) as count FROM walk_in WHERE status = 'complete'");
if ($result) $complete_walkins = mysqli_fetch_row($result)[0];

$result = mysqli_query($con, "SELECT COUNT(*) as count FROM walk_in WHERE DATE(created_at) = CURDATE()");
if ($result) $today_walkins = mysqli_fetch_row($result)[0];

// Check if queue table exists before querying
$queue_check = mysqli_query($con, "SHOW TABLES LIKE 'queue'");
if ($queue_check && mysqli_num_rows($queue_check) > 0) {
    $result = mysqli_query($con, "SELECT COUNT(*) as count FROM queue");
    if ($result) $in_queue = mysqli_fetch_row($result)[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Appointments - OASYS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stats-card-content {
            display: flex;
            align-items: center;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            background: var(--lighter);
            color: var(--accent);
            flex-shrink: 0;
        }

        .stats-info {
            flex: 1;
            min-width: 0;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stats-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Cards */
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

        /* Tables */
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

        .selected-row {
            background-color: rgba(37, 99, 235, 0.1) !important;
        }

        /* Buttons */
        .btn {
            border-radius: var(--radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }

        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }

        .btn-success:hover {
            background-color: #047857;
            border-color: #047857;
        }

        .btn-outline-primary {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-outline-primary:hover {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        /* Modal */
        .modal-content {
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            background: var(--light);
            border-top: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }

        /* Form Controls */
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
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        /* Checkboxes */
        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        /* Badges */
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
        }

        .user-avatar-sm {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 10px;
            flex-shrink: 0;
        }

        /* Alert */
        .alert {
            border-radius: var(--radius);
            border: 1px solid transparent;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: rgba(5, 150, 105, 0.1);
            border-color: rgba(5, 150, 105, 0.2);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(220, 38, 38, 0.1);
            border-color: rgba(220, 38, 38, 0.2);
            color: var(--danger);
        }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h6 {
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .empty-state p {
            font-size: 0.875rem;
            margin: 0;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
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

            .stats-grid {
                grid-template-columns: 1fr;
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
                        <i class="bi bi-sun"></i>
                    </div>
                    <div>
                        <h5>OASYS Admin</h5>
                        <div class="version">Municipality of Solano</div>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">
                    <i class="bi bi-house"></i>
                    Dashboard
                </a>
                <a href="appointment.php">
                    <i class="bi bi-calendar-check"></i>
                    Appointments
                </a>
                <a href="#" class="active">
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
                <a href="#">
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
                            <h1 class="page-title">Walk-in Appointments</h1>
                            <p class="text-muted mb-0 small d-none d-sm-block">Manage walk-in appointments and queue processing</p>
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
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" id="success-alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon">
                                <i class="bi bi-person-walking"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $total_walkins ?></div>
                                <div class="stats-label">Total Walk-ins</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(217, 119, 6, 0.1); color: var(--warning);">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $waiting_walkins ?></div>
                                <div class="stats-label">Waiting</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(5, 150, 105, 0.1); color: var(--success);">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $complete_walkins ?></div>
                                <div class="stats-label">Completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--accent);">
                                <i class="bi bi-calendar-day"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $today_walkins ?></div>
                                <div class="stats-label">Today</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-icon" style="background: rgba(139, 69, 19, 0.1); color: #8b4513;">
                                <i class="bi bi-list-ol"></i>
                            </div>
                            <div class="stats-info">
                                <div class="stats-number"><?= $in_queue ?></div>
                                <div class="stats-label">In Queue</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
               <div class="container-fluid">
    <div class="row">
        
        
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert" id="success-alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <h1 class="mb-4 mt-3">Walk-in Appointments</h1>

            <!-- Button to trigger modal -->
            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addWalkInModal">
                <i class="bi bi-plus-circle"></i> Add Walk-in Appointment
            </button>

            <!-- Walk-in List -->
            <form method="post" action="walk_in.php">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50px">Select</th>
                                <th>Appointment #</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Purpose</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($walk_ins_result->num_rows > 0): ?>
                                <?php while($walk_in = $walk_ins_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input row-checkbox" type="checkbox" 
                                                   name="selected_walkins[]" value="<?= $walk_in['id'] ?>">
                                        </div>
                                    </td>
                                    <td><?= $walk_in['appointment_number'] ?></td>
                                    <td><?= $walk_in['name'] ?></td>
                                    <td><?= $walk_in['address'] ?></td>
                                    <td><?= $walk_in['purpose'] ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($walk_in['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No walk-in appointments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($walk_ins_result->num_rows > 0): ?>
                <button type="submit" name="send_to_queue" class="btn btn-success mt-3">
                    <i class="bi bi-send"></i> Send Selected to Queue
                </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

    <!-- Add Walk-in Modal -->
<div class="modal fade" id="addWalkInModal" tabindex="-1" aria-labelledby="addWalkInModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="walk_in.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addWalkInModalLabel">Add New Walk-in</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Barangay</label>
                        <select class="form-select" id="address" name="address" required>
                            <option value="" selected disabled>Select Barangay</option>
                            <option value="Aggub">Aggub</option>
                            <option value="Dadap">Dadap</option>
                            <option value="Roxas">Roxas</option>
                            <option value="Bagahabag">Bagahabag</option>
                            <option value="Lactawan">Lactawan</option>
                            <option value="San Juan">San Juan</option>
                            <option value="Bangaan">Bangaan</option>
                            <option value="Osmeña">Osmeña</option>
                            <option value="San Luis">San Luis</option>
                            <option value="Bangar">Bangar</option>
                            <option value="Quezon">Quezon</option>
                            <option value="Tucal">Tucal</option>
                            <option value="Bascaran">Bascaran</option>
                            <option value="PD Galima">PD Galima</option>
                            <option value="Uddiawan">Uddiawan</option>
                            <option value="Communal">Communal</option>
                            <option value="Poblacion North">Poblacion North</option>
                            <option value="Wacal">Wacal</option>
                            <option value="Concepcion">Concepcion</option>
                            <option value="Poblacion South">Poblacion South</option>
                            <option value="Curifang">Curifang</option>
                            <option value="Quirino">Quirino</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_walk_in" class="btn btn-primary">Add Walk-in</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing walk-in page...');
            
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

            // Checkbox functionality
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            const selectedCountSpan = document.getElementById('selectedCount');
            const sendToQueueBtn = document.getElementById('sendToQueueBtn');

            console.log('Found checkboxes:', rowCheckboxes.length);

            function updateSelectedCount() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const count = checkedBoxes.length;
                
                console.log('Selected count:', count);
                
                // Update selected count display
                if (selectedCountSpan) {
                    selectedCountSpan.textContent = count;
                }
                
                // Enable/disable the send to queue button
                if (sendToQueueBtn) {
                    sendToQueueBtn.disabled = count === 0;
                }

                // Update select all checkbox state
                if (selectAllCheckbox && rowCheckboxes.length > 0) {
                    const totalCheckboxes = rowCheckboxes.length;
                    if (count === 0) {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.indeterminate = false;
                    } else if (count === totalCheckboxes) {
                        selectAllCheckbox.checked = true;
                        selectAllCheckbox.indeterminate = false;
                    } else {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.indeterminate = true;
                    }
                }
            }

            // Individual checkbox change handlers
            rowCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    console.log('Checkbox changed:', this.value, this.checked);
                    
                    const row = this.closest('tr');
                    if (this.checked) {
                        row.classList.add('selected-row');
                    } else {
                        row.classList.remove('selected-row');
                    }
                    updateSelectedCount();
                });
            });

            // Select all functionality
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    console.log('Select all changed:', this.checked);
                    
                    const isChecked = this.checked;
                    rowCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = isChecked;
                        const row = checkbox.closest('tr');
                        if (isChecked) {
                            row.classList.add('selected-row');
                        } else {
                            row.classList.remove('selected-row');
                        }
                    });
                    updateSelectedCount();
                });
            }

            // Initial count update
            updateSelectedCount();

            // Form validation - prevent submission if no items selected
            const walkInForm = document.getElementById('walkInForm');
            if (walkInForm) {
                walkInForm.addEventListener('submit', function(e) {
                    console.log('Form submitted');
                    
                    // Check if this is the send to queue button
                    if (e.submitter && e.submitter.name === 'send_to_queue') {
                        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                        console.log('Checked boxes for queue:', checkedBoxes.length);
                        
                        if (checkedBoxes.length === 0) {
                            e.preventDefault();
                            alert('Please select at least one walk-in to send to queue.');
                            return false;
                        }
                        
                        // Show loading state
                        if (sendToQueueBtn) {
                            sendToQueueBtn.disabled = true;
                            sendToQueueBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Sending...';
                        }
                        
                        // Show confirmation
                        if (!confirm('Are you sure you want to send ' + checkedBoxes.length + ' walk-in(s) to the queue?')) {
                            e.preventDefault();
                            if (sendToQueueBtn) {
                                sendToQueueBtn.disabled = false;
                                sendToQueueBtn.innerHTML = '<i class="bi bi-send me-2"></i>Send Selected to Queue';
                            }
                            return false;
                        }
                    }
                });
            }

            // Auto-dismiss success alert after 5 seconds
            const successAlert = document.getElementById('success-alert');
            if (successAlert) {
                setTimeout(function() {
                    const alert = bootstrap.Alert.getOrCreateInstance(successAlert);
                    alert.close();
                }, 5000);
            }

            // Add walk-in form handling
            const addWalkInForm = document.getElementById('addWalkInForm');
            const addWalkInBtn = document.getElementById('addWalkInBtn');

            if (addWalkInForm && addWalkInBtn) {
                addWalkInForm.addEventListener('submit', function(e) {
                    console.log('Add walk-in form submitted');
                    
                    // Basic validation
                    const name = document.getElementById('name').value.trim();
                    const address = document.getElementById('address').value;
                    const purpose = document.getElementById('purpose').value.trim();
                    
                    if (!name || !address || !purpose) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                        return false;
                    }
                    
                    if (this.checkValidity()) {
                        addWalkInBtn.disabled = true;
                        addWalkInBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Adding...';
                    }
                });
            }

            // Clear modal form when closed
            const addWalkInModal = document.getElementById('addWalkInModal');
            if (addWalkInModal) {
                addWalkInModal.addEventListener('hidden.bs.modal', function() {
                    const form = this.querySelector('form');
                    if (form) {
                        form.reset();
                        form.classList.remove('was-validated');
                    }
                    // Reset button state
                    if (addWalkInBtn) {
                        addWalkInBtn.disabled = false;
                        addWalkInBtn.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Walk-in';
                    }
                });
            }

            console.log('Walk-in page initialized successfully');
        });
    </script>
</body>
</html>