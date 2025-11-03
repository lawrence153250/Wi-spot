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

require_once 'config.php';

// Get parameters from URL
$bookingId = isset($_GET['bookingId']) ? intval($_GET['bookingId']) : 0;

// Verify booking ID
if ($bookingId <= 0) {
    die("Invalid booking ID");
}

// Fetch booking details to show user
$stmt = $conn->prepare("SELECT b.*, p.packageName FROM booking b 
                       JOIN package p ON b.packageId = p.packageId 
                       WHERE b.bookingId = ?");
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found");
}

$booking = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Determine if this was a partial or full payment attempt
$paymentType = isset($_GET['paymentType']) ? $_GET['paymentType'] : 'unknown';
$attemptedAmount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// Calculate payment progress
$paidAmount = $booking['price'] - $booking['paymentBalance'];
$paidPercentage = ($paidAmount / $booking['price']) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .payment-failed-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .alert-icon {
            font-size: 72px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .booking-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .btn-retry {
            background-color: #dc3545;
            color: white;
        }
        .btn-retry:hover {
            background-color: #bb2d3b;
            color: white;
        }
        
        .progress {
            height: 20px;
            margin: 10px 0;
        }
        .progress-bar {
            background-color: #28a745;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" id="grad">
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
    <div class="container">
        <div class="payment-failed-container text-center">
            <div class="alert-icon">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <h2 class="text-danger mb-4">Payment Failed</h2>
            <p class="lead">We couldn't process your payment. Please try again or contact support if the problem persists.</p>
            
            <div class="booking-details text-start">
                <h5>Booking Details:</h5>
                <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($bookingId); ?></p>
                <p><strong>Package:</strong> <?php echo htmlspecialchars($booking['packageName']); ?></p>
                <p><strong>Total Price:</strong> ₱<?php echo number_format($booking['price'], 2); ?></p>
                <p><strong>Remaining Balance:</strong> ₱<?php echo number_format($booking['paymentBalance'], 2); ?></p>
                
                <!-- Payment progress bar -->
                <div class="progress">
                    <div class="progress-bar" role="progressbar" 
                         style="width: <?php echo $paidPercentage; ?>%" 
                         aria-valuenow="<?php echo $paidPercentage; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?php echo round($paidPercentage); ?>% Paid
                    </div>
                </div>
                
                <p><strong>Payment Attempt:</strong> 
                    <?php 
                    if ($paymentType === 'partialpayment') {
                        echo "Partial Payment (₱" . number_format($attemptedAmount, 2) . ")";
                    } elseif ($paymentType === 'fullpayment') {
                        echo "Full Payment (₱" . number_format($attemptedAmount, 2) . ")";
                    } else {
                        echo "Payment attempt";
                    }
                    ?>
                </p>
                <p><strong>Current Status:</strong> <?php echo htmlspecialchars($booking['paymentStatus']); ?></p>
            </div>
            
            <div class="d-grid gap-2 d-md-block mt-4">
                <?php if ($paymentType === 'partialpayment' || $paymentType === 'fullpayment'): ?>
                    <a href="payment.php?bookingId=<?php echo $bookingId; ?>" class="btn btn-retry btn-lg me-md-2">
                        <i class="bi bi-arrow-repeat"></i> Try Again
                    </a>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-person"></i> Back to Profile
                </a>
                <a href="contactus.php" class="btn btn-outline-primary btn-lg ms-md-2">
                    <i class="bi bi-headset"></i> Contact Support
                </a>
            </div>
            
            <div class="mt-4 text-muted">
                <small>If you believe this is an error, please contact our support team with your booking ID.</small>
            </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>