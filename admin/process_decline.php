<?php
session_start();

// 1. First include the connection file
require_once __DIR__ . '/connect.php';

// 2. Then include other dependencies
require_once __DIR__ . '/../user/email_helper_phpmailer.php';

// 3. Set JSON header
header('Content-Type: application/json');

// 4. Enhanced session validation
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login as admin.'
    ]));
}

// 5. Validate input
$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$decline_reason = trim(filter_input(INPUT_POST, 'decline_reason', FILTER_SANITIZE_STRING));

if (!$appointment_id || empty($decline_reason)) {
    http_response_code(400);
    die(json_encode([
        'success' => false, 
        'message' => 'Invalid input data'
    ]));
}

try {
    // 6. Get appointment details with additional validation
    $stmt = $con->prepare("
        SELECT a.*, u.email, u.name 
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    
    if (!$appointment) {
        http_response_code(404);
        die(json_encode([
            'success' => false,
            'message' => 'Appointment not found'
        ]));
    }

    // 7. Start transaction
    $con->begin_transaction();
    
    // Update appointment status
    $update_stmt = $con->prepare("UPDATE appointments SET status_enum = 'declined', updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $appointment_id);
    $update_stmt->execute();
    
    // Record decline reason
    $decline_stmt = $con->prepare("INSERT INTO decline_table (app_id, reason) VALUES (?, ?)");
    $decline_stmt->bind_param("is", $appointment_id, $decline_reason);
    $decline_stmt->execute();
    
    // Send decline email
    $email_result = sendAppointmentDeclinedEmail(
        $appointment['email'],
        $appointment['name'],
        $appointment['date'],
        $appointment['time'],
        $appointment['purpose'],
        $decline_reason
    );

    $con->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment declined successfully',
        'email_sent' => $email_result['success']
    ]);

} catch (Exception $e) {
    $con->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}