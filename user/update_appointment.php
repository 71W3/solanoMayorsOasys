<?php
// update_appointment.php

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Database connection
    $servername = "localhost";
    $dbusername = "root";
    $dbpassword = "";
    $dbname = "my_auth_db";
    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get and sanitize input
    $appointment_id = intval($_POST['appointment_id']);
    $purpose = trim($_POST['purpose']);
    $attendees = intval($_POST['attendees']);

    // Only allow updating appointments belonging to the logged-in user
    $user_id = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("UPDATE appointments SET purpose = ?, attendees = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("siii", $purpose, $attendees, $appointment_id, $user_id);

    // --- Handle attachment removal ---
    if (isset($_POST['remove_attachments']) && is_array($_POST['remove_attachments'])) {
        // Fetch current attachments
        $fetchStmt = $conn->prepare("SELECT attachments FROM appointments WHERE id = ? AND user_id = ?");
        $fetchStmt->bind_param("ii", $appointment_id, $user_id);
        $fetchStmt->execute();
        $fetchStmt->bind_result($currentAttachments);
        $fetchStmt->fetch();
        $fetchStmt->close();

        $attachmentsArr = array_filter(array_map('trim', explode(',', $currentAttachments)));
        $toRemove = $_POST['remove_attachments'];
        $newAttachmentsArr = array_diff($attachmentsArr, $toRemove);

        // Optionally, delete files from uploads/ directory
        foreach ($toRemove as $file) {
            $filePath = __DIR__ . '/uploads/' . basename($file);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $newAttachmentsStr = implode(',', $newAttachmentsArr);
        // Update the attachments column
        $updateAttachStmt = $conn->prepare("UPDATE appointments SET attachments = ? WHERE id = ? AND user_id = ?");
        $updateAttachStmt->bind_param("sii", $newAttachmentsStr, $appointment_id, $user_id);
        $updateAttachStmt->execute();
        $updateAttachStmt->close();
    }

    if ($stmt->execute()) {
        $_SESSION['update_success'] = true;
        header('Location: userAppointment.php?tab=pending');
        exit;
    } else {
        $_SESSION['update_error'] = true;
        header('Location: userAppointment.php?tab=pending&error=1');
        exit;
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>