<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get the notification ID from POST data
$notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;

// Debug logging
error_log("dismiss_notification.php called with notification_id: $notification_id, user_id: " . $_SESSION['user_id']);

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid notification ID']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "oasys");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check if dismissed_notifications table exists, if not create it
$create_table_sql = "CREATE TABLE IF NOT EXISTS dismissed_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    appointment_id INT NOT NULL,
    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dismissal (user_id, appointment_id)
)";

if (!$conn->query($create_table_sql)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create dismissed_notifications table']);
    $conn->close();
    exit();
}

// Insert the dismissed notification
$user_id = $_SESSION['user_id'];
$insert_sql = "INSERT IGNORE INTO dismissed_notifications (user_id, appointment_id) VALUES (?, ?)";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("ii", $user_id, $notification_id);

if ($stmt->execute()) {
    error_log("Successfully dismissed notification $notification_id for user " . $_SESSION['user_id']);
    echo json_encode(['success' => true, 'message' => 'Notification dismissed']);
} else {
    error_log("Failed to dismiss notification $notification_id for user " . $_SESSION['user_id'] . ": " . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to dismiss notification']);
}

$stmt->close();
$conn->close();
?> 