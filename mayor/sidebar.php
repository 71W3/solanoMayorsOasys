<?php
// Get current page to highlight active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="mayorDashboard.php" class="sidebar-brand">
            <i class="bi bi-building"></i>
            Mayor Dashboard
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="mayorDashboard.php" class="nav-link <?= $current_page === 'mayorDashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="mayorsAppointment.php" class="nav-link <?= $current_page === 'mayorsAppointment.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                Appointments
            </a>
        </div>
        <div class="nav-item">
            <a href="mayorsCalendar.php" class="nav-link <?= $current_page === 'mayorsCalendar.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-week"></i>
                Calendar View
            </a>
        </div>
        <div class="nav-item">
            <a href="mayorsSchedule.php" class="nav-link <?= $current_page === 'mayorsSchedule.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                My Schedule
            </a>
        </div>
    </nav>
</div>