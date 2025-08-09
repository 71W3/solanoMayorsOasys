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
    <title>Schedule Calendar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        <?php include 'dashboard-style.css'; ?>

        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .wrapper {
            
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex-grow: 1;
            background-color: #f8f9fa;
        }

        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        #calendar {
            max-width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }

        .fc-event {
            border: none;
            font-size: 0.9em;
            padding: 3px 5px;
            margin-bottom: 2px;
        }

        .fc-event.regular-appointment {
            background-color: #0055a4;
            border-left: 4px solid #003366;
        }

        .fc-event.mayor-appointment {
            background-color: #ff6b35;
            border-left: 4px solid #cc4c2c;
        }

        .fc-event-title {
            font-weight: 500;
            white-space: normal;
        }

        .legend {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 0.9em;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            margin-right: 8px;
        }

        .fc-daygrid-event-dot {
            display: none;
        }

        .fc-more-link {
            font-weight: bold;
            background: #f0f0f0;
            border-radius: 3px;
            padding: 2px 4px;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="wrapper">
    <div class="main-content">
        <div class="content-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Appointment Schedule</h2>
                <p class="text-muted mb-0">View and manage daily appointments</p>
            </div>

            <div id="calendar"></div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #0055a4;"></div>
                    <span>Regular Appointments</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ff6b35;"></div>
                    <span>Mayor's Appointments</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="function.php">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="sched_id" id="schedId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="modalName" class="d-block p-2 bg-light rounded"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Purpose:</strong> <span id="modalPurpose" class="d-block p-2 bg-light rounded"></span></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <p><strong>Attendees:</strong> <span id="modalAttendees" class="d-block p-2 bg-light rounded"></span></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Date:</strong> <span id="modalDate" class="d-block p-2 bg-light rounded"></span></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Time:</strong> <span id="modalTime" class="d-block p-2 bg-light rounded"></span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label fw-bold">Admin Notes</label>
                        <textarea name="note" class="form-control" id="note" rows="4" placeholder="Add notes about this appointment..."></textarea>
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
    const calendarEl = document.getElementById('calendar');
    const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));

    const appointments = <?= json_encode($appointments) ?>;

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
        if (appt.is_mayor_appointment) {
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
            className: appt.appointment_type === 'mayor' ? 
                'mayor-appointment' : 'regular-appointment',
            display: 'block',
            overlap: false
        };
    });

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 700,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: events,
        eventDisplay: 'block',
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: 'short',
            hour12: true
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
        dayMaxEvents: 10, // Show up to 3 events before showing "more" link
        moreLinkText: function(num) {
            return num + ' appointments'; // Changed from "+2 more" to "2 appointments"
        },
        moreLinkClassNames: 'fc-more-link',
        eventOrder: 'start'
    });

    calendar.render();
});
</script>
</body>
</html>