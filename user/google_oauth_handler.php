<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';

// Google OAuth Configuration
$client_id = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$redirect_uri = GOOGLE_REDIRECT_URI;

// Database configuration
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASS;
$dbname = DB_NAME;

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Google OAuth callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Get user info from Google
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_data['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_data = json_decode($user_response, true);
        
        if ($user_data) {
            $google_email = $user_data['email'];
            $google_name = $user_data['name'];
            $google_picture = $user_data['picture'];
            
            // Check if user exists in database
            $check_sql = "SELECT * FROM users WHERE email = ? OR google_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ss", $google_email, $google_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists - login directly
                $user = $result->fetch_assoc();
                
                // Update Google info if needed
                $update_sql = "UPDATE users SET google_id = ?, profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ssi", $google_email, $google_picture, $user['id']);
                $stmt->execute();
                
                // Set session and redirect
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_success'] = true;
                $_SESSION['user_full_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: adminDashboard.php");
                } elseif ($user['role'] === 'mayor') {
                    header("Location: mayorDashboard.php");
                } else {
                    header("Location: userSide.php");
                }
                exit();
                
            } else {
                // New user - store Google info and redirect to profile completion
                $_SESSION['google_user_data'] = [
                    'email' => $google_email,
                    'name' => $google_name,
                    'picture' => $google_picture
                ];
                
                header("Location: complete_profile.php");
                exit();
            }
        }
    }
}

// If no code, redirect to Google OAuth
if (!isset($_GET['code'])) {
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => 'email profile',
        'response_type' => 'code',
        'access_type' => 'offline'
    ]);
    
    header("Location: " . $auth_url);
    exit();
}
?> 