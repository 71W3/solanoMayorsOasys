<?php
session_start();

// Include activity logger
include "activity_logger.php";

// Log logout activity before destroying session
if (isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'];
    $user_name = $_SESSION['admin_username'] ?? $_SESSION['user_full_name'] ?? 'Unknown User';
    $user_role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'admin';
    
    // Get database connection
    include "connect.php";
    
    // Log the logout
    logLogout($con, $user_id, $user_name, $user_role);
}

// Unset all admin session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_role']);
unset($_SESSION['user_id']);
unset($_SESSION['user_full_name']);
unset($_SESSION['role']);

// Optionally, destroy the session
session_destroy();
header('Location: ../user/landingPage.php');
exit(); 