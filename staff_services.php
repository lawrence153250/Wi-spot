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
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'staff') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Function to get counts from database
function getCount($conn, $table, $column = null, $values = null) {
    if ($column && $values) {
        if (is_array($values)) {
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);
        } else {
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // For tables where we just count all entries (feedback and announcements)
        $sql = "SELECT COUNT(*) as count FROM $table";
        $result = $conn->query($sql);
    }
    
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get counts for each section
$bookingCount = getCount($conn, 'booking', 'bookingStatus', 'Pending');
$packageCount = getCount($conn, 'package', 'status', ['available', 'rejected']);
$voucherCount = getCount($conn, 'voucher_batch', 'approvalStatus', ['approved', 'declined']);
$inventoryCount = getCount($conn, 'inventory', 'status', ['available', 'rejected']);
$feedbackCount = getCount($conn, 'feedback');
$announcementCount = getCount($conn, 'announcement');

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
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
            <a class="navbar-brand" href="staff_dashboard.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a class="nav-link" href="staff_dashboard.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="staff_accounts.php">ACCOUNTS</a></li>
            <li><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
            <li class="active"><a class="nav-link" href="staff_services.php">SERVICES</a></li>
            <li><a class="nav-link" href="staff_landingReport.php">REPORTS</a></li>
            <li><a class="nav-link" href="staff_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="staff_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>STAFF REPORTS VIEW</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Dashboard Cards -->
        <div class="dashboard-cards">

                    
            <a href="staff_inventory.php" class="dashboard-card">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                    <div class="card-text">
                        <h3>Inventory</h3>
                        <p>Available/Rejected items</p>
                    </div>
                </div>
                <div class="card-count"><?php echo $inventoryCount; ?></div>
            </a>
            
           <a href="staff_packages.php" class="dashboard-card">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="card-text">
                        <h3>Packages</h3>
                        <p>Available/Rejected items</p>
                    </div>
                </div>
                <div class="card-count"><?php echo $packageCount; ?></div>
            </a>
            
            <a href="staff_vouchers.php" class="dashboard-card">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="card-text">
                        <h3>Vouchers</h3>
                        <p>Approved/Declined batches</p>
                    </div>
                </div>
                <div class="card-count"><?php echo $voucherCount; ?></div>
            </a>
            

        </div>
    </div>
</body>
</html>
