<?php
include "connect.php";
//adminPanel.php Function

// Function to display time elapsed in a human-readable format
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Total Appointments
$result = $con->query("SELECT COUNT(*) FROM appointments");
$row = $result->fetch_row();
$totalAppointments = $row[0];

// Completed Appointments (using status_enum)
$result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'completed'");
$row = $result->fetch_row();
$completedAppointments = $row[0];

// Pending Appointments
$result = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'pending'");
$row = $result->fetch_row();
$pendingAppointments = $row[0];

// Registered Users
$result = $con->query("SELECT COUNT(*) FROM users");
$row = $result->fetch_row();
$registeredUsers = $row[0];

// Get today's date in YYYY-MM-DD for SQL
$today = date('Y-m-d');

// Updated Query: Get today's appointments
$sql = "
    SELECT 
        a.id,
        u.name AS resident_name,
        a.time,
        a.date,
        a.purpose,
        a.status_enum AS status
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = '$today'
    ORDER BY a.time ASC
";

$todayAppointments = $con->query($sql);

$recentActivity = $con->query("
    SELECT 
        a.id AS appointment_id,
        u.name AS resident_name,
        a.time,
        a.date,
        a.purpose,
        a.status_enum AS status,
        a.updated_at
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.updated_at DESC
    LIMIT 5
");

$mayorsAppointments = $con->query("
    SELECT 
        m.id,
        m.appointment_title,
        m.description,
        m.time,
        m.date,
        m.place,
        COUNT(s.sched_id) AS attendee_count
    FROM mayors_appointment m
    LEFT JOIN schedule s ON m.id = s.mayor_id
    WHERE m.date >= CURDATE()
    GROUP BY m.id
    ORDER BY m.date ASC, m.time ASC
    LIMIT 5
");



//schedule.php Functions

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

//announcement.php Functions

// Get all announcements
function get_all_announcements() {
    global $con;
    $sql = "SELECT * FROM announcement ORDER BY id DESC";
    $result = mysqli_query($con, $sql);
    $announcements = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $announcements[] = $row;
        }
    }
    
    return $announcements;
}

// Add announcement
function add_announcement($title, $description, $image) {
    global $con;
    
    // Handle image upload
    $imageData = null;
    if ($image && $image['error'] == UPLOAD_ERR_OK) {
        $imageData = file_get_contents($image['tmp_name']);
    }
    
    $stmt = mysqli_prepare($con, "INSERT INTO announcement (title, description, image) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssb", $title, $description, $imageData);
    mysqli_stmt_send_long_data($stmt, 2, $imageData);
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Update announcement
function update_announcement($id, $title, $description, $image) {
    global $con;
    
    // Check if image is being updated
    if ($image && $image['error'] == UPLOAD_ERR_OK) {
        $imageData = file_get_contents($image['tmp_name']);
        $stmt = mysqli_prepare($con, "UPDATE announcement SET title = ?, description = ?, image = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssbi", $title, $description, $imageData, $id);
        mysqli_stmt_send_long_data($stmt, 2, $imageData);
    } else {
        $stmt = mysqli_prepare($con, "UPDATE announcement SET title = ?, description = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $title, $description, $id);
    }
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Delete announcement
function delete_announcement($id) {
    global $con;
    $stmt = mysqli_prepare($con, "DELETE FROM announcement WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}
