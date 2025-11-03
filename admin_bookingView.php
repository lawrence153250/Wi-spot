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

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $editId = $_POST['editId'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Get the edit request
        $stmt = $conn->prepare("SELECT * FROM booking_edits WHERE editId = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit = $result->fetch_assoc();
        $stmt->close();
        
        if ($edit) {
            $editedData = json_decode($edit['edited_data'], true);
            
            // Update the original booking
            $updateStmt = $conn->prepare("UPDATE booking SET 
                dateOfBooking = ?,
                dateOfReturn = ?,
                eventLocation = ?,
                packageId = ?,
                price = ?,
                bookingStatus = 'Confirmed'
                WHERE bookingId = ?");
            $updateStmt->bind_param(
                "ssssdi",
                $editedData['dateOfBooking'],
                $editedData['dateOfReturn'],
                $editedData['eventLocation'],
                $editedData['packageId'],
                $editedData['price'],
                $edit['bookingId']
            );
            
            if ($updateStmt->execute()) {
                // Mark edit as approved
                $conn->query("UPDATE booking_edits SET edit_status = 'Approved', processed_at = NOW() WHERE editId = $editId");
                $message = "Booking changes approved successfully.";
            } else {
                $error = "Error updating booking: " . $conn->error;
            }
            
            $updateStmt->close();
        }
    } elseif ($action === 'reject') {
        // Mark edit as rejected
        $conn->query("UPDATE booking_edits SET edit_status = 'Rejected', processed_at = NOW() WHERE editId = $editId");
        
        // Set booking status back to Confirmed
        $bookingId = $_POST['bookingId'];
        $conn->query("UPDATE booking SET bookingStatus = 'Confirmed' WHERE bookingId = $bookingId");
        
        $message = "Booking changes rejected.";
    }
}

// Fetch pending edit requests
$edits = [];
$query = "SELECT be.*, b.customerId, c.username, p.packageName 
          FROM booking_edits be
          JOIN booking b ON be.bookingId = b.bookingId
          JOIN customer c ON b.customerId = c.customerId
          JOIN package p ON b.packageId = p.packageId
          WHERE be.edit_status = 'Pending'";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $edits[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Booking Approvals</title>
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
        
        .sidebar-menu li.active {
            background-color: #34485f;
        }
        
        .sidebar-menu li a.nav-link {
            color: #FFFFFF;
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            background-color: #3498db;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .diff-old {
            background-color: #ffe6e6;
            text-decoration: line-through;
        }
        
        .diff-new {
            background-color: #e6ffe6;
            font-weight: bold;
        }
        
        .badge-changed {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-action {
            border-radius: 5px;
            font-weight: 500;
            padding: 8px 20px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #f39c12;
            color: white;
        }
        
        .price-highlight {
            font-weight: 700;
            color: #27ae60;
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
            <li><a class="nav-link" href="admin_services.php">SERVICES</a></li>
            <li class="active"><a class="nav-link" href="admin_booking.php">BOOKING MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_management.php">REPORTS MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="admin_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Booking Change Approvals</h2>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($edits)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">No pending booking changes to approve</h4>
                        <p class="text-muted">All booking change requests have been processed</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($edits as $edit): 
                    $original = json_decode($edit['original_data'], true);
                    $edited = json_decode($edit['edited_data'], true);
                    
                    // Calculate number of days
                    $originalStart = new DateTime($original['dateOfBooking']);
                    $originalEnd = new DateTime($original['dateOfReturn']);
                    $originalDays = $originalEnd->diff($originalStart)->days + 1;
                    
                    $editedStart = new DateTime($edited['dateOfBooking']);
                    $editedEnd = new DateTime($edited['dateOfReturn']);
                    $editedDays = $editedEnd->diff($editedStart)->days + 1;
                    
                    // Get edited package name
                    $editedPackageStmt = $conn->prepare("SELECT packageName FROM package WHERE packageId = ?");
                    $editedPackageStmt->bind_param("i", $edited['packageId']);
                    $editedPackageStmt->execute();
                    $editedPackageResult = $editedPackageStmt->get_result();
                    $editedPackage = $editedPackageResult->fetch_assoc();
                    $editedPackageName = $editedPackage ? $editedPackage['packageName'] : 'Unknown Package';
                    $editedPackageStmt->close();
                    ?>
                    
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Booking #<?= $edit['bookingId'] ?></h5>
                                <small class="d-block">Requested by: <?= htmlspecialchars($edit['username']) ?></small>
                            </div>
                            <span class="status-badge status-pending">Pending Approval</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Original Booking Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <dl class="row mb-0">
                                                <dt class="col-sm-5">Booking Dates:</dt>
                                                <dd class="col-sm-7"><?= date('M j, Y', strtotime($original['dateOfBooking'])) ?> to <?= date('M j, Y', strtotime($original['dateOfReturn'])) ?></dd>
                                                
                                                <dt class="col-sm-5">Duration:</dt>
                                                <dd class="col-sm-7"><?= $originalDays ?> day(s)</dd>
                                                
                                                <dt class="col-sm-5">Event Location:</dt>
                                                <dd class="col-sm-7"><?= htmlspecialchars($original['eventLocation']) ?></dd>
                                                
                                                <dt class="col-sm-5">Package:</dt>
                                                <dd class="col-sm-7"><?= htmlspecialchars($edit['packageName']) ?></dd>
                                                
                                                <dt class="col-sm-5">Total Price:</dt>
                                                <dd class="col-sm-7 price-highlight">₱<?= number_format($original['price'], 2) ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Requested Changes</h6>
                                        </div>
                                        <div class="card-body">
                                            <dl class="row mb-0">
                                                <dt class="col-sm-5">Booking Dates:</dt>
                                                <dd class="col-sm-7 <?= $original['dateOfBooking'] != $edited['dateOfBooking'] ? 'diff-new' : '' ?>">
                                                    <?= date('M j, Y', strtotime($edited['dateOfBooking'])) ?> to <?= date('M j, Y', strtotime($edited['dateOfReturn'])) ?>
                                                    <?php if ($original['dateOfBooking'] != $edited['dateOfBooking']): ?>
                                                        <span class="badge badge-changed ms-2">Changed</span>
                                                    <?php endif; ?>
                                                </dd>
                                                
                                                <dt class="col-sm-5">Duration:</dt>
                                                <dd class="col-sm-7 <?= $originalDays != $editedDays ? 'diff-new' : '' ?>">
                                                    <?= $editedDays ?> day(s)
                                                    <?php if ($originalDays != $editedDays): ?>
                                                        <span class="badge badge-changed ms-2">Changed</span>
                                                    <?php endif; ?>
                                                </dd>
                                                
                                                <dt class="col-sm-5">Event Location:</dt>
                                                <dd class="col-sm-7 <?= $original['eventLocation'] != $edited['eventLocation'] ? 'diff-new' : '' ?>">
                                                    <?= htmlspecialchars($edited['eventLocation']) ?>
                                                    <?php if ($original['eventLocation'] != $edited['eventLocation']): ?>
                                                        <span class="badge badge-changed ms-2">Changed</span>
                                                    <?php endif; ?>
                                                </dd>
                                                
                                                <dt class="col-sm-5">Package:</dt>
                                                <dd class="col-sm-7 <?= $original['packageId'] != $edited['packageId'] ? 'diff-new' : '' ?>">
                                                    <?= htmlspecialchars($editedPackageName) ?>
                                                    <?php if ($original['packageId'] != $edited['packageId']): ?>
                                                        <span class="badge badge-changed ms-2">Changed</span>
                                                    <?php endif; ?>
                                                </dd>
                                                
                                                <dt class="col-sm-5">Total Price:</dt>
                                                <dd class="col-sm-7 price-highlight <?= $original['price'] != $edited['price'] ? 'diff-new' : '' ?>">
                                                    ₱<?= number_format($edited['price'], 2) ?>
                                                    <?php if ($original['price'] != $edited['price']): ?>
                                                        <span class="badge badge-changed ms-2">Changed</span>
                                                    <?php endif; ?>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="editId" value="<?= $edit['editId'] ?>">
                                    <input type="hidden" name="bookingId" value="<?= $edit['bookingId'] ?>">
                                    <button type="submit" name="action" value="reject" class="btn btn-action btn-danger">
                                        <i class="bi bi-x-circle"></i> Reject
                                    </button>
                                    <button type="submit" name="action" value="approve" class="btn btn-action btn-success">
                                        <i class="bi bi-check-circle"></i> Approve
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    // Close connection at the very end
    $conn->close();
    ?>
</body>
</html>