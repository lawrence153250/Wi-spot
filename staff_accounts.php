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

// Handle verification status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_verification_status'])) {
    $customerId = $_POST['customer_id'];
    $newStatus = $_POST['verification_status'];
    
    $updateSql = "UPDATE customer SET accountVerification = ? WHERE customerId = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si", $newStatus, $customerId);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Verification status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating verification status: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: staff_accounts.php");
    exit();
}

// Handle search and sorting
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'userName';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Build the base query
$sql = "SELECT customerId, email, firstName, lastName, contactNumber, address, birthday, 
               userName, facebookProfile, validId, proofOfBilling, idNumber, accountVerification, emailVerification,
               CASE WHEN validId IS NOT NULL AND proofOfBilling IS NOT NULL THEN 'ID Uploaded' ELSE 'ID not yet uploaded' END as idUploaded
        FROM customer";

// Add search condition if search term exists
if (!empty($search)) {
    $sql .= " WHERE userName LIKE '%$search%' OR email LIKE '%$search%' OR firstName LIKE '%$search%' OR lastName LIKE '%$search%'";
}

// Add sorting
switch ($sort) {
    case 'username':
        $sql .= " ORDER BY userName $order";
        break;
    case 'verification':
        $sql .= " ORDER BY accountVerification $order";
        break;
    default:
        $sql .= " ORDER BY userName $order";
        break;
}

// Fetch all customers with verification status
$customers = [];
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Account Management</title>
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
        
        /* Table Styles - Updated to match booking table */
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

        /* Add new styles for the verification status box */
        .verification-status-box {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            min-width: 120px;
            border: 1px solid transparent;
            cursor: default;
        }

        /* Keep the existing color classes but apply them to the new box */
        .id-uploaded {
            background-color: #2ecc71;
            color: white;
            border-color: #27ae60;
        }

        .id-not-yet-uploaded {
            background-color: #e74c3c;
            color: white;
            border-color: #c0392b;
        }

        /* If you want to add a subtle hover effect (optional) */
        .verification-status-box:hover {
            opacity: 0.9;
        }
        
        /* Updated View ID button styles for uniform appearance */
        .view-id-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            min-width: 120px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
            line-height: 1.2;
        }
        
        .view-id-btn:hover {
            background-color: #2980b9;
            color: white;
            text-decoration: none;
        }
        
        /* Alternative button for "No ID/Proof" state */
        .no-upload-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: not-allowed;
            font-size: 0.875rem;
            min-width: 120px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
            line-height: 1.2;
            opacity: 0.7;
        }
        
        /* Status badge styles */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .bg-success {
            background-color: #2ecc71 !important;
            color: white;
        }
        
        .bg-warning {
            background-color: #f39c12 !important;
            color: white;
        }
        
        .bg-secondary {
            background-color: #e74c3c !important;
            color: white;
        }
        
        .form-select-sm {
            padding: 0.5rem 0.75rem; /* Increased from 0.25rem 0.5rem */
            font-size: 0.875rem;
            border-radius: 0.375rem; /* Slightly more rounded */
            min-width: 120px; /* Ensure minimum width */
            height: auto; /* Let it expand naturally */
        }

        /* Alternative: Remove the -sm class entirely and use regular form-select */
        .verification-dropdown {
            padding: 0.575rem 0.75rem;
            font-size: 0.9rem;
            border-radius: 0.375rem;
            min-width: 130px;
            margin-right: 0.5rem;
        }

        /* Keep the button styling consistent */
        .btn-sm {
            padding: 0.5rem 0.75rem; /* Match the dropdown padding */
            font-size: 0.875rem;
            border-radius: 0.375rem;
            min-height: 38px; /* Ensure same height as dropdown */
        }
        
        /* Sort Dropdown */
        .sort-dropdown {
            margin-bottom: 20px;
        }
        
        /* Sort Dropdown Styles - Match booking page */
        .sort-dropdown .dropdown-toggle {
            background-color: #3498db;
            border: none;
        }
        
        /* Search Form */
        .search-form {
            margin-bottom: 20px;
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
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="staff_dashboard.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a class="nav-link" href="staff_dashboard.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="staff_accounts.php">ACCOUNTS</a></li>
            <li><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
            <li><a class="nav-link" href="staff_services.php">SERVICES</a></li>
            <li><a class="nav-link" href="staff_landingReport.php">REPORTS</a></li>
            <li><a class="nav-link" href="staff_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="staff_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>ACCOUNT MANAGEMENT</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Sort Section -->
        <div class="d-flex justify-content-between mb-3">

        <!-- Sort Dropdown -->
            <div class="dropdown sort-dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-sort-alpha-down"></i> Sort By
                </button>
                <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                    <li><h6 class="dropdown-header">Sort Options</h6></li>
                    <li><a class="dropdown-item" href="?sort=username&order=asc<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Username (A-Z)</a></li>
                    <li><a class="dropdown-item" href="?sort=username&order=desc<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Username (Z-A)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=verification&order=asc<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Verification Status (A-Z)</a></li>
                    <li><a class="dropdown-item" href="?sort=verification&order=desc<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Verification Status (Z-A)</a></li>
                </ul>
            </div>

            <!-- Search Form -->
            <form class="search-form" method="GET" action="">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by username, email, or name" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="staff_accounts.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

         <!-- Customer Accounts Section -->
         <div class="account-section">
            <h3>Customer Accounts</h3>
            <div class="table-responsive">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Email Verification</th>
                            <th>Full Name</th>
                            <th>Contact Number</th>
                            <th>Address</th>
                            <th>Birthday</th>
                            <th>Facebook Profile</th>
                            <th>ID Verification</th>
                            <th>Valid ID</th>
                            <th>Proof of Billing</th>
                            <th>Verification Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['userName']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td>
                                        <?php 
                                        $emailStatus = $customer['emailVerification'] ?? 'unverified';
                                        $emailBadgeClass = ($emailStatus == 'verified') ? 'bg-success' : 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $emailBadgeClass; ?>">
                                            <?php echo ucfirst($emailStatus); ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['contactNumber']); ?></td>
                                    <td>
                                    <?php if (!empty($customer['address'])): ?>
                                        <button type="button" class="view-id-btn" data-bs-toggle="modal" data-bs-target="#viewAddressModal<?php echo $customer['customerId']; ?>">
                                            View Address
                                        </button>
                                        <!-- View Address Modal -->
                                        <div class="modal fade" id="viewAddressModal<?php echo $customer['customerId']; ?>" tabindex="-1" aria-labelledby="viewAddressModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="viewAddressModalLabel">Customer Address</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-upload-btn">
                                            No Address
                                        </div>
                                    <?php endif; ?>
                                </td>
                                    <td class="text-nowrap">
                                        <?php echo htmlspecialchars($customer['birthday']); ?>
                                    </td>
                                    <td>
                                        <?php if ($customer['facebookProfile']): ?>
                                            <?php 
                                            // Ensure the link has http:// or https:// prefix
                                            $fbLink = $customer['facebookProfile'];
                                            if (!preg_match("~^(?:f|ht)tps?://~i", $fbLink)) {
                                                $fbLink = "https://" . $fbLink;
                                            }
                                            ?>
                                            <a href="<?php echo htmlspecialchars($fbLink); ?>" target="_blank">
                                                View Profile
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="verification-form">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['customerId']; ?>">
                                            <input type="hidden" name="uploaded_status" value="<?php echo $customer['idUploaded'] == 'ID Uploaded' ? 'ID not yet Uploaded' : 'ID Uploaded'; ?>">
                                            <div class="verification-status-box <?php echo strtolower(str_replace(' ', '-', $customer['idUploaded'])); ?>">
                                                <?php echo htmlspecialchars($customer['idUploaded']); ?>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['validId'])): ?>
                                            <button type="button" class="view-id-btn" data-bs-toggle="modal" data-bs-target="#viewIdModal<?php echo $customer['customerId']; ?>">
                                                View ID
                                            </button>
                                            
                                            <!-- View Valid ID Modal -->
                                            <div class="modal fade" id="viewIdModal<?php echo $customer['customerId']; ?>" tabindex="-1" aria-labelledby="viewIdModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="viewIdModalLabel">Valid ID</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <img src="data:image/jpeg;base64,<?php echo base64_encode($customer['validId']); ?>" alt="Valid ID" class="img-fluid mb-3" style="max-width: 100%;">
                                                            <?php if (!empty($customer['idNumber'])): ?>
                                                                <p><strong>ID Number:</strong> <?php echo htmlspecialchars($customer['idNumber']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="no-upload-btn">
                                                No ID
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['proofOfBilling'])): ?>
                                            <button type="button" class="view-id-btn" data-bs-toggle="modal" data-bs-target="#viewBillingModal<?php echo $customer['customerId']; ?>">
                                                View Proof
                                            </button>
                                            
                                            <!-- View Proof of Billing Modal -->
                                            <div class="modal fade" id="viewBillingModal<?php echo $customer['customerId']; ?>" tabindex="-1" aria-labelledby="viewBillingModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="viewBillingModalLabel">Proof of Billing</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <img src="data:image/jpeg;base64,<?php echo base64_encode($customer['proofOfBilling']); ?>" alt="Proof of Billing" class="img-fluid mb-3" style="max-width: 100%;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="no-upload-btn">
                                                No Proof
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $customer['accountVerification'] ?? 'not verified';
                                        $badgeClass = '';
                                        if ($status == 'verified') $badgeClass = 'bg-success';
                                        elseif ($status == 'pending') $badgeClass = 'bg-warning text-dark';
                                        else $badgeClass = 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['customerId']; ?>">
                                            <select name="verification_status" class="form-select form-select-sm me-2">
                                                <option value="verified" <?php echo ($status == 'verified') ? 'selected' : ''; ?>>Verified</option>
                                                <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="not verified" <?php echo ($status == 'not verified') ? 'selected' : ''; ?>>Not Verified</option>
                                            </select>
                                            <button type="submit" name="update_verification_status" class="btn btn-sm btn-primary">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center">No customer accounts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>