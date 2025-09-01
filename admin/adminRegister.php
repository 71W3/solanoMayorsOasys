<?php
session_start();
include "connect.php";
include "adminPanel_functions.php";

// Initialize admin panel to get proper admin info
$adminData = initializeAdminPanel($con);
$admin_name = $adminData['admin_name'];
$admin_role = $adminData['admin_role'];

// Initialize variables
$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Get and sanitize form data
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $role = trim($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name)) {
        $error_message = "Full Name is required!";
    } elseif (empty($username)) {
        $error_message = "Username is required!";
    } elseif (empty($email)) {
        $error_message = "Email is required!";
    } elseif (empty($address)) {
        $error_message = "Address is required!";
    } elseif (empty($role)) {
        $error_message = "Role is required!";
    } elseif (empty($password)) {
        $error_message = "Password is required!";
    } elseif (empty($confirm_password)) {
        $error_message = "Confirm Password is required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long!";
    } else {
        // Check for existing username and email
        $check_username = $con->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        $username_result = $check_username->get_result();
        $username_exists = $username_result->fetch_assoc()['count'] > 0;
        
        $check_email = $con->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $email_result = $check_email->get_result();
        $email_exists = $email_result->fetch_assoc()['count'] > 0;
        
        if ($username_exists && $email_exists) {
            $error_message = "Both username '$username' and email '$email' are already taken!";
        } elseif ($username_exists) {
            $error_message = "Username '$username' is already taken! Please choose a different username.";
        } elseif ($email_exists) {
            $error_message = "Email '$email' is already registered! Please use a different email address.";
        } else {
            // Hash password and insert into database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $phone = null; // Set phone to null as requested
            
            $stmt = $con->prepare("INSERT INTO users (name, email, username, password, phone, address, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("sssssss", $full_name, $email, $username, $hashed_password, $phone, $address, $role);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Admin account has been created successfully! The new admin can now login using their username and password.";
                header("Location: adminRegister.php");
                exit();
            } else {
                $error_message = "Registration failed: " . $stmt->error;
            }
            
            $stmt->close();
        }
        
        $check_username->close();
        $check_email->close();
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Registration - SOLAR Appointment System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                        <?php if($success_message): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i> 
                                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
                                <div class="mt-2">
                                    <a href="adminPanel.php" class="btn btn-sm btn-outline-success me-2">
                                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                                    </a>
                                    <a href="adminRegister.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-person-plus me-1"></i>Create Another Account
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="adminRegister.php" id="adminRegistrationForm">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" placeholder="Enter admin's full name" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" placeholder="Choose a username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" placeholder="Enter admin's email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" placeholder="Enter full address" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select a role</option>
                                    <option value="admin">Admin</option>
                                    <option value="frontdesk">Front Desk</option>
                                    <option value="mayor">Mayor</option>
                                    <option value="superadmin">Super Admin</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" placeholder="Create a strong password" required minlength="8">
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm your password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button class="btn btn-primary btn-lg" type="submit" name="register">
                                    <i class="bi bi-person-plus me-2"></i>Create Admin Account
                                </button>
                            </div>
                            
                            <div class="form-footer mt-3">
                                <p class="text-center">Already have an account? <a href="adminPanel.php">Login here</a></p>
                            </div>
                        </form>
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

            // Force clear form on page load to prevent browser caching
            const form = document.getElementById('adminRegistrationForm');
            if (form) {
                form.reset();
                
                // Clear all input values manually
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.type === 'text' || input.type === 'email' || input.type === 'password') {
                        input.value = '';
                    } else if (input.tagName === 'SELECT') {
                        input.selectedIndex = 0;
                    }
                });
            }

            // Form validation
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');

            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            if (password && confirmPassword) {
                password.addEventListener('change', validatePasswords);
                confirmPassword.addEventListener('keyup', validatePasswords);
            }

            // Clear form after successful submission
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    form.reset();
                }, 100);
            });
        });

        // Prevent back button cache
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        // Clear form when page is about to unload
        window.addEventListener('beforeunload', function() {
            const form = document.getElementById('adminRegistrationForm');
            if (form) {
                form.reset();
            }
        });
    </script>
    
    <script src="adminRegister.js"></script>
</body>
</html>