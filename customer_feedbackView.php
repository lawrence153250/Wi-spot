<?php
// Start the session
session_start();
include 'chatbot-widget.html';
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

$error = '';
$feedbacks = [];

try {
    $stmt = $conn->prepare("
        SELECT 
            f.feedbackId,
            f.bookingId,
            f.internet_speed,
            f.reliability,
            f.signal_strength,
            f.customer_service,
            f.installation_service,
            f.equipment_quality,
            f.overall_rating,
            f.comment,
            f.response,
            f.responseDate,
            f.responseStatus,
            f.timestamp
        FROM feedback f
        WHERE f.customerId = ?
        ORDER BY f.timestamp DESC
    ");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $feedbacks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error = "Error fetching feedback: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Feedback | Wi-Spot</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="feedbackstyle.css">
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

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center mb-4 mt-5">MY FEEDBACK HISTORY</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (empty($feedbacks)): ?>
                    <div class="alert alert-info text-center">You haven't submitted any feedback yet.</div>
                <?php else: ?>
                    <?php foreach ($feedbacks as $feedback): ?>
                    <div class="card feedback-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small">Booking #<?php echo $feedback['bookingId']; ?></span>
                                <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($feedback['timestamp'])); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <h5>OVERALL RATING</h5>
                                <div class="d-flex align-items-center">
                                    <div class="star-rating me-2">
                                        <?php
                                        $rating = $feedback['overall_rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rating ? '★' : '☆';
                                        }
                                        ?>
                                    </div>
                                    <span class="rating-badge"><?php echo $rating; ?>/5</span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span>Internet Speed: </span>
                                        <span class="rating-badge"><?php echo $feedback['internet_speed']; ?>/5</span>
                                    </div>
                                    <div class="mb-2">
                                        <span>Reliability: </span>
                                        <span class="rating-badge"><?php echo $feedback['reliability']; ?>/5</span>
                                    </div>
                                    <div class="mb-2">
                                        <span>Signal Strength: </span>
                                        <span class="rating-badge"><?php echo $feedback['signal_strength']; ?>/5</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span>Customer Service: </span>
                                        <span class="rating-badge"><?php echo $feedback['customer_service']; ?>/5</span>
                                    </div>
                                    <div class="mb-2">
                                        <span>Installation: </span>
                                        <span class="rating-badge"><?php echo $feedback['installation_service']; ?>/5</span>
                                    </div>
                                    <div class="mb-2">
                                        <span>Equipment Quality: </span>
                                        <span class="rating-badge"><?php echo $feedback['equipment_quality']; ?>/5</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($feedback['comment'])): ?>
                            <div class="mb-3">
                                <h5>MY COMMENTS</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['comment'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($feedback['responseStatus'] === 'responded' && !empty($feedback['response'])): ?>
                            <div class="card response-card mt-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0">Staff Response</h5>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y h:i A', strtotime($feedback['responseDate'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['response'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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