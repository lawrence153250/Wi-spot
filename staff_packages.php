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

// Get current staff ID
$username = $_SESSION['username'];
$staffQuery = $conn->prepare("SELECT staffId FROM staff WHERE username = ?");
$staffQuery->bind_param("s", $username);
$staffQuery->execute();
$staffResult = $staffQuery->get_result();

if ($staffResult->num_rows === 0) {
    die("Error: Staff account not found for username: " . htmlspecialchars($username));
}

$staffData = $staffResult->fetch_assoc();
$staffId = $staffData['staffId'];
$staffQuery->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_package'])) {
    $packageName = $conn->real_escape_string($_POST['package_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = (float)$_POST['price'];
    $numberOfUsers = (int)$_POST['number_of_users'];
    $eventType = $conn->real_escape_string($_POST['event_type']);
    $eventAreaSize = (float)$_POST['event_area_size'];
    $expectedBandwidth = (float)$_POST['expected_bandwidth'];
    $selectedItems = $_POST['equipment_items'] ?? [];
    
    // Validate at least one item is selected
    if (empty($selectedItems)) {
        $error_message = "Please select at least one equipment item";
    } else {
        // Convert selected items array to comma-separated string
        $equipmentsIncluded = implode(',', $selectedItems);
        
        // Insert new package with all fields
        $stmt = $conn->prepare("INSERT INTO package (
            staffId, packageName, description, price, equipmentsIncluded, 
            status, numberOfUsers, eventType, eventAreaSize, expectedBandwidth
        ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "issdsisdd", 
            $staffId, 
            $packageName, 
            $description, 
            $price, 
            $equipmentsIncluded,
            $numberOfUsers,
            $eventType,
            $eventAreaSize,
            $expectedBandwidth
        );
        
        if ($stmt->execute()) {
            $success_message = "Package created successfully! Waiting for admin approval.";
        } else {
            $error_message = "Error creating package: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch available inventory items
$inventoryItems = [];
$inventoryQuery = $conn->query("SELECT itemId, itemName, itemType FROM inventory WHERE status = 'available'");
if ($inventoryQuery->num_rows > 0) {
    while ($row = $inventoryQuery->fetch_assoc()) {
        $inventoryItems[] = $row;
    }
}

// Fetch ALL packages with proper date fields (not just current staff's packages)
$packages = [];
$packageQuery = $conn->query("SELECT 
                            p.packageId, p.packageName, p.description, p.price, 
                            p.equipmentsIncluded, p.status, p.dateCreated, p.approvalDate,
                            p.numberOfUsers, p.eventType, p.eventAreaSize, p.expectedBandwidth,
                            s.username AS staffUsername,
                            GROUP_CONCAT(i.itemName SEPARATOR ', ') AS equipmentNames
                            FROM package p
                            LEFT JOIN inventory i ON FIND_IN_SET(i.itemId, p.equipmentsIncluded)
                            LEFT JOIN staff s ON p.staffId = s.staffId
                            GROUP BY p.packageId
                            ORDER BY 
                              CASE p.status 
                                WHEN 'pending' THEN 1
                                WHEN 'available' THEN 2
                                WHEN 'rejected' THEN 3
                                ELSE 4
                              END, 
                            p.dateCreated DESC");

if ($packageQuery->num_rows > 0) {
    while ($row = $packageQuery->fetch_assoc()) {
        $packages[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Packages</title>
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
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 24px;
            margin: 0;
        }
        
        .user-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .user-info i {
            margin-left: 8px;
            font-size: 18px;
        }
        
        /* Enhanced Form Container */
        .package-form-container {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        
        .package-form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 900px;
            border: 1px solid #e9ecef;
        }
        
        .form-card-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-card-header h4 {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-card-header p {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }
        
        /* Enhanced Form Styles */
        .form-label {
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        .form-control:hover, .form-select:hover {
            border-color: #bdc3c7;
        }
        
        .equipment-list {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e9ecef;
            padding: 20px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .form-check {
            margin-bottom: 12px;
            padding-left: 30px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-left: -30px;
            margin-top: 3px;
        }
        
        .form-check-label {
            color: #2c3e50;
            font-size: 14px;
            cursor: pointer;
        }
        
        .btn-create-package {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            width: 100%;
        }
        
        .btn-create-package:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-create-package:active {
            transform: translateY(0);
        }
        
        /* Table Styles */
        .packages-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .packages-table th, 
        .packages-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .packages-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .packages-table tr:hover {
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
        
        .table-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            border: 1px solid #e9ecef;
        }
        
        .table-section h4 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
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
                padding: 15px;
            }
            
            .package-form-card {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .packages-table {
                display: block;
                overflow-x: auto;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        .details-btn {
            background-color: #17a2b8;
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .details-btn:hover {
            background-color: #138496;
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
            <h2>PACKAGE MANAGEMENT</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Enhanced Create Package Form -->
        <div class="package-form-container">
            <div class="package-form-card">
                <div class="form-card-header">
                    <h4><i class="bi bi-box-seam"></i> Create New Package</h4>
                    <p>Design a new package for events and services</p>
                </div>
                
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="package_name" class="form-label">
                                <i class="bi bi-tag"></i> Package Name
                            </label>
                            <input type="text" class="form-control" id="package_name" name="package_name" placeholder="Enter package name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">
                                <i class="bi bi-currency-exchange"></i> Price (₱)
                            </label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="number_of_users" class="form-label">
                                <i class="bi bi-people"></i> Number of Users
                            </label>
                            <input type="number" class="form-control" id="number_of_users" name="number_of_users" min="1" placeholder="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="event_type" class="form-label">
                                <i class="bi bi-calendar-event"></i> Event Type
                            </label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="">Select type</option>
                                <option value="indoor">Indoor</option>
                                <option value="outdoor">Outdoor</option>
                                <option value="concert">Concert</option>
                                <option value="any">Any</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="event_area_size" class="form-label">
                                <i class="bi bi-aspect-ratio"></i> Event Area Size (sqm)
                            </label>
                            <input type="number" class="form-control" id="event_area_size" name="event_area_size" step="0.01" min="1" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="expected_bandwidth" class="form-label">
                                <i class="bi bi-speedometer2"></i> Expected Bandwidth (Mbps)
                            </label>
                            <input type="number" class="form-control" id="expected_bandwidth" name="expected_bandwidth" step="0.01" min="1" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">
                            <i class="bi bi-card-text"></i> Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe the package details and features..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-gear"></i> Select Equipment Items
                        </label>
                        <div class="equipment-list">
                            <?php if (!empty($inventoryItems)): ?>
                                <?php foreach ($inventoryItems as $item): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                            name="equipment_items[]" 
                                            value="<?php echo $item['itemId']; ?>" 
                                            id="item<?php echo $item['itemId']; ?>">
                                        <label class="form-check-label" for="item<?php echo $item['itemId']; ?>">
                                            <strong><?php echo htmlspecialchars($item['itemName']); ?></strong>
                                            <small class="text-muted">(<?php echo htmlspecialchars($item['itemType']); ?>)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> No available equipment items found in inventory.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="create_package" class="btn-create-package">
                        <i class="bi bi-check-circle"></i> Submit for Approval
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Enhanced Packages Table -->
        <div class="table-section">
            <h4><i class="bi bi-list-check"></i> All Packages</h4>
            <div class="table-responsive">
                <table class="packages-table">
                   <thead>
                        <tr>
                            <th>Name</th>
                            <th>Event Specifications</th>
                            <th>Price</th>
                            <th>Users</th>
                            <th>Type</th>
                            <th>Area (sqm)</th>
                            <th>Bandwidth</th>
                            <th>Equipment Included</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Date Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($packages)): ?>
                            <?php foreach ($packages as $package): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($package['packageName']); ?></td>
                                    <td>
                                        <button class="details-btn" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $package['packageId']; ?>">
                                            <i class="bi bi-gear"></i> View Details
                                        </button>
                                    </td>
                                    <td>₱<?php echo number_format($package['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($package['numberOfUsers']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($package['eventType'])); ?></td>
                                    <td><?php echo number_format($package['eventAreaSize'], 2); ?></td>
                                    <td><?php echo number_format($package['expectedBandwidth'], 2); ?> Mbps</td>
                                    <td>
                                        <button class="details-btn" data-bs-toggle="modal" data-bs-target="#equipmentModal<?php echo $package['packageId']; ?>">
                                        <i class="bi bi-hdd"></i> View Equipment
                                        </button>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($package['status']); ?>">
                                            <?php echo ucfirst($package['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date("M j, Y", strtotime($package['dateCreated'])); ?></td>
                                    <td>
                                        <?php echo $package['approvalDate'] ? date("M j, Y", strtotime($package['approvalDate'])) : 'Pending'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">
                                    <i class="bi bi-inbox"></i> No packages created yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Package Details Modals -->
    <?php foreach ($packages as $package): ?>
        <div class="modal fade" id="detailsModal<?php echo $package['packageId']; ?>" tabindex="-1" 
             aria-labelledby="detailsModalLabel<?php echo $package['packageId']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="detailsModalLabel<?php echo $package['packageId']; ?>">
                            <i class="bi bi-box-seam"></i> Package Details: <?php echo htmlspecialchars($package['packageName']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-calendar-event"></i> Event Specifications</h6>
                                <p><strong>Number of Users:</strong> <?php echo htmlspecialchars($package['numberOfUsers']); ?></p>
                                <p><strong>Event Type:</strong> <?php echo ucfirst(htmlspecialchars($package['eventType'])); ?></p>
                                <p><strong>Event Area Size:</strong> <?php echo number_format($package['eventAreaSize'], 2); ?> sqm</p>
                                <p><strong>Expected Bandwidth:</strong> <?php echo number_format($package['expectedBandwidth'], 2); ?> Mbps</p>
                            </div>
                        </div>  
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Equipment Included Modals -->
<?php foreach ($packages as $package): ?>
    <div class="modal fade" id="equipmentModal<?php echo $package['packageId']; ?>" tabindex="-1"
         aria-labelledby="equipmentModalLabel<?php echo $package['packageId']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="equipmentModalLabel<?php echo $package['packageId']; ?>">
                        <i class="bi bi-hdd"></i> Equipment Included: <?php echo htmlspecialchars($package['packageName']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group">
                        <?php 
                        // Use equipmentNames if available, otherwise fall back to equipmentsIncluded
                        if (!empty($package['equipmentNames'])) {
                            $equipments = explode(',', $package['equipmentNames']);
                            foreach ($equipments as $equip) {
                                echo '<li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>' . htmlspecialchars(trim($equip)) . '</li>';
                            }
                        } else {
                            $ids = explode(',', $package['equipmentsIncluded']);
                            foreach ($ids as $id) {
                                echo '<li class="list-group-item"><i class="bi bi-dash-circle-fill text-muted me-2"></i>' . htmlspecialchars(trim($id)) . '</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>


</body>
</html>