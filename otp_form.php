<?php
$email = $_POST['email'] ?? '';

if (!$email) {
    echo "Error: Email address not provided.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <title>OTP Verification</title>
    <style>
     .modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0; 
    top: 0;
    width: 100%; 
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 500px;
    border-radius: 5px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.input-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 20px 0;
}

input {
    padding: 10px;
    margin: 10px 0;
    width: 100%;
    max-width: 350px;
    box-sizing: border-box;
    border: 1px solid #ddd;
    border-radius: 4px;
}

button {
    padding: 12px 20px;
    margin: 10px 0;
    width: auto;
    max-width: 350px;
    box-sizing: border-box;
    background-color: #1847d0;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

button:hover {
    background-color:rgb(18, 52, 152);
}

#response {
    margin: 15px 0;
    padding: 12px;
    border-radius: 4px;
    text-align: center;
}

.success {
    background-color: #dff0d8;
    color: #3c763d;
    border: 1px solid #d6e9c6;
}

.error {
    background-color: #f2dede;
    color: #a94442;
    border: 1px solid #ebccd1;
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

 <div style="display: flex; flex-direction: column; align-items: center; margin-top: 150px; margin-bottom: 130px;">
    <h1>VERIFY YOUR EMAIL</h1>
    <button id="verifyBtn" style="margin-top: 20px;">Verify Your Email</button>
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
    <!-- Request OTP Modal -->
    <div id="requestOTPModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('requestOTPModal')">&times;</span>
            <h2>Email Verification</h2>
            <p>Enter your email address to receive an OTP:</p>
            <form id="requestOTPForm">
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <input type="hidden" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <button type="submit">Send OTP</button>
            </form>
            <div id="requestResponse"></div>
        </div>
    </div>

    <!-- Verify OTP Modal -->
    <div id="verifyOTPModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('verifyOTPModal')">&times;</span>
            <h2>Enter OTP</h2>
            <p>We've sent a 6-digit code to your email. Please enter it below:</p>
            <form id="verifyOTPForm">
                <input type="hidden" id="verifyEmail" name="email">
                <input type="text" id="otp" name="otp" placeholder="Enter OTP" required maxlength="6">
                <button type="submit">Verify OTP</button>
            </form>
            <div id="verifyResponse"></div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('successModal')">&times;</span>
        <h2>Verification Successful!</h2>
        <p>Your email has been successfully verified.</p>
        <a class="nav-link" href="profile.php">Continue</a>
    </div>
</div>


<script>
    // Open request OTP modal
    document.getElementById('verifyBtn').addEventListener('click', function() {
        document.getElementById('requestOTPModal').style.display = 'block';
    });

    // Close modal function
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Send OTP
    document.getElementById('requestOTPForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const email = document.getElementById('email').value.trim();
        const responseDiv = document.getElementById('requestResponse');
        const submitBtn = this.querySelector('button[type="submit"]');

        responseDiv.className = '';
        responseDiv.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        try {
            const response = await fetch('otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_otp',
                    email: email
                })
            });

            const data = await response.json();
            responseDiv.className = data.status;
            responseDiv.textContent = data.message;

            if (data.status === 'success') {
                document.getElementById('requestOTPModal').style.display = 'none';
                document.getElementById('verifyEmail').value = email;
                document.getElementById('verifyOTPModal').style.display = 'block';
            }
        } catch (error) {
            responseDiv.className = 'error';
            responseDiv.textContent = 'Error sending OTP. Please try again.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send OTP';
        }
    });

    // Verify OTP
    document.getElementById('verifyOTPForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const email = document.getElementById('verifyEmail').value.trim();
        const otp = document.getElementById('otp').value.trim();
        const responseDiv = document.getElementById('verifyResponse');
        const submitBtn = this.querySelector('button[type="submit"]');

        responseDiv.className = '';
        responseDiv.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Verifying...';

        try {
            const response = await fetch('otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'verify_otp',
                    email: email,       // from verifyEmail hidden input
                    otp: otp            // from user input field
                })
            });


            const data = await response.json();
            responseDiv.className = data.status;
            responseDiv.textContent = data.message;

            if (data.status === 'success') {
                document.getElementById('verifyOTPModal').style.display = 'none';
                document.getElementById('successModal').style.display = 'block';
            }
        } catch (error) {
            responseDiv.className = 'error';
            responseDiv.textContent = 'Error verifying OTP.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Verify OTP';
        }
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
</script>
</body>
</html>
