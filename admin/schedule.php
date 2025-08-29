    <?php
    session_start();
    include "connect.php";

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $schedId = $_POST['sched_id'];
        $note = $_POST['note'];

        $stmt = $con->prepare("UPDATE schedule SET note = ? WHERE sched_id = ?");
        $stmt->bind_param("si", $note, $schedId);
        if ($stmt->execute()) {
            header("Location: schedule.php?success=1");
        } else {
            echo "Error saving note.";
        }
    }

    // Fetch all scheduled appointments
    $appointments = [];
    $result = $con->query("
        SELECT
            s.sched_id,
            a.id AS appointment_id,
            a.purpose,
            a.attendees,
            a.date,
            a.time,
            u.name AS resident_name,
            s.note,
            NULL AS is_mayor_appointment,
            'regular' AS appointment_type,
            NULL AS mayor_appointment_type,
            NULL AS mayor_photographer
        FROM schedule s
        JOIN appointments a ON s.app_id = a.id
        JOIN users u ON a.user_id = u.id
        
        UNION ALL
        
        SELECT
            s.sched_id,
            NULL AS appointment_id,
            m.appointment_title AS purpose,
            1 AS attendees,
            m.date,
            m.time,
            'Mayor' AS resident_name,
            s.note AS note,
            1 AS is_mayor_appointment,
            'mayor' AS appointment_type,
            m.appointment_type AS mayor_appointment_type,
            m.photographer AS mayor_photographer
        FROM schedule s
        JOIN mayors_appointment m ON s.mayor_id = m.id
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    } else {
        error_log("Database query failed: " . $con->error);
        $appointments = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Schedule Calendar - SOLAR Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
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

            .sidebar.show {
                transform: translateX(0);
            }

            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                }
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

            @media (max-width: 768px) {
                .main-content {
                    margin-left: 0;
                    width: 100%;
                }
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

            .mobile-menu-btn {
                display: none;
                background: none;
                border: none;
                font-size: 1.25rem;
                color: var(--text-primary);
                cursor: pointer;
                padding: 0.25rem;
            }

            @media (max-width: 768px) {
                .mobile-menu-btn {
                    display: block;
                }
                
                .topbar {
                    padding: 1rem;
                }
            }

            .page-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: var(--text-primary);
                margin: 0;
            }

            .content {
                padding: 2rem;
            }

            @media (max-width: 768px) {
                .content {
                    padding: 1rem;
                }
                
                .page-title {
                    font-size: 1.25rem;
                }
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
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            @media (max-width: 768px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }
            }

            @media (max-width: 480px) {
                .stats-grid {
                    grid-template-columns: 1fr;
                }
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

            @media (max-width: 768px) {
                .stats-card {
                    padding: 1rem;
                }
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

            @media (max-width: 768px) {
                .stats-icon {
                    width: 50px;
                    height: 50px;
                    font-size: 1.25rem;
                    margin-right: 0.75rem;
                }
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

            @media (max-width: 768px) {
                .stats-number {
                    font-size: 1.5rem;
                }
            }

            .stats-label {
                font-size: 0.875rem;
                color: var(--text-secondary);
                font-weight: 500;
            }

            @media (max-width: 768px) {
                .stats-label {
                    font-size: 0.8125rem;
                }
            }

            /* Calendar Card */
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

            @media (max-width: 768px) {
                .card-header {
                    padding: 1rem;
                    font-size: 1rem;
                }
            }

            .card-body {
                padding: 1.5rem;
            }

            @media (max-width: 768px) {
                .card-body {
                    padding: 1rem;
                }
            }

            /* FullCalendar Customization */
            .fc {
                font-family: 'Inter', sans-serif;
            }

            .fc-header-toolbar {
                margin-bottom: 24px !important;
                padding-bottom: 16px !important;
                border-bottom: 1px solid var(--border) !important;
                flex-wrap: wrap !important;
                gap: 12px !important;
            }

            @media (max-width: 768px) {
                .fc-header-toolbar {
                    flex-direction: column !important;
                    text-align: center !important;
                }
            }

            .fc-toolbar-title {
                font-size: 24px !important;
                font-weight: 700 !important;
                color: var(--text-primary) !important;
            }

            @media (max-width: 768px) {
                .fc-toolbar-title {
                    font-size: 20px !important;
                }
            }

            .fc-button-primary {
                background-color: var(--primary) !important;
                border-color: var(--primary) !important;
                border-radius: var(--radius) !important;
                font-weight: 500 !important;
                font-size: 13px !important;
                padding: 8px 12px !important;
                transition: all 0.2s ease !important;
            }

            .fc-button-primary:hover {
                background-color: #374151 !important;
                border-color: #374151 !important;
            }

            .fc-button-primary:focus {
                box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2) !important;
            }

            .fc-button-primary:disabled {
                background-color: #9ca3af !important;
                border-color: #9ca3af !important;
            }

            .fc-col-header-cell {
                background-color: var(--light) !important;
                border-color: var(--border) !important;
                font-weight: 600 !important;
                color: var(--text-secondary) !important;
                text-transform: uppercase !important;
                font-size: 11px !important;
                letter-spacing: 0.5px !important;
                padding: 12px 8px !important;
            }

            .fc-daygrid-day {
                border-color: var(--border) !important;
            }

            .fc-daygrid-day:hover {
                background-color: var(--light) !important;
            }

            .fc-daygrid-day-number {
                color: var(--text-secondary) !important;
                font-weight: 600 !important;
                font-size: 14px !important;
                padding: 8px !important;
            }

            .fc-day-today {
                background-color: #f0f9ff !important;
            }

            .fc-day-today .fc-daygrid-day-number {
                background-color: var(--accent) !important;
                color: white !important;
                border-radius: 6px !important;
                width: 28px !important;
                height: 28px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            /* Event Styling */
            .fc-event {
                border: none !important;
                border-radius: 6px !important;
                padding: 4px 8px !important;
                margin: 1px !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                cursor: pointer !important;
                transition: all 0.15s ease !important;
            }

            .fc-event:hover {
                transform: translateY(-1px) !important;
                box-shadow: var(--shadow-md) !important;
            }

            .fc-event.regular-appointment {
                background-color: var(--accent) !important;
                color: white !important;
            }

            .fc-event.mayor-info-appointment {
                background-color: var(--success) !important;
                color: white !important;
            }

            .fc-event.mayor-attendance-appointment {
                background-color: var(--warning) !important;
                color: white !important;
            }

            .fc-event-title {
                font-weight: 500 !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }

            .fc-more-link {
                background-color: var(--light) !important;
                color: var(--text-secondary) !important;
                border-radius: 4px !important;
                padding: 2px 6px !important;
                font-size: 11px !important;
                font-weight: 500 !important;
                border: 1px solid var(--border) !important;
                margin-top: 2px !important;
            }

            .fc-more-link:hover {
                background-color: var(--border) !important;
            }

            /* Legend */
            .legend {
                background-color: var(--light);
                border-top: 1px solid var(--border);
                padding: 20px;
                display: flex;
                justify-content: center;
                gap: 32px;
                flex-wrap: wrap;
            }

            @media (max-width: 768px) {
                .legend {
                    padding: 16px;
                    gap: 16px;
                }
            }

            .legend-item {
                display: flex;
                align-items: center;
                font-size: 13px;
                font-weight: 500;
                color: var(--text-secondary);
            }

            .legend-dot {
                width: 12px;
                height: 12px;
                border-radius: 3px;
                margin-right: 8px;
            }

            .legend-dot.regular {
                background-color: var(--accent);
            }

            .legend-dot.mayor-info {
                background-color: var(--success);
            }

            .legend-dot.mayor-attendance {
                background-color: var(--warning);
            }

            /* Modal Styling */
            .modal-content {
                border: none;
                border-radius: 16px;
                box-shadow: var(--shadow-lg);
            }

            .modal-header {
                background-color: var(--primary);
                color: white;
                border-radius: 16px 16px 0 0;
                padding: 20px 24px;
                border: none;
            }

            .modal-title {
                font-size: 18px;
                font-weight: 600;
            }

            .modal-body {
                padding: 24px;
            }

            @media (max-width: 768px) {
                .modal-body {
                    padding: 20px;
                }
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
                margin-bottom: 24px;
            }

            @media (max-width: 768px) {
                .info-grid {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }
            }

            .info-item {
                background-color: var(--light);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 16px;
            }

            .info-label {
                font-size: 11px;
                font-weight: 600;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 4px;
            }

            .info-value {
                font-size: 14px;
                font-weight: 500;
                color: var(--text-primary);
            }

            .notes-section {
                background-color: var(--light);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 20px;
            }

            .form-label {
                font-size: 13px;
                font-weight: 600;
                color: var(--text-secondary);
                margin-bottom: 8px;
            }

            .form-control {
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 10px 12px;
                font-size: 14px;
                transition: border-color 0.15s ease;
                font-family: 'Inter', sans-serif;
            }

            .form-control:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            }

            .btn-primary {
                background-color: var(--primary);
                border-color: var(--primary);
                border-radius: var(--radius);
                padding: 8px 16px;
                font-weight: 500;
                font-size: 13px;
                transition: all 0.15s ease;
            }

            .btn-primary:hover {
                background-color: #374151;
                border-color: #374151;
            }

            .btn-secondary {
                background-color: var(--light);
                border-color: var(--border);
                color: var(--text-secondary);
                border-radius: var(--radius);
                padding: 8px 16px;
                font-weight: 500;
                font-size: 13px;
            }

            .btn-secondary:hover {
                background-color: var(--border);
                border-color: #d1d5db;
                color: var(--text-primary);
            }

            .success-alert {
                background-color: #d1fae5;
                border: 1px solid #a7f3d0;
                color: #065f46;
                border-radius: var(--radius);
                padding: 12px 16px;
                margin-bottom: 20px;
                font-size: 14px;
                display: flex;
                align-items: center;
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

            /* Responsive adjustments */
            @media (max-width: 576px) {
                .fc-toolbar-chunk {
                    display: flex;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .fc-button-group {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 4px;
                }

                .fc-button-primary {
                    font-size: 11px !important;
                    padding: 6px 8px !important;
                }

                .legend {
                    flex-direction: column;
                    align-items: center;
                }

                .stats-card {
                    padding: 0.75rem;
                }

                .stats-icon {
                    width: 45px;
                    height: 45px;
                    font-size: 1.1rem;
                    margin-right: 0.5rem;
                }

                .stats-number {
                    font-size: 1.125rem;
                }

                .stats-label {
                    font-size: 0.75rem;
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
                    <a href="schedule.php" class="active">
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
                                <h1 class="page-title">Schedule Calendar</h1>
                                <p class="text-muted mb-0 small d-none d-sm-block">View and manage appointment schedules</p>
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
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                        <div class="success-alert">
                            <i class="bi bi-check-circle me-2"></i>Note saved successfully!
                        </div>
                    <?php endif; ?>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon">
                                    <i class="bi bi-calendar-day"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="todayCount">0</div>
                                    <div class="stats-label">Today</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon" style="background: rgba(5, 150, 105, 0.1); color: var(--success);">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="weekCount">0</div>
                                    <div class="stats-label">This Week</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--accent);">
                                    <i class="bi bi-calendar-month"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="monthCount">0</div>
                                    <div class="stats-label">This Month</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon" style="background: rgba(220, 38, 38, 0.1); color: var(--danger);">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="mayorCount">0</div>
                                    <div class="stats-label">Mayor's</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="section-title mb-0">Appointment Calendar</h2>
                        </div>
                        <div class="card-body p-0">
                            <div id="calendar"></div>
                        </div>
                        
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-dot regular"></div>
                                <span>Regular Appointments</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot mayor-info"></div>
                                <span>Mayor's Appointments (For Info)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot mayor-attendance"></div>
                                <span>Mayor's Appointments (For Attendance)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="schedule.php">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Appointment Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="sched_id" id="schedId">
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Name</div>
                                    <div class="info-value" id="modalName"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Purpose</div>
                                    <div class="info-value" id="modalPurpose"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Attendees</div>
                                    <div class="info-value" id="modalAttendees"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date</div>
                                    <div class="info-value" id="modalDate"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Time</div>
                                    <div class="info-value" id="modalTime"></div>
                                </div>
                                <div class="info-item" id="appointmentTypeItem" style="display: none;">
                                    <div class="info-label">Appointment Type</div>
                                    <div class="info-value" id="modalAppointmentType"></div>
                                </div>
                                <div class="info-item" id="photographerItem" style="display: none;">
                                    <div class="info-label">With Photographer</div>
                                    <div class="info-value" id="modalPhotographer"></div>
                                </div>
                            </div>
                            
                            <div class="notes-section">
                                <label for="note" class="form-label">Admin Notes</label>
                                <textarea name="note" class="form-control" id="note" rows="4" 
                                        placeholder="Add notes about this appointment..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="save_note">Save Note</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
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

                // Escape key to close sidebar
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });

                const calendarEl = document.getElementById('calendar');
                const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                
                // Your actual PHP data
                const appointments = <?= json_encode($appointments) ?>;

                // Calculate statistics
                const today = new Date().toISOString().split('T')[0];
                const thisWeekStart = new Date();
                thisWeekStart.setDate(thisWeekStart.getDate() - thisWeekStart.getDay());
                const thisMonthStart = new Date();
                thisMonthStart.setDate(1);

                let todayCount = 0;
                let weekCount = 0;
                let monthCount = 0;
                let mayorCount = 0;

                appointments.forEach(appt => {
                    if (appt.date === today) todayCount++;
                    if (new Date(appt.date) >= thisWeekStart) weekCount++;
                    if (new Date(appt.date) >= thisMonthStart) monthCount++;
                    if (appt.appointment_type === 'mayor' || appt.is_mayor_appointment) mayorCount++;
                });

                document.getElementById('todayCount').textContent = todayCount;
                document.getElementById('weekCount').textContent = weekCount;
                document.getElementById('monthCount').textContent = monthCount;
                document.getElementById('mayorCount').textContent = mayorCount;

                const events = appointments.map(appt => {
                    // Format time properly (9:00 AM instead of 9:0)
                    const timeParts = appt.time.split(':');
                    let hours = parseInt(timeParts[0]);
                    const minutes = timeParts[1] || '00';
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    const formattedTime = hours + ':' + (minutes.length === 1 ? '0' + minutes : minutes) + ' ' + ampm;
                    
                    // Format title
                    let title;
                    if (appt.is_mayor_appointment || appt.appointment_type === 'mayor') {
                        title = formattedTime + ' MAYOR - ' + appt.purpose;
                    } else {
                        title = formattedTime + ' ' + appt.resident_name + ' - ' + appt.purpose;
                    }
                    
                    return {
                        id: appt.sched_id,
                        title: title,
                        start: appt.date + 'T' + appt.time,
                        extendedProps: {
                            name: appt.resident_name,
                            purpose: appt.purpose,
                            attendees: appt.attendees,
                            time: appt.time,
                            date: appt.date,
                            note: appt.note,
                            isMayorAppointment: appt.is_mayor_appointment || 0,
                            type: appt.appointment_type,
                            mayorAppointmentType: appt.mayor_appointment_type,
                            mayorPhotographer: appt.mayor_photographer
                        },
                        className: (appt.appointment_type === 'mayor' || appt.is_mayor_appointment) ? 
                            (appt.mayor_appointment_type === 'For Info' ? 'mayor-info-appointment' : 'mayor-attendance-appointment') : 'regular-appointment',
                        display: 'block',
                        overlap: false
                    };
                });

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: window.innerWidth < 768 ? 'timeGridDay' : 'dayGridMonth',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: window.innerWidth < 768 ? 
                            'dayGridMonth,timeGridDay' : 
                            'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: events,
                    eventDisplay: 'block',
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: 'short',
                        hour12: true
                    },
                    dayMaxEvents: 4,
                    moreLinkText: function(num) {
                        return `+${num} appointments`;
                    },
                    eventClick: function (info) {
                        const props = info.event.extendedProps;
                        document.getElementById('schedId').value = info.event.id;
                        document.getElementById('modalName').textContent = props.name;
                        document.getElementById('modalPurpose').textContent = props.purpose;
                        document.getElementById('modalAttendees').textContent = props.attendees;
                        
                        const dateObj = new Date(props.date);
                        const formattedDate = dateObj.toLocaleDateString('en-US', { 
                            month: 'long', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                        
                        const timeParts = props.time.split(':');
                        let hours = parseInt(timeParts[0]);
                        const minutes = timeParts[1] || '00';
                        const ampm = hours >= 12 ? 'PM' : 'AM';
                        hours = hours % 12;
                        hours = hours ? hours : 12;
                        const formattedTime = hours + ':' + (minutes.length === 1 ? '0' + minutes : minutes) + ' ' + ampm;
                        
                        document.getElementById('modalTime').textContent = formattedTime;
                        document.getElementById('modalDate').textContent = formattedDate;
                        
                        // Show existing admin notes if they exist, otherwise show empty field
                        document.getElementById('note').value = props.note || '';
                        
                        // Show/hide mayor appointment specific fields
                        const appointmentTypeItem = document.getElementById('appointmentTypeItem');
                        const photographerItem = document.getElementById('photographerItem');
                        
                        if (props.isMayorAppointment || props.type === 'mayor') {
                            // Show mayor appointment fields
                            appointmentTypeItem.style.display = 'block';
                            photographerItem.style.display = 'block';
                            
                            // Populate the fields with mayor appointment data
                            document.getElementById('modalAppointmentType').textContent = props.mayorAppointmentType || 'Not specified';
                            document.getElementById('modalPhotographer').textContent = props.mayorPhotographer || 'Not specified';
                        } else {
                            // Hide mayor appointment fields for regular appointments
                            appointmentTypeItem.style.display = 'none';
                            photographerItem.style.display = 'none';
                        }
                        
                        modal.show();
                    },
                    eventContent: function(arg) {
                        return {
                            html: `<div class="fc-event-title">${arg.event.title}</div>`
                        };
                    },
                    windowResize: function() {
                        if (window.innerWidth < 768) {
                            calendar.changeView('timeGridDay');
                        } else {
                            calendar.changeView('dayGridMonth');
                        }
                    },
                    eventOrder: 'start'
                });

                calendar.render();

                // Add click effects to stats cards
                document.querySelectorAll('.stats-card').forEach(card => {
                    card.addEventListener('click', function(e) {
                        e.preventDefault();
                        this.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 150);
                    });
                });

                // Handle form submission with loading state
                const form = document.querySelector('form[method="POST"]');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            const originalText = submitBtn.innerHTML;
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
                            
                            // Re-enable button after 3 seconds as fallback
                            setTimeout(() => {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }, 3000);
                        }
                    });
                }

                // Initialize tooltips if Bootstrap is available
                if (typeof bootstrap !== 'undefined') {
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }

                console.log('Modern schedule page initialized successfully');
            });
        </script>
    </body>
    </html>