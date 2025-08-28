<div class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="topbar-title">
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            switch($current_page) {
                case 'dashboard.php':
                    echo 'Mayor Dashboard';
                    break;
                case 'appointments.php':
                    echo 'Appointments';
                    break;
                case 'calendar.php':
                    echo 'Calendar View';
                    break;
                case 'my-schedule.php':
                    echo 'My Schedule';
                    break;
                default:
                    echo 'Mayor Dashboard';
            }
            ?>
        </h1>
    </div>
    <div class="topbar-right">
        <?php
        date_default_timezone_set('Asia/Manila');
        ?>
        <div class="datetime-display">
            <span id="currentDate"><?= date('F j, Y') ?></span>
            <span id="currentTime"><?= date('g:i A') ?></span>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>
</div>