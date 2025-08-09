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
            // Get appointment details for scheduling
            $appointment = $con->query("SELECT * FROM appointments WHERE id = $app_id")->fetch_assoc();
            if (!$appointment) throw new Exception("Appointment not found");

            // Update status_enum to 'approved'
            $stmt = $con->prepare("UPDATE appointments SET status_enum = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();

            // Insert into schedule table
            $stmt = $con->prepare("INSERT INTO schedule (app_id, user_id, note, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $note = 'Approved by admin';
            $stmt->bind_param("iis", $appointment['id'], $appointment['user_id'], $note);
            $stmt->execute();

            $_SESSION['message'] = "Appointment #$app_id has been approved and scheduled.";
            $_SESSION['message_type'] = "success";

            } elseif ($action == 'cancel') {
            $decline_reason = $_POST['decline_reason']; // Get the reason from the form

            // Update appointment status to 'declined'
            $stmt = $con->prepare("UPDATE appointments SET status_enum = 'declined', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();

            // CORRECTED: Insert into decline_table instead of line_table
            $stmt = $con->prepare("INSERT INTO decline_table (reason, app_id) VALUES (?, ?)");
            $stmt->bind_param("si", $decline_reason, $app_id);
            $stmt->execute();

            $_SESSION['message'] = "Appointment #$app_id has been declined.";
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
            
            // MODIFIED: Insert into new `message` table instead of `users` table
            $message = "Your appointment #$app_id has been rescheduled to $new_date at $new_time. Admin note: $admin_message";
            $stmt = $con->prepare("INSERT INTO message (user_id, message, date, time) VALUES (?, ?, CURDATE(), CURTIME())");
            $stmt->bind_param("is", $user_id, $message);
            $stmt->execute();
            // END MODIFIED

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

            // MODIFIED: Insert into new `message` table instead of `users` table
            $message = "Your appointment #$app_id has been marked as completed.";
            $stmt = $con->prepare("INSERT INTO message (user_id, message, date, time) VALUES (?, ?, CURDATE(), CURTIME())");
            $stmt->bind_param("is", $user_id, $message);
            $stmt->execute();
            // END MODIFIED

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
    <title>Manage Appointments - SOLAR Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --solano-blue: #0055a4;
            --solano-orange: #ff6b35;
        }
        body { font-family: 'Poppins', sans-serif; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar {
            width: 250px;
            background: var(--solano-blue);
            color: white;
            height: 100vh;
            position: fixed;
        }
        .sidebar a { color: white; text-decoration: none; display: block; padding: 15px; }
        .sidebar a.active, .sidebar a:hover { background: rgba(255,255,255,0.1); }
        .main-content { margin-left: 250px; flex: 1; }
        .topbar { background: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .content { padding: 30px; }
        .alert { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .today-row { background-color: rgba(0, 255, 0, 0.05); }
        .past-row { background-color: rgba(255, 0, 0, 0.05); }
        .attachment-icon { cursor: pointer; }
        .print-only { display: none; }
        @media print {
            .no-print { display: none; }
            .print-only { display: block; }
            body { background: white; }
            .sidebar, .topbar { display: none; }
            .main-content { margin-left: 0; }
            .content { padding: 0; }
            table { width: 100%; }
        }

        /*styling for the dropdown */
        * Custom styles for the decline modal */
        #declineModal .modal-content {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        #declineModal .modal-header {
            padding: 15px 20px;
        }

        #declineModal .modal-body {
            padding: 20px;
        }

        #declineModal .form-select {
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        #declineModal .form-select:focus {
            border-color: #ff6b35;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }

        #declineModal .form-control {
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        #declineModal .form-control:focus {
            border-color: #ff6b35;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }

        #declineModal .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        #declineModal .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
            transform: translateY(-2px);
        }

        #declineModal .btn-outline-secondary {
            padding: 8px 20px;
            font-weight: 500;
        }

        #custom_reason_container {
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>
    <div class="wrapper">
        
        <div class="main-content">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>
            
            <div class="content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Pending Appointments</h4>
                    <div>
                        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#mayorAppointmentModal">
                            <i class="bi bi-plus-circle"></i> Add Mayor's Appointment
                        </button>
                    </div>
                </div>
                
                <div class="modal fade" id="mayorAppointmentModal" tabindex="-1" aria-labelledby="mayorAppointmentModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post" action="appointment.php">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="mayorAppointmentModalLabel">Add Mayor's Appointment</h5>
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
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="date" name="date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="time" class="form-label">Time</label>
                                        <input type="time" class="form-control" id="time" name="time" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary" name="add_mayor_appointment">Save Appointment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post" action="appointment.php">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reschedule Appointment</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                                    <input type="hidden" name="action" value="reschedule">
                                    <div class="mb-3">
                                        <label for="new_date" class="form-label">New Date</label>
                                        <input type="date" class="form-control" id="new_date" name="new_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_time" class="form-label">New Time</label>
                                        <input type="time" class="form-control" id="new_time" name="new_time" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_message" class="form-label">Message to User</label>
                                        <textarea class="form-control" id="admin_message" name="admin_message" rows="3" required placeholder="Explain why you're rescheduling this appointment..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Reschedule</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Appointment Attachments</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="attachmentModalBody">
                                </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Resident</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Attendees</th>
                                <th>Other Details</th>
                                <th>Attachments</th>
                                <th class="no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments->num_rows > 0): ?>
                                <?php while ($row = $appointments->fetch_assoc()): ?>
                                    <tr class="<?= $row['date_status'] == 'today' ? 'today-row' : ($row['date_status'] == 'past' ? 'past-row' : '') ?>">
                                        <td><?= $row['appointment_id'] ?></td>
                                        <td><?= htmlspecialchars($row['resident_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                        <td><?= date('g:i A', strtotime($row['time'])) ?></td>
                                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                                        <td><?= $row['attendees'] ?></td>
                                        <td><?= htmlspecialchars($row['other_details']) ?></td>
                                        <td>
                                            <?php if (!empty($row['attachments'])): ?>
                                                <button type="button" class="btn btn-info btn-sm view-attachments-btn" data-attachments='<?= htmlspecialchars($row['attachments'], ENT_QUOTES, 'UTF-8') ?>'>
                                                    <i class="bi bi-paperclip"></i> View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No Attachment</span>
                                            <?php endif; ?>
                                        </td>
                                       <td class="no-print">
                                            <button type="button" class="btn btn-info btn-sm view-details-btn" data-appointment='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                                <button name="action" value="approve" class="btn btn-success btn-sm">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-danger btn-sm ms-1 decline-btn" 
                                                    data-appointment-id="<?= $row['appointment_id'] ?>" 
                                                    data-bs-toggle="modal" data-bs-target="#declineModal">
                                                <i class="bi bi-x-circle"></i> Decline
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">No pending appointments found.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Approved Appointments</h4>
                    <!-- <form method="get" class="d-flex gap-2 align-items-center">
                        <label for="filter" class="form-label mb-0">Filter by date:</label>
                        <input type="date" name="approved_filter_date" class="form-control form-control-sm"
                            value="<?= $_GET['approved_filter_date'] ?? date('Y-m-d') ?>" onchange="this.form.submit()">
                    </form> -->
                </div>


                <table class="table table-bordered table-hover">
                    <thead class="table-info">
                        <tr>
                            <th>ID</th>
                            <th>Resident</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th>Attendees</th>
                            <th>Other Details</th>
                            <th>Attachments</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($approved_appointments->num_rows > 0): ?>
                            <?php while ($row = $approved_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['appointment_id'] ?></td>
                                    <td><?= htmlspecialchars($row['resident_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                    <td><?= date('g:i A', strtotime($row['time'])) ?></td>
                                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                                    <td><?= htmlspecialchars($row['attendees']) ?></td>
                                    <td><?= htmlspecialchars($row['other_details']) ?></td>
                                    <td>
                                        <?php if (!empty($row['attachments'])): ?>
                                            <button type="button" class="btn btn-info btn-sm approved-view-attachments-btn" data-attachments='<?= htmlspecialchars($row['attachments'], ENT_QUOTES, 'UTF-8') ?>'>
                                                <i class="bi bi-paperclip"></i> View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">No Attachment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <form method="post" action="appointment.php" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn btn-success btn-sm complete-btn">
                                                <i class="bi bi-check-circle"></i> Complete
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-warning btn-sm ms-1 reschedule-btn" 
                                            data-appointment-id="<?= $row['appointment_id'] ?>"
                                            data-appointment-date="<?= $row['date'] ?>"
                                            data-appointment-time="<?= $row['time'] ?>"
                                            data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                                            <i class="bi bi-calendar-check"></i> Reschedule
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No approved appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="print-only">
                    <h2>Appointment Report</h2>
                    <p>Generated on: <?= date('F j, Y') ?></p>
                    <hr>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="viewDetailsModalLabel">Appointment Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="viewDetailsBody">
            </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="adminAttachmentPreviewModal" tabindex="-1" aria-labelledby="adminAttachmentPreviewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="adminAttachmentPreviewModalLabel">Attachment Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="adminAttachmentPreviewBody" style="text-align:center; min-height:300px; display:flex; align-items:center; justify-content:center;"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-success" id="adminPrintAttachmentBtn"><i class="bi bi-printer"></i> Print</button>
            <a href="#" class="btn btn-primary" id="adminDownloadAttachmentBtn" download><i class="bi bi-download"></i> Download</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="attachmentsModalLabel">Appointment Attachments</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="attachmentsModalBody" style="text-align:center; min-height:300px; display:flex; flex-direction:column; align-items:center; justify-content:center;"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-success" id="attachmentsPrintBtn"><i class="bi bi-printer"></i> Print</button>
            <a href="#" class="btn btn-primary" id="attachmentsDownloadBtn" download><i class="bi bi-download"></i> Download</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

        <!-- Decline Confirmation Modal -->
    <!-- Decline Confirmation Modal -->
        <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="appointment.php">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Confirm Decline</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="fw-medium mb-3">Are you sure you want to decline this appointment?</p>
                            
                            <div class="mb-3">
                                <label for="decline_reason_select" class="form-label">Common Reasons:</label>
                                <select class="form-select shadow-sm" id="decline_reason_select" required>
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
                                <textarea class="form-control shadow-sm" id="decline_reason" name="decline_reason" rows="3" placeholder="Please provide specific reason..."></textarea>
                            </div>
                            
                            <input type="hidden" name="appointment_id" id="decline_appointment_id">
                            <input type="hidden" name="action" value="cancel">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Confirm Decline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            new bootstrap.Alert(alert).close();
        });
    }, 5000);

    // Handle attachment viewing placeholder
    document.querySelectorAll('.view-attachment').forEach(button => {
        button.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-appointment-id');
            document.getElementById('attachmentModalBody').innerHTML = `
                <div class="text-center py-4">
                    <h4>Attachments for Appointment #${appointmentId}</h4>
                    <p class="text-muted">This would display the actual attachments in a real implementation.</p>
                    <p>For demo purposes, we're showing a placeholder.</p>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('attachmentModal')).show();
        });
    });

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
                    attachmentsHtml = '<div class="mb-2 fw-medium">Attachments:</div><div class="d-flex flex-wrap gap-2">' +
                        files.map((file, idx) => {
                            const ext = file.split('.').pop().toLowerCase();
                            let icon = 'bi-file-earmark';
                            if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) icon = 'bi-file-earmark-image';
                            else if (ext === "pdf") icon = 'bi-file-earmark-pdf';
                            else if (["doc","docx"].includes(ext)) icon = 'bi-file-earmark-word';
                            return `<button type="button" class="btn btn-outline-secondary btn-sm admin-attachment-btn" data-file="${file}"><i class="bi ${icon}"></i> ${file}</button>`;
                        }).join('') + '</div>';
                } else {
                    attachmentsHtml = '<span class="text-muted">No Attachment</span>';
                }
            } else {
                attachmentsHtml = '<span class="text-muted">No Attachment</span>';
            }
            
            document.getElementById('viewDetailsBody').innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6 mb-2"><strong>Resident:</strong> ${app.resident_name}</div>
                    <div class="col-md-6 mb-2"><strong>Date:</strong> ${app.date}</div>
                    <div class="col-md-6 mb-2"><strong>Time:</strong> ${app.time}</div>
                    <div class="col-md-6 mb-2"><strong>Status:</strong> ${app.status}</div>
                    <div class="col-md-6 mb-2"><strong>Purpose:</strong> ${app.purpose}</div>
                    <div class="col-md-6 mb-2"><strong>Attendees:</strong> ${app.attendees}</div>
                    <div class="col-md-12 mb-2"><strong>Other Details:</strong> ${app.other_details}</div>
                </div>
                <div>${attachmentsHtml}</div>
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
                previewBody.innerHTML = `<img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:10px;box-shadow:0 2px 8px #0002;">`;
            } else if (ext === "pdf") {
                previewBody.innerHTML = `<embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:10px;box-shadow:0 2px 8px #0002;"/>`;
            } else {
                previewBody.innerHTML = `<div style='color:#888;font-size:1.1rem;'>Cannot preview this file type.<br><a href='../user/uploads/${file}' download style='color:#28a745;font-weight:600;'>Download</a></div>`;
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
            previewBody.innerHTML = navHtml + `<img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:10px;box-shadow:0 2px 8px #0002;">`;
        } else if (ext === "pdf") {
            previewBody.innerHTML = navHtml + `<embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:10px;box-shadow:0 2px 8px #0002;"/>`;
        } else {
            previewBody.innerHTML = navHtml + `<div style='color:#888;font-size:1.1rem;'>Cannot preview this file type.<br><a href='../user/uploads/${file}' download style='color:#28a745;font-weight:600;'>Download</a></div>`;
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

// Decline form submission
const declineForm = document.querySelector('#declineModal form');
if (declineForm) {
    declineForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('[type="submit"]');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Processing...';
        
        fetch('process_decline.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            // First check if the response is JSON
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            
            // If not JSON, get the text and try to parse it
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch {
                throw new Error(text || 'Server returned non-JSON response');
            }
        })
        .then(data => {
            if (data && data.success) {
                showAlert('Appointment declined' + (data.email_sent ? '' : ' (email failed to send)'), 'success');
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('declineModal')).hide();
                    location.reload();
                }, 1500);
            } else {
                throw new Error(data?.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error: ' + (error.message || 'Failed to decline appointment'), 'danger');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirm Decline';
        });
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

// Helper function to show alerts
function showAlert(message, type) {
    // Remove any existing alerts first
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('.content') || document.body;
    container.prepend(alertDiv);
    
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}
});
</script>

</body>
</html>