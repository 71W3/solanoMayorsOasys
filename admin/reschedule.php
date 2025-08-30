<?php
session_start();
include "connect.php";
include "activity_logger.php"; // Include activity logger

// Session validation handled by adminPanel_functions.php

// Check if appointment_id is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "No appointment ID provided.";
    $_SESSION['message_type'] = "danger";
    header("Location: appointment.php");
    exit();
}

$appointment_id = intval($_GET['id']);

// Get appointment details
$stmt = $con->prepare("
    SELECT a.*, u.name as user_name, u.email as user_email 
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Appointment not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: appointment.php");
    exit();
}

$appointment = $result->fetch_assoc();

// Handle reschedule form submission
if (isset($_POST['action']) && $_POST['action'] === 'reschedule') {
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    $admin_message = $_POST['admin_message'];

    try {
        $con->begin_transaction();
        
        $user_id = $appointment['user_id'];
        $old_date = $appointment['date'];
        $old_time = $appointment['time'];

        // Update appointment
        $stmt = $con->prepare("UPDATE appointments SET date = ?, time = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $new_date, $new_time, $appointment_id);
        $stmt->execute();
        
        // Insert into message table
        $message = "Your appointment #$appointment_id has been rescheduled to $new_date at $new_time. Admin note: $admin_message";
        $stmt = $con->prepare("INSERT INTO message (user_id, message, date, time) VALUES (?, ?, CURDATE(), CURTIME())");
        $stmt->bind_param("is", $user_id, $message);
        $stmt->execute();

        // Send reschedule email
        include_once "../user/email_helper_phpmailer.php";
        
        $email_result = sendAppointmentRescheduledEmail(
            $appointment['user_email'],
            $appointment['user_name'],
            $old_date,
            $old_time,
            $new_date,
            $new_time,
            $appointment['purpose'],
            $admin_message
        );

        $con->commit();
        
        // Log activity for superadmin monitoring
        $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        $admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_full_name'] ?? 'Unknown Admin';
        $admin_role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'admin';
        
        if ($admin_id) {
            logAppointmentReschedule(
                $con, 
                $admin_id, 
                $admin_name, 
                $admin_role, 
                $appointment_id, 
                $appointment['user_name'], 
                $appointment['purpose'], 
                $old_date, 
                $new_date
            );
        }

        if ($email_result['success']) {
            $_SESSION['message'] = "Appointment #$appointment_id rescheduled successfully and email notification sent to " . $appointment['user_email'] . ". 🗓️";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Appointment #$appointment_id rescheduled successfully, but email notification failed to send.";
            $_SESSION['message_type'] = "warning";
        }
        
        header("Location: appointment.php");
        exit();
        
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - SOLAR Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #64748b;
            --accent: #2563eb;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --light: #f8fafc;
            --lighter: #f1f5f9;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --radius: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--lighter);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container-fluid {
            padding: 2rem;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: white;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-header {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn {
            border-radius: var(--radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-outline-secondary {
            border: 1px solid var(--border);
            color: var(--text-secondary);
            background: transparent;
        }

        .form-control, .form-select {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .appointment-info {
            background: var(--light);
            padding: 1.25rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2rem;
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
            color: var(--primary);
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
            background-color: rgba(37, 99, 235, 0.1);
        }

        .calendar-day.selected {
            background-color: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .calendar-day.disabled {
            background-color: #f8f9fa;
            color: #ccc;
            cursor: not-allowed;
        }

        .time-group-header {
            font-size: 1.1rem;
            color: var(--accent);
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
            position: relative;
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
            background: #f9fafb;
            border-radius: 8px;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, border 0.15s;
            border: 1px solid transparent;
            color: #444;
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
            background: #f3f4f6 !important;
            color: #bdbdbd !important;
            border: 1px solid #f3f4f6 !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.6;
        }

        .time-slot.approved {
            background: #f0fdf4 !important;
            color: #4caf50 !important;
            border: 1px solid #e5e7eb !important;
            font-weight: 500;
        }

        .time-slot.pending {
            background: #f5f6fa !important;
            color: #b1a06b !important;
            border: 1px solid #e5e7eb !important;
            font-weight: 400;
        }

        .selection-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
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
            background: #007bff;
            border-color: #007bff;
        }
        .legend-unavailable {
            background: #eee;
            border-color: #bbb;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">
                <i class="bi bi-calendar-check me-2"></i>
                Reschedule Appointment
            </h1>
            <a href="appointment.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Back to Appointments
            </a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Current Appointment Info -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>
                Current Appointment Details
            </div>
            <div class="card-body">
                <div class="appointment-info">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Resident:</strong> <?= htmlspecialchars($appointment['user_name']) ?></p>
                            <p><strong>Purpose:</strong> <?= htmlspecialchars($appointment['purpose']) ?></p>
                            <p><strong>Current Date:</strong> <?= date('F j, Y', strtotime($appointment['date'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Current Time:</strong> <?= date('g:i A', strtotime($appointment['time'])) ?></p>
                            <p><strong>Attendees:</strong> <?= htmlspecialchars($appointment['attendees']) ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-warning"><?= ucfirst($appointment['status_enum']) ?></span></p>
                        </div>
                    </div>
                    <?php if (!empty($appointment['other_details'])): ?>
                        <p><strong>Other Details:</strong> <?= htmlspecialchars($appointment['other_details']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reschedule Form with Calendar -->
        <form method="post" action="reschedule.php?id=<?= $appointment_id ?>">
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="new_date" id="selected_date">
            <input type="hidden" name="new_time" id="selected_time">
            
            <div class="row">
                <!-- Calendar Section -->
                <div class="col-lg-8">
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="prevMonth">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <h5 class="mb-0" id="currentMonthYear"></h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="nextMonth">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex flex-wrap gap-3 mb-2">
                                <span class="d-flex align-items-center">
                                    <span class="legend-dot legend-approved me-1"></span>
                                    <small>Approved</small>
                                </span>
                                <span class="d-flex align-items-center">
                                    <span class="legend-dot legend-pending me-1"></span>
                                    <small>Pending</small>
                                </span>
                                <span class="d-flex align-items-center">
                                    <span class="legend-dot legend-available me-1"></span>
                                    <small>Available</small>
                                </span>
                                <span class="d-flex align-items-center">
                                    <span class="legend-dot legend-unavailable me-1"></span>
                                    <small>Unavailable</small>
                                </span>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Select a date within the next 14 days to reschedule the appointment
                            </small>
                        </div>
                        
                        <div class="calendar-grid"></div>
                        
                        <div class="time-slots-container" style="display: none;">
                            <h6 class="mt-4 mb-3">Available Time Slots</h6>
                            <div class="time-group">
                                <div class="time-group-header">Morning (AM)</div>
                                <div class="time-slots" id="am-slots"></div>
                            </div>
                            <div class="time-group mt-3">
                                <div class="time-group-header">Afternoon (PM)</div>
                                <div class="time-slots" id="pm-slots"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Selection Summary and Form -->
                <div class="col-lg-4">
                    <div class="selection-summary">
                        <h6>Reschedule Summary</h6>
                        <div class="alert alert-info" id="selection-info">
                            <i class="bi bi-calendar-x me-2"></i>
                            Please select a new date and time
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="admin_message" class="form-label">Message to User <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="admin_message" name="admin_message" rows="4" required placeholder="Explain why you're rescheduling this appointment..."></textarea>
                                <small class="form-text text-muted">This message will be sent to the user explaining the reschedule reason.</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning" id="confirm-btn" disabled>
                                    <i class="bi bi-calendar-check"></i>
                                    Reschedule Appointment
                                </button>
                                <a href="appointment.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDate = new Date();
        let selectedDate = null;
        let selectedTime = null;

        function generateCalendar(date) {
            const year = date.getFullYear();
            const month = date.getMonth();
            
            // Update month/year display
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;
            
            // Clear calendar grid
            const calendarGrid = document.querySelector('.calendar-grid');
            calendarGrid.innerHTML = '';
            
            // Add day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'calendar-day-header';
                dayHeader.textContent = day;
                calendarGrid.appendChild(dayHeader);
            });
            
            // Get first day of month and number of days
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();
            
            // Add empty cells for days before the first day of the month
            for (let i = 0; i < startingDayOfWeek; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day disabled';
                calendarGrid.appendChild(emptyDay);
            }
            
            // Add days of the month
            const today = new Date();
            today.setHours(0,0,0,0);
            const maxDate = new Date();
            maxDate.setDate(maxDate.getDate() + 14); // 14 days from today
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                dayElement.textContent = day;
                
                const currentDayDate = new Date(year, month, day);
                
                // Disable past dates and dates beyond 14 days
                if (currentDayDate < today || currentDayDate > maxDate) {
                    dayElement.classList.add('disabled');
                } else {
                    dayElement.addEventListener('click', (event) => selectDate(currentDayDate, event));
                }
                
                calendarGrid.appendChild(dayElement);
            }
        }

        function selectDate(date, event) {
            // Remove previous selection
            document.querySelectorAll('.calendar-day.selected').forEach(day => {
                day.classList.remove('selected');
            });
            
            // Add selection to clicked day
            event.target.classList.add('selected');
            
            selectedDate = date;
            // Fix timezone offset issue - use local date instead of UTC
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            // Update hidden form fields
            document.getElementById('selected_date').value = dateStr;
            
            // Load time slots for selected date
            loadTimeSlots(dateStr);
        }

        function loadTimeSlots(dateStr) {
            const formData = new FormData();
            formData.append('date', dateStr);
            
            fetch('get_reschedule_slots.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                displayTimeSlots(data);
                document.querySelector('.time-slots-container').style.display = 'block';
                updateSelectionSummary();
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function displayTimeSlots(data) {
            const amSlotsContainer = document.getElementById('am-slots');
            const pmSlotsContainer = document.getElementById('pm-slots');
            
            // Clear existing slots
            amSlotsContainer.innerHTML = '';
            pmSlotsContainer.innerHTML = '';
            
            // Create unavailable slots lookup
            const unavailableSlots = {};
            data.unavailable_slots.forEach(slot => {
                unavailableSlots[slot.time] = slot.status;
            });
            
            // Display AM slots
            data.am_slots.forEach(slot => {
                const slotElement = createTimeSlotElement(slot, unavailableSlots[slot]);
                amSlotsContainer.appendChild(slotElement);
            });
            
            // Display PM slots
            data.pm_slots.forEach(slot => {
                const slotElement = createTimeSlotElement(slot, unavailableSlots[slot]);
                pmSlotsContainer.appendChild(slotElement);
            });
        }

        function createTimeSlotElement(time, status) {
            const slotElement = document.createElement('div');
            slotElement.className = 'time-slot';
            slotElement.textContent = time;
            
            if (status) {
                slotElement.classList.add('unavailable', status);
                slotElement.title = `This slot is ${status}`;
            } else {
                slotElement.addEventListener('click', () => selectTimeSlot(slotElement, time));
            }
            
            return slotElement;
        }

        function selectTimeSlot(element, time) {
            // Remove previous time selection
            document.querySelectorAll('.time-slot.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Add selection to clicked time slot
            element.classList.add('selected');
            selectedTime = time;
            
            // Update hidden form field
            document.getElementById('selected_time').value = time;
            
            updateSelectionSummary();
            document.getElementById('confirm-btn').disabled = false;
        }

        function updateSelectionSummary() {
            const summaryElement = document.getElementById('selection-info');
            
            if (selectedDate && selectedTime) {
                const dateStr = selectedDate.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                summaryElement.innerHTML = `
                    <i class="bi bi-calendar-check me-2"></i>
                    <strong>New Date:</strong> ${dateStr}<br>
                    <i class="bi bi-clock me-2"></i>
                    <strong>New Time:</strong> ${selectedTime}
                `;
                summaryElement.className = 'alert alert-success';
            } else if (selectedDate) {
                const dateStr = selectedDate.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                summaryElement.innerHTML = `
                    <i class="bi bi-calendar-check me-2"></i>
                    <strong>Selected Date:</strong> ${dateStr}<br>
                    <i class="bi bi-clock me-2"></i>
                    Please select a time slot
                `;
                summaryElement.className = 'alert alert-info';
            } else {
                summaryElement.innerHTML = `
                    <i class="bi bi-calendar-x me-2"></i>
                    Please select a new date and time
                `;
                summaryElement.className = 'alert alert-info';
            }
        }

        // Navigation event listeners
        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar(currentDate);
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar(currentDate);
        });

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            if (!selectedDate || !selectedTime) {
                e.preventDefault();
                alert('Please select both a date and time before submitting.');
                return;
            }
            
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        // Initialize calendar
        generateCalendar(currentDate);
    </script>
</body>
</html>
