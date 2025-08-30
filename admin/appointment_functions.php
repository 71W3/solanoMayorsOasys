<?php
session_start();

// Function to get admin info from session or database
function getAdminInfo($con) {
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

    return ['name' => $admin_name, 'role' => $admin_role];
}

// Function to handle adding mayor's appointment
function addMayorAppointment($con, $appointment_title, $description, $place, $date, $time) {
    try {
        $con->begin_transaction();
        
        // Insert into mayors_appointment table
        $stmt = $con->prepare("INSERT INTO mayors_appointment 
            (appointment_title, description, place, date, time) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $appointment_title, $description, $place, $date, $time);
        $stmt->execute();
        $mayor_app_id = $con->insert_id;
        
        // Insert into schedule table - only set mayor_id, not app_id
        $stmt = $con->prepare("INSERT INTO schedule 
            (mayor_id, created_at, updated_at) 
            VALUES (?, NOW(), NOW())");
        $stmt->bind_param("i", $mayor_app_id);
        $stmt->execute();
        
        $con->commit();
        return ['success' => true, 'message' => "Mayor's appointment has been successfully added!"];
        
    } catch (Exception $e) {
        $con->rollback();
        return ['success' => false, 'message' => "Error: " . $e->getMessage()];
    }
}

// Function to handle appointment actions (approve, cancel, complete, reschedule)
function handleAppointmentAction($con, $app_id, $action, $additional_data = []) {
    try {
        // Start a transaction for atomicity
        $con->begin_transaction();

        if ($action == 'approve') {
            return handleAppointmentApproval($con, $app_id, $additional_data);
        } elseif ($action == 'cancel') {
            return handleAppointmentCancellation($con, $app_id, $additional_data);
        } elseif ($action == 'complete') {
            // Get admin information from session if available
            $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
            $admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_full_name'] ?? 'Unknown Admin';
            $admin_role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'admin';
            
            return handleAppointmentCompletion($con, $app_id, $admin_id, $admin_name, $admin_role);
        } elseif ($action == 'reschedule') {
            return handleAppointmentReschedule($con, $app_id, $additional_data);
        } else {
            throw new Exception("Invalid action specified");
        }

    } catch (Exception $e) {
        $con->rollback();
        return ['success' => false, 'message' => "Error: " . $e->getMessage()];
    }
}

// Function to handle appointment approval
function handleAppointmentApproval($con, $app_id, $additional_data) {
    // Get appointment details for email
    $appointment_query = "SELECT a.*, u.name as user_name, u.email as user_email 
                         FROM appointments a 
                         JOIN users u ON a.user_id = u.id 
                         WHERE a.id = $app_id";
    $appointment_result = $con->query($appointment_query);
    
    if (!$appointment_result || $appointment_result->num_rows === 0) {
        throw new Exception("Appointment not found");
    }
    
    $appointment = $appointment_result->fetch_assoc();
    $admin_message = trim($additional_data['admin_message'] ?? '');

    // Update status_enum to 'approved'
    $stmt = $con->prepare("UPDATE appointments SET status_enum = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();

    // Insert into schedule table
    $stmt = $con->prepare("INSERT INTO schedule (app_id, user_id, note, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $note = !empty($admin_message) ? 'Approved with message: ' . $admin_message : 'Approved by admin';
    $stmt->bind_param("iis", $appointment['id'], $appointment['user_id'], $note);
    $stmt->execute();

    // Send approval email
    include_once "../user/email_helper_phpmailer.php";
    
    $email_result = sendAppointmentApprovedEmail(
        $appointment['user_email'],
        $appointment['user_name'],
        $appointment['date'],
        $appointment['time'],
        $appointment['purpose'],
        $appointment['attendees'],
        $appointment['other_details'],
        $admin_message
    );

    $con->commit();

    if ($email_result['success']) {
        return [
            'success' => true, 
            'message' => "Appointment #$app_id has been approved and confirmation email sent to " . $appointment['user_email'] . "."
        ];
    } else {
        return [
            'success' => true, 
            'message' => "Appointment #$app_id has been approved, but email notification failed to send.",
            'warning' => true
        ];
    }
}

// Function to handle appointment cancellation
function handleAppointmentCancellation($con, $app_id, $additional_data) {
    $decline_reason = $additional_data['decline_reason'] ?? '';

    // Get appointment details for email
    $appointment_query = "SELECT a.*, u.name as user_name, u.email as user_email 
                         FROM appointments a 
                         JOIN users u ON a.user_id = u.id 
                         WHERE a.id = $app_id";
    $appointment_result = $con->query($appointment_query);
    $appointment = $appointment_result->fetch_assoc();

    // Update appointment status to 'declined'
    $stmt = $con->prepare("UPDATE appointments SET status_enum = 'declined', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();

    // Insert into decline_table
    $stmt = $con->prepare("INSERT INTO decline_table (reason, app_id) VALUES (?, ?)");
    $stmt->bind_param("si", $decline_reason, $app_id);
    $stmt->execute();

    // Send decline email
    include_once "../user/email_helper_phpmailer.php";
    
    $email_result = sendAppointmentDeclinedEmail(
        $appointment['user_email'],
        $appointment['user_name'],
        $appointment['date'],
        $appointment['time'],
        $appointment['purpose'],
        $decline_reason
    );

    $con->commit();

    if ($email_result['success']) {
        return [
            'success' => true, 
            'message' => "Appointment #$app_id has been declined and notification email sent to " . $appointment['user_email'] . "."
        ];
    } else {
        return [
            'success' => true, 
            'message' => "Appointment #$app_id has been declined, but email notification failed to send.",
            'warning' => true
        ];
    }
}

// Function to handle appointment completion
function handleAppointmentCompletion($con, $app_id, $admin_id = null, $admin_name = null, $admin_role = null) {
    // Get appointment details for logging
    $stmt = $con->prepare("SELECT purpose FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    
    // Update appointment status to 'completed'
    $stmt = $con->prepare("UPDATE appointments SET status_enum = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();

    // Remove from schedule table
    $stmt = $con->prepare("DELETE FROM schedule WHERE app_id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();

    // Log activity for superadmin monitoring if admin info is provided
    if ($admin_id && $admin_name && $admin_role) {
        include_once "activity_logger.php";
        logAppointmentCompletion(
            $con, 
            $admin_id, 
            $admin_name, 
            $admin_role, 
            $app_id, 
            'Resident', 
            $appointment['purpose']
        );
    }

    $con->commit();
    
    return [
        'success' => true, 
        'message' => "Appointment #$app_id has been marked as completed."
    ];
}

// Function to handle appointment reschedule
function handleAppointmentReschedule($con, $app_id, $additional_data) {
    $new_date = $additional_data['new_date'] ?? '';
    $new_time = $additional_data['new_time'] ?? '';
    $reschedule_reason = $additional_data['reschedule_reason'] ?? '';

    if (empty($new_date) || empty($new_time)) {
        throw new Exception("New date and time are required for rescheduling");
    }

    // Get appointment details for email
    $appointment_query = "SELECT a.*, u.name as user_name, u.email as user_email 
                         FROM appointments a 
                         JOIN users u ON a.user_id = u.id 
                         WHERE a.id = $app_id";
    $appointment_result = $con->query($appointment_query);
    
    if (!$appointment_result || $appointment_result->num_rows === 0) {
        throw new Exception("Appointment not found");
    }
    
    $appointment = $appointment_result->fetch_assoc();

    // Update appointment details
    $stmt = $con->prepare("UPDATE appointments SET date = ?, time = ?, status_enum = 'pending', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $new_date, $new_time, $app_id);
    $stmt->execute();

    // Remove from schedule table (since it's now pending again)
    $stmt = $con->prepare("DELETE FROM schedule WHERE app_id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();

    // Send reschedule email
    include_once "../user/email_helper_phpmailer.php";
    
    $email_result = sendAppointmentRescheduledEmail(
        $appointment['user_email'],
        $appointment['user_name'],
        $new_date,
        $new_time,
        $appointment['purpose'],
        $reschedule_reason
    );

    $con->commit();

    if ($email_result['success']) {
        return [
            'success' => true, 
            'message' => "Appointment #$app_id has been rescheduled and notification email sent to " . $appointment['user_email'] . "."
        ];
    } else {
        return [
            'success' => true, 
            'message' => "Appointment #$app_id has been rescheduled, but email notification failed to send.",
            'warning' => true
        ];
    }
}

// Function to get pending appointments
function getPendingAppointments($con) {
    $query = "SELECT a.*, u.name as resident_name, u.email as resident_email 
              FROM appointments a 
              JOIN users u ON a.user_id = u.id 
              WHERE a.status_enum = 'pending' 
              ORDER BY a.date ASC, a.time ASC";
    
    $result = $con->query($query);
    return $result;
}

// Function to get approved appointments
function getApprovedAppointments($con) {
    $query = "SELECT a.*, u.name as resident_name, u.email as resident_email 
              FROM appointments a 
              JOIN users u ON a.user_id = u.id 
              WHERE a.status_enum = 'approved' 
              ORDER BY a.date ASC, a.time ASC";
    
    $result = $con->query($query);
    return $result;
}

// Function to get completed appointments
function getCompletedAppointments($con) {
    $query = "SELECT a.*, u.name as resident_name, u.email as resident_email 
              FROM appointments a 
              JOIN users u ON a.user_id = u.id 
              WHERE a.status_enum = 'completed' 
              ORDER BY a.date ASC, a.time ASC";
    
    $result = $con->query($query);
    return $result;
}

// Function to get declined appointments
function getDeclinedAppointments($con) {
    $query = "SELECT a.*, u.name as resident_name, u.email as resident_email, dt.reason as decline_reason
              FROM appointments a 
              JOIN users u ON a.user_id = u.id 
              LEFT JOIN decline_table dt ON a.id = dt.app_id
              WHERE a.status_enum = 'declined' 
              ORDER BY a.date ASC, a.time ASC";
    
    $result = $con->query($query);
    return $result;
}

// Function to get mayor's appointments
function getMayorAppointments($con) {
    $query = "SELECT * FROM mayors_appointment WHERE date >= CURDATE() ORDER BY date ASC, time ASC";
    $result = $con->query($query);
    return $result;
}

// Function to format date for display
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Function to format time for display
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'approved':
            return 'status-approved';
        case 'completed':
            return 'status-completed';
        case 'cancelled':
        case 'declined':
            return 'status-cancelled';
        default:
            return 'bg-secondary';
    }
}

// Function to check if user has admin privileges
function hasAdminPrivileges() {
    return isset($_SESSION['admin_id']) || 
           (isset($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'mayor']));
}

// Function to validate admin session
function validateAdminSession() {
    if (!hasAdminPrivileges()) {
        header("Location: ../user/login.php");
        exit();
    }
}

// Function to get pending appointments with date status
function getPendingAppointmentsWithDateStatus($con) {
    $today = date('Y-m-d');
    $query = "
        SELECT 
            a.id AS appointment_id,
            u.name AS resident_name,
            a.date,
            a.time,
            a.purpose,
            a.attendees,
            a.other_details,
            a.attachments,
            a.status_enum AS status,
            CASE 
                WHEN a.date = '$today' THEN 'today'
                WHEN a.date < '$today' THEN 'past'
                ELSE 'future'
            END AS date_status
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.status_enum = 'pending'
        ORDER BY 
            CASE 
                WHEN a.date = '$today' THEN 0
                WHEN a.date < '$today' THEN 1
                ELSE 2
            END,
            a.date ASC, 
            a.time ASC
    ";
    
    $result = $con->query($query);
    if (!$result) {
        die("Error fetching appointments: " . $con->error);
    }
    return $result;
}


?>
