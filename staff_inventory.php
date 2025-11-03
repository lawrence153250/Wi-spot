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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment'])) {
    $itemName = $conn->real_escape_string($_POST['item_name']);
    $itemType = $conn->real_escape_string($_POST['item_type']);
    $quantity = (int)$_POST['quantity'];
    $description = $conn->real_escape_string($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO inventory 
                          (itemName, itemType, quantity, description, staffId, status, dateAdded) 
                          VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("ssisi", $itemName, $itemType, $quantity, $description, $staffId);
    
    if ($stmt->execute()) {
        $success_message = "Equipment added successfully! Waiting for admin approval.";
    } else {
        $error_message = "Error adding equipment: " . $conn->error;
    }
    $stmt->close();
}

// Fetch ALL inventory items (not just current staff's items)
$inventoryItems = [];
$query = $conn->query("SELECT 
                      i.itemId, i.itemName, i.itemType, i.quantity, 
                      i.status, i.description, i.dateAdded,
                      s.username AS staffUsername
                      FROM inventory i
                      LEFT JOIN staff s ON i.staffId = s.staffId
                      ORDER BY 
                        CASE i.status 
                          WHEN 'pending' THEN 1
                          WHEN 'available' THEN 2
                          WHEN 'rejected' THEN 3
                          ELSE 4
                        END, 
                      i.dateAdded DESC");

if ($query->num_rows > 0) {
    while ($row = $query->fetch_assoc()) {
        $inventoryItems[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Inventory</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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
        .inventory-form-container {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        
        .inventory-form-card {
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
        
        .btn-add-equipment {
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
        
        .btn-add-equipment:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-add-equipment:active {
            transform: translateY(0);
        }
        
        /* Enhanced Card Styles for Equipment List */
        .equipment-cards-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            border: 1px solid #e9ecef;
        }
        
        .equipment-cards-section h4 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .equipment-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .equipment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }
        
        .equipment-card h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .equipment-status {
            font-weight: 600;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
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
        
        .equipment-type-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .quantity-badge {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .equipment-dates {
            color: #6c757d;
            font-size: 13px;
            margin-top: 8px;
        }
        
        .equipment-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .submitted-date {
            color: #6c757d;
            font-size: 12px;
        }
        
        .item-id-badge {
            background-color: #f8f9fa;
            color: #495057;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            font-family: monospace;
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
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
            text-align: center;
            padding: 20px;
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
            
            .inventory-form-card {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .equipment-card {
                margin-bottom: 15px;
            }
            
            .equipment-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
            <h2>INVENTORY MANAGEMENT</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Display messages -->
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
        
        <!-- Enhanced Add Equipment Form -->
        <div class="inventory-form-container">
            <div class="inventory-form-card">
                <div class="form-card-header">
                    <h4><i class="bi bi-box-seam"></i> Add New Equipment</h4>
                    <p>Submit equipment for approval and inventory management</p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="item_name" class="form-label">
                                <i class="bi bi-tag"></i> Equipment Name
                            </label>
                            <input type="text" class="form-control" id="item_name" name="item_name" placeholder="Enter equipment name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="item_type" class="form-label">
                                <i class="bi bi-gear"></i> Equipment Type
                            </label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="">Select Type</option>
                                <option value="WiFi Router">WiFi Router</option>
                                <option value="WiFi Extender">WiFi Extender</option>
                                <option value="Ethernet Cable">Ethernet Cable</option>
                                <option value="Network Switch">Network Switch</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label">
                            <i class="bi bi-hash"></i> Quantity
                        </label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" placeholder="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">
                            <i class="bi bi-file-text"></i> Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe the equipment details..."></textarea>
                    </div>
                    
                    <button type="submit" name="add_equipment" class="btn-add-equipment">
                        <i class="bi bi-check-circle"></i> Submit for Approval
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Enhanced Equipment Cards Section -->
        <div class="equipment-cards-section">
            <h4><i class="bi bi-collection"></i> All Inventory Items</h4>
            <?php if (empty($inventoryItems)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No equipment submissions found. Submit your first equipment above.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($inventoryItems as $item): ?>
                        <div class="col-md-6">
                            <div class="equipment-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5><?php echo htmlspecialchars($item['itemName']); ?></h5>
                                        <span class="equipment-type-badge"><?php echo htmlspecialchars($item['itemType']); ?></span>
                                    </div>
                                    <span class="equipment-status status-<?php echo strtolower($item['status']); ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($item['description'])): ?>
                                    <p class="mt-2 mb-3"><?php echo htmlspecialchars($item['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="quantity-badge">Qty: <?php echo $item['quantity']; ?></span>
                                    <span class="item-id-badge">ID: <?php echo htmlspecialchars($item['itemId']); ?></span>
                                </div>
                                
                                <div class="equipment-dates">
                                    <i class="bi bi-calendar-plus"></i>
                                    Submitted: <?php echo date('M d, Y', strtotime($item['dateAdded'])); ?>
                                </div>
                                
                                <div class="equipment-meta">
                                    <span class="submitted-date">
                                        <i class="bi bi-clock"></i>
                                        Added: <?php echo date('M d, Y H:i', strtotime($item['dateAdded'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
