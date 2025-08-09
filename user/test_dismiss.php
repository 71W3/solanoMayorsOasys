<?php
session_start();

// Simulate a logged-in user for testing
$_SESSION['user_id'] = 1;

// Database connection
$conn = new mysqli("localhost", "root", "", "my_auth_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
    die("Failed to create dismissed_notifications table");
}

echo "<h2>Testing Notification Dismissal</h2>";

// Test 1: Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'dismissed_notifications'");
if ($result->num_rows > 0) {
    echo "<p>✅ dismissed_notifications table exists</p>";
} else {
    echo "<p>❌ dismissed_notifications table does not exist</p>";
}

// Test 2: Check current dismissed notifications
$user_id = 1;
$check_sql = "SELECT COUNT(*) as count FROM dismissed_notifications WHERE user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p>Current dismissed notifications for user $user_id: " . $row['count'] . "</p>";

// Test 3: Try to dismiss a notification
$test_notification_id = 1; // Assuming notification ID 1 exists
$insert_sql = "INSERT IGNORE INTO dismissed_notifications (user_id, appointment_id) VALUES (?, ?)";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("ii", $user_id, $test_notification_id);

if ($stmt->execute()) {
    echo "<p>✅ Successfully dismissed notification $test_notification_id</p>";
} else {
    echo "<p>❌ Failed to dismiss notification: " . $stmt->error . "</p>";
}

// Test 4: Check dismissed notifications again
$check_sql = "SELECT COUNT(*) as count FROM dismissed_notifications WHERE user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p>Dismissed notifications after test: " . $row['count'] . "</p>";

$conn->close();
?> 