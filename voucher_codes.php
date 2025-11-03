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

// Database connection
require_once 'config.php';

// Get batch ID from URL
$batchId = isset($_GET['batchId']) ? intval($_GET['batchId']) : 0;

if ($batchId <= 0) {
    die("Invalid voucher batch ID");
}

// Get batch details
$batchQuery = $conn->prepare("SELECT * FROM voucher_batch WHERE batchId = ?");
$batchQuery->bind_param("i", $batchId);
$batchQuery->execute();
$batchResult = $batchQuery->get_result();

if ($batchResult->num_rows === 0) {
    die("Voucher batch not found");
}

$batchData = $batchResult->fetch_assoc();
$batchQuery->close();

// Get voucher codes
$codesQuery = $conn->prepare("SELECT * FROM voucher_code WHERE batchId = ? ORDER BY isUsed, createdAt");
$codesQuery->bind_param("i", $batchId);
$codesQuery->execute();
$codesResult = $codesQuery->get_result();
$voucherCodes = $codesResult->fetch_all(MYSQLI_ASSOC);
$codesQuery->close();

// Determine user type and set appropriate links
$isAdmin = (isset($_SESSION['userlevel']) && $_SESSION['userlevel'] === 'admin');
$backLink = $isAdmin ? 'admin_vouchers.php' : 'staff_vouchers.php';
$dashboardLink = $isAdmin ? 'adminhome.php' : 'staff_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Voucher Codes</title>
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
            font-size: 1.5vh;
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
        
        /* Table Styles - Updated to match staff booking */
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .bookings-table th, 
        .bookings-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .bookings-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .bookings-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: 600;
        }
        
        .account-section {
            margin-bottom: 40px;
        }
        .batch-info {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .batch-info h4 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .batch-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .batch-detail {
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .code-used {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .code-unused {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .export-btn {
            margin-bottom: 15px;
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
            
            .bookings-table {
                display: block;
                overflow-x: auto;
            }
        }
        
    </style>
</head>
<body>
    <!-- Dynamic Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="<?php echo $dashboardLink; ?>"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <?php if ($isAdmin): ?>
                <!-- Admin Sidebar -->
                <li><a class="nav-link" href="adminhome.php">DASHBOARD</a></li>
                <li><a class="nav-link" href="admin_accounts.php">ACCOUNTS</a></li>
                <li class="active"><a class="nav-link" href="admin_services.php">SERVICES</a></li>
                <li><a class="nav-link" href="admin_booking.php">BOOKING MANAGEMENT</a></li>
                <li><a class="nav-link" href="admin_management.php">REPORTS MANAGEMENT</a></li>
                <li><a class="nav-link" href="admin_announcements.php">ANNOUNCEMENTS</a></li>
                <li><a class="nav-link" href="admin_resetpass.php">RESET PASSWORD</a></li>
            <?php else: ?>
                <!-- Staff Sidebar -->
                <li><a class="nav-link" href="staff_dashboard.php">DASHBOARD</a></li>
                <li><a class="nav-link" href="staff_accounts.php">ACCOUNTS</a></li>
                <li><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
                <li class="active"><a class="nav-link" href="staff_services.php">SERVICES</a></li>
                <li><a class="nav-link" href="staff_landingReport.php">REPORTS</a></li>
                <li><a class="nav-link" href="staff_announcements.php">ANNOUNCEMENTS</a></li>
                <li><a class="nav-link" href="staff_resetpass.php">RESET PASSWORD</a></li>
            <?php endif; ?>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>VOUCHER CODES</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Batch Information -->
        <div class="batch-info">
    <h4><?php echo htmlspecialchars($batchData['voucherName']); ?></h4>
    <div class="batch-details">
        <span class="batch-detail"><strong>Type:</strong> <?php echo htmlspecialchars($batchData['voucherType']); ?></span>
        <span class="batch-detail"><strong>Discount:</strong> <?php echo htmlspecialchars($batchData['discountRate']); ?>%</span>
        <span class="batch-detail"><strong>Valid:</strong> <?php echo date('M d, Y', strtotime($batchData['startDate'])); ?> to <?php echo date('M d, Y', strtotime($batchData['endDate'])); ?></span>
        <span class="batch-detail"><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($batchData['approvalStatus'])); ?></span>
        <span class="batch-detail"><strong>Total Codes:</strong> <?php echo count($voucherCodes); ?></span>
    </div>
    <p><?php echo htmlspecialchars($batchData['description']); ?></p>
    <div class="d-flex gap-2">
        <a href="<?php echo $backLink; ?>" class="btn btn-outline-primary">Back to Vouchers</a>
        <button onclick="exportToCSV()" class="btn btn-success">
            <i class="bi bi-download"></i> Export to CSV
        </button>
    </div>
</div>
        
        <!-- Voucher Codes Table -->
        <div class="table-responsive">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Voucher Code</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Used Date</th>
                        <th>Customer ID</th>
                        <th>Booking ID</th>
                        <th>Voucher Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($voucherCodes as $code): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($code['code']); ?></code></td>
                            <td>
                                <?php if ($code['isUsed']): ?>
                                    <span class="code-used">Used</span>
                                <?php else: ?>
                                    <span class="code-unused">Available</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($code['createdAt'])); ?></td>
                            <td>
                                <?php if ($code['usedDate']): ?>
                                    <?php echo date('M d, Y H:i', strtotime($code['usedDate'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($code['customerId']): ?>
                                    <?php echo htmlspecialchars($code['customerId']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($code['bookingId']): ?>
                                    <?php echo htmlspecialchars($code['bookingId']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($code['voucherType']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportToCSV() {
            // Get batch info for filename
            const batchName = "<?php echo preg_replace('/[^a-z0-9]/i', '_', strtolower($batchData['voucherName'])); ?>";
            const batchId = <?php echo $batchId; ?>;
            
            // Create CSV content
            let csvContent = "Voucher Code,Status,Created Date,Used Date,Customer ID,Booking ID,Voucher Type\n";

            <?php foreach ($voucherCodes as $code): ?>
                csvContent += `"<?php echo $code['code']; ?>",`;
                csvContent += `"<?php echo $code['isUsed'] ? 'Used' : 'Available'; ?>",`;
                csvContent += `"<?php echo date('M d, Y H:i', strtotime($code['createdAt'])); ?>",`;
                csvContent += `"<?php echo $code['usedDate'] ? date('M d, Y H:i', strtotime($code['usedDate'])) : ''; ?>",`;
                csvContent += `"<?php echo $code['customerId'] ?: ''; ?>",`;
                csvContent += `"<?php echo $code['bookingId'] ?: ''; ?>"\n`;
                csvContent += `"<?php echo $code['voucherType']; ?>"\n`;
            <?php endforeach; ?>
            
            // Create download link
            const encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `voucher_codes_${batchName}_${batchId}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
