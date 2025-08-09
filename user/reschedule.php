<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: landingPage.php');
    exit();
}

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    header('Location: userAppointment.php');
    exit();
}

$appointment_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Database connection
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_auth_db";
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch appointment details
$sql = "SELECT 
            a.id, 
            a.purpose AS title, 
            COALESCE(s.name, 'General Service') AS service,
            a.service_id,
            a.date, 
            a.time, 
            a.status_enum,
            a.purpose,
            a.attendees,
            a.created_at,
            a.updated_at,
            a.other_details,
            a.attachments
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.id = ? AND a.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: userAppointment.php');
    exit();
}

$appointment = $result->fetch_assoc();

// For rescheduled appointments, always go to pending tab since they need approval again
$target_tab = 'pending';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    $reason = $_POST['reason'];
    
    // Convert the time format to ensure consistency
    // If the time is in 12-hour format (e.g., "4:00 PM"), convert it to 24-hour format for storage
    if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $new_time, $matches)) {
        $hour = intval($matches[1]);
        $minute = $matches[2];
        $period = strtoupper($matches[3]);
        
        if ($period === 'PM' && $hour !== 12) {
            $hour += 12;
        } elseif ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }
        
        $new_time = sprintf('%02d:%02d', $hour, $minute);
    }
    
    // Validate that the new date/time is in the future
    $new_datetime = strtotime($new_date . ' ' . $new_time);
    if ($new_datetime <= time()) {
        $error = "New appointment date and time must be in the future.";
    } else {
        // Check if the new slot is available
        $check_sql = "SELECT id FROM appointments WHERE date = ? AND time = ? AND status_enum IN ('pending', 'approved') AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $new_date, $new_time, $appointment_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "The selected date and time is not available. Please choose another slot.";
        } else {
            // Update the appointment and set status to Pending since it needs approval again
            $update_sql = "UPDATE appointments SET date = ?, time = ?, status_enum = 'Pending', updated_at = NOW() WHERE id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssii", $new_date, $new_time, $appointment_id, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['reschedule_success'] = true;
                header('Location: userAppointment.php?tab=' . $target_tab . '&show_appointment=' . $appointment_id);
                exit();
            } else {
                $error = "Failed to reschedule appointment. Please try again.";
            }
        }
    }
}

// Define time slots (same as userSide.php)
$am_slots = [
    "8:00 AM", "8:30 AM", "9:00 AM", "9:30 AM", "10:00 AM", "10:30 AM", "11:00 AM", "11:30 AM"
];
$pm_slots = [
    "1:00 PM", "1:30 PM", "2:00 PM", "2:30 PM", "3:00 PM", "3:30 PM", "4:00 PM", "4:30 PM", "5:00 PM"
];

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

// Add the current appointment being rescheduled to the counts for the new date
// This ensures the selected timeslot shows as pending in the calendar
if (isset($_POST['new_date']) && isset($_POST['new_time'])) {
    $new_date = $_POST['new_date'];
    if (!isset($appointment_counts[$new_date])) {
        $appointment_counts[$new_date] = [];
    }
    if (!isset($appointment_counts[$new_date]['Pending'])) {
        $appointment_counts[$new_date]['Pending'] = 0;
    }
    $appointment_counts[$new_date]['Pending']++;
}

// Fetch available time slots for the selected date
$available_slots = [];
if (isset($_POST['check_date'])) {
    $check_date = $_POST['check_date'];
    
    // Get booked slots
    $booked_sql = "SELECT time FROM appointments WHERE date = ? AND status_enum IN ('pending', 'approved') AND id != ?";
    $booked_stmt = $conn->prepare($booked_sql);
    $booked_stmt->bind_param("si", $check_date, $appointment_id);
    $booked_stmt->execute();
    $booked_result = $booked_stmt->get_result();
    
    $booked_times = [];
    while ($row = $booked_result->fetch_assoc()) {
        $booked_times[] = $row['time'];
    }
    
    // Filter available slots
    foreach ($time_slots as $slot) {
        if (!in_array($slot, $booked_times)) {
            $available_slots[] = $slot;
        }
    }
}

// Fetch services for dropdown
$services = [];
$serviceResult = $conn->query("SELECT id, name FROM services");
if ($serviceResult && $serviceResult->num_rows > 0) {
    while ($service = $serviceResult->fetch_assoc()) {
        $services[$service['id']] = $service['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - Solano Mayor's Office</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0055a4;
            --secondary-blue: #007bff;
            --success-green: #28a745;
            --warning-orange: #ffc107;
            --danger-red: #dc3545;
            --light-gray: #f8f9fa;
            --border-gray: #dee2e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f7fb;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .reschedule-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-top: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 600;
        }

        .card-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 40px;
        }

        .current-appointment {
            background: var(--light-gray);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-blue);
        }

        .current-appointment h3 {
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-item i {
            color: var(--primary-blue);
            font-size: 1.2rem;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
        }

        .reschedule-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h4 {
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            padding: 12px 15px;
            border: 2px solid var(--border-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            width: 100%;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 85, 164, 0.1);
            outline: none;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .time-slot {
            padding: 12px;
            border: 2px solid var(--border-gray);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .time-slot:hover {
            border-color: var(--primary-blue);
            background: #f8f9ff;
        }

        .time-slot.selected {
            border-color: var(--primary-blue);
            background: var(--primary-blue);
            color: white;
        }

        .time-slot.unavailable {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #e9ecef;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #004085;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 85, 164, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        /* Calendar Styles */
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
            justify-content: center;
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
        
        /* Time Slots Styles */
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
            background: #f7fafd;
            border-radius: 8px;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border 0.15s;
            border: 1px solid transparent;
            color: #000000;
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
        .time-slot.unavailable {
            background: #f6f6f6 !important;
            color: #bdbdbd !important;
            border: 1px solid #bdbdbd !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.6;
        }
        .time-slot.approved {
            background: #f0fdf4 !important;
            color: #4caf50 !important;
            border: 1px solid #4caf50 !important;
            font-weight: 500;
        }
        .time-slot.pending {
            background: #f5f6fa !important;
            color: #b1a06b !important;
            border: 1px solid #e5e7eb !important;
            font-weight: 500;
        }
        
        /* Legend dots */
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .legend-approved { background: #4caf50; }
        .legend-pending { background: #b1a06b; }
        .legend-available { background: #5a7bbd; }
        .legend-unavailable { background: #bdbdbd; }
        
        .time-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .time-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #eee;
        }
        .time-divider span {
            background: white;
            padding: 0 15px;
            color: #666;
            font-size: 0.9rem;
        }

        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger-red);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success-green);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-blue);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
            padding: 12px 20px;
            background: white;
            border: 2px solid var(--primary-blue);
            border-radius: 10px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 85, 164, 0.1);
        }

        .back-link:hover {
            color: white;
            background: var(--primary-blue);
            transform: translateX(-8px);
            box-shadow: 0 4px 15px rgba(0, 85, 164, 0.2);
        }

        .back-link i {
            font-size: 1.2rem;
        }

        /* Breadcrumb Navigation */
        .breadcrumb-nav {
            margin-bottom: 20px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            list-style: none;
            padding: 0;
            margin: 0;
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .breadcrumb-item:not(:last-child)::after {
            content: '/';
            margin: 0 15px;
            color: #ccc;
            font-weight: 300;
        }

        .breadcrumb-item a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .breadcrumb-item a:hover {
            color: #004085;
        }

        .breadcrumb-item.active {
            color: #666;
            font-weight: 600;
        }

        .breadcrumb-item.active a {
            color: #666;
            pointer-events: none;
        }

        /* Modal Styles */
        .current-appointment-summary,
        .new-appointment-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--primary-blue);
        }

        .new-appointment-summary {
            border-left-color: #ffc107;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
        }

        .modal-title {
            font-weight: 600;
        }

        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .card-header h1 {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
            }
            
            .time-slots {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="userSide.php">
                        <i class="bi bi-house"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="userAppointment.php?tab=<?= $target_tab ?>&show_appointment=<?= $appointment_id ?>">
                        <i class="bi bi-calendar-check"></i>
                        My Appointments
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <i class="bi bi-calendar-event"></i>
                    Reschedule
                </li>
            </ol>
        </nav>

        <a href="userAppointment.php?tab=<?= $target_tab ?>&show_appointment=<?= $appointment_id ?>" class="back-link">
            <i class="bi bi-arrow-left"></i>
            ← Back to My Appointments
        </a>

        <div class="reschedule-card">
            <div class="card-header">
                <h1><i class="bi bi-calendar-event me-3"></i>Reschedule Appointment</h1>
                <p>Select a new date and time for your appointment</p>
            </div>

            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Current Appointment Details -->
                <div class="current-appointment">
                    <h3><i class="bi bi-info-circle me-2"></i>Current Appointment Details</h3>
                    <div class="appointment-details">
                        <div class="detail-item">
                            <i class="bi bi-calendar-date"></i>
                            <div>
                                <div class="detail-label">Date</div>
                                <div class="detail-value"><?= date('F j, Y', strtotime($appointment['date'])) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="bi bi-clock"></i>
                            <div>
                                <div class="detail-label">Time</div>
                                <div class="detail-value"><?= date('g:i A', strtotime($appointment['time'])) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="bi bi-briefcase"></i>
                            <div>
                                <div class="detail-label">Service</div>
                                <div class="detail-value"><?= htmlspecialchars($appointment['service']) ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="bi bi-person"></i>
                            <div>
                                <div class="detail-label">Attendees</div>
                                <div class="detail-value"><?= htmlspecialchars($appointment['attendees']) ?> person(s)</div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <i class="bi bi-chat-text"></i>
                        <div>
                            <div class="detail-label">Purpose</div>
                            <div class="detail-value"><?= htmlspecialchars($appointment['purpose']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Reschedule Form -->
                <div class="reschedule-form">
                    <form method="POST" id="rescheduleForm">
                        <div class="form-section">
                            <h4><i class="bi bi-calendar-plus me-2"></i>Select New Date & Time</h4>
                            
                            <!-- Calendar Container -->
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <h4 id="currentMonthYear">July 2025</h4>
                                    <div>
                                        <button type="button" id="prevMonth" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-chevron-left"></i></button>
                                        <button type="button" id="nextMonth" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></button>
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
                                    <div class="time-slots" id="pm-slots"></div>
                                </div>
                            </div>
                            
                            <input type="hidden" id="selected_date" name="new_date" required>
                            <input type="hidden" id="selected_time" name="new_time" required>
                        </div>

                        <div class="form-section">
                            <h4><i class="bi bi-chat-dots me-2"></i>Reason for Rescheduling (Optional)</h4>
                            <div class="form-group">
                                <label for="reason" class="form-label">Reason</label>
                                <textarea id="reason" name="reason" class="form-control" rows="3" 
                                          placeholder="Please provide a reason for rescheduling (optional)"></textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end">
                            <a href="userAppointment.php" class="btn btn-outline">
                                <i class="bi bi-x-circle"></i>
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="bi bi-calendar-check"></i>
                                Confirm Reschedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="rescheduleConfirmModal" tabindex="-1" aria-labelledby="rescheduleConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rescheduleConfirmModalLabel">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                        Confirm Rescheduling
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Important Notice:</strong> Rescheduling your appointment will change its status back to "Pending" and require approval from our staff again.
                    </div>
                    
                    <div class="current-appointment-summary">
                        <h6 class="mb-3"><i class="bi bi-calendar-event me-2"></i>Current Appointment:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Date:</strong> <span id="modalCurrentDate"></span><br>
                                <strong>Time:</strong> <span id="modalCurrentTime"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Service:</strong> <span id="modalCurrentService"></span><br>
                                <strong>Status:</strong> <span id="modalCurrentStatus"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="new-appointment-summary mt-3">
                        <h6 class="mb-3"><i class="bi bi-calendar-plus me-2"></i>New Appointment:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Date:</strong> <span id="modalNewDate"></span><br>
                                <strong>Time:</strong> <span id="modalNewTime"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Service:</strong> <span id="modalNewService"></span><br>
                                <strong>Status:</strong> <span class="text-warning"><i class="bi bi-clock me-1"></i>Pending (Needs Approval)</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <p class="mb-0"><i class="bi bi-lightbulb me-2"></i><strong>What happens next?</strong></p>
                        <ul class="mt-2">
                            <li>Your appointment will be marked as "Pending"</li>
                            <li>Our staff will review your request</li>
                            <li>You'll receive a notification once approved</li>
                            <li>You can track the status in your appointments</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmRescheduleBtn">
                        <i class="bi bi-check-circle me-1"></i>
                        Yes, Reschedule Appointment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips for pending slots
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, { trigger: 'hover' }));
            
            // Calendar functionality
            let currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            let selectedSlot = null;
            
            updateMonthYearDisplay();
            renderCalendar();
            initTimeSlots();
            
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
                today.setHours(0,0,0,0);

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
                        document.getElementById('selected_date').value = selectedDate;

                        // Clear selected time and input
                        selectedSlot = null;
                        document.getElementById('selected_time').value = '';
                        // Remove selected class from all time slots
                        document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
                        document.getElementById('submitBtn').disabled = true;

                        // AJAX to fetch unavailable slots
                        fetch('get_pending_slots.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'date=' + encodeURIComponent(selectedDate)
                        })
                        .then(response => response.json())
                        .then(unavailableSlots => {
                            console.log('Unavailable slots:', unavailableSlots);
                            renderTimeSlots(unavailableSlots, amSlots, pmSlots);
                        });
                    });
                });
            }
            
            function initTimeSlots() {
                const timeSlots = document.querySelectorAll('.time-slot.available');
                timeSlots.forEach(slot => {
                    slot.addEventListener('click', function() {
                        // Remove 'selected' from all slots
                        document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
                        // Add 'selected' to the clicked slot
                        this.classList.add('selected');
                        // Extract just the time from the slot (remove icon and get only the time text)
                        const timeSpan = this.querySelector('span');
                        selectedSlot = timeSpan ? timeSpan.textContent.trim() : this.textContent.trim();
                        document.getElementById('selected_time').value = selectedSlot;
                        document.getElementById('submitBtn').disabled = false;
                    });
                });
            }

            // Pass PHP slot arrays to JS
            const amSlots = <?php echo json_encode($am_slots); ?>;
            const pmSlots = <?php echo json_encode($pm_slots); ?>;
            
            function renderTimeSlots(unavailableSlots, amSlots, pmSlots) {
                // unavailableSlots is an array of objects: [{time: '9:00 AM', status: 'pending'|'approved'}]
                let slotStatusMap = {};
                if (unavailableSlots.length && typeof unavailableSlots[0] === 'object' && unavailableSlots[0].time) {
                    unavailableSlots.forEach(s => {
                        slotStatusMap[s.time.toUpperCase().trim()] = s.status;
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
                    
                    // Mark past slots as unavailable for today
                    let selectedDateStr = document.getElementById('selected_date').value;
                    let now = new Date();
                    let isToday = false;
                    if (selectedDateStr) {
                        let todayStr = now.toISOString().slice(0,10);
                        isToday = (selectedDateStr === todayStr);
                    }
                    let slotIsPast = false;
                    if (isToday) {
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
                        icon = `<i class="bi bi-hourglass-split" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eee;color:#aaa;border:1px solid #bbb;min-width:100px;padding:6px 12px;white-space:nowrap;opacity:0.7;cursor:not-allowed;pointer-events:none;';
                        tooltip = 'This timeslot is unavailable (already passed).';
                    } else {
                        slotClass = 'time-slot available';
                        icon = `<i class="bi bi-calendar-plus-fill" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eaf3fb;color:#2563eb;border:2px solid #2563eb;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is available';
                    }
                    amContainer.innerHTML += `<div class="${slotClass}${isSelected ? ' selected' : ''}" style="${style}" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}">${icon}<span style="font-weight:600;white-space:nowrap;">${slot}</span></div>`;
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
                    
                    // Mark past slots as unavailable for today
                    let selectedDateStr = document.getElementById('selected_date').value;
                    let now = new Date();
                    let isToday = false;
                    if (selectedDateStr) {
                        let todayStr = now.toISOString().slice(0,10);
                        isToday = (selectedDateStr === todayStr);
                    }
                    let slotIsPast = false;
                    if (isToday) {
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
                        icon = `<i class="bi bi-hourglass-split" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eee;color:#aaa;border:1px solid #bbb;min-width:100px;padding:6px 12px;white-space:nowrap;opacity:0.7;cursor:not-allowed;pointer-events:none;';
                        tooltip = 'This timeslot is unavailable (already passed).';
                    } else {
                        slotClass = 'time-slot available';
                        icon = `<i class="bi bi-calendar-plus-fill" style="font-size:1.3em;"></i>`;
                        style = 'display:flex;align-items:center;justify-content:center;gap:2px;background:#eaf3fb;color:#2563eb;border:2px solid #2563eb;font-weight:600;min-width:100px;padding:6px 12px;white-space:nowrap;';
                        tooltip = 'This timeslot is available';
                    }
                    pmContainer.innerHTML += `<div class="${slotClass}${isSelected ? ' selected' : ''}" style="${style}" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}">${icon}<span style="font-weight:600;white-space:nowrap;">${slot}</span></div>`;
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

            // Form validation and confirmation modal
            document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const selectedDate = document.getElementById('selected_date').value;
                const selectedTime = document.getElementById('selected_time').value;
                
                if (!selectedDate || !selectedTime) {
                    alert('Please select both a date and time slot.');
                    return false;
                }
                
                // Populate modal with current and new appointment details
                document.getElementById('modalCurrentDate').textContent = '<?= date('F j, Y', strtotime($appointment['date'])) ?>';
                document.getElementById('modalCurrentTime').textContent = '<?= date('g:i A', strtotime($appointment['time'])) ?>';
                document.getElementById('modalCurrentService').textContent = '<?= htmlspecialchars($appointment['service']) ?>';
                document.getElementById('modalCurrentStatus').textContent = '<?= $appointment['status_enum'] ?>';
                
                // Format the new date and time for display
                const newDate = new Date(selectedDate);
                const newDateFormatted = newDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                document.getElementById('modalNewDate').textContent = newDateFormatted;
                document.getElementById('modalNewTime').textContent = selectedTime;
                document.getElementById('modalNewService').textContent = '<?= htmlspecialchars($appointment['service']) ?>';
                
                // Show the confirmation modal
                const modal = new bootstrap.Modal(document.getElementById('rescheduleConfirmModal'));
                modal.show();
            });
            
            // Handle confirmation button click
            document.getElementById('confirmRescheduleBtn').addEventListener('click', function() {
                // Mark the selected slot as pending before submitting
                const selectedSlotElement = document.querySelector('.time-slot.selected');
                if (selectedSlotElement) {
                    selectedSlotElement.classList.remove('available', 'selected');
                    selectedSlotElement.classList.add('pending');
                    selectedSlotElement.style.background = '#fff8e1';
                    selectedSlotElement.style.color = '#bfa700';
                    selectedSlotElement.style.border = '2px solid #ffc107';
                    const iconElement = selectedSlotElement.querySelector('i');
                    if (iconElement) {
                        iconElement.className = 'bi bi-hourglass-split';
                        iconElement.style.fontSize = '1.3em';
                    }
                    selectedSlotElement.setAttribute('data-bs-original-title', 'This timeslot is now pending');
                }
                
                // Submit the form
                document.getElementById('rescheduleForm').submit();
            });
        });
    </script>
</body>
</html> 