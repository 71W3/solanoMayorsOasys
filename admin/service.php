<?php
require_once 'connect.php';
require_once 'function.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_service'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $duration = $_POST['duration'];
        
        if (add_service($name, $description, $duration)) {
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Service added successfully!'];
        } else {
            $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to add service.'];
        }
        header("Location: service.php");
        exit();
    }
    
    if (isset($_POST['update_service'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $duration = $_POST['duration'];
        
        if (update_service($id, $name, $description, $duration)) {
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Service updated successfully!'];
        } else {
            $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to update service.'];
        }
        header("Location: service.php");
        exit();
    }
    
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        
        if (delete_service($id)) {
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Service deleted successfully!'];
        } else {
            $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to delete service.'];
        }
        header("Location: service.php");
        exit();
    }
}

// Get all services for display
$services = get_all_services();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: fadeOut 2s forwards 3s;
        }
        @keyframes fadeOut {
            to { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?> 
    <div class="container mt-5">
        <h2>Manage Services</h2>
        
        <!-- Notification Area -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="notification alert alert-<?= $_SESSION['notification']['type'] ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['notification']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
        
        <!-- Add Service Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="fas fa-plus"></i> Add New Service
        </button>
        
        <!-- Services Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Duration (mins)</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['id']) ?></td>
                        <td><?= htmlspecialchars($service['name']) ?></td>
                        <td><?= htmlspecialchars($service['description']) ?></td>
                        <td><?= htmlspecialchars($service['duration']) ?></td>
                        <td><?= htmlspecialchars($service['created_at']) ?></td>
                        <td>
                            <!-- Edit Button -->
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editServiceModal<?= $service['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <!-- Delete Button -->
                            <a href="service.php?delete=<?= $service['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this service?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    
                    <!-- Edit Service Modal for each service -->
                    <div class="modal fade" id="editServiceModal<?= $service['id'] ?>" tabindex="-1" aria-labelledby="editServiceModalLabel<?= $service['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editServiceModalLabel<?= $service['id'] ?>">Edit Service</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="service.php" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                        <div class="mb-3">
                                            <label for="name<?= $service['id'] ?>" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="name<?= $service['id'] ?>" name="name" value="<?= htmlspecialchars($service['name']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description<?= $service['id'] ?>" class="form-label">Description</label>
                                            <textarea class="form-control" id="description<?= $service['id'] ?>" name="description" rows="3" required><?= htmlspecialchars($service['description']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="duration<?= $service['id'] ?>" class="form-label">Duration (minutes)</label>
                                            <input type="number" class="form-control" id="duration<?= $service['id'] ?>" name="duration" value="<?= htmlspecialchars($service['duration']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="update_service" class="btn btn-primary">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addServiceModalLabel">Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="service.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="duration" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="duration" name="duration" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_service" class="btn btn-primary">Add Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide notifications after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var notifications = document.querySelectorAll('.notification');
                notifications.forEach(function(notification) {
                    notification.style.display = 'none';
                });
            }, 3000);
        });
    </script>
</body>
</html>