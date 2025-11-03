<?php
session_start();
if (isset($_SESSION['selected_equipment'])) {
    unset($_SESSION['selected_equipment']);
}
require_once 'track_visitor.php';
include 'chatbot-widget.html';
// Include config file for database connections
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home page</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<style>
    /* Custom tour styling - more appealing version */
    .introjs-overlay {
        background-color: rgba(33, 37, 41, 0.8) !important;
    }
    
    .introjs-tooltip {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        max-width: 400px;
        font-family: 'Outfit', sans-serif;
    }
    
    .introjs-tooltip-header {
        background-color: #0d6efd;
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 12px 16px;
    }
    
    .introjs-tooltiptext {
        padding: 16px;
        font-size: 16px;
        line-height: 1.5;
    }
    
    .introjs-button {
        border-radius: 8px !important;
        padding: 8px 16px !important;
        font-weight: 500 !important;
        transition: all 0.2s !important;
    }
    
    .introjs-skipbutton {
        background-color: #dc3545 !important;
        color: white !important;
    }
    
    .introjs-skipbutton:hover {
        background-color: #bb2d3b !important;
        transform: translateY(-2px);
    }
    
    .introjs-nextbutton {
        background-color: #0d6efd !important;
        color: white !important;
    }
    
    .introjs-nextbutton:hover {
        background-color: #0b5ed7 !important;
        transform: translateY(-2px);
    }
    
    .introjs-bullets ul li a {
        background-color: #adb5bd !important;
    }
    
    .introjs-bullets ul li a.active {
        background-color: #0d6efd !important;
    }
    
    /* Floating restart tour button */
    .restart-tour-btn {
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 9999;
        background-color: #0d6efd;
        color: white;
        border: none;
        border-radius: 50px;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .restart-tour-btn:hover {
        background-color: #0b5ed7;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
    }
    
    /* Special highlight for chatbot */
    .introjs-helperLayer.chatbot-highlight {
        border-radius: 50% !important;
        box-shadow: 0 0 0 1000px rgba(33, 37, 41, 0.8),
                    0 0 0 4px rgba(13, 110, 253, 0.8),
                    0 0 20px 10px rgba(13, 110, 253, 0.4) !important;
    }
    /* Custom styling for profile tour */
    .custom-profile-tooltip {
        max-width: 350px;
        border-radius: 12px;
    }

    .custom-profile-tooltip .introjs-tooltip-header {
        background-color: #0d6efd;
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 12px 16px;
    }

    .custom-profile-highlight {
        box-shadow: 0 0 0 1000px rgba(0,0,0,0.7),
                    0 0 0 4px rgba(13, 110, 253, 0.8),
                    0 0 20px 10px rgba(13, 110, 253, 0.4);
    }

    .introjs-helperLayer.custom-profile-highlight {
        border-radius: 4px;
    }
</style>
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

<div class="hero">
    <div class="headcontainer">
        <div class="text-content">
            <h1 class="anim">STAY CONNECTED ANYTIME, ANYWHERE WITH WI-SPOT</h1>
            <p class="anim">Tired of dealing with unstable internet at crucial moments? Need a fast, seamless connection for your business or event?</p>
            <a href="booking.php" class="btn btn-primary anim">BOOK NOW</a>
        </div>
    </div>
</div>
<div class = "clients">
    <h1>Trusted by Businesses and Events Nationwide</h1>
</div>
    <div class="clientsslider" style="
        --width: 200px;
        --height: 200px;
        --quantity: 7;
        "> 
        <div class = "list">
            <div class="item" style ="--position: 1"><img src="uploads/client1.png" alt=""></div>
            <div class="item" style ="--position: 2"><img src="uploads/client2.png" alt=""></div>
            <div class="item" style ="--position: 3"><img src="uploads/client3.png" alt=""></div>
            <div class="item" style ="--position: 4"><img src="uploads/client4.png" alt=""></div>
            <div class="item" style ="--position: 5"><img src="uploads/client5.png" alt=""></div>
            <div class="item" style ="--position: 6"><img src="uploads/client6.png" alt=""></div>
            <div class="item" style ="--position: 7"><img src="uploads/client7.png" alt=""></div>
        </div>
    </div>

    <div class="helpcontainer">
    <div class="row">
        <!-- LEFT SIDE: Text -->
        <div class="col-sm-6">
            <h1> MADE FOR PEOPLE ON THE MOVE</h1>
            <p>
                Wi-Spot offers fast, reliable WiFi rental solutions for events, travels, short-term setups, and on-the-go lifestyles. No long-term contracts—just seamless connectivity, anytime, anywhere.
            </p>
        </div>

        <!-- RIGHT SIDE: Cards -->
        <div class="col-sm-6">
            <div class="card card-1">
                <div class="card-body1">
                    <h5 class="card-title">01</h5>
                    <p class="card-text">Businesses</p> 
                </div>
            </div>
            <div class="card card-2">
                <div class="card-body1">
                    <h5 class="card-title">02</h5>
                    <p class="card-text">Events and Livestreams</p> 
                </div>
            </div>
            <div class="card card-3">
                <div class="card-body1">
                    <h5 class="card-title">03</h5>
                    <p class="card-text">Outdoor Enthusiasts</p>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="customizedcontainer">
    <img src="uploads/booking.png" alt="Satellite Internet" class="left-image">
    <div class="text-content">
        <h1>QUICK AND EASY BOOKING, TAILORED TO YOUR NEEDS</h1>
        <p>Quickly customize your satellite internet service to fit your event or business needs with our easy-to-use booking interface.</p>
        <a href="booking.php" class="btn btn-primary">BOOK OUR SERVICE</a>
    </div>
</div>
    <div class="mapcontainer">
                <img src="uploads/maps.png" alt="Map Coverage" class="feature-maps">
                <div class="map-content">
                    <h1>REAL-TIME COVERAGE VISUALIZATION</h1>
                    <p>Visualize and monitor the satellite signal strength at your location in real-time, ensuring reliable connectivity wherever you are.</p>
                <a href="mapcoverage.php" class="btn btn-primary">CHECK COVERAGE</a>
                </div>
            </div>

    <div class="vouchercontainer">
    <div class="voucher-content">
        <h1>GET REWARDS: Discounts & Vouchers with Every Booking</h1>
        <p>Enjoy exclusive discounts and vouchers with our loyalty program. The more you book, the more you save on future events and projects!</p>
        <a href="customer_voucher.php" class="btn btn-primary">CHECK REWARDS</a>
    </div>
    <img src="uploads/discount.png" alt="Discount" class="feature-voucher">
</div>

<!-- Feedback Section -->
<div class="feedback-container">
    <h1 class="feedback-title">WHAT OUR CUSTOMERS SAY</h1>
    
    <div id="feedbackCarousel" class="carousel slide feedback-carousel" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php
            try {
                // Fetch feedback from database
                $sql = "SELECT f.*, c.firstName, c.lastName 
                        FROM feedback f 
                        JOIN customer c ON f.customerId = c.customerId 
                        WHERE f.displayStatus = 'display'
                        ORDER BY f.timestamp DESC
                        LIMIT 5";
                
                $result = $conn->query($sql);
                
                if (!$result) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                if ($result->num_rows > 0) {
                    $active = true;
                    while ($row = $result->fetch_assoc()) {
                        // Calculate average rating
                        $ratings = array_filter([
                            $row['internet_speed'] ?? 0,
                            $row['reliability'] ?? 0,
                            $row['signal_strength'] ?? 0,
                            $row['customer_service'] ?? 0,
                            $row['installation_service'] ?? 0,
                            $row['equipment_quality'] ?? 0
                        ]);

                        $average_rating = !empty($ratings) ? round(array_sum($ratings) / count($ratings), 1) : 0;

                        // Process image - assume photo contains the path (e.g., "uploads/feedback/img123.jpg")
                        $imageDisplay = '';
                        if (!empty($row['photo'])) {
                            $imagePath = trim($row['photo']);
                            if (file_exists($imagePath)) {
                                $imageDisplay = '<div class="text-center mt-3">
                                    <img src="' . htmlspecialchars($imagePath) . '" 
                                         alt="Feedback Photo" class="img-fluid rounded" style="max-height: 200px;">
                                </div>';
                            } else {
                                $imageDisplay = '<div class="text-center mt-3 text-muted">
                                    <small>Photo not found.</small>
                                </div>';
                            }
                        }
                        ?>
                        <div class="carousel-item <?php echo $active ? 'active' : ''; ?>">
                            <div class="feedback-card">
                                <div class="feedback-user">
                                    <div class="user-avatar">
                                        <i class="bi bi-person-circle" style="font-size: 3rem; color: #6c757d;"></i>
                                    </div>
                                    <div class="user-info">
                                        <h5><?php echo htmlspecialchars(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')); ?></h5>
                                        <div class="rating">
                                            <?php 
                                            $full_stars = floor($average_rating);
                                            $half_star = ($average_rating - $full_stars) >= 0.5;

                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $full_stars) {
                                                    echo '<i class="bi bi-star-fill text-warning"></i>';
                                                } elseif ($half_star && $i == $full_stars + 1) {
                                                    echo '<i class="bi bi-star-half text-warning"></i>';
                                                } else {
                                                    echo '<i class="bi bi-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="ms-2"><?php echo $average_rating; ?>/5</span>
                                        </div>
                                        <p><?php echo isset($row['timestamp']) ? date('F j, Y', strtotime($row['timestamp'])) : ''; ?></p>
                                    </div>
                                </div>
                                <div class="feedback-text">
                                    <p><?php echo !empty($row['comment']) ? nl2br(htmlspecialchars($row['comment'])) : 'No comment provided'; ?></p>
                                </div>
                                <?php echo $imageDisplay; ?>
                            </div>
                        </div>
                        <?php
                        $active = false;
                    }
                } else {
                    ?>
                    <div class="carousel-item active">
                        <div class="feedback-card">
                            <div class="feedback-text">
                                <p>No feedback available yet. Be the first to share your experience!</p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } catch (Exception $e) {
                ?>
                <div class="carousel-item active">
                    <div class="feedback-card">
                        <div class="feedback-text">
                            <p>We're having trouble loading feedback.</p>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <?php if (isset($result) && $result->num_rows > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#feedbackCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#feedbackCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
        <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/intro.min.js"></script>
<script>
// Debugged tour implementation
document.addEventListener('DOMContentLoaded', function() {
    // Create restart tour button
    const restartTourBtn = document.createElement('button');
    restartTourBtn.className = 'restart-tour-btn';
    restartTourBtn.innerHTML = `
        <i class="bi bi-info-circle"></i> Show Tour Again
    `;
    restartTourBtn.onclick = startTour;
    document.body.appendChild(restartTourBtn);

    // Check if tour was previously skipped
    if (!localStorage.getItem('wiSpotTourSkipped') && !localStorage.getItem('tourSkipped')) {
        setTimeout(startTour, 1000);
    }
});

// Add this style for the tour buttons
const tourStyles = document.createElement('style');
tourStyles.textContent = `
    .booking-tooltip {
        max-width: 350px;
        border-radius: 12px;
        font-family: 'Outfit', sans-serif;
    }
    .booking-tooltip .introjs-tooltip-header {
        background-color: #0d6efd;
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 12px 16px;
    }
    .booking-highlight {
        box-shadow: 0 0 0 1000px rgba(0,0,0,0.7),
                    0 0 0 4px rgba(13, 110, 253, 0.8),
                    0 0 20px 10px rgba(13, 110, 253, 0.4);
    }
    /* Custom skip button styles */
    .introjs-skipbutton {
        background-color: #dc3545 !important;
        color: white !important;
        padding: 4px 8px !important;
        font-size: 12px !important;
        margin-right: 5px !important;
    }
    .introjs-skipbutton:hover {
        background-color: #c82333 !important;
    }
    /* Adjust other buttons to match */
    .introjs-nextbutton, 
    .introjs-prevbutton, 
    .introjs-donebutton {
        padding: 4px 8px !important;
        font-size: 12px !important;
    }
`;
document.head.appendChild(tourStyles);

function startTour() {
    // Clear any skip flags when manually starting tour
    localStorage.removeItem('wiSpotTourSkipped');
    localStorage.removeItem('tourSkipped');

    // Try to find the chatbot element
    const chatbotElement = document.querySelector('.chatbot-widget-container') || 
                         document.querySelector('.chatbot-button') || 
                         document.querySelector('.chatbot-toggler') ||
                         document.querySelector('.chatbot-container');

    const steps = [
        {
            title: "🌟 Welcome to Wi-Spot!",
            intro: "<div style='text-align:center;'><img src='logoo.png' style='width:120px; margin-bottom:15px;'><br>Let us guide you through our WiFi rental services to help you get started!</div>",
            position: 'center'
        },
        {
            element: document.querySelector('.navbar-brand'),
            title: "🏠 Home Navigation",
            intro: "Our logo is your home button. Click here anytime to return to the homepage.",
            position: 'bottom'
        },
        {
            element: document.querySelector('a[href="booking.php"]'),
            title: "📅 Easy Booking",
            intro: "Click here to start the booking process for our WiFi rental services. Login first to access this page",
            position: 'bottom'
        },
        {
            element: document.querySelector('a[href="mapcoverage.php"]'),
            title: "🗺️ Coverage Map",
            intro: "Click here to visualize your booking and check how many routers are needed for your event location",
            position: 'bottom'
        },
        {
            element: document.querySelector('a[href="customer_voucher.php"]'),
            title: "🎁 Discounts & Vouchers",
            intro: "View available discounts and vouchers you can use for your bookings.",
            position: 'bottom'
        },
        {
            element: document.querySelector('a[href="aboutus.php"]'),
            title: "ℹ️ About Our Company",
            intro: "Learn about our mission, values, and the team behind Wi-Spot.",
            position: 'bottom'
        },
        {
            element: document.querySelector('.carousel'),
            title: "⭐ Customer Feedback",
            intro: "See what our satisfied customers say about our WiFi rental services. Their experiences might help you decide!",
            position: 'top'
        },
        {
            element: document.querySelector('.foot-icons a'),
            title: "👍 Connect With Us",
            intro: "Follow us on Facebook for updates, tips, and special promotions.",
            position: 'top'
        },
        {
            element: document.querySelector('.policy-links a[href="contactus.php"]'),
            title: "📞 Contact Support",
            intro: "Need help? Reach out to our friendly support team here.",
            position: 'top'
        },
        {
            element: chatbotElement,
            title: "💬 24/7 Chat Support",
            intro: "Our AI assistant is always available to answer your questions! Click the chat icon at the bottom right anytime for instant help.",
            position: 'left'
        },
        {
            element: document.querySelector('.auth-buttons') || document.querySelector('.navbar-nav.ms-auto'),
            title: "🔐 Your Account",
            intro: "Sign up or log in here to start the booking process and manage your rentals",
            position: 'bottom'
        },
        {
            title: "🚀 You're All Set!",
            intro: "Start exploring Wi-Spot! Ready to book? Click the 'BOOK NOW' button on our homepage.",
            position: 'center'
        }
    ];

    // Filter out any null elements (in case selectors don't match)
    const validSteps = steps.filter(step => {
        if (step.element && !document.body.contains(step.element)) {
            console.warn('Element not found for step:', step.title);
            return false;
        }
        return true;
    });

    // Initialize and start the tour
    const tour = introJs();
    tour.setOptions({
        steps: validSteps,
        nextLabel: 'Next →',
        prevLabel: '← Back',
        skipLabel: 'Skip',
        doneLabel: 'Finish',
        exitOnOverlayClick: false
    });

    tour.oncomplete(function() {
        localStorage.setItem('wiSpotTourSkipped', 'true');
    }).onexit(function() {
        localStorage.setItem('wiSpotTourSkipped', 'true');
    }).start();
}

// Profile tour (only for logged-in users)
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['username'])) { ?>
        if (!localStorage.getItem('profileTourShown')) {
            setTimeout(startProfileTour, 1500);
        }
    <?php } ?>
});

function startProfileTour() {
    const profileElement = document.querySelector('.nav-link[href="profile.php"]') || 
                         document.querySelector('a[href="profile.php"]') ||
                         document.querySelector('.navbar-nav.ms-auto .nav-link');

    if (!profileElement) {
        console.log("Profile element not found");
        return;
    }

    introJs().setOptions({
        steps: [
            {
                element: profileElement,
                title: "👤 Your Profile",
                intro: "Now that you're logged in, click your username here to access your profile dashboard.",
                position: 'bottom'
            },
            {
                element: document.querySelector('a[href="profile.php"]'),
                title: "Profile Features",
                intro: `<div style="text-align:left">
                        <p>In your profile you can:</p>
                        <ul>
                            <li>📝 View and edit personal information</li>
                            <li>🆔 Upload verification documents</li>
                            <li>📷 Change your profile picture</li>
                            <li>💳 View transaction history</li>
                            <li>💰 Make payments for bookings</li>
                            <li>📦 Track current rentals</li>
                        </ul>
                        </div>`,
                position: 'right'
            }
        ],
        nextLabel: 'Next →',
        prevLabel: '← Back',
        doneLabel: 'Got It!',
        exitOnOverlayClick: false,
        tooltipClass: 'custom-profile-tooltip',
        highlightClass: 'custom-profile-highlight'
    }).oncomplete(function() {
        localStorage.setItem('profileTourShown', 'true');
    }).onexit(function() {
        localStorage.setItem('profileTourShown', 'true');
    }).start();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>