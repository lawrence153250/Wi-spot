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

// Database connection and PHPMailer setup
require_once 'config.php';
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send booking confirmation email
function sendBookingConfirmationEmail($customerEmail, $bookingId, $bookingDetails) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'wispot.servicesph@gmail.com';
        $mail->Password   = 'dzij hshz xbqt hwlb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('wispot.servicesph@gmail.com', 'Wi-Spot Services');
        $mail->addAddress($customerEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Booking #' . $bookingId . ' Has Been Confirmed';
        
        $mail->Body = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { margin-top: 20px; padding: 20px; background-color: #f8f9fa; text-align: center; font-size: 12px; }
                .booking-details { margin: 20px 0; }
                .detail-row { margin-bottom: 10px; }
                .detail-label { font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Booking Confirmation</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($bookingDetails['customer_firstname']) . ',</p>
                    <p>We are pleased to inform you that your booking with Wi-Spot Services has been confirmed.</p>
                    
                    <div class="booking-details">
                        <h3>Booking Details</h3>
                        <div class="detail-row">
                            <span class="detail-label">Booking ID:</span> ' . $bookingId . '
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Package:</span> ' . htmlspecialchars($bookingDetails['package_chosen']) . '
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Event Date:</span> ' . htmlspecialchars($bookingDetails['date_of_start']) . ' to ' . htmlspecialchars($bookingDetails['date_of_return']) . '
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Location:</span> ' . htmlspecialchars($bookingDetails['event_location']) . '
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Price:</span> ₱' . number_format($bookingDetails['total_price'], 2) . '
                        </div>
                    </div>
                    
                    <p>Thank you for choosing Wi-Spot Services. If you have any questions, please don\'t hesitate to contact us.</p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Wi-Spot Services. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Dear " . $bookingDetails['customer_firstname'] . ",\n\n" .
                        "We are pleased to inform you that your booking with Wi-Spot Services has been confirmed.\n\n" .
                        "Booking Details:\n" .
                        "Booking ID: " . $bookingId . "\n" .
                        "Package: " . $bookingDetails['package_chosen'] . "\n" .
                        "Event Date: " . $bookingDetails['date_of_start'] . " to " . $bookingDetails['date_of_return'] . "\n" .
                        "Location: " . $bookingDetails['event_location'] . "\n" .
                        "Total Price: ₱" . number_format($bookingDetails['total_price'], 2) . "\n\n" .
                        "Thank you for choosing Wi-Spot Services. If you have any questions, please don't hesitate to contact us.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $bookingId = $_POST['booking_id'];
        $action = $_POST['action'];
        
        // First, get customer email and details for potential notification
        $stmt = $conn->prepare("SELECT c.email, c.firstName, c.lastName, p.packageName, b.dateOfBooking, b.dateOfReturn, b.eventLocation, b.price 
                               FROM booking b 
                               JOIN customer c ON b.customerId = c.customerId 
                               JOIN package p ON b.packageId = p.packageId 
                               WHERE b.bookingId = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $customerData = $result->fetch_assoc();
        $stmt->close();
        
        $bookingDetails = [
            'customer_firstname' => $customerData['firstName'],
            'package_chosen' => $customerData['packageName'],
            'date_of_start' => date("F j, Y", strtotime($customerData['dateOfBooking'])),
            'date_of_return' => date("F j, Y", strtotime($customerData['dateOfReturn'])),
            'event_location' => $customerData['eventLocation'],
            'total_price' => $customerData['price']
        ];
        
        if ($action === 'update_status' && isset($_POST['new_status'])) {
            // Update booking status to the selected value
            $newStatus = $_POST['new_status'];
            $stmt = $conn->prepare("UPDATE booking SET bookingStatus = ? WHERE bookingId = ?");
            $stmt->bind_param("si", $newStatus, $bookingId);
            $stmt->execute();
            $stmt->close();
            
            // Send email if status changed to Confirmed
            if ($newStatus === 'Confirmed' && !empty($customerData['email'])) {
                $emailSent = sendBookingConfirmationEmail($customerData['email'], $bookingId, $bookingDetails);
                if (!$emailSent) {
                    $_SESSION['error'] = "Booking status updated but failed to send confirmation email.";
                }
            }
            
            $_SESSION['message'] = "Booking status updated successfully!";
        } 
        elseif ($action === 'update_connection' && isset($_POST['new_connection_status'])) {
            // Update connection status to the selected value
            $newStatus = $_POST['new_connection_status'];
            $stmt = $conn->prepare("UPDATE booking SET connectionStatus = ? WHERE bookingId = ?");
            $stmt->bind_param("si", $newStatus, $bookingId);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['message'] = "Connection status updated successfully!";
        }
        elseif ($action === 'cancel_booking' && isset($_POST['cancel_reason'])) {
            // Handle cancellation with reason
            $reason = $_POST['cancel_reason'];
            $stmt = $conn->prepare("UPDATE booking SET bookingStatus = 'Cancelled', cancelReason = ? WHERE bookingId = ?");
            $stmt->bind_param("si", $reason, $bookingId);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['message'] = "Booking #$bookingId has been cancelled successfully.";
        }
        else {
            // Original accept/decline logic (for backward compatibility)
            $stmt = $conn->prepare("UPDATE booking SET bookingStatus = ? WHERE bookingId = ?");
            $status = ($action === 'accept') ? 'Confirmed' : 'Cancelled';
            $stmt->bind_param("si", $status, $bookingId);
            $stmt->execute();
            $stmt->close();
            
            // Send email if booking was accepted
            if ($action === 'accept' && !empty($customerData['email'])) {
                $emailSent = sendBookingConfirmationEmail($customerData['email'], $bookingId, $bookingDetails);
                if (!$emailSent) {
                    $_SESSION['error'] = "Booking accepted but failed to send confirmation email.";
                }
            }
            
            $_SESSION['message'] = "Booking has been " . strtolower($status) . " successfully.";
        }
        
        // Redirect to avoid form resubmission
        header("Location: staff_booking.php");
        exit();
    }
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'timestamp';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Fetch all bookings with customer information
$sql = "SELECT 
            b.bookingId,
            b.timestamp AS date_booking_created,
            b.dateOfBooking AS date_of_start,
            b.dateOfReturn AS date_of_return,
            b.eventLocation AS event_location,
            p.packageName AS package_chosen,
            b.price AS total_price,
            b.bookingStatus AS booking_status,
            b.connectionStatus AS connection_status,
            b.paymentStatus AS payment_status,
            b.cancelReason,
            c.username AS customer_username,
            c.firstName AS customer_firstname,
            c.lastName AS customer_lastname,
            c.contactNumber AS customer_contact
        FROM booking b
        JOIN package p ON b.packageId = p.packageId
        JOIN customer c ON b.customerId = c.customerId";

// Add sorting
switch ($sort) {
    case 'event_date':
        $sql .= " ORDER BY b.dateOfBooking $order";
        break;
    case 'package':
        $sql .= " ORDER BY p.packageName $order";
        break;
    case 'status':
        $sql .= " ORDER BY b.bookingStatus $order";
        break;
    default:
        $sql .= " ORDER BY b.timestamp $order";
        break;
}

$result = $conn->query($sql);

$bookings = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Function to format a date string to "F j, Y" format
function formatDate($dateString) {
    return date("F j, Y", strtotime($dateString));
}

// Format the dates in the bookings array
foreach ($bookings as &$booking) {
    $booking['date_booking_created'] = formatDate($booking['date_booking_created']);
    $booking['date_of_start'] = formatDate($booking['date_of_start']);
    $booking['date_of_return'] = formatDate($booking['date_of_return']);
}
unset($booking); // Break the reference with the last element

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Booking Management</title>
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
        
        /* Status Badges */
        .status-pending {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-confirmed {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .status-in-progress {
            color: #3498db;
            font-weight: 600;
        }
        
        .status-completed {
            color: #9b59b6;
            font-weight: 600;
        }
        
        .status-cancelled {
            color: #e74c3c;
            font-weight: 600;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* Tooltip Styles */
        .tooltip-inner {
            max-width: 300px;
            text-align: left;
            background-color: #f8f9fa;
            color: #212529;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow::before, 
        .bs-tooltip-top .tooltip-arrow::before {
            border-top-color: #dee2e6;
        }

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
        /* Connection Status Styles */
        .connection-connecting {
            color: #ffc107; /* Yellow for connecting */
            font-weight: bold;
        }
        .connection-connected {
            color: #28a745; /* Green for connected */
            font-weight: bold;
        }
        .connection-connection-error {
            color: #dc3545; /* Red for error */
            font-weight: bold;
        }
        
        /* Connection Option Styles in Modal */
        .connection-option.connecting {
            display: block;
            padding: 8px;
            margin: 5px 0;
            background-color: #fff3cd;
            border-radius: 4px;
        }
        .connection-option.connected {
            display: block;
            padding: 8px;
            margin: 5px 0;
            background-color: #d4edda;
            border-radius: 4px;
        }
        .connection-option.connection-error {
            display: block;
            padding: 8px;
            margin: 5px 0;
            background-color: #f8d7da;
            border-radius: 4px;
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
            <li class="active"><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
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
            <h2>BOOKING MANAGEMENT</h2>
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
        
        <!-- Sort Dropdown -->
        <div class="dropdown sort-dropdown mb-3">
            <button class="btn btn-primary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-sort-alpha-down"></i> Sort By
            </button>
            <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                <li><h6 class="dropdown-header">Sort Options</h6></li>
                <li><a class="dropdown-item" href="?sort=timestamp&order=desc">Newest First</a></li>
                <li><a class="dropdown-item" href="?sort=timestamp&order=asc">Oldest First</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?sort=event_date&order=asc">Event Date (Oldest First)</a></li>
                <li><a class="dropdown-item" href="?sort=event_date&order=desc">Event Date (Newest First)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?sort=package&order=asc">Package (A-Z)</a></li>
                <li><a class="dropdown-item" href="?sort=package&order=desc">Package (Z-A)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?sort=status&order=asc">Status (A-Z)</a></li>
                <li><a class="dropdown-item" href="?sort=status&order=desc">Status (Z-A)</a></li>
            </ul>
        </div>
        
        <div class="table-responsive">
            <table class="bookings-table">
               <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Date Created</th>
                        <th>Event Dates</th>
                        <th>Location</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Booking Status</th>
                        <th>Connection Status</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?><br>
                                    <small><?php echo htmlspecialchars($booking['customer_username']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['customer_contact']); ?></td>
                                <td class="text-nowrap"><?php echo htmlspecialchars($booking['date_booking_created']); ?></td>
                                <td class="text-nowrap">
                                    <?php echo htmlspecialchars($booking['date_of_start']); ?> -<br>
                                    <?php echo htmlspecialchars($booking['date_of_return']); ?>
                                </td>
                                <td>
                                    <?php if (!empty($booking['event_location'])): ?>
                                        <button type="button" class="view-id-btn" data-bs-toggle="modal" data-bs-target="#locationModal<?php echo $booking['bookingId']; ?>">
                                            View Location
                                        </button>

                                        <!-- Location Modal -->
                                        <div class="modal fade" id="locationModal<?php echo $booking['bookingId']; ?>" tabindex="-1" aria-labelledby="locationModalLabel<?php echo $booking['bookingId']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="locationModalLabel<?php echo $booking['bookingId']; ?>">Event Location</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($booking['event_location'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-upload-btn">
                                            No Location
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($booking['package_chosen']); ?></td>
                                <td>₱<?php echo number_format($booking['total_price'], 2); ?></td>
                                <td class="<?php echo 'status-' . strtolower(str_replace(' ', '-', $booking['booking_status'])); ?>">
                                    <?php echo htmlspecialchars($booking['booking_status']); ?>
                                </td>
                                <td class="<?php echo 'connection-' . strtolower(str_replace(' ', '-', $booking['connection_status'])); ?>">
                                    <?php echo htmlspecialchars($booking['connection_status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($booking['payment_status']); ?></td>
                                <td>
                                    <?php if (strtolower($booking['booking_status']) == 'pending'): ?>
                                        <div class="action-buttons">
                                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $booking['bookingId']; ?>">
                                                Accept
                                            </button>
                                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" 
                                                    data-bs-target="#cancelBookingModal" data-booking-id="<?php echo $booking['bookingId']; ?>">
                                                Decline
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="action-buttons">
                                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $booking['bookingId']; ?>">
                                                Update Status
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#updateConnectionModal<?php echo $booking['bookingId']; ?>">
                                                Update Connection
                                            </button>
                                            <?php if (strtolower($booking['booking_status']) != 'cancelled'): ?>
                                                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" 
                                                        data-bs-target="#cancelBookingModal" data-booking-id="<?php echo $booking['bookingId']; ?>">
                                                    Cancel
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!empty($booking['cancelReason'])): ?>
                                                <button class="btn btn-sm btn-outline-secondary mt-1" data-bs-toggle="tooltip" 
                                                        title="<?php echo htmlspecialchars($booking['cancelReason']); ?>">
                                                    <i class="bi bi-info-circle"></i> View Reason
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Accept Booking Modal -->
                            <div class="modal fade" id="acceptModal<?php echo $booking['bookingId']; ?>" tabindex="-1" aria-labelledby="acceptModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="post">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingId']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="acceptModalLabel">Accept Booking #<?php echo $booking['bookingId']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <h6>Booking Details:</h6>
                                                <p>
                                                    <strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?><br>
                                                    <strong>Current Package:</strong> <?php echo htmlspecialchars($booking['package_chosen']); ?><br>
                                                    <strong>Event Dates:</strong> <?php echo htmlspecialchars($booking['date_of_start']); ?> to <?php echo htmlspecialchars($booking['date_of_return']); ?><br>
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($booking['event_location']); ?>
                                                </p>
                                                
                                                <hr>
                                                
                                                <h6>Package Options:</h6>
                                                <p>Select the package for this booking:</p>
                                                
                                                <div class="modal-item-list">
                                                    <?php
                                                    // Reconnect to database for package query
                                                    require 'config.php';
                                                    if ($conn->connect_error) {
                                                        die("Connection failed: " . $conn->connect_error);
                                                    }
                                                    
                                                    // Fetch all packages
                                                    $package_sql = "SELECT packageId, packageName, status FROM package ORDER BY packageName";
                                                    $package_result = $conn->query($package_sql);
                                                    
                                                    if ($package_result->num_rows > 0) {
                                                        while ($package = $package_result->fetch_assoc()) {
                                                            $status_class = ($package['status'] == 'available') ? 'text-success' : 'text-danger';
                                                            $disabled = ($package['status'] != 'available') ? 'disabled' : '';
                                                            echo '<div class="form-check mb-2">';
                                                            echo '<input class="form-check-input package-radio" type="radio" name="selected_package" value="'.$package['packageId'].'" id="package'.$package['packageId'].'"';
                                                            // Preselect the current package
                                                            if ($package['packageName'] == $booking['package_chosen']) {
                                                                echo ' checked';
                                                            }
                                                            echo ' '.$disabled.'>';
                                                            echo '<label class="form-check-label" for="package'.$package['packageId'].'">';
                                                            echo htmlspecialchars($package['packageName']);
                                                            echo ' <span class="'.$status_class.'">('.$package['status'].')</span>';
                                                            echo '</label>';
                                                            echo '</div>';
                                                        }
                                                    } else {
                                                        echo '<p>No packages found in the system.</p>';
                                                    }
                                                    $conn->close();
                                                    ?>
                                                </div>
                                                
                                                <div class="alert alert-info mt-3">
                                                    <i class="bi bi-info-circle"></i> Only packages with "available" status can be selected. 
                                                    If no suitable package is available, please decline the booking and advise the customer.
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <label for="staffNotes" class="form-label">Staff Notes:</label>
                                                    <textarea class="form-control" id="staffNotes" name="staff_notes" rows="3" 
                                                            placeholder="Add any notes about package selection or special instructions"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Confirm Acceptance</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateStatusModal<?php echo $booking['bookingId']; ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingId']; ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="updateStatusModalLabel">Update Booking Status #<?php echo $booking['bookingId']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>
                                                    <strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?><br>
                                                    <strong>Current Status:</strong> <span class="<?php echo 'status-' . strtolower(str_replace(' ', '-', $booking['booking_status'])); ?>"><?php echo htmlspecialchars($booking['booking_status']); ?></span><br>
                                                    <strong>Event Dates:</strong> <?php echo htmlspecialchars($booking['date_of_start']); ?> to <?php echo htmlspecialchars($booking['date_of_return']); ?>
                                                </p>
                                                
                                                <hr>
                                                
                                                <h6>Select New Status:</h6>
                                                <div class="status-selector">
                                                    <label class="status-option pending">
                                                        <input type="radio" name="new_status" value="Pending" <?php echo ($booking['booking_status'] == 'Pending') ? 'checked' : ''; ?>>
                                                        Pending
                                                    </label>
                                                    <label class="status-option confirmed">
                                                        <input type="radio" name="new_status" value="Confirmed" <?php echo ($booking['booking_status'] == 'Confirmed') ? 'checked' : ''; ?>>
                                                        Confirmed
                                                    </label>
                                                    <label class="status-option in-progress">
                                                        <input type="radio" name="new_status" value="In-progress" <?php echo ($booking['booking_status'] == 'In-progress') ? 'checked' : ''; ?>>
                                                        In-progress
                                                    </label>
                                                    <label class="status-option completed">
                                                        <input type="radio" name="new_status" value="Completed" <?php echo ($booking['booking_status'] == 'Completed') ? 'checked' : ''; ?>>
                                                        Completed
                                                    </label>
                                                    <label class="status-option cancelled">
                                                        <input type="radio" name="new_status" value="Cancelled" <?php echo ($booking['booking_status'] == 'Cancelled') ? 'checked' : ''; ?>>
                                                        Cancelled
                                                    </label>
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <label for="statusNotes" class="form-label">Status Update Notes:</label>
                                                    <textarea class="form-control" id="statusNotes" name="status_notes" rows="3" 
                                                            placeholder="Add any notes about this status change"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Status</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>


                            <!-- Update Connection Status Modal -->
                            <div class="modal fade" id="updateConnectionModal<?php echo $booking['bookingId']; ?>" tabindex="-1" aria-labelledby="updateConnectionModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingId']; ?>">
                                            <input type="hidden" name="action" value="update_connection">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="updateConnectionModalLabel">Update Connection Status #<?php echo $booking['bookingId']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>
                                                    <strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?><br>
                                                    <strong>Current Status:</strong> <span class="<?php echo 'connection-' . strtolower(str_replace(' ', '-', $booking['connection_status'])); ?>"><?php echo htmlspecialchars($booking['connection_status']); ?></span><br>
                                                    <strong>Event Dates:</strong> <?php echo htmlspecialchars($booking['date_of_start']); ?> to <?php echo htmlspecialchars($booking['date_of_return']); ?>
                                                </p>
                                                
                                                <hr>
                                                
                                                <h6>Select New Connection Status:</h6>
                                                <div class="status-selector">
                                                    <label class="connection-option connecting">
                                                        <input type="radio" name="new_connection_status" value="Connecting" <?php echo ($booking['connection_status'] == 'Connecting') ? 'checked' : ''; ?>>
                                                        Connecting
                                                    </label>
                                                    <label class="connection-option connected">
                                                        <input type="radio" name="new_connection_status" value="Connected" <?php echo ($booking['connection_status'] == 'Connected') ? 'checked' : ''; ?>>
                                                        Connected
                                                    </label>
                                                    <label class="connection-option connection-error">
                                                        <input type="radio" name="new_connection_status" value="Connection error" <?php echo ($booking['connection_status'] == 'Connection error') ? 'checked' : ''; ?>>
                                                        Connection error
                                                    </label>
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <label for="connectionNotes" class="form-label">Connection Update Notes:</label>
                                                    <textarea class="form-control" id="connectionNotes" name="connection_notes" rows="3" 
                                                            placeholder="Add any notes about this connection status change"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Connection Status</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No bookings found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- Inside your foreach loop, after the Update Status Modal -->

    <!-- Cancel Booking Confirmation Modal -->
<div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="cancelBookingModalLabel">Confirm Booking Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel booking #<span id="bookingIdDisplay"></span>?</p>
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    <input type="hidden" name="action" value="cancel_booking">
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation:</label>
                        <textarea class="form-control" id="cancelReason" name="cancel_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
    // Cancel Booking Modal Handler
    document.addEventListener('DOMContentLoaded', function() {
        var cancelBookingModal = document.getElementById('cancelBookingModal');
        if (cancelBookingModal) {
            cancelBookingModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var bookingId = button.getAttribute('data-booking-id');
                var modal = this;
                modal.querySelector('#cancelBookingId').value = bookingId;
                modal.querySelector('#bookingIdDisplay').textContent = bookingId;
            });
        }
        
        // Enable tooltips for cancellation reasons
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>
