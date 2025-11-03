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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucherType = $_POST['voucherType'];
    $voucherName = $_POST['voucherName'];
    $description = $_POST['description'];
    $discountRate = $_POST['discountRate'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    $quantity = $_POST['quantity'];

    // Insert voucher batch
    $stmt = $conn->prepare("INSERT INTO voucher_batch (staffId, voucherType, voucherName, description, discountRate, startDate, endDate, quantity) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssdssi", $staffId, $voucherType, $voucherName, $description, $discountRate, $startDate, $endDate, $quantity);
    
    if ($stmt->execute()) {
        $batchId = $stmt->insert_id;
        
        // Generate voucher codes
        $codes = generateVoucherCodes($quantity);
        
        // Insert voucher codes with voucherType
        $insertCode = $conn->prepare("INSERT INTO voucher_code (batchId, code, voucherType) VALUES (?, ?, ?)");
        foreach ($codes as $code) {
            $insertCode->bind_param("iss", $batchId, $code, $voucherType);
            $insertCode->execute();
        }
        
        $_SESSION['success_message'] = "Voucher batch created successfully with $quantity codes!";
        header("Location: staff_vouchers.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error creating voucher batch: " . $conn->error;
    }
}

// Function to generate random alphanumeric codes
function generateVoucherCodes($quantity) {
    $codes = [];
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    for ($i = 0; $i < $quantity; $i++) {
        $code = '';
        for ($j = 0; $j < 12; $j++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        $codes[] = $code;
    }
    
    return $codes;
}

// Fetch existing vouchers
$vouchers = [];
$result = $conn->query("SELECT * FROM voucher_batch ORDER BY createdAt DESC");
if ($result) {
    $vouchers = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Vouchers</title>
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
        .voucher-form-container {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        
        .voucher-form-card {
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
        
        .btn-create-voucher {
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
        
        .btn-create-voucher:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .btn-create-voucher:active {
            transform: translateY(0);
        }
        
        /* Enhanced Card Styles for Voucher List */
        .voucher-cards-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            border: 1px solid #e9ecef;
        }
        
        .voucher-cards-section h4 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .voucher-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .voucher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }
        
        .voucher-card h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .voucher-status {
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
        
        .status-approved {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status-declined {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .voucher-type-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .discount-badge {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .voucher-dates {
            color: #6c757d;
            font-size: 13px;
            margin-top: 8px;
        }
        
        .voucher-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .btn-view-codes {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-view-codes:hover {
            background: linear-gradient(135deg, #2980b9, #2471a3);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }
        
        .created-date {
            color: #6c757d;
            font-size: 12px;
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
            
            .voucher-form-card {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .voucher-card {
                margin-bottom: 15px;
            }
            
            .voucher-meta {
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
            <h2>VOUCHER MANAGEMENT</h2>
            <div class="user-info"> 
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Enhanced Create Voucher Form -->
        <div class="voucher-form-container">
            <div class="voucher-form-card">
                <div class="form-card-header">
                    <h4><i class="bi bi-ticket-perforated"></i> Create New Voucher Batch</h4>
                    <p>Generate multiple vouchers for promotional campaigns</p>
                </div>
                
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="voucherType" class="form-label">
                                <i class="bi bi-tag"></i> Voucher Type
                            </label>
                            <select class="form-select" id="voucherType" name="voucherType" required>
                                <option value="">Select type</option>
                                <option value="Birthday">Birthday</option>
                                <option value="Referral">Referral</option>
                                <option value="Returning Customer">Returning Customer</option>
                                <option value="Limited-Time">Limited-Time</option>
                                <option value="First Rental">First Rental</option>
                                <option value="Seasonal">Seasonal</option>
                                <option value="VIP">VIP</option>
                                <option value="Bundle">Bundle</option>
                                <option value="Flash Sale">Flash Sale</option>
                                <option value="Corporate Discount">Corporate Discount</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="voucherName" class="form-label">
                                <i class="bi bi-card-text"></i> Voucher Name
                            </label>
                            <input type="text" class="form-control" id="voucherName" name="voucherName" placeholder="Enter voucher name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">
                            <i class="bi bi-file-text"></i> Description
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe the voucher details and terms..."></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="discountRate" class="form-label">
                                <i class="bi bi-percent"></i> Discount Rate (%)
                            </label>
                            <input type="number" class="form-control" id="discountRate" name="discountRate" min="1" max="100" placeholder="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="startDate" class="form-label">
                                <i class="bi bi-calendar-check"></i> Start Date
                            </label>
                            <input type="datetime-local" class="form-control" id="startDate" name="startDate" required>
                        </div>
                        <div class="col-md-4">
                            <label for="endDate" class="form-label">
                                <i class="bi bi-calendar-x"></i> End Date
                            </label>
                            <input type="datetime-local" class="form-control" id="endDate" name="endDate" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label">
                            <i class="bi bi-hash"></i> Number of Vouchers to Generate
                        </label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" max="1000" placeholder="1" required>
                    </div>
                    
                    <button type="submit" class="btn-create-voucher">
                        <i class="bi bi-check-circle"></i> Create Vouchers
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Enhanced Voucher Cards Section -->
        <div class="voucher-cards-section">
            <h4><i class="bi bi-collection"></i> Existing Voucher Batches</h4>
            <?php if (empty($vouchers)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No voucher batches found. Create your first voucher batch above.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($vouchers as $voucher): ?>
                        <div class="col-md-6">
                            <div class="voucher-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5><?php echo htmlspecialchars($voucher['voucherName']); ?></h5>
                                        <span class="voucher-type-badge"><?php echo htmlspecialchars($voucher['voucherType']); ?></span>
                                    </div>
                                    <span class="voucher-status status-<?php echo strtolower($voucher['approvalStatus']); ?>">
                                        <?php echo htmlspecialchars($voucher['approvalStatus']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($voucher['description'])): ?>
                                    <p class="mt-2 mb-3"><?php echo htmlspecialchars($voucher['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="discount-badge"><?php echo $voucher['discountRate']; ?>% OFF</span>
                                </div>
                                
                                <div class="voucher-dates">
                                    <i class="bi bi-calendar-range"></i>
                                    Valid: <?php echo date('M d, Y', strtotime($voucher['startDate'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($voucher['endDate'])); ?>
                                </div>
                                
                                <div class="voucher-meta">
                                    <span class="created-date">
                                        <i class="bi bi-clock"></i>
                                        Created: <?php echo date('M d, Y H:i', strtotime($voucher['createdAt'])); ?>
                                    </span>
                                    <a href="voucher_codes.php?batchId=<?php echo $voucher['batchId']; ?>" class="btn-view-codes">
                                        <i class="bi bi-eye"></i> View Codes
                                    </a>
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
