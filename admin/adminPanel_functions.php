<?php
// Improved time elapsed function with timezone consideration
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        if (empty($datetime)) {
            return 'Unknown time';
        }
        
        try {
            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
            $diff = $now->diff($ago);

            $diff->w = floor($diff->d / 7);
            $diff->d -= $diff->w * 7;

            $string = array(
                'y' => 'year',
                'm' => 'month',
                'w' => 'week',
                'd' => 'day',
                'h' => 'hour',
                'i' => 'minute',
                's' => 'second',
            );
            
            foreach ($string as $k => &$v) {
                if ($diff->$k) {
                    $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
                } else {
                    unset($string[$k]);
                }
            }

            if (!$full) $string = array_slice($string, 0, 1);
            return $string ? implode(', ', $string) . ' ago' : 'just now';
        } catch (Exception $e) {
            return 'Recently';
        }
    }
}

// Function to get dashboard statistics
function getDashboardStats($con) {
    $stats = [
        'totalAppointments' => 0,
        'completedAppointments' => 0,
        'pendingAppointments' => 0,
        'approvedAppointments' => 0,
        'registeredUsers' => 0,
        'todayAppointments' => null,
        'mayorsAppointments' => null,
        'recentActivity' => null,
        'dailyStats' => null,
        'weeklyStats' => null,
        'dailyChartData' => [],
        'weeklyChartData' => [],
        'statusChartData' => []
    ];

    try {
        // Fetch data for the stats cards with error handling
        $result = $con->query("SELECT COUNT(*) FROM appointments");
        if ($result) {
            $stats['totalAppointments'] = $result->fetch_row()[0];
        }

        $result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'completed'");
        if ($result) {
            $stats['completedAppointments'] = $result->fetch_row()[0];
        }

        $result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'pending'");
        if ($result) {
            $stats['pendingAppointments'] = $result->fetch_row()[0];
        }

        $result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'approved'");
        if ($result) {
            $stats['approvedAppointments'] = $result->fetch_row()[0];
        }

        $result = $con->query("SELECT COUNT(*) FROM users");
        if ($result) {
            $stats['registeredUsers'] = $result->fetch_row()[0];
        }

        // Fetch only today's APPROVED appointments
        $today = date('Y-m-d');
        $stats['todayAppointments'] = $con->query("
            SELECT a.id, u.name AS resident_name, a.purpose, a.time, a.date, a.status_enum as status
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            WHERE a.date = '{$today}' AND a.status_enum = 'approved'
            ORDER BY a.time ASC
        ");

        // Check if the query failed
        if (!$stats['todayAppointments']) {
            // Create an empty result object to prevent errors
            $stats['todayAppointments'] = new stdClass();
            $stats['todayAppointments']->num_rows = 0;
        }

        // Fetch upcoming mayor's appointments (check if table exists first)
        $tableCheck = $con->query("SHOW TABLES LIKE 'mayors_appointment'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stats['mayorsAppointments'] = $con->query("SELECT * FROM mayors_appointment WHERE date >= CURDATE() ORDER BY date ASC, time ASC LIMIT 5");
        } else {
            // Create empty result if table doesn't exist
            $stats['mayorsAppointments'] = new stdClass();
            $stats['mayorsAppointments']->num_rows = 0;
        }

        // Fetch recent activity with better time handling
        $stats['recentActivity'] = $con->query("
            SELECT a.purpose, u.name AS resident_name, a.status_enum as status, 
                   COALESCE(a.updated_at, a.created_at, NOW()) as last_updated
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            ORDER BY COALESCE(a.updated_at, a.created_at, NOW()) DESC
            LIMIT 5
        ");

        if (!$stats['recentActivity']) {
            $stats['recentActivity'] = new stdClass();
            $stats['recentActivity']->num_rows = 0;
        }

        // Fetch daily appointment statistics for charts (last 30 days)
        $stats['dailyStats'] = $con->query("
            SELECT 
                DATE(date) as day_date,
                DATE_FORMAT(date, '%M %d') as day_name,
                COUNT(*) as total,
                SUM(CASE WHEN status_enum = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status_enum = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status_enum = 'approved' THEN 1 ELSE 0 END) as approved
            FROM appointments 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(date), DATE_FORMAT(date, '%M %d')
            ORDER BY day_date ASC
        ");

        if ($stats['dailyStats'] && $stats['dailyStats']->num_rows > 0) {
            $dailyLabels = [];
            $dailyTotal = [];
            $dailyCompleted = [];
            $dailyPending = [];
            $dailyApproved = [];
            
            while ($row = $stats['dailyStats']->fetch_assoc()) {
                $dailyLabels[] = $row['day_name'];
                $dailyTotal[] = (int)$row['total'];
                $dailyCompleted[] = (int)$row['completed'];
                $dailyPending[] = (int)$row['pending'];
                $dailyApproved[] = (int)$row['approved'];
            }
            
            $stats['dailyChartData'] = [
                'labels' => $dailyLabels,
                'total' => $dailyTotal,
                'completed' => $dailyCompleted,
                'pending' => $dailyPending,
                'approved' => $dailyApproved
            ];
        } else {
            $stats['dailyChartData'] = [
                'labels' => [],
                'total' => [],
                'completed' => [],
                'pending' => [],
                'approved' => []
            ];
        }

        // Fetch weekly appointment statistics for charts
        $stats['weeklyStats'] = $con->query("
            SELECT 
                DAYNAME(date) as day_name,
                WEEKDAY(date) as day_order,
                COUNT(*) as count
            FROM appointments 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DAYNAME(date), WEEKDAY(date)
            ORDER BY day_order ASC
        ");

        if ($stats['weeklyStats'] && $stats['weeklyStats']->num_rows > 0) {
            $weeklyLabels = [];
            $weeklyCounts = [];
            
            while ($row = $stats['weeklyStats']->fetch_assoc()) {
                $weeklyLabels[] = $row['day_name'];
                $weeklyCounts[] = (int)$row['count'];
            }
            
            $stats['weeklyChartData'] = [
                'labels' => $weeklyLabels,
                'counts' => $weeklyCounts
            ];
        } else {
            $stats['weeklyChartData'] = [
                'labels' => [],
                'counts' => []
            ];
        }

        // Prepare status chart data
        $stats['statusChartData'] = [
            'labels' => ['Completed', 'Approved', 'Pending'],
            'data' => [$stats['completedAppointments'], $stats['approvedAppointments'], $stats['pendingAppointments']]
        ];

    } catch (Exception $e) {
        // Log error and set default values
        error_log("Dashboard query error: " . $e->getMessage());
        
        // Initialize with safe defaults
        $stats['totalAppointments'] = 0;
        $stats['completedAppointments'] = 0;
        $stats['pendingAppointments'] = 0;
        $stats['approvedAppointments'] = 0;
        $stats['registeredUsers'] = 0;
        
        // Create empty result objects
        $stats['todayAppointments'] = new stdClass();
        $stats['todayAppointments']->num_rows = 0;
        
        $stats['mayorsAppointments'] = new stdClass();
        $stats['mayorsAppointments']->num_rows = 0;
        
        $stats['recentActivity'] = new stdClass();
        $stats['recentActivity']->num_rows = 0;
        
        $stats['dailyChartData'] = ['labels' => [], 'total' => [], 'completed' => [], 'pending' => [], 'approved' => []];
        $stats['weeklyChartData'] = ['labels' => [], 'counts' => []];
        $stats['statusChartData'] = ['labels' => [], 'data' => []];
    }

    return $stats;
}

// Function to initialize admin panel with timezone, admin info, and dashboard stats
function initializeAdminPanel($con) {
    // Set timezone to match your location (Philippines)
    date_default_timezone_set('Asia/Manila');

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

    // Check if database connection exists
    if (!isset($con) || !$con) {
        die("Database connection failed. Please check your connection settings.");
    }

    // Get dashboard statistics using the function
    $dashboardStats = getDashboardStats($con);

    // Extract variables from the stats array
    $totalAppointments = $dashboardStats['totalAppointments'];
    $completedAppointments = $dashboardStats['completedAppointments'];
    $pendingAppointments = $dashboardStats['pendingAppointments'];
    $approvedAppointments = $dashboardStats['approvedAppointments'];
    $registeredUsers = $dashboardStats['registeredUsers'];
    $todayAppointments = $dashboardStats['todayAppointments'];
    $mayorsAppointments = $dashboardStats['mayorsAppointments'];
    $recentActivity = $dashboardStats['recentActivity'];
    $dailyStats = $dashboardStats['dailyStats'];
    $weeklyStats = $dashboardStats['weeklyStats'];
    $dailyChartData = $dashboardStats['dailyChartData'];
    $weeklyChartData = $dashboardStats['weeklyChartData'];
    $statusChartData = $dashboardStats['statusChartData'];

    // Return all the initialized data
    return [
        'admin_name' => $admin_name,
        'admin_role' => $admin_role,
        'totalAppointments' => $totalAppointments,
        'completedAppointments' => $completedAppointments,
        'pendingAppointments' => $pendingAppointments,
        'approvedAppointments' => $approvedAppointments,
        'registeredUsers' => $registeredUsers,
        'todayAppointments' => $todayAppointments,
        'mayorsAppointments' => $mayorsAppointments,
        'recentActivity' => $recentActivity,
        'dailyStats' => $dailyStats,
        'weeklyStats' => $weeklyStats,
        'dailyChartData' => $dailyChartData,
        'weeklyChartData' => $weeklyChartData,
        'statusChartData' => $statusChartData
    ];
}
?>
