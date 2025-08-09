<?php
session_start();
// Unset all admin session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_role']);
// Optionally, destroy the session
session_destroy();
header('Location: ../user/landingPage.php');
exit(); 