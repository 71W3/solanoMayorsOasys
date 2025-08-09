<?php
session_start();
include "connect.php";

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = $row['id']; // From your database

$registration_success = false;
$registration_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $name = $con->real_escape_string(trim($_POST['name']));
    $email = $con->real_escape_string(trim($_POST['email']));
    $username = $con->real_escape_string(trim($_POST['username']));
    $role = $con->real_escape_string(trim($_POST['role']));
    $password_input = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($name) || empty($email) || empty($username) || empty($role) || empty($password_input)) {
        $registration_error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registration_error = "Invalid email format!";
    } elseif ($password_input !== $confirm_password) {
        $registration_error = "Passwords do not match!";
    } elseif (strlen($password_input) < 8) {
        $registration_error = "Password must be at least 8 characters long!";
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT * FROM admin WHERE username = '$username' OR email = '$email'";
        $result = $con->query($check_sql);
        if ($result->num_rows > 0) {
            $registration_error = "Username or email already exists!";
        } else {
            // Hash password
            $password = password_hash($password_input, PASSWORD_DEFAULT);
            // Insert admin into database
            $insert_sql = "INSERT INTO admin (name, email, username, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $con->prepare($insert_sql);
            $stmt->bind_param("sssss", $name, $email, $username, $password, $role);
            if ($stmt->execute()) {
                $registration_success = true;
            } else {
                $registration_error = "Error: " . $con->error;
            }
            $stmt->close();
        }
    }
}
$role = isset($_POST['role']) ? $_POST['role'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register - SOLAR Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-person-plus"></i> Admin Register</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($registration_success): ?>
                            <div class="alert alert-success">Admin registered successfully!</div>
                        <?php elseif ($registration_error): ?>
                            <div class="alert alert-danger"><?php echo $registration_error; ?></div>
                        <?php endif; ?>
                        <form method="post" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select role</option>
                                    <option value="Admin" <?php if($role == "Admin") echo "selected"; ?>>Admin</option>
                                    <option value="Mayor" <?php if($role == "Mayor") echo "selected"; ?>>Mayor</option>
                                    <option value="SuperAdmin" <?php if($role == "SuperAdmin") echo "selected"; ?>>SuperAdmin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="8">
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="register" class="btn btn-primary">Register</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 