<?php
// Enable output buffering and compression
ob_start();
if (extension_loaded('zlib') && !ob_get_level()) {
    ob_start('ob_gzhandler');
}

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use optimized database connection
require_once 'db_config.php';
$conn = getDB();

// Login logic
$login_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $loginUsername = $conn->real_escape_string(trim($_POST['loginUsername']));
    $loginPassword = trim($_POST['loginPassword']);  // Don't escape password

    // Validate inputs
    if (empty($loginUsername) || empty($loginPassword)) {
        $login_error = "Both fields are required!";
    } else {
        // Use prepared statement for security and performance
        $stmt = executeQuery(
            "SELECT id, username, name, password, role FROM users WHERE username = ? OR email = ? LIMIT 1",
            "ss",
            [$loginUsername, $loginUsername]
        );
        
        if (!$stmt) {
            $login_error = "Database error. Please try again.";
        } else {
            $result = $stmt->get_result();

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
                        header("Location: ../admin/adminPanel.php");
                    } elseif ($user['role'] === 'mayor') {
                        header("Location: ../mayor/mayorDashboard.php");
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
            $stmt->close();
        }
    }
}

// Connection will be managed by singleton pattern
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Solano Mayor's Office Appointment System">
    <title>Login - Solano Mayor's Office Appointment System</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="userStyles/login.css" as="style">
    
    <!-- DNS prefetch -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    
    <!-- Critical CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="userStyles/login.css">
</head>
<body>
    <a href="landingPage.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Home</span>
    </a>
    
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <div class="logo-icon no-border">
                    <img src="images/logooo.png" alt="Solano Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:50px;height:50px;background:#0055a4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;\'>S</div>';">
                </div>
            </div>
            <div class="brand-text">
                <h3 class="brand-title">Sol<span class="title-style">ano</span></h3>
                <p class="brand-subtitle">Mayor's Office</p>
            </div>
            <h4>Welcome Back</h4>
            <p>Sign in to access your appointment dashboard</p>
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
            
            <div class="divider">
                <span class="divider-text">or continue with</span>
            </div>
            
            <button class="social-login-btn google-btn" id="googleLoginBtn" type="button">
                <i class="bi bi-google"></i>Login with Google
            </button>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>

    <!-- Optimized JavaScript loading -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <script>
        // Password toggle functionality - EXACTLY like working version
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.querySelector('input[name="loginPassword"]');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('bi-eye-slash');
            this.classList.toggle('bi-eye');
        });
        
        // Google Login - EXACTLY like working version
        document.getElementById('googleLoginBtn').addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Redirecting to Google...';
            this.disabled = true;
            
            // Redirect to Google OAuth simulator
            window.location.href = 'google_oauth_simulator.php';
        });
        
        // Registration success toast - EXACTLY like working version
        const registrationSuccess = document.getElementById('registrationSuccess');
        if (registrationSuccess) {
            // Auto-hide after 5 seconds
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