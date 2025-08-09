<?php
session_start();
require_once 'email_helper_phpmailer.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_auth_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$verification_error = "";
$verification_success = false;

// Check if user has pending registration
if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['verification_code'])) {
    header("Location: landingPage.php");
    exit();
}

// Process verification code submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $user_code = trim($_POST['verification_code']);
    
    if ($user_code == $_SESSION['verification_code']) {
        // Code is correct, create account
        $data = $_SESSION['pending_registration'];
        $full_name = $conn->real_escape_string($data['full_name']);
        $username = $conn->real_escape_string($data['username']);
        $email = $conn->real_escape_string($data['email']);
        $phone = $conn->real_escape_string($data['phone']);
        $address = $conn->real_escape_string($data['address']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $insert_sql = "INSERT INTO users (name, email, username, password, phone, address, created_at)
                       VALUES ('$full_name','$email', '$username','$password',  '$phone', '$address',  NOW())";
        
        if ($conn->query($insert_sql)) {
            $verification_success = true;
            unset($_SESSION['pending_registration']);
            unset($_SESSION['verification_code']);
        } else {
            $verification_error = "Error creating account: " . $conn->error;
        }
    } else {
        $verification_error = "Incorrect verification code. Please check your email and try again.";
    }
}

$user_email = $_SESSION['pending_registration']['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Registration - Solano Mayor's Office</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
            background: #5d6d7e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .verification-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            margin: 20px;
        }
        
        .verification-header {
            background: #0055a4;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .verification-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        .verification-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .verification-body {
            padding: 30px;
        }
        
        .verification-icon {
            font-size: 4rem;
            color: #0055a4;
            margin-bottom: 20px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 85, 164, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .btn-primary {
            background: var(--primary-blue);
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: #004080;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <h1><i class="bi bi-shield-check me-3"></i>Email Verification</h1>
            <p>Complete your registration by verifying your email</p>
        </div>
        
        <div class="verification-body">
            <?php if($verification_success): ?>
                <div class="text-center">
                    <div class="verification-icon">
                        <i class="bi bi-check-circle-fill text-success"></i>
                    </div>
                    <h2 class="mb-4">Registration Successful!</h2>
                    <p class="text-muted mb-4">Your account has been created successfully. You can now login to your account.</p>
                    <a href="login.php?registration=success" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center mb-4">
                    <div class="verification-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h2 class="mb-3">Verify Your Email</h2>
                    <p class="text-muted">We've sent a verification code to <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                </div>
                
                <?php if($verification_error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $verification_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-4">
                        <label class="form-label">Verification Code</label>
                        <input type="text" class="form-control" name="verification_code" placeholder="Enter the 6-digit code" required maxlength="6" pattern="[0-9]{6}">
                        <small class="text-muted">Enter the 6-digit code sent to your email</small>
                    </div>
                    
                    <button type="submit" name="verify" class="btn btn-primary mb-3 w-100">
                        <i class="bi bi-shield-check me-2"></i>Verify & Create Account
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="register.php" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back to Registration
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 