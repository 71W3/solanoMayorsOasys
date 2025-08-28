<?php
include 'conn.php';

// Get today's date
$today = date('Y-m-d');

// Get selected date range (default to next 3 weeks)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $today;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+3 weeks'));

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
    <title>My Schedule - Mayor Dashboard</title>
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
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-person-badge"></i>
                        My Personal Schedule (<?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?>)
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Date Filters -->
                    <form method="GET" class="date-filters">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                            Apply Filter
                        </button>
                    </form>

                    <div class="quick-filters">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('today')">Today</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('week')">This Week</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('month')">This Month</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('next-week')">Next Week</button>
                    </div>

                    <!-- My Schedule List -->
                    <div id="scheduleList">
                        <?php if (count($mayor_appointments) > 0): ?>
                            <?php 
                            $current_date = '';
                            foreach ($mayor_appointments as $appointment): 
                                $appointment_date = $appointment['date'];
                                if ($appointment_date !== $current_date):
                                    $current_date = $appointment_date;
                            ?>
                                <div class="schedule-date-header">
                                    <i class="bi bi-calendar-date me-2"></i>
                                    <?= date('l, F j, Y', strtotime($appointment_date)) ?>
                                </div>
                            <?php endif; ?>
                                <div class="mayor-appointment">
                                    <div class="mayor-appointment-title">
                                        <?= htmlspecialchars($appointment['appointment_title']) ?>
                                        <span style="float: right; font-size: 0.9rem; opacity: 0.9;">
                                            <?= date('g:i A', strtotime($appointment['time'])) ?>
                                        </span>
                                    </div>
                                    <div class="mayor-appointment-details">
                                        <div class="detail-item">
                                            <span class="detail-label" style="color: rgba(255,255,255,0.8);">Place</span>
                                            <span class="detail-value" style="color: white;"><?= htmlspecialchars($appointment['place']) ?></span>
                                        </div>
                                        <?php if (!empty($appointment['description'])): ?>
                                            <div class="detail-item">
                                                <span class="detail-label" style="color: rgba(255,255,255,0.8);">Description</span>
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
                                <p>You have no personal appointments scheduled for the selected date range.</p>
                                <a href="add-appointment.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-plus"></i>
                                    Add Appointment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="dashboard-scripts.js"></script>
    <script>
        function setDateRange(range) {
            const today = new Date();
            let startDate, endDate;

            switch (range) {
                case 'today':
                    startDate = endDate = today.toISOString().split('T')[0];
                    break;
                case 'week':
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekStart.getDate() + 6);
                    startDate = weekStart.toISOString().split('T')[0];
                    endDate = weekEnd.toISOString().split('T')[0];
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
                    break;
                case 'next-week':
                    const nextWeekStart = new Date(today);
                    nextWeekStart.setDate(today.getDate() + (7 - today.getDay()));
                    const nextWeekEnd = new Date(nextWeekStart);
                    nextWeekEnd.setDate(nextWeekStart.getDate() + 6);
                    startDate = nextWeekStart.toISOString().split('T')[0];
                    endDate = nextWeekEnd.toISOString().split('T')[0];
                    break;
                default:
                    return;
            }

            // Redirect to same page with new parameters
            window.location.href = `my-schedule.php?start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>