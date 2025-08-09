<?php
// Google OAuth Configuration
// Replace these with your actual credentials from Google Cloud Console

// Option 1: Direct values (for testing)
define('GOOGLE_CLIENT_ID', 'your_actual_google_client_id_here'); // Replace with your actual Google Client ID
define('GOOGLE_CLIENT_SECRET', 'your_actual_google_client_secret_here'); // Replace with your actual Google Client Secret
define('GOOGLE_REDIRECT_URI', 'http://localhost/barangay/google_oauth_handler.php');

// Option 2: Environment variables (recommended for production)
// define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
// define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
// define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? '');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'my_auth_db');
?> 