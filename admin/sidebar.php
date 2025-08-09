<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --solano-blue: #0055a4;
            --solano-gold: #ffd700;
            --solano-orange: #ff6b35;
            --solano-light: #f8f9fa;
            --solano-dark: #212529;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--solano-dark);
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
            background: linear-gradient(180deg, var(--solano-blue) 0%, #003a75 100%);
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
            position: relative;
        }
        
        .sidebar-brand i {
            font-size: 2rem;
            color: var(--solano-gold);
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
            margin-bottom: 5px;
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
            border-left: 4px solid var(--solano-orange);
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            font-size: 1.2rem;
            width: 25px;
            text-align: center;
        }
        
        .menu-badge {
            margin-left: auto;
            background: var(--solano-orange);
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
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
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 99;
        }
        
        .toggle-btn {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            color: var(--solano-dark);
            cursor: pointer;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .notification {
            position: relative;
            margin-right: 20px;
            font-size: 1.3rem;
            color: var(--solano-dark);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--solano-orange);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: linear-gradient(135deg, var(--solano-blue) 0%, var(--solano-orange) 100%);
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
        /* Sidebar Brand - Fixed Alignment and Sizing */
.sidebar-brand {
    padding: 20px 15px; /* MODIFIED: Set a fixed padding */
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center; /* MODIFIED: Vertically align logo */
}

/* MODIFIED: Add a specific style for the logo image */
.sidebar-brand img {
    max-width: 100%; /* Ensure the logo scales within its container */
    height: auto;
    display: block; /* Removes any extra space below the image */
}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
      <div class="sidebar">
        <!-- Sidebar Brand - Fixed Alignment -->
        <div class="sidebar-brand">
            <img src="../image/logo.png" alt="">
        </div>
        
        <!-- Sidebar Menu with Added Announcement Item -->
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="adminPanel.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="appointment.php">
                        <i class="bi bi-calendar-plus"></i>
                        <span>Appointments</span>
                    </a>
                </li>
                <li>
                    <a href="schedule.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Schedules</span>
                    </a>
                </li>
                <li>
                    <a href="walk_in.php">
                        <i class="bi bi-journal-text"></i>
                        <span>Walk-In</span>
                    </a>
                </li>
                <li>
                    <a href="announcement.php">
                        <i class="bi bi-megaphone"></i>
                        <span>Announcements</span>
                    </a>
                </li>
                <li>
                    <a href="history.php">
                        <i class="bi bi-clock-history"></i>
                        <span>History</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="adminRegister.php">
                        <i class="bi bi-person-plus"></i> Admin Register
                    </a>
                </li>
            </ul>
        </div>
    </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <button class="toggle-btn d-md-none">
                    <i class="bi bi-list"></i>
                </button>
                <div class="user-menu">
                    <div class="user-profile">
                        <div class="user-info">
                            <div class="user-name">Admin</div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>
</body>
</html>