<?php
// Start the session
session_start();

// Set session timeout to 15 minutes (900 seconds)
$inactive = 900; 

// Check if timeout variable is set
if (isset($_SESSION['timeout'])) {
    // Calculate the session's lifetime
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        // Logout and redirect to login page
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}

// Update timeout with current time
$_SESSION['timeout'] = time();

// Check if user is logged in and is staff
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'staff') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Fetch all announcements with admin details
$query = "SELECT a.*, ad.username AS admin_username 
          FROM announcement a 
          JOIN admin ad ON a.adminId = ad.adminId 
          ORDER BY a.date DESC";
$announcements = $conn->query($query);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Announcements</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Sidebar Styles */

        .sidebar .sidebar-menu li a.nav-link {
    color: #FFFFFF !important;
}
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            height: 100vh;
            padding: 20px 0;
            position: fixed;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            font-size: 2vh;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu li:hover {
            background-color: #34495e;
        }
        
.sidebar-menu li a.nav-link {
        color: #FFFFFF;
        }

        .sidebar-menu li.active {
            background-color: #34485f;
        }
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        /* Announcement Card Styles */
        .announcement-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .announcement-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .announcement-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .announcement-category {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .category-employee {
            background-color: #3498db;
        }
        
        .category-promotional {
            background-color: #2ecc71;
        }
        
        .category-regular {
            background-color: #f39c12;
        }
        
        .category-maintenance {
            background-color: #9b59b6;
        }
        
        .category-event {
            background-color: #e74c3c;
        }
        
        .category-policy {
            background-color: #34495e;
        }
        
        .announcement-priority {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .announcement-date {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .announcement-body {
            padding: 20px;
        }
        
        .announcement-description {
            color: #34495e;
            line-height: 1.6;
            white-space: pre-line;
        }
        
        .announcement-footer {
            padding: 10px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h1, 
            .sidebar-menu li span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .announcement-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
<!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="staff_dashboard.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a class="nav-link" href="staff_dashboard.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="staff_accounts.php">ACCOUNTS</a></li>
            <li><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
            <li><a class="nav-link" href="staff_services.php">SERVICES</a></li>
            <li><a class="nav-link" href="staff_landingReport.php">REPORTS</a></li>
            <li class="active"><a class="nav-link" href="staff_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="staff_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>ANNOUNCEMENTS</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>

        <!-- Announcements List -->
        <div class="announcements-container">
            <?php if ($announcements->num_rows > 0): ?>
                <?php while ($announcement = $announcements->fetch_assoc()): ?>
                    <div class="announcement-card">
                        <div class="announcement-header">
                            <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="announcement-meta">
                                <span class="announcement-category category-<?php echo strtolower($announcement['category']); ?>">
                                    <?php echo htmlspecialchars($announcement['category']); ?>
                                </span>
                                <?php if ($announcement['isPriority']): ?>
                                    <span class="announcement-priority">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Priority
                                    </span>
                                <?php endif; ?>
                                <span class="announcement-date">
                                    <?php echo date('M d, Y h:i A', strtotime($announcement['date'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="announcement-body">
                            <p class="announcement-description"><?php echo htmlspecialchars($announcement['description']); ?></p>
                            
                            <?php if (!empty($announcement['startDate']) || !empty($announcement['endDate'])): ?>
                                <div class="announcement-dates mt-3">
                                    <?php if (!empty($announcement['startDate'])): ?>
                                        <div><strong>Start:</strong> <?php echo date('M d, Y h:i A', strtotime($announcement['startDate'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($announcement['endDate'])): ?>
                                        <div><strong>End:</strong> <?php echo date('M d, Y h:i A', strtotime($announcement['endDate'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-footer">
                            Posted by: <?php echo htmlspecialchars($announcement['admin_username']); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    No announcements found.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
