<?php
session_start();
include 'chatbot-widget.html';
if (isset($_SESSION['selected_equipment'])) {
    unset($_SESSION['selected_equipment']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="aboutusstyle.css">
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
    
    <section class="about-section py-5">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-sm-8 text-center">
                    <br>
                    <br>
                    <h1 class="section-title">ABOUT WI-SPOT</h1>
                    <div class="title-underline"></div>
                </div>
            </div>
            <div class="row align-items-stretch">
                <div class="col-lg-6 mb-4">
                    <div class="info-card">
                        <h3>OUR STORY</h3>
                        <p>Wi-spot began as a single piso net hotspot - a coin-operated internet station created by our founder to serve local connectivity needs. What started as a neighborhood service quickly gained popularity for its reliability and fair pricing.
                        </p>
                        <p>
                        Time passed, we expanded into satellite internet rental solutions for homes and businesses, along with additional network services. Today, Wi-spot provides affordable, reliable connectivity across the region.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
            <div class="info-card">
                <h3>CONNECT WITH US</h3>
                <p>We'd love to hear from you! Connect with us on social media or contact us directly.</p>
                <div class="social-links mt-4 d-flex align-items-center"> <a href="https://www.facebook.com/WiSpotServices" class="social-icon me-3" target="_blank" aria-label="Facebook"> <i class="bi bi-facebook fs-1"></i> </a>

                    <a href="tel:+639054585366" class="text-decoration-none text-dark fs-3"> <i class="bi bi-telephone-fill me-2"></i> +63 905 458 5366
                    </a>
            </div>
</div>
</div>
            </div>
        </div>
    </section>

    <!-- Our Services in Action Section -->
    <section class="services-gallery py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-sm-8 text-center">
                    <h2 class="section-title" style="font-size: 48px;">OUR SERVICES</h2>
                    <div class="title-underline"></div>
                </div>
            </div>
            <div class="row align-items-stretch">
                <div class="col-lg-4 mb-4">
                    <div class="info-card">
                        <img src="img\imgAbout\43879514_1898695700223196_4750494246128058368_n.jpg" alt="Piso Net Hotspot Service" class="service-image mb-3">
                        <h5>PISO-NET HOTSPOT</h5>
                        <p>Our coin-operated internet stations serving local communities with reliable connectivity.</p>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="info-card">
                        <img src="img\imgAbout\482346809_1153865653107873_2072301733285116936_n.jpg" alt="Satellite Internet Installation" class="service-image mb-3">
                        <h5>SATELLITE INTERNET</h5>
                        <p>Professional installation of satellite internet for homes and businesses across the region.</p>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="info-card">
                        <img src="img\imgAbout\481044633_1147189410442164_3886537667126314387_n.jpg" alt="Network Services" class="service-image mb-3">
                        <h5>NETWORK SOLUTIONS</h5>
                        <p>Comprehensive network services and technical support for all your connectivity needs.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="ownercontainer">
            <img src="img\imgAbout\482326580_1152594703234968_8879343790075071330_n.jpg" alt="Owner Portrait" style="width:150px; height:150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px;">
    
            <p>Wi-Spot was founded with the vision of addressing local connectivity challenges, beginning with a single piso net hotspot. Under the leadership of our founder, the service quickly gained recognition for its reliability and affordability. Over time, it has grown into a trusted provider of satellite internet rentals and network solutions, committed to delivering accessible and dependable connectivity across the region.</p>
            <p><strong>Joshua Ed Napila</strong></p>
            <p><i>Founder / owner of Wi-Spot Services</i></p>
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

    <style>
        .info-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .info-card:hover {
            transform: translateY(-5px);
        }

        .info-card h3, .info-card h5 {
            margin-bottom: 1.5rem;
            color: #333;
        }

        .service-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        .services-gallery {
            background-color: #f8f9fa;
        }
    </style>
    
</body>
</html>
