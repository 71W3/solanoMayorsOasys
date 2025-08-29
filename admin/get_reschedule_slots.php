<?php
session_start();
include "connect.php";

// Handle AJAX request for time slots
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date'])) {
    header('Content-Type: application/json');
    
    $selected_date = $con->real_escape_string($_POST['date']);

    // Define time slots
    $am_slots = [
        "8:00 AM", "8:30 AM", "9:00 AM", "9:30 AM", "10:00 AM", "10:30 AM", "11:00 AM", "11:30 AM"
    ];
    $pm_slots = [
        "1:00 PM", "1:30 PM", "2:00 PM", "2:30 PM", "3:00 PM", "3:30 PM", "4:00 PM", "4:30 PM", "5:00 PM"
    ];

    // Mark past time slots as unavailable if the selected date is today
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $past_slots = [];

    if ($selected_date === $today) {
        // Check AM slots for past times
        foreach ($am_slots as $index => $slot) {
            $slot_time = date('H:i:s', strtotime($slot));
            if ($slot_time <= $current_time) {
                $past_slots[] = [
                    'time' => $slot,
                    'status' => 'past'
                ];
            }
        }
        
        // Check PM slots for past times
        foreach ($pm_slots as $index => $slot) {
            $slot_time = date('H:i:s', strtotime($slot));
            if ($slot_time <= $current_time) {
                $past_slots[] = [
                    'time' => $slot,
                    'status' => 'past'
                ];
            }
        }
    }

    // Get existing appointments for the selected date
    $result = $con->query("
        SELECT time, status_enum as status 
        FROM appointments 
        WHERE date = '$selected_date' 
        AND (status_enum = 'pending' OR status_enum = 'approved')
    ");

    $unavailable_slots = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Convert 24-hour time to 12-hour format
            $time_12hr = date('g:i A', strtotime($row['time']));
            $unavailable_slots[] = [
                'time' => $time_12hr,
                'status' => $row['status']
            ];
        }
    }

    // Merge past slots with unavailable slots
    $unavailable_slots = array_merge($unavailable_slots, $past_slots);

    echo json_encode([
        'am_slots' => $am_slots,
        'pm_slots' => $pm_slots,
        'unavailable_slots' => $unavailable_slots
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Slots - SOLAR Appointment System</title>
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

        .btn-outline-secondary {
            border: 1px solid var(--border);
            color: var(--text-secondary);
            background: transparent;
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

        .time-slot.past {
            background: #f8f9fa !important;
            color: #adb5bd !important;
            border: 1px solid #dee2e6 !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.5;
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
                Available Reschedule Slots
            </h1>
            <a href="appointment.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Back to Appointments
            </a>
        </div>

        <!-- Calendar Section -->
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
                    <span class="d-flex align-items-center">
                        <span class="legend-dot" style="background: #f8f9fa; border-color: #adb5bd;"></span>
                        <small>Past Time</small>
                    </span>
                </div>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Select a date to view available time slots
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

        // Initialize calendar
        generateCalendar(currentDate);
    </script>
</body>
</html>
