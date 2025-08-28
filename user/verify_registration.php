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
    <title>Verify Registration - Solano Mayor's Office Appointment System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="userStyles/verify_registration.css">
</head>
<body>
    <a href="landingPage.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Home</span>
    </a>
    
    <div class="verification-container">
        <div class="verification-header">
            <div class="logo-container">
                <div class="logo-icon no-border">
                    <img src="images/logooo.png" alt="Solano Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:50px;height:50px;background:#0055a4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;\'>S</div>';">
                </div>
            </div>
            <div class="brand-text">
                <h3 class="brand-title">Sol<span class="title-style">ano</span></h3>
                <p class="brand-subtitle">Mayor's Office</p>
            </div>
            <h4>Email Verification</h4>
            <p>Complete your registration by verifying your email</p>
        </div>
        
        <div class="verification-body">
            <?php if($verification_success): ?>
                <div class="text-center success-content">
                    <div class="verification-icon success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2 class="mb-4" style="color: var(--success);">Registration Successful!</h2>
                    <p class="text-muted mb-4">Your account has been created successfully. You can now login to your account and start scheduling appointments.</p>
                    <a href="login.php?registration=success" class="btn btn-success">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center mb-4">
                    <div class="verification-icon pending">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h2 class="mb-3">Verify Your Email</h2>
                    <p class="text-muted">We've sent a verification code to<br><strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                </div>
                
                <?php if($verification_error): ?>
                    <div class="alert alert-danger" id="errorAlert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $verification_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" id="verificationForm">
                    <div class="mb-4">
                        <label class="form-label">Verification Code</label>
                        <input type="text" class="form-control code-input" name="verification_code" placeholder="000000" required maxlength="6" pattern="[0-9]{6}" id="codeInput">
                        <small class="text-muted">Enter the 6-digit code sent to your email</small>
                    </div>
                    
                    <button type="submit" name="verify" class="btn btn-primary mb-3" id="verifyBtn">
                        <i class="bi bi-shield-check me-2"></i>Verify & Create Account
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="register.php" class="back-link">
                        <i class="bi bi-arrow-left"></i>
                        <span>Back to Registration</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced form submission with loading animation - matching login/register pages
        const verificationForm = document.getElementById('verificationForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const codeInput = document.getElementById('codeInput');
        
        if (verificationForm) {
            verificationForm.addEventListener('submit', function(e) {
                const code = codeInput.value.trim();
                
                if (!code || code.length !== 6) {
                    alert('Please enter a valid 6-digit verification code');
                    e.preventDefault();
                    return;
                }
                
                // Show loading state with animation - matching login/register pages
                const originalText = verifyBtn.innerHTML;
                verifyBtn.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Verifying...';
                verifyBtn.disabled = true;
                
                // Allow form to submit normally
            });
        }
        
        // Auto-format code input (numbers only, max 6 digits)
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Remove non-numeric characters
                let value = e.target.value.replace(/\D/g, '');
                
                // Limit to 6 digits
                if (value.length > 6) {
                    value = value.substr(0, 6);
                }
                
                e.target.value = value;
                
                // Auto-submit when 6 digits are entered
                if (value.length === 6) {
                    setTimeout(() => {
                        verificationForm.submit();
                    }, 500);
                }
            });
            
            // Focus on page load
            codeInput.focus();
            
            // Add paste support for codes
            codeInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 6) {
                        value = value.substr(0, 6);
                    }
                    e.target.value = value;
                    
                    if (value.length === 6) {
                        setTimeout(() => {
                            verificationForm.submit();
                        }, 500);
                    }
                }, 10);
            });
        }
        
        // Enhanced alert auto-hide functionality - matching login/register pages
        const errorAlert = document.getElementById('errorAlert');
        if (errorAlert) {
            // Auto-hide after 5 seconds
            setTimeout(() => {
                errorAlert.classList.add('fade-out');
                setTimeout(() => {
                    if (errorAlert.parentNode) {
                        errorAlert.remove();
                    }
                }, 500);
            }, 5000);
            
            // Also hide on click
            errorAlert.addEventListener('click', function() {
                this.classList.add('fade-out');
                setTimeout(() => {
                    if (this.parentNode) {
                        this.remove();
                    }
                }, 500);
            });
        }
        
        // Add subtle hover effects to form elements
        if (codeInput) {
            codeInput.addEventListener('mouseenter', function() {
                if (!this.matches(':focus')) {
                    this.style.borderColor = '#cbd5e1';
                }
            });
            
            codeInput.addEventListener('mouseleave', function() {
                if (!this.matches(':focus')) {
                    this.style.borderColor = 'var(--border)';
                }
            });
        }
        
        // Keyboard navigation enhancement
        document.addEventListener('keydown', function(e) {
            // Allow Enter to submit form when code input is focused and filled
            if (e.key === 'Enter' && codeInput && codeInput === document.activeElement && codeInput.value.length === 6) {
                verificationForm.submit();
            }
        });
    </script>
</body>
</html>