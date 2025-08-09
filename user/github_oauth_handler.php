<?php
session_start();

// GitHub OAuth Configuration - No payment verification required
$client_id = 'Ov23limPhKwC97WrklLR'; // Your GitHub Client ID
$client_secret = 'd14c20c7bf063382a53daa3e5f99e44e17fb0c1e'; // Your GitHub Client Secret
$redirect_uri = 'http://localhost/barangay/github_oauth_handler.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oasys";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle GitHub OAuth callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange code for access token
    $token_url = 'https://github.com/login/oauth/access_token';
    $post_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: Solano-Mayor-App'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Get user info from GitHub
        $user_info_url = 'https://api.github.com/user';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $token_data['access_token'],
            'Accept: application/json',
            'User-Agent: Solano-Mayor-App'
        ]);
        
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_data = json_decode($user_response, true);
        
        if ($user_data) {
            $github_email = $user_data['email'] ?? $user_data['login'] . '@github.com';
            $github_name = $user_data['name'] ?? $user_data['login'];
            $github_picture = $user_data['avatar_url'];
            
            // Check if user exists in database
            $check_sql = "SELECT * FROM users WHERE email = ? OR github_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ss", $github_email, $user_data['login']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists - login directly
                $user = $result->fetch_assoc();
                
                // Update GitHub info if needed
                $update_sql = "UPDATE users SET github_id = ?, profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ssi", $user_data['login'], $github_picture, $user['id']);
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
                // New user - store GitHub info and redirect to profile completion
                $_SESSION['github_user_data'] = [
                    'email' => $github_email,
                    'name' => $github_name,
                    'picture' => $github_picture,
                    'github_id' => $user_data['login']
                ];
                
                header("Location: complete_profile.php");
                exit();
            }
        }
    }
}

// If no code, redirect to GitHub OAuth
if (!isset($_GET['code'])) {
    $auth_url = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => 'user:email'
    ]);
    
    header("Location: " . $auth_url);
    exit();
}
?> 