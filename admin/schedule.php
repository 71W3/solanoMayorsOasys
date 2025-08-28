    <?php
    include "connect.php";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $schedId = $_POST['sched_id'];
        $note = $_POST['note'];

        $stmt = $con->prepare("UPDATE schedule SET note = ? WHERE sched_id = ?");
        $stmt->bind_param("si", $note, $schedId);
        if ($stmt->execute()) {
            header("Location: schedule.php?success=1");
        } else {
            echo "Error saving note.";
        }
    }

    // Fetch all scheduled appointments
    $appointments = [];
    $result = $con->query("
        SELECT
            s.sched_id,
            a.id AS appointment_id,
            a.purpose,
            a.attendees,
            a.date,
            a.time,
            u.name AS resident_name,
            s.note,
            NULL AS is_mayor_appointment,
            'regular' AS appointment_type
        FROM schedule s
        JOIN appointments a ON s.app_id = a.id
        JOIN users u ON a.user_id = u.id
        
        UNION ALL
        
        SELECT
            s.sched_id,
            NULL AS appointment_id,
            m.appointment_title AS purpose,
            1 AS attendees,
            m.date,
            m.time,
            'Mayor' AS resident_name,
            CONCAT('Mayor\'s Appointment: ', m.description) AS note,
            1 AS is_mayor_appointment,
            'mayor' AS appointment_type
        FROM schedule s
        JOIN mayors_appointment m ON s.mayor_id = m.id
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    } else {
        error_log("Database query failed: " . $con->error);
        $appointments = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Schedule Calendar - SOLAR Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="adminStyles/schedule.css">
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
                            <h5>SOLAR Admin</h5>
                            <div class="version">Municipality of Solano</div>
                        </div>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <a href="adminPanel.php">
                        <i class="bi bi-house"></i>
                        Dashboard
                    </a>
                    <a href="appointment.php">
                        <i class="bi bi-calendar-check"></i>
                        Appointments
                    </a>
                    <a href="schedule.php" class="active">
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
                    <a href="#">
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
                            <div>
                                <h1 class="page-title">Schedule Calendar</h1>
                                <p class="text-muted mb-0 small d-none d-sm-block">View and manage appointment schedules</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-2 d-none d-sm-inline">Admin</span>
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content">
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                        <div class="success-alert">
                            <i class="bi bi-check-circle me-2"></i>Note saved successfully!
                        </div>
                    <?php endif; ?>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon">
                                    <i class="bi bi-calendar-day"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="todayCount">0</div>
                                    <div class="stats-label">Today</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon" style="background: rgba(5, 150, 105, 0.1); color: var(--success);">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="weekCount">0</div>
                                    <div class="stats-label">This Week</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--accent);">
                                    <i class="bi bi-calendar-month"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="monthCount">0</div>
                                    <div class="stats-label">This Month</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-card-content">
                                <div class="stats-icon" style="background: rgba(220, 38, 38, 0.1); color: var(--danger);">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div class="stats-info">
                                    <div class="stats-number" id="mayorCount">0</div>
                                    <div class="stats-label">Mayor's</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="section-title mb-0">Appointment Calendar</h2>
                        </div>
                        <div class="card-body p-0">
                            <div id="calendar"></div>
                        </div>
                        
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-dot regular"></div>
                                <span>Regular Appointments</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot mayor"></div>
                                <span>Mayor's Appointments</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="schedule.php">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Appointment Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="sched_id" id="schedId">
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Name</div>
                                    <div class="info-value" id="modalName"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Purpose</div>
                                    <div class="info-value" id="modalPurpose"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Attendees</div>
                                    <div class="info-value" id="modalAttendees"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Date</div>
                                    <div class="info-value" id="modalDate"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Time</div>
                                    <div class="info-value" id="modalTime"></div>
                                </div>
                            </div>
                            
                            <div class="notes-section">
                                <label for="note" class="form-label">Admin Notes</label>
                                <textarea name="note" class="form-control" id="note" rows="4" 
                                        placeholder="Add notes about this appointment..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="save_note">Save Note</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Mobile menu functionality
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');

                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', function() {
                        sidebar.classList.toggle('show');
                        overlay.classList.toggle('show');
                        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                    });
                }

                if (overlay) {
                    overlay.addEventListener('click', function() {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                    });
                }

                // Close sidebar on window resize if desktop
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });

                // Escape key to close sidebar
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });

                const calendarEl = document.getElementById('calendar');
                const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                
                // Your actual PHP data
                const appointments = <?= json_encode($appointments) ?>;

                // Calculate statistics
                const today = new Date().toISOString().split('T')[0];
                const thisWeekStart = new Date();
                thisWeekStart.setDate(thisWeekStart.getDate() - thisWeekStart.getDay());
                const thisMonthStart = new Date();
                thisMonthStart.setDate(1);

                let todayCount = 0;
                let weekCount = 0;
                let monthCount = 0;
                let mayorCount = 0;

                appointments.forEach(appt => {
                    if (appt.date === today) todayCount++;
                    if (new Date(appt.date) >= thisWeekStart) weekCount++;
                    if (new Date(appt.date) >= thisMonthStart) monthCount++;
                    if (appt.appointment_type === 'mayor' || appt.is_mayor_appointment) mayorCount++;
                });

                document.getElementById('todayCount').textContent = todayCount;
                document.getElementById('weekCount').textContent = weekCount;
                document.getElementById('monthCount').textContent = monthCount;
                document.getElementById('mayorCount').textContent = mayorCount;

                const events = appointments.map(appt => {
                    // Format time properly (9:00 AM instead of 9:0)
                    const timeParts = appt.time.split(':');
                    let hours = parseInt(timeParts[0]);
                    const minutes = timeParts[1] || '00';
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    const formattedTime = hours + ':' + (minutes.length === 1 ? '0' + minutes : minutes) + ' ' + ampm;
                    
                    // Format title
                    let title;
                    if (appt.is_mayor_appointment || appt.appointment_type === 'mayor') {
                        title = formattedTime + ' MAYOR - ' + appt.purpose;
                    } else {
                        title = formattedTime + ' ' + appt.resident_name + ' - ' + appt.purpose;
                    }
                    
                    return {
                        id: appt.sched_id,
                        title: title,
                        start: appt.date + 'T' + appt.time,
                        extendedProps: {
                            name: appt.resident_name,
                            purpose: appt.purpose,
                            attendees: appt.attendees,
                            time: appt.time,
                            date: appt.date,
                            note: appt.note,
                            isMayorAppointment: appt.is_mayor_appointment || 0,
                            type: appt.appointment_type
                        },
                        className: (appt.appointment_type === 'mayor' || appt.is_mayor_appointment) ? 
                            'mayor-appointment' : 'regular-appointment',
                        display: 'block',
                        overlap: false
                    };
                });

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: window.innerWidth < 768 ? 'timeGridDay' : 'dayGridMonth',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: window.innerWidth < 768 ? 
                            'dayGridMonth,timeGridDay' : 
                            'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: events,
                    eventDisplay: 'block',
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: 'short',
                        hour12: true
                    },
                    dayMaxEvents: 4,
                    moreLinkText: function(num) {
                        return `+${num} appointments`;
                    },
                    eventClick: function (info) {
                        const props = info.event.extendedProps;
                        document.getElementById('schedId').value = info.event.id;
                        document.getElementById('modalName').textContent = props.name;
                        document.getElementById('modalPurpose').textContent = props.purpose;
                        document.getElementById('modalAttendees').textContent = props.attendees;
                        
                        const dateObj = new Date(props.date);
                        const formattedDate = dateObj.toLocaleDateString('en-US', { 
                            month: 'long', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                        
                        const timeParts = props.time.split(':');
                        let hours = parseInt(timeParts[0]);
                        const minutes = timeParts[1] || '00';
                        const ampm = hours >= 12 ? 'PM' : 'AM';
                        hours = hours % 12;
                        hours = hours ? hours : 12;
                        const formattedTime = hours + ':' + (minutes.length === 1 ? '0' + minutes : minutes) + ' ' + ampm;
                        
                        document.getElementById('modalTime').textContent = formattedTime;
                        document.getElementById('modalDate').textContent = formattedDate;
                        document.getElementById('note').value = props.note || '';
                        
                        modal.show();
                    },
                    eventContent: function(arg) {
                        return {
                            html: `<div class="fc-event-title">${arg.event.title}</div>`
                        };
                    },
                    windowResize: function() {
                        if (window.innerWidth < 768) {
                            calendar.changeView('timeGridDay');
                        } else {
                            calendar.changeView('dayGridMonth');
                        }
                    },
                    eventOrder: 'start'
                });

                calendar.render();

                // Add click effects to stats cards
                document.querySelectorAll('.stats-card').forEach(card => {
                    card.addEventListener('click', function(e) {
                        e.preventDefault();
                        this.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 150);
                    });
                });

                // Handle form submission with loading state
                const form = document.querySelector('form[method="POST"]');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            const originalText = submitBtn.innerHTML;
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
                            
                            // Re-enable button after 3 seconds as fallback
                            setTimeout(() => {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }, 3000);
                        }
                    });
                }

                // Initialize tooltips if Bootstrap is available
                if (typeof bootstrap !== 'undefined') {
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }

                console.log('Modern schedule page initialized successfully');
            });
        </script>
    </body>
    </html>