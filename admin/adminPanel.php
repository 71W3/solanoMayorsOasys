<?php
include "connect.php";
include "function.php";
include "sidebar.php";

// Fetch data for the stats cards
$totalAppointments = $con->query("SELECT COUNT(*) FROM appointments")->fetch_row()[0];
$completedAppointments = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'completed'")->fetch_row()[0];
$pendingAppointments = $con->query("SELECT COUNT(*) FROM appointments WHERE status_enum = 'pending'")->fetch_row()[0];
$registeredUsers = $con->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

// MODIFIED: Fetch only today's APPROVED appointments
$today = date('Y-m-d');
$todayAppointments = $con->query("
    SELECT a.id, u.name AS resident_name, a.purpose, a.time, a.date, a.status_enum as status
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = '{$today}' AND a.status_enum = 'approved'
    ORDER BY a.time ASC
");

// Fetch upcoming mayor's appointments
$mayorsAppointments = $con->query("SELECT * FROM mayors_appointment WHERE date >= CURDATE() ORDER BY date ASC, time ASC LIMIT 5");

// // Fetch recent activity
// $recentActivity = $con->query("
//     SELECT a.purpose, u.name AS resident_name, a.status_enum as status, a.updated_at as last_updated
//     FROM appointments a
//     JOIN users u ON a.user_id = u.id
//     ORDER BY a.updated_at DESC
//     LIMIT 5
// ");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SOLAR Appointment System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
        /* Content Area */
        .content {
            padding: 30px;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 25px;
            padding-bottom: 15px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--solano-orange);
            border-radius: 3px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.8rem;
            background: rgba(0, 85, 164, 0.1);
            color: var(--solano-blue);
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--solano-blue);
        }
        
        .stat-title {
            font-size: 0.95rem;
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .stat-change {
            font-size: 0.85rem;
            color: #28a745;
            display: flex;
            align-items: center;
        }
        
        /* Charts */
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-container {
            height: 350px;
            position: relative;
        }
        
        .chart-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            flex-direction: column;
        }
        
        .chart-placeholder i {
            font-size: 3rem;
            color: rgba(0, 85, 164, 0.2);
            margin-bottom: 15px;
        }
        
        /* Recent Activity */
        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }
        
        .activity-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            background: rgba(0, 85, 164, 0.1);
            color: var(--solano-blue);
            font-size: 1.2rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .activity-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-confirmed {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
        }
        
        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        /* Recent Appointments */
        .appointments-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .table th {
            background: var(--solano-blue);
            color: white;
            font-weight: 500;
            padding: 15px 20px;
        }
        
        .table td {
            padding: 12px 20px;
            vertical-align: middle;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 85, 164, 0.03);
        }
        
        .user-avatar-sm {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--solano-blue) 0%, var(--solano-orange) 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        
        /* Footer */
        .footer {
            background: white;
            padding: 20px 30px;
            border-top: 1px solid #eee;
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .main-content.active {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        
        <div class="main-content">
            <div class="content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">Appointment Dashboard</h2>
                        <p class="text-muted mb-0">Manage and track all appointments</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $totalAppointments ?></div>
                            <div class="stat-title">Total Appointments</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $completedAppointments ?></div>
                            <div class="stat-title">Completed</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $pendingAppointments ?></div>
                            <div class="stat-title">Pending</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 107, 53, 0.1); color: var(--solano-orange);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $registeredUsers ?></div>
                            <div class="stat-title">Registered Users</div>
                        </div>
                    </div>
                </div>

                <div class="chart-card mb-4">
                    <div class="chart-header">
                        <h4 class="section-title">Appointments Overview</h4>
                        <!-- <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary active">Week</button>
                            <button class="btn btn-sm btn-outline-primary">Month</button>
                            <button class="btn btn-sm btn-outline-primary">Quarter</button>
                        </div> -->
                    </div>
                    <div class="chart-container">
                        <canvas id="appointmentsChart" height="100"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="appointments-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="section-title mb-0">Today's Approved Appointments</h4>
                                <div><?= date('F j, Y') ?></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Resident</th>
                                            <th>Purpose</th>
                                            <th>Time</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($todayAppointments->num_rows > 0): ?>
                                            <?php while ($row = $todayAppointments->fetch_assoc()): ?>
                                                <tr>
                                                    <td>#APT-<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar-sm">
                                                                <?= strtoupper(substr($row['resident_name'], 0, 1)) ?>
                                                            </div>
                                                            <?= htmlspecialchars($row['resident_name']) ?>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                                                    <td><?= date('g:i A', strtotime($row['time'])) ?></td>
                                                    <td><?= date('m/d/Y', strtotime($row['date'])) ?></td>
                                                    <td>
                                                        <?php
                                                            $status = strtolower($row['status']);
                                                            $badgeClass = match($status) {
                                                                'pending' => 'status-pending',
                                                                'approved' => 'status-confirmed',
                                                                'completed' => 'status-completed',
                                                                'cancelled' => 'status-cancelled',
                                                                default => '',
                                                            };
                                                        ?>
                                                        <span class="status-badge <?= $badgeClass ?>">
                                                            <?= ucfirst($status) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No approved appointments for today.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="appointments-card mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="section-title mb-0">Upcoming Mayor's Appointments</h4>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Date & Time</th>
                                            <th>Location</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($mayorsAppointments->num_rows > 0): ?>
                                            <?php while ($row = $mayorsAppointments->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['appointment_title']) ?></td>
                                                    <td>
                                                        <?= date('M j, Y', strtotime($row['date'])) ?><br>
                                                        <?= date('g:i A', strtotime($row['time'])) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['place']) ?></td>
                                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No upcoming mayor's appointments.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- <div class="col-lg-4">
                        <div class="activity-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="section-title mb-0">Recent Activity</h4>
                                <a href="activity_log.php" class="btn btn-sm btn-link">View All</a>
                            </div>
                            <div class="activity-list">
                                <?php if ($recentActivity && $recentActivity->num_rows > 0): ?>
                                    <?php while ($row = $recentActivity->fetch_assoc()): ?>
                                        <?php
                                            $status = strtolower($row['status']);
                                            $icon = match($status) {
                                                'completed' => 'bi-check-circle',
                                                'approved' => 'bi-person-check',
                                                'pending' => 'bi-hourglass-split',
                                                'cancelled' => 'bi-x-circle',
                                                default => 'bi-info-circle',
                                            };
                                            // MODIFIED: Assumed time_elapsed_string function exists in function.php
                                            $timeAgo = time_elapsed_string($row['last_updated']);
                                        ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <i class="bi <?= $icon ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-title"><?= ucfirst($status) ?> Appointment</div>
                                                <div class="activity-info">
                                                    <?= htmlspecialchars($row['purpose']) ?> for <?= htmlspecialchars($row['resident_name']) ?>
                                                </div>
                                                <div class="activity-time"><?= $timeAgo ?></div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center">No recent activity.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div> -->
                </div>
            </div>

            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            &copy; <?= date('Y') ?> SOLAR Appointment System - Solano Municipality. All Rights Reserved.
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="me-3">v2.5.1</span>
                            <span>Last updated: <?= date('M j, Y') ?></span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.toggle-btn').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });
        
        // Add active class to buttons
        const buttons = document.querySelectorAll('.btn-group .btn');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                buttons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Here you would typically reload the chart data based on the selected timeframe
                // For now, we'll just log the selection
                console.log('Selected timeframe:', this.textContent.trim());
            });
        });
    </script>

    <script>
        // Pass PHP variables to JS
        const totalAppointments = <?= $totalAppointments ?>;
        const completedAppointments = <?= $completedAppointments ?>;
        const pendingAppointments = <?= $pendingAppointments ?>;

        const ctx = document.getElementById('appointmentsChart').getContext('2d');

        const appointmentsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Appointments', 'Completed', 'Pending'],
                datasets: [{
                    label: 'Appointments',
                    data: [totalAppointments, completedAppointments, pendingAppointments],
                    backgroundColor: [
                        '#0055a4',  // Total (Solano Blue)
                        '#28a745', // Completed (Success Green)
                        '#007bff'   // Pending (Primary Blue)
                    ],
                    borderColor: [
                        '#0055a4',
                        '#28a745',
                        '#007bff'
                    ],
                    borderWidth: 1,
                    borderRadius: 10, // MODIFIED: Added border-radius for a more modern look
                    barPercentage: 0.6,
                    categoryPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Overall Appointment Status', // MODIFIED: Added a chart title
                        font: {
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: 1,
                            color: '#6c757d'
                        },
                        grid: {
                            drawOnChartArea: true,
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#6c757d'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Function to update chart based on timeframe
        function updateChart(timeframe) {
            // You would typically make an AJAX call here to get new data
            // For now, we'll just log the selection
            console.log('Updating chart for:', timeframe);
            
            // Example of how you might update the chart
            // appointmentsChart.data.datasets[0].data = [newTotal, newCompleted, newPending];
            // appointmentsChart.update();
        }
    </script>
</body>
</html>