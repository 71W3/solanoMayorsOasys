<?php
session_start();
include "connect.php";

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
        if ($action == 'approve') {
            $con->begin_transaction();

            $appointment = $con->query("SELECT * FROM appointments WHERE id = $app_id")->fetch_assoc();
            if (!$appointment) throw new Exception("Appointment not found");

            // Update status_enum to 'approved'
            $stmt = $con->prepare("UPDATE appointments SET status_enum = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();

            // Insert into schedule table (copy all details)
            $stmt = $con->prepare("INSERT INTO schedule 
                (app_id, user_id, note, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())");
            $note = 'Approved by admin';
            $stmt->bind_param("iis", 
                $appointment['id'],
                $appointment['user_id'],
                $note
            );
            $stmt->execute();

            $con->commit();

            $_SESSION['message'] = "Appointment #$app_id has been approved and scheduled.";
            $_SESSION['message_type'] = "success";

        } elseif ($action == 'cancel') {
            // Update status_enum to 'cancelled'
            $stmt = $con->prepare("UPDATE appointments SET status_enum = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();

            $_SESSION['message'] = "Appointment #$app_id has been cancelled.";
            $_SESSION['message_type'] = "danger";
        } elseif ($action == 'decline') {
            // Update status_enum to 'declined'
            $stmt = $con->prepare("UPDATE appointments SET status_enum = 'declined', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();

            $_SESSION['message'] = "Appointment #$app_id has been declined.";
            $_SESSION['message_type'] = "danger";
        } elseif ($action == 'add_note') {
            // Add admin note (mayor only)
            $admin_notes = trim($_POST['admin_notes']);
            if (!empty($admin_notes)) {
                $stmt = $con->prepare("UPDATE appointments SET admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $admin_notes, $app_id);
                $stmt->execute();
                
                $_SESSION['message'] = "Note added to appointment #$app_id successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Note cannot be empty.";
                $_SESSION['message_type'] = "warning";
            }
        }

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

$stmt = $con->prepare("
    SELECT 
        a.id AS appointment_id,
        u.name AS resident_name,
        a.date,
        a.time,
        a.purpose,
        a.attendees,
        a.other_details
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.status_enum = 'approved' AND a.date = ?
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("s", $filter_date);
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
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>
    <div class="wrapper">
        
        <div class="main-content">
            <?php if (isset($_SESSION['message'])): ?>
                <div aria-live="polite" aria-atomic="true" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100; min-width: 300px;">
                    <div id="sessionToast" class="toast align-items-center text-bg-<?= $_SESSION['message_type'] ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                        <div class="d-flex">
                            <div class="toast-body">
                                <?= $_SESSION['message'] ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var toastEl = document.getElementById('sessionToast');
                        if (toastEl) {
                            var toast = new bootstrap.Toast(toastEl);
                            toast.show();
                        }
                    });
                </script>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>
            
            <div class="content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Manage Appointments</h4>
                    <div>
                        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#mayorAppointmentModal">
                            <i class="bi bi-plus-circle"></i> Add Mayor's Appointment
                        </button>
                    </div>
                </div>
                
                <!-- Mayor's Appointment Modal -->
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
                
                <!-- Reschedule Modal -->
                <!-- Reschedule Modal -->
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
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Reschedule</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Attachment Modal -->
                <div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Appointment Attachments</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="attachmentModalBody">
                                <!-- Content will be loaded via JavaScript -->
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
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                                <button name="action" value="cancel" class="btn btn-danger btn-sm ms-1">
                                                    <i class="bi bi-x-circle"></i> Cancel
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-info btn-sm ms-1 add-note-btn" 
                                                data-appointment-id="<?= $row['appointment_id'] ?>"
                                                data-appointment-resident="<?= htmlspecialchars($row['resident_name']) ?>">
                                                <i class="bi bi-sticky"></i> Add Note
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
                    <form method="get" class="d-flex gap-2 align-items-center">
                        <label for="filter" class="form-label mb-0">Filter by date:</label>
                        <input type="date" name="approved_filter_date" class="form-control form-control-sm"
                            value="<?= $_GET['approved_filter_date'] ?? date('Y-m-d') ?>" onchange="this.form.submit()">
                    </form>
                </div>


                <div class="table-responsive">
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
                                        <!-- In the approved appointments table -->
                                        <td class="no-print">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="bi bi-check-circle"></i> Complete
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-warning btn-sm ms-1 reschedule-btn" 
                                                data-appointment-id="<?= $row['appointment_id'] ?>"
                                                data-appointment-date="<?= $row['date'] ?>"
                                                data-appointment-time="<?= $row['time'] ?>">
                                                <i class="bi bi-calendar-check"></i> Reschedule
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No approved appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Print Header (only shows when printing) -->
                <div class="print-only">
                    <h2>Appointment Report</h2>
                    <p>Generated on: <?= date('F j, Y') ?></p>
                    <hr>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="viewDetailsModalLabel">Appointment Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="viewDetailsBody">
            <!-- Content will be loaded by JS -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Attachment Preview Modal -->
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

    <!-- Attachments Modal -->
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




    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
        
        // Handle attachment viewing
        document.querySelectorAll('.view-attachment').forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-appointment-id');
                
                // In a real implementation, you would fetch the attachments via AJAX
                // For this example, we'll just show a placeholder
                document.getElementById('attachmentModalBody').innerHTML = `
                    <div class="text-center py-4">
                        <h4>Attachments for Appointment #${appointmentId}</h4>
                        <p class="text-muted">This would display the actual attachments in a real implementation.</p>
                        <p>For demo purposes, we're showing a placeholder.</p>
                    </div>
                `;
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
                modal.show();
            });
        });
        
        // Handle rescheduling
        document.querySelectorAll('.reschedule-btn').forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-appointment-id');
                const currentDate = this.getAttribute('data-appointment-date');
                const currentTime = this.getAttribute('data-appointment-time');
                
                // Set the appointment ID in the form
                document.getElementById('reschedule_appointment_id').value = appointmentId;
                
                // Set current date/time as default values
                document.getElementById('new_date').value = currentDate;
                document.getElementById('new_time').value = currentTime;
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
                modal.show();
            });
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
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
                            return <button type="button" class="btn btn-outline-secondary btn-sm admin-attachment-btn" data-file="${file}"><i class="bi ${icon}"></i> ${file}</button>;
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
            const modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            modal.show();
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
            if (["jpg","jpeg","png","gif","bmp","webp","svg"].includes(ext)) {
                previewBody.innerHTML = <img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:10px;box-shadow:0 2px 8px #0002;">;
            } else if (ext === "pdf") {
                previewBody.innerHTML = <embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:10px;box-shadow:0 2px 8px #0002;"/>;
            } else if (["doc","docx"].includes(ext)) {
                const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/user/uploads/' + file)}`;
                previewBody.innerHTML = `
                    <div style="text-align:center;padding:20px;">
                        <i class="bi bi-file-earmark-word text-primary" style="font-size:3rem;margin-bottom:15px;"></i>
                        <h5>Word Document Preview</h5>
                        <p class="text-muted">Opening in Microsoft Office Online viewer...</p>
                        <div style="margin-top:20px;">
                            <a href="${officeViewerUrl}" target="_blank" class="btn btn-primary me-2">
                                <i class="bi bi-eye me-1"></i>Open in Office Online
                            </a>
                            <a href="../user/uploads/${file}" download class="btn btn-outline-primary">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                `;
            } else if (["xls","xlsx"].includes(ext)) {
                const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/user/uploads/' + file)}`;
                previewBody.innerHTML = `
                    <div style="text-align:center;padding:20px;">
                        <i class="bi bi-file-earmark-excel text-success" style="font-size:3rem;margin-bottom:15px;"></i>
                        <h5>Excel Spreadsheet Preview</h5>
                        <p class="text-muted">Opening in Microsoft Office Online viewer...</p>
                        <div style="margin-top:20px;">
                            <a href="${officeViewerUrl}" target="_blank" class="btn btn-success me-2">
                                <i class="bi bi-eye me-1"></i>Open in Office Online
                            </a>
                            <a href="../user/uploads/${file}" download class="btn btn-outline-success">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                `;
            } else if (["ppt","pptx"].includes(ext)) {
                const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/user/uploads/' + file)}`;
                previewBody.innerHTML = `
                    <div style="text-align:center;padding:20px;">
                        <i class="bi bi-file-earmark-ppt text-warning" style="font-size:3rem;margin-bottom:15px;"></i>
                        <h5>PowerPoint Presentation Preview</h5>
                        <p class="text-muted">Opening in Microsoft Office Online viewer...</p>
                        <div style="margin-top:20px;">
                            <a href="${officeViewerUrl}" target="_blank" class="btn btn-warning me-2">
                                <i class="bi bi-eye me-1"></i>Open in Office Online
                            </a>
                            <a href="../user/uploads/${file}" download class="btn btn-outline-warning">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                `;
            } else {
                previewBody.innerHTML = `
                    <div style="text-align:center;padding:30px;">
                        <i class="bi bi-file-earmark-text text-muted" style="font-size:4rem;margin-bottom:20px;"></i>
                        <h5>File Preview</h5>
                        <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                            <p><strong>File Name:</strong> ${file}</p>
                            <p><strong>File Type:</strong> ${ext.toUpperCase()} file</p>
                        </div>
                        <p class="text-muted">This file type cannot be previewed directly.</p>
                        <a href="../user/uploads/${file}" download class="btn btn-primary">
                            <i class="bi bi-download me-1"></i>Download File
                        </a>
                    </div>
                `;
            }
            const modal = new bootstrap.Modal(document.getElementById('adminAttachmentPreviewModal'));
            modal.show();
        }
    });
    // Print button for attachment preview
    document.getElementById('adminPrintAttachmentBtn').addEventListener('click', function() {
        var previewBody = document.getElementById('adminAttachmentPreviewBody');
        var printWindow = window.open('', '', 'width=900,height=700');
        printWindow.document.write('<html><head><title>Print Attachment</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
        printWindow.document.write('</head><body style="padding:20px;">');
        printWindow.document.write(previewBody.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() { printWindow.print(); printWindow.close(); }, 500);
    });

    // Attachments modal logic
    let currentAttachment = '';
    let allAttachments = [];
    document.querySelectorAll('.view-attachments-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const attachments = this.getAttribute('data-attachments');
            allAttachments = attachments.split(',').filter(f => f.trim() !== '');
            if (allAttachments.length === 0) return;
            showAttachment(allAttachments[0]);
            const modal = new bootstrap.Modal(document.getElementById('attachmentsModal'));
            modal.show();
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
                    return <button type=\"button\" class=\"btn ${active} btn-sm attachment-nav-btn\" data-file=\"${f}\">${f}</button>;
                }).join('') + '</div>';
        }
        downloadBtn.href = '../user/uploads/' + file;
        downloadBtn.setAttribute('download', file);
        if (["jpg","jpeg","png","gif","bmp","webp","svg"].includes(ext)) {
            previewBody.innerHTML = navHtml + <img src="../user/uploads/${file}" style="max-width:100%;max-height:70vh;border-radius:10px;box-shadow:0 2px 8px #0002;">;
        } else if (ext === "pdf") {
            previewBody.innerHTML = navHtml + <embed src="../user/uploads/${file}" type="application/pdf" width="100%" height="500px" style="border-radius:10px;box-shadow:0 2px 8px #0002;"/>;
        } else if (["doc","docx"].includes(ext)) {
            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/user/uploads/' + file)}`;
            previewBody.innerHTML = navHtml + `
                <div style="text-align:center;padding:20px;">
                    <i class="bi bi-file-earmark-word text-primary" style="font-size:3rem;margin-bottom:15px;"></i>
                    <h5>Word Document Preview</h5>
                    <p class="text-muted">Opening in Microsoft Office Online viewer...</p>
                    <div style="margin-top:20px;">
                        <a href="${officeViewerUrl}" target="_blank" class="btn btn-primary me-2">
                            <i class="bi bi-eye me-1"></i>Open in Office Online
                        </a>
                        <a href="../user/uploads/${file}" download class="btn btn-outline-primary">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                </div>
            `;
        } else if (["xls","xlsx"].includes(ext)) {
            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/user/uploads/' + file)}`;
            previewBody.innerHTML = navHtml + `
                <div style="text-align:center;padding:20px;">
                    <i class="bi bi-file-earmark-excel text-success" style="font-size:3rem;margin-bottom:15px;"></i>
                    <h5>Excel Spreadsheet Preview</h5>
                    <p class="text-muted">Opening in Microsoft Office Online viewer...</p>
                    <div style="margin-top:20px;">
                        <a href="${officeViewerUrl}" target="_blank" class="btn btn-success me-2">
                            <i class="bi bi-eye me-1"></i>Open in Office Online
                        </a>
                        <a href="../user/uploads/${file}" download class="btn btn-outline-success">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                </div>
            `;
        } else if (["ppt","pptx"].includes(ext)) {
            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(window.location.origin + '/user/uploads/' + file)}`;
            previewBody.innerHTML = navHtml + `
                <div style="text-align:center;padding:20px;">
                    <i class="bi bi-file-earmark-ppt text-warning" style="font-size:3rem;margin-bottom:15px;"></i>
                    <h5>PowerPoint Presentation Preview</h5>
                    <p class="text-muted">Opening in Microsoft Office Online viewer...</p>
                    <div style="margin-top:20px;">
                        <a href="${officeViewerUrl}" target="_blank" class="btn btn-warning me-2">
                            <i class="bi bi-eye me-1"></i>Open in Office Online
                        </a>
                        <a href="../user/uploads/${file}" download class="btn btn-outline-warning">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                </div>
            `;
        } else {
            previewBody.innerHTML = navHtml + `
                <div style="text-align:center;padding:30px;">
                    <i class="bi bi-file-earmark-text text-muted" style="font-size:4rem;margin-bottom:20px;"></i>
                    <h5>File Preview</h5>
                    <div style="background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;text-align:left;">
                        <p><strong>File Name:</strong> ${file}</p>
                        <p><strong>File Type:</strong> ${ext.toUpperCase()} file</p>
                    </div>
                    <p class="text-muted">This file type cannot be previewed directly.</p>
                    <a href="../user/uploads/${file}" download class="btn btn-primary">
                        <i class="bi bi-download me-1"></i>Download File
                    </a>
                </div>
            `;
        }
        // Add event listeners for nav buttons
        document.querySelectorAll('.attachment-nav-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                showAttachment(this.getAttribute('data-file'));
            });
        });
    }
    // Print button for attachment preview
    document.getElementById('attachmentsPrintBtn').addEventListener('click', function() {
        var previewBody = document.getElementById('attachmentsModalBody');
        var printWindow = window.open('', '', 'width=900,height=700');
        printWindow.document.write('<html><head><title>Print Attachment</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
        printWindow.document.write('</head><body style="padding:20px;">');
        // Only print the preview, not the nav buttons
        let content = previewBody.innerHTML;
        if (content.indexOf('attachment-nav-btn') !== -1) {
            content = content.replace(/<div class=\"mb-3 d-flex flex-wrap gap-2 justify-content-center\">[\s\S]*?<\/div>/, '');
        }
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() { printWindow.print(); printWindow.close(); }, 500);
    });
});


</script>
<script>
document.querySelectorAll('.reschedule-btn').forEach(button => {
    button.addEventListener('click', function () {
        const appointmentId = this.getAttribute('data-appointment-id');
        const currentDate = this.getAttribute('data-appointment-date');
        const currentTime = this.getAttribute('data-appointment-time');

        document.getElementById('reschedule_appointment_id').value = appointmentId;
        document.getElementById('new_date').value = currentDate;
        document.getElementById('new_time').value = currentTime;

        const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
        modal.show();
    });
});
</script>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">
                    <i class="bi bi-sticky me-2"></i>Add Admin Note
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="note_appointment_id">
                    <input type="hidden" name="action" value="add_note">
                    
                    <div class="mb-3">
                        <label class="form-label">Resident:</label>
                        <input type="text" class="form-control" id="note_resident_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Note (Mayor Only):</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4" 
                            placeholder="Add confidential notes about this resident/appointment that only the mayor can see..."></textarea>
                        <div class="form-text">
                            <i class="bi bi-shield-lock me-1"></i>
                            This note will only be visible to the mayor.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add Note Modal functionality
document.querySelectorAll('.add-note-btn').forEach(button => {
    button.addEventListener('click', function () {
        const appointmentId = this.getAttribute('data-appointment-id');
        const residentName = this.getAttribute('data-appointment-resident');

        document.getElementById('note_appointment_id').value = appointmentId;
        document.getElementById('note_resident_name').value = residentName;

        const modal = new bootstrap.Modal(document.getElementById('addNoteModal'));
        modal.show();
    });
});
</script>

</body>
</html>