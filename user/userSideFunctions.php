
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
            UNIQUE KEY unique_dismissal (user_id, appointment_id),
            INDEX idx_user_id (user_id),
            INDEX idx_appointment_id (appointment_id)
        )";
        $conn->query($create_table_sql);
        
        // Check for recent status changes (last 30 days) that haven't been dismissed
        $user_id = $_SESSION['user_id'];
        
        // MAIN QUERY: Get appointments that are NOT dismissed
        $sql = "SELECT a.id, a.purpose, a.date, a.time, a.status_enum, a.updated_at 
                FROM appointments a
                WHERE a.user_id = ? 
                AND a.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND a.status_enum IN ('approved', 'cancelled', 'declined', 'rescheduled', 'completed', 'Approved', 'Cancelled', 'Declined', 'Rescheduled', 'Completed')
                AND NOT EXISTS (
                    SELECT 1 FROM dismissed_notifications dn 
                    WHERE dn.user_id = ? AND dn.appointment_id = a.id
                )
                ORDER BY a.updated_at DESC
                LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Debug: Check what we're getting from the database
            error_log("Executing notification query for user: $user_id");
            
            while ($row = $result->fetch_assoc()) {
                // Additional debug: Check each notification
                error_log("Found notification: ID=" . $row['id'] . ", Status=" . $row['status_enum'] . ", Updated=" . $row['updated_at']);
                $status_notifications[] = $row;
            }
            $stmt->close();
        } else {
            error_log("Main notification query failed: " . $conn->error);
        }
        
        // Debug: Final notification count and details
        error_log("FINAL: Found " . count($status_notifications) . " non-dismissed notifications for user $user_id");
        
        // Add debug output to page (only in development)
        if (true) { // Set to false in production
            echo "<!-- DEBUG: Found " . count($status_notifications) . " notifications after filtering -->";
            echo "<script>console.log('PHP DEBUG: Found " . count($status_notifications) . " notifications after filtering');</script>";
            
            if (count($status_notifications) > 0) {
                foreach ($status_notifications as $notif) {
                    echo "<!-- DEBUG: Final Notification ID: " . $notif['id'] . ", Status: " . $notif['status_enum'] . ", Updated: " . $notif['updated_at'] . " -->";
                    echo "<script>console.log('PHP DEBUG: Final Notification - ID: " . $notif['id'] . ", Status: " . $notif['status_enum'] . "');</script>";
                }
            } else {
                echo "<!-- DEBUG: No notifications found after filtering dismissed ones -->";
                echo "<script>console.log('PHP DEBUG: No notifications found after filtering');</script>";
            }
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
           function dismissNotification(appointmentId, element = null) {
                console.log('Dismissing notification for appointment ID:', appointmentId);
                
                // If element not provided, find it by appointment ID
                if (!element) {
                    element = document.querySelector(`[data-notification-id="${appointmentId}"]`);
                }
                
                if (!element) {
                    console.error('Could not find notification element for ID:', appointmentId);
                    return;
                }
                
                fetch('dismiss_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'appointment_id=' + appointmentId
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Dismiss response:', data);
                    if (data.success) {
                        // Remove the notification from the UI with animation
                        const notificationItem = element.closest ? element.closest('.notification-item') : element;
                        if (notificationItem) {
                            // Add fade out animation
                            notificationItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            notificationItem.style.opacity = '0';
                            notificationItem.style.transform = 'translateX(100px)';
                            
                            // Remove after animation
                            setTimeout(() => {
                                notificationItem.remove();
                                updateNotificationCount();
                            }, 300);
                        }
                        
                        console.log('Notification dismissed successfully');
                    } else {
                        console.error('Failed to dismiss notification:', data.error);
                        // Show user-friendly error message
                        showToastMessage('Failed to dismiss notification: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToastMessage('An error occurred while dismissing the notification', 'error');
                });
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
                
                if (notificationItems.length === 0) {
                    showToastMessage('No notifications to dismiss', 'info');
                    return;
                }
                
                let dismissedCount = 0;
                let totalCount = notificationItems.length;
                let hasErrors = false;
                
                // Show loading state
                const clearAllBtn = document.querySelector('[onclick="markAllAsRead()"]');
                if (clearAllBtn) {
                    clearAllBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Clearing...';
                    clearAllBtn.disabled = true;
                }
                
                notificationItems.forEach(item => {
                    const notificationId = item.getAttribute('data-notification-id');
                    if (notificationId) {
                        fetch('dismiss_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `appointment_id=${notificationId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            dismissedCount++;
                            
                            if (data.success) {
                                // Add fade out animation
                                item.style.transition = 'opacity 0.3s ease';
                                item.style.opacity = '0';
                                
                                // Remove after animation
                                setTimeout(() => {
                                    item.remove();
                                }, 300);
                            } else {
                                hasErrors = true;
                                console.error('Failed to dismiss notification:', data.error);
                            }
                            
                            // Check if all notifications have been processed
                            if (dismissedCount === totalCount) {
                                setTimeout(() => {
                                    updateNotificationCount();
                                    
                                    // Reset button
                                    if (clearAllBtn) {
                                        clearAllBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Clear All';
                                        clearAllBtn.disabled = false;
                                    }
                                    
                                    // Show result message
                                    if (hasErrors) {
                                        showToastMessage('Some notifications could not be dismissed', 'warning');
                                    } else {
                                        showToastMessage('All notifications cleared successfully', 'success');
                                    }
                                }, 350);
                            }
                        })
                        .catch(error => {
                            dismissedCount++;
                            hasErrors = true;
                            console.error('Error dismissing notification:', error);
                            
                            // Still remove the notification locally
                            item.style.transition = 'opacity 0.3s ease';
                            item.style.opacity = '0';
                            setTimeout(() => {
                                item.remove();
                            }, 300);
                            
                            // Check if all notifications have been processed
                            if (dismissedCount === totalCount) {
                                setTimeout(() => {
                                    updateNotificationCount();
                                    
                                    // Reset button
                                    if (clearAllBtn) {
                                        clearAllBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Clear All';
                                        clearAllBtn.disabled = false;
                                    }
                                    
                                    showToastMessage('Some notifications could not be dismissed', 'warning');
                                }, 350);
                            }
                        });
                    } else {
                        dismissedCount++;
                        // Remove items without IDs immediately
                        item.remove();
                        
                        if (dismissedCount === totalCount) {
                            updateNotificationCount();
                            if (clearAllBtn) {
                                clearAllBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Clear All';
                                clearAllBtn.disabled = false;
                            }
                        }
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
