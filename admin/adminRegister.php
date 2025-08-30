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

// Use database connection and functions
require_once 'connect.php';
require_once 'function.php';
require_once 'adminPanel_functions.php';

// Check if users table exists and has required columns
$table_check = mysqli_query($con, "SHOW TABLES LIKE 'users'");
if (!$table_check || $table_check->num_rows == 0) {
    die("Error: Users table does not exist. Please check your database setup.");
}

// Check if required columns exist
$columns_check = mysqli_query($con, "SHOW COLUMNS FROM users LIKE 'role'");
if (!$columns_check || $columns_check->num_rows == 0) {
    die("Error: Users table is missing required columns. Please check your database setup.");
}

// Test database connection
if (!$con) {
    die("Error: Database connection failed.");
}

// Test if we can query the users table
$test_query = mysqli_query($con, "SELECT COUNT(*) as count FROM users");
if (!$test_query) {
    die("Error: Cannot query users table. Error: " . mysqli_error($con));
}

// Log current user count for debugging
$user_count = mysqli_fetch_assoc($test_query)['count'];
error_log("Current users in database: " . $user_count);

// Test if we have INSERT permissions
$test_insert = mysqli_query($con, "INSERT INTO users (name, email, username, password, phone, address, role) VALUES ('TEST', 'test@test.com', 'testuser', 'testpass', '123', 'test', 'user')");
if ($test_insert) {
    error_log("Test insert successful - we have INSERT permissions");
    // Clean up test record
    mysqli_query($con, "DELETE FROM users WHERE username = 'testuser'");
} else {
    error_log("Test insert failed - permission issue: " . mysqli_error($con));
}

// Initialize admin panel to get admin info (same as other admin pages)
$adminData = initializeAdminPanel($con);

// Extract admin info from the data array
$admin_name = $adminData['admin_name'];
$admin_role = $adminData['admin_role'];

// Process registration form
$registration_success = false;
$registration_error = "";
$form_values = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    error_log("Admin registration form submitted");
    error_log("POST data received: " . print_r($_POST, true));
    
    // Get form data
    $full_name = $con->real_escape_string(trim($_POST['full_name']));
    $username = $con->real_escape_string(trim($_POST['username']));
    $email = $con->real_escape_string(trim($_POST['email']));
    $phone = $con->real_escape_string(trim($_POST['phone']));
    $address = $con->real_escape_string(trim($_POST['address']));
    $role = $con->real_escape_string(trim($_POST['role']));
    $password_input = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $form_values = [
        'full_name' => $full_name,
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'role' => $role
    ];
    
    // Validate inputs
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
    } elseif (empty($role)) {
        $registration_error = "Role is required!";
    } elseif (empty($password_input)) {
        $registration_error = "Password is required!";
    } elseif (empty($confirm_password)) {
        $registration_error = "Confirm Password is required!";
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
            
            // Check for existing username and email
            $check_username = mysqli_query($con, "SELECT COUNT(*) as count FROM users WHERE username = '$username'");
            $check_email = mysqli_query($con, "SELECT COUNT(*) as count FROM users WHERE email = '$email'");
            
            if (!$check_username || !$check_email) {
                $registration_error = "Database error. Please try again.";
                error_log("Database check failed: username check=" . ($check_username ? "success" : "failed") . ", email check=" . ($check_email ? "success" : "failed"));
            } else {
                $username_exists = mysqli_fetch_assoc($check_username)['count'] > 0;
                $email_exists = mysqli_fetch_assoc($check_email)['count'] > 0;
                
                error_log("Username exists: " . ($username_exists ? "yes" : "no") . ", Email exists: " . ($email_exists ? "yes" : "no"));
                
                if ($username_exists && $email_exists) {
                    $registration_error = "Both username '$username' and email '$email' are already taken!";
                } elseif ($username_exists) {
                    $registration_error = "Username '$username' is already taken! Please choose a different username.";
                } elseif ($email_exists) {
                    $registration_error = "Email '$email' is already registered! Please use a different email address.";
                } else {
                // Hash password and insert into users table
                $password = password_hash($password_input, PASSWORD_DEFAULT);
                
                $insert_query = "INSERT INTO users (name, email, username, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                error_log("Insert query: " . $insert_query);
                error_log("Values: name=$full_name, email=$email, username=$username, phone=$phone, address=$address, role=$role");
                
                $stmt = $con->prepare($insert_query);
                if (!$stmt) {
                    $registration_error = "Statement preparation failed: " . $con->error;
                    error_log("Statement preparation failed: " . $con->error);
                } else {
                    $stmt->bind_param("sssssss", $full_name, $email, $username, $password, $phone, $address, $role);
                    
                    if ($stmt->execute()) {
                        $registration_success = true;
                        $form_values = [];
                        error_log("Admin account created successfully for role: " . $role . " with ID: " . $stmt->insert_id);
                    } else {
                        $registration_error = "Registration failed: " . $stmt->error;
                        error_log("Admin registration failed: " . $stmt->error);
                    }
                    
                    $stmt->close();
                }
            }
        }
    }
}

// Connection managed by connect.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Registration - Solano Mayor's Office Appointment System">
    <title>Admin Registration - SOLAR Appointment System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminRegister.css">
</head>
<body>
    <div class="overlay" id="overlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <img src="../image/logo.png" alt="Logo" style="width: 36px; height: 36px; object-fit: contain;">
                    </div>
                    <div>
                        <h5>OASYS Admin</h5>
                        <div class="version">Municipality of Solano</div>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="adminPanel.php">
                    <i class="bi bi-house"></i>
                    Dashboard
                </a>
                <a href="appointment.php">
                    <i class="bi bi-calendar-check"></i>
                    Appointments
                </a>
                <a href="walk_in.php">
                    <i class="bi bi-person-walking"></i>
                    Walk-ins
                </a>
                <a href="queue.php">
                    <i class="bi bi-list-ol"></i>
                    Queue
                </a>
                <a href="schedule.php">
                    <i class="bi bi-calendar"></i>
                    Schedule
                </a>
                <a href="announcement.php">
                    <i class="bi bi-megaphone"></i>
                    Announcement
                </a>
                <a href="history.php">
                    <i class="bi bi-clock-history"></i>
                    History
                </a>
                <a href="adminRegister.php" class="active">
                    <i class="bi bi-person-plus"></i>
                    Admin Registration
                </a>
                <a href="logoutAdmin.php" class="text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </nav>
        </div>

        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="mobile-menu-btn d-md-none me-3" id="mobileMenuBtn">
                            <i class="bi bi-list"></i>
                        </button>
                        <div>
                            <h1 class="page-title">Admin Registration</h1>
                            <p class="text-muted mb-0 small d-none d-sm-block">Create and manage administrative accounts</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="d-none d-sm-inline me-2">
                            <div class="text-muted small"><?= htmlspecialchars($admin_name) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($admin_role) ?></div>
                        </div>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Registration Form -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="section-title">Create New Admin Account</h2>
                    </div>
                    <div class="card-body">
            <?php if($registration_success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i> 
                    <strong>Success!</strong> Admin account has been created successfully. 
                    The new admin can now login using their username and password.
                    <div class="mt-2">
                        <a href="adminPanel.php" class="btn btn-sm btn-outline-success me-2">
                            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="adminRegister.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-person-plus me-1"></i>Create Another Account
                        </a>
                    </div>
                </div>
            <?php elseif($registration_error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                    <strong>Error:</strong> <?php echo $registration_error; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="adminRegistrationForm" onsubmit="return validateForm()">
                <input type="hidden" name="register" value="1">
                
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" placeholder="Enter admin's full name" value="<?php echo isset($form_values['full_name']) ? $form_values['full_name'] : ''; ?>" required>
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
                            <input type="email" class="form-control" name="email" placeholder="Enter admin's email" value="<?php echo isset($form_values['email']) ? $form_values['email'] : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="Enter phone number" value="<?php echo isset($form_values['phone']) ? $form_values['phone'] : ''; ?>" pattern="[0-9]{10,15}" title="10-15 digit phone number (numbers only)" required>
                            <small class="text-muted">Numbers only (10-15 digits)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Enter full address" value="<?php echo isset($form_values['address']) ? $form_values['address'] : ''; ?>" required>
                            <small class="text-muted">Must contain letters (not just numbers)</small>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" required>
                        <option value="">Select a role</option>
                        <option value="admin" <?php echo (isset($form_values['role']) && $form_values['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="frontdesk" <?php echo (isset($form_values['role']) && $form_values['role'] == 'frontdesk') ? 'selected' : ''; ?>>Front Desk</option>
                        <option value="mayor" <?php echo (isset($form_values['role']) && $form_values['role'] == 'mayor') ? 'selected' : ''; ?>>Mayor</option>
                        <option value="superadmin" <?php echo (isset($form_values['role']) && $form_values['role'] == 'superadmin') ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                    <small class="text-muted">Choose the appropriate administrative role</small>
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
                
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" id="registerSubmit" name="register" type="submit">
                        <i class="bi bi-person-plus me-2"></i>Create Admin Account
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="testDatabase()">
                        <i class="bi bi-database me-2"></i>Test Database Connection
                    </button>
                </div>
                
                <div class="form-footer">
                    <p>Already have an account? <a href="adminPanel.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Mobile menu functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            if (mobileMenuBtn && sidebar && overlay) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                });
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }
        });
    </script>
    
    <script src="adminRegister.js"></script>
    
    <script>
        function testDatabase() {
            // Test database connection by making a simple AJAX request
            fetch('test_db.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({test: 'connection'})
            })
            .then(response => response.text())
            .then(data => {
                alert('Database test result: ' + data);
            })
            .catch(error => {
                alert('Database test failed: ' + error);
            });
        }
        
        function validateForm() {
            console.log('Form submission started');
            
            // Get form data
            const formData = new FormData(document.getElementById('adminRegistrationForm'));
            console.log('Form data:', Object.fromEntries(formData));
            
            // Check if all required fields are filled
            const requiredFields = ['full_name', 'username', 'email', 'phone', 'address', 'role', 'password', 'confirm_password'];
            for (let field of requiredFields) {
                if (!formData.get(field)) {
                    alert('Please fill in all required fields');
                    return false;
                }
            }
            
            // Check password match
            if (formData.get('password') !== formData.get('confirm_password')) {
                alert('Passwords do not match');
                return false;
            }
            
            console.log('Form validation passed, submitting...');
            return true;
        }
    </script>
</body>
</html> 