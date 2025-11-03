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

// Handle package status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $packageId = (int)$_POST['package_id'];
    $newStatus = $conn->real_escape_string($_POST['new_status']);
    
    // Validate status
    $validStatuses = ['available', 'in_use', 'maintenance', 'rejected'];
    if (!in_array($newStatus, $validStatuses)) {
        die("Invalid status value");
    }
    
    // Update package status
    $updateQuery = "UPDATE package SET status = ?, approvalDate = NOW(), adminId = ? WHERE packageId = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("sii", $newStatus, $adminId, $packageId);
    
    if ($stmt->execute()) {
        header("Location: admin_packages.php?success=updated");
        exit();
    } else {
        header("Location: admin_packages.php?error=" . urlencode($conn->error));
        exit();
    }
}

// Handle sorting and searching
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'dateCreated';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$packagesQuery = "
    SELECT 
        p.*,
        s.username AS staff_username,
        a.username AS admin_username,
        GROUP_CONCAT(i.itemName SEPARATOR ', ') AS equipmentNames
    FROM package p
    LEFT JOIN staff s ON p.staffId = s.staffId
    LEFT JOIN admin a ON p.adminId = a.adminId
    LEFT JOIN inventory i ON FIND_IN_SET(i.itemId, p.equipmentsIncluded)
";

// Add search condition if search term exists
if (!empty($search)) {
    $packagesQuery .= " WHERE p.packageName LIKE '%" . $conn->real_escape_string($search) . "%'";
}

$packagesQuery .= " GROUP BY p.packageId";

// Add sorting
switch ($sort) {
    case 'name':
        $packagesQuery .= " ORDER BY p.packageName $order";
        break;
    case 'price':
        $packagesQuery .= " ORDER BY p.price $order";
        break;
    case 'bandwidth':
        $packagesQuery .= " ORDER BY p.expectedBandwidth $order";
        break;
    case 'status':
        $packagesQuery .= " ORDER BY p.status $order";
        break;
    default:
        $packagesQuery .= " ORDER BY 
            CASE p.status 
                WHEN 'pending' THEN 1
                WHEN 'available' THEN 2
                WHEN 'in_use' THEN 3
                WHEN 'maintenance' THEN 4
                WHEN 'rejected' THEN 5
                ELSE 6
            END,
            p.dateCreated $order";
        break;
}

$packages = $conn->query($packagesQuery);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Packages</title>
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
            vertical-align: middle;
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
        
        .status-available {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .status-maintenance {
            background-color: #E2E3E5;
            color: #383D41;
        }
        
        .status-in_use {
            background-color: #CCE5FF;
            color: #004085;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        /* Info Button Styles */
        .info-btn {
            background-color: #17a2b8;
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .info-btn:hover {
            background-color: #138496;
        }

        /* Equipment Button Styles */
        .equipment-btn {
            background-color: #17a2b8;
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .equipment-btn:hover {
            background-color: #138496;
        }
        
        /* Modal Styles */
        .modal-header {
            background-color: #3498db;
            color: white;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-header.equipment-modal {
            background-color: #3498db;
        }
        
        /* Equipment List in Modal */
        .equipment-item {
            padding: 8px 12px;
            margin: 5px 0;
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            border-radius: 4px;
        }
        
        /* Action Control Styles */
        .action-control-group {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: nowrap;
        }

        .action-control-group .form-select {
            min-width: 110px;
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
        }

        .action-control-group .btn {
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1.2;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-control-group .btn i {
            font-size: 12px;
        }

        /* Pending action buttons */
        .pending-actions {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }

        .pending-actions .btn {
            padding: 4px 8px;
            font-size: 11px;
            white-space: nowrap;
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
            
            .search-sort-container {
                flex-direction: column;
            }

            .action-control-group {
                flex-direction: column;
                gap: 2px;
            }

            .pending-actions {
                flex-direction: column;
                gap: 2px;
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
            <h2>Package Management</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Package <?php echo htmlspecialchars($_GET['success']); ?> successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error: <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
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
                    <li><a class="dropdown-item" href="?sort=price&order=asc">Price (Low to High)</a></li>
                    <li><a class="dropdown-item" href="?sort=price&order=desc">Price (High to Low)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=bandwidth&order=asc">Bandwidth (Low to High)</a></li>
                    <li><a class="dropdown-item" href="?sort=bandwidth&order=desc">Bandwidth (High to Low)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=status&order=asc">Status (A-Z)</a></li>
                    <li><a class="dropdown-item" href="?sort=status&order=desc">Status (Z-A)</a></li>
                </ul>
            </div>
            
            <!-- Search Form -->
            <form class="search-form" method="GET" action="">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by package name" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="admin_packages.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <a href="admin_packagesCreate.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Package
        </a><br><br>
        <!-- Packages Table -->
        <div class="table-responsive">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Package Name</th>
                        <th>Price</th>
                        <th>Users</th>
                        <th>Event Type</th>
                        <th>Area (sqm)</th>
                        <th>Bandwidth</th>
                        <th>Equipment Included</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Date Created</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($packages && $packages->num_rows > 0): ?>
                        <?php while ($package = $packages->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($package['packageName']); ?></strong></td>
                                <td><strong>₱<?php echo number_format($package['price'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($package['numberOfUsers']); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($package['eventType'])); ?></td>
                                <td><?php echo number_format($package['eventAreaSize'], 2); ?></td>
                                <td><?php echo number_format($package['expectedBandwidth'], 2); ?> Mbps</td>
                                <td>
                                    <button class="equipment-btn" data-bs-toggle="modal" data-bs-target="#equipmentModal<?php echo $package['packageId']; ?>">
                                        <i class="bi bi-gear"></i> View Equipment
                                    </button>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($package['status']); ?>">
                                        <?php echo ucfirst($package['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($package['staff_username'] ?? 'System'); ?></td>
                                <td><?php echo date("M j, Y", strtotime($package['dateCreated'])); ?></td>
                                <td>
                                    <button class="info-btn" data-bs-toggle="modal" data-bs-target="#descriptionModal<?php echo $package['packageId']; ?>">
                                        <i class="bi bi-file-text"></i> View
                                    </button>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($package['status'] === 'pending'): ?>
                                        <div class="pending-actions">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="package_id" value="<?php echo $package['packageId']; ?>">
                                                <input type="hidden" name="new_status" value="available">
                                                <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="package_id" value="<?php echo $package['packageId']; ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="package_id" value="<?php echo $package['packageId']; ?>">
                                            <div class="action-control-group">
                                                <select name="new_status" class="form-select form-select-sm">
                                                    <option value="available" <?php echo $package['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                                    <option value="in_use" <?php echo $package['status'] === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                                                    <option value="maintenance" <?php echo $package['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                    <option value="rejected" <?php echo $package['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">No packages found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Description Modals -->
    <?php 
    // Reset the result pointer to create modals
    $packages->data_seek(0);
    while ($package = $packages->fetch_assoc()): 
    ?>
    <!-- Description Modal -->
    <div class="modal fade" id="descriptionModal<?php echo $package['packageId']; ?>" tabindex="-1" aria-labelledby="descriptionModalLabel<?php echo $package['packageId']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descriptionModalLabel<?php echo $package['packageId']; ?>">
                        <i class="bi bi-file-text"></i> <?php echo htmlspecialchars($package['packageName']); ?> - Description
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><i class="bi bi-file-text"></i> Package Description</h6>
                    <p><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Equipment Modal -->
    <div class="modal fade" id="equipmentModal<?php echo $package['packageId']; ?>" tabindex="-1" aria-labelledby="equipmentModalLabel<?php echo $package['packageId']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header equipment-modal">
                    <h5 class="modal-title" id="equipmentModalLabel<?php echo $package['packageId']; ?>">
                        <i class="bi bi-gear"></i> <?php echo htmlspecialchars($package['packageName']); ?> - Equipment Included
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><i class="bi bi-list-ul"></i> Equipment List</h6>
                    <?php 
                    $equipmentList = $package['equipmentNames'] ?? $package['equipmentsIncluded'];
                    if (!empty($equipmentList)) {
                        $equipmentItems = explode(', ', $equipmentList);
                        foreach ($equipmentItems as $equipment) {
                            if (!empty(trim($equipment))) {
                                echo '<div class="equipment-item"><i class="bi bi-check-circle text-info"></i> ' . htmlspecialchars(trim($equipment)) . '</div>';
                            }
                        }
                    } else {
                        echo '<div class="text-muted"><i class="bi bi-info-circle"></i> No equipment specified for this package.</div>';
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</body>
</html>