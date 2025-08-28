<?php
/**
 * Process Appointment Approval with Email Notification
 * This file handles the approval of appointments and sends confirmation emails
 */
require_once __DIR__ . '/connect.php';

// 2. Then include other dependencies
require_once __DIR__ . '/../user/email_helper_phpmailer.php';
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include necessary files
include "connect.php";
include "email_helper.php"; // Make sure this includes our new approval email function

// Check if required data is provided
if (!isset($_POST['appointment_id']) || !isset($_POST['action']) || $_POST['action'] !== 'approve') {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit();
}

$appointment_id = intval($_POST['appointment_id']);
$admin_message = trim($_POST['admin_message'] ?? ''); // Optional admin message

try {
    // Start transaction for data consistency
    $con->begin_transaction();
    
    // Get appointment details with user information
    $stmt = $con->prepare("
        SELECT a.*, u.name as user_name, u.email as user_email 
        FROM appointments a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ? AND a.status_enum = 'pending'
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found or already processed");
    }
    
    $appointment = $result->fetch_assoc();
    
    // Update appointment status to 'approved'
    $stmt = $con->prepare("UPDATE appointments SET status_enum = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update appointment status");
    }
    
    // Insert into schedule table for approved appointments
    $stmt = $con->prepare("INSERT INTO schedule (app_id, user_id, note, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $schedule_note = !empty($admin_message) ? "Approved with message: " . $admin_message : "Approved by admin";
    $stmt->bind_param("iis", $appointment['id'], $appointment['user_id'], $schedule_note);
    if (!$stmt->execute()) {
        throw new Exception("Failed to create schedule entry");
    }
    
    // Send approval email to the user
    $email_result = sendAppointmentApprovedEmail(
        $appointment['user_email'],
        $appointment['user_name'],
        $appointment['date'],
        $appointment['time'],
        $appointment['purpose'],
        $appointment['attendees'],
        $appointment['other_details'],
        $admin_message
    );
    
    // Commit the database transaction
    $con->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Appointment approved successfully',
        'email_sent' => $email_result['success'],
        'email_message' => $email_result['message'],
        'appointment_id' => $appointment_id
    ];
    
    // Log the approval action
    error_log("Appointment #$appointment_id approved by admin. Email " . ($email_result['success'] ? 'sent' : 'failed'));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($con->inTransaction ?? false) {
        $con->rollback();
    }
    
    // Log the error
    error_log("Approval error for appointment #$appointment_id: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$con->close();
?>