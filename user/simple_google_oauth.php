<?php
session_start();

// Simple Google OAuth - No Google Cloud required
// This simulates Google login for development/testing

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['google_login'])) {
    // Simulate Google user data
    $google_email = $_POST['email'];
    $google_name = $_POST['name'];
    $google_picture = 'https://via.placeholder.com/150/4285f4/ffffff?text=' . substr($google_name, 0, 1);
    
    // Database configuration
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "my_auth_db";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
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
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Login Simulator - Solano Mayor's Office</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0055a4;
            --primary-green: #28a745;
            --primary-orange: #ff6b35;
            --primary-light: #f8f9fa;
            --primary-dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--primary-dark);
            background: linear-gradient(135deg, rgba(0,85,164,0.9) 0%, rgba(40,167,69,0.85) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .google-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            margin: 20px;
        }
        
        .google-header {
            background: linear-gradient(135deg, #4285f4 0%, #34a853 50%, #fbbc05 75%, #ea4335 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .google-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .google-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .google-body {
            padding: 30px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #4285f4;
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .btn-google {
            background: #4285f4;
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            font-size: 1rem;
            color: white;
        }
        
        .btn-google:hover {
            background: #3367d6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .info-box h6 {
            color: #4285f4;
            font-weight: 600;
        }
        
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .back-home:hover {
            color: rgba(255,255,255,0.8);
            transform: translateX(-5px);
        }
        
        @media (max-width: 768px) {
            .google-container {
                margin: 10px;
                max-width: 95%;
            }
            
            .google-header,
            .google-body {
                padding: 20px 15px;
            }
            
            .back-home {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 15px;
                color: var(--primary-blue);
            }
        }
    </style>
</head>
<body>
    <a href="login.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        Back to Login
    </a>
    
    <div class="google-container">
        <div class="google-header">
            <h1><i class="bi bi-google me-3"></i>Google Login Simulator</h1>
            <p>Test Google OAuth functionality without setup</p>
        </div>
        
        <div class="google-body">
            <form method="post">
                <div class="mb-4">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" placeholder="Enter your email address" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" placeholder="Enter your full name" required>
                </div>
                
                <button class="btn btn-google" name="google_login" type="submit">
                    <i class="bi bi-google me-2"></i>Continue with Google
                </button>
            </form>
            
            <div class="info-box">
                <h6><i class="bi bi-info-circle me-2"></i>Development Mode</h6>
                <p class="mb-2">This simulates Google OAuth for testing purposes. In production, you would:</p>
                <ul class="mb-0">
                    <li>Set up Google Cloud OAuth credentials</li>
                    <li>Use the actual Google OAuth flow</li>
                    <li>Get real user data from Google</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 