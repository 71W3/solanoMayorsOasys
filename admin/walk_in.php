<?php
include 'connect.php';

$success = false;
$success_message = '';

// Handle form submission for new walk-in
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_walk_in'])) {
    $name = $con->real_escape_string($_POST['name']);
    $address = $con->real_escape_string($_POST['address']);
    $purpose = $con->real_escape_string($_POST['purpose']);
    
    // Generate appointment number
    $last_id_query = "SELECT MAX(id) as max_id FROM walk_in";
    $result = $con->query($last_id_query);
    $row = $result->fetch_assoc();
    $next_id = $row['max_id'] + 1;
    $appointment_number = '#' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
    
    $insert_query = "INSERT INTO walk_in (appointment_number, name, address, purpose, created_at, status) 
                     VALUES ('$appointment_number', '$name', '$address', '$purpose', NOW(), 'waiting')";
    
    if ($con->query($insert_query)) {
        $success = true;
        $success_message = "Walk-in added successfully!";
    } else {
        $success = true;
        $success_message = "Error adding walk-in: " . $con->error;
    }
}

// Handle sending to queue with transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_to_queue'])) {
    if (!empty($_POST['selected_walkins'])) {
        $con->autocommit(FALSE); // Start transaction
        $success = true;
        $sent_appointments = [];
        $errors = [];
        $today = date('Y-m-d');
        
        foreach ($_POST['selected_walkins'] as $walk_in_id) {
            $walk_in_id = $con->real_escape_string($walk_in_id);
            
            // Verify walk-in exists and is waiting
            $check_query = "SELECT id, appointment_number FROM walk_in WHERE id = '$walk_in_id' AND status = 'waiting'";
            $check_result = $con->query($check_query);
            
            if ($check_result && $check_result->num_rows > 0) {
                $walk_in = $check_result->fetch_assoc();
                $sent_appointments[] = $walk_in['appointment_number'];
                
                // Insert into queue
                $insert_queue_query = "INSERT INTO queue (walk_in_id, created_at) VALUES ('$walk_in_id', NOW())";
                if (!$con->query($insert_queue_query)) {
                    $errors[] = "Failed to add to queue: " . $con->error;
                }
                
                // Update status to complete
                $update_status_query = "UPDATE walk_in SET status = 'complete' WHERE id = '$walk_in_id'";
                if (!$con->query($update_status_query)) {
                    $errors[] = "Failed to update status: " . $con->error;
                }
                
                // Add to walkin_history
                $insert_history_query = "INSERT INTO walkin_history (walk_in_id, date) VALUES ('$walk_in_id', '$today')";
                if (!$con->query($insert_history_query)) {
                    $errors[] = "Failed to add to history: " . $con->error;
                }
            } else {
                $errors[] = "Walk-in ID $walk_in_id not found or not in waiting status";
            }
        }
        
        if (empty($errors)) {
            $con->commit();
            $success_message = "Appointments sent to queue: " . implode(", ", $sent_appointments);
        } else {
            $con->rollback();
            $success_message = "Errors occurred: " . implode("; ", $errors);
        }
        
        $con->autocommit(TRUE); // End transaction
    } else {
        $success = true;
        $success_message = "Please select at least one walk-in to send to queue.";
    }
}

// Fetch all waiting walk-in appointments (only those not in queue and with waiting status)
$walk_ins_query = "SELECT * FROM walk_in WHERE id NOT IN (SELECT walk_in_id FROM queue) AND status = 'waiting' ORDER BY created_at ASC";
$walk_ins_result = $con->query($walk_ins_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .selected-row {
            background-color: #e7f1ff !important;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .sidebar {
            min-height: 100vh;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>
<div class="container-fluid">
    <div class="row">
        
        
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert" id="success-alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <h1 class="mb-4 mt-3">Walk-in Appointments</h1>

            <!-- Button to trigger modal -->
            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addWalkInModal">
                <i class="bi bi-plus-circle"></i> Add Walk-in Appointment
            </button>

            <!-- Walk-in List -->
            <form method="post" action="walk_in.php">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50px">Select</th>
                                <th>Appointment #</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Purpose</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($walk_ins_result->num_rows > 0): ?>
                                <?php while($walk_in = $walk_ins_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input row-checkbox" type="checkbox" 
                                                   name="selected_walkins[]" value="<?= $walk_in['id'] ?>">
                                        </div>
                                    </td>
                                    <td><?= $walk_in['appointment_number'] ?></td>
                                    <td><?= $walk_in['name'] ?></td>
                                    <td><?= $walk_in['address'] ?></td>
                                    <td><?= $walk_in['purpose'] ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($walk_in['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No walk-in appointments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($walk_ins_result->num_rows > 0): ?>
                <button type="submit" name="send_to_queue" class="btn btn-success mt-3">
                    <i class="bi bi-send"></i> Send Selected to Queue
                </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Add Walk-in Modal -->
<div class="modal fade" id="addWalkInModal" tabindex="-1" aria-labelledby="addWalkInModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="walk_in.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addWalkInModalLabel">Add New Walk-in</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Barangay</label>
                        <select class="form-select" id="address" name="address" required>
                            <option value="" selected disabled>Select Barangay</option>
                            <option value="Aggub">Aggub</option>
                            <option value="Dadap">Dadap</option>
                            <option value="Roxas">Roxas</option>
                            <option value="Bagahabag">Bagahabag</option>
                            <option value="Lactawan">Lactawan</option>
                            <option value="San Juan">San Juan</option>
                            <option value="Bangaan">Bangaan</option>
                            <option value="Osmeña">Osmeña</option>
                            <option value="San Luis">San Luis</option>
                            <option value="Bangar">Bangar</option>
                            <option value="Quezon">Quezon</option>
                            <option value="Tucal">Tucal</option>
                            <option value="Bascaran">Bascaran</option>
                            <option value="PD Galima">PD Galima</option>
                            <option value="Uddiawan">Uddiawan</option>
                            <option value="Communal">Communal</option>
                            <option value="Poblacion North">Poblacion North</option>
                            <option value="Wacal">Wacal</option>
                            <option value="Concepcion">Concepcion</option>
                            <option value="Poblacion South">Poblacion South</option>
                            <option value="Curifang">Curifang</option>
                            <option value="Quirino">Quirino</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_walk_in" class="btn btn-primary">Add Walk-in</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Highlight selected rows
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
        });
    });

    // Auto-dismiss success alert after 5 seconds
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(successAlert);
            alert.close();
        }, 5000);
    }
    
    // Select all checkboxes
    function selectAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
            const row = checkbox.closest('tr');
            if (source.checked) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
        });
    }
</script>
</body>
</html>