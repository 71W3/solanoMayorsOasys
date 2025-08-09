<?php
$conn = new mysqli("localhost", "root", "", "my_auth_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Manila'); // Set to your local timezone

$date = isset($_POST['date']) ? $conn->real_escape_string($_POST['date']) : '';

// Return array of {time, status}
$unavailable = [];
if (!empty($date)) {
    $sql = "SELECT time, status_enum FROM appointments WHERE date = '$date' AND (status_enum = 'pending' OR status_enum = 'approved')";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $formatted = date('g:i A', strtotime($row['time']));
        $unavailable[] = [
            'time' => $formatted,
            'status' => strtolower($row['status_enum'])
        ];
    }
}
echo json_encode($unavailable);
?>