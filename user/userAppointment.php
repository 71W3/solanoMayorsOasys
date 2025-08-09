<?php
session_start();
$show_toast = false;
$show_reschedule_toast = false;
if (isset($_SESSION['appointment_success'])) {
    $show_toast = true;
    unset($_SESSION['appointment_success']);
}
if (isset($_SESSION['reschedule_success'])) {
    $show_reschedule_toast = true;
    unset($_SESSION['reschedule_success']);
}




// Handle tab parameter for navigation
$active_tab = 'upcoming'; // default tab
if (isset($_GET['tab'])) {
    $valid_tabs = ['upcoming', 'pending', 'lapsed', 'history'];
    if (in_array($_GET['tab'], $valid_tabs)) {
        $active_tab = $_GET['tab'];
    }
}

// Handle show_appointment parameter for auto-opening appointment details
$show_appointment_id = null;
if (isset($_GET['show_appointment'])) {
    $show_appointment_id = intval($_GET['show_appointment']);
}

// --- Restore your original code below this line ---

// Database connection
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_auth_db";
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch services for dropdown
$services = [];
$serviceResult = $conn->query("SELECT id, name FROM services");
if ($serviceResult && $serviceResult->num_rows > 0) {
    while ($service = $serviceResult->fetch_assoc()) {
        $services[$service['id']] = $service['name'];
    }
}

// Initialize user variables
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_initials = $username ? strtoupper(substr($username, 0, 2)) : 'GU';

// Status mapping
$statusMap = [
    'Pending' => 'Pending Approval',
    'Approved' => 'Confirmed',
    'Declined' => 'Declined',
    'declined' => 'Declined',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
    'cancelled' => 'Cancelled'
];

// Fetch appointments from database
$appointments = [];
if ($user_id) {
    $sql = "SELECT 
                a.id, 
                a.purpose AS title, 
                COALESCE(s.name, 'General Service') AS service,
                a.service_id,
                a.date, 
                a.time, 
                a.status_enum,
                a.purpose,
                a.attendees,
                a.created_at,
                a.updated_at,
                a.other_details,
                a.attachments,
                d.reason AS decline_reason  
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id 
            LEFT JOIN decline_table d ON a.id = d.app_id
            WHERE a.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $status = $statusMap[$row['status_enum']] ?? $row['status_enum'];
        
        // Process attachments
        $attachmentFiles = [];
        if ($row['attachments']) {
            $attachments = explode(',', $row['attachments']);
            foreach ($attachments as $attachment) {
                $fileType = pathinfo($attachment, PATHINFO_EXTENSION);
                $iconClass = '';
                $colorClass = '';
                
                // Determine icon and color based on file type
                switch (strtolower($fileType)) {
                    case 'pdf':
                        $iconClass = 'bi-file-earmark-pdf';
                        $colorClass = 'pdf-color';
                        break;
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        $iconClass = 'bi-file-earmark-image';
                        $colorClass = 'image-color';
                        break;
                    case 'doc':
                    case 'docx':
                        $iconClass = 'bi-file-earmark-word';
                        $colorClass = 'doc-color';
                        break;
                    case 'xls':
                    case 'xlsx':
                        $iconClass = 'bi-file-earmark-excel';
                        $colorClass = 'sheet-color';
                        break;
                    case 'txt':
                        $iconClass = 'bi-file-earmark-text';
                        $colorClass = 'text-color';
                        break;
                    case 'zip':
                    case 'rar':
                        $iconClass = 'bi-file-earmark-zip';
                        $colorClass = 'archive-color';
                        break;
                    default:
                        $iconClass = 'bi-file-earmark';
                        $colorClass = 'text-muted';
                }
                
                $attachmentFiles[] = [
                    'name' => $attachment,
                    'icon' => $iconClass,
                    'color' => $colorClass,
                    'type' => $fileType
                ];
            }
        }
        
       $appointments[] = [
            'id' => $row['id'],
            'app_id' => 'APP-' . $row['id'],
            'title' => $row['title'],
            'service' => $row['service'],
            'service_id' => $row['service_id'],
            'department' => 'Mayor\'s Office',
            'officer' => 'To be assigned',
            'date' => date('F j, Y', strtotime($row['date'])),
            'time' => date('g:i A', strtotime($row['time'])),
            'status' => $status,
            'purpose' => $row['purpose'],
            'attendees' => $row['attendees'],
            'requested' => date('F j, Y g:i A', strtotime($row['created_at'])),
            'updated' => $row['updated_at'] ? date('F j, Y g:i A', strtotime($row['updated_at'])) : '',
            'progress' => $status === 'Pending' || $status === 'Pending Approval' ? 30 : ($status === 'Confirmed' ? 60 : 100),
            'attachments' => $attachmentFiles,
            'timeline' => getTimeline($status, $row),
            'decline_reason' => $row['decline_reason'] ?? null // Added decline reason
        ];
    }
    $stmt->close();
}

// Function to generate timeline based on status
function getTimeline($status, $row) {
    $baseTimeline = [
        [
            'step' => 'Appointment Requested', 
            'date' => date('F j, Y g:i A', strtotime($row['created_at'])), 
            'status' => 'completed'
        ]
    ];
    
    switch ($status) {
        case 'Confirmed':
            return array_merge($baseTimeline, [
                ['step' => 'Appointment Confirmed', 'date' => date('F j, Y g:i A', strtotime($row['created_at'])), 'status' => 'completed'],
                ['step' => 'Scheduled', 'date' => date('F j, Y', strtotime($row['date'])) . ' at ' . date('g:i A', strtotime($row['time'])), 'status' => 'active'],
                ['step' => 'Completed', 'date' => '', 'status' => 'pending']
            ]);
        case 'Completed':
            $completedDate = $row['updated_at'] ? date('F j, Y g:i A', strtotime($row['updated_at'])) : date('F j, Y g:i A');
            return array_merge($baseTimeline, [
                ['step' => 'Appointment Confirmed', 'date' => date('F j, Y g:i A', strtotime($row['created_at'])), 'status' => 'completed'],
                ['step' => 'Scheduled', 'date' => date('F j, Y', strtotime($row['date'])) . ' at ' . date('g:i A', strtotime($row['time'])), 'status' => 'completed'],
                ['step' => 'Completed', 'date' => $completedDate, 'status' => 'completed']
            ]);
        case 'Cancelled':
            $cancelledDate = $row['updated_at'] ? date('F j, Y g:i A', strtotime($row['updated_at'])) : date('F j, Y g:i A');
            return array_merge($baseTimeline, [
                ['step' => 'Cancelled', 'date' => $cancelledDate, 'status' => 'completed']
            ]);
        case 'Declined':
            $declinedDate = $row['updated_at'] ? date('F j, Y g:i A', strtotime($row['updated_at'])) : date('F j, Y g:i A');
            return array_merge($baseTimeline, [
                ['step' => 'Declined', 'date' => $declinedDate, 'status' => 'completed']
            ]);
        default: // Pending
            return array_merge($baseTimeline, [
                ['step' => 'Pending Approval', 'date' => '', 'status' => 'active'],
                ['step' => 'Confirmation', 'date' => '', 'status' => 'pending'],
                ['step' => 'Scheduled', 'date' => '', 'status' => 'pending']
            ]);
    }
}

// Count appointment types
$counts = ['pending' => 0, 'upcoming' => 0, 'completed' => 0, 'cancelled' => 0, 'declined' => 0];
foreach ($appointments as $app) {
    switch ($app['status']) {
        case 'Pending Approval':
            $appointmentDateTime = strtotime($app['date'] . ' ' . $app['time']);
            if ($appointmentDateTime >= time()) $counts['pending']++;
            break;
        case 'Confirmed': $counts['upcoming']++; break;
        case 'Completed': $counts['completed']++; break;
        case 'Cancelled': $counts['cancelled']++; break;
        case 'Declined': $counts['declined']++; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solano Mayor's Office - Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0055a4;
            --primary-green: #28a745;
            --primary-orange: #ff6b35;
            --primary-yellow: #ffc107;
            --primary-light: #f8f9fa;
            --primary-dark: #212529;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            padding-bottom: 40px;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003a75 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 55px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
        
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            
        }
        
        .logo i {
            font-size: 30px;
            color: var(--primary-blue);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: black;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
        }
        
        .app-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
            background: linear-gradient(to right, white 60%, #f0f8ff 100%);
            border-left: 5px solid var(--primary-blue);
        }
        
        .appointment-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-bottom: 4px solid transparent;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .summary-card.pending {
            border-color: #FFC107;
        }
        
        .summary-card.upcoming {
            border-color: #2196F3;
        }
        
        .summary-card.completed {
            border-color: #28a745;
        }
        
        .summary-card.cancelled {
            border-color: #dc3545;
        }
        
        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        
        .pending .summary-icon {
            background: rgba(255, 193, 7, 0.1);
            color: #FFC107;
        }
        
        .upcoming .summary-icon {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }
        
        .completed .summary-icon {
            background: rgba(40, 167, 69, 0.1);
            color: var(--primary-green);
        }
        
        .cancelled .summary-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .declined .summary-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #c53030;
        }
        
        .summary-content {
            flex: 1;
        }
        
        .summary-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
            color: var(--primary-dark);
        }
        
        .summary-title {
            font-size: 0.95rem;
            color: #777;
        }
        
        .tab-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 0 20px;
            background: #f8f9fa;
        }
        
        .nav-tabs .nav-link {
            padding: 15px 25px;
            border: none;
            border-radius: 0;
            font-weight: 500;
            color: #777;
            position: relative;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-blue);
            border-radius: 3px 3px 0 0;
        }
        
        .tab-content {
            padding: 25px;
        }
        
        /* Modern Table Styles */
        .appointments-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .appointments-table thead {
            background: linear-gradient(to right, var(--primary-blue), #1a6dca);
            color: white;
        }
        
        .appointments-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 500;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .appointments-table th:last-child {
            border-right: none;
        }
        
        .appointments-table tbody tr {
            background: white;
            transition: all 0.2s;
        }
        
        .appointments-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .appointments-table tbody tr:nth-child(even) {
            background-color: #f9fbfd;
        }
        
        .appointments-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .appointments-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #FFC107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .badge-confirmed {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .badge-completed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--primary-green);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .badge-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .badge-declined {
            background: rgba(197, 48, 48, 0.1);
            color: #c53030;
            border: 1px solid rgba(197, 48, 48, 0.3);
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .btn-outline:hover {
            background: rgba(0, 85, 164, 0.1);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #bd2130;
        }
        
        /* Off-canvas Styles */
        .offcanvas-container {
            position: fixed;
            top: 0;
            right: 0;
            width: 600px;
            height: 100%;
            background: white;
            box-shadow: -5px 0 25px rgba(0,0,0,0.1);
            z-index: 1050;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
            overflow-y: auto;
        }
        
        .offcanvas-container.active {
            transform: translateX(0);
        }
        
        .offcanvas-header {
            padding: 20px;
            background: linear-gradient(to right, var(--primary-blue), #1a6dca);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .offcanvas-body {
            padding: 25px;
        }
        
        .offcanvas-title {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .close-offcanvas {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* Timeline Styles */
        .tracking-section {
            margin: 30px 0;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: var(--primary-blue);
        }
        
        .section-title i {
            margin-right: 10px;
            font-size: 1.3rem;
        }
        
        .tracking-timeline {
            position: relative;
            padding-left: 30px;
            margin: 25px 0;
        }
        
        .timeline-bar {
            position: absolute;
            left: 3px;
            top: 5px;
            bottom: 5px;
            width: 8px;
            background: #e0e0e0;
            border-radius: 3px;
        }
        
        .timeline-progress {
            position: absolute;
            left: 5px;
            top: 5px;
            width: 3px;
            background: var(--primary-green);
            border-radius: 3px;
            transition: height 1s ease;
        }
        
        .timeline-step {
            position: relative;
            margin-bottom: 30px;
            padding-left: 30px;
        }
        
        .step-indicator {
            position: absolute;
            left: -5px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            border: 3px solid white;
        }
        
        .step-indicator.active {
            background: var(--primary-blue);
            box-shadow: 0 0 0 5px rgba(40, 167, 69, 0.2);
        }
        
        .step-indicator.completed {
            background: var(--primary-green);
        }
        
        .step-indicator i {
            color: white;
            font-size: 0.7rem;
        }
        
        .step-content {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
            border-left: 3px solid #e0e0e0;
        }
        
        .step-content.active {
            background: #e8f5e9;
            border-left: 3px solid var(--primary-green);
        }
        
        .step-content.completed {
            background: #f1f8e9;
            border-left: 3px solid var(--primary-green);
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .step-title i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        .step-date {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 5px;
        }
        
        .step-desc {
            font-size: 0.9rem;
            color: #555;
        }
        
        .attachments-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .attachment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            background-color: #e9ecef;
            border-radius: 30px;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .attachment-item:hover {
            background-color: #d1e3ff;
            transform: translateY(-2px);
        }
        
        .attachment-item i {
            margin-right: 8px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-blue);
            color: white;
            padding: 4px 12px;
            font-size: 0.8rem;
            border-radius: 4px;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            box-shadow: 0 1px 2px rgba(0, 85, 164, 0.15);
        }
        
        .btn-primary:hover {
            background: #00448a;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 85, 164, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .btn-outline:hover {
            background: rgba(0, 85, 164, 0.1);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .footer {
            text-align: center;
            padding: 25px;
            color: #777;
            font-size: 0.9rem;
            margin-top: 30px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .empty-state .btn-primary {
            margin-top: 15px;
            padding: 6px 16px;
            font-size: 0.85rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #777;
            margin-bottom: 15px;
        }
        
        /* File Preview Styles */
        .file-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .file-preview-item {
            position: relative;
            width: 120px;
            height: 140px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        .file-preview-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .file-preview-thumb {
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
        }
        
        .file-preview-thumb img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .file-preview-icon {
            font-size: 2.5rem;
        }
        
        .file-preview-info {
            padding: 8px;
            text-align: center;
        }
        
        .file-name {
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #495057;
        }
        
        .file-size {
            font-size: 0.65rem;
            color: #868e96;
        }
        
        /* Preview Modal */
        .preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10510 !important;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .preview-modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .preview-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow: auto;
            position: relative;
            z-index: 10511 !important;
        }
        
        .preview-header {
            padding: 15px 20px;
            background: #f1f3f5;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-body {
            padding: 25px;
            text-align: center;
        }
        
        .preview-iframe {
            width: 100%;
            height: 70vh;
            border: none;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 70vh;
        }
        
        .preview-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
        }
        
        .unsupported-preview {
            padding: 40px;
            text-align: center;
        }
        
        /* Edit Modal Styles */
        .edit-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 3000;
            overflow: auto;
            padding: 20px;
        }
        .hide-modal-force {
            display: none !important;
            z-index: 0 !important;
        }
+       .hide-offcanvas-force {
+           display: none !important;
+           z-index: 0 !important;
+       }
        
        .edit-modal-content {
            background: white;
            border-radius: 15px;
            max-width: 700px;
            margin: 40px auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .edit-modal-header {
            padding: 20px;
            background: var(--primary-blue);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .edit-modal-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #444;
        }
        
        .form-control, .form-select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 85, 164, 0.2);
        }
        
        .existing-attachments {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid var(--primary-blue);
        }
        
        .attachment-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            background: #e9ecef;
            border-radius: 20px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        .attachment-badge i {
            margin-right: 5px;
        }
        
        .attachment-preview {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .attachment-preview:hover {
            color: var(--primary-blue);
            text-decoration: underline;
        }
        
        .edit-modal-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-save {
            background: var(--primary-blue);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
        }
        
        .btn-save:hover {
            background: #00448a;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .attachment-remove {
            margin-left: 8px;
            color: #dc3545;
            cursor: pointer;
        }
        
        .attachment-remove:hover {
            color: #bd2130;
        }
        
        .offcanvas-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .offcanvas-backdrop.active {
            opacity: 1;
            visibility: visible;
        }
        
        @media (max-width: 768px) {
            .header .container {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .user-info {
                justify-content: center;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .nav-tabs .nav-link {
                text-align: center;
            }
            
            .file-preview-container {
                justify-content: center;
            }
            
            .offcanvas-container {
                width: 100%;
            }
        }
        .offcanvas-actions .action-btn.btn-sm {
            padding: 4px 10px;
            font-size: 0.85rem;
            border-radius: 4px;
            gap: 4px;
        }
        /* Add custom styles for better dropdowns */
        .custom-dropdown-btn {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 0.7rem 2.2rem 0.7rem 1.2rem;
            font-size: 1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        .custom-dropdown-btn:focus, .custom-dropdown-btn:hover {
            border-color: #b6d4fe;
            box-shadow: 0 4px 16px rgba(0,123,255,0.08);
            color: #0d6efd;
        }
        .custom-dropdown-chevron {
            font-size: 1.2em;
            margin-left: 0.5em;
            color: #888;
            transition: color 0.2s;
        }
        .custom-dropdown-btn[aria-expanded="true"] .custom-dropdown-chevron {
            color: #0d6efd;
        }
        .custom-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0; /* anchor to the left */
            right: auto; /* unset the right anchor */
            margin-top: 0.2rem;
            min-width: 180px;
            max-width: 100%; /* keep it inside container */
            box-sizing: border-box;
            z-index: 100;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            padding: 0.5rem 0;
            background: #fff;
            border: 1px solid #e3e3e3;
            animation: fadeIn 0.18s;
            overflow-x: hidden; /* prevents horizontal scroll */
        }

        .custom-dropdown-menu .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 0.7rem;
            margin: 0.1rem 0.5rem;
            transition: background 0.15s, color 0.15s;
        }
        .custom-dropdown-menu .dropdown-item.active,
        .custom-dropdown-menu .dropdown-item:active,
        .custom-dropdown-menu .dropdown-item:hover {
            background: #e7f1ff;
        }
        /* Custom Dropdown Styles */
        .custom-dropdown { position: relative; display: inline-block; }
        .custom-dropdown-btn {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 0.7rem 2.2rem 0.7rem 1.2rem;
            font-size: 1rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            min-width: 180px;
            transition: box-shadow 0.2s, border-color 0.2s, color 0.2s;
            outline: none;
        }
        .custom-dropdown-btn:focus, .custom-dropdown-btn.open {
            border-color: #b6d4fe;
            box-shadow: 0 4px 16px rgba(0,123,255,0.08);
            color: #0d6efd;
        }
        .custom-dropdown-chevron {
            font-size: 1.2em;
            margin-left: auto;
            color: #888;
            transition: transform 0.2s, color 0.2s;
        }
        .custom-dropdown-btn.open .custom-dropdown-chevron {
            color: #0d6efd;
            transform: rotate(180deg);
        }
        .custom-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            left: auto;
            top: 100%;
            margin-top: 0.2rem;
            min-width: 180px;
            max-width: 260px;
            box-sizing: border-box;
            z-index: 100;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            padding: 0.5rem 0;
            background: #fff;
            border: 1px solid #e3e3e3;
            animation: fadeIn 0.18s;
        }
        .custom-dropdown.open .custom-dropdown-menu {
            display: block;
        }
        .custom-dropdown-menu .custom-dropdown-item {
            padding: 0.75rem 1.5rem;
            padding-left: 2.2rem; /* more space on the left to account for the checkmark */
            font-size: 1rem;
            border-radius: 0.7rem;
            margin: 0.1rem 0.5rem;
            color: #333;
            background: none;
            border: none;
            width: calc(100% - 1rem); /* respect horizontal margins */
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            position: relative;
            box-sizing: border-box;
        }

        .custom-dropdown-menu .custom-dropdown-item.selected,
        .custom-dropdown-menu .custom-dropdown-item:active,
        .custom-dropdown-menu .custom-dropdown-item:hover {
            background: #e7f1ff;
            color: #0d6efd;
        }
        .custom-dropdown-check {
            color: #0d6efd;
            font-size: 1.1em;
            width: 1rem;
            visibility: hidden;
            display: inline-block;
            flex-shrink: 0;
            position: absolute;
            left: 1.2rem; /* position it independently */
        }

        .custom-dropdown-item.selected .custom-dropdown-check {
            visibility: visible;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Add to your existing styles */
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .alert-danger .bg-white {
            background-color: rgba(255,255,255,0.9) !important;
        }


    </style>
    <!-- Add this in the <head> or before </body> -->
    <script src="shared_appointments.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo-container">
                    <div class="logo">
                       <img src="images/logooo.png" alt="Company Logo" style="width:150px; height:auto; border-radius: 15px;">
                    </div>
                    <div>
                        <h3 class="mb-0">Solano Mayor's Office</h3>
                        <p class="mb-0">Online Appointment System</p>
                    </div>
                </div>
                
                <!-- Profile Section -->
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?= $user_initials ?></div>
                    <div>
                        <div class="text-white fw-bold"><?= htmlspecialchars($username) ?></div>
                        <div class="text-white-50">Resident</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="app-container">
        <div class="mb-4">
            <a href="userSide.php" class="btn btn-outline-primary rounded-pill px-4 py-2 d-inline-flex align-items-center" style="gap:0.5rem;font-weight:500;">
                <i class="bi bi-arrow-left"></i> Back to Set Appointments
            </a>
        </div>

        <?php if ($show_toast): ?>
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
          <div id="successToast" class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
              <div class="toast-body">
                Appointment booked successfully! You will receive updates via Gmail or SMS.
              </div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
          </div>
        </div>
        <script>
          setTimeout(() => {
            var toastEl = document.getElementById('successToast');
            if (toastEl) {
              var toast = new bootstrap.Toast(toastEl);
              toast.show();
            }
          }, 500);
        </script>
        <?php endif; ?>
        <?php if ($show_reschedule_toast): ?>
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
          <div id="rescheduleToast" class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
              <div class="toast-body">
                <i class="bi bi-calendar-check me-2"></i>Appointment rescheduled successfully!
              </div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
          </div>
        </div>
        <script>
          setTimeout(() => {
            var toastEl = document.getElementById('rescheduleToast');
            if (toastEl) {
              var toast = new bootstrap.Toast(toastEl);
              toast.show();
            }
          }, 500);
        </script>
        <?php endif; ?>
        <?php if (isset($_SESSION['update_success'])): ?>
            <div class="position-fixed top-0 end-0 p-3" style="z-index: 2000">
                <div id="updateToast" class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            Appointment updated successfully!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var toastEl = document.getElementById('updateToast');
                    if (toastEl) {
                        var toast = new bootstrap.Toast(toastEl, { delay: 3500 });
                        toast.show();
                    }
                });
            </script>
            <?php unset($_SESSION['update_success']); ?>
        <?php endif; ?>
        
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="d-flex flex-column flex-md-row align-items-center">
                <div class="flex-grow-1 mb-3 mb-md-0">
                    <h2 class="mb-2">Your Appointments</h2>
                    <p class="mb-0">Manage your scheduled appointments and track their progress</p>
                </div>
                <a href="userSide.php#calendar" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i>Book New Appointment
                </a>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="appointment-summary">
            <div class="summary-card pending">
                <div class="summary-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-number"><?= $counts['pending'] ?></div>
                    <div class="summary-title">Pending Requests</div>
                </div>
            </div>
            
            <div class="summary-card upcoming">
                <div class="summary-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-number"><?= $counts['upcoming'] ?></div>
                    <div class="summary-title">Upcoming Appointments</div>
                </div>
            </div>
            
            <div class="summary-card completed">
                <div class="summary-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-number"><?= $counts['completed'] ?></div>
                    <div class="summary-title">Completed</div>
                </div>
            </div>
            
            <div class="summary-card cancelled">
                <div class="summary-icon">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-number"><?= $counts['cancelled'] ?></div>
                    <div class="summary-title">Cancelled</div>
                </div>
            </div>

            <div class="summary-card declined">
                <div class="summary-icon">
                    <i class="bi bi-x-octagon"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-number"><?= $counts['declined'] ?></div>
                    <div class="summary-title">Declined</div>
                </div>
            </div>
        </div>
        
        <!-- Appointment Tabs -->
        <div class="tab-container">
            <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $active_tab === 'upcoming' ? 'active' : '' ?>" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">Upcoming Appointments</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $active_tab === 'pending' ? 'active' : '' ?>" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending Requests</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $active_tab === 'lapsed' ? 'active' : '' ?>" id="lapsed-tab" data-bs-toggle="tab" data-bs-target="#lapsed" type="button" role="tab">Lapsed Pending</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $active_tab === 'history' ? 'active' : '' ?>" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">History</button>
                </li>
            </ul>
            <div class="tab-content" id="appointmentTabsContent">
                <!-- Upcoming Appointments Tab -->
                <div class="tab-pane fade <?= $active_tab === 'upcoming' ? 'show active' : '' ?>" id="upcoming" role="tabpanel">
                    <?php if($counts['upcoming'] > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table">
                                <thead>
                                    <tr>
                                        <th>Reference ID</th>
                                        <th>Service</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Upcoming Appointments: sort by requested (created_at) DESC
                                    $upcomingApps = array_filter($appointments, function($app) { return $app['status'] === 'Confirmed'; });
                                    usort($upcomingApps, function($a, $b) {
                                        return strtotime($b['requested']) - strtotime($a['requested']);
                                    });
                                    foreach ($upcomingApps as $app): ?>
                                            <tr class="appointment-row" 
                                                data-appointment='<?= htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8') ?>'>
                                                <td><?= $app['app_id'] ?></td>
                                                <td><?= $app['service'] ?></td>
                                                <td><?= $app['date'] ?></td>
                                                <td><?= $app['time'] ?></td>
                                                <td><span class="status-badge badge-confirmed"><?= $app['status'] ?></span></td>
                                                <td>
                                                    <button class="action-btn btn-outline view-details-btn">
                                                        <i class="bi bi-eye me-1"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h4>No Upcoming Appointments</h4>
                            <p>You don't have any confirmed appointments scheduled.</p>
                            <a href="userSide.php#calendar" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i>Book New Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pending Requests Tab -->
                <div class="tab-pane fade <?= $active_tab === 'pending' ? 'show active' : '' ?>" id="pending" role="tabpanel">
                    <?php if($counts['pending'] > 0): ?>
                        <div class="d-flex align-items-center mb-4" style="gap: 0.5rem; margin-bottom:2rem!important;"> <!-- mb-4 for more space -->
                            <span class="me-1" style="font-size:1.2rem;color:#6c757d;"><i class="bi bi-sort-down"></i></span>
                            <div class="custom-dropdown" id="pendingSortDropdown">
                                <button type="button" class="custom-dropdown-btn" id="pendingSortBtn" aria-haspopup="listbox" aria-expanded="false">
                                    Sort: <span id="pendingSortLabel">Latest to Oldest</span>
                                    <i class="bi bi-chevron-down custom-dropdown-chevron"></i>
                                </button>
                                <div class="custom-dropdown-menu" role="listbox">
                                    <button class="custom-dropdown-item pending-sort-option selected" type="button" data-value="desc">
                                        <span class="custom-dropdown-check"><i class="bi bi-check2"></i></span>Latest to Oldest
                                    </button>
                                    <button class="custom-dropdown-item pending-sort-option" type="button" data-value="asc">
                                        <span class="custom-dropdown-check"><i class="bi bi-check2"></i></span>Oldest to Latest
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="appointments-table" id="pendingRequestsTable">
                                <thead>
                                    <tr>
                                        <th>Reference ID</th>
                                        <th>Service</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Pending Requests: sort by requested (created_at) DESC
                                    $pendingApps = array_filter($appointments, function($app) {
                                        if ($app['status'] !== 'Pending Approval') return false;
                                        $appointmentDateTime = strtotime($app['date'] . ' ' . $app['time']);
                                        return $appointmentDateTime >= time();
                                    });
                                    usort($pendingApps, function($a, $b) {
                                        return strtotime($b['requested']) - strtotime($a['requested']);
                                    });
                                    foreach ($pendingApps as $app): ?>
                                            <tr class="appointment-row" 
                                                data-appointment='<?= htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8') ?>'
                                                data-requested="<?= strtotime($app['requested']) ?>">
                                                <td><?= $app['app_id'] ?></td>
                                                <td><?= $app['service'] ?></td>
                                                <td><?= $app['date'] ?></td>
                                                <td><?= $app['time'] ?></td>
                                                <td><span class="status-badge badge-pending"><?= $app['status'] ?></span></td>
                                                <td>
                                                    <button class="action-btn btn-outline view-details-btn">
                                                        <i class="bi bi-eye me-1"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- What happens next information -->
                        <div class="mt-4 p-4 bg-light rounded-3 border-start border-4 border-warning">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle-fill text-warning me-3" style="font-size: 1.5rem; margin-top: 2px;"></i>
                                <div>
                                    <h6 class="mb-3 text-dark fw-semibold">What happens next?</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="bi bi-clock me-2 text-muted"></i>
                                            <span class="text-muted">Our staff will review your appointment request within <strong>24-48 hours</strong></span>
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-envelope me-2 text-muted"></i>
                                            <span class="text-muted">You will receive an <strong>email notification</strong> once your appointment is approved or if any changes are needed</span>
                                        </li>
                                        <li class="mb-0">
                                            <i class="bi bi-check-circle me-2 text-muted"></i>
                                            <span class="text-muted">Please check your <strong>email regularly</strong> for updates</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-hourglass-split"></i>
                            <h4>No Pending Requests</h4>
                            <p>You don't have any pending appointment requests.</p>
                            <p class="text-muted mt-2">
                                <small>If you recently created an appointment, it should appear here shortly. 
                                If you believe this is an error, please contact support.</small>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Lapsed Pending Tab -->
                <div class="tab-pane fade <?= $active_tab === 'lapsed' ? 'show active' : '' ?>" id="lapsed" role="tabpanel">
                    <?php
                    // Lapsed Pending: Pending Approval with date/time in the past
                    $lapsedApps = array_filter($appointments, function($app) {
                        if ($app['status'] !== 'Pending Approval') return false;
                        $appointmentDateTime = strtotime($app['date'] . ' ' . $app['time']);
                        return $appointmentDateTime < time();
                    });
                    if (count($lapsedApps) > 0): ?>
                        <div class="table-responsive">
                            <table class="appointments-table">
                                <thead>
                                    <tr>
                                        <th>Reference ID</th>
                                        <th>Service</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lapsedApps as $app): ?>
                                        <tr class="appointment-row" 
                                            data-appointment='<?= htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8') ?>'>
                                            <td><?= $app['app_id'] ?></td>
                                            <td><?= $app['service'] ?></td>
                                            <td><?= $app['date'] ?></td>
                                            <td><?= $app['time'] ?></td>
                                            <td><span class="status-badge badge-pending">Lapsed Pending</span></td>
                                            <td>
                                                <button class="action-btn btn-outline view-details-btn">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-hourglass-bottom"></i>
                            <h4>No Lapsed Pending Requests</h4>
                            <p>You have no pending requests that have lapsed their scheduled date and time.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- History Tab -->
                <div class="tab-pane fade <?= $active_tab === 'history' ? 'show active' : '' ?>" id="history" role="tabpanel">
                    <div class="d-flex align-items-center mb-4" style="gap: 0.5rem; margin-bottom:2rem!important;">
                        <span class="me-1" style="font-size:1.2rem;color:#6c757d;"><i class="bi bi-funnel"></i></span>
                        <div class="custom-dropdown" id="historyFilterDropdown">
                            <button type="button" class="custom-dropdown-btn" id="historyFilterBtn" aria-haspopup="listbox" aria-expanded="false">
                                Show: <span id="historyFilterLabel">All</span>
                                <i class="bi bi-chevron-down custom-dropdown-chevron"></i>
                            </button>
                            <div class="custom-dropdown-menu" role="listbox">
                                <button class="custom-dropdown-item history-filter-option selected" type="button" data-value="all">
                                    <span class="custom-dropdown-check"><i class="bi bi-check2"></i></span>All
                                </button>
                                <button class="custom-dropdown-item history-filter-option" type="button" data-value="Completed">
                                    <span class="custom-dropdown-check"><i class="bi bi-check2"></i></span>Completed
                                </button>
                                <button class="custom-dropdown-item history-filter-option" type="button" data-value="Cancelled">
                                    <span class="custom-dropdown-check"><i class="bi bi-check2"></i></span>Cancelled
                                </button>
                                <button class="custom-dropdown-item history-filter-option" type="button" data-value="Declined">
                                    <span class="custom-dropdown-check"><i class="bi bi-check2"></i></span>Declined
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php
                    $historyApps = array_filter($appointments, function($app) {
                        return $app['status'] === 'Completed' || $app['status'] === 'Cancelled' || $app['status'] === 'Declined';
                    });
                    if (count($historyApps) > 0): ?>
                        <div class="table-responsive mb-4 history-table" data-status="all">
                            <table class="appointments-table" id="historyAppointmentsTable">
                                <thead id="historyTableHead">
                                    <tr>
                                        <th>Reference ID</th>
                                        <th>Service</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historyApps as $app): ?>
                                        <tr class="appointment-row" data-appointment='<?= htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8') ?>' data-status="<?= $app['status'] ?>">
                                            <td><?= $app['app_id'] ?></td>
                                            <td><?= $app['service'] ?></td>
                                            <td><?= $app['date'] ?></td>
                                            <td><?= $app['time'] ?></td>
                                            <td>
                                                <?php if ($app['status'] === 'Completed'): ?>
                                                    <span class="status-badge badge-completed">Completed</span>
                                                <?php elseif ($app['status'] === 'Cancelled'): ?>
                                                    <span class="status-badge badge-cancelled">Cancelled</span>
                                                <?php elseif ($app['status'] === 'Declined'): ?>
                                                    <span class="status-badge badge-declined">Declined</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="action-btn btn-outline view-details-btn">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-folder-x"></i>
                            <h4>No Appointment History</h4>
                            <p>You don't have any completed, cancelled, or declined appointments yet.</p>
                        </div>
                    <?php endif; ?>
                    <div id="historyEmptyState" class="empty-state" style="display:none;">
                        <i class="bi bi-folder-x"></i>
                        <h4>No matching appointment history</h4>
                        <p>There are no appointments matching your selected filter.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?= date('Y') ?> Solano Mayor's Office Appointment System. All Rights Reserved. | v2.0</p>
            <p class="mt-2 text-muted">Logged in as: <?= htmlspecialchars($username) ?> (ID: <?= $user_id ? $user_id : 'Guest' ?>)</p>
        </div>
    </div>
    
    <!-- Off-Canvas Panel -->
    <div class="offcanvas-backdrop" id="offcanvasBackdrop"></div>
    <div class="offcanvas-container" id="appointmentDetailsCanvas">
        <div class="offcanvas-header">
            <h3 class="offcanvas-title">Appointment Details</h3>
            <button class="close-offcanvas" id="closeOffcanvas">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="offcanvas-body" id="offcanvasBody">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
    
    <!-- Preview Modal -->
    <div class="preview-modal" id="previewModal">
        <div class="preview-content">
            <div class="preview-header">
                <h5 id="previewFileName">Attachment Preview</h5>
                <button class="btn btn-sm btn-outline-danger" id="closePreview">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="preview-body" id="previewBody">
                <div class="unsupported-preview">
                    <i class="bi bi-file-earmark-text display-1 text-muted"></i>
                    <h5>Preview not available</h5>
                    <p>This file type cannot be previewed. Please download the file to view it.</p>
                </div>
            </div>
            <div class="preview-actions" id="previewActions">
                <a href="#" class="btn btn-primary" id="downloadFile" download>
                    <i class="bi bi-download"></i> Download
                </a>
                <button class="btn btn-outline-secondary" id="closePreviewBtn">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Appointment Modal -->
    <div class="edit-modal" id="editAppointmentModal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h4 class="mb-0">Edit Appointment Request</h4>
                <button class="btn btn-sm btn-light" id="closeEditModal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="edit-modal-body">
                <form id="editAppointmentForm" method="post" action="update_appointment.php" enctype="multipart/form-data">
                    <input type="hidden" name="appointment_id" id="editAppointmentId">
                    
                    <div class="form-group">
                        <label for="editPurpose" class="form-label">Purpose of Appointment</label>
                        <textarea class="form-control" id="editPurpose" name="purpose" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="editAttendees" class="form-label">Attendees</label>
                        <input type="text" class="form-control" id="editAttendees" name="attendees" 
                               placeholder="Enter names of attendees (comma separated)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Existing Attachments</label>
                        <div class="existing-attachments" id="existingAttachmentsList">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="newAttachments" class="form-label">Add New Attachments</label>
                        <input type="file" class="form-control" id="newAttachments" name="attachments[]" multiple>
                        <small class="form-text text-muted">You can add multiple files (PDF, JPG, PNG, DOC, DOCX)</small>
                    </div>
                </form>
            </div>
            <div class="edit-modal-footer">
                <button class="btn btn-cancel" id="cancelEdit">Cancel</button>
                <button class="btn btn-save" id="saveChanges">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-labelledby="cancelConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title d-flex align-items-center" id="cancelConfirmModalLabel">
              <i class="bi bi-exclamation-octagon me-2"></i> Cancel Appointment
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex align-items-center">
              <i class="bi bi-x-circle display-5 text-danger me-3"></i>
              <div>
                <div>Are you sure you want to <span class="fw-bold text-danger">cancel</span> this appointment?</div>
                <small class="text-muted">This action cannot be undone. You will not be able to attend this appointment if cancelled.</small>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep</button>
            <button type="button" class="btn btn-danger" id="confirmCancelBtn">
              <i class="bi bi-x-circle me-1"></i> Yes, Cancel
            </button>
          </div>
        </div>
      </div>
    </div>


    <!-- Remove Attachment Confirmation Modal -->
    <div class="modal fade" id="removeAttachmentModal" tabindex="-1" aria-labelledby="removeAttachmentModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="removeAttachmentModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Remove Attachment</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex align-items-center">
              <i class="bi bi-file-earmark-x display-5 text-danger me-3"></i>
              <div>
                <div>Are you sure you want to remove <span id="removeAttachmentFileName" class="fw-bold"></span>?</div>
                <small class="text-muted">This action cannot be undone.</small>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmRemoveAttachmentBtn">Remove</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast for cancellation -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
      <div id="cancelToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            Appointment cancelled successfully.
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>

    <!-- Toast for attachment removal -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
      <div id="removeAttachmentToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            Attachment removed successfully.
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const offcanvas = document.getElementById('appointmentDetailsCanvas');
            const offcanvasBody = document.getElementById('offcanvasBody');
            const offcanvasBackdrop = document.getElementById('offcanvasBackdrop');
            const closeOffcanvas = document.getElementById('closeOffcanvas');
            const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
            const appointmentRows = document.querySelectorAll('.appointment-row');
            
            // Function to open off-canvas with appointment details
           function openAppointmentDetails(appointment) {
    const offcanvasBody = document.getElementById('offcanvasBody');
    
    // Build HTML for decline reason
    const declineHTML = (appointment.status === 'Declined' && appointment.decline_reason)
        ? `
           <div class="alert alert-danger mb-4 p-3">
            <div class="d-flex align-items-start">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3 mt-1"></i>
                <div>
                    <h6 class="mb-2 fw-bold">Appointment Declined</h6>
                    <div class="bg-white p-3 rounded border">
                        <p class="mb-0 text-dark"><strong>Reason:</strong> ${appointment.decline_reason}</p>
                    </div>
                </div>
            </div>
        </div>
        `
        : '';

    offcanvasBody.innerHTML = `
        <div class="offcanvas-details-wrapper">
            ${declineHTML}
            <div class="offcanvas-section mb-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <span class="status-badge ${
                            appointment.status === 'Pending Approval' ? 'badge-pending' :
                            appointment.status === 'Confirmed' ? 'badge-confirmed' :
                            appointment.status === 'Completed' ? 'badge-completed' :
                            appointment.status === 'Cancelled' ? 'badge-cancelled' :
                            appointment.status === 'Declined' ? 'badge-declined' : 'badge-cancelled'
                        }">${appointment.status}</span>
                    </div>
                    <h3 class="offcanvas-title mb-0">${appointment.title}</h3>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="info-label">Date</div>
                        <div class="info-value">${appointment.date}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="info-label">Time</div>
                        <div class="info-value">${appointment.time}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="info-label">Service</div>
                        <div class="info-value">${appointment.service}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="info-label">Reference ID</div>
                        <div class="info-value">${appointment.app_id}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="info-label">Department</div>
                        <div class="info-value">${appointment.department}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="info-label">Attendees</div>
                        <div class="info-value">${appointment.attendees}</div>
                    </div>
                    <div class="col-12">
                        <div class="info-label">Purpose</div>
                        <div class="info-value">${appointment.purpose}</div>
                    </div>
                </div>
            </div>
            ${appointment.attachments.length > 0 ? `
            <div class="offcanvas-section mb-4">
                <div class="section-title mb-2">
                    <i class="bi bi-paperclip"></i>
                    <span class="ms-2">Attachments</span>
                </div>
                <div class="file-preview-container">
                    ${appointment.attachments.map(attachment => `
                        <div class="file-preview-item" 
                             data-file="${attachment.name}"
                             data-type="${attachment.type}">
                            <div class="file-preview-thumb">
                                <i class="bi ${attachment.icon} file-preview-icon ${attachment.color}"></i>
                            </div>
                            <div class="file-preview-info">
                                <div class="file-name">${attachment.name}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}
            <div class="offcanvas-actions d-flex gap-1 mb-3">
                ${appointment.status === 'Pending Approval' ? `
                    <button class="action-btn btn-outline btn-sm edit-request-btn" 
                        data-appointment-id="${appointment.id}"
                        data-purpose="${appointment.purpose}"
                        data-attendees="${appointment.attendees}"
                        data-attachments='${JSON.stringify(appointment.attachments)}'>
                        <i class="bi bi-pencil me-1"></i> Edit Request
                    </button>
                ` : ''}
                ${(appointment.status === 'Pending Approval' || appointment.status === 'Confirmed') ? `
                    <button class="action-btn btn-danger btn-sm cancel-appointment-btn" 
                        data-app-id="${appointment.id}">
                        <i class="bi bi-x-circle me-1"></i> 
                        ${appointment.status === 'Pending Approval' ? 'Cancel Request' : 'Cancel Appointment'}
                    </button>
                ` : ''}
                ${appointment.status === 'Confirmed' ? `
                    <a href="reschedule.php?id=${appointment.id}" class="action-btn btn-outline btn-sm reschedule-appointment-btn">
                        <i class="bi bi-calendar-event me-1"></i> Reschedule
                    </a>
                ` : ''}
                <button class="action-btn btn-primary btn-sm">
                    <i class="bi bi-printer me-1"></i> Print Details
                </button>
            </div>
            <div class="offcanvas-section mb-4">
                <div class="section-title mb-2">
                    <i class="bi bi-geo-alt"></i>
                    <span class="ms-2">Appointment Tracking</span>
                </div>
                <div class="tracking-timeline">
                    <div class="timeline-bar"></div>
                    <div class="timeline-progress" style="height: ${appointment.progress}%;"></div>
                    ${appointment.timeline.map(step => `
                        <div class="timeline-step">
                            <div class="step-indicator ${step.status}">
                                ${step.status === 'completed' ?
                                    '<i class="bi bi-check"></i>' :
                                    step.status === 'active' ?
                                    '<i class="bi bi-record-circle"></i>' :
                                    '<i class="bi bi-circle"></i>'}
                            </div>
                            <div class="step-content ${step.status}">
                                <div class="step-title">
                                    <i class="bi bi-${
                                        step.step === 'Appointment Requested' ? 'calendar-plus' :
                                        (step.step === 'Appointment Confirmed' ? 'check-circle' :
                                        (step.step === 'Scheduled' ? 'clock' :
                                        (step.step === 'Completed' ? 'check2-all' : 'x-circle')))
                                    }"></i>
                                    ${step.step}
                                </div>
                                ${step.date ? `<div class="step-date">${step.date}</div>` : ''}
                                <div class="step-desc">
                                    ${
                                        step.step === 'Appointment Requested' ? 
                                        'Your appointment request has been submitted' : 
                                        step.step === 'Appointment Confirmed' ? 
                                        'Your appointment has been confirmed by our staff' : 
                                        step.step === 'Scheduled' ? 
                                        'Your appointment is scheduled for the selected time' : 
                                        step.step === 'Completed' ? 
                                        'Appointment was successfully completed' : 
                                        step.step === 'Cancelled' ? 
                                        'Appointment was cancelled' : 
                                        step.step === 'Declined' ? 
                                        'Appointment was declined by admin staff' : 
                                        (appointment.status === 'Declined' && step.step === 'Pending Approval') ? 
                                        'Your appointment request is under review by admin staff' : 
                                        (appointment.status === 'Declined' && step.step === 'Confirmation') ? 
                                        'Your appointment request was declined by admin staff' : 
                                        (appointment.status === 'Pending Approval' && step.step === 'Pending Approval') ? 
                                        'Your appointment request is under review by admin staff' : 
                                        (appointment.status === 'Pending Approval' && step.step === 'Confirmation') ? 
                                        'Awaiting admin approval' : 
                                        'Appointment was cancelled'
                                    }
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
                
                // Add event listeners to new file preview items
                document.querySelectorAll('#offcanvasBody .file-preview-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const fileName = this.getAttribute('data-file');
                        const fileType = this.getAttribute('data-type');
                        showFilePreview(fileName, fileType);
                    });
                });
                
                // Add event listeners to action buttons in off-canvas
                const editBtn = offcanvasBody.querySelector('.edit-request-btn');
                if (editBtn) {
                    editBtn.addEventListener('click', function() {
                        const appointmentId = this.getAttribute('data-appointment-id');
                        const purpose = this.getAttribute('data-purpose');
                        const attendees = this.getAttribute('data-attendees');
                        const attachments = JSON.parse(this.getAttribute('data-attachments'));
                        
                        openEditModal(appointmentId, purpose, attendees, attachments);
                    });
                }
                
                // Cancel button in off-canvas
               
                
                // Open off-canvas
                offcanvas.classList.add('active');
                offcanvasBackdrop.classList.add('active');
            }
            
            // Open off-canvas when clicking on row or view button
            function handleAppointmentRowClick(row) {
                const appointmentData = JSON.parse(row.getAttribute('data-appointment'));
                openAppointmentDetails(appointmentData);
            }
            
            // Add event listeners to appointment rows
            appointmentRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicked on action button
                    if (!e.target.closest('.action-btn')) {
                        handleAppointmentRowClick(this);
                    }
                });
            });
            
            // Add event listeners to view buttons
            viewDetailsBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const row = this.closest('.appointment-row');
                    handleAppointmentRowClick(row);
                });
            });
            
            // Close off-canvas
            function closeOffcanvasPanel() {
                offcanvas.classList.remove('active');
                offcanvasBackdrop.classList.remove('active');
            }
            
            closeOffcanvas.addEventListener('click', closeOffcanvasPanel);
            offcanvasBackdrop.addEventListener('click', closeOffcanvasPanel);
            
            // File preview functionality
            const previewModal = document.getElementById('previewModal');
            const previewBody = document.getElementById('previewBody');
            const previewFileName = document.getElementById('previewFileName');
            const downloadFile = document.getElementById('downloadFile');
            
            // Function to show file preview
            function showFilePreview(fileName, fileType) {
                const fileUrl = `uploads/${fileName}`;
                
                previewFileName.textContent = fileName;
                downloadFile.setAttribute('href', fileUrl);
                downloadFile.setAttribute('download', fileName);
                
                // Clear previous content
                previewBody.innerHTML = '';
                
                // Handle different file types
                const imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
                const pdfTypes = ['pdf'];
                
                if (imageTypes.includes(fileType.toLowerCase())) {
                    const img = document.createElement('img');
                    img.src = fileUrl;
                    img.className = 'preview-image';
                    img.alt = fileName;
                    previewBody.appendChild(img);
                } 
                else if (pdfTypes.includes(fileType.toLowerCase())) {
                    const iframe = document.createElement('iframe');
                    iframe.src = fileUrl;
                    iframe.className = 'preview-iframe';
                    previewBody.appendChild(iframe);
                }
                else {
                    const unsupported = document.createElement('div');
                    unsupported.className = 'unsupported-preview';
                    unsupported.innerHTML = `
                        <i class="bi bi-file-earmark-text display-1 text-muted"></i>
                        <h5>Preview not available</h5>
                        <p>This file type cannot be previewed. Please download the file to view it.</p>
                    `;
                    previewBody.appendChild(unsupported);
                }
                
                previewModal.classList.add('active');
            }
            
            // Close modal buttons
            document.getElementById('closePreview').addEventListener('click', function() {
                previewModal.classList.remove('active');
            });
            
            document.getElementById('closePreviewBtn').addEventListener('click', function() {
                previewModal.classList.remove('active');
            });
            
            // Close modal when clicking outside content
            previewModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    previewModal.classList.remove('active');
                }
            });
            
            // Edit Request functionality
            const editModal = document.getElementById('editAppointmentModal');
            const editBtns = document.querySelectorAll('.edit-request-btn');
            const closeEditModalBtn = document.getElementById('closeEditModal');
            const cancelEditBtn = document.getElementById('cancelEdit');
            const saveChangesBtn = document.getElementById('saveChanges');
            
            // Open edit modal
            function openEditModal(appointmentId, purpose, attendees, attachments) {
                // Populate form
                document.getElementById('editAppointmentId').value = appointmentId;
                document.getElementById('editPurpose').value = purpose;
                document.getElementById('editAttendees').value = attendees;
                
                // Populate attachments list
                const attachmentsList = document.getElementById('existingAttachmentsList');
                attachmentsList.innerHTML = '';
                
                if (attachments.length > 0) {
                    attachments.forEach(attachment => {
                        const badge = document.createElement('div');
                        badge.className = 'attachment-badge';
                        badge.innerHTML = `
                            <span class="attachment-preview" 
                                  data-file="${attachment.name}" 
                                  data-type="${attachment.type}">
                                <i class="bi ${attachment.icon} ${attachment.color}"></i>
                                ${attachment.name}
                            </span>
                            <span class="attachment-remove" data-file="${attachment.name}">
                                <i class="bi bi-x"></i>
                            </span>
                        `;
                        attachmentsList.appendChild(badge);
                    });
                } else {
                    attachmentsList.innerHTML = '<p class="text-muted">No attachments uploaded</p>';
                }
                
                // Add click event for attachment preview in edit modal
                document.querySelectorAll('.attachment-preview').forEach(item => {
                    item.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const fileName = this.getAttribute('data-file');
                        const fileType = this.getAttribute('data-type');
                        showFilePreview(fileName, fileType);
                    });
                });
                
                // Show modal
                editModal.style.display = 'block';
            }
            
            editBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const appointmentId = this.getAttribute('data-appointment-id');
                    const purpose = this.getAttribute('data-purpose');
                    const attendees = this.getAttribute('data-attendees');
                    const attachments = JSON.parse(this.getAttribute('data-attachments'));
                    
                    openEditModal(appointmentId, purpose, attendees, attachments);
                });
            });
            
            // Close edit modal
            function closeEditModal() {
                editModal.style.display = 'none';
            }
            
            closeEditModalBtn.addEventListener('click', closeEditModal);
            cancelEditBtn.addEventListener('click', closeEditModal);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === editModal) {
                    closeEditModal();
                }
            });
            
          
            
            // Save changes
            saveChangesBtn.addEventListener('click', function() {
                const form = document.getElementById('editAppointmentForm');
                saveChangesBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
                saveChangesBtn.disabled = true;
                form.submit();
            });
            
            // Cancel appointment buttons in table
            const cancelButtons = document.querySelectorAll('.cancel-appointment-btn, .cancel-request-btn');
            cancelButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const row = this.closest('tr');
                    const title = row.querySelector('td:nth-child(2)').textContent;
                    const appId = this.getAttribute('data-app-id');
                    
                    if(confirm(`Are you sure you want to cancel "${title}"?`)) {
                        // Show loading state
                        row.style.opacity = '0.6';
                        row.querySelectorAll('button').forEach(btn => btn.disabled = true);
                        
                        // Simulate processing delay
                        setTimeout(() => {
                            alert('Appointment cancelled successfully.');
                            row.remove();
                        }, 1500);
                    }
                });
            });

            // Activate Pending tab if ?tab=pending in URL
            if (window.location.search.includes('tab=pending')) {
                var pendingTab = document.getElementById('pending-tab');
                if (pendingTab) {
                    pendingTab.click();
                }
            }

            // --- Custom Dropdown Logic (Reusable) ---
            function setupCustomDropdown(dropdownId, btnId, optionClass, labelId, callback) {
                const dropdown = document.getElementById(dropdownId);
                const btn = document.getElementById(btnId);
                const label = document.getElementById(labelId);
                const options = dropdown.querySelectorAll('.' + optionClass);
                // Toggle menu
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const expanded = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', !expanded);
                    btn.classList.toggle('open');
                    dropdown.classList.toggle('open');
                });
                // Option select
                options.forEach(function(option) {
                    option.addEventListener('click', function(e) {
                        e.preventDefault();
                        options.forEach(opt => opt.classList.remove('selected'));
                        this.classList.add('selected');
                        label.textContent = this.textContent.trim();
                        btn.setAttribute('aria-expanded', 'false');
                        btn.classList.remove('open');
                        dropdown.classList.remove('open');
                        if (callback) callback(this.getAttribute('data-value'), this.textContent.trim());
                    });
                });
                // Close on outside click
                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target)) {
                        btn.setAttribute('aria-expanded', 'false');
                        btn.classList.remove('open');
                        dropdown.classList.remove('open');
                    }
                });
            }
            // --- Pending Requests Sort Dropdown ---
            setupCustomDropdown(
                'pendingSortDropdown',
                'pendingSortBtn',
                'pending-sort-option',
                'pendingSortLabel',
                function(value) {
                    // Sort rows
                    const pendingTable = document.getElementById('pendingRequestsTable');
                    if (!pendingTable) return;
                    const tbody = pendingTable.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.sort(function(a, b) {
                        const aTime = parseInt(a.getAttribute('data-requested'));
                        const bTime = parseInt(b.getAttribute('data-requested'));
                        return value === 'asc' ? aTime - bTime : bTime - aTime;
                    });
                    rows.forEach(row => tbody.appendChild(row));
                }
            );
            // --- History Tab Filter Dropdown ---
            setupCustomDropdown(
                'historyFilterDropdown',
                'historyFilterBtn',
                'history-filter-option',
                'historyFilterLabel',
                function(value) {
                    const filterValue = value.trim().toLowerCase();
                    document.querySelectorAll('#historyAppointmentsTable tbody tr').forEach(function(row) {
                        const rowStatus = (row.getAttribute('data-status') || '').trim().toLowerCase();
                        if (filterValue === 'all' || rowStatus === filterValue) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    updateHistoryEmptyState();
                }
            );

            let cancelAppId = null; // Store the appointment ID to cancel

            // Listen for cancel button in off-canvas and table
            document.addEventListener('click', function(e) {
                const cancelBtn = e.target.closest('.cancel-appointment-btn, .cancel-request-btn');
                if (cancelBtn) {
                    e.preventDefault();
                    cancelAppId = cancelBtn.getAttribute('data-app-id');
                    // Show the confirmation modal
                    var cancelModal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));
                    cancelModal.show();
                }
            });

            // Handle confirmation in modal
            document.getElementById('confirmCancelBtn').addEventListener('click', function() {
                if (!cancelAppId) return;
                // Hide modal
                var cancelModalEl = document.getElementById('cancelConfirmModal');
                var cancelModal = bootstrap.Modal.getInstance(cancelModalEl);
                cancelModal.hide();

                // Send cancellation request
                fetch('cancel_appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'appointment_id=' + encodeURIComponent(cancelAppId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show toast
                        var toastEl = document.getElementById('cancelToast');
                        var toast = new bootstrap.Toast(toastEl, { delay: 3500 });
                        toast.show();
                        // Optionally, remove the row or reload after a delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        alert('Failed to cancel appointment: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch((err) => {
                    alert('Error cancelling appointment.');
                });
            });

            // Remove attachment handler with modal confirmation
            let fileToRemove = null;
            let badgeToRemove = null;
            document.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.attachment-remove');
                if (removeBtn) {
                    e.preventDefault();
                    fileToRemove = removeBtn.getAttribute('data-file');
                    badgeToRemove = removeBtn.closest('.attachment-badge');
                    document.getElementById('removeAttachmentFileName').textContent = fileToRemove;
                    // Forcibly hide the edit modal and any modal backdrop
                    document.getElementById('editAppointmentModal').classList.add('hide-modal-force');
                    let backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.classList.add('hide-modal-force');
                    var removeModal = new bootstrap.Modal(document.getElementById('removeAttachmentModal'));
                    removeModal.show();
                }
            });
            // When the remove modal is closed, re-show the edit modal and its backdrop
            document.getElementById('removeAttachmentModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('editAppointmentModal').classList.remove('hide-modal-force');
                let backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.classList.remove('hide-modal-force');
            });
            // Handle the Remove button in the confirmation modal
            document.getElementById('confirmRemoveAttachmentBtn').addEventListener('click', function() {
                if (!fileToRemove || !badgeToRemove) return;
                // Create hidden input to mark file for removal
                const removalInput = document.createElement('input');
                removalInput.type = 'hidden';
                removalInput.name = 'remove_attachments[]';
                removalInput.value = fileToRemove;
                document.getElementById('editAppointmentForm').appendChild(removalInput);
                // Remove from UI
                badgeToRemove.remove();

                // If no more attachments, show the message
                const attachmentsList = document.getElementById('existingAttachmentsList');
                if (!attachmentsList.querySelector('.attachment-badge')) {
                    attachmentsList.innerHTML = '<p class="text-muted">No attachments uploaded</p>';
                }

                // Show toast
                var toastEl = document.getElementById('removeAttachmentToast');
                var toast = new bootstrap.Toast(toastEl, { delay: 2000 });
                toast.show();

                // Hide modal
                var removeModalEl = document.getElementById('removeAttachmentModal');
                var removeModal = bootstrap.Modal.getInstance(removeModalEl);
                removeModal.hide();
                // Reset
                fileToRemove = null;
                badgeToRemove = null;
            });

            // Reschedule button now links to dedicated reschedule page
            // No JavaScript needed as it's a direct link
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Activate the correct tab based on URL parameter
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab');
      if (tabParam) {
        const validTabs = ['upcoming', 'pending', 'lapsed', 'history'];
        if (validTabs.includes(tabParam)) {
          const targetTab = document.querySelector(`[data-bs-target="#${tabParam}"]`);
          if (targetTab) {
            targetTab.click();
          }
        }
      }
      
      // Auto-open appointment details if show_appointment parameter is present
      const showAppointmentId = urlParams.get('show_appointment');
      if (showAppointmentId) {
        // Find the appointment row with the matching ID
        const appointmentRows = document.querySelectorAll('.appointment-row');
        let targetRow = null;
        
        appointmentRows.forEach(row => {
          const appointmentData = JSON.parse(row.getAttribute('data-appointment'));
          if (appointmentData.id == showAppointmentId) {
            targetRow = row;
          }
        });
        
        if (targetRow) {
          // Trigger the view details button click
          const viewButton = targetRow.querySelector('.view-details-btn');
          if (viewButton) {
            setTimeout(() => {
              viewButton.click();
            }, 500); // Small delay to ensure tab switching is complete
          }
        }
      }
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // When the cancel modal is closed, re-show the off-canvas and its backdrop
      document.getElementById('cancelConfirmModal').addEventListener('hidden.bs.modal', function () {
        offcanvas.classList.remove('hide-offcanvas-force');
        offcanvasBackdrop.classList.remove('hide-offcanvas-force');
      });
    });
    </script>
    <script>
    function updateHistoryEmptyState() {
        const table = document.getElementById('historyAppointmentsTable');
        const emptyState = document.getElementById('historyEmptyState');
        const thead = document.getElementById('historyTableHead');
        if (!table || !emptyState || !thead) return;
        const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
        if (visibleRows.length === 0) {
            emptyState.style.display = '';
            thead.style.display = 'none';
        } else {
            emptyState.style.display = 'none';
            thead.style.display = '';
        }
    }

    // Call this function at the end of your history filter callback:
    setupCustomDropdown(
        'historyFilterDropdown',
        'historyFilterBtn',
        'history-filter-option',
        'historyFilterLabel',
        function(value) {
            // ... your existing filter logic ...
            document.querySelectorAll('#historyAppointmentsTable tbody tr').forEach(function(row) {
                if (value === 'all' || row.getAttribute('data-status') === value) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            updateHistoryEmptyState();
        }
    );
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var offcanvas = document.getElementById('appointmentDetailsCanvas');
        var offcanvasBackdrop = document.getElementById('offcanvasBackdrop');
        var cancelModal = document.getElementById('cancelConfirmModal');
        if (cancelModal) {
            cancelModal.addEventListener('hidden.bs.modal', function () {
                offcanvas.classList.remove('hide-offcanvas-force');
                offcanvasBackdrop.classList.remove('hide-offcanvas-force');
            });
        }
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('cancelConfirmModal').addEventListener('hidden.bs.modal', function () {
            document.querySelectorAll('.hide-offcanvas-force').forEach(function(el) {
                el.classList.remove('hide-offcanvas-force');
            });
        });
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var cancelModal = document.getElementById('cancelConfirmModal');
        var offcanvas = document.getElementById('appointmentDetailsCanvas');
        var offcanvasBackdrop = document.getElementById('offcanvasBackdrop');
        if (cancelModal) {
            cancelModal.addEventListener('hidden.bs.modal', function () {
                // Remove the forced hiding class from all elements
                document.querySelectorAll('.hide-offcanvas-force').forEach(function(el) {
                    el.classList.remove('hide-offcanvas-force');
                });
                // Explicitly show the offcanvas and backdrop if they exist
                if (offcanvas) {
                    offcanvas.classList.add('active');
                    offcanvas.style.display = '';
                    offcanvas.style.visibility = '';
                }
                if (offcanvasBackdrop) {
                    offcanvasBackdrop.classList.add('active');
                    offcanvasBackdrop.style.display = '';
                    offcanvasBackdrop.style.visibility = '';
                }
            });
        }
    });


    </script>
</body>
</html>