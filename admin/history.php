<?php
session_start();
include 'connect.php';

// Initialize variables for filtering
$history_type = isset($_GET['history_type']) ? $_GET['history_type'] : 'all';
$time_range = isset($_GET['time_range']) ? $_GET['time_range'] : 'today';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build WHERE clauses for filtering
$where_online = "WHERE a.status_enum = 'completed'"; // Modified to only show completed appointments
$where_walkin = "WHERE 1=1";

// Apply time range filters
switch ($time_range) {
    case 'today':
        $where_online .= " AND DATE(a.date) = CURDATE()";
        $where_walkin .= " AND DATE(w.created_at) = CURDATE()";
        break;
    case 'week':
        $where_online .= " AND YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1)";
        $where_walkin .= " AND YEARWEEK(w.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $where_online .= " AND YEAR(a.date) = YEAR(CURDATE()) AND MONTH(a.date) = MONTH(CURDATE())";
        $where_walkin .= " AND YEAR(w.created_at) = YEAR(CURDATE()) AND MONTH(w.created_at) = MONTH(CURDATE())";
        break;
    case 'year':
        $where_online .= " AND YEAR(a.date) = YEAR(CURDATE())";
        $where_walkin .= " AND YEAR(w.created_at) = YEAR(CURDATE())";
        break;
    case 'custom':
        if (!empty($start_date)) {
            $where_online .= " AND DATE(a.date) >= '$start_date'";
            $where_walkin .= " AND DATE(w.created_at) >= '$start_date'";
        }
        if (!empty($end_date)) {
            $where_online .= " AND DATE(a.date) <= '$end_date'";
            $where_walkin .= " AND DATE(w.created_at) <= '$end_date'";
        }
        break;
}

// Apply history type filter
if ($history_type == 'online') {
    $where_walkin .= " AND 1=0"; // Exclude walk-ins
} elseif ($history_type == 'walkin') {
    $where_online .= " AND 1=0"; // Exclude online
}

// Fetch online appointment history with simplified query
$online_query = "SELECT u.name, a.date, a.time, 
                        CASE 
                            WHEN a.purpose LIKE '%other%' THEN a.other_details
                            ELSE a.purpose
                        END as display_purpose
                 FROM appointments a
                 JOIN users u ON a.user_id = u.id
                 $where_online
                 ORDER BY a.date DESC, a.time DESC";
$online_result = mysqli_query($con, $online_query);

// Fetch walk-in appointment history with simplified query
// Changed w.created_at to just created_at since we're not joining tables
$walkin_query = "SELECT name, DATE(created_at) as date, TIME(created_at) as time, purpose
                 FROM walk_in w
                 $where_walkin
                 ORDER BY created_at DESC";
$walkin_result = mysqli_query($con, $walkin_query);

// Count total records
$total_online = mysqli_num_rows($online_result);
$total_walkin = mysqli_num_rows($walkin_result);
$total_records = $total_online + $total_walkin;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History | SOLAR Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --solano-blue: #0055a4;
            --solano-gold: #ffd700;
            --solano-orange: #ff6b35;
            --solano-light: #f8f9fa;
            --solano-dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .filter-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--solano-blue);
        }
        
        .nav-pills .nav-link {
            color: var(--solano-dark);
        }
        
        .badge-online {
            background-color: #28a745;
        }
        
        .badge-walkin {
            background-color: var(--solano-orange);
        }
        
        .history-table th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        
        .dataTables_filter input {
            border-radius: 20px;
            padding: 5px 15px;
            border: 1px solid #dee2e6;
        }
        
        .dataTables_length select {
            border-radius: 5px;
            padding: 5px;
            border: 1px solid #dee2e6;
        }
        
        .btn-filter {
            background-color: var(--solano-blue);
            color: white;
            border-radius: 5px;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-filter:hover {
            background-color: #003a75;
            color: white;
        }
        
        .btn-export {
            background-color: var(--solano-gold);
            color: var(--solano-dark);
            border-radius: 5px;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-export:hover {
            background-color: #e6c200;
            color: var(--solano-dark);
        }
        
        .tab-content {
            padding: 0;
        }
        @media print {
        body * {
            visibility: hidden;
        }
        .main-content, .main-content * {
            visibility: visible;
        }
        .main-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .filter-section, .nav-pills, .card-header {
            display: none;
        }
        table {
            width: 100% !important;
        }
        .history-table th {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
    }
    </style>
</head>
<body>
            <?php include 'sidebar.php'; ?>
    <div class="wrapper">
        <div class="main-content">

            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="fw-bold mb-0">Appointment History</h2>
                    </div>
                </div>

                <form method="GET" action="history.php">
                    <div class="filter-section">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="historyType" class="form-label fw-bold">History Type</label>
                                <select class="form-select" id="historyType" name="history_type">
                                    <option value="all" <?= $history_type == 'all' ? 'selected' : '' ?>>All History</option>
                                    <option value="online" <?= $history_type == 'online' ? 'selected' : '' ?>>Online Appointments</option>
                                    <option value="walkin" <?= $history_type == 'walkin' ? 'selected' : '' ?>>Walk-In Appointments</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="timeRange" class="form-label fw-bold">Time Range</label>
                                <select class="form-select" id="timeRange" name="time_range">
                                    <option value="today" <?= $time_range == 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="week" <?= $time_range == 'week' ? 'selected' : '' ?>>This Week</option>
                                    <option value="month" <?= $time_range == 'month' ? 'selected' : '' ?>>This Month</option>
                                    <option value="year" <?= $time_range == 'year' ? 'selected' : '' ?>>This Year</option>
                                    <option value="custom" <?= $time_range == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-filter me-2">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-filter me-2" onclick="printRecords()">
                                    <i class="bi bi-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mt-3" id="customDateRange" style="display: <?= $time_range == 'custom' ? 'block' : 'none' ?>;">
                            <div class="col-md-6 mb-3">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" name="start_date" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" name="end_date" value="<?= $end_date ?>">
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Appointment Records</h5>
                                <span class="badge bg-primary">Total: <?= $total_records ?></span>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-pills mb-4" id="history-tab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="online-tab" data-bs-toggle="pill" data-bs-target="#online-history" type="button" role="tab">
                                            Online Appointments <span class="badge bg-primary ms-1"><?= $total_online ?></span>
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="walkin-tab" data-bs-toggle="pill" data-bs-target="#walkin-history" type="button" role="tab">
                                            Walk-In Appointments <span class="badge bg-primary ms-1"><?= $total_walkin ?></span>
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="history-tabContent">
                                    <div class="tab-pane fade show active" id="online-history" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover history-table" id="onlineTable">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Date</th>
                                                        <th>Time</th>
                                                        <th>Purpose</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($row = mysqli_fetch_assoc($online_result)): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                                        <td><?= date('h:i A', strtotime($row['time'])) ?></td>
                                                        <td><?= htmlspecialchars($row['display_purpose']) ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    
                                    <div class="tab-pane fade" id="walkin-history" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover history-table" id="walkinTable">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Date</th>
                                                        <th>Time</th>
                                                        <th>Purpose</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($row = mysqli_fetch_assoc($walkin_result)): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                                        <td><?= date('h:i A', strtotime($row['time'])) ?></td>
                                                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalDetailsContent">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#onlineTable').DataTable();
            $('#walkinTable').DataTable();
            
            // Show/hide custom date range
            $('#timeRange').change(function() {
                if ($(this).val() === 'custom') {
                    $('#customDateRange').show();
                } else {
                    $('#customDateRange').hide();
                }
            });
            
            // View details button click handler
            $('.view-details').click(function() {
                const id = $(this).data('id');
                const type = $(this).data('type');
                
                // Show loading spinner
                $('#modalDetailsContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                
                // Load details via AJAX
                $.ajax({
                    url: 'get_appointment_details.php',
                    type: 'GET',
                    data: { id: id, type: type },
                    success: function(response) {
                        $('#modalDetailsContent').html(response);
                    },
                    error: function() {
                        $('#modalDetailsContent').html('<div class="alert alert-danger">Failed to load details. Please try again.</div>');
                    }
                });
                
                // Show modal
                $('#detailsModal').modal('show');
            });
            
            // Export button functionality
            $('#exportBtn').click(function() {
                // Get current filter values
                const historyType = $('#historyType').val();
                const timeRange = $('#timeRange').val();
                const startDate = $('#startDate').val();
                const endDate = $('#endDate').val();
                
                // Build export URL
                let exportUrl = 'export_history.php?history_type=' + historyType + '&time_range=' + timeRange;
                if (timeRange === 'custom') {
                    exportUrl += '&start_date=' + startDate + '&end_date=' + endDate;
                }
                
                // Open export URL in new tab
                window.open(exportUrl, '_blank');
            });
        });

        function printRecords() {
        // Get the active tab content
        const activeTab = document.querySelector('.tab-pane.active');
        
        // Create a print window
        const printWindow = window.open('', '_blank');
        
        // Get the current date for the report title
        const today = new Date();
        const dateString = today.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Build the HTML content for printing
        let printContent = `
            <html>
                <head>
                    <title>Appointment History Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .print-title {
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        .print-date {
                            font-size: 14px;
                            color: #666;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                        }
                        th {
                            background-color: #f2f2f2;
                            text-align: left;
                            padding: 8px;
                            border: 1px solid #ddd;
                        }
                        td {
                            padding: 8px;
                            border: 1px solid #ddd;
                        }
                        .total-records {
                            margin-top: 20px;
                            font-weight: bold;
                        }
                        @page {
                            size: auto;
                            margin: 10mm;
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <div class="print-title">Appointment History Report</div>
                        <div class="print-date">Generated on: ${dateString}</div>
                    </div>
        `;
        
        // Add the active tab's content
        const table = activeTab.querySelector('table');
        const tableClone = table.cloneNode(true);
        
        // Remove DataTables classes and styles
        tableClone.classList.remove('dataTable');
        tableClone.querySelectorAll('*').forEach(el => {
            el.removeAttribute('style');
            el.classList.remove('sorting', 'sorting_asc', 'sorting_desc');
        });
        
        printContent += tableClone.outerHTML;
        
        // Add total records
        const totalRecords = document.querySelector('.card-header .badge').textContent;
        printContent += `
            <div class="total-records">${totalRecords} records found</div>
            </body>
            </html>
        `;
        
        // Write and print the content
        printWindow.document.open();
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load before printing
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    }
    </script>
</body>
</html>