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

// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database 
require_once 'config.php';

// Function to get counts from database
function getCount($conn, $table, $column = null, $value = null) {
    if ($column && $value) {
        $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // For tables where we just count all entries
        $sql = "SELECT COUNT(*) as count FROM $table";
        $result = $conn->query($sql);
    }
    
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get locked accounts count from multiple tables
function getLockedAccountsCount($conn) {
    $tables = ['admin', 'staff', 'customer'];
    $total = 0;
    
    foreach ($tables as $table) {
        $sql = "SELECT COUNT(*) as count FROM $table WHERE accountStatus = 'locked'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $total += $row['count'];
    }
    
    return $total;
}

// Get counts for each section
$lockedAccountsCount = getLockedAccountsCount($conn);
$packageCount = getCount($conn, 'package', 'status', 'pending');
$voucherCount = getCount($conn, 'voucher_batch', 'approvalStatus', 'pending');
$inventoryCount = getCount($conn, 'inventory', 'status', 'pending');
$feedbackCount = getCount($conn, 'feedback', 'displayStatus', 'pending');

// New counts for booking sections
$bookingEditsCount = getCount($conn, 'booking_edits', 'edit_status', 'Pending');
$connectionErrorCount = getCount($conn, 'booking', 'connectionStatus', 'error');

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
            position: fixed;
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden; /* Hide horizontal scrollbar */
        }
        
        .sidebar-content {
            padding: 20px 0;
            min-height: 100%; /* Ensure content takes full height */
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
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
        
        .sidebar-menu li.active {
            background-color: #34485f;
        }
        

        /* Custom scrollbar for webkit browsers */
                .sidebar::-webkit-scrollbar {
                    width: 6px;
                }
                
                .sidebar::-webkit-scrollbar-track {
                    background: #34495e;
                }
                
                .sidebar::-webkit-scrollbar-thumb {
                    background: #5a6c7d;
                    border-radius: 3px;
                }
                
                .sidebar::-webkit-scrollbar-thumb:hover {
                    background: #7f8c8d;
                }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 15px;
            }

            .sidebar-menu li {
                padding: 8px 10px;
                font-size: 1.8vh;
            }
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
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            border-left: 5px solid #3498db;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .card-content {
            display: flex;
            align-items: center;
        }
        
        .card-icon {
            font-size: 2.5rem;
            margin-right: 20px;
            color: #3498db;
        }
        
        .card-text h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .card-text p {
            margin: 5px 0 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .card-count {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
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
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="adminhome.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a class="nav-link" href="adminhome.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="admin_accounts.php">ACCOUNTS</a></li>
            <li class="active"><a class="nav-link" href="admin_services.php">SERVICES</a></li>
            <li><a class="nav-link" href="admin_booking.php">BOOKING MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_management.php">REPORTS MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="admin_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>ADMIN SERVICES</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            

            <a href="admin_packages.php" class="dashboard-card">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="card-text">
                        <h3>Packages</h3>
                        <p>Pending approval</p>
                    </div>
                </div>
                <div class="card-count"><?php echo $packageCount; ?></div>
            </a>
            
            <a href="admin_vouchers.php" class="dashboard-card">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="card-text">
                        <h3>Vouchers</h3>
                        <p>Pending approval</p>
                    </div>
                </div>
                <div class="card-count"><?php echo $voucherCount; ?></div>
            </a>
            
            <a href="admin_inventory.php" class="dashboard-card">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                    <div class="card-text">
                        <h3>Inventory</h3>
                        <p>Pending approval</p>
                    </div>
                </div>
                <div class="card-count"><?php echo $inventoryCount; ?></div>
            </a>
        </div>
    </div>
</body>
</html>
