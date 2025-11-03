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

if (!isset($_SESSION['username'])) {
    echo '<div class="alert">You need to log in first. Redirecting to login page...</div>';
    header("Refresh: 3; url=login.php");
    exit();
}

$username = $_SESSION['username'];

require_once 'config.php';

// Fetch user information safely using prepared statements
$stmt = $conn->prepare("SELECT * FROM customer WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $customerId = $user['customerId']; // Fetch customerId from the customer table
} else {
    die("User not found.");
}

$stmt->close();

// Handle form submission for editing booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_booking'])) {
    $bookingId = $_POST['bookingId'];
    $newDateOfBooking = $_POST['dateOfBooking'];
    $newDateOfReturn = $_POST['dateOfReturn'];
    $newEventLocation = $_POST['eventLocation'];
    $newPackageId = $_POST['packageId'];
    
    // Calculate number of days
    $startDate = new DateTime($newDateOfBooking);
    $endDate = new DateTime($newDateOfReturn);
    $interval = $startDate->diff($endDate);
    $numberOfDays = $interval->days + 1; // +1 to include both start and end date
    
    // Get package price
    $packageStmt = $conn->prepare("SELECT price FROM package WHERE packageId = ?");
    $packageStmt->bind_param("i", $newPackageId);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    $package = $packageResult->fetch_assoc();
    $packagePrice = $package['price'];
    $packageStmt->close();
    
    // Calculate new price
    $newPrice = $numberOfDays * $packagePrice;
    
    // Get original booking data
    $originalStmt = $conn->prepare("SELECT * FROM booking WHERE bookingId = ?");
    $originalStmt->bind_param("i", $bookingId);
    $originalStmt->execute();
    $originalResult = $originalStmt->get_result();
    $originalData = $originalResult->fetch_assoc();
    $originalStmt->close();
    
    // Prepare edited data
    $editedData = [
        'dateOfBooking' => $newDateOfBooking,
        'dateOfReturn' => $newDateOfReturn,
        'eventLocation' => $newEventLocation,
        'packageId' => $newPackageId,
        'price' => $newPrice,
        'numberOfDays' => $numberOfDays
    ];
    
    // Save to booking_edits table
    $editStmt = $conn->prepare("INSERT INTO booking_edits (bookingId, original_data, edited_data) VALUES (?, ?, ?)");
    $originalJson = json_encode($originalData);
    $editedJson = json_encode($editedData);
    $editStmt->bind_param("iss", $bookingId, $originalJson, $editedJson);
    
    if ($editStmt->execute()) {
        // Update booking status to "For Approval"
        $updateStmt = $conn->prepare("UPDATE booking SET bookingStatus = 'For Approval' WHERE bookingId = ?");
        $updateStmt->bind_param("i", $bookingId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Success - reload the page to show updated data
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "<script>alert('Error saving booking changes: ".$conn->error."');</script>";
    }
    
    $editStmt->close();
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $bookingId = $_POST['bookingId'];
    $cancelReason = $_POST['cancelReason'];
    
    // Update the booking status to "Cancelled" and add reason
    $cancelStmt = $conn->prepare("UPDATE booking SET bookingStatus = 'Cancelled', cancelReason = ? WHERE bookingId = ?");
    $cancelStmt->bind_param("si", $cancelReason, $bookingId);
    
    if ($cancelStmt->execute()) {
        // Success - reload the page to show updated data
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "<script>alert('Error cancelling booking: ".$conn->error."');</script>";
    }
    
    $cancelStmt->close();
}

// Fetch booking data for the logged-in customer
$sql = "SELECT 
    b.bookingId,
    b.timestamp AS date_booking_created,
    b.dateOfBooking AS date_of_start,
    b.dateOfReturn AS date_of_return,
    b.eventLocation AS event_location,
    p.packageName AS package_chosen,
    p.packageId AS package_id,
    b.price AS total_price,
    b.bookingStatus AS booking_status,
    b.paymentStatus AS payment_status,
    b.cancelReason AS cancel_reason,
    DATEDIFF(b.dateOfReturn, b.dateOfBooking) + 1 AS numberOfDays
FROM booking b
JOIN package p ON b.packageId = p.packageId
WHERE b.customerId = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Also fetch all available packages for the edit form
$packages = [];
$packageQuery = $conn->query("SELECT * FROM package");
if ($packageQuery->num_rows > 0) {
    while ($row = $packageQuery->fetch_assoc()) {
        $packages[] = $row;
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

$stmt->close();
$conn->close();

include 'chatbot-widget.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Transactions</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="transactionstyle.css">
    <style>
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .btn-edit {
            background-color: #ffc107;
            border-color: #ffc107;
        }
        .btn-cancel {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .price-calculation {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body style="background-color: #f0f3fa;"> <nav class="navbar navbar-expand-lg navbar-dark" id="grad">
    <div class="container">
        <a class="navbar-brand" href="index.php"><img src="logoo.png" class="logo"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link" href="booking.php">BOOKING</a></li>
                <li class="nav-item"><a class="nav-link" href="mapcoverage.php">MAP COVERAGE</a></li>
                <li class="nav-item"><a class="nav-link" href="customer_voucher.php">VOUCHERS</a></li>
                <li class="nav-item"><a class="nav-link" href="aboutus.php">ABOUT US</a></li>
            </ul>

            <?php if (isset($_SESSION['username'])): ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><?= htmlspecialchars($_SESSION['username']) ?> <i class="bi bi-person-circle"></i></a>
                    </li>
                </ul>
            <?php else: ?>
                <div class="auth-buttons d-flex flex-column flex-lg-row ms-lg-auto gap-2 mt-2 mt-lg-0">
                    <a class="btn btn-primary" href="login.php">LOGIN</a>
                    <a class="nav-link" href="register.php">SIGN UP</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

    <div class="container mt-6">
        <h2>MY TRANSACTIONS</h2>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date Booking Created</th>
                        <th>Date of Start</th>
                        <th>Date of Return</th>
                        <th>Event Location</th>
                        <th>Package Chosen</th>
                        <th>Total Price</th>
                        <th>Booking Status</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['date_booking_created']); ?></td>
                                <td><?php echo htmlspecialchars($booking['date_of_start']); ?></td>
                                <td><?php echo htmlspecialchars($booking['date_of_return']); ?></td>
                                <td><?php echo htmlspecialchars($booking['event_location']); ?></td>
                                <td><?php echo htmlspecialchars($booking['package_chosen']); ?></td>
                                <td>₱<?php echo number_format($booking['total_price'], 2); ?></td>
                                <td>
                                    <?= htmlspecialchars($booking['booking_status']) ?>

                                    <?php if ($booking['booking_status'] === 'Completed'): ?>
                                        <br>
                                        <a href="customer_feedback.php?bookingId=<?= $booking['bookingId'] ?>" class="btn btn-primary btn-sm mt-1">Give Feedback</a>

                                    <?php elseif ($booking['booking_status'] === 'Cancelled' && !empty($booking['cancel_reason'])): ?>
                                        <br>
                                        <button type="button" class="btn btn-outline-danger btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $booking['bookingId'] ?>">
                                            View Reason
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($booking['payment_status']); ?>
                                    <br>
                                    <?php if ($booking['payment_status'] !== 'Paid' && $booking['booking_status'] !== 'Cancelled'): ?>
                                        <a href="xendit_payment.php?bookingId=<?php echo $booking['bookingId']; ?>" class="btn btn-success btn-sm mt-1">Proceed to Payment</a>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($booking['booking_status'] !== 'Cancelled' && $booking['booking_status'] !== 'Completed'): ?>
                                        <button type="button" class="btn btn-edit btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $booking['bookingId'] ?>">
                                            Edit Booking
                                        </button>
                                        <button type="button" class="btn btn-cancel btn-sm text-white" data-bs-toggle="modal" data-bs-target="#cancelBookingModal<?= $booking['bookingId'] ?>">
                                            Cancel Booking
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Your existing modals remain the same, except for the edit modal which is updated below -->
            
            <?php foreach ($bookings as $booking): ?>
                <!-- Edit Booking Modal with Price Calculation -->
                <div class="modal fade" id="editModal<?= $booking['bookingId'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $booking['bookingId'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-white">
                                <h5 class="modal-title" id="editModalLabel<?= $booking['bookingId'] ?>">Edit Booking #<?= $booking['bookingId'] ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="bookingId" value="<?= $booking['bookingId'] ?>">
                                    <input type="hidden" name="edit_booking" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="dateOfBooking<?= $booking['bookingId'] ?>" class="form-label">New Start Date</label>
                                        <input type="date" class="form-control" id="dateOfBooking<?= $booking['bookingId'] ?>" name="dateOfBooking" value="<?= date('Y-m-d', strtotime($booking['date_of_start'])) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="dateOfReturn<?= $booking['bookingId'] ?>" class="form-label">New Return Date</label>
                                        <input type="date" class="form-control" id="dateOfReturn<?= $booking['bookingId'] ?>" name="dateOfReturn" value="<?= date('Y-m-d', strtotime($booking['date_of_return'])) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="eventLocation<?= $booking['bookingId'] ?>" class="form-label">Event Location</label>
                                        <input type="text" class="form-control" id="eventLocation<?= $booking['bookingId'] ?>" name="eventLocation" value="<?= htmlspecialchars($booking['event_location']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="packageId<?= $booking['bookingId'] ?>" class="form-label">Package</label>
                                        <select class="form-select" id="packageId<?= $booking['bookingId'] ?>" name="packageId" required>
                                            <?php foreach ($packages as $package): ?>
                                                <option value="<?= $package['packageId'] ?>" data-price="<?= $package['price'] ?>" <?= ($package['packageId'] == $booking['package_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($package['packageName']) ?> (₱<?= number_format($package['price'], 2) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- In the edit modal, update the price calculation to use the calculated numberOfDays -->
                                    <div class="price-calculation">
                                        <h6>Price Calculation:</h6>
                                        <div id="calculationDetails<?= $booking['bookingId'] ?>">
                                            <?php
                                            $days = $booking['numberOfDays'];
                                            $pricePerDay = $booking['total_price'] / $days;
                                            ?>
                                            <p><?= $days ?> day(s) × ₱<?= number_format($pricePerDay, 2) ?> = ₱<?= number_format($booking['total_price'], 2) ?></p>
                                        </div>
                                        <div id="newPrice<?= $booking['bookingId'] ?>" class="fw-bold"></div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> After submitting changes, your booking will need to be approved by admin.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Submit for Approval</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Cancel Booking Modal -->
                <div class="modal fade" id="cancelBookingModal<?= $booking['bookingId'] ?>" tabindex="-1" aria-labelledby="cancelBookingModalLabel<?= $booking['bookingId'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="cancelBookingModalLabel<?= $booking['bookingId'] ?>">Cancel Booking #<?= $booking['bookingId'] ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="bookingId" value="<?= $booking['bookingId'] ?>">
                                    <input type="hidden" name="cancel_booking" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="cancelReason<?= $booking['bookingId'] ?>" class="form-label">Reason for Cancellation</label>
                                        <textarea class="form-control" id="cancelReason<?= $booking['bookingId'] ?>" name="cancelReason" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Are you sure you want to cancel this booking? This action cannot be undone.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Cancellation</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
     <div class="foot-container">
            <div class="foot-logo" style="text-align: center; margin-bottom: 1rem;">
            <img src="logofooter.png" alt="Wi-Spot Logo" style="width: 140px;">
        </div>
        <div class="foot-icons">
            <a href="https://www.facebook.com/WiSpotServices" class="bi bi-facebook" target="_blank"></a>
        </div>

        <hr>

        <div class="foot-policy">
            <div class="policy-links">
            <a href="termsofservice.php" target="_blank">TERMS OF SERVICE</a>
            <a href="copyrightpolicy.php" target="_blank">COPYRIGHT POLICY</a>
            <a href="privacypolicy.php" target="_blank">PRIVACY POLICY</a>
            <a href="contactus.php" target="_blank">CONTACT US</a>
            </div>
        </div>

        <hr>

        <div class="foot_text">
            <br>
            <p>&copy;2025 Wi-spot. All rights reserved. Wi-spot and related trademarks and logos are the property of Wi-spot. All other trademarks are the property of their respective owners.</p><br>
        </div>
    </div>
<!-- View Cancel Reason Modal -->
<div class="modal fade" id="cancelModal<?= $booking['bookingId'] ?>" tabindex="-1" aria-labelledby="cancelModalLabel<?= $booking['bookingId'] ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancelModalLabel<?= $booking['bookingId'] ?>">Cancellation Reason</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Booking #<?= $booking['bookingId'] ?> was cancelled for the following reason:</strong></p>
                <div class="alert alert-light">
                    <?= htmlspecialchars($booking['cancel_reason']) ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Set minimum date for date inputs to today and handle price calculation
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = document.querySelectorAll('input[type="date"]');
            
            dateInputs.forEach(input => {
                input.min = today;
                
                // Add event listeners for price calculation
                if (input.id.includes('dateOfBooking') || input.id.includes('dateOfReturn')) {
                    const bookingId = input.id.replace('dateOfBooking', '').replace('dateOfReturn', '');
                    const startDateInput = document.getElementById('dateOfBooking' + bookingId);
                    const endDateInput = document.getElementById('dateOfReturn' + bookingId);
                    const packageSelect = document.getElementById('packageId' + bookingId);
                    const calculationDiv = document.getElementById('calculationDetails' + bookingId);
                    const newPriceDiv = document.getElementById('newPrice' + bookingId);
                    
                    function calculatePrice() {
                        if (startDateInput.value && endDateInput.value && packageSelect.value) {
                            const startDate = new Date(startDateInput.value);
                            const endDate = new Date(endDateInput.value);
                            const days = Math.floor((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
                            const pricePerDay = parseFloat(packageSelect.options[packageSelect.selectedIndex].getAttribute('data-price'));
                            const totalPrice = days * pricePerDay;
                            
                            if (days > 0) {
                                calculationDiv.innerHTML = `<p>${days} day(s) × ₱${pricePerDay.toFixed(2)} = ₱${totalPrice.toFixed(2)}</p>`;
                                newPriceDiv.innerHTML = `New Total Price: ₱${totalPrice.toFixed(2)}`;
                            } else {
                                calculationDiv.innerHTML = `<p class="text-danger">End date must be after start date</p>`;
                                newPriceDiv.innerHTML = '';
                            }
                        }
                    }
                    
                    startDateInput.addEventListener('change', function() {
                        endDateInput.min = this.value;
                        calculatePrice();
                    });
                    
                    endDateInput.addEventListener('change', calculatePrice);
                    packageSelect.addEventListener('change', calculatePrice);
                    
                    // Calculate on modal show
                    const modal = document.getElementById('editModal' + bookingId);
                    modal.addEventListener('shown.bs.modal', calculatePrice);
                }
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Bootstrap version:', bootstrap.Tooltip.VERSION);
            if (typeof bootstrap !== 'undefined') {
                console.log('Bootstrap is loaded correctly');
            } else {
                console.error('Bootstrap is not loaded');
            }
        });
    </script>
</body>
</html>