<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Remove or comment out the Composer autoloader line
// require 'vendor/autoload.php';

// Manually load PHPMailer classes - adjust paths as needed
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$success_message = '';
$error_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the form submission
    require_once 'config.php';

    try {
        $email = trim($_POST['email'] ?? '');
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }

        // Check if email exists in database
        $stmt = $conn->prepare("SELECT customerId FROM customer WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Email not found in our system");
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Clear previous tokens for the email
        $conn->query("DELETE FROM password_reset_tokens WHERE email = '$email'");

        // Insert new token
        $stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires_at);
        $stmt->execute();

        // If resetpassword.php is in a 'members' subdirectory
        $reset_link = 'https://wispotservices.great-site.net/resetpassword.php?' . http_build_query([
            'token' => $token,
            'email' => $email
        ]);

        // Send email with reset link
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
            $mail->setFrom('wispot.servicesph@gmail.com', 'Wi-Spot');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(false); // Set to false for plain text email
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "We received a request to reset your password. Click the link below to reset your password:\n\n"
                           . "$reset_link\n\n"
                           . "This link will expire in 1 hour. If you didn't request this, please ignore this email.";

            $mail->send();
            
            $success_message = "Password reset link has been sent to your email!";
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $e->getMessage());
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Wi-Spot</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styleslogin.css">
    <style>
        .login-wrapper {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .login-image {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('login-bg.jpg');
            background-size: cover;
            background-position: center;
            min-height: 400px;
        }
        .login-overlay-text {
            padding: 2rem;
        }
        .form-control {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
        }
        .btn-primary {
            background-color: #4e73df;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            width: 100%;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body style="background-color: #f0f3fa;">
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

                <div class="auth-buttons d-flex flex-column flex-lg-row ms-lg-auto gap-2 mt-2 mt-lg-0">
                    <a class="btn btn-primary" href="login.php">LOGIN</a>
                    <a class="nav-link" href="register.php">SIGN UP</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row login-wrapper shadow rounded overflow-hidden">
            <div class="col-md-6 login-form p-5 bg-white">
                <h2>Forgot Password</h2>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <form method="POST" action="forgotpassword.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Request Reset Link</button>
                </form>
                <div class="mt-3">
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
            <div class="col-md-6 login-image position-relative text-white d-flex justify-content-center align-items-center">
                <div class="login-overlay-text text-center">
                    <h2 class="fw-bold">Connect Smarter with Wi-Spot</h2>
                    <p class="lead">Reliable connectivity for businesses, events, and communities—anywhere you need it.</p>
                    <p>Sign in to manage your services and stay connected, all in one platform.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- footer -->
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