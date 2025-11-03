<?php
session_start();

require_once 'config.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);

        if ($stmt->execute()) {
            echo "<script>alert('Your message has been submitted successfully.'); window.location.href='contactus.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error submitting message.');</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('All fields are required.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(rgba(249, 251, 255, 0.5)), url('aboutusbg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: Arial, sans-serif;
            color: #333;
        }
        .contact-section { margin-top: 80px; margin-bottom: 60px; }
        .contact-container {
            max-width: 800px; margin: 0 auto; padding: 40px; background: white;
            border-radius: 10px; box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .contact-container h2 {
            color: #0d6efd; margin-bottom: 30px; text-align: center; font-weight: 600;
        }
        .form-group { margin-bottom: 25px; }
        .form-group label { font-weight: 500; margin-bottom: 8px; display: block; }
        .form-control {
            height: 45px; border-radius: 5px; border: 1px solid #ced4da;
            padding: 10px 15px; font-size: 16px; width: 100%;
        }
        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        textarea.form-control { height: auto; min-height: 150px; resize: vertical; }
        .btn-submit {
            background-color: #0d6efd; border: none; padding: 12px 30px;
            font-size: 16px; font-weight: 500; width: 100%; transition: all 0.3s;
        }
        .btn-submit:hover { background-color: #0b5ed7; transform: translateY(-2px); }
        @media (max-width: 768px) {
            .contact-container { padding: 20px; margin: 20px; }
        }
        .contact-info {
            margin-top: 40px; background: #f1f8ff;
            padding: 25px; border-radius: 8px;
        }
        .contact-info h3 { color: #0d6efd; margin-bottom: 20px; }
        .contact-info p { margin-bottom: 10px; }
        .contact-icon { font-size: 24px; color: #0d6efd; margin-right: 10px; }
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

<div class="contact-section">
    <div class="contact-container">
        <h2>CONTACT US</h2>
        <form method="POST">
            <div class="form-group">
                <label for="name">NAME:</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">EMAIL:</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="subject">SUBJECT:</label>
                <input type="text" id="subject" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="message">MESSAGE:</label>
                <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-submit">Submit</button>
        </form>

        <div class="contact-info">
            <h3>OTHER WAYS TO REACH US</h3>
            <p><i class="bi bi-envelope contact-icon"></i> Joshua.napila@gmail.com</p>
            <p><i class="bi bi-telephone contact-icon"></i> 0905 458 5366</p>
            <p><i class="bi bi-geo-alt contact-icon"></i> Cainta, Rizal</p>
            <p><i class="bi bi-clock contact-icon"></i> Customer Support Hours: Mon-Fri, 9AM-5PM PST</p>
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
