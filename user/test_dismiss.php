<?php
session_start();

// Set proper headers for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "my_auth_db");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the appointment ID from POST data
$appointment_id = null;
if (isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
} elseif (isset($_POST['notification_id'])) {
    // Handle both parameter names for backward compatibility
    $appointment_id = (int)$_POST['notification_id'];
}

if (!$appointment_id) {
    echo json_encode(['success' => false, 'error' => 'No appointment ID provided']);
    exit();
}

// Log the dismissal attempt
error_log("User $user_id attempting to dismiss notification for appointment $appointment_id");

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

if (!$conn->query($create_table_sql)) {
    error_log("Failed to create dismissed_notifications table: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Failed to create table']);
    exit();
}

// Verify that this appointment belongs to the current user
$verify_sql = "SELECT id FROM appointments WHERE id = ? AND user_id = ?";
$verify_stmt = $conn->prepare($verify_sql);
if (!$verify_stmt) {
    error_log("Failed to prepare verify statement: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

$verify_stmt->bind_param("ii", $appointment_id, $user_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Appointment $appointment_id not found for user $user_id");
    echo json_encode(['success' => false, 'error' => 'Appointment not found or access denied']);
    exit();
}
$verify_stmt->close();

// Check if notification is already dismissed
$check_sql = "SELECT id FROM dismissed_notifications WHERE user_id = ? AND appointment_id = ?";
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    error_log("Failed to prepare check statement: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

$check_stmt->bind_param("ii", $user_id, $appointment_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    error_log("Notification for appointment $appointment_id already dismissed by user $user_id");
    echo json_encode(['success' => true, 'message' => 'Notification was already dismissed']);
    $check_stmt->close();
    $conn->close();
    exit();
}
$check_stmt->close();

// Insert into dismissed_notifications table
$insert_sql = "INSERT INTO dismissed_notifications (user_id, appointment_id, dismissed_at) VALUES (?, ?, NOW())";
$insert_stmt = $conn->prepare($insert_sql);

if (!$insert_stmt) {
    error_log("Failed to prepare insert statement: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement']);
    exit();
}

$insert_stmt->bind_param("ii", $user_id, $appointment_id);

if ($insert_stmt->execute()) {
    if ($insert_stmt->affected_rows > 0) {
        error_log("Successfully dismissed notification for appointment $appointment_id by user $user_id");
        
        // Verify the insertion by checking the database
        $verify_insert_sql = "SELECT id FROM dismissed_notifications WHERE user_id = ? AND appointment_id = ?";
        $verify_insert_stmt = $conn->prepare($verify_insert_sql);
        $verify_insert_stmt->bind_param("ii", $user_id, $appointment_id);
        $verify_insert_stmt->execute();
        $verify_result = $verify_insert_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            error_log("Dismissal verified in database for appointment $appointment_id");
            echo json_encode(['success' => true, 'message' => 'Notification dismissed successfully']);
        } else {
            error_log("WARNING: Dismissal not found in database after insertion for appointment $appointment_id");
            echo json_encode(['success' => false, 'error' => 'Dismissal not saved properly']);
        }
        $verify_insert_stmt->close();
    } else {
        error_log("No rows affected when dismissing notification for appointment $appointment_id");
        echo json_encode(['success' => false, 'error' => 'No rows affected']);
    }
} else {
    error_log("Failed to dismiss notification for appointment $appointment_id: " . $insert_stmt->error);
    echo json_encode(['success' => false, 'error' => 'Failed to dismiss notification: ' . $insert_stmt->error]);
}

$insert_stmt->close();
$conn->close();
?>