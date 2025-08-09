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
        // Validate inputs (same as before)
        if (empty($full_name) || empty($username) || empty($email) || empty($phone) || empty($address) || empty($password_input)) {
            $registration_error = "All fields are required!";
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
            // Check if username or email already exists
            $check_username_sql = "SELECT * FROM users WHERE username = '$username'";
            $check_email_sql = "SELECT * FROM users WHERE email = '$email'";
            
            $username_result = $conn->query($check_username_sql);
            $email_result = $conn->query($check_email_sql);
            
            if ($username_result->num_rows > 0 && $email_result->num_rows > 0) {
                $registration_error = "Both username '$username' and email '$email' are already taken!";
                error_log("Both username and email already exist");
            } elseif ($username_result->num_rows > 0) {
                $registration_error = "Username '$username' is already taken! Please choose a different username.";
                error_log("Username already exists");
            } elseif ($email_result->num_rows > 0) {
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
            $insert_sql = "INSERT INTO users (name, email, username, password, phone, address, created_at)
                           VALUES ('$full_name','$email', '$username','$password',  '$phone', '$address',  NOW())";
            if ($conn->query($insert_sql)) {
                $registration_success = true;
                $form_values = [];
                unset($_SESSION['pending_registration']);
                unset($_SESSION['verification_code']);
            } else {
                $registration_error = "Error: " . $conn->error;
                $registration_step = 'verify';
            }
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
    <title>Register - Solano Mayor's Office Appointment System</title>
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
            padding: 20px 0;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            margin: 20px;
        }
        
        .register-header {
            background: #0055a4;
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .register-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .register-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .form-control {
            padding: 15px 20px;
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
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
            width: 100%;
            font-size: 1.1rem;
        }
        
        .btn-primary:hover {
            background: #004080;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
            margin-top: 30px;
            font-size: 1rem;
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
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .password-strength {
            height: 5px;
            width: 100%;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .strength-0 { width: 20%; background: #dc3545; }
        .strength-1 { width: 40%; background: #fd7e14; }
        .strength-2 { width: 60%; background: #ffc107; }
        .strength-3 { width: 80%; background: #20c997; }
        .strength-4 { width: 100%; background: #28a745; }
        
        .password-strength-text {
            font-size: 0.8rem;
            margin-top: 5px;
            color: #6c757d;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .form-check-label a {
            color: var(--primary-blue);
            text-decoration: none;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
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
            .register-container {
                margin: 10px;
            }
            
            .register-header,
            .register-body {
                padding: 30px 20px;
            }
            
            .back-home {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 20px;
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
    
    <div class="register-container">
        <div class="register-header">
            <img src="images/logooo.png" alt="Company Logo" style="width:120px; height:auto; border-radius: 15px;">
            <h2><i class="bi bi-person-plus me-3"></i>Create Account</h2>
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
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" placeholder="Enter your full name" value="<?php echo isset($form_values['full_name']) ? $form_values['full_name'] : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Choose a username" value="<?php echo isset($form_values['username']) ? $form_values['username'] : ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="Enter your email" value="<?php echo isset($form_values['email']) ? $form_values['email'] : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="Enter your phone number" value="<?php echo isset($form_values['phone']) ? $form_values['phone'] : ''; ?>" pattern="[0-9]{10,15}" title="10-15 digit phone number (numbers only)" required>
                            <small class="text-muted">Numbers only (10-15 digits)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Enter your full address" value="<?php echo isset($form_values['address']) ? $form_values['address'] : ''; ?>" required>
                            <small class="text-muted">Must contain letters (not just numbers)</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
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
                        <div class="mb-4">
                            <label class="form-label">Confirm Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" name="confirm_password" id="registerConfirmPassword" placeholder="Confirm your password" required>
                                <i class="bi bi-eye-slash toggle-password" id="toggleConfirmPassword"></i>
                            </div>
                            <div id="confirmPasswordFeedback" style="min-height: 20px; font-size: 0.95em; margin-top: 5px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="termsCheck" name="terms" required>
                        <label class="form-check-label" for="termsCheck">
                            I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                
                <button class="btn btn-primary mb-4" id="registerSubmit" name="register" type="submit">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
                
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle functionality
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
        
        // Password strength checker
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
            
            // Update strength meter
            strengthMeter.className = 'strength-meter';
            strengthMeter.classList.add('strength-' + (strength - 1));
            
            // Update text
            const strengthLabels = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
            strengthText.textContent = 'Password strength: ' + strengthLabels[strength - 1];
            strengthText.style.color = strength < 2 ? '#dc3545' : strength < 4 ? '#ffc107' : '#28a745';
        });
        
        // Real-time password match feedback
        const registerPassword = document.getElementById('registerPassword');
        const registerConfirmPassword = document.getElementById('registerConfirmPassword');
        const confirmPasswordFeedback = document.getElementById('confirmPasswordFeedback');

        function checkPasswordMatch() {
            if (!registerConfirmPassword.value) {
                confirmPasswordFeedback.textContent = '';
                return;
            }
            if (registerPassword.value === registerConfirmPassword.value) {
                confirmPasswordFeedback.textContent = 'Passwords match!';
                confirmPasswordFeedback.style.color = '#28a745';
            } else {
                confirmPasswordFeedback.textContent = 'Passwords do not match.';
                confirmPasswordFeedback.style.color = '#dc3545';
            }
        }
        registerPassword.addEventListener('input', checkPasswordMatch);
        registerConfirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Form submission handler
        const registerSubmitBtn = document.getElementById('registerSubmit');
        
        registerSubmitBtn.addEventListener('click', function(e) {
            const fullName = document.querySelector('input[name="full_name"]').value;
            const username = document.querySelector('input[name="username"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const phone = document.querySelector('input[name="phone"]').value;
            const address = document.querySelector('input[name="address"]').value;
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            const terms = document.getElementById('termsCheck').checked;
            
            if (!fullName || !username || !email || !phone || !address || !password || !confirmPassword) {
                alert('Please fill in all fields');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                e.preventDefault();
                return;
            }
            
            if (!terms) {
                alert('Please agree to the Terms of Service and Privacy Policy');
                e.preventDefault();
                return;
            }
            
            // Show loading state
            this.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Creating account...';
            this.disabled = true;
            
            // Submit the form
            this.closest('form').submit();
        });
    </script>
</body>
</html> 