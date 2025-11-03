<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Log payment verification attempt
error_log("Payment verification started for booking ID: " . ($_GET['bookingId'] ?? 'unknown') . ", Session ID: " . session_id());

// Verify payment was successful
if (!isset($_GET['bookingId']) || !isset($_GET['paymentType']) || !isset($_GET['amount'])) {
    die("Invalid request parameters");
}

$bookingId = intval($_GET['bookingId']);
$paymentType = $_GET['paymentType'];
$amountPaid = floatval($_GET['amount']);

// ENHANCED: More flexible payment verification
$paymentVerified = false;

// Method 1: Check session data (primary method)
if (isset($_SESSION['payment_info']) && $_SESSION['payment_info']['bookingId'] == $bookingId) {
    $paymentVerified = true;
    error_log("Payment verified via session for booking ID: $bookingId");
} 
// Method 2: Check if payment was already processed (fallback method)
else {
    // Check if this payment was already recorded in database
    $checkStmt = $conn->prepare("SELECT lastPaymentAmount, paymentStatus FROM booking WHERE bookingId = ? AND lastPaymentAmount = ?");
    $checkStmt->bind_param("id", $bookingId, $amountPaid);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        $paymentVerified = true;
        error_log("Payment verified via database check for booking ID: $bookingId");
    }
    $checkStmt->close();
}

if (!$paymentVerified) {
    // Final check: See if booking exists and get current status
    $finalCheck = $conn->prepare("SELECT paymentStatus, paymentBalance FROM booking WHERE bookingId = ?");
    $finalCheck->bind_param("i", $bookingId);
    $finalCheck->execute();
    $finalCheck->bind_result($currentStatus, $currentBalance);
    
    if ($finalCheck->fetch()) {
        // If booking exists and payment seems processed, proceed anyway
        if ($currentStatus == 'Paid' || $currentStatus == 'Partially Paid') {
            $paymentVerified = true;
            error_log("Payment verified via status check. Status: $currentStatus");
        }
    }
    $finalCheck->close();
}

if (!$paymentVerified) {
    die("Payment verification failed. Please contact support with booking ID: " . $bookingId);
}

// First, get the current payment balance, price, and check if there's already a voucher applied
$stmt = $conn->prepare("SELECT paymentBalance, price, voucherCode FROM booking WHERE bookingId = ?");
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$stmt->bind_result($currentBalance, $totalPrice, $existingVoucherCode);
$stmt->fetch();
$stmt->close();

// Debug logging
error_log("Payment Processing - Booking ID: $bookingId, Current Balance: $currentBalance, Total Price: $totalPrice, Amount Paid: $amountPaid, Payment Type: $paymentType");

// Check if a voucher was applied in the session
$voucherApplied = isset($_SESSION['voucher_code']);
$discountAmount = 0;

// FIXED: Proper balance calculation
if ($paymentType === 'fullpayment') {
    // For full payment, balance should be 0 regardless of amount paid
    $newBalance = 0;
    
    // Mark voucher as used if applied
    if ($voucherApplied) {
        $voucherCode = $_SESSION['voucher_code'];
        $customerId = $_SESSION['customerId'] ?? null;
        
        $stmt = $conn->prepare("UPDATE voucher_code 
                               SET isUsed = TRUE, usedDate = NOW(), customerId = ?, bookingId = ?
                               WHERE code = ?");
        $stmt->bind_param("iis", $customerId, $bookingId, $voucherCode);
        $stmt->execute();
        $stmt->close();
        
        // Clear voucher session
        unset($_SESSION['voucher_code']);
        unset($_SESSION['discount_rate']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['original_balance']);
    }
} else {
    // For partial payment - CRITICAL FIX: Calculate based on total price and previous payments
    
    // First, get the sum of all previous payments for this booking
    $paymentSumStmt = $conn->prepare("SELECT COALESCE(SUM(lastPaymentAmount), 0) FROM booking WHERE bookingId = ? AND lastPaymentAmount IS NOT NULL");
    $paymentSumStmt->bind_param("i", $bookingId);
    $paymentSumStmt->execute();
    $paymentSumStmt->bind_result($totalPaidSoFar);
    $paymentSumStmt->fetch();
    $paymentSumStmt->close();
    
    // Calculate total paid including current payment
    $totalPaidIncludingCurrent = $totalPaidSoFar + $amountPaid;
    
    // Calculate new balance
    $newBalance = $totalPrice - $totalPaidIncludingCurrent;
    
    // Ensure balance doesn't go negative
    if ($newBalance < 0) {
        $newBalance = 0;
    }
    
    error_log("Partial Payment Calculation - Total Price: $totalPrice, Previous Payments: $totalPaidSoFar, Current Payment: $amountPaid, New Balance: $newBalance");
    
    // Mark voucher as used only if balance is fully paid after this payment
    if ($voucherApplied && $newBalance <= 0) {
        $voucherCode = $_SESSION['voucher_code'];
        $customerId = $_SESSION['customerId'] ?? null;
        
        $stmt = $conn->prepare("UPDATE voucher_code 
                               SET isUsed = TRUE, usedDate = NOW(), customerId = ?, bookingId = ?
                               WHERE code = ?");
        $stmt->bind_param("iis", $customerId, $bookingId, $voucherCode);
        $stmt->execute();
        $stmt->close();
        
        // Clear voucher session
        unset($_SESSION['voucher_code']);
        unset($_SESSION['discount_rate']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['original_balance']);
    }
}

// Ensure balance doesn't go negative
if ($newBalance < 0) {
    $newBalance = 0;
}

// Determine new payment status
$newStatus = ($newBalance <= 0) ? 'Paid' : 'Partially Paid';

// Update database with the new balance
$updateSql = "UPDATE booking SET 
              paymentStatus = ?, 
              paymentBalance = ?,
              voucherCode = ?,
              lastPaymentAmount = ?,
              lastPaymentDate = NOW()
              WHERE bookingId = ?";

$stmt = $conn->prepare($updateSql);
$voucherCodeToStore = $voucherApplied ? ($_SESSION['voucher_code'] ?? null) : null;
$stmt->bind_param("sddsi", $newStatus, $newBalance, $voucherCodeToStore, $amountPaid, $bookingId);

if ($stmt->execute()) {
    // Payment successfully recorded
    unset($_SESSION['payment_info']);
    $message = "Payment successful! Status updated to: " . $newStatus;
    
    // Get customer email and booking details for confirmation email
    $emailStmt = $conn->prepare("
        SELECT c.email, b.price, b.dateOfBooking, b.dateOfReturn, b.eventLocation, 
            p.packageName, c.firstName 
        FROM booking b
        JOIN customer c ON b.customerId = c.customerId
        JOIN package p ON b.packageId = p.packageId
        WHERE b.bookingId = ?
    ");
    $emailStmt->bind_param("i", $bookingId);
    $emailStmt->execute();
    $emailStmt->bind_result($customerEmail, $bookingPrice, $dateOfBooking, $dateOfReturn, $eventLocation, $packageName, $firstName);
    $emailStmt->fetch();
    $emailStmt->close();
    
    // Include PHPMailer files
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'wispot.servicesph@gmail.com';
        $mail->Password = 'dzij hshz xbqt hwlb'; // Consider using an App Password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Enable debugging (remove in production)
        $mail->SMTPDebug = 0; // 0 = off, 1 = client messages, 2 = client and server messages
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP debug level $level: $str");
        };

        // Recipients
        $mail->setFrom('wispot.servicesph@gmail.com', 'Wi-Spot');
        $mail->addAddress($customerEmail, $firstName); // Use $customerEmail instead of $email
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmation - Booking #' . $bookingId;
        
        
        // Email body
        $emailBody = "
            <h2>Payment Confirmation</h2>
            <p>Dear $firstName,</p>
            <p>Thank you for your payment. Your booking details are as follows:</p>
            
            <table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
                <tr style='background-color: #f2f2f2;'>
                    <th style='padding: 8px; text-align: left;'>Booking ID</th>
                    <td style='padding: 8px;'>#$bookingId</td>
                </tr>
                <tr>
                    <th style='padding: 8px; text-align: left;'>Package Name</th>
                    <td style='padding: 8px;'>$packageName</td>
                </tr>
                <tr style='background-color: #f2f2f2;'>
                    <th style='padding: 8px; text-align: left;'>Booking Date</th>
                    <td style='padding: 8px;'>$dateOfBooking</td>
                </tr>
                <tr>
                    <th style='padding: 8px; text-align: left;'>Return Date</th>
                    <td style='padding: 8px;'>$dateOfReturn</td>
                </tr>
                <tr style='background-color: #f2f2f2;'>
                    <th style='padding: 8px; text-align: left;'>Event Location</th>
                    <td style='padding: 8px;'>$eventLocation</td>
                </tr>
                <tr>
                    <th style='padding: 8px; text-align: left;'>Total Amount</th>
                    <td style='padding: 8px;'>₱" . number_format($bookingPrice, 2) . "</td>
                </tr>
                <tr style='background-color: #f2f2f2;'>
                    <th style='padding: 8px; text-align: left;'>Amount Paid</th>
                    <td style='padding: 8px;'>₱" . number_format($amountPaid, 2) . "</td>
                </tr>
                <tr>
                    <th style='padding: 8px; text-align: left;'>Remaining Balance</th>
                    <td style='padding: 8px;'>₱" . number_format($newBalance, 2) . "</td>
                </tr>
                <tr style='background-color: #f2f2f2;'>
                    <th style='padding: 8px; text-align: left;'>Payment Status</th>
                    <td style='padding: 8px;'>$newStatus</td>
                </tr>
            </table>
            
            <p style='margin-top: 20px;'>If you have any questions about your booking, please contact our support team.</p>
            <p>Thank you for choosing Wi-Spot!</p>
        ";
        
        $mail->Body = $emailBody;
    $mail->AltBody = strip_tags($emailBody);
    
    if ($mail->send()) {
        $emailSent = true;
        error_log("Email successfully sent to $customerEmail");
    } else {
        $emailSent = false;
        error_log("Email failed to send to $customerEmail");
    }
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        $emailSent = false;
    }
    
} else {
    $message = "Payment received but database update failed. Please contact support.";
}

$stmt->close();
$conn->close();

// Calculate progress for display
$paidAmount = $totalPrice - $newBalance;
$paidPercentage = ($paidAmount / $totalPrice) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Success</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .card {
            max-width: 600px;
            margin: 100px auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            font-weight: bold;
        }
        .payment-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .progress {
            height: 20px;
            margin-bottom: 20px;
        }
        .progress-bar {
            background-color: #28a745;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
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

    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white text-center">
                <div class="success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h3>Payment Successful!</h3>
            </div>
            <div class="card-body">
                <div class="payment-details">
                    <p class="text-center"><?php echo htmlspecialchars($message); ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Booking ID:</strong></p>
                        </div>
                        <div class="col-md-6">
                            <p>#<?php echo htmlspecialchars($bookingId); ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Amount Paid:</strong></p>
                        </div>
                        <div class="col-md-6">
                            <p>₱<?php echo number_format($amountPaid, 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Remaining Balance:</strong></p>
                        </div>
                        <div class="col-md-6">
                            <p>₱<?php echo number_format($newBalance, 2); ?></p>
                        </div>
                    </div>
                    
                    <?php if (isset($emailSent) && $emailSent): ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-envelope-check"></i> A confirmation email has been sent to your registered email address.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Payment progress bar -->
                    <div class="mt-4">
                        <h6>Payment Progress</h6>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" 
                                style="width: <?php echo $paidPercentage; ?>%" 
                                aria-valuenow="<?php echo $paidPercentage; ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <?php echo round($paidPercentage); ?>% Paid
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small>₱0</small>
                            <small>₱<?php echo number_format($totalPrice, 2); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="profile.php" class="btn btn-primary">
                        <i class="bi bi-person-circle"></i> Return to Profile
                    </a>
                    <a href="viewtransactions.php?bookingId=<?php echo $bookingId; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-eye"></i> View Booking Details
                    </a>
                </div>
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
</body>
</html>