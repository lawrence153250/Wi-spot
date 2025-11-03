<?php
session_start();
include 'chatbot-widget.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Copyright Policy</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="TOSstyle.css">
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
<div class="tos-container">
    <h1 class="tos-header">
        <i class="bi bi-satellite satellite-icon"></i>
        COPYRIGHT POLICY
    </h1>
    <p>All content on this website, including text, graphics, logos, and images, is the property of Wi-Spot services or its content suppliers and protected by international copyright laws.</p>
    
    <div class="tos-section">
        <h2><i class="bi bi-arrow-right-circle-fill"></i><b>USE OF CONTENT</b></h2>
    <p>You may not reproduce, distribute, or create derivative works from any content on this website without express written permission from Wi-Spot services.</p>
    </div>

    <div class="tos-section">
        <h2><i class="bi bi-arrow-right-circle-fill"></i><b>REPORTING INFRINGEMENT</b></h2>
    <p>If you believe that any content on our website infringes your copyright, please contact us with details of the infringement and we will address the issue promptly.</p>
    </div>
    <div class="tos-section tos-contact">
        <h2><i class="bi bi-headset"></i><b>CONTACT US</b></h2>
        <p>For any copyright-related inquiries:</p>
        
        <div class="contact-methods">
            <div class="contact-method">
                <i class="bi bi-envelope"></i>
                <span>Joshua.napila@gmail.com</span>
            </div>
            <div class="contact-method">
                <i class="bi bi-phone"></i>
                <span>0905 458 5366</span>
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
