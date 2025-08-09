<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Appointment System - Mayor's Office</title>
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
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--primary-dark);
            background-color: #f5f7fa;
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }
        
        /* Layout */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-blue) 0%, #003a75 100%);
            color: white;
            position: fixed;
            height: 100vh;
            z-index: 100;
            transition: all 0.3s;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
        }
        
        .sidebar-brand {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
        }
        
        .sidebar-brand i {
            font-size: 2rem;
            color: white;
            margin-right: 10px;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 25px;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 10px solid var(--primary-green);
            transition: 0.05s ease
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            font-size: 1.8rem;
            width: 25px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }
        
        /* Topbar */
        .topbar {
            background: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 99;
            text-align: center;
        }
        
        .toggle-btn {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-dark);
            cursor: pointer;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-green) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .user-info {
            line-height: 1.3;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .user-role {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
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
            background: var(--primary-green);
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
            color: var(--primary-blue);
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-blue);
        }
        
        .stat-title {
            font-size: 0.95rem;
            color: #6c757d;
            margin-bottom: 0;
        }
        
        /* Calendar */
        .calendar-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            padding: 10px;
            color: var(--primary-blue);
        }
        
        .calendar-day {
            height: 100px;
            border: 1px solid #eee;
            padding: 5px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .calendar-day:hover {
            background-color: rgba(0, 85, 164, 0.05);
        }
        
        .day-number {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .event-indicator {
            position: absolute;
            bottom: 5px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.7rem;
        }
        
        .event-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary-green);
            margin: 0 1px;
        }
        
        .appointment-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .appointment-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .appointment-item:hover {
            background-color: rgba(0, 85, 164, 0.05);
        }
        
        .appointment-time {
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .appointment-service {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .appointment-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
        }
        
        .status-confirmed {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-cancelled {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .appointment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .btn-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="bi bi-calendar-check"></i>
                <h4 class="mb-0">Appointment System</h4>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li>
                        <a href="#" class="active">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="bi bi-calendar-plus"></i>
                            <span>New Appointment</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="bi bi-calendar-week"></i>
                            <span>Calendar View</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="#">
                            <i class="bi bi-gear"></i>
                            <span>Services</span>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="bi bi-clock-history"></i>
                            <span>Appointment History</span>
                        </a>
                    </li>
                  
                    <li>
                        <a href="#">
                            <i class="bi bi-person-gear"></i>
                            <span>Staff</span>
                        </a>
                    </li>
                    
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <button class="toggle-btn d-md-none">
                    <i class="bi bi-list"></i>
                </button>
                <div class="d-flex align-items-center justify-content-center">
                    <h4 class="mb-0">Mayor's Office Appointment System</h4>
                </div>
                <div class="user-menu">
                    <div class="user-profile">
                        <div class="user-avatar">JD</div>
                        <div class="user-info">
                            <div class="user-name">Juan Dela Cruz</div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">Appointment Dashboard</h2>
                        <p class="text-muted mb-0">Manage appointments and schedules</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" id="newAppointmentBtn">
                            <i class="bi bi-plus-circle me-2"></i>New Appointment
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">142</div>
                            <div class="stat-title">Today's Appointments</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">89</div>
                            <div class="stat-title">Completed Today</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">48</div>
                            <div class="stat-title">Pending Approval</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(0, 85, 164, 0.1); color: var(--primary-blue);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">5,872</div>
                            <div class="stat-title">Registered Residents</div>
                        </div>
                    </div>
                </div>

                <!-- Calendar & Upcoming Appointments -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h4 class="section-title">Appointment Calendar</h4>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary me-2"><i class="bi bi-chevron-left"></i></button>
                                    <span class="fw-bold">July 2023</span>
                                    <button class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-chevron-right"></i></button>
                                </div>
                            </div>
                            <div class="calendar-grid">
                                <div class="calendar-day-header">Sun</div>
                                <div class="calendar-day-header">Mon</div>
                                <div class="calendar-day-header">Tue</div>
                                <div class="calendar-day-header">Wed</div>
                                <div class="calendar-day-header">Thu</div>
                                <div class="calendar-day-header">Fri</div>
                                <div class="calendar-day-header">Sat</div>
                                
                                <!-- Calendar days -->
                                <div class="calendar-day">
                                    <div class="day-number">25</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">26</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">27</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">28</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">29</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">30</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">1</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">2</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">3</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">4</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">5</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">6</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">7</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">8</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">9</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">10</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">11</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">12</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">13</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">14</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">15</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">16</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">17</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">18</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">19</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">20</div>
                                    <div class="event-indicator">
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                        <span class="event-dot"></span>
                                    </div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">21</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">22</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">23</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">24</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">25</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">26</div>
                                </div>
                                <div class="calendar-day">
                                    <div class="day-number">27</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="calendar-container">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="section-title mb-0">Upcoming Appointments</h4>
                                <a href="#" class="btn btn-sm btn-link">View All</a>
                            </div>
                            <div class="appointment-list">
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="appointment-time">09:00 AM</div>
                                        <span class="appointment-status status-confirmed">Confirmed</span>
                                    </div>
                                    <div class="appointment-service">Marriage License Application</div>
                                    <div class="mt-2">
                                        <i class="bi bi-person me-1"></i>Maria Santos
                                    </div>
                                </div>
                                
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="appointment-time">10:30 AM</div>
                                        <span class="appointment-status status-confirmed">Confirmed</span>
                                    </div>
                                    <div class="appointment-service">Business Permit Renewal</div>
                                    <div class="mt-2">
                                        <i class="bi bi-person me-1"></i>Roberto Javier
                                    </div>
                                </div>
                                

                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="appointment-time">11:15 AM</div>
                                        <span class="appointment-status status-pending">Pending</span>
                                    </div>
                                    <div class="appointment-service">Community Meeting</div>
                                    <div class="mt-2">
                                        <i class="bi bi-person me-1"></i>Barangay Council
                                    </div>
                                </div>
                                
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="appointment-time">01:30 PM</div>
                                        <span class="appointment-status status-confirmed">Confirmed</span>
                                    </div>
                                    <div class="appointment-service">Building Permit Consultation</div>
                                    <div class="mt-2">
                                        <i class="bi bi-person me-1"></i>Carlos Reyes
                                    </div>
                                </div>
                                
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="appointment-time">02:45 PM</div>
                                        <span class="appointment-status status-confirmed">Confirmed</span>
                                    </div>
                                    <div class="appointment-service">Tax Payment</div>
                                    <div class="mt-2">
                                        <i class="bi bi-person me-1"></i>Sofia Garcia
                                    </div>
                                </div>
                                
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="appointment-time">03:30 PM</div>
                                        <span class="appointment-status status-pending">Pending</span>
                                    </div>
                                    <div class="appointment-service">Public Hearing</div>
                                    <div class="mt-2">
                                        <i class="bi bi-people me-1"></i>Community Group
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointment Form Modal -->
                <div class="appointment-modal" id="appointmentModal">
                    <div class="modal-content">
                        <span class="btn-close" id="closeModal">&times;</span>
                        <h4 class="mb-4">Schedule New Appointment</h4>
                        
                        <form id="appointmentForm">
                            <div class="mb-3">
                                <label class="form-label">Resident</label>
                                <select class="form-select">
                                    <option selected>Select resident</option>
                                    <option>Maria Santos</option>
                                    <option>Roberto Javier</option>
                                    <option>Carlos Reyes</option>
                                    <option>Sofia Garcia</option>
                                    <option>New Resident</option>
                                </select>
                            </div>
                            

                            <div class="mb-3">
                                <label class="form-label">Service Type</label>
                                <select class="form-select">
                                    <option selected>Select service</option>
                                    <option>Marriage License</option>
                                    <option>Business Permit</option>
                                    <option>Building Permit</option>
                                    <option>Tax Payment</option>
                                    <option>Public Hearing</option>
                                    <option>Community Meeting</option>
                                    <option>Complaint</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Time</label>
                                    <input type="time" class="form-control">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Staff Member</label>
                                <select class="form-select">
                                    <option selected>Select staff</option>
                                    <option>Mayor's Office</option>
                                    <option>Permits Office</option>
                                    <option>Tax Office</option>
                                    <option>Public Affairs</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" rows="3" placeholder="Add any additional information"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            &copy; 2023 Mayor's Office Appointment System. All Rights Reserved.
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="me-3">v1.2.0</span>
                            <span>Last updated: Jul 10, 2023</span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.toggle-btn').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        });
        
        // Appointment modal functionality
        const modal = document.getElementById('appointmentModal');
        const openBtn = document.getElementById('newAppointmentBtn');
        const closeBtn = document.getElementById('closeModal');
        
        openBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
        });
        
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Form submission
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const toast = new bootstrap.Toast(document.getElementById('successToast'));
            toast.show();
            modal.style.display = 'none';
        });
        
        // Calendar day click
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.addEventListener('click', function() {
                alert('View appointments for selected date');
            });
        });
    </script>

    <!-- Toast Notification -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
      <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            Appointment scheduled successfully!
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
</body>
</html>