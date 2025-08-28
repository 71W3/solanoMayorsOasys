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

require_once 'email_helper_phpmailer.php';

// Process registration form
$registration_step = 'form';
$registration_success = false;
$registration_error = "";
$form_values = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    error_log("Registration form submitted");
    // Step 1: User submits registration form (no code yet)
    if (!isset($_POST['verification_code'])) {
        $full_name = $conn->real_escape_string(trim($_POST['full_name']));
        $username = $conn->real_escape_string(trim($_POST['username']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $phone = $conn->real_escape_string(trim($_POST['phone']));
        $address = $conn->real_escape_string(trim($_POST['address']));
        $password_input = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $form_values = [
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'address' => $address
        ];
        // Validate inputs with specific error messages
        if (empty($full_name)) {
            $registration_error = "Full Name is required!";
        } elseif (empty($username)) {
            $registration_error = "Username is required!";
        } elseif (empty($email)) {
            $registration_error = "Email is required!";
        } elseif (empty($phone)) {
            $registration_error = "Phone Number is required!";
        } elseif (empty($address)) {
            $registration_error = "Address is required!";
        } elseif (empty($password_input)) {
            $registration_error = "Password is required!";
        } elseif (empty($confirm_password)) {
            $registration_error = "Confirm Password is required!";
        } elseif (!isset($_POST['terms']) || !$_POST['terms']) {
            $registration_error = "You must agree to the Terms of Service and Privacy Policy!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $registration_error = "Invalid email format!";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $registration_error = "Phone number must contain only numbers (10-15 digits)!";
        } elseif (!preg_match('/[a-zA-Z]/', $address)) {
            $registration_error = "Address must contain letters!";
        } elseif ($password_input !== $confirm_password) {
            $registration_error = "Passwords do not match!";
        } elseif (strlen($password_input) < 8) {
            $registration_error = "Password must be at least 8 characters long!";
        } else {
            error_log("Validation passed, checking for existing users");
            // Use prepared statements for checking existing users
            $check_stmt = executeQuery(
                "SELECT COUNT(*) as count, 'username' as type FROM users WHERE username = ? 
                 UNION ALL 
                 SELECT COUNT(*) as count, 'email' as type FROM users WHERE email = ?",
                "ss",
                [$username, $email]
            );
            
            if (!$check_stmt) {
                $registration_error = "Database error. Please try again.";
            } else {
                $check_result = $check_stmt->get_result();
                $username_exists = false;
                $email_exists = false;
                
                while ($row = $check_result->fetch_assoc()) {
                    if ($row['type'] === 'username' && $row['count'] > 0) {
                        $username_exists = true;
                    } elseif ($row['type'] === 'email' && $row['count'] > 0) {
                        $email_exists = true;
                    }
                }
                $check_stmt->close();
            
                if ($username_exists && $email_exists) {
                    $registration_error = "Both username '$username' and email '$email' are already taken!";
                    error_log("Both username and email already exist");
                } elseif ($username_exists) {
                    $registration_error = "Username '$username' is already taken! Please choose a different username.";
                    error_log("Username already exists");
                } elseif ($email_exists) {
                    $registration_error = "Email '$email' is already registered! Please use a different email address.";
                    error_log("Email already exists");
                } else {
                // Generate code, store all data in session, send email
                $verification_code = rand(100000, 999999);
                $_SESSION['pending_registration'] = [
                    'full_name' => $full_name,
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'password' => $password_input
                ];
                $_SESSION['verification_code'] = $verification_code;
                // Send verification email and redirect to verification page
                error_log("Sending verification email to: " . $email);
                $email_result = sendVerificationCodeEmail($email, $full_name, $verification_code);
                
                if ($email_result['success']) {
                    error_log("Verification email sent successfully");
                    // Redirect to verification page
                    header("Location: verify_registration.php");
                    exit();
                } else {
                    $registration_error = "Failed to send verification email: " . $email_result['message'];
                    error_log("Email sending failed: " . $email_result['message']);
                }
            }
        }
    } else {
        // Step 2: User submits verification code
        $user_code = trim($_POST['verification_code']);
        if (!isset($_SESSION['verification_code']) || !isset($_SESSION['pending_registration'])) {
            $registration_error = "Session expired. Please try registering again.";
        } elseif ($user_code != $_SESSION['verification_code']) {
            $registration_error = "Incorrect verification code. Please check your email.";
            $registration_step = 'verify';
        } else {
            // All good, create account
            $data = $_SESSION['pending_registration'];
            $full_name = $conn->real_escape_string($data['full_name']);
            $username = $conn->real_escape_string($data['username']);
            $email = $conn->real_escape_string($data['email']);
            $phone = $conn->real_escape_string($data['phone']);
            $address = $conn->real_escape_string($data['address']);
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $insert_stmt = executeQuery(
                "INSERT INTO users (name, email, username, password, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                "ssssss",
                [$full_name, $email, $username, $password, $phone, $address]
            );
            
            if ($insert_stmt) {
                $registration_success = true;
                $form_values = [];
                unset($_SESSION['pending_registration']);
                unset($_SESSION['verification_code']);
            } else {
                $registration_error = "Registration failed. Please try again.";
                $registration_step = 'verify';
            }
            if ($insert_stmt) {
                $insert_stmt->close();
            }
        }
    }
}

// Connection managed by singleton pattern
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for Solano Mayor's Office Appointment System">
    <title>Register - Solano Mayor's Office Appointment System</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="userStyles/register.css" as="style">
    
    <!-- DNS prefetch -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    
    <!-- Critical CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="userStyles/register.css">
</head>
<body>
    <a href="landingPage.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Home</span>
    </a>
    
    <div class="register-container">
        <div class="register-header">
            <div class="logo-container">
                <div class="logo-icon no-border">
                    <img src="images/logooo.png" alt="Solano Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:50px;height:50px;background:#0055a4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;\'>S</div>';">
                </div>
            </div>
            <div class="brand-text">
                <h3 class="brand-title">Sol<span class="title-style">ano</span></h3>
                <p class="brand-subtitle">Mayor's Office</p>
            </div>
            <h4>Create Account</h4>
            <p>Join our appointment system to schedule your visits</p>
        </div>
        
        <div class="register-body">
            <?php if($registration_success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i> Registration successful! You can now <a href="login.php">login</a>.
                </div>
            <?php elseif($registration_error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $registration_error; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="register" value="1">
                
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" placeholder="Enter your full name" value="<?php echo isset($form_values['full_name']) ? $form_values['full_name'] : ''; ?>" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Choose a username" value="<?php echo isset($form_values['username']) ? $form_values['username'] : ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="Enter your email" value="<?php echo isset($form_values['email']) ? $form_values['email'] : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="Enter your phone number" value="<?php echo isset($form_values['phone']) ? $form_values['phone'] : ''; ?>" pattern="[0-9]{10,15}" title="10-15 digit phone number (numbers only)" required>
                            <small class="text-muted">Numbers only (10-15 digits)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Enter your full address" value="<?php echo isset($form_values['address']) ? $form_values['address'] : ''; ?>" required>
                            <small class="text-muted">Must contain letters (not just numbers)</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="registerPassword" name="password" placeholder="Create a strong password" required>
                                <i class="bi bi-eye-slash toggle-password" id="toggleRegisterPassword"></i>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter" id="strengthMeter"></div>
                            </div>
                            <div class="password-strength-text" id="strengthText">Password strength: weak</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" name="confirm_password" id="registerConfirmPassword" placeholder="Confirm your password" required>
                                <i class="bi bi-eye-slash toggle-password" id="toggleConfirmPassword"></i>
                            </div>
                            <div id="confirmPasswordFeedback" style="min-height: 20px; font-size: 0.75rem; margin-top: 0.25rem; color: var(--text-light);"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="termsCheck" name="terms" required>
                        <label class="form-check-label" for="termsCheck">
                            I agree to the <a href="terms.php" target="_blank" class="text-decoration-none">Terms of Service</a> and <a href="privacy.php" target="_blank" class="text-decoration-none">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                
                <button class="btn btn-primary mb-3" id="registerSubmit" name="register" type="submit">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
                
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Optimized JavaScript loading -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <script>
        // Enhanced password toggle functionality - EXACTLY like login page
        const passwordToggles = document.querySelectorAll('.toggle-password');
        
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                // Toggle icon
                this.classList.toggle('bi-eye-slash');
                this.classList.toggle('bi-eye');
            });
        });
        
        // Enhanced password strength checker with animations
        const passwordInput = document.getElementById('registerPassword');
        const strengthMeter = document.getElementById('strengthMeter');
        const strengthText = document.getElementById('strengthText');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength += 1;
            
            // Check for lowercase letters
            if (/[a-z]/.test(password)) strength += 1;
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) strength += 1;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength += 1;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength meter with animation
            strengthMeter.className = 'strength-meter';
            if (strength > 0) {
                strengthMeter.classList.add('strength-' + (strength - 1));
            }
            
            // Update text with color transition
            const strengthLabels = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
            if (password.length > 0) {
                strengthText.textContent = 'Password strength: ' + strengthLabels[Math.max(0, strength - 1)];
                strengthText.style.color = strength < 2 ? '#dc2626' : strength < 4 ? '#ffc107' : '#059669';
            } else {
                strengthText.textContent = 'Password strength: weak';
                strengthText.style.color = 'var(--text-light)';
            }
        });
        
        // Enhanced real-time password match feedback with animations
        const registerPassword = document.getElementById('registerPassword');
        const registerConfirmPassword = document.getElementById('registerConfirmPassword');
        const confirmPasswordFeedback = document.getElementById('confirmPasswordFeedback');

        function checkPasswordMatch() {
            if (!registerConfirmPassword.value) {
                confirmPasswordFeedback.textContent = '';
                confirmPasswordFeedback.style.color = 'var(--text-light)';
                return;
            }
            if (registerPassword.value === registerConfirmPassword.value) {
                confirmPasswordFeedback.textContent = '✓ Passwords match!';
                confirmPasswordFeedback.style.color = '#059669';
            } else {
                confirmPasswordFeedback.textContent = '✗ Passwords do not match.';
                confirmPasswordFeedback.style.color = '#dc2626';
            }
        }
        
        registerPassword.addEventListener('input', checkPasswordMatch);
        registerConfirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Enhanced form submission handler - EXACTLY like login page
        const registerSubmitBtn = document.getElementById('registerSubmit');

registerSubmitBtn.addEventListener('click', function(e) {
        // Show loading state with animation
        this.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Creating account...';
        this.disabled = true;
        
        // Submit the form
        this.closest('form').submit();
    });
        
        // Enhanced alert auto-hide functionality - EXACTLY like login page  
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            // Auto-hide success/error alerts after 5 seconds
            if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }, 5000);
                
                // Also hide on click
                alert.addEventListener('click', function() {
                    this.classList.add('fade-out');
                    setTimeout(() => {
                        if (this.parentNode) {
                            this.remove();
                        }
                    }, 500);
                });
            }
        });
        
        // Add subtle hover effects to form elements
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.addEventListener('mouseenter', function() {
                if (!this.matches(':focus')) {
                    this.style.borderColor = '#cbd5e1';
                }
            });
            
            control.addEventListener('mouseleave', function() {
                if (!this.matches(':focus')) {
                    this.style.borderColor = 'var(--border)';
                }
            });
        });
    </script>
</body>
</html>