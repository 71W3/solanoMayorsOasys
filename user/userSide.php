<?php
session_start();

// Include email helper functions
require_once 'email_helper_phpmailer.php'; // For production with PHPMailer
// require_once 'email_helper_simple.php'; // For testing - simulates email sending
// require_once 'email_helper.php'; // For basic mail() function
// require_once 'email_helper_gmail.php'; // For Gmail SMTP

// Check for appointment status notifications
$status_notifications = [];
if (isset($_SESSION['user_id'])) {
    $conn = new mysqli("localhost", "root", "", "my_auth_db");
    if (!$conn->connect_error) {
        // First, ensure the dismissed_notifications table exists   
        $create_table_sql = "CREATE TABLE IF NOT EXISTS dismissed_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            appointment_id INT NOT NULL,
            dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   
            UNIQUE KEY unique_dismissal (user_id, appointment_id)
        )";
        $conn->query($create_table_sql);
        
        // Check for recent status changes (last 24 hours) that haven't been dismissed
        $user_id = $_SESSION['user_id'];
        
        // Debug: Check if dismissed_notifications table has data
        $debug_sql = "SELECT COUNT(*) as dismissed_count FROM dismissed_notifications WHERE user_id = ?";
        $debug_stmt = $conn->prepare($debug_sql);
        if ($debug_stmt) {
            $debug_stmt->bind_param("i", $user_id);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            $debug_row = $debug_result->fetch_assoc();
            error_log("User $user_id has " . $debug_row['dismissed_count'] . " dismissed notifications");
            $debug_stmt->close();
        }
        
        $sql = "SELECT a.id, a.purpose, a.date, a.time, a.status_enum, a.updated_at 
                FROM appointments a
                LEFT JOIN dismissed_notifications dn ON a.id = dn.appointment_id AND dn.user_id = ?
                WHERE a.user_id = ? 
                AND a.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND a.status_enum IN ('approved', 'cancelled', 'declined', 'rescheduled','completed')
                AND dn.id IS NULL
                ORDER BY a.updated_at DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $status_notifications[] = $row;
            }
            $stmt->close();
        } else {
            // Fallback query if the JOIN fails
            $fallback_sql = "SELECT id, purpose, date, time, status_enum, updated_at 
                            FROM appointments 
                            WHERE user_id = ? 
                            AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            AND status_enum IN ('approved', 'cancelled', 'declined', 'rescheduled')
                            ORDER BY updated_at DESC";
            $stmt = $conn->prepare($fallback_sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $status_notifications[] = $row;
                }
                $stmt->close();
            }
        }
        
        // Debug: Log notification count
        error_log("Found " . count($status_notifications) . " notifications for user $user_id");
        foreach ($status_notifications as $notif) {
            error_log("Notification ID: " . $notif['id'] . ", Status: " . $notif['status_enum']);
        }
        
        // Add debug output to page
        if (count($status_notifications) > 0) {
            echo "<!-- DEBUG: Found " . count($status_notifications) . " notifications -->";
            echo "<script>console.log('PHP DEBUG: Found " . count($status_notifications) . " notifications');</script>";
            foreach ($status_notifications as $notif) {
                echo "<!-- DEBUG: Notification ID: " . $notif['id'] . ", Status: " . $notif['status_enum'] . " -->";
                echo "<script>console.log('PHP DEBUG: Notification ID: " . $notif['id'] . ", Status: " . $notif['status_enum'] . "');</script>";
            }
        } else {
            echo "<!-- DEBUG: No notifications found -->";
            echo "<script>console.log('PHP DEBUG: No notifications found');</script>";
        }
        
        $conn->close();
    }
}

// Check for login success message
$show_login_success = false;
$user_full_name = '';
if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
    $show_login_success = true;
    $user_full_name = isset($_SESSION['user_full_name']) ? $_SESSION['user_full_name'] : '';
    // Clear the session variable so it doesn't show again on page refresh
    unset($_SESSION['login_success']);
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

if (!empty($username)) {
    include 'kon.php'; // Ensure this includes $conn

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $row['id'];
    } else {
        // User not found, handle accordingly
        echo "User not found.";
    }
} else {
    // No username in session, redirect to login
    header('Location: login.php');
    exit();
}

// Database connection
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_auth_db";
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle appointment form submission
$appointment_success = false;
$appointment_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_appointment'])) {
    // Get and sanitize form data
    $selected_date = $conn->real_escape_string($_POST['selected_date']);
    $selected_time = $conn->real_escape_string($_POST['selected_time']);
    // Convert selected_time (e.g., '1:30 PM') to 24-hour format for MySQL
    $selected_time_24 = date('H:i:s', strtotime($selected_time));
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $attendees = intval($_POST['attendees']);
    $other_details = $conn->real_escape_string($_POST['other_details']);
    $user = $conn->real_escape_string($username);

    // Get user_id from session
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    // Get service_id from POST
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

    // Check if slot is already pending
    $check_sql = "SELECT id FROM appointments 
                  WHERE date = '$selected_date' 
                  AND time = '$selected_time_24' 
                  AND status_enum = 'pending'";
    
    $check_result = $conn->query($check_sql);
    if ($check_result && $check_result->num_rows > 0) {
        $appointment_error = "This time slot is already pending. Please choose another slot.";
    } else {
        // Handle file uploads
        $attachments = [];
        $file_errors = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                $file_name = basename($_FILES['attachments']['name'][$key]);
                $file_size = $_FILES['attachments']['size'][$key];
                $file_tmp = $_FILES['attachments']['tmp_name'][$key];
                $file_type = $_FILES['attachments']['type'][$key];
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                                 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($file_type, $allowed_types)) {
                    if ($file_size < 5000000) { // 5MB max
                        $new_file_name = uniqid() . '.' . $file_ext;
                        $destination = $uploadDir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $attachments[] = $new_file_name;
                        } else {
                            $file_errors[] = "Failed to upload: $file_name";
                        }
                    } else {
                        $file_errors[] = "File too large: $file_name (max 5MB allowed)";
                    }
                } else {
                    $file_errors[] = "Invalid file type: $file_name (only PDF, Word docs, and images allowed)";
                }
            }
        }
        
        $attachments_str = implode(',', $attachments);

        // Handle file upload errors
        if (!empty($file_errors)) {
            $appointment_error = "File upload errors:\n" . implode("\n", $file_errors);
        } else {
            // Insert into appointments table
            $sql = "INSERT INTO appointments (user_id, service_id, date, time, purpose, attendees, other_details, attachments, status_enum, created_at)
                    VALUES ($user_id, $service_id, '$selected_date', '$selected_time_24', '$purpose', $attendees, '$other_details', '$attachments_str', 'pending', NOW())";
            
            if ($conn->query($sql)) {
            // Get user email and name for email notification
            $user_email = getUserEmail($conn, $user_id);
            $user_name = getUserFullName($conn, $user_id);
            
            // If we have user email, send confirmation email
            if ($user_email && $user_name) {
                // Convert time back to 12-hour format for email
                $appointment_time_12hr = date('g:i A', strtotime($selected_time_24));
                
                // Send email notification
                $email_result = sendAppointmentConfirmationEmail(
                    $user_email,
                    $user_name,
                    $selected_date,
                    $appointment_time_12hr,
                    $purpose,
                    $attendees,
                    $other_details
                );
                
                // Log email status
                if (!$email_result['success']) {
                    error_log("Failed to send appointment confirmation email to user ID: $user_id - " . $email_result['message']);
                }
            }
            
            // Set a session variable for the toast notification
            $_SESSION['appointment_success'] = true;
            header('Location: userAppointment.php?tab=pending');
            exit();
        } else {
            $appointment_error = "Error: " . $conn->error;
        }
        }
    }
}

// Get appointment counts for visual indicators
$appointment_counts = [];
$date_result = $conn->query("
    SELECT date, status_enum as status, COUNT(*) as count 
    FROM appointments 
    GROUP BY date, status_enum
");

if ($date_result) {
    while ($row = $date_result->fetch_assoc()) {
        $appointment_counts[$row['date']][$row['status']] = $row['count'];
    }
} else {
    die("Query Error: " . $conn->error);
}

// Get unavailable time slots for selected date
$unavailable_slots = [];
if (isset($_POST['selected_date']) && !empty($_POST['selected_date'])) {
    $selected_date = $conn->real_escape_string($_POST['selected_date']);
    $result = $conn->query("SELECT time, status_enum as status FROM appointments WHERE date = '$selected_date' AND (status_enum = 'pending' OR status_enum = 'approved')");
    while ($row = $result->fetch_assoc()) {
        $unavailable_slots[] = $row;
    }
}

// Define time slots
$am_slots = [
    "8:00 AM", "8:30 AM", "9:00 AM", "9:30 AM", "10:00 AM", "10:30 AM", "11:00 AM", "11:30 AM"
];
$pm_slots = [
    "1:00 PM", "1:30 PM", "2:00 PM", "2:30 PM", "3:00 PM", "3:30 PM", "4:00 PM", "4:30 PM", "5:00 PM"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mayor's Office - Online Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #0055a4;
            --primary-green: #28a745;
            --primary-orange: #ff6b35;
            --primary-yellow: #ffc107;
            --primary-light: #f8f9fa;
            --primary-dark: #212529;
            --subtle-blue-bg: #f3f7fb;
            --slot-available-bg: #f7fafd;
            --slot-available-color: #5a7bbd;
            --slot-selected-bg: #e3f7f1;
            --slot-selected-color: #2e8b57;
            --slot-selected-border: #2e8b57;
            --slot-pending-bg: #fffbe9;
            --slot-pending-color: #bfa700;
            --slot-pending-border: #ffe066;
            --slot-unavailable-bg: #f6f6f6;
            --slot-unavailable-color: #bdbdbd;
            --slot-approved-bg: #eafaf1;
            --slot-approved-color: #3bb77e;
            --slot-approved-border: #3bb77e;
            --slot-shadow: 0 2px 12px 0 rgba(90,123,189,0.07);
            --slot-focus: 0 0 0 3px #b3e6ff;
            --booking-bg: #f9fafb;
            --slot-available-bg: #f9fafb;
            --slot-available-color: #444;
            --slot-selected-bg: #eaf3fb;
            --slot-selected-color: #2563eb;
            --slot-selected-border: #2563eb;
            --slot-pending-bg: #f5f6fa;
            --slot-pending-color: #b1a06b;
            --slot-pending-border: #e5e7eb;
            --slot-unavailable-bg: #f3f4f6;
            --slot-unavailable-color: #bdbdbd;
            --slot-approved-bg: #f0fdf4;
            --slot-approved-color: #4caf50;
            --slot-approved-border: #e5e7eb;
            --slot-shadow: none;
        }
        /* Fix double check icon for approved slot */
        .bi-check-circle-fill::before { content: none !important; }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--subtle-blue-bg);
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003a75 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 2000;
            transition: all 0.3s ease;
            opacity: 1;
            pointer-events: auto;
        }

        .header.hidden {
            opacity: 0.9;
            transform: translateY(-100%);
            pointer-events: auto;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
       
        
        .logo i {
            font-size: 30px;
            color: var(--primary-blue);
        }
        
        .hero {
            background:;
            height: 550px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 85, 164, 0.7);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            padding: 20px;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
            color: var(--primary-blue);
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 70px;
            height: 4px;
            background: var(--primary-green);
            border-radius: 2px;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin: 40px 0;
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .service-icon {
            height: 120px;
            background: rgba(0, 85, 164, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: var(--primary-blue);
        }
        
        .service-content {
            padding: 25px;
        }
        
        .btn-book {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-book:hover {
            background: #218838;
            transform: scale(1.05);
        }
        
        .booking-section {
            background: var(--booking-bg);
            padding: 60px 0;
        }
        
        .booking-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            padding-bottom: 40px;
        }
        
        .booking-header {
            background: var(--primary-blue);
            color: white;
            padding: 20px 30px;
        }
        
        .booking-steps {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 0;
            padding: 30px 0 0 0;
        }
        
        .step {
            width: 220px;
            min-height: unset;
            height: auto;
            text-align: center;
            padding: 16px;
            border-radius: 10px;
            background: rgba(0, 85, 164, 0.05);
            position: relative;
            box-shadow: none;
        }
        
        @media (max-width: 768px) {
            .booking-steps {
                flex-direction: column;
                align-items: stretch;
                padding: 20px 0 0 0;
            }
            .step {
                width: 100%;
            }
        }
        
        .step.active {
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid var(--primary-green);
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background: var(--primary-green);
        }
        
        .booking-content {
            display: flex;
            flex-direction: column;
            gap: 40px;
            padding: 30px;
        }
        
        @media (max-width: 992px) {
            .booking-content {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
        }
        
        .calendar-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: 50px;
            gap: 8px;
            min-height: 400px;
            background: transparent;
        }
        
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            padding: 10px;
            color: var(--primary-blue);
        }
        
        .calendar-day {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center; /* Center content vertically */
            height: 50px;
            border: 1px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            font-size: 1.08rem;
            font-weight: 500;
        }
        .calendar-day:hover {
            background-color: rgba(0, 85, 164, 0.1);
        }
        .calendar-day.selected {
            background-color: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        .calendar-day.disabled {
            background-color: #f8f9fa;
            color: #ccc;
            cursor: not-allowed;
        }
        /* Remove green dot for today */
        .calendar-day.today::after { display: none !important; }
        
        .time-slots-container {
            margin-top: 25px;
        }
        
        .time-group-header {
            font-size: 1.1rem;
            color: var(--primary-blue);
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
            position: relative;
        }
        
        .time-group-header::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ccc, transparent);
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .time-slot {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 16px;
            min-height: 38px;
            height: 38px;
            background: var(--slot-available-bg);
            border-radius: 8px;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border 0.15s;
            border: 1px solid transparent;
            color: var(--slot-available-color);
            font-family: 'Inter', system-ui, Arial, sans-serif;
            font-weight: 400;
            font-size: 1rem;
            box-shadow: none;
            position: relative;
            outline: none;
            margin-bottom: 6px;
            margin-top: 0;
            letter-spacing: 0.01em;
        }
        .time-slot:hover {
            background: #f3f6fa;
        }
        .time-slot:focus {
            box-shadow: var(--slot-focus);
        }
        .time-slot.selected {
            background: #111 !important;
            color: #fff !important;
            border: 3px solid #000 !important;
            font-weight: 700;
            box-shadow: none !important;
            z-index: 2;
            position: relative;
            transition: background 0.2s, color 0.2s, border 0.2s;
        }
        
        /* Ensure selected state overrides all other states */
        .time-slot.selected.available,
        .time-slot.selected.pending,
        .time-slot.selected.approved {
            background: #111 !important;
            color: #fff !important;
            border: 3px solid #000 !important;
        }
        .time-slot.selected .slot-icon i,
        .time-slot.selected i.bi-check-lg {
            color: #fff !important;
        }
        .time-slot.unavailable {
            background: var(--slot-unavailable-bg) !important;
            color: var(--slot-unavailable-color) !important;
            border: 1px solid var(--slot-unavailable-bg) !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.6;
        }
        .time-slot.unavailable .slot-icon svg {
            stroke: var(--slot-unavailable-color) !important;
        }
        .time-slot.approved {
            background: var(--slot-approved-bg) !important;
            color: var(--slot-approved-color) !important;
            border: 1px solid var(--slot-approved-border) !important;
            font-weight: 500;
        }
        .time-slot.approved .slot-icon svg {
            stroke: var(--slot-approved-color) !important;
        }
        .time-slot.pending {
            background: #f5f6fa !important;
            color: #b1a06b !important;
            border: 1px solid #e5e7eb !important;
            font-weight: 500;
        }
        .time-slot.pending .slot-icon svg {
            stroke: var(--slot-pending-color) !important;
        }
        
        .time-slot.pending::before {
            content: none !important;
        }
        
        .appointment-indicators {
            position: absolute;
            bottom: 5px;
            display: flex;
            justify-content: center;
            width: 100%;
            gap: 2px;
        }
        
        .appointment-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        
        .indicator-approved {
            background-color: var(--primary-green);
        }
        
        .indicator-pending {
            background-color: var(--primary-yellow);
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 30px;
        }
        
        .btn-submit {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.2rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            min-width: 220px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        .notification-item.completed {
                border-left: 4px solid #10b981 !important;
                background: #ecfdf5 !important;
                color: #065f46 !important;
            }

            .notification-item.completed .notification-icon {
                background: #10b981 !important;
                color: white !important;
                border: 1px solid #10b981 !important;
            }
        
        .btn-submit:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit.loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.3);
            animation: loadingPulse 1.5s infinite;
        }
        
        @keyframes loadingPulse {
            0% { opacity: 0; }
            50% { opacity: 0.5; }
            100% { opacity: 0; }
        }
        
        .btn-submit .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-submit.loading .spinner {
            display: inline-block;
        }
        
        .btn-submit.success i {
            color: #fff;
            font-size: 1.5rem;
            animation: bounce 0.5s;
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        

        
        .notification {
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 4.5s;
            transform: translateX(100%);
            opacity: 0;
        }
        
        @keyframes slideIn {
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
            }
        }
        
        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(0, 85, 164, 0.1);
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin: 20px 0;
        }
        
        .testimonial-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-green) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .testimonial-rating {
            color: #ffc107;
            font-size: 20px;
            margin-top: 10px;
        }
        
        .footer {
            background: var(--primary-dark);
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .copyright {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
            margin-top: 30px;
            text-align: center;
            color: rgba(255,255,255,0.5);
        }
        
        @media (max-width: 768px) {
            .booking-steps {
                flex-direction: column;
            }
            
            .hero {
                height: 300px;
            }
            
            .btn-submit {
                padding: 12px 25px;
                font-size: 1.1rem;
                min-width: 180px;
            }
        }
        
        @media (min-width: 769px) {
            .booking-steps {
                flex-direction: row;
            }
            .step {
                width: 220px;
                flex: unset;
            }
        }
        
        .time-divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: #6c757d;
        }
        
        .time-divider::before,
        .time-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ccc, transparent);
        }
        
        .time-divider span {
            padding: 0 15px;
            font-weight: 600;
        }
        
        /* Tooltip styling */
        .tooltip-inner {
            background-color: #ffc107;
            color: #333;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 4px;
            z-index: 9999 !important;
        }
        
        .bs-tooltip-top .tooltip-arrow::before, 
        .bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow::before {
            border-top-color: #ffc107;
        }
        
        /* Ensure tooltips are visible */
        .tooltip {
            z-index: 9999 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .tooltip-inner {
            background-color: #ffc107 !important;
            color: #333 !important;
            font-weight: 500 !important;
            padding: 8px 12px !important;
            border-radius: 4px !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Attachment styling */
        .attachment-preview {
            border: 1px dashed #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            background: #f9f9f9;
            display: none;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            padding: 8px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .attachment-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
            border-radius: 4px;
            margin-right: 10px;
            color: #495057;
        }
        
        .attachment-info {
            flex-grow: 1;
        }
        
        .attachment-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .attachment-size {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .attachment-remove {
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }
        
        .attachment-remove:hover {
            color: #bd2130;
        }
        .legend-dot {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            margin-right: 6px;
            border: 2px solid #ccc;
        }
        .legend-approved {
            background: #28a745;
            border-color: #28a745;
        }
        .legend-pending {
            background: #ffc107;
            border-color: #ffc107;
        }
        .legend-available {
            background: #fff;
            border-color: #007bff;
        }
        .legend-unavailable {
            background: #eee;
            border-color: #bbb;
        }
        .time-slot.approved {
            background: #e6f9ed !important;
            color: #218838 !important;
            border: 2px solid #28a745 !important;
            font-weight: 600;
            position: relative;
        }
        .time-slot.approved::before {
            content: '\f26b'; /* bi-check-circle-fill */
            font-family: 'bootstrap-icons';
            color: #28a745;
            margin-right: 6px;
            font-size: 1.1em;
            vertical-align: middle;
        }
        .time-slot.pending {
            background: #fff8e1 !important;
            color: #856404 !important;
            border: 2px solid #ffc107 !important;
            font-weight: 600;
            position: relative;
            cursor: not-allowed !important;
            opacity: 0.5 !important;
            pointer-events: none !important;
            box-shadow: none !important;
        }
        .time-slot.pending::before {
            content: '\f335'; /* bi-hourglass-split */
            font-family: 'bootstrap-icons';
            color: #ffc107;
            margin-right: 6px;
            font-size: 1.1em;
            vertical-align: middle;
        }
        .time-slot.unavailable {
            background: #eee !important;
            color: #aaa !important;
            border: 1px solid #bbb !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.7;
        }
        .time-slot.available {
            background: #fff !important;
            color: #007bff !important;
            border: 1px solid #007bff !important;
        }
        @media (max-width: 1200px) {
            .info-box {
                position: static !important;
                width: 100% !important;
                min-width: unset !important;
                margin-bottom: 20px;
            }
        }
        .time-slot.selected .slot-icon i {
            color: inherit !important;
            fill: inherit !important;
            font-size: inherit !important;
            text-shadow: none !important;
        }
        .time-slot .slot-icon {
            display: flex;
            align-items: center;
        }
        .tooltip.bs-tooltip-top .tooltip-arrow::before,
        .tooltip.bs-tooltip-bottom .tooltip-arrow::before,
        .tooltip.bs-tooltip-start .tooltip-arrow::before,
        .tooltip.bs-tooltip-end .tooltip-arrow::before {
            border-top-color: #111 !important;
            border-bottom-color: #111 !important;
            border-left-color: #111 !important;
            border-right-color: #111 !important;
        }

        .tooltip-inner {
            background-color: #111 !important;
            color: #fff !important;
            font-weight: 500;
            padding: 4px 8px; /* Reduced padding */
            border-radius: 6px;
            font-size: 0.85rem; /* Reduced font size */
            transition: background 0.3s, color 0.3s;
        }
        .calendar-day.today > div:nth-child(2) {
            margin-top: 8px !important; /* Reduced for better centering */
            font-size: 0.65em;
            color: #28a745;
            font-weight: 600;
            line-height: 1.1;
        }
        .calendar-day.today > div:first-child {
            margin-bottom: 0 !important;
        }

        /* Simple Notification Bell Styles */
        .notification-bell-container {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            position: relative;
            width: 40px;
            height: 40px;
            background: #6c757d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
            color: white;
            font-size: 1.2rem;
            z-index: 1001;
        }

        .notification-bell:hover {
            background: #5a6268;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
            border: 2px solid white;
            transition: opacity 0.3s ease;
        }

        .notification-bell:hover .notification-badge {
            opacity: 0;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            margin-top: 8px;
            display: none;
            max-height: 400px;
            overflow: hidden;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .notification-header h6 {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
            margin: 0;
        }

        .notification-header .btn-close {
            background: none;
            border: none;
            color: #666;
            font-size: 1rem;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .notification-header .btn-close:hover {
            background: #e9ecef;
        }

        .notification-header .btn-outline-success {
            border-color: #10b981;
            color: #10b981;
            font-size: 0.8rem;
            padding: 4px 8px;
            transition: all 0.2s ease;
        }

        .notification-header .btn-outline-success:hover {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            transform: scale(1.05);
        }

        .notification-header .btn-outline-success:active {
            transform: scale(0.95);
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 12px 12px;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .notification-item.Approved {
            border-left-color: #10b981 !important;
            background: #ecfdf5 !important;
            border-left-width: 4px !important;
            border-left-style: solid !important;
        }

        .notification-item.Cancelled,
        .notification-item.Declined {
            border-left-color: #ef4444 !important;
            background: #fef2f2 !important;
            border-left-width: 4px !important;
            border-left-style: solid !important;
        }

        .notification-item.Rescheduled {
            border-left-color: #3b82f6 !important;
            background: #eff6ff !important;
            border-left-width: 4px !important;
            border-left-style: solid !important;
        }

        .notification-item .notification-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .notification-item.Approved .notification-icon {
            background: #10b981 !important;
            color: white !important;
            border: 1px solid #10b981 !important;
        }

        .notification-item.Cancelled .notification-icon,
        .notification-item.Declined .notification-icon {
            background: #ef4444 !important;
            color: white !important;
            border: 1px solid #ef4444 !important;
        }

        .notification-item.Rescheduled .notification-icon {
            background: #3b82f6 !important;
            color: white !important;
            border: 1px solid #3b82f6 !important;
        }

        .notification-item .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-item .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
            color: #333;
        }

        .notification-item .notification-message {
            font-size: 0.8rem;
            color: #666;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .notification-item .notification-time {
            font-size: 0.75rem;
            color: #999;
        }

        .notification-item .notification-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: #999;
            font-size: 0.9rem;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
            opacity: 0;
        }

        .notification-item:hover .notification-close {
            opacity: 1;
        }

        .notification-item .notification-close:hover {
            background: #e9ecef;
            color: #666;
        }

        /* Empty state */
        .notification-empty {
            padding: 32px 16px;
            text-align: center;
            color: #666;
        }

        .notification-empty i {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .notification-empty h6 {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .notification-empty p {
            font-size: 0.85rem;
            margin: 0;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .notification-dropdown {
                position: fixed;
                top: 60px;
                right: 10px;
                left: 10px;
                width: auto;
                max-height: 60vh;
                margin-top: 0;
            }

            .notification-list {
                max-height: 50vh;
            }

            .notification-item {
                padding: 10px 8px;
                gap: 6px;
            }

            .notification-item .notification-icon {
                width: 20px;
                height: 20px;
                font-size: 0.7rem;
            }

            .notification-item .notification-title {
                font-size: 0.85rem;
                font-weight: 600;
            }

            .notification-item .notification-message {
                font-size: 0.75rem;
                line-height: 1.3;
            }

            .notification-item .notification-time {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .notification-bell {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }

            .notification-badge {
                width: 16px;
                height: 16px;
                font-size: 0.65rem;
            }

            .notification-dropdown {
                top: 50px;
                right: 5px;
                left: 5px;
            }

            .notification-header {
                padding: 10px 12px;
            }

            .notification-item {
                padding: 8px 6px;
                gap: 5px;
            }

            .notification-item .notification-icon {
                width: 18px;
                height: 18px;
                font-size: 0.65rem;
            }

            .notification-item .notification-title {
                font-size: 0.8rem;
            }

            .notification-item .notification-message {
                font-size: 0.7rem;
            }

            .notification-item .notification-time {
                font-size: 0.65rem;
            }
        }





        .notification-time {
            font-size: 0.8rem;
            color: #999;
            font-style: italic;
        }

        .notification-close {
            background: none;
            border: none;
            color: #999;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.2s;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        .notification-close:hover {
            background: rgba(0,0,0,0.1);
            color: #666;
            transform: scale(1.1);
        }
        
        .notification-close:active {
            transform: scale(0.95);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }



        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>
    <!-- Notification Container -->
    <div class="notification-container">
        <?php if($appointment_success): ?>
            <div class="notification alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> 
                Appointment booked successfully! Status: Pending.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif($appointment_error): ?>
            <div class="notification alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i> 
                <?php echo $appointment_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        

    </div>

    <!-- Login Success Toast -->
    <?php if($show_login_success): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
        <div id="loginSuccessToast" class="toast align-items-center text-bg-primary border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Login successful! Hello, <?php echo htmlspecialchars($user_full_name); ?>!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo-container">
                  <div class ="logo">
                       <img src="images/logooo.png" alt="Company Logo" style="width:150px; height:auto; border-radius: 15px;">
                   </div>
                    <div>
                        <h4 class="mb-0">Solano Mayor's Office</h4>
                         
                        <p class="mb-0" style = "font-size:13px;">Online Appointment System</p>
                    </div>
                </div>
                
                <!-- Profile and Logout Section -->
                <div class="d-flex align-items-center">
                    <?php if($username): ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-link text-white dropdown-toggle d-flex align-items-center" 
                                    type="button" id="userDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false">
                                <div class="me-2">
                                    <i class="bi bi-person-circle fs-4"></i>
                                </div>
                                <span class="fw-bold"><?php echo htmlspecialchars($username); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="userAppointment.php"><i class="bi bi-calendar-check me-2"></i>My Appointments</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Notification Bell -->
                        <div class="notification-bell-container">
                            <div class="notification-bell" id="notificationBell" style="cursor: pointer;" onclick="console.log('Bell clicked via onclick'); toggleNotifications();">
                                <i class="bi bi-bell"></i>
                                <?php if (!empty($status_notifications)): ?>
                                    <span class="notification-badge"><?php echo count($status_notifications); ?></span>
                                    <!-- Debug: Found <?php echo count($status_notifications); ?> notifications -->
                                <?php else: ?>
                                    <!-- Debug: No notifications found -->
                                <?php endif; ?>
                            </div>

                            <!-- Notification Dropdown -->
                            <?php if (!empty($status_notifications)): ?>
                                <div class="notification-dropdown" id="notificationDropdown">
                                    <div class="notification-header">
                                        <h6>Notifications</h6>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="markAllAsRead()" 
                                                title="Clear all notifications" 
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top">
                                            <i class="bi bi-check2-all me-1"></i>Clear All
                                        </button>
                                    </div>
                                    <div class="notification-list">
                                        <?php foreach ($status_notifications as $notification): ?>
                                            <!-- Debug: Status = <?php echo $notification['status_enum']; ?> -->
                                            <div class="notification-item <?php echo $notification['status_enum']; ?>" data-notification-id="<?php echo $notification['id']; ?>" 
                                                 style="cursor: pointer;
                                                        <?php if ($notification['status_enum'] === 'Approved'): ?>
                                                            border-left: 4px solid #10b981 !important; background: #ecfdf5 !important; color: #065f46 !important;
                                                        <?php elseif ($notification['status_enum'] === 'Declined' || $notification['status_enum'] === 'Cancelled'): ?>
                                                            border-left: 4px solid #ef4444 !important; background: #fef2f2 !important; color: #991b1b !important;
                                                        <?php elseif ($notification['status_enum'] === 'Rescheduled'): ?>
                                                            border-left: 4px solid #3b82f6 !important; background: #eff6ff !important; color: #1e40af !important;
                                                        <?php endif; ?>"
                                                 onclick="goToAppointment(<?php echo $notification['id']; ?>)">
                                                <div class="notification-icon">
                                                    <?php if ($notification['status_enum'] === 'Approved'): ?>
                                                        <i class="bi bi-check-circle-fill"></i>
                                                    <?php elseif ($notification['status_enum'] === 'Cancelled'): ?>
                                                        <i class="bi bi-x"></i>

                                                    <?php elseif ($notification['status_enum'] === 'Declined'): ?>
                                                        <i class="bi bi-x-circle-fill"></i>
                                                    <?php elseif ($notification['status_enum'] === 'Rescheduled'): ?>
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    <?php elseif ($notification['status_enum'] === 'Completed'): ?>
                                                     <i class="bi bi-check-circle text-success"></i> <!-- Green icon -->


                                                    <?php endif; ?>
                                                </div>
                                                <div class="notification-content">
                                                    <div class="notification-title">
                                                        <?php if ($notification['status_enum'] === 'Approved'): ?>
                                                            Appointment Approved
                                                        <?php elseif ($notification['status_enum'] === 'Cancelled'): ?>
                                                            Appointment Cancelled
                                                        <?php elseif ($notification['status_enum'] === 'Declined'): ?>
                                                            Appointment Declined
                                                        <?php elseif ($notification['status_enum'] === 'Rescheduled'): ?>
                                                            Appointment Rescheduled
                                                        <?php elseif ($notification['status_enum'] === 'Completed'): ?>
                                                            Appointment Completed
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="notification-message">
                                                        Your appointment for "<?php echo htmlspecialchars($notification['purpose']); ?>" 
                                                        on <?php echo date('M j, Y', strtotime($notification['date'])); ?> 
                                                        at <?php echo date('g:i A', strtotime($notification['time'])); ?> 
                                                        has been <?php echo ucfirst($notification['status_enum']); ?>.
                                                        <?php if ($notification['status_enum'] === 'Rescheduled'): ?>
                                                            Please check your email for the new schedule.
                                                        <?php endif; ?>
                                                        <!-- STATUS: <?php echo $notification['status_enum']; ?> -->
                                                    </div>
                                                                                        <div class="notification-time">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['updated_at'])); ?>
                                        <small class="text-muted ms-2">Click to view details</small>
                                    </div>
                                                </div>
                                                <button type="button" class="notification-close" data-notification-id="<?php echo $notification['id']; ?>" title="Dismiss notification">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="notification-dropdown" id="notificationDropdown">
                                    <div class="notification-header">
                                        <h6><i class="bi bi-bell me-2"></i>Notifications</h6>
                                        <button type="button" class="btn-close" onclick="toggleNotifications()" title="Close">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                    <div class="notification-empty">
                                        <i class="bi bi-bell-slash"></i>
                                        <h6>No notifications</h6>
                                        <p>You're all caught up!</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="fw-bold">Welcome!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="display-4 fw-bold mb-3">Book Your Appointment Online</h1>
            <p class="lead mb-4">Save time and avoid queues by scheduling your visit to the Mayor's Office in advance</p>
            <a href="#booking" class="btn btn-primary btn-lg px-5 py-3">
                <i class="bi bi-calendar-plus me-2"></i>Book Appointment Now
            </a>
        </div>
    </section>

    <!-- Services Section -->
    <section class="container py-5">
        <div class="text-center mb-5">
            <h2 class="section-title d-inline-block">Our Services</h2>
            <p class="text-muted">Select from our range of municipal services</p>
        </div>
        
        <div class="services-grid">
            <!-- Service Cards -->
            <div class="service-card">
                <div class="service-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="service-content">
                    <h4>Document Requests</h4>
                    <p class="text-muted">Request certificates, permits, and official documents</p>
                    <button class="btn-book">
                        <i class="bi bi-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="bi bi-house-door"></i>
                </div>
                <div class="service-content">
                    <h4>Housing Services</h4>
                    <p class="text-muted">Apply for housing permits and assistance programs</p>
                    <button class="btn-book">
                        <i class="bi bi-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="bi bi-building"></i>
                </div>
                <div class="service-content">
                    <h4>Business Licensing</h4>
                    <p class="text-muted">Register new businesses and renew existing licenses</p>
                    <button class="btn-book">
                        <i class="bi bi-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="service-content">
                    <h4>Community Programs</h4>
                    <p class="text-muted">Apply for social services and community programs</p>
                    <button class="btn-book">
                        <i class="bi bi-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="service-content">
                    <h4>Tax Payments</h4>
                    <p class="text-muted">Schedule payments for municipal taxes and fees</p>
                    <button class="btn-book">
                        <i class="bi bi-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="bi bi-person-lines-fill"></i>
                </div>
                <div class="service-content">
                    <h4>Meet the Mayor</h4>
                    <p class="text-muted">Request a personal meeting with the Mayor</p>
                    <button class="btn-book">
                        <i class="bi bi-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="booking-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title d-inline-block">Book Your Appointment</h2>
                <p class="text-muted">Simple 3-step process to schedule your visit</p>
            </div>
            <div class="d-flex flex-row-reverse flex-wrap gap-4 align-items-start" style="min-height: 700px;">
                <div style="flex:1; min-width: 320px;">
                    <div class="booking-container">
                        <div class="booking-header">
                            <h3 class="mb-0">Document Request Service</h3>
                        </div>
                        <!-- Steps go above the calendar and form -->
                        <div class="booking-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <h5>Select Date & Time</h5>
                                <small>Choose your preferred slot</small>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <h5>Your Information</h5>
                                <small>Provide your details</small>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <h5>Confirmation</h5>
                                <small>Review and confirm</small>
                            </div>
                        </div>
                        <div class="booking-content">
                            <div class="calendar-container" id="calendar">
                                <div class="calendar-header">
                                    <h4 id="currentMonthYear">July 2025</h4>
                                    <div>
                                        <button id="prevMonth" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-chevron-left"></i></button>
                                        <button id="nextMonth" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                </div>
                                <!-- Legend for time slots -->
                                <div class="mb-3 d-flex flex-wrap align-items-center gap-3" style="font-size: 1rem;">
                                    <span class="d-flex align-items-center"><span class="legend-dot legend-approved me-1"></span> Approved</span>
                                    <span class="d-flex align-items-center"><span class="legend-dot legend-pending me-1"></span> Pending</span>
                                    <span class="d-flex align-items-center"><span class="legend-dot legend-available me-1"></span> Available</span>
                                    <span class="d-flex align-items-center"><span class="legend-dot legend-unavailable me-1"></span> Unavailable</span>
                                </div>
                                
                                <div class="calendar-grid"></div>
                                
                                <div class="time-slots-container">
                                    <h5 class="time-group-header">Morning (AM)</h5>
                                    <div class="time-slots" id="am-slots"></div>
                                    
                                    <div class="time-divider">
                                        <span>Afternoon</span>
                                    </div>
                                    
                                    <h5 class="time-group-header">Afternoon (PM)</h5>
                                    <div class="time-slots" id="pm-slots">
                                        <div class="mt-2 mb-3">
                                          <span style="color:#bbb;"><i class="bi bi-hourglass-split"></i> Unavailable</span>
                                          <span style="margin-left:20px;color:#007bff;"><span style="display:inline-block;width:16px;height:16px;border:1px solid #007bff;background:#fff;vertical-align:middle;margin-right:4px;"></span> Available</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Summary will appear here -->
                            <div id="selectedSummary" style="display: none;"></div>
                            
                            <div class="form-container">
                                <h4 class="mb-4">Your Information</h4>
                                <form method="post" id="appointmentForm" enctype="multipart/form-data">
                                    <input type="hidden" name="service_id" value="1">
                                    <input type="hidden" name="confirm_appointment" value="1">
                                    <input type="hidden" name="selected_date" id="selectedDateInput" value="<?php echo isset($_POST['selected_date']) ? $_POST['selected_date'] : ''; ?>">
                                    <input type="hidden" name="selected_time" id="selectedTimeInput" value="<?php echo isset($_POST['selected_time']) ? $_POST['selected_time'] : ''; ?>">
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Purpose of Visit</label>
                                            <select class="form-select" name="purpose" id="purposeSelect" required>
                                                <option value="" selected disabled>Select purpose</option>
                                                <option>Certificate Request</option>
                                                <option>Business Permit</option>
                                                <option>Tax Payment</option>
                                                <option>Complaint</option>
                                                <option>Meeting</option>
                                                <option>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Number of expected attendees</label>
                                            <input type="number" class="form-control" name="attendees" placeholder="e.g. 1-5" min="1" max="20" required>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="otherDetailsContainer" style="display:none;">
                                        <label class="form-label">If others please specify</label>
                                        <textarea class="form-control" name="other_details" rows="3" placeholder="Any special requirements or additional information"></textarea>
                                    </div>
                                    
                                    <!-- File Attachment Section -->
                                    <div class="mb-4">
                                        <label class="form-label">Attachments (Optional)</label>
                                        <input class="form-control" type="file" name="attachments[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                        <small class="text-muted">Upload PDF, Word documents, or images (max 5MB each)</small>
        <div id="fileSizeError" class="alert alert-danger mt-2" style="display: none;">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span id="fileSizeErrorMessage"></span>
        </div>
                                        
                                        <div class="attachment-preview" id="attachmentPreview">
                                            <div class="mb-2 fw-medium">Selected files:</div>
                                            <div id="fileList"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button class="btn-submit" type="submit" name="confirm_appointment" id="confirmBtn">
                                            <span class="spinner"></span>
                                            <i class="bi bi-calendar-check me-1"></i>
                                            <span>Confirm Appointment</span>
                                        </button>

                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials
    <section class="container py-5">
        <div class="text-center mb-5">
            <h2 class="section-title d-inline-block">What Residents Say</h2>
            <p class="text-muted">Feedback from our community</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">MJ</div>
                        <div>
                            <h5>Maria Javier</h5>
                            <p class="text-muted mb-0">Poblacion Resident</p>
                        </div>
                    </div>
                    <p>"Booking my business permit renewal was so easy with this system. I was in and out of the office in 15 minutes!"</p>
                    <div class="testimonial-rating">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="testimonial-card">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">CR</div>
                        <div>
                            <h5>Carlos Reyes</h5>
                            <p class="text-muted mb-0">Lubing Resident</p>
                        </div>
                    </div>
                    <p>"I needed a barangay clearance urgently. The online appointment saved me hours of waiting. Excellent service!"</p>
                    <div class="testimonial-rating">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-half"></i>
                    </div>
                </div>
            </div>
        </div>
    </section> -->

    <!-- FAQ Section -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title d-inline-block">Frequently Asked Questions</h2>
                <p class="text-muted">Find answers to common questions</p>
            </div>
            
            <div class="faq-container">
                <div class="accordion" id="faqAccordion">
                    <!-- FAQ Items -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                How far in advance can I book an appointment?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show">
                            <div class="accordion-body">
                                You can book appointments up to 30 days in advance. Same-day appointments may be available depending on staff schedules.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                What should I bring to my appointment?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                Please bring a valid ID and any documents related to your appointment. Specific requirements will be listed in your confirmation email.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                Can I reschedule or cancel my appointment?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                Yes, you can reschedule or cancel your appointment up to 24 hours before your scheduled time through the link in your confirmation email.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFour">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                                Is there a fee for using the online appointment system?
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                No, the online appointment system is completely free to use. You'll only need to pay for any applicable service fees during your visit.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFive">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">
                                What if I'm late for my appointment?
                            </button>
                        </h2>
                        <div id="collapseFive" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                If you arrive more than 15 minutes late, your appointment may be given to another resident. We recommend arriving 10 minutes early to complete any necessary paperwork.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="logo-container mb-3">
                        <div class="logo">
                            <img src="images/logooo.png" alt="Company Logo" style="width:150px; height:auto; border-radius: 15px;">
                        </div>
                        <div>
                            <h4 class="text-white mb-0">Solano Mayor's Office</h4>
                            <p class="mb-0">Serving our community</p>
                        </div>
                    </div>
                    <p class="text-white-50">Providing efficient and accessible services to all residents of Solano.</p>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <div class="footer-links">
                        <a href="#">Home</a>
                        <a href="#">Services</a>
                        <a href="#">Book Appointment</a>
                        <a href="#">FAQs</a>
                        <a href="#">Contact Us</a>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5 class="text-white mb-4">Contact Information</h5>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i> Municipal Hall, Solano, Nueva Vizcaya</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i> (078) 123-4567</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i> info@solanomayor.gov.ph</li>
                        <li class="mb-2"><i class="bi bi-clock me-2"></i> Mon-Fri: 8:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; 2025 Solano Mayor's Office. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
          <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-bottom: none;">
            <h5 class="modal-title" id="confirmationModalLabel" style="font-weight: 600;">
              <i class="bi bi-calendar-check me-2"></i>Confirm Your Appointment
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="confirmationSummary" style="padding: 30px; overflow-y: auto; flex: 1 1 auto;">
            <!-- Summary will be injected here -->
          </div>
          <!-- <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 20px 30px;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="padding: 10px 25px; font-weight: 500;">
              <i class="bi bi-x-circle me-2"></i>Cancel
            </button> -->
           <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 20px 30px;">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="padding: 10px 25px; font-weight: 500;">
        <i class="bi bi-x-circle me-2"></i>Cancel
    </button>
            <button type="button" class="btn btn-success" id="finalConfirmBtn" style="padding: 12px 30px; font-weight: 600; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true" id="modalSpinner"></span>
                <span id="modalBtnText"><i class="bi bi-check-circle me-2"></i>Confirm Appointment</span>
            </button>
        </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Attachment Preview Modal -->
    <div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-labelledby="attachmentPreviewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="attachmentPreviewModalLabel">Attachment Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="attachmentPreviewBody" style="text-align:center; min-height:300px; display:flex; align-items:center; justify-content:center;"></div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show login success toast if available
            <?php if($show_login_success): ?>
            var loginSuccessToast = document.getElementById('loginSuccessToast');
            if (loginSuccessToast) {
                var toast = new bootstrap.Toast(loginSuccessToast, {
                    delay: 4000
                });
                toast.show();
            }
            <?php endif; ?>
            
            // Initialize tooltips for pending slots and notification buttons
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' }));
            
            // Calendar functionality
            let currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            
            updateMonthYearDisplay();
            renderCalendar();
            initTimeSlots();
            setupFormSubmission();
            setupFileAttachments();
            
            // Service card booking buttons
            const bookButtons = document.querySelectorAll('.btn-book');
            bookButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('booking').scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });
            
            // Header scroll behavior
            let lastScrollTop = 0;
            const header = document.querySelector('.header');
            
            window.addEventListener('scroll', () => {
                const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                
                if (currentScroll > lastScrollTop && currentScroll > 400) {
                    header.classList.add('hidden');
                } else {
                    header.classList.remove('hidden');
                }
                
                lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
            });
            
            // Show header on hover when hidden
            let hideTimeout;
            header.addEventListener('mouseenter', () => {
                if (header.classList.contains('hidden')) {
                    header.classList.remove('hidden');
                }
                clearTimeout(hideTimeout);
            });
            
            header.addEventListener('mouseleave', () => {
                const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                if (currentScroll > 400) {
                    hideTimeout = setTimeout(() => {
                        header.classList.add('hidden');
                    }, 1000); // 1 second delay
                }
            });
            
            // Calendar navigation
            document.getElementById('prevMonth').addEventListener('click', () => {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                updateMonthYearDisplay();
                renderCalendar();
            });
            
            document.getElementById('nextMonth').addEventListener('click', () => {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                updateMonthYearDisplay();
                renderCalendar();
            });

            console.log('Setting up final confirm button listener, button element:', finalConfirmBtn);
            finalConfirmBtn.addEventListener('click', function() {
                console.log('Final confirm button clicked');
                
                // Show loading spinner and change text
                const spinner = document.getElementById('modalSpinner');
                const btnText = document.getElementById('modalBtnText');
                spinner.classList.remove('d-none');
                btnText.innerHTML = '<i class="bi bi-hourglass me-2"></i>Processing...';
                
                // Disable button to prevent double-click
                finalConfirmBtn.disabled = true;
                
                // Set timeout to allow spinner to appear before form submission
                setTimeout(() => {
                    allowSubmit = true;
                    console.log('About to submit form');
                    form.submit();
                }, 500);
            });
            
            
            // Functions
            function updateMonthYearDisplay() {
                const monthNames = ["January", "February", "March", "April", "May", "June",
                                  "July", "August", "September", "October", "November", "December"];
                document.getElementById('currentMonthYear').textContent = 
                    `${monthNames[currentMonth]} ${currentYear}`;
            }
            
            function renderCalendar() {
                const monthNames = ["January", "February", "March", "April", "May", "June",
                                  "July", "August", "September", "October", "November", "December"];
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                
                const today = new Date();
                today.setHours(0,0,0,0); // Remove time part

                const maxDate = new Date(today);
                maxDate.setDate(today.getDate() + 13); // 2 weeks = 14 days including today
                
                // Get first day of the month (0-6, Sunday-Saturday)
                const firstDay = new Date(currentYear, currentMonth, 1).getDay();
                // Get number of days in the month
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                // Get today's date for comparison
                const isCurrentMonth = today.getMonth() === currentMonth && today.getFullYear() === currentYear;
                
                let calendarHTML = '';
                
                // Add day headers
                for (let i = 0; i < dayNames.length; i++) {
                    calendarHTML += `<div class="calendar-day-header">${dayNames[i]}</div>`;
                }
                
                // Add empty cells for days before the first day of the month
                for (let i = 0; i < firstDay; i++) {
                    calendarHTML += `<div class="calendar-day disabled"></div>`;
                }
                
                // Add days of the month
                for (let i = 1; i <= daysInMonth; i++) {
                    const dateObj = new Date(currentYear, currentMonth, i);
                    dateObj.setHours(0,0,0,0);

                    const isToday = isCurrentMonth && i === today.getDate();
                    const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;

                    // Check if date is in the allowed range
                    let isDisabled = dateObj < today || dateObj > maxDate;
                    
                    calendarHTML += `<div class="calendar-day${isToday ? ' today' : ''}${isDisabled ? ' disabled' : ''}" data-date="${dateStr}">`;
                    calendarHTML += `<div style='font-size:1.08em;line-height:1.1;'>${i}</div>`;
                    if (isToday) {
                        calendarHTML += `<div style='color:#28a745;font-size:0.65em;font-weight:600;margin-top:2px;line-height:1.1;'>Today</div>`;
                    }
                    // Get appointment counts for this day
                    const approvedCount = <?php echo json_encode($appointment_counts); ?>[dateStr]?.approved || 0;
                    const pendingCount = <?php echo json_encode($appointment_counts); ?>[dateStr]?.pending || 0;
                    
                    // Add appointment indicators
                    if (approvedCount > 0 || pendingCount > 0) {
                        calendarHTML += `<div class="appointment-indicators">`;
                        
                        // Add approved indicators (green)
                        for (let j = 0; j < approvedCount; j++) {
                            calendarHTML += `<div class="appointment-indicator indicator-approved"></div>`;
                        }
                        
                        // Add pending indicators (yellow)
                        for (let j = 0; j < pendingCount; j++) {
                            calendarHTML += `<div class="appointment-indicator indicator-pending"></div>`;
                        }
                        
                        calendarHTML += `</div>`;
                    }
                    
                    calendarHTML += `</div>`;
                }
                
                // Fill remaining grid cells
                const totalCells = 42; // 6 weeks * 7 days
                const daysAdded = firstDay + daysInMonth;
                const remainingCells = totalCells - daysAdded;
                
                for (let i = 1; i <= remainingCells; i++) {
                    calendarHTML += `<div class="calendar-day disabled"></div>`;
                }
                
                // Update the calendar grid
                document.querySelector('.calendar-grid').innerHTML = calendarHTML;
                
                // Add click event listeners to calendar days
                const calendarDays = document.querySelectorAll('.calendar-day:not(.disabled)');
                calendarDays.forEach(day => {
                    day.addEventListener('click', function() {
                        // Remove selection from all days
                        calendarDays.forEach(d => d.classList.remove('selected'));
                        // Add selection to clicked day
                        this.classList.add('selected');
                        
                        // Format the selected date as YYYY-MM-DD
                        const selectedDate = this.dataset.date;
                        
                        // Update the hidden input
                        document.getElementById('selectedDateInput').value = selectedDate;

                        // Clear selected time and input
                        selectedSlot = null;
                        document.getElementById('selectedTimeInput').value = '';
                        // Remove selected class from all time slots (if any are rendered)
                        document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
                        updateSelectedSummary();

                        // AJAX to fetch unavailable slots
                        fetch('get_pending_slots.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'date=' + encodeURIComponent(selectedDate)
                        })
                        .then(response => response.json())
                        .then(unavailableSlots => {
                            console.log('Unavailable slots:', unavailableSlots); // Debugging line
                            renderTimeSlots(unavailableSlots, amSlots, pmSlots);
                            updateSelectedSummary();
                        });
                    });
                });
            }
            
            // Declare these at the top of your script, outside any function
            let selectedSlot = null;
            let unavailableSlotsGlobal = [];

            function initTimeSlots() {
                const timeSlots = document.querySelectorAll('.time-slot.available');
                console.log('Found time slots:', timeSlots.length);
                timeSlots.forEach(slot => {
                    slot.addEventListener('click', function() {
                        console.log('Time slot clicked:', this.textContent);
                        // Remove 'selected' from all slots
                        document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
                        // Add 'selected' to the clicked slot
                        this.classList.add('selected');
                        selectedSlot = this.textContent.trim();
                        console.log('Selected slot:', selectedSlot);
                        document.getElementById('selectedTimeInput').value = selectedSlot;
                        console.log('Time input value set to:', selectedSlot);
                        updateSelectedSummary();
                    });
                });
                // Remove 'selected' class from any pending slots just in case
                document.querySelectorAll('.time-slot.pending.selected').forEach(slot => slot.classList.remove('selected'));
                updateSelectedSummary();
                
                // Debug: Check all timeslots and their classes
                const allTimeSlots = document.querySelectorAll('.time-slot');
                console.log('=== ALL TIMESLOTS DEBUG ===');
                allTimeSlots.forEach((slot, index) => {
                    console.log(`Slot ${index + 1}:`, {
                        text: slot.textContent.trim(),
                        classes: slot.className,
                        hasTooltip: slot.hasAttribute('data-bs-toggle'),
                        title: slot.getAttribute('title')
                    });
                });
            }

            function setupFormSubmission() {
                const form = document.getElementById('appointmentForm');
                const submitBtn = document.getElementById('confirmBtn');
                const btnText = submitBtn.querySelector('span:last-child');
                const btnIcon = submitBtn.querySelector('i');

                // Modal elements
                const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                const confirmationSummary = document.getElementById('confirmationSummary');
                const finalConfirmBtn = document.getElementById('finalConfirmBtn');
                let allowSubmit = false;

                form.addEventListener('submit', function(e) {
                    if (!allowSubmit) {
                        e.preventDefault();
                        console.log('Preventing default submit, showing modal');
                        // Gather form data
                        const purpose = document.getElementById('purposeSelect').value;
                        const attendees = document.querySelector('input[name="attendees"]').value;
                        const selectedDate = document.getElementById('selectedDateInput').value;
                        const selectedTime = document.getElementById('selectedTimeInput').value;
                        const otherDetails = document.querySelector('textarea[name="other_details"]').value;
                        const selectedSlot = Array.from(document.querySelectorAll('.time-slot.selected'))[0];
                        console.log('Selected slot element:', selectedSlot);
                        console.log('Form validation check:', {
                            purpose: purpose,
                            attendees: attendees,
                            selectedDate: selectedDate,
                            selectedTime: selectedTime,
                            selectedSlot: selectedSlot,
                            isUnavailable: selectedSlot ? selectedSlot.classList.contains('unavailable') : 'no slot'
                        });
                        // Simplified validation - only check essential fields
                        if (!purpose || !attendees || !selectedDate || !selectedTime) {
                            // Show toast notification instead of alert
                            const toastContainer = document.getElementById('toastContainer');
                            if (!toastContainer) {
                                const container = document.createElement('div');
                                container.id = 'toastContainer';
                                container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
                                document.body.appendChild(container);
                            }
                            const toast = document.createElement('div');
                            toast.className = 'toast show';
                            toast.style.cssText = 'background: #dc3545; color: white; border-radius: 8px; padding: 15px 20px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);';
                            toast.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Please select a valid, available time slot.';
                            document.getElementById('toastContainer').appendChild(toast);
                            setTimeout(() => {
                                toast.remove();
                            }, 4000);
                            return;
                        }
                        // Build summary HTML
                        let summaryHtml = `
                            <div style="text-align: center; margin-bottom: 25px;">
                                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="bi bi-calendar-check" style="font-size: 2.5rem; color: white;"></i>
                                </div>
                                <h6 style="color: #28a745; font-weight: 600; margin-bottom: 5px;">Appointment Summary</h6>
                                <p style="color: #6c757d; margin: 0;">Please review your appointment details below</p>
                            </div>
                            <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                                <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-bottom: 10px;">
                                    <div style="color: #6c757d; font-size: 0.9rem; margin-bottom: 5px;"><i class="bi bi-calendar me-1"></i>Requested Appointment Date & Time</div>
                                    <div style="font-weight: 600; color: #333;">
                                        ${selectedDate && selectedDate.trim() !== '' ? 
                                            `${selectedDate} at ${selectedTime}` : 
                                            '<span style="color: #dc3545; font-weight: 700;">Please select a date</span>'
                                        }
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                                        <div style="color: #6c757d; font-size: 0.9rem; margin-bottom: 5px;"><i class="bi bi-file-text me-1"></i>Purpose</div>
                                        <div style="font-weight: 600; color: #333;">${purpose}</div>
                                    </div>
                                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-top: 10px;">
                                        <div style="color: #6c757d; font-size: 0.9rem; margin-bottom: 5px;"><i class="bi bi-people me-1"></i>Attendees</div>
                                        <div style="font-weight: 600; color: #333;">${attendees} person(s)</div>
                                    </div>
                                    ${otherDetails ? `
                                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-top: 10px;">
                                        <div style="color: #6c757d; font-size: 0.9rem; margin-bottom: 5px;"><i class="bi bi-chat-text me-1"></i>Additional Details</div>
                                        <div style="font-weight: 600; color: #333;">${otherDetails}</div>
                                    </div>
                                    ` : ''}
                                    ${document.getElementById('fileInput').files.length > 0 ? `
                                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-top: 10px;">
                                        <div style="color: #6c757d; font-size: 0.9rem; margin-bottom: 10px;"><i class="bi bi-paperclip me-1"></i>Attachment(tap to preview)</div>
                                        <div id="modalAttachmentPreviewList" style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px; max-height: 200px; overflow-y: auto;">
                                            ${Array.from(document.getElementById('fileInput').files).map((file, idx) => `
                                                <div class="modal-attachment-card" data-idx="${idx}" style="position: relative; width: 120px; height: 140px; border-radius: 8px; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.1); transition: all 0.3s; background: #f8f9fa; cursor: pointer;">
                                                    <div style="height: 90px; display: flex; align-items: center; justify-content: center; background: #e9ecef;">
                                                        <i class="bi ${getFileIcon(file.name.split('.').pop().toLowerCase())}" style="font-size: 2.5rem; color: #6c757d;"></i>
                                                    </div>
                                                    <div style="padding: 8px; text-align: center;">
                                                        <div style="font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #495057;">${file.name}</div>
                                                        <div style="font-size: 0.65rem; color: #868e96;">${formatFileSize(file.size)}</div>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div style="background: #e8f5e8; border: 1px solid #28a745; border-radius: 8px; padding: 15px; text-align: center;">
                                <i class="bi bi-info-circle me-2" style="color: #28a745;"></i>
                                <span style="color: #28a745; font-weight: 500;">Your appointment will be submitted for approval</span>
                            </div>
                        `;
                        console.log('About to show confirmation modal');
                        confirmationSummary.innerHTML = summaryHtml;
                        confirmationModal.show();
                        console.log('Modal should be visible now');
                    }
                });

                console.log('Setting up final confirm button listener, button element:', finalConfirmBtn);
                finalConfirmBtn.addEventListener('click', function() {
                    console.log('Final confirm button clicked');
                    allowSubmit = true;
                    console.log('About to submit form');
                    
                    // Debug: Log form data before submission
                    const formData = new FormData(form);
                    for (let [key, value] of formData.entries()) {
                        console.log('Form data:', key, '=', value);
                    }
                    
                    form.submit();
                });
            }
            
            function setupFileAttachments() {
                const fileInput = document.getElementById('fileInput');
                const fileList = document.getElementById('fileList');
                const attachmentPreview = document.getElementById('attachmentPreview');
                
                fileInput.addEventListener('change', function() {
                    fileList.innerHTML = '';
                    
                    if (this.files.length > 0) {
                        attachmentPreview.style.display = 'block';
                        
                        for (let i = 0; i < this.files.length; i++) {
                            const file = this.files[i];
                            const fileSize = formatFileSize(file.size);
                            const fileExt = getFileExtension(file.name);
                            
                            const attachmentItem = document.createElement('div');
                            attachmentItem.className = 'attachment-item';
                            
                            attachmentItem.innerHTML = `
                                <div class="attachment-icon">
                                    <i class="bi ${getFileIcon(fileExt)}"></i>
                                </div>
                                <div class="attachment-info">
                                    <div class="attachment-name">${file.name}</div>
                                    <div class="attachment-size">${fileSize}</div>
                                </div>
                                <div class="attachment-remove" data-index="${i}">
                                    <i class="bi bi-x-lg"></i>
                                </div>
                            `;
                            
                            fileList.appendChild(attachmentItem);
                        }
                    } else {
                        attachmentPreview.style.display = 'none';
                    }
                });
                
                // Handle file removal
                fileList.addEventListener('click', function(e) {
                    if (e.target.closest('.attachment-remove')) {
                        const index = e.target.closest('.attachment-remove').dataset.index;
                        
                        // Create a new DataTransfer object to update the file input
                        const dataTransfer = new DataTransfer();
                        const files = Array.from(fileInput.files);
                        
                        // Remove the file at the specified index
                        files.splice(index, 1);
                        
                        // Add the remaining files to the DataTransfer object
                        files.forEach(file => {
                            dataTransfer.items.add(file);
                        });
                        
                        // Update the file input
                        fileInput.files = dataTransfer.files;
                        
                        // Trigger the change event to update the preview
                        const event = new Event('change');
                        fileInput.dispatchEvent(event);
                    }
                });
            }
            
            function getFileExtension(filename) {
                return filename.slice((filename.lastIndexOf('.') + 1)).toLowerCase();
            }
            
            function getFileIcon(extension) {
                const icons = {
                    'pdf': 'bi-file-earmark-pdf',
                    'doc': 'bi-file-earmark-word',
                    'docx': 'bi-file-earmark-word',
                    'jpg': 'bi-file-earmark-image',
                    'jpeg': 'bi-file-earmark-image',
                    'png': 'bi-file-earmark-image'
                };
                
                return icons[extension] || 'bi-file-earmark';
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // Purpose select show/hide 'Other' details
            var purposeSelect = document.getElementById('purposeSelect');
            var otherDetailsContainer = document.getElementById('otherDetailsContainer');
            if (purposeSelect) {
                purposeSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        otherDetailsContainer.style.display = '';
                    } else {
                        otherDetailsContainer.style.display = 'none';
                    }
                });
                // On page load, check if 'Other' is selected
                if (purposeSelect.value === 'Other') {
                    otherDetailsContainer.style.display = '';
                }
            }

            function updateTimeSlots(unavailableSlots) {
                // AM slots
                document.querySelectorAll('.time-slots').forEach((container, idx) => {
                    const slots = idx === 0 ? <?php echo json_encode($am_slots); ?> : <?php echo json_encode($pm_slots); ?>;
                    container.innerHTML = '';
                    slots.forEach(slot => {
                        if (unavailableSlots.includes(slot)) {
                            container.innerHTML += `<div class="time-slot unavailable" style="background:#eee;color:#aaa;cursor:not-allowed;pointer-events:none;opacity:0.7;"><i class="bi bi-hourglass-split"></i> ${slot}</div>`;
                        } else {
                            container.innerHTML += `<div class="time-slot">${slot}</div>`;
                        }
                    });
                });
                initTimeSlots(); // re-attach click handlers
            }

            function formatTimeToDisplay(timeStr) {
                // timeStr is like '09:00:00'
                const [hour, minute] = timeStr.split(':');
                let h = parseInt(hour, 10);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${h}:${minute} ${ampm}`;
            }

            // Pass PHP slot arrays to JS
            const amSlots = <?php echo json_encode($am_slots); ?>;
            const pmSlots = <?php echo json_encode($pm_slots); ?>;
            // Initial render for today (or selected date if any)
            let selectedDate = document.getElementById('selectedDateInput').value || new Date().toISOString().slice(0,10);
            renderTimeSlots([], amSlots, pmSlots);

            function renderTimeSlots(unavailableSlots, amSlots, pmSlots) {
                unavailableSlotsGlobal = unavailableSlots; // Save for re-render
                // unavailableSlots is now an array of objects: [{time: '9:00 AM', status: 'pending'|'approved'}]
                // If not, convert it for backward compatibility
                let slotStatusMap = {};
                if (unavailableSlots.length && typeof unavailableSlots[0] === 'object' && unavailableSlots[0].time) {
                    unavailableSlots.forEach(s => {
                        slotStatusMap[s.time.toUpperCase().trim()] = s.status;
                    });
                } else {
                    // fallback: treat all as pending
                    unavailableSlots.forEach(s => {
                        slotStatusMap[s.toUpperCase().trim()] = 'pending';
                    });
                }
                const amContainer = document.getElementById('am-slots');
                amContainer.innerHTML = '';
                amSlots.forEach(slot => {
                    slot = slot.replace(/✔|✓|<.*?>/g, '').trim();
                    let slotClass = '';
                    let icon = '';
                    let style = '';
                    let isSelected = (slot === selectedSlot);
                    let tooltip = '';
                    let status = slotStatusMap[slot.toUpperCase().trim()];
                    // --- NEW LOGIC: Mark past slots as unavailable for today ---
                    let selectedDateStr = document.getElementById('selectedDateInput').value;
                    let now = new Date();
                    let isToday = false;
                    if (selectedDateStr) {
                        let todayStr = now.toISOString().slice(0,10);
                        isToday = (selectedDateStr === todayStr);
                    }
                    let slotIsPast = false;
                    if (isToday) {
                        // Parse slot time (e.g., "8:00 AM")
                        let [time, meridian] = slot.split(' ');
                        let [hour, minute] = time.split(':');
                        hour = parseInt(hour, 10);
                        minute = parseInt(minute, 10);
                        if (meridian === 'PM' && hour !== 12) hour += 12;
                        if (meridian === 'AM' && hour === 12) hour = 0;
                        let slotDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
                        if (slotDate < now) slotIsPast = true;
                    }
                    if (slotIsPast && !status) {
                        status = 'unavailable';
                    }
                    // --- END NEW LOGIC ---
                    if (status === 'approved') {
                        slotClass = 'time-slot approved';
                        icon = `<i class="bi bi-check-circle-fill" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#e6f9ed;color:#218838;border:2px solid #28a745;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is approved.';
                    } else if (status === 'pending') {
                        slotClass = 'time-slot pending';
                        icon = `<i class="bi bi-hourglass-split" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#fff8e1;color:#bfa700;border:2px solid #ffc107;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is pending, just waiting for approval';
                    } else if (status === 'unavailable') {
                        slotClass = 'time-slot unavailable';
                        icon = `<i class="bi bi-hourglass-bottom" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eee;color:#aaa;border:1px solid #bbb;min-width:100px;padding:6px 12px;white-space:nowrap;opacity:0.7;cursor:not-allowed;pointer-events:none;';
                        tooltip = 'This timeslot is unavailable (time has passed).';
                    } else {
                        slotClass = 'time-slot available';
                        icon = `<i class="bi bi-calendar-plus-fill" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eaf3fb;color:#2563eb;border:2px solid #2563eb;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is available';
                    }
                    const slotHTML = `<div class="${slotClass}${isSelected ? ' selected' : ''}" style="${style}" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}">${icon}<span style="font-weight:600;white-space:nowrap;">${slot}</span></div>`;
                    console.log('AM Slot HTML:', slotHTML);
                    amContainer.innerHTML += slotHTML;
                });
                const pmContainer = document.getElementById('pm-slots');
                pmContainer.innerHTML = '';
                pmSlots.forEach(slot => {
                    slot = slot.replace(/✔|✓|<.*?>/g, '').trim();
                    let slotClass = '';
                    let icon = '';
                    let style = '';
                    let isSelected = (slot === selectedSlot);
                    let tooltip = '';
                    let status = slotStatusMap[slot.toUpperCase().trim()];
                    // --- NEW LOGIC: Mark past slots as unavailable for today ---
                    let selectedDateStr = document.getElementById('selectedDateInput').value;
                    let now = new Date();
                    let isToday = false;
                    if (selectedDateStr) {
                        let todayStr = now.toISOString().slice(0,10);
                        isToday = (selectedDateStr === todayStr);
                    }
                    let slotIsPast = false;
                    if (isToday) {
                        // Parse slot time (e.g., "1:00 PM")
                        let [time, meridian] = slot.split(' ');
                        let [hour, minute] = time.split(':');
                        hour = parseInt(hour, 10);
                        minute = parseInt(minute, 10);
                        if (meridian === 'PM' && hour !== 12) hour += 12;
                        if (meridian === 'AM' && hour === 12) hour = 0;
                        let slotDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
                        if (slotDate < now) slotIsPast = true;
                    }
                    if (slotIsPast && !status) {
                        status = 'unavailable';
                    }
                    // --- END NEW LOGIC ---
                    if (status === 'approved') {
                        slotClass = 'time-slot approved';
                        icon = `<i class="bi bi-check-circle-fill" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#e6f9ed;color:#218838;border:2px solid #28a745;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is approved.';
                    } else if (status === 'pending') {
                        slotClass = 'time-slot pending';
                        icon = `<i class="bi bi-hourglass-split" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#fff8e1;color:#bfa700;border:2px solid #ffc107;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is pending, just waiting for approval';
                    } else if (status === 'unavailable') {
                        slotClass = 'time-slot unavailable';
                        icon = `<i class="bi bi-hourglass-bottom" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eee;color:#aaa;border:1px solid #bbb;min-width:100px;padding:6px 12px;white-space:nowrap;opacity:0.7;cursor:not-allowed;pointer-events:none;';
                        tooltip = 'This timeslot is unavailable (time has passed).';
                    } else {
                        slotClass = 'time-slot available';
                        icon = `<i class="bi bi-calendar-plus-fill" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eaf3fb;color:#2563eb;border:2px solid #2563eb;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is available';
                    }
                    const slotHTML = `<div class="${slotClass}${isSelected ? ' selected' : ''}" style="${style}" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}">${icon}<span style="font-weight:600;white-space:nowrap;">${slot}</span></div>`;
                    console.log('PM Slot HTML:', slotHTML);
                    pmContainer.innerHTML += slotHTML;
                });
                initTimeSlots();
                // Re-initialize Bootstrap tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                    const tooltipInstance = new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' });
                    tooltipTriggerEl.addEventListener('click', function() {
                        tooltipInstance.hide();
                    });
                    tooltipTriggerEl.addEventListener('touchstart', function() {
                        tooltipInstance.hide();
                    });
                });
            }

            // On calendar day click, fetch unavailable slots and re-render
            function fetchAndRenderSlots(date) {
                fetch('get_pending_slots.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'date=' + encodeURIComponent(date)
                })
                .then(response => response.json())
                .then(unavailableSlots => {
                    console.log('Unavailable slots:', unavailableSlots); // Debugging line
                    renderTimeSlots(unavailableSlots, amSlots, pmSlots);
                });
            }

            // Update calendar day click handler to use fetchAndRenderSlots
            function updateCalendarDayHandlers() {
                const calendarDays = document.querySelectorAll('.calendar-day:not(.disabled)');
                calendarDays.forEach(day => {
                    day.addEventListener('click', function() {
                        calendarDays.forEach(d => d.classList.remove('selected'));
                        this.classList.add('selected');
                        selectedDate = this.dataset.date;
                        document.getElementById('selectedDateInput').value = selectedDate;
                        fetchAndRenderSlots(selectedDate);
                    });
                });
            }
            // Call after calendar is rendered
            updateCalendarDayHandlers();

            // On page load, fetch slots for initial date
            fetchAndRenderSlots(selectedDate);
            updateSelectedSummary();

            // Show a summary of the selected date and time
            function updateSelectedSummary() {
                console.log('updateSelectedSummary called');
                let summary = document.getElementById('selectedSummary');
                console.log('Summary element found:', summary);
                const date = document.getElementById('selectedDateInput').value;
                const time = document.getElementById('selectedTimeInput').value;
                console.log('Date:', date, 'Time:', time);
                console.log('Date length:', date ? date.length : 0, 'Time length:', time ? time.length : 0);
                if (time) { // Show summary if time is selected, even without date
                    console.log('Time selected, showing summary');
                    console.log('About to set summary HTML');
                    summary.innerHTML = `
                      <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px; padding: 20px; margin: 20px 0; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                          <div style="display: flex; align-items: center; gap: 15px;">
                              <span style='font-size: 2.5rem;'><i class="bi bi-calendar-check"></i></span>
                              <div>
                                  <h5 style="margin: 0; font-weight: 600; font-size: 1.3rem;">Your Requested Date & Time</h5>
                                  <div style="font-size: 1.2rem; margin-top: 8px;">
                                      <strong>Time:</strong> ${time}<br>
                                      ${date && date.trim() !== '' ? `<strong>Date:</strong> ${date}` : '<span style="color: #dc3545; font-weight: 700;">Please select a date</span>'}
                                  </div>
                              </div>
                          </div>
                      </div>
                   `;
                   summary.style.display = '';
                   console.log('Summary displayed');
                   console.log('Summary HTML set, display should be visible');
                } else if (summary) {
                    console.log('No time selected, hiding summary');
                    summary.innerHTML = '';
                    summary.style.display = 'none';
                }
            }

            // Attachment preview modal logic
            document.addEventListener('click', function(e) {
                if (e.target.closest('.modal-attachment-card')) {
                    const card = e.target.closest('.modal-attachment-card');
                    const idx = parseInt(card.getAttribute('data-idx'));
                    const files = document.getElementById('fileInput').files;
                    const file = files[idx];
                    const previewModal = new bootstrap.Modal(document.getElementById('attachmentPreviewModal'));
                    const previewBody = document.getElementById('attachmentPreviewBody');
                    previewBody.innerHTML = '<div style="color:#888;font-size:1.2rem;">Loading preview...</div>';
                    const ext = file.name.split('.').pop().toLowerCase();
                    
                    // Enhanced file type support
                    if (["jpg","jpeg","png","gif","bmp","webp","svg"].includes(ext)) {
                        // Image files
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            previewBody.innerHTML = `<img src="${ev.target.result}" style="max-width:100%;max-height:70vh;border-radius:10px;box-shadow:0 2px 8px #0002;">`;
                        };
                        reader.readAsDataURL(file);
                    } else if (ext === "pdf") {
                        // PDF files
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            previewBody.innerHTML = `<embed src="${ev.target.result}" type="application/pdf" width="100%" height="500px" style="border-radius:10px;box-shadow:0 2px 8px #0002;"/>`;
                        };
                        reader.readAsDataURL(file);
                    } else if (["doc","docx"].includes(ext)) {
                        // Word documents - try to extract text content for preview
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            const arrayBuffer = ev.target.result;
                            previewBody.innerHTML = `
                                <div style="text-align:center;padding:20px;">
                                    <i class="bi bi-file-earmark-word text-primary" style="font-size:3rem;margin-bottom:15px;"></i>
                                    <h5>Word Document Preview</h5>
                                    <p class="text-muted mb-3">Extracting document content...</p>
                                    <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                                        <p><strong>File Name:</strong> ${file.name}</p>
                                        <p><strong>File Type:</strong> Word Document (.${ext})</p>
                                        <p><strong>File Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                                    </div>
                                    <div style="margin-top:20px;">
                                        <button id="previewContent" class="btn btn-primary me-2">
                                            <i class="bi bi-eye me-1"></i>Preview Content
                                        </button>
                                        <a href="#" id="downloadAttachment" class="btn btn-outline-primary">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            `;
                            
                            // Preview content button
                            document.getElementById('previewContent').onclick = function() {
                                // For .docx files, try to extract text content
                                if (ext === 'docx') {
                                    // Create a simple text preview by reading as text
                                    const textReader = new FileReader();
                                    textReader.onload = function(e) {
                                        const content = e.target.result;
                                        const previewText = content.substring(0, 1000) + (content.length > 1000 ? '...' : '');
                                        
                                        previewBody.innerHTML = `
                                            <div style="text-align:left;padding:20px;">
                                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                                                    <h5><i class="bi bi-file-earmark-word text-primary me-2"></i>Document Content Preview</h5>
                                                    <button onclick="closePreview()" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </div>
                                                <div style="background:white;border:1px solid #dee2e6;border-radius:8px;padding:20px;max-height:400px;overflow-y:auto;">
                                                    <pre style="white-space:pre-wrap;font-family:'Courier New',monospace;font-size:14px;margin:0;">${previewText}</pre>
                                                </div>
                                                <div style="margin-top:15px;text-align:center;">
                                                    <small class="text-muted">Showing first 1000 characters. Download the file to view the complete document.</small>
                                                </div>
                                            </div>
                                        `;
                                    };
                                    textReader.readAsText(file);
                                } else {
                                    // For .doc files, show a message
                                    previewBody.innerHTML = `
                                        <div style="text-align:center;padding:20px;">
                                            <i class="bi bi-file-earmark-word text-primary" style="font-size:3rem;margin-bottom:15px;"></i>
                                            <h5>Word Document (.doc)</h5>
                                            <p class="text-muted">This is a legacy Word document format. Please download the file to view its contents.</p>
                                            <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                                                <p><strong>File Name:</strong> ${file.name}</p>
                                                <p><strong>File Type:</strong> Word Document (.${ext})</p>
                                                <p><strong>File Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                                            </div>
                                        </div>
                                    `;
                                }
                            };
                            
                            document.getElementById('downloadAttachment').onclick = function(ev) {
                                ev.preventDefault();
                                const url = URL.createObjectURL(file);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = file.name;
                                document.body.appendChild(a);
                                a.click();
                                setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 100);
                            };
                        };
                        reader.readAsArrayBuffer(file);
                    } else if (["xls","xlsx"].includes(ext)) {
                        // Excel files - try to extract CSV-like content for preview
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            const arrayBuffer = ev.target.result;
                            previewBody.innerHTML = `
                                <div style="text-align:center;padding:20px;">
                                    <i class="bi bi-file-earmark-excel text-success" style="font-size:3rem;margin-bottom:15px;"></i>
                                    <h5>Excel Spreadsheet Preview</h5>
                                    <p class="text-muted mb-3">Extracting spreadsheet data...</p>
                                    <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                                        <p><strong>File Name:</strong> ${file.name}</p>
                                        <p><strong>File Type:</strong> Excel Spreadsheet (.${ext})</p>
                                        <p><strong>File Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                                    </div>
                                    <div style="margin-top:20px;">
                                        <button id="previewContent" class="btn btn-success me-2">
                                            <i class="bi bi-eye me-1"></i>Preview Data
                                        </button>
                                        <a href="#" id="downloadAttachment" class="btn btn-outline-success">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            `;
                            
                            // Preview content button
                            document.getElementById('previewContent').onclick = function() {
                                // Try to read as text to extract CSV-like data
                                const textReader = new FileReader();
                                textReader.onload = function(e) {
                                    const content = e.target.result;
                                    const lines = content.split('\n').slice(0, 20); // Show first 20 lines
                                    const previewText = lines.join('\n');
                                    
                                    previewBody.innerHTML = `
                                        <div style="text-align:left;padding:20px;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                                                <h5><i class="bi bi-file-earmark-excel text-success me-2"></i>Spreadsheet Data Preview</h5>
                                                <button onclick="closePreview()" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                            <div style="background:white;border:1px solid #dee2e6;border-radius:8px;padding:20px;max-height:400px;overflow-y:auto;">
                                                <pre style="white-space:pre-wrap;font-family:'Courier New',monospace;font-size:12px;margin:0;">${previewText}</pre>
                                            </div>
                                            <div style="margin-top:15px;text-align:center;">
                                                <small class="text-muted">Showing first 20 rows. Download the file to view the complete spreadsheet.</small>
                                            </div>
                                        </div>
                                    `;
                                };
                                textReader.readAsText(file);
                            };
                            
                            document.getElementById('downloadAttachment').onclick = function(ev) {
                                ev.preventDefault();
                                const url = URL.createObjectURL(file);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = file.name;
                                document.body.appendChild(a);
                                a.click();
                                setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 100);
                            };
                        };
                        reader.readAsArrayBuffer(file);
                    } else if (["ppt","pptx"].includes(ext)) {
                        // PowerPoint files - show presentation info
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            const arrayBuffer = ev.target.result;
                            previewBody.innerHTML = `
                                <div style="text-align:center;padding:20px;">
                                    <i class="bi bi-file-earmark-ppt text-warning" style="font-size:3rem;margin-bottom:15px;"></i>
                                    <h5>PowerPoint Presentation Preview</h5>
                                    <p class="text-muted mb-3">Presentation file detected</p>
                                    <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                                        <p><strong>File Name:</strong> ${file.name}</p>
                                        <p><strong>File Type:</strong> PowerPoint Presentation (.${ext})</p>
                                        <p><strong>File Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                                    </div>
                                    <div style="margin-top:20px;">
                                        <button id="previewContent" class="btn btn-warning me-2">
                                            <i class="bi bi-eye me-1"></i>View Details
                                        </button>
                                        <a href="#" id="downloadAttachment" class="btn btn-outline-warning">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            `;
                            
                            // Preview content button
                            document.getElementById('previewContent').onclick = function() {
                                previewBody.innerHTML = `
                                    <div style="text-align:left;padding:20px;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                                            <h5><i class="bi bi-file-earmark-ppt text-warning me-2"></i>Presentation Details</h5>
                                            <button onclick="closePreview()" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                        <div style="background:white;border:1px solid #dee2e6;border-radius:8px;padding:20px;">
                                            <div style="text-align:center;padding:20px;">
                                                <i class="bi bi-file-earmark-ppt text-warning" style="font-size:4rem;margin-bottom:15px;"></i>
                                                <h6>PowerPoint Presentation</h6>
                                                <p class="text-muted">This is a PowerPoint presentation file.</p>
                                                <div style="background:#f8f9fa;border-radius:8px;padding:15px;margin:15px 0;">
                                                    <p><strong>File:</strong> ${file.name}</p>
                                                    <p><strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                                                    <p><strong>Type:</strong> ${ext.toUpperCase()} format</p>
                                                </div>
                                                <p class="text-muted small">Download the file to view the complete presentation with slides, animations, and formatting.</p>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            };
                            
                            document.getElementById('downloadAttachment').onclick = function(ev) {
                                ev.preventDefault();
                                const url = URL.createObjectURL(file);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = file.name;
                                document.body.appendChild(a);
                                a.click();
                                setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 100);
                            };
                        };
                        reader.readAsArrayBuffer(file);
                    } else if (["txt","md","log","csv"].includes(ext)) {
                        // Text files
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            const content = ev.target.result;
                            previewBody.innerHTML = `
                                <div style="background:#f8f9fa;border-radius:10px;padding:20px;max-height:500px;overflow-y:auto;">
                                    <h6 style="margin-bottom:15px;"><i class="bi bi-file-text me-2"></i>Text File Preview</h6>
                                    <pre style="background:white;padding:15px;border-radius:5px;border:1px solid #dee2e6;white-space:pre-wrap;font-family:monospace;font-size:0.9rem;">${content}</pre>
                                </div>
                            `;
                        };
                        reader.readAsText(file);
                    } else {
                        // Other file types - show file info and download option
                        const fileSize = (file.size / 1024 / 1024).toFixed(2);
                        previewBody.innerHTML = `
                            <div style="text-align:center;padding:30px;">
                                <i class="bi bi-file-earmark-text text-muted" style="font-size:4rem;margin-bottom:20px;"></i>
                                <h5>File Preview</h5>
                                <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                                    <p><strong>File Name:</strong> ${file.name}</p>
                                    <p><strong>File Type:</strong> ${ext.toUpperCase()} file</p>
                                    <p><strong>File Size:</strong> ${fileSize} MB</p>
                                    <p><strong>Upload Date:</strong> ${new Date().toLocaleDateString()}</p>
                                </div>
                                <p class="text-muted">This file type cannot be previewed directly.</p>
                                <a href="#" id="downloadAttachment" class="btn btn-primary">
                                    <i class="bi bi-download me-1"></i>Download File
                                </a>
                            </div>
                        `;
                        document.getElementById('downloadAttachment').onclick = function(ev) {
                            ev.preventDefault();
                            const url = URL.createObjectURL(file);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = file.name;
                            document.body.appendChild(a);
                            a.click();
                            setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 100);
                        };
                    }
                    previewModal.show();
                }
            });

            // Notification dismissal function - make it globally accessible
            window.dismissNotification = function(appointmentId) {
                const notification = document.querySelector(`[data-notification-id="${appointmentId}"]`);
                if (notification) {
                    // Send AJAX request to dismiss notification
                    const formData = new FormData();
                    formData.append('notification_id', appointmentId);
                    
                    fetch('dismiss_notification.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the notification item
                            notification.remove();
                            
                            // Update the notification count
                            updateNotificationCount();
                        }
                    })
                    .catch(error => {
                        // Still remove the notification locally even if server request fails
                        notification.remove();
                    });
                }
            }
            
            // Update notification count and badge
            function updateNotificationCount() {
                const notificationItems = document.querySelectorAll('.notification-item');
                const badge = document.querySelector('.notification-badge');
                const dropdown = document.getElementById('notificationDropdown');
                
                console.log('Updating notification count. Found items:', notificationItems.length);
                
                if (notificationItems.length === 0) {
                    // No more notifications, hide badge and show empty state
                    if (badge) {
                        badge.style.display = 'none';
                        console.log('Hiding notification badge');
                    }
                    if (dropdown) {
                        // Replace dropdown content with empty state
                        dropdown.innerHTML = `
                            <div class="notification-header">
                                <h6><i class="bi bi-bell me-2"></i>Notifications</h6>
                                <button type="button" class="btn-close" onclick="toggleNotifications()" title="Close">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <div class="notification-empty">
                                <i class="bi bi-bell-slash"></i>
                                <h6>No notifications</h6>
                                <p>You're all caught up!</p>
                            </div>
                        `;
                    }
                } else {
                    // Update badge count
                    if (badge) {
                        badge.textContent = notificationItems.length;
                        badge.style.display = 'flex';
                        console.log('Updated badge count to:', notificationItems.length);
                    }
                }
            }

            // Function to dismiss a single notification
            function dismissNotification(notificationId) {
                console.log('Dismissing notification:', notificationId);
                const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notification) {
                    console.log('Found notification element, sending dismiss request');
                    // Send AJAX request to dismiss notification
                    fetch('dismiss_notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `notification_id=${notificationId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Dismiss response:', data);
                        if (data.success) {
                            // Remove the notification from DOM
                            notification.remove();
                            console.log('Notification removed from DOM');
                            // Update the notification count
                            updateNotificationCount();
                        }
                    })
                    .catch(error => {
                        console.error('Error dismissing notification:', error);
                        // Still remove the notification locally even if server request fails
                        notification.remove();
                        updateNotificationCount();
                    });
                } else {
                    console.log('Notification element not found for ID:', notificationId);
                }
            }

            // Simple toggle function for notifications
            window.toggleNotifications = function() {
                console.log('toggleNotifications called');
                const dropdown = document.getElementById('notificationDropdown');
                console.log('Dropdown element:', dropdown);
                if (dropdown) {
                    dropdown.classList.toggle('show');
                    console.log('Dropdown toggled, show class:', dropdown.classList.contains('show'));
                } else {
                    console.log('No dropdown found');
                    alert('No notifications to display');
                }
            }
            
            // Notification Bell System
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Setting up notification bell system');
                const notificationBell = document.getElementById('notificationBell');
                const notificationDropdown = document.getElementById('notificationDropdown');
                
                console.log('Bell element:', notificationBell);
                console.log('Dropdown element:', notificationDropdown);
                
                            // Initialize notification count on page load
            console.log('Page loaded, initializing notification system...');
            updateNotificationCount();
            
            // Debug: Log all notification items
            const allNotifications = document.querySelectorAll('.notification-item');
            console.log('Found notification items:', allNotifications.length);
            console.log('Notification dropdown HTML:', document.getElementById('notificationDropdown')?.innerHTML);
            allNotifications.forEach((item, index) => {
                console.log(`Notification ${index + 1}:`, {
                    id: item.getAttribute('data-notification-id'),
                    classes: item.className,
                    status: item.classList.contains('approved') ? 'approved' : 
                           item.classList.contains('declined') ? 'declined' : 
                           item.classList.contains('cancelled') ? 'cancelled' : 
                           item.classList.contains('rescheduled') ? 'rescheduled' : 'unknown',
                    element: item
                });
            });
                
                if (notificationBell) {
                    console.log('Setting up click listener for bell');
                    // Toggle notification dropdown
                    notificationBell.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Bell clicked');
                        toggleNotifications();
                    });
                    
                    // Close dropdown when clicking outside
                    document.addEventListener('click', function(e) {
                        if (notificationDropdown && !notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                            notificationDropdown.classList.remove('show');
                        }
                    });
                    
                    // Add click event listeners for notification close buttons using event delegation
                    document.addEventListener('click', function(e) {
                        if (e.target.closest('.notification-close')) {
                            e.preventDefault();
                            e.stopPropagation();
                            const button = e.target.closest('.notification-close');
                            const notificationId = button.getAttribute('data-notification-id');
                            console.log('Close button clicked for notification:', notificationId);
                            if (notificationId) {
                                dismissNotification(notificationId);
                            }
                        }
                    });

                    // Add hover effect to hide badge on hover
                    notificationBell.addEventListener('mouseenter', function() {
                        const badge = this.querySelector('.notification-badge');
                        if (badge && badge.style.display !== 'none') {
                            badge.style.opacity = '0';
                            console.log('Hiding badge on hover');
                        }
                    });

                    notificationBell.addEventListener('mouseleave', function() {
                        const badge = this.querySelector('.notification-badge');
                        if (badge && badge.style.display !== 'none') {
                            badge.style.opacity = '1';
                            console.log('Showing badge on mouse leave');
                        }
                    });
                } else {
                    console.log('Bell element not found!');
                }
            });
            
            // Mark all notifications as read
            window.markAllAsRead = function() {
                const notificationItems = document.querySelectorAll('.notification-item');
                let dismissedCount = 0;
                
                notificationItems.forEach(item => {
                    const notificationId = item.getAttribute('data-notification-id');
                    if (notificationId) {
                        // Send AJAX request to dismiss notification
                        fetch('dismiss_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `notification_id=${notificationId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            dismissedCount++;
                            // Remove the notification from DOM
                            item.remove();
                            
                            // If all notifications have been processed, update the count
                            if (dismissedCount === notificationItems.length) {
                                updateNotificationCount();
                            }
                        })
                        .catch(error => {
                            dismissedCount++;
                            // Still remove the notification locally even if server request fails
                            item.remove();
                            
                            // If all notifications have been processed, update the count
                            if (dismissedCount === notificationItems.length) {
                                updateNotificationCount();
                            }
                        });
                    }
                });
            }

            // Function to navigate to appointment page and show appointment details
            window.goToAppointment = function(appointmentId) {
                // First dismiss the notification
                dismissNotification(appointmentId);
                
                // Then navigate to the appointment page and auto-open appointment details
                window.location.href = `userAppointment.php?show_appointment=${appointmentId}`;
            }

            // Function to close preview and return to file info
            window.closePreview = function() {
                const previewModal = document.getElementById('attachmentPreviewModal');
                if (previewModal) {
                    const previewBody = previewModal.querySelector('.modal-body');
                    // Reset to show file selection or close modal
                    previewModal.style.display = 'none';
                }
            }

            // File size validation
            document.getElementById('fileInput').addEventListener('change', function(e) {
                const files = e.target.files;
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                const errorDiv = document.getElementById('fileSizeError');
                const errorMessage = document.getElementById('fileSizeErrorMessage');
                const submitBtn = document.querySelector('button[type="submit"]');
                let hasError = false;
                let errorMessages = [];

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (file.size > maxSize) {
                        hasError = true;
                        errorMessages.push(`${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
                    }
                }

                if (hasError) {
                    errorMessage.textContent = `Files too large: ${errorMessages.join(', ')}. Maximum size is 5MB per file.`;
                    errorDiv.style.display = 'block';
                    // Disable submit button and show warning
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Cannot Submit - Files Too Large';
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-danger');
                    // Clear the file input
                    e.target.value = '';
                } else {
                    errorDiv.style.display = 'none';
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Book Appointment';
                    submitBtn.classList.remove('btn-danger');
                    submitBtn.classList.add('btn-primary');
                }
            });

            // Prevent form submission if files are too large
            document.getElementById('appointmentForm').addEventListener('submit', function(e) {
                const fileInput = document.getElementById('fileInput');
                const files = fileInput.files;
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                let hasLargeFiles = false;
                let largeFileNames = [];

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (file.size > maxSize) {
                        hasLargeFiles = true;
                        largeFileNames.push(file.name);
                    }
                }

                if (hasLargeFiles) {
                    e.preventDefault();
                    const errorDiv = document.getElementById('fileSizeError');
                    const errorMessage = document.getElementById('fileSizeErrorMessage');
                    errorMessage.textContent = `Cannot submit: Files too large - ${largeFileNames.join(', ')}. Maximum size is 5MB per file.`;
                    errorDiv.style.display = 'block';
                    
                    // Scroll to error message
                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
            });
        });
    </script>
</body>
</html>