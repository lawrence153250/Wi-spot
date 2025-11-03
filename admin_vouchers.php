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

// Database connection
require_once 'config.php';

// Get current admin ID
$username = $_SESSION['username'];
$adminQuery = $conn->prepare("SELECT adminId FROM admin WHERE username = ?");
$adminQuery->bind_param("s", $username);
$adminQuery->execute();
$adminResult = $adminQuery->get_result();

if ($adminResult->num_rows === 0) {
    die("Error: Admin account not found for username: " . htmlspecialchars($username));
}

$adminData = $adminResult->fetch_assoc();
$adminId = $adminData['adminId'];
$adminQuery->close();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $batchId = $_POST['batch_id'];
        $newStatus = $_POST['new_status'];
        $adminNotes = $_POST['admin_notes'] ?? null;
        
        $stmt = $conn->prepare("UPDATE voucher_batch SET approvalStatus = ?, adminId = ?, approvalDate = NOW(), adminNotes = ? WHERE batchId = ?");
        $stmt->bind_param("sisi", $newStatus, $adminId, $adminNotes, $batchId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Voucher status updated successfully!";
            header("Location: admin_vouchers.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error updating status: " . $conn->error;
        }
        $stmt->close();
    }
    // Handle voucher deletion
    elseif (isset($_POST['delete_voucher'])) {
        $batchId = $_POST['batch_id'];
        
        // First delete all voucher codes associated with this batch
        $deleteCodes = $conn->prepare("DELETE FROM voucher_code WHERE batchId = ?");
        $deleteCodes->bind_param("i", $batchId);
        $deleteCodes->execute();
        $deleteCodes->close();
        
        // Then delete the voucher batch
        $deleteBatch = $conn->prepare("DELETE FROM voucher_batch WHERE batchId = ?");
        $deleteBatch->bind_param("i", $batchId);
        
        if ($deleteBatch->execute()) {
            $_SESSION['success_message'] = "Voucher batch deleted successfully!";
            header("Location: admin_vouchers.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error deleting voucher batch: " . $conn->error;
        }
        $deleteBatch->close();
    }
}

// Handle sorting and searching
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'createdAt';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$query = "SELECT vb.*, s.username AS staff_username 
          FROM voucher_batch vb
          LEFT JOIN staff s ON vb.staffId = s.staffId";

// Add search condition if search term exists
if (!empty($search)) {
    $query .= " WHERE vb.voucherName LIKE '%" . $conn->real_escape_string($search) . "%'";
}

// Add sorting
switch ($sort) {
    case 'name':
        $query .= " ORDER BY vb.voucherName $order";
        break;
    case 'type':
        $query .= " ORDER BY vb.voucherType $order";
        break;
    case 'status':
        $query .= " ORDER BY vb.approvalStatus $order";
        break;
    default:
        $query .= " ORDER BY 
                   CASE vb.approvalStatus 
                     WHEN 'pending' THEN 1
                     WHEN 'approved' THEN 2
                     WHEN 'declined' THEN 3
                     ELSE 4
                   END,
                   vb.createdAt $order";
        break;
}

// Execute the query
$result = $conn->query($query);
$voucherBatches = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Vouchers</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            padding: 0 15px 15px;
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
        
        /* Table Styles */
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .status-approved {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status-declined {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .voucher-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .notes-section {
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .notes-label {
            font-weight: 600;
        }
        
        /* Search and Sort Styles */
        .search-sort-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .sort-dropdown .dropdown-toggle {
            background-color: #3498db;
            border: none;
        }
        
        .search-form {
            display: flex;
            gap: 5px;
            flex-grow: 1;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .search-sort-container {
                flex-direction: column;
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
            <h2>Voucher Approval</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Search and Sort Controls -->
        <div class="search-sort-container">
            <!-- Sort Dropdown -->
            <div class="dropdown sort-dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-sort-alpha-down"></i> Sort By
                </button>
                <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                    <li><h6 class="dropdown-header">Sort Options</h6></li>
                    <li><a class="dropdown-item" href="?sort=name&order=asc">Name (A-Z)</a></li>
                    <li><a class="dropdown-item" href="?sort=name&order=desc">Name (Z-A)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=type&order=asc">Type (A-Z)</a></li>
                    <li><a class="dropdown-item" href="?sort=type&order=desc">Type (Z-A)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=status&order=asc">Status (A-Z)</a></li>
                    <li><a class="dropdown-item" href="?sort=status&order=desc">Status (Z-A)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=createdAt&order=desc">Newest First</a></li>
                    <li><a class="dropdown-item" href="?sort=createdAt&order=asc">Oldest First</a></li>
                </ul>
            </div>
            
            <!-- Search Form -->
            <form class="search-form" method="GET" action="">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by voucher name" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="admin_vouchers.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <a href="admin_voucherCreate.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Voucher
        </a><br><br>
        <!-- Vouchers Table -->
        <div class="table-responsive">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Voucher Name</th>
                        <th>Type</th>
                        <th>Discount</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created Date</th>
                        <th>Valid Until</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($voucherBatches)): ?>
                        <?php foreach ($voucherBatches as $batch): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($batch['voucherName']); ?></strong>
                                    <?php if ($batch['description']): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($batch['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($batch['voucherType']); ?></td>
                                <td><?php echo htmlspecialchars($batch['discountRate']); ?>%</td>
                                <td><?php echo htmlspecialchars($batch['quantity']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($batch['approvalStatus']); ?>">
                                        <?php echo ucfirst($batch['approvalStatus']); ?>
                                    </span>
                                    <?php if ($batch['approvalDate']): ?>
                                        <div class="text-muted small"><?php echo date("M j, Y", strtotime($batch['approvalDate'])); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($batch['staff_username'] ?? 'System'); ?></td>
                                <td><?php echo date("M j, Y", strtotime($batch['createdAt'])); ?></td>
                                <td><?php echo date("M j, Y", strtotime($batch['endDate'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($batch['approvalStatus'] === 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="batch_id" value="<?php echo $batch['batchId']; ?>">
                                                <input type="hidden" name="new_status" value="approved">
                                                <div class="mb-2">
                                                    <textarea name="admin_notes" class="form-control form-control-sm" placeholder="Approval notes (optional)" rows="2"></textarea>
                                                </div>
                                                <button type="submit" name="update_status" class="btn btn-success btn-sm">Approve</button>
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="this.form.new_status.value='declined'">Decline</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="voucher_codes.php?batchId=<?php echo $batch['batchId']; ?>" class="btn btn-primary btn-sm">View Codes</a>
                                            <?php if ($batch['approvalStatus'] === 'declined'): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this voucher batch? This cannot be undone.');">
                                                    <input type="hidden" name="batch_id" value="<?php echo $batch['batchId']; ?>">
                                                    <button type="submit" name="delete_voucher" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($batch['adminNotes']): ?>
                                                <div class="notes-section">
                                                    <span class="notes-label">Notes:</span>
                                                    <?php echo htmlspecialchars($batch['adminNotes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">No voucher batches found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
