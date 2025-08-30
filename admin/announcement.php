<?php
session_start();
require_once 'connect.php';
require_once 'function.php';

// Get admin info from session or database
$admin_name = "Admin";
$admin_role = "Administrator";

// Check for admin_id (from admin login) or user_id (from user login for admin users)
$admin_id = null;
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'mayor'])) {
    $admin_id = $_SESSION['user_id'];
}

if ($admin_id) {
    $stmt = $con->prepare("SELECT name, role FROM users WHERE id = ? AND role IN ('admin', 'mayor')");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($admin = $result->fetch_assoc()) {
        $admin_name = $admin['name'];
        $admin_role = ucfirst($admin['role']);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $image = $_FILES['image'];
        
        if (add_announcement($title, $description, $image)) {
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Announcement added successfully!'];
        } else {
            $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to add announcement.'];
        }
        header("Location: announcement.php");
        exit();
    }
    
    if (isset($_POST['update_announcement'])) {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $image = $_FILES['image'];
        
        if (update_announcement($id, $title, $description, $image)) {
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Announcement updated successfully!'];
        } else {
            $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to update announcement.'];
        }
        header("Location: announcement.php");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    if (delete_announcement($id)) {
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'Announcement deleted successfully!'];
    } else {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to delete announcement.'];
    }
    header("Location: announcement.php");
    exit();
}

$announcements = get_all_announcements();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - SOLAR Appointment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #64748b;
            --accent: #2563eb;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --light: #f8fafc;
            --lighter: #f1f5f9;
            --dark: #0f172a;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--lighter);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--primary);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-header .logo-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .sidebar-header h5 {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }

        .sidebar-header .version {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            background: transparent;
            position: relative;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: var(--lighter);
            color: var(--accent);
        }

        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--accent);
        }

        .sidebar-nav a i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            min-height: 100vh;
            width: calc(100% - 260px);
        }

        .topbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 500;
        }

        .content {
            padding: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: white;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 1.5rem 0;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--accent);
            border-radius: 3px;
        }

        .btn {
            border-radius: var(--radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #b45309;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border);
            color: var(--text-secondary);
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--light);
            border-color: var(--secondary);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }

        .table {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin: 0;
        }

        .table thead th {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
            padding: 1rem 0.75rem;
            border-top: none;
        }

        .table tbody td {
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--lighter);
        }

        .table-responsive {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
        }

        .form-control,
        .form-select {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .img-thumbnail {
            max-width: 80px;
            max-height: 80px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .alert {
            border-radius: var(--radius);
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }

        .modal-content {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            background: var(--light);
            border-top: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .topbar {
                padding: 1rem;
            }

            .content {
                padding: 1rem;
            }

            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.25rem;
                color: var(--text-primary);
                cursor: pointer;
                padding: 0.25rem;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .card-body {
                padding: 1rem;
            }
        }

        @media (min-width: 769px) {
            .mobile-menu-btn {
                display: none;
            }
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <img src="../image/logo.png" alt="Logo" style="width: 32px; height: 32px; object-fit: contain;">
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
                <a href="announcement.php" class="active">
                    <i class="bi bi-megaphone"></i>
                    Announcement
                </a>
                <a href="history.php">
                    <i class="bi bi-clock-history"></i>
                    History
                </a>
                <a href="adminRegister.php">
                    <i class="bi bi-person-plus"></i>
                    Admin Registration
                </a>
                <a href="logoutAdmin.php">
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
                            <h1 class="page-title">Announcements</h1>
                            <p class="text-muted mb-0 small d-none d-sm-block">Manage public announcements</p>
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
                <?php if (isset($_SESSION['notification'])): ?>
                    <div class="alert alert-<?= $_SESSION['notification']['type'] == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-<?= $_SESSION['notification']['type'] == 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                        <?= $_SESSION['notification']['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['notification']); ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="section-title mb-0">Manage Announcements</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                            <i class="bi bi-plus-circle me-2"></i> Add New Announcement
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($announcements && count($announcements) > 0): ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">#<?= str_pad($announcement['id'], 3, '0', STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($announcement['title']) ?></div>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($announcement['description']) ?>">
                                                    <?= htmlspecialchars($announcement['description']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($announcement['image'])): ?>
                                                    <img src="data:image/jpeg;base64,<?= base64_encode($announcement['image']) ?>" class="img-thumbnail">
                                                <?php else: ?>
                                                    <span class="text-muted">No Image</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?= $announcement['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="announcement.php?delete=<?= $announcement['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editAnnouncementModal<?= $announcement['id'] ?>" tabindex="-1" aria-labelledby="editAnnouncementModalLabel<?= $announcement['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editAnnouncementModalLabel<?= $announcement['id'] ?>">Edit Announcement</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="POST" action="announcement.php" enctype="multipart/form-data">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Title</label>
                                                                <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($announcement['title']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="3" required><?= htmlspecialchars($announcement['description']) ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Image</label>
                                                                <input type="file" class="form-control" name="image">
                                                                <?php if (!empty($announcement['image'])): ?>
                                                                    <small class="text-muted">Current image will be replaced</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_announcement" class="btn btn-primary">Save changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-megaphone fs-1 d-block mb-2 opacity-50"></i>
                                                No announcements found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAnnouncementModalLabel">Add New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="announcement.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="image">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_announcement" class="btn btn-primary">Add Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu functionality
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    document.body.style.overflow = '';
                });
            }

            // Close sidebar on window resize if desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });

            // Auto-hide notifications
            setTimeout(function() {
                var notifications = document.querySelectorAll('.alert');
                notifications.forEach(function(notification) {
                    if (notification.classList.contains('alert-dismissible')) {
                        var alert = new bootstrap.Alert(notification);
                        alert.close();
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>
