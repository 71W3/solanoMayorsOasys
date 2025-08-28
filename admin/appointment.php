<?php
session_start();
include "connect.php";

$_SESSION['admin_logged_in'] = true;


// Handle Add Mayor's Appointment
if (isset($_POST['add_mayor_appointment'])) {
    $appointment_title = $_POST['appointment_title'];
    $description = $_POST['description'];
    $place = $_POST['place'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    
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
            (mayor_id, note, created_at, updated_at) 
            VALUES (?, ?, NOW(), NOW())");
        $note = "Mayor's appointment: $appointment_title";
        $stmt->bind_param("is", $mayor_app_id, $note);
        $stmt->execute();
        
        $con->commit();
        
        $_SESSION['message'] = "Mayor's appointment has been successfully added!";
        $_SESSION['message_type'] = "success";
        header("Location: appointment.php");
        exit();
        
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        header("Location: appointment.php");
        exit();
    }
}

// Handle Approve/Cancel/Complete/Reschedule Actions
if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $app_id = intval($_POST['appointment_id']);
    $action = $_POST['action'];

    try {
        // Start a transaction for atomicity
        $con->begin_transaction();

        if ($action == 'approve') {
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
            $admin_message = trim($_POST['admin_message'] ?? '');

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

            if ($email_result['success']) {
                $_SESSION['message'] = "Appointment #$app_id has been approved and confirmation email sent to " . $appointment['user_email'] . ".";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Appointment #$app_id has been approved, but email notification failed to send.";
                $_SESSION['message_type'] = "warning";
            }

        } elseif ($action == 'cancel') {
            $decline_reason = $_POST['decline_reason']; // Get the reason from the form

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

            if ($email_result['success']) {
                $_SESSION['message'] = "Appointment #$app_id has been declined and notification email sent.";
            } else {
                $_SESSION['message'] = "Appointment #$app_id has been declined, but email notification failed to send.";
            }
            $_SESSION['message_type'] = "danger";
            
        } elseif ($action == 'reschedule') {
            $new_date = $_POST['new_date'];
            $new_time = $_POST['new_time'];
            $admin_message = $_POST['admin_message'];

            // Get user_id for this appointment
            $stmt = $con->prepare("SELECT user_id FROM appointments WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $user_id = $appointment['user_id'];

            // Update appointment
            $stmt = $con->prepare("UPDATE appointments SET date = ?, time = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $new_date, $new_time, $app_id);
            $stmt->execute();
            
            // Insert into message table
            $message = "Your appointment #$app_id has been rescheduled to $new_date at $new_time. Admin note: $admin_message";
            $stmt = $con->prepare("INSERT INTO message (user_id, message, date, time) VALUES (?, ?, CURDATE(), CURTIME())");
            $stmt->bind_param("is", $user_id, $message);
            $stmt->execute();

            $_SESSION['message'] = "Appointment #$app_id rescheduled successfully and user notified. 🗓️";
            $_SESSION['message_type'] = "info";
            
        } elseif ($action == 'complete') {
            // Get user_id for this appointment
            $stmt = $con->prepare("SELECT user_id FROM appointments WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $user_id = $appointment['user_id'];

            // Update appointment status to 'completed'
            $stmt = $con->prepare("UPDATE appointments SET status_enum = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();

            // Insert into message table
            $message = "Your appointment #$app_id has been marked as completed.";
            $stmt = $con->prepare("INSERT INTO message (user_id, message, date, time) VALUES (?, ?, CURDATE(), CURTIME())");
            $stmt->bind_param("is", $user_id, $message);
            $stmt->execute();

            $_SESSION['message'] = "Appointment #$app_id marked as completed and user notified. ✅";
            $_SESSION['message_type'] = "success";
        }
        
        $con->commit();

    } catch (Exception $e) {
        if ($con->transactions > 0) {
            $con->rollback();
        }
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    header("Location: appointment.php");
    exit();
}

// Fetch appointments with additional conditions
$today = date('Y-m-d');
$appointments = $con->query("
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
");

if (!$appointments) {
    die("Error fetching appointments: " . $con->error);
}

// Fetch approved appointments based on filter
$filter_date = $_GET['approved_filter_date'] ?? date('Y-m-d');
$today = date('Y-m-d');

$stmt = $con->prepare("
    SELECT 
        a.id AS appointment_id,
        u.id AS user_id,
        u.name AS resident_name,
        a.date,
        a.time,
        a.purpose,
        a.attendees,
        a.other_details,
        a.attachments
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.status_enum = 'approved' AND a.date >= ?
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("s", $today);
$stmt->execute();
$approved_appointments = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - SOLAR Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="adminStyles/appointment.css">
</head>
<body>
    <div class="overlay" id="overlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="bi bi-sun"></i>
                    </div>
                    <div>
                        <h5>OASYS Admin</h5>
                        <div class="version">Municipality of Solano</div>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="adminPanel.php">
                    <i class="bi bi-house"></i>
                    Dashboard
                </a>
                <a href="appointment.php" class="active">
                    <i class="bi bi-calendar-check"></i>
                    Appointments
                </a>
                <a href="walk_in.php">
                    <i class="bi bi-person-walking"></i>
                    Walk-ins
                </a>
                <a href="queue.php">
                    <i class="bi bi-list-ol"></i>
                    Queue
                </a>
                <a href="schedule.php">
                    <i class="bi bi-calendar"></i>
                    Schedule
                </a>
                <a href="#">
                    <i class="bi bi-graph-up"></i>
                    Reports
                </a>
                <a href="#">
                    <i class="bi bi-gear"></i>
                    Settings
                </a>
                <a href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </nav>
        </div>

        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="mobile-menu-btn d-md-none me-3" id="mobileMenuBtn">
                            <i class="bi bi-list"></i>
                        </button>
                        <h1 class="page-title">Appointment Management</h1>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-2">Admin</span>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="content">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $appointments->num_rows ?></div>
                            <div class="stats-label">Pending Appointments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $approved_appointments->num_rows ?></div>
                            <div class="stats-label">Approved Appointments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php
                                $today_count = 0;
                                mysqli_data_seek($appointments, 0);
                                while ($row = $appointments->fetch_assoc()) {
                                    if ($row['date_status'] == 'today') $today_count++;
                                }
                                echo $today_count;
                                ?>
                            </div>
                            <div class="stats-label">Today's Appointments</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card text-end">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mayorAppointmentModal">
                                <i class="bi bi-plus-circle"></i>
                                Add Mayor's Appointment
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pending Appointments -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="section-title mb-0">Pending Appointments</h2>
                        <span class="badge bg-warning"><?= $appointments->num_rows ?> pending</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Resident</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Purpose</th>
                                        <th class="d-none d-md-table-cell">Attendees</th>
                                        <th class="d-none d-lg-table-cell">Other Details</th>
                                        <th class="d-none d-md-table-cell">Attachments</th>
                                        <th class="no-print text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    mysqli_data_seek($appointments, 0);
                                    if ($appointments->num_rows > 0):
                                    ?>
                                        <?php while ($row = $appointments->fetch_assoc()): ?>
                                            <tr class="<?= $row['date_status'] == 'today' ? 'today-row' : ($row['date_status'] == 'past' ? 'past-row' : '') ?>">
                                                <td>
                                                    <span class="badge bg-secondary">#<?= $row['appointment_id'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($row['resident_name']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= date('M d, Y', strtotime($row['date'])) ?></div>
                                                    <?php if ($row['date_status'] == 'today'): ?>
                                                        <small class="text-success fw-medium">Today</small>
                                                    <?php elseif ($row['date_status'] == 'past'): ?>
                                                        <small class="text-danger fw-medium">Overdue</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= date('g:i A', strtotime($row['time'])) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($row['purpose']) ?>">
                                                        <?= htmlspecialchars($row['purpose']) ?>
                                                    </div>
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    <span class="badge bg-light text-dark"><?= $row['attendees'] ?> person(s)</span>
                                                </td>
                                                <td class="d-none d-lg-table-cell">
                                                    <div class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['other_details']) ?>">
                                                        <?= htmlspecialchars($row['other_details']) ?>
                                                    </div>
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    <?php if (!empty($row['attachments'])): ?>
                                                        <button type="button" class="btn btn-outline-primary btn-sm view-attachments-btn" data-attachments='<?= htmlspecialchars($row['attachments'], ENT_QUOTES, 'UTF-8') ?>'>
                                                            <i class="bi bi-paperclip"></i>
                                                            <span class="d-none d-lg-inline">View</span>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">No files</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="no-print">
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <button type="button" class="btn btn-outline-primary btn-sm view-details-btn" 
                                                                data-appointment='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                                                            <i class="bi bi-eye"></i>
                                                            <span class="d-none d-xl-inline">View</span>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-success btn-sm approve-btn" 
                                                                data-appointment-id="<?= $row['appointment_id'] ?>" 
                                                                data-appointment-data='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'
                                                                data-bs-toggle="modal" data-bs-target="#approveModal">
                                                            <i class="bi bi-check-circle"></i>
                                                            <span class="d-none d-xl-inline">Approve</span>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-danger btn-sm decline-btn" 
                                                                data-appointment-id="<?= $row['appointment_id'] ?>" 
                                                                data-bs-toggle="modal" data-bs-target="#declineModal">
                                                            <i class="bi bi-x-circle"></i>
                                                            <span class="d-none d-xl-inline">Decline</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="bi bi-calendar-x display-4 d-block mb-3"></i>
                                                    <h5>No pending appointments</h5>
                                                    <p class="mb-0">All appointments have been processed.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Approved Appointments -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="section-title mb-0">Approved Appointments</h2>
                        <span class="badge bg-success"><?= $approved_appointments->num_rows ?> approved</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Resident</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Purpose</th>
                                        <th class="d-none d-md-table-cell">Attendees</th>
                                        <th class="d-none d-lg-table-cell">Other Details</th>
                                        <th class="d-none d-md-table-cell">Attachments</th>
                                        <th class="no-print text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($approved_appointments->num_rows > 0): ?>
                                        <?php while ($row = $approved_appointments->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-success">#<?= $row['appointment_id'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= htmlspecialchars($row['resident_name']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= date('M d, Y', strtotime($row['date'])) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= date('g:i A', strtotime($row['time'])) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($row['purpose']) ?>">
                                                        <?= htmlspecialchars($row['purpose']) ?>
                                                    </div>
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($row['attendees']) ?> person(s)</span>
                                                </td>
                                                <td class="d-none d-lg-table-cell">
                                                    <div class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['other_details']) ?>">
                                                        <?= htmlspecialchars($row['other_details']) ?>
                                                    </div>
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    <?php if (!empty($row['attachments'])): ?>
                                                        <button type="button" class="btn btn-outline-primary btn-sm approved-view-attachments-btn" data-attachments='<?= htmlspecialchars($row['attachments'], ENT_QUOTES, 'UTF-8') ?>'>
                                                            <i class="bi bi-paperclip"></i>
                                                            <span class="d-none d-lg-inline">View</span>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">No files</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="no-print">
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <form method="post" action="appointment.php" style="display:inline;">
                                                            <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-success btn-sm complete-btn">
                                                                <i class="bi bi-check-circle"></i>
                                                                <span class="d-none d-xl-inline">Complete</span>
                                                            </button>
                                                        </form>
                                                        
                                                        <button type="button" class="btn btn-warning btn-sm reschedule-btn" 
                                                            data-appointment-id="<?= $row['appointment_id'] ?>"
                                                            data-appointment-date="<?= $row['date'] ?>"
                                                            data-appointment-time="<?= $row['time'] ?>"
                                                            data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                                                            <i class="bi bi-calendar-check"></i>
                                                            <span class="d-none d-xl-inline">Reschedule</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="bi bi-calendar-check display-4 d-block mb-3"></i>
                                                    <h5>No approved appointments</h5>
                                                    <p class="mb-0">Approved appointments will appear here.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Print Section -->
                <div class="print-only">
                    <h2>Appointment Report</h2>
                    <p>Generated on: <?= date('F j, Y') ?></p>
                    <hr>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Mayor's Appointment Modal -->
    <div class="modal fade" id="mayorAppointmentModal" tabindex="-1" aria-labelledby="mayorAppointmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="appointment.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mayorAppointmentModalLabel">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add Mayor's Appointment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="appointment_title" class="form-label">Appointment Title</label>
                            <input type="text" class="form-control" id="appointment_title" name="appointment_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="place" class="form-label">Place</label>
                            <input type="text" class="form-control" id="place" name="place" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="time" class="form-label">Time</label>
                                <input type="time" class="form-control" id="time" name="time" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_mayor_appointment">
                            <i class="bi bi-save"></i>
                            Save Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="appointment.php">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-calendar-check me-2"></i>
                            Reschedule Appointment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                        <input type="hidden" name="action" value="reschedule">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_date" class="form-label">New Date</label>
                                <input type="date" class="form-control" id="new_date" name="new_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_time" class="form-label">New Time</label>
                                <input type="time" class="form-control" id="new_time" name="new_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="admin_message" class="form-label">Message to User</label>
                            <textarea class="form-control" id="admin_message" name="admin_message" rows="3" required placeholder="Explain why you're rescheduling this appointment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-calendar-check"></i>
                            Reschedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDetailsModalLabel">
                        <i class="bi bi-eye me-2"></i>
                        Appointment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewDetailsBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attachment Modals -->
    <div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-paperclip me-2"></i>
                        Appointment Attachments
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="attachmentModalBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminAttachmentPreviewModal" tabindex="-1" aria-labelledby="adminAttachmentPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminAttachmentPreviewModalLabel">
                        <i class="bi bi-file-earmark me-2"></i>
                        Attachment Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="adminAttachmentPreviewBody" style="text-align:center; min-height:300px; display:flex; align-items:center; justify-content:center;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="adminPrintAttachmentBtn">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <a href="#" class="btn btn-primary" id="adminDownloadAttachmentBtn" download>
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attachmentsModalLabel">
                        <i class="bi bi-paperclip me-2"></i>
                        Appointment Attachments
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="attachmentsModalBody" style="text-align:center; min-height:300px; display:flex; flex-direction:column; align-items:center; justify-content:center;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="attachmentsPrintBtn">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <a href="#" class="btn btn-primary" id="attachmentsDownloadBtn" download>
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="appointment.php">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-check-circle me-2"></i>
                            Confirm Approval
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fw-medium mb-3">Are you sure you want to approve this appointment?</p>
                        
                        <!-- Appointment Details Preview -->
                        <div class="appointment-details" id="appointmentDetailsPreview">
                            <!-- This will be populated by JavaScript -->
                        </div>
                        
                        <div class="mb-3">
                            <label for="approve_message_select" class="form-label">Add a message to the approval email:</label>
                            <select class="form-select" id="approve_message_select">
                                <option value="" selected>-- Choose a message template or write custom --</option>
                                <option value="welcome">Welcome & general information</option>
                                <option value="preparation">Preparation instructions</option>
                                <option value="documents">Document requirements</option>
                                <option value="early_arrival">Early arrival reminder</option>
                                <option value="contact_info">Contact information</option>
                                <option value="custom">Custom message</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="custom_message_container" style="display: none;">
                            <label for="admin_message" class="form-label">Your message:</label>
                            <textarea class="form-control" id="admin_message" name="admin_message" rows="4" 
                                placeholder="Enter your custom message for the user..."></textarea>
                            <small class="form-text text-muted">
                                This message will be included in the approval email to provide additional information.
                            </small>
                        </div>
                        
                        <input type="hidden" name="appointment_id" id="approve_appointment_id">
                        <input type="hidden" name="action" value="approve">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Decline Confirmation Modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="appointment.php">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-x-circle me-2"></i>
                            Confirm Decline
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fw-medium mb-3">Are you sure you want to decline this appointment?</p>
                        
                        <div class="mb-3">
                            <label for="decline_reason_select" class="form-label">Common Reasons:</label>
                            <select class="form-select" id="decline_reason_select" required>
                                <option value="" selected disabled>-- Select a reason --</option>
                                <option value="Conflict with mayor's schedule">Conflict with mayor's schedule</option>
                                <option value="Insufficient information provided">Insufficient information provided</option>
                                <option value="Does not align with office priorities">Does not align with office priorities</option>
                                <option value="Requested date not available">Requested date not available</option>
                                <option value="Inappropriate request">Inappropriate request</option>
                                <option value="others">Others (please specify)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="custom_reason_container" style="display: none;">
                            <label for="decline_reason" class="form-label">Specify reason:</label>
                            <textarea class="form-control" id="decline_reason" name="decline_reason" rows="3" placeholder="Please provide specific reason..."></textarea>
                        </div>
                        
                        <input type="hidden" name="appointment_id" id="decline_appointment_id">
                        <input type="hidden" name="action" value="cancel">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Confirm Decline
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }

        // Pre-written message templates
        const messageTemplates = {
            welcome: `Welcome! We're pleased to confirm your appointment with the Solano Mayor's Office. Our team looks forward to assisting you with your needs.`,
            
            preparation: `Please prepare for your appointment:
• Arrive 10 minutes early
• Bring a valid government-issued ID
• Have any relevant documents ready
• Prepare a list of questions you may have`,
            
            documents: `Please bring the following documents:
• Valid government-issued ID (Driver's License, Passport, etc.)
• Proof of residency (if applicable)
• Any previous correspondence related to your request
• Supporting documents mentioned in your original request`,
            
            early_arrival: `Important reminder: Please arrive at our office 10 minutes before your scheduled appointment time. This will allow us to process your visit efficiently and ensure we can give you our full attention during your appointment.`,
            
            contact_info: `If you have any questions or need to make changes to your appointment, please contact us:
• Phone: (078) 123-4567
• Email: info@solanomayor.gov.ph
• Office Hours: Monday - Friday, 8:00 AM - 5:00 PM`
        };

        // Handle approve button clicks
        document.addEventListener('click', function(e) {
            if (e.target.closest('.approve-btn')) {
                const button = e.target.closest('.approve-btn');
                const appointmentId = button.getAttribute('data-appointment-id');
                const appointmentData = JSON.parse(button.getAttribute('data-appointment-data') || '{}');
                
                // Set appointment ID
                document.getElementById('approve_appointment_id').value = appointmentId;
                
                // Populate appointment details preview
                populateAppointmentDetails(appointmentData);
                
                // Reset form
                resetApproveForm();
            }
        });

        // Populate appointment details in modal
        function populateAppointmentDetails(appointment) {
            const detailsContainer = document.getElementById('appointmentDetailsPreview');
            
            const formattedDate = new Date(appointment.date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const formattedTime = new Date('1970-01-01T' + appointment.time).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });

            detailsContainer.innerHTML = `
                <h6 class="fw-bold mb-2">Appointment Details:</h6>
                <p class="mb-1"><strong>Resident:</strong> ${appointment.resident_name || 'N/A'}</p>
                <p class="mb-1"><strong>Date:</strong> ${formattedDate}</p>
                <p class="mb-1"><strong>Time:</strong> ${formattedTime}</p>
                <p class="mb-1"><strong>Purpose:</strong> ${appointment.purpose || 'N/A'}</p>
                <p class="mb-1"><strong>Attendees:</strong> ${appointment.attendees || 'N/A'} person(s)</p>
                ${appointment.other_details ? `<p class="mb-0"><strong>Other Details:</strong> ${appointment.other_details}</p>` : ''}
            `;
        }

        // Handle message template selection
        const messageSelect = document.getElementById('approve_message_select');
        const customContainer = document.getElementById('custom_message_container');
        const messageTextarea = document.getElementById('admin_message');

        if (messageSelect) {
            messageSelect.addEventListener('change', function() {
                const selectedValue = this.value;
                
                if (selectedValue === 'custom') {
                    customContainer.style.display = 'block';
                    messageTextarea.value = '';
                    messageTextarea.focus();
                } else if (selectedValue && messageTemplates[selectedValue]) {
                    customContainer.style.display = 'block';
                    messageTextarea.value = messageTemplates[selectedValue];
                } else {
                    customContainer.style.display = 'none';
                    messageTextarea.value = '';
                }
            });
        }

        // Reset form when modal is closed
        const approveModal = document.getElementById('approveModal');
        if (approveModal) {
            approveModal.addEventListener('hidden.bs.modal', function() {
                resetApproveForm();
            });
        }

        function resetApproveForm() {
            if (messageSelect) messageSelect.selectedIndex = 0;
            if (customContainer) customContainer.style.display = 'none';
            if (messageTextarea) messageTextarea.value = '';
        }

        // Handle form submission with better feedback
        const approveForm = document.querySelector('#approveModal form');
        if (approveForm) {
            approveForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processing...';
                
                // Allow form to submit normally, but provide visual feedback
                setTimeout(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirm Approval';
                    }
                }, 3000);
            });
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (bootstrap.Alert.getInstance(alert)) {
                    bootstrap.Alert.getInstance(alert).close();
                } else {
                    new bootstrap.Alert(alert).close();
                }
            });
        }, 5000);

        // Handle Reschedule button
        document.querySelectorAll('.reschedule-btn').forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-appointment-id');
                const currentDate = this.getAttribute('data-appointment-date');
                const currentTime = this.getAttribute('data-appointment-time');

                document.getElementById('reschedule_appointment_id').value = appointmentId;
                document.getElementById('new_date').value = currentDate;
                document.getElementById('new_time').value = currentTime;
            });
        });

        // View details modal logic
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const app = JSON.parse(this.getAttribute('data-appointment'));
                let attachmentsHtml = '';
                
                if (app.attachments && app.attachments.length > 0) {
                    const files = app.attachments.split(',').filter(f => f.trim() !== '');
                    if (files.length > 0) {
                        attachmentsHtml = '<div class="mb-3"><div class="fw-medium mb-2">Attachments:</div><div class="d-flex flex-wrap gap-2">' +
                            files.map((file, idx) => {
                                const ext = file.split('.').pop().toLowerCase();
                                let icon = 'bi-file-earmark';
                                if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) icon = 'bi-file-earmark-image';
                                else if (ext === "pdf") icon = 'bi-file-earmark-pdf';
                                else if (["doc","docx"].includes(ext)) icon = 'bi-file-earmark-word';
                                return `<button type="button" class="btn btn-outline-secondary btn-sm admin-attachment-btn" data-file="${file}"><i class="bi ${icon}"></i> ${file}</button>`;
                            }).join('') + '</div></div>';
                    } else {
                        attachmentsHtml = '<div class="mb-3"><div class="fw-medium mb-2">Attachments:</div><span class="text-muted">No files attached</span></div>';
                    }
                } else {
                    attachmentsHtml = '<div class="mb-3"><div class="fw-medium mb-2">Attachments:</div><span class="text-muted">No files attached</span></div>';
                }
                
                document.getElementById('viewDetailsBody').innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-1">Resident</div>
                                <div>${app.resident_name}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-1">Status</div>
                                <span class="badge bg-warning">${app.status}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-1">Date</div>
                                <div>${app.date}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-1">Time</div>
                                <div>${app.time}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-1">Attendees</div>
                                <div>${app.attendees} person(s)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-1">Appointment ID</div>
                                <div>#${app.appointment_id}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-2">Purpose</div>
                                <div>${app.purpose}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="fw-medium text-muted mb-2">Other Details</div>
                                <div>${app.other_details || 'None provided'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">${attachmentsHtml}</div>
                `;
                new bootstrap.Modal(document.getElementById('viewDetailsModal')).show();
            });
        });

        // Attachment preview modal logic
        document.getElementById('viewDetailsBody').addEventListener('click', function(e) {
            if (e.target.closest('.admin-attachment-btn')) {
                const btn = e.target.closest('.admin-attachment-btn');
                const file = btn.getAttribute('data-file');
                const ext = file.split('.').pop().toLowerCase();
                const previewBody = document.getElementById('adminAttachmentPreviewBody');
                const downloadBtn = document.getElementById('adminDownloadAttachmentBtn');
                
                previewBody.innerHTML = '<div style="color:#888;font-size:1.2rem;">Loading preview...</div>';
                downloadBtn.href = '../user/uploads/' + file;
                downloadBtn.setAttribute('download', file);
                
                if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
                    previewBody.innerHTML = `<img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">`;
                } else if (ext === "pdf") {
                    previewBody.innerHTML = `<embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);"/>`;
                } else {
                    previewBody.innerHTML = `<div style='color:#888;font-size:1.1rem;'>Cannot preview this file type.<br><a href='../user/uploads/${file}' download class='btn btn-primary mt-3'><i class="bi bi-download"></i> Download File</a></div>`;
                }
                
                new bootstrap.Modal(document.getElementById('adminAttachmentPreviewModal')).show();
            }
        });

        // Print button for attachment preview
        document.getElementById('adminPrintAttachmentBtn').addEventListener('click', function() {
            const previewBody = document.getElementById('adminAttachmentPreviewBody');
            const printWindow = window.open('', '', 'width=900,height=700');
            printWindow.document.write('<html><head><title>Print Attachment</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            printWindow.document.write('</head><body style="padding:20px;">');
            printWindow.document.write(previewBody.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
        });

        // Attachments modal logic
        let currentAttachment = '';
        let allAttachments = [];
        
        document.querySelectorAll('.view-attachments-btn, .approved-view-attachments-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const attachments = this.getAttribute('data-attachments');
                allAttachments = attachments.split(',').filter(f => f.trim() !== '');
                if (allAttachments.length === 0) return;
                showAttachment(allAttachments[0]);
                new bootstrap.Modal(document.getElementById('attachmentsModal')).show();
            });
        });

        function showAttachment(file) {
            currentAttachment = file;
            const ext = file.split('.').pop().toLowerCase();
            const previewBody = document.getElementById('attachmentsModalBody');
            const downloadBtn = document.getElementById('attachmentsDownloadBtn');
            let navHtml = '';
            
            if (allAttachments.length > 1) {
                navHtml = '<div class="mb-3 d-flex flex-wrap gap-2 justify-content-center">' +
                    allAttachments.map(f => {
                        const active = (f === file) ? 'btn-primary' : 'btn-outline-primary';
                        return `<button type="button" class="btn ${active} btn-sm attachment-nav-btn" data-file="${f}">${f}</button>`;
                    }).join('') + '</div>';
            }
            
            downloadBtn.href = '../user/uploads/' + file;
            downloadBtn.setAttribute('download', file);
            
            if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
                previewBody.innerHTML = navHtml + `<img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">`;
            } else if (ext === "pdf") {
                previewBody.innerHTML = navHtml + `<embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:8px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);"/>`;
            } else {
                previewBody.innerHTML = navHtml + `<div style='color:#888;font-size:1.1rem;'>Cannot preview this file type.<br><a href='../user/uploads/${file}' download class='btn btn-primary mt-3'><i class="bi bi-download"></i> Download File</a></div>`;
            }
            
            document.querySelectorAll('.attachment-nav-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    showAttachment(this.getAttribute('data-file'));
                });
            });
        }

        // Print button for attachments modal
        document.getElementById('attachmentsPrintBtn').addEventListener('click', function() {
            const previewBody = document.getElementById('attachmentsModalBody');
            const printWindow = window.open('', '', 'width=900,height=700');
            printWindow.document.write('<html><head><title>Print Attachment</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            printWindow.document.write('</head><body style="padding:20px;">');
            
            let content = previewBody.innerHTML;
            if (content.indexOf('attachment-nav-btn') !== -1) {
                content = content.replace(/<div class="mb-3 d-flex flex-wrap gap-2 justify-content-center">[\s\S]*?<\/div>/, '');
            }
            
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
        });

        // Decline functionality
        document.querySelectorAll('.decline-btn').forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-appointment-id');
                document.getElementById('decline_appointment_id').value = appointmentId;
            });
        });

        const reasonSelect = document.getElementById('decline_reason_select');
        const customReasonContainer = document.getElementById('custom_reason_container');
        const textarea = document.getElementById('decline_reason');

        if (reasonSelect) {
            reasonSelect.addEventListener('change', function() {
                if (this.value === 'others') {
                    customReasonContainer.style.display = 'block';
                    textarea.required = true;
                    textarea.value = '';
                } else {
                    customReasonContainer.style.display = 'none';
                    textarea.required = false;
                    textarea.value = this.value;
                }
            });
        }

        // Reset decline modal when closed
        const declineModal = document.getElementById('declineModal');
        if (declineModal) {
            declineModal.addEventListener('hidden.bs.modal', function() {
                if (reasonSelect) reasonSelect.selectedIndex = 0;
                if (customReasonContainer) customReasonContainer.style.display = 'none';
                if (textarea) {
                    textarea.value = '';
                    textarea.required = false;
                }
            });
        }

        // Add confirmation to complete buttons
        document.querySelectorAll('.complete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to mark this appointment as completed?')) {
                    e.preventDefault();
                }
            });
        });

        // Helper function to show alerts
        function showAlert(message, type) {
            // Remove any existing alerts first
            document.querySelectorAll('.alert').forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle-fill me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            const container = document.querySelector('.content') || document.body;
            container.prepend(alertDiv);
            
            setTimeout(() => {
                if (bootstrap.Alert.getInstance(alertDiv)) {
                    bootstrap.Alert.getInstance(alertDiv).close();
                } else {
                    new bootstrap.Alert(alertDiv).close();
                }
            }, 5000);
        }

        // Set minimum date for date inputs to today
        const today = new Date().toISOString().split('T')[0];
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            input.setAttribute('min', today);
        });

        // Form validation feedback
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        // Close sidebar on window resize if mobile
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    });
    </script>

</body>
</html>