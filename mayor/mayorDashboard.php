<?php
include 'conn.php';

// Get today's date
$today = date('Y-m-d');

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mayor Dashboard - Barangay Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="dashboard-styles.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <?php include 'topbar.php'; ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Today</span>
                        <div class="stat-icon" style="background: var(--primary);">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['total_today'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Approved</span>
                        <div class="stat-icon" style="background: var(--success);">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['approved_today'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Completed</span>
                        <div class="stat-icon" style="background: var(--info);">
                            <i class="bi bi-check2-all"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['completed_today'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Cancelled</span>
                        <div class="stat-icon" style="background: var(--danger);">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['cancelled_today'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Rescheduled</span>
                        <div class="stat-icon" style="background: var(--warning);">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['rescheduled_today'] ?? 0 ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-lightning"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="mayorsAppointment.php" class="btn btn-primary w-100">
                                <i class="bi bi-calendar-check"></i>
                                View Appointments
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="mayorsCalendar.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-calendar-week"></i>
                                Calendar View
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="mayorsSchedule.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-person-badge"></i>
                                My Schedule
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="reports.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-graph-up"></i>
                                Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Updates -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-bell"></i>
                        Recent Updates (Last 7 Days)
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (count($recent_updates) > 0): ?>
                        <?php foreach ($recent_updates as $update): ?>
                            <div class="appointment-item">
                                <div class="appointment-header">
                                    <span class="appointment-id">#APP-<?= $update['id'] ?></span>
                                    <span class="status-badge status-<?= $update['status_enum'] ?>">
                                        <?= ucfirst($update['status_enum']) ?>
                                    </span>
                                </div>
                                <div class="appointment-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Purpose</span>
                                        <span class="detail-value"><?= htmlspecialchars($update['purpose']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Resident</span>
                                        <span class="detail-value"><?= htmlspecialchars($update['name']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Date</span>
                                        <span class="detail-value"><?= date('M j, Y', strtotime($update['date'])) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Time</span>
                                        <span class="detail-value"><?= date('g:i A', strtotime($update['time'])) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Updated</span>
                                        <span class="detail-value"><?= date('M j, g:i A', strtotime($update['updated_at'])) ?></span>
                                    </div>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="dashboard-scripts.js"></script>
</body>
</html>