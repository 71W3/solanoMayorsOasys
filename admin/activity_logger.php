<?php
/**
 * Activity Logger for Admin and Frontdesk Actions
 * Tracks all activities for superadmin monitoring
 */

// Prevent function redeclaration
if (!function_exists('logActivity')) {
    // Function to log admin/frontdesk activities
    function logActivity($con, $user_id, $user_name, $user_role, $action_type, $action_description, $target_type = null, $target_id = null) {
        // Debug: Log function call
        error_log("DEBUG logActivity: user_id=$user_id, user_name=$user_name, user_role=$user_role, action_type=$action_type");
        
        try {
            // Create activity_log table if it doesn't exist
            $create_table_sql = "CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `user_name` varchar(255) NOT NULL,
                `user_role` varchar(50) NOT NULL,
                `action_type` varchar(100) NOT NULL,
                `action_description` text NOT NULL,
                `target_type` varchar(50) DEFAULT NULL,
                `target_id` int(11) DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_action_type` (`action_type`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_user_role` (`user_role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $con->query($create_table_sql);
            
            // Get IP address and user agent
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Insert activity log
            $stmt = $con->prepare("INSERT INTO activity_log (user_id, user_name, user_role, action_type, action_description, target_type, target_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssss", $user_id, $user_name, $user_role, $action_type, $action_description, $target_type, $target_id, $ip_address, $user_agent);
            $result = $stmt->execute();
            
            // Debug: Log SQL execution result
            if ($result) {
                error_log("DEBUG logActivity: Successfully inserted activity log");
            } else {
                error_log("DEBUG logActivity: Failed to insert activity log - " . $stmt->error);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
            return false;
        }
    }
}

// Function to log login activities
if (!function_exists('logLogin')) {
    function logLogin($con, $user_id, $user_name, $user_role) {
        return logActivity($con, $user_id, $user_name, $user_role, 'login', "{$user_role} logged in successfully", 'system', null);
    }
}

// Function to log logout activities
if (!function_exists('logLogout')) {
    function logLogout($con, $user_id, $user_name, $user_role) {
        return logActivity($con, $user_id, $user_name, $user_role, 'logout', "{$user_role} logged out", 'system', null);
    }
}

// Function to log appointment approval
if (!function_exists('logAppointmentApproval')) {
    function logAppointmentApproval($con, $user_id, $user_name, $user_role, $appointment_id, $resident_name, $purpose) {
        $description = "{$user_role} approved appointment #{$appointment_id} for {$resident_name} - {$purpose}";
        return logActivity($con, $user_id, $user_name, $user_role, 'appointment_approved', $description, 'appointment', $appointment_id);
    }
}

// Function to log appointment decline
if (!function_exists('logAppointmentDecline')) {
    function logAppointmentDecline($con, $user_id, $user_name, $user_role, $appointment_id, $resident_name, $purpose, $reason = '') {
        $description = "{$user_role} declined appointment #{$appointment_id} for {$resident_name} - {$purpose}";
        if ($reason) {
            $description .= " (Reason: {$reason})";
        }
        return logActivity($con, $user_id, $user_name, $user_role, 'appointment_declined', $description, 'appointment', $appointment_id);
    }
}

// Function to log appointment reschedule
if (!function_exists('logAppointmentReschedule')) {
    function logAppointmentReschedule($con, $user_id, $user_name, $user_role, $appointment_id, $resident_name, $purpose, $old_date, $new_date) {
        $description = "{$user_role} rescheduled appointment #{$appointment_id} for {$resident_name} - {$purpose} from {$old_date} to {$new_date}";
        return logActivity($con, $user_id, $user_name, $user_role, 'appointment_rescheduled', $description, 'appointment', $appointment_id);
    }
}

// Function to log appointment completion
if (!function_exists('logAppointmentCompletion')) {
    function logAppointmentCompletion($con, $user_id, $user_name, $user_role, $appointment_id, $resident_name, $purpose) {
        $description = "{$user_role} marked appointment #{$appointment_id} as completed for {$resident_name} - {$purpose}";
        return logActivity($con, $user_id, $user_name, $user_role, 'appointment_completed', $description, 'appointment', $appointment_id);
    }
}

// Function to log walk-in registration
if (!function_exists('logWalkInRegistration')) {
    function logWalkInRegistration($con, $user_id, $user_name, $user_role, $resident_name, $purpose) {
        $description = "{$user_role} registered walk-in for {$resident_name} - {$purpose}";
        return logActivity($con, $user_id, $user_name, $user_role, 'walk_in_registered', $description, 'walk_in', null);
    }
}

// Function to log queue management
if (!function_exists('logQueueManagement')) {
    function logQueueManagement($con, $user_id, $user_name, $user_role, $action, $details) {
        $description = "{$user_role} performed queue management: {$action} - {$details}";
        return logActivity($con, $user_id, $user_name, $user_role, 'queue_management', $description, 'queue', null);
    }
}

// Function to log schedule changes
if (!function_exists('logScheduleChange')) {
    function logScheduleChange($con, $user_id, $user_name, $user_role, $action, $details) {
        $description = "{$user_role} made schedule change: {$action} - {$details}";
        return logActivity($con, $user_id, $user_name, $user_role, 'schedule_change', $description, 'schedule', null);
    }
}

// Function to log announcement actions
if (!function_exists('logAnnouncementAction')) {
    function logAnnouncementAction($con, $user_id, $user_name, $user_role, $action, $announcement_id, $title) {
        $description = "{$user_role} {$action} announcement: {$title}";
        return logActivity($con, $user_id, $user_name, $user_role, 'announcement_' . $action, $description, 'announcement', $announcement_id);
    }
}

// Function to log user management actions
if (!function_exists('logUserManagement')) {
    function logUserManagement($con, $user_id, $user_name, $user_role, $action, $target_user_name, $details = '') {
        $description = "{$user_role} performed user {$action}: {$target_user_name}";
        if ($details) {
            $description .= " - {$details}";
        }
        return logActivity($con, $user_id, $user_name, $user_role, 'user_' . $action, $description, 'user', null);
    }
}

// Function to get recent activity for admin panel
if (!function_exists('getRecentActivity')) {
    function getRecentActivity($con, $limit = 20) {
        try {
            $stmt = $con->prepare("
                SELECT al.*, 
                       CASE 
                           WHEN al.action_type LIKE '%appointment%' THEN 'appointment'
                           WHEN al.action_type LIKE '%login%' OR al.action_type LIKE '%logout%' THEN 'system'
                           WHEN al.action_type LIKE '%walk_in%' THEN 'walk_in'
                           WHEN al.action_type LIKE '%queue%' THEN 'queue'
                           WHEN al.action_type LIKE '%schedule%' THEN 'schedule'
                           WHEN al.action_type LIKE '%announcement%' THEN 'announcement'
                           WHEN al.action_type LIKE '%user%' THEN 'user'
                           ELSE 'other'
                       END as category
                FROM activity_log al
                ORDER BY al.created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            return $stmt->get_result();
        } catch (Exception $e) {
            error_log("Error getting recent activity: " . $e->getMessage());
            return false;
        }
    }
}

// Function to get activity by user role
if (!function_exists('getActivityByRole')) {
    function getActivityByRole($con, $role, $limit = 20) {
        try {
            $stmt = $con->prepare("
                SELECT * FROM activity_log 
                WHERE user_role = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bind_param("si", $role, $limit);
            $stmt->execute();
            return $stmt->get_result();
        } catch (Exception $e) {
            error_log("Error getting activity by role: " . $e->getMessage());
            return false;
        }
    }
}

// Function to get activity by date range
if (!function_exists('getActivityByDateRange')) {
    function getActivityByDateRange($con, $start_date, $end_date, $limit = 50) {
        try {
            $stmt = $con->prepare("
                SELECT * FROM activity_log 
                WHERE DATE(created_at) BETWEEN ? AND ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bind_param("ssi", $start_date, $end_date, $limit);
            $stmt->execute();
            return $stmt->get_result();
        } catch (Exception $e) {
            error_log("Error getting activity by date range: " . $e->getMessage());
            return false;
        }
    }
}

// Function to get activity statistics
if (!function_exists('getActivityStats')) {
    function getActivityStats($con) {
        try {
            $stats = [];
            
            // Total activities
            $result = $con->query("SELECT COUNT(*) as total FROM activity_log");
            $stats['total'] = $result->fetch_assoc()['total'];
            
            // Activities by role
            $result = $con->query("SELECT user_role, COUNT(*) as count FROM activity_log GROUP BY user_role");
            $stats['by_role'] = [];
            while ($row = $result->fetch_assoc()) {
                $stats['by_role'][$row['user_role']] = $row['count'];
            }
            
            // Activities by type
            $result = $con->query("SELECT action_type, COUNT(*) as count FROM activity_log GROUP BY action_type ORDER BY count DESC LIMIT 10");
            $stats['by_type'] = [];
            while ($row = $result->fetch_assoc()) {
                $stats['by_type'][$row['action_type']] = $row['count'];
            }
            
            // Recent activity count (last 24 hours)
            $result = $con->query("SELECT COUNT(*) as recent FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['recent_24h'] = $result->fetch_assoc()['recent'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting activity stats: " . $e->getMessage());
            return false;
        }
    }
}
?>
