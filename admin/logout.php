<?php
// Start the session
session_start();

// Destroy all session data
session_unset();  // Remove all session variables
session_destroy(); // Destroy the session

// Redirect to landing page
header("Location: ../user/landingPage.php");
exit();
?>
