<?php
session_start();
require 'login_code.php'; // Includes the login logic
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styleslogin.css">
</head>
<body>
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

<div class="container mt-5">
    <div class="row login-wrapper shadow rounded overflow-hidden">
        <div class="col-md-6 login-form p-5 bg-white">
            <h2 class="text-center mb-4">LOGIN FORM</h2>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message; ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group mb-4 position-relative">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-icon">
                        <i class='bx bxs-user'></i>
                        <input type="text" id="username" name="username" class="form-control wi-input" required>
                    </div>
                </div>
                <div class="form-group mb-4 position-relative">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-icon">
                        <i class='bx bxs-lock-alt'></i>
                        <input type="password" id="password" name="password" class="form-control wi-input" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i id="toggleIcon" class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid text-center mt-3">
                    <button type="submit" name="login" class="btn btn-primary">LOGIN</button>
                </div>
                <div class="text-center mt-3">
                    <a href="forgotpassword.php" class="forgot-password-link">Forgot Password?</a>
                </div>
            </form>
            <div class="text-center mt-3">
                <p>Don't have an account? <a href="register.php" class="register-link">Register now</a></p>
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
<script>
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    }
</script>

<script src="scriptlogin.js"></script>
</body>
</html>
