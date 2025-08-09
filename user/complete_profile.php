<?php
session_start();

// Check if user has OAuth data (Google or GitHub)
if (!isset($_SESSION['google_user_data']) && !isset($_SESSION['github_user_data'])) {
    header("Location: login.php");
    exit();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_auth_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get OAuth data (Google or GitHub)
    if (isset($_SESSION['google_user_data'])) {
        $oauth_data = $_SESSION['google_user_data'];
        $oauth_type = 'google';
    } else {
        $oauth_data = $_SESSION['github_user_data'];
        $oauth_type = 'github';
    }
    
    $email = $oauth_data['email'];
    $name = $oauth_data['name'];
    $picture = $oauth_data['picture'];
    
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $role = $conn->real_escape_string(trim($_POST['role']));
    
    // Validation
    if (empty($phone) || empty($address) || empty($role)) {
        $error = "All fields are required!";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $error = "Phone number must contain only numbers (10-15 digits)!";
    } elseif (!preg_match('/[a-zA-Z]/', $address)) {
        $error = "Address must contain letters!";
    } else {
        // Generate username from email
        $username = explode('@', $email)[0];
        
        // Check if username exists, if so, add random number
        $check_username = "SELECT * FROM users WHERE username = '$username'";
        $result = $conn->query($check_username);
        if ($result->num_rows > 0) {
            $username = $username . rand(100, 999);
        }
        
        // Insert user into database
        if ($oauth_type === 'google') {
            $insert_sql = "INSERT INTO users (name, email, username, phone, address, role, google_id, profile_picture, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssssss", $name, $email, $username, $phone, $address, $role, $email, $picture);
        } else {
            $insert_sql = "INSERT INTO users (name, email, username, phone, address, role, github_id, profile_picture, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssssss", $name, $email, $username, $phone, $address, $role, $oauth_data['github_id'], $picture);
        }
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['login_success'] = true;
            $_SESSION['user_full_name'] = $name;
            $_SESSION['user_role'] = $role;
            
            // Clear OAuth data
            if (isset($_SESSION['google_user_data'])) {
                unset($_SESSION['google_user_data']);
            }
            if (isset($_SESSION['github_user_data'])) {
                unset($_SESSION['github_user_data']);
            }
            
            $success = "Profile completed successfully!";
            
            // Redirect based on role
            if ($role === 'admin') {
                header("Location: adminDashboard.php");
            } elseif ($role === 'mayor') {
                header("Location: mayorDashboard.php");
            } else {
                header("Location: userSide.php");
            }
            exit();
        } else {
            $error = "Error creating account: " . $conn->error;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - Solano Mayor's Office Appointment System</title>
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
        
        .profile-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003a75 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .profile-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .profile-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .google-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px;
            text-align: center;
        }
        
        .google-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: block;
            border: 3px solid var(--primary-blue);
        }
        
        .profile-body {
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
        }
        
        @media (max-width: 768px) {
            .profile-container {
                margin: 10px;
                max-width: 95%;
            }
            
            .profile-header,
            .profile-body {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h1><i class="bi bi-person-plus me-3"></i>Complete Your Profile</h1>
            <p>Please provide additional information to complete your registration</p>
        </div>
        
        <div class="google-info">
            <?php 
            if (isset($_SESSION['google_user_data'])) {
                $oauth_data = $_SESSION['google_user_data'];
                $oauth_name = 'Google';
            } else {
                $oauth_data = $_SESSION['github_user_data'];
                $oauth_name = 'GitHub';
            }
            ?>
            <img src="<?php echo $oauth_data['picture']; ?>" alt="Profile" class="google-avatar">
            <h5><?php echo $oauth_data['name']; ?></h5>
            <p class="text-muted mb-0"><?php echo $oauth_data['email']; ?></p>
            <small class="text-muted">Connected via <?php echo $oauth_name; ?></small>
        </div>
        
        <div class="profile-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-4">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" placeholder="Enter your phone number" pattern="[0-9]{10,15}" title="10-15 digit phone number (numbers only)" required>
                    <small class="text-muted">Numbers only (10-15 digits)</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" placeholder="Enter your full address" required>
                    <small class="text-muted">Must contain letters (not just numbers)</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Role</label>
                    <select class="form-control" name="role" required>
                        <option value="">Select your role</option>
                        <option value="user">Regular User</option>
                        <option value="mayor">Mayor</option>
                        <option value="admin">Administrator</option>
                    </select>
                    <small class="text-muted">Choose the role that best describes your position</small>
                </div>
                
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check-circle me-2"></i>Complete Registration
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 