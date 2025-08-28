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
    <link href="userStyles/reschedule.css" rel="stylesheet">
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