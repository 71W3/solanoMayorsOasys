<?php
require_once 'connect.php';
require_once 'function.php';

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
    <title>Manage Announcements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .img-thumbnail {
            max-width: 100px;
            max-height: 100px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container mt-5">
        <h2>Manage Announcements</h2>
        
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="notification alert alert-<?= $_SESSION['notification']['type'] ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['notification']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
        
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
            <i class="fas fa-plus"></i> Add New Announcement
        </button>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
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
                    <?php foreach ($announcements as $announcement): ?>
                    <tr>
                        <td><?= htmlspecialchars($announcement['id']) ?></td>
                        <td><?= htmlspecialchars($announcement['title']) ?></td>
                        <td><?= htmlspecialchars($announcement['description']) ?></td>
                        <td>
                            <?php if (!empty($announcement['image'])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($announcement['image']) ?>" class="img-thumbnail">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?= $announcement['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="announcement.php?delete=<?= $announcement['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                <i class="fas fa-trash"></i>
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
                </tbody>
            </table>
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
        // Auto-hide notifications
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