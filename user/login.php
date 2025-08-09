<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Login logic
$login_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $loginUsername = $conn->real_escape_string(trim($_POST['loginUsername']));
    $loginPassword = trim($_POST['loginPassword']);  // Don't escape password

    // Validate inputs
    if (empty($loginUsername) || empty($loginPassword)) {
        $login_error = "Both fields are required!";
    } else {
        // Find user by username or email
        $sql = "SELECT * FROM users WHERE username = '$loginUsername' OR email = '$loginUsername' LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($loginPassword, $user['password'])) {
                // Login success, set session and redirect
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_success'] = true;
                $_SESSION['user_full_name'] = $user['name'];
                $_SESSION['role'] = $user['role']; // Add role to session
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin../adminPanel.php");
                } elseif ($user['role'] === 'mayor') {
                    header("Location: mayorDashboard.php");
                } else {
                    // Default to user dashboard for regular users
                    header("Location: userSide.php");
                }
                exit();
            } else {
                $login_error = "Invalid password. Please try again.";
            }
        } else {
            $login_error = "User not found. Please check your credentials.";
        }
    }
}

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Solano Mayor's Office Appointment System</title>
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
            background: #5d6d7e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }
        
        .login-header {
            background: #0055a4;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .login-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 25px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 0.95rem;
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
            border-radius: 8px;
            transition: all 0.3s;
            width: 100%;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: #004080;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .social-login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 600;
            margin: 12px 0;
            transition: all 0.3s;
            text-decoration: none;
            color: white;
            border: none;
            width: 100%;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .social-login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: white;
        }
        
        .google-btn {
            background: #DB4437;
        }
        
        .google-btn:hover {
            background: #c23325;
        }
        

        

        
        .social-login-btn i {
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .divider:before,
        .divider:after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .divider-text {
            padding: 0 20px;
            color: #777;
            font-weight: 500;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .form-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #ecf0f1;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .back-home:hover {
            color: #bdc3c7;
            transform: translateX(-5px);
        }
        
        .security-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .security-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .security-item:last-child {
            margin-bottom: 0;
        }
        
        .security-item i {
            color: var(--primary-green);
            margin-right: 15px;
            margin-top: 3px;
            font-size: 1.2rem;
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        .spinner {
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 10px;
                max-width: 95%;
            }
            
            .login-header,
            .login-body {
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
    <a href="landingPage.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        Back to Home
    </a>
    
    <div class="login-container">
        <div class="login-header">
           <img src="images/logooo.png" alt="Company Logo" style="width:150px; height:auto; border-radius: 15px;">
            <p>Access your appointment dashboard</p>
        </div>
        
        <div class="login-body">
            <?php if(!empty($login_error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $login_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['registration']) && $_GET['registration'] == 'success'): ?>
                <div class="alert alert-success" id="registrationSuccess">
                    <i class="bi bi-check-circle-fill me-2"></i> Registration successful! You can now sign in with your new account.
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email or Username</label>
                    <input type="text" class="form-control" name="loginUsername" placeholder="Enter your email or username" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="password-container">
                        <input type="password" class="form-control" name="loginPassword" placeholder="Enter your password" required>
                        <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                    <div class="text-end mt-2">
                        <a href="#" class="text-decoration-none">Forgot Password?</a>
                    </div>
                </div>
                
                <button class="btn btn-primary mb-3" id="loginSubmit" name="login" type="submit">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </button>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
            
            <div class="divider">
                <span class="divider-text">or continue with</span>
            </div>
            
            <button class="social-login-btn google-btn" id="googleLoginBtn" type="button">
                <i class="bi bi-google"></i>Login with Google
            </button>
            
            <div class="security-info">
                <div class="security-item">
                    <i class="bi bi-shield-check"></i>
                    <div>We never access your personal information without permission</div>
                </div>
                <div class="security-item">
                    <i class="bi bi-lock"></i>
                    <div>Your data is encrypted and securely stored</div>
                </div>
                <div class="security-item">
                    <i class="bi bi-person-x"></i>
                    <div>We don't share your information with third parties</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.querySelector('input[name="loginPassword"]');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('bi-eye-slash');
            this.classList.toggle('bi-eye');
        });
        
        // Google Login
        document.getElementById('googleLoginBtn').addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Redirecting to Google...';
            this.disabled = true;
            
            // Redirect to Google OAuth simulator (works without Google Cloud setup)
            window.location.href = 'google_oauth_simulator.php';
        });
        
        // Registration success toast
        const registrationSuccess = document.getElementById('registrationSuccess');
        if (registrationSuccess) {
            // Auto-hide the toast after 5 seconds
            setTimeout(() => {
                registrationSuccess.classList.add('fade-out');
                setTimeout(() => {
                    registrationSuccess.remove();
                }, 500);
            }, 5000);
            
            // Also hide on click
            registrationSuccess.addEventListener('click', function() {
                this.classList.add('fade-out');
                setTimeout(() => {
                    this.remove();
                }, 500);
            });
        }
        

    </script>
</body>
</html> 