<?php
session_start();
require_once 'config.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_auth_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and is mayor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mayor') {
    header("Location: login.php");
    exit();
}

// Get today's date and selected date range
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Get selected date range (default to next 3 weeks)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $today;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+3 weeks'));

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
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$scheduled_appointments = $result->fetch_all(MYSQLI_ASSOC);

// Get recent updates (cancellations, reschedules) from the last 7 days
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.purpose,
        a.date,
        a.time,
        a.status_enum,
        a.updated_at,
        u.name
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND a.status_enum IN ('cancelled', 'rescheduled')
    ORDER BY a.updated_at DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
$recent_updates = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics from schedule table
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_today,
        SUM(CASE WHEN a.status_enum = 'approved' THEN 1 ELSE 0 END) as approved_today,
        SUM(CASE WHEN a.status_enum = 'completed' THEN 1 ELSE 0 END) as completed_today,
        SUM(CASE WHEN a.status_enum = 'cancelled' THEN 1 ELSE 0 END) as cancelled_today,
        SUM(CASE WHEN a.status_enum = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_today
    FROM schedule s
    JOIN appointments a ON s.app_id = a.id
    WHERE a.date = ?
");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

// Get mayor's own appointments for selected date range
$stmt = $conn->prepare("
    SELECT 
        id,
        appointment_title,
        description,
        time,
        date,
        place
    FROM mayors_appointment 
    WHERE date BETWEEN ? AND ?
    ORDER BY date ASC, time ASC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$mayor_appointments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mayor Dashboard - Barangay Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1e293b;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary-color);
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .mayor-appointment-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .mayor-appointment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .mayor-appointment-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .mayor-appointment-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
        }

        .mayor-appointment-time {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .main-content, .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .appointment-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .appointment-id {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-approved {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(5, 150, 105, 0.3);
        }

        .status-completed {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(37, 99, 235, 0.3);
        }

        .status-cancelled {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .status-rescheduled {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-label {
            font-weight: 500;
            color: var(--secondary-color);
            min-width: 80px;
        }

        .detail-value {
            color: #1e293b;
        }

        .admin-notes-section {
            background: rgba(255, 248, 220, 0.8);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .notes-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #856404;
        }

        .notes-label {
            font-size: 0.9rem;
        }

        .notes-content {
            color: #664d03;
            font-size: 0.9rem;
            line-height: 1.4;
            white-space: pre-line;
        }

        .update-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--warning-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .update-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .update-time {
            font-size: 0.8rem;
            color: var(--secondary-color);
        }

        .update-details {
            font-size: 0.9rem;
            color: #1e293b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .logout-btn {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: rgba(220, 38, 38, 0.9);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .logout-btn:hover {
            background: rgba(220, 38, 38, 1);
            color: white;
            transform: translateY(-2px);
        }

        .date-range-selector {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .date-range-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .date-input-group label {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .date-input-group input {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .quick-date-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .quick-date-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-date-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .quick-date-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .schedule-date-header {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.3);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <a href="logout.php" class="logout-btn">
        <i class="bi bi-box-arrow-right me-2"></i>Logout
    </a>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <h1><i class="bi bi-building me-3"></i>Mayor Dashboard</h1>
            <p>Today's Schedule & Recent Updates</p>
            <div class="mt-3">
                <span class="text-muted">Date: <?= date('F j, Y') ?></span>
                <span class="text-muted ms-3">Time: <span id="current-time"><?= date('g:i A') ?></span></span>
            </div>
        </div>

        <!-- Date Range Selector -->
        <div class="date-range-selector">
            <h3 class="mb-3"><i class="bi bi-calendar-range me-2"></i>Schedule Date Range</h3>
            <form method="GET" class="date-range-form">
                <div class="date-input-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" required>
                </div>
                <div class="date-input-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" required>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                    <i class="bi bi-search me-2"></i>View Schedule
                </button>
            </form>
            <div class="quick-date-buttons mt-3">
                <button type="button" class="quick-date-btn" onclick="setDateRange('<?= $today ?>', '<?= date('Y-m-d', strtotime('+1 week')) ?>')">This Week</button>
                <button type="button" class="quick-date-btn" onclick="setDateRange('<?= $today ?>', '<?= date('Y-m-d', strtotime('+2 weeks')) ?>')">2 Weeks</button>
                <button type="button" class="quick-date-btn" onclick="setDateRange('<?= $today ?>', '<?= date('Y-m-d', strtotime('+3 weeks')) ?>')">3 Weeks</button>
                <button type="button" class="quick-date-btn" onclick="setDateRange('<?= date('Y-m-d', strtotime('monday this week')) ?>', '<?= date('Y-m-d', strtotime('sunday this week')) ?>')">This Week (Mon-Sun)</button>
                <button type="button" class="quick-date-btn" onclick="setDateRange('<?= date('Y-m-d', strtotime('monday next week')) ?>', '<?= date('Y-m-d', strtotime('sunday next week')) ?>')">Next Week</button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary-color);">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-number"><?= $stats['total_today'] ?? 0 ?></div>
                <div class="stat-label">Total Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success-color);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-number"><?= $stats['approved_today'] ?? 0 ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--info-color);">
                    <i class="bi bi-check2-all"></i>
                </div>
                <div class="stat-number"><?= $stats['completed_today'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--danger-color);">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-number"><?= $stats['cancelled_today'] ?? 0 ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning-color);">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div class="stat-number"><?= $stats['rescheduled_today'] ?? 0 ?></div>
                <div class="stat-label">Rescheduled</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Scheduled Appointments -->
            <div class="main-content">
                <h2 class="section-title">
                    <i class="bi bi-calendar-event"></i>
                    Scheduled Appointments (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)
                </h2>
                
                <?php if (count($scheduled_appointments) > 0): ?>
                    <?php 
                    $current_date = '';
                    foreach ($scheduled_appointments as $appointment): 
                        $appointment_date = $appointment['date'];
                        if ($appointment_date !== $current_date):
                            $current_date = $appointment_date;
                    ?>
                        <div class="schedule-date-header">
                            <i class="bi bi-calendar-date me-2"></i>
                            <?= date('l, F j, Y', strtotime($appointment_date)) ?>
                        </div>
                    <?php endif; ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <span class="appointment-id">#APP-<?= $appointment['id'] ?></span>
                                <span class="status-badge status-<?= $appointment['status_enum'] ?>">
                                    <?= ucfirst($appointment['status_enum']) ?>
                                </span>
                            </div>
                            
                                                         <div class="appointment-details">
                                                                   <div class="detail-item">
                                      <span class="detail-label">Purpose:</span>
                                      <span class="detail-value"><?= $appointment['purpose'] ?></span>
                                  </div>
                                 <div class="detail-item">
                                     <span class="detail-label">Time:</span>
                                     <span class="detail-value"><?= date('g:i A', strtotime($appointment['time'])) ?></span>
                                 </div>
                                 <div class="detail-item">
                                     <span class="detail-label">Resident:</span>
                                     <span class="detail-value"><?= $appointment['name'] ?></span>
                                 </div>
                                 <div class="detail-item">
                                     <span class="detail-label">Contact:</span>
                                     <span class="detail-value"><?= $appointment['phone'] ?></span>
                                 </div>
                             </div>
                             
                                                           <?php if (!empty($appointment['admin_notes'])): ?>
                                  <div class="admin-notes-section">
                                      <div class="notes-header">
                                          <i class="bi bi-sticky me-2"></i>
                                          <span class="notes-label">Admin Notes</span>
                                      </div>
                                      <div class="notes-content">
                                          <?= nl2br(htmlspecialchars($appointment['admin_notes'])) ?>
                                      </div>
                                  </div>
                              <?php endif; ?>
                              
                              <?php if (!empty($appointment['schedule_note'])): ?>
                                  <div class="admin-notes-section" style="background: rgba(220, 248, 255, 0.8); border-color: rgba(0, 123, 255, 0.3);">
                                      <div class="notes-header" style="color: #0056b3;">
                                          <i class="bi bi-calendar-event me-2"></i>
                                          <span class="notes-label">Schedule Notes</span>
                                      </div>
                                      <div class="notes-content" style="color: #004085;">
                                          <?= nl2br(htmlspecialchars($appointment['schedule_note'])) ?>
                                      </div>
                                  </div>
                              <?php endif; ?>
                            
                            <?php if ($appointment['status_enum'] === 'cancelled' || $appointment['status_enum'] === 'rescheduled'): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        Updated: <?= date('M j, g:i A', strtotime($appointment['updated_at'])) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <h4>No Scheduled Appointments</h4>
                        <p>There are no approved appointments scheduled for the selected date range.</p>
                    </div>
                <?php endif; ?>

                <!-- Mayor's Own Appointments -->
                <h2 class="section-title mt-4">
                    <i class="bi bi-person-badge"></i>
                    My Appointments (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)
                </h2>
                
                <?php if (count($mayor_appointments) > 0): ?>
                    <?php foreach ($mayor_appointments as $appointment): ?>
                        <div class="mayor-appointment-card">
                            <div class="mayor-appointment-header">
                                <span class="mayor-appointment-title"><?= htmlspecialchars($appointment['appointment_title']) ?></span>
                                <span class="mayor-appointment-time"><?= date('g:i A', strtotime($appointment['time'])) ?></span>
                            </div>
                            <div class="appointment-details">
                                <div class="detail-item">
                                    <span class="detail-label" style="color: rgba(255,255,255,0.8);">Place:</span>
                                    <span class="detail-value" style="color: white;"><?= htmlspecialchars($appointment['place']) ?></span>
                                </div>
                                <?php if (!empty($appointment['description'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label" style="color: rgba(255,255,255,0.8);">Description:</span>
                                        <span class="detail-value" style="color: white;"><?= htmlspecialchars($appointment['description']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-person-x"></i>
                        <h4>No Personal Appointments</h4>
                        <p>You have no personal appointments scheduled for today.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Updates -->
            <div class="sidebar">
                <h2 class="section-title">
                    <i class="bi bi-bell"></i>
                    Recent Updates
                </h2>
                
                <?php if (count($recent_updates) > 0): ?>
                    <?php foreach ($recent_updates as $update): ?>
                        <div class="update-item">
                            <div class="update-header">
                                <span class="status-badge status-<?= $update['status_enum'] ?>">
                                    <?= ucfirst($update['status_enum']) ?>
                                </span>
                                <span class="update-time">
                                    <?= date('M j, g:i A', strtotime($update['updated_at'])) ?>
                                </span>
                            </div>
                            <div class="update-details">
                                <strong>#APP-<?= $update['id'] ?></strong> - <?= $update['purpose'] ?><br>
                                <small class="text-muted">
                                    <?= $update['name'] ?> • 
                                    <?= date('M j', strtotime($update['date'])) ?> at <?= date('g:i A', strtotime($update['time'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-bell-slash"></i>
                        <h4>No Recent Updates</h4>
                        <p>No cancellations or reschedules in the last 7 days.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time every minute
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('current-time').textContent = timeString;
        }

        // Set date range for quick buttons
        function setDateRange(startDate, endDate) {
            document.getElementById('start_date').value = startDate;
            document.getElementById('end_date').value = endDate;
            document.querySelector('form').submit();
        }

        // Update time every minute
        setInterval(updateTime, 60000);
        
        // Initial update
        updateTime();
    </script>
</body>
</html> 