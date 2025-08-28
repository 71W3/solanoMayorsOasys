<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set proper headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("DISMISS ERROR: User not logged in");
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "my_auth_db");
if ($conn->connect_error) {
    error_log("DISMISS ERROR: Database connection failed - " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Set charset to ensure proper data handling
$conn->set_charset("utf8");

$user_id = $_SESSION['user_id'];

// Log session info
error_log("DISMISS DEBUG: User ID from session: $user_id");
error_log("DISMISS DEBUG: Full session: " . print_r($_SESSION, true));
error_log("DISMISS DEBUG: POST data: " . print_r($_POST, true));

// Get the appointment ID from POST data
$appointment_id = null;
if (isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
} elseif (isset($_POST['notification_id'])) {
    $appointment_id = (int)$_POST['notification_id'];
}

if (!$appointment_id || $appointment_id <= 0) {
    error_log("DISMISS ERROR: Invalid appointment ID provided: " . var_export($appointment_id, true));
    echo json_encode(['success' => false, 'error' => 'No valid appointment ID provided']);
    exit();
}

error_log("DISMISS DEBUG: Processing dismissal - User: $user_id, Appointment: $appointment_id");

// Ensure the dismissed_notifications table exists with correct structure
$create_table_sql = "CREATE TABLE IF NOT EXISTS dismissed_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    appointment_id INT NOT NULL,
    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dismissal (user_id, appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (!$conn->query($create_table_sql)) {
    error_log("DISMISS ERROR: Failed to create table: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Failed to create table']);
    exit();
}

// Verify that this appointment belongs to the current user
$verify_sql = "SELECT id, purpose, status_enum FROM appointments WHERE id = ? AND user_id = ?";
$verify_stmt = $conn->prepare($verify_sql);
if (!$verify_stmt) {
    error_log("DISMISS ERROR: Failed to prepare verify statement: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database prepare error']);
    exit();
}

$verify_stmt->bind_param("ii", $appointment_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    error_log("DISMISS ERROR: Appointment $appointment_id not found for user $user_id or access denied");
    echo json_encode(['success' => false, 'error' => 'Appointment not found or access denied']);
    $verify_stmt->close();
    exit();
}

$appointment_data = $verify_result->fetch_assoc();
error_log("DISMISS DEBUG: Verified appointment - ID: {$appointment_data['id']}, Purpose: {$appointment_data['purpose']}, Status: {$appointment_data['status_enum']}");
$verify_stmt->close();

// Check if already dismissed
$check_sql = "SELECT id FROM dismissed_notifications WHERE user_id = ? AND appointment_id = ?";
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    error_log("DISMISS ERROR: Failed to prepare check statement: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database prepare error']);
    exit();
}

$check_stmt->bind_param("ii", $user_id, $appointment_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    error_log("DISMISS INFO: Notification already dismissed - User: $user_id, Appointment: $appointment_id");
    echo json_encode(['success' => true, 'message' => 'Notification was already dismissed']);
    $check_stmt->close();
    exit();
}
$check_stmt->close();

// Start transaction to ensure data integrity
$conn->autocommit(FALSE);

try {
    // Insert the dismissal
    $insert_sql = "INSERT INTO dismissed_notifications (user_id, appointment_id, dismissed_at) VALUES (?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception("Failed to prepare insert statement: " . $conn->error);
    }
    
    $insert_stmt->bind_param("ii", $user_id, $appointment_id);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to execute insert: " . $insert_stmt->error);
    }
    
    if ($insert_stmt->affected_rows <= 0) {
        throw new Exception("No rows were inserted");
    }
    
    $dismiss_id = $conn->insert_id;
    error_log("DISMISS SUCCESS: Inserted dismissal with ID: $dismiss_id");
    
    // Commit the transaction
    $conn->commit();
    
    // Verify the insertion
    $verify_insert_sql = "SELECT id, dismissed_at FROM dismissed_notifications WHERE user_id = ? AND appointment_id = ?";
    $verify_insert_stmt = $conn->prepare($verify_insert_sql);
    $verify_insert_stmt->bind_param("ii", $user_id, $appointment_id);
    $verify_insert_stmt->execute();
    $verify_insert_result = $verify_insert_stmt->get_result();
    
    if ($verify_insert_result->num_rows > 0) {
        $inserted_data = $verify_insert_result->fetch_assoc();
        error_log("DISMISS VERIFIED: Dismissal confirmed in database - ID: {$inserted_data['id']}, Time: {$inserted_data['dismissed_at']}");
        echo json_encode([
            'success' => true, 
            'message' => 'Notification dismissed successfully',
            'dismiss_id' => $inserted_data['id'],
            'dismissed_at' => $inserted_data['dismissed_at']
        ]);
    } else {
        throw new Exception("Dismissal not found after insertion");
    }
    
    $verify_insert_stmt->close();
    $insert_stmt->close();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("DISMISS ERROR: Transaction failed - " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to dismiss notification: ' . $e->getMessage()]);
}

// Restore autocommit
$conn->autocommit(TRUE);
$conn->close();
?>