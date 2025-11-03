<?php 
session_start(); 

// Initialize field-specific errors
$fieldErrors = [
    'firstname' => '',
    'lastname' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'birthday' => '',
    'contactnumber' => '',
    'address' => ''
];

// Process errors from session
if (isset($_SESSION['errors'])) {
    foreach ($_SESSION['errors'] as $error) {
        // Map general errors to specific fields where possible
        if (strpos($error, 'Username') !== false) {
            $fieldErrors['username'] = $error;
        } elseif (strpos($error, 'Email') !== false) {
            $fieldErrors['email'] = $error;
        } elseif (strpos($error, 'Contact number') !== false) {
            $fieldErrors['contactnumber'] = $error;
        } elseif (strpos($error, 'Password') !== false) {
            if (strpos($error, 'Confirm') !== false) {
                $fieldErrors['confirm_password'] = $error;
            } else {
                $fieldErrors['password'] = $error;
            }
        } elseif (strpos($error, 'First name') !== false) {
            $fieldErrors['firstname'] = $error;
        } elseif (strpos($error, 'Last name') !== false) {
            $fieldErrors['lastname'] = $error;
        } elseif (strpos($error, 'Birthday') !== false) {
            $fieldErrors['birthday'] = $error;
        } elseif (strpos($error, 'Address') !== false) {
            $fieldErrors['address'] = $error;
        }
    }
    unset($_SESSION['errors']);
}

// Repopulate form fields if available
$formData = $_SESSION['form_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="registerstyle.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .alert-danger {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
    </style>
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

<div class="container mt-5">
    <div class="row login-wrapper shadow rounded overflow-hidden">

        <!-- LEFT SIDE -->
        <div class="col-md-6 login-image position-relative text-white d-flex justify-content-center align-items-center">
            <div class="login-overlay-text text-center p-4">
                <h2 class="fw-bold">Join Wi-Spot Today</h2>
                <p class="lead">Empowering businesses, events, and communities with trusted connectivity.</p>
            </div>
        </div>

 <!-- RIGHT SIDE -->
        <div class="col-md-6 login-form p-5 bg-white">
            <h2 class="text-center mb-4">SIGN UP</h2>

            <!-- SUCCESS OR ERROR MESSAGE -->
            <?php
                if (isset($_GET['success']) && $_GET['success'] == 1) {
                    echo '<div class="alert alert-success text-center">Registration Successful!</div>';
                } elseif (isset($_GET['error'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($_GET['error']) . '</div>';
                }
                
                // Display validation errors
                if (isset($_SESSION['errors'])) {
                    echo '<div class="alert alert-danger">';
                    foreach ($_SESSION['errors'] as $error) {
                        echo htmlspecialchars($error) . '<br>';
                    }
                    echo '</div>';
                    unset($_SESSION['errors']);
                }
                
                // Get form data for repopulation
                $formData = $_SESSION['form_data'] ?? [];
                unset($_SESSION['form_data']);
            ?>

            <form method="POST" action="register_code.php" id="registrationForm" novalidate>
                <div class="form-group mb-3">
                    <label for="firstname">First name</label>
                    <?php if (!empty($fieldErrors['firstname'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['firstname']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="text" id="firstname" name="firstname" class="form-control wi-input <?= !empty($fieldErrors['firstname']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['firstname'] ?? '') ?>" required>
                    <div class="error-message" id="firstnameError"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="lastname">Last name</label>
                    <?php if (!empty($fieldErrors['lastname'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['lastname']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="text" id="lastname" name="lastname" class="form-control wi-input <?= !empty($fieldErrors['lastname']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['lastname'] ?? '') ?>" required>
                    <div class="error-message" id="lastnameError"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="username">Username</label>
                    <?php if (!empty($fieldErrors['username'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['username']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="text" id="username" name="username" class="form-control wi-input <?= !empty($fieldErrors['username']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['username'] ?? '') ?>" required>
                    <div class="error-message" id="usernameError"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="email">Email</label>
                    <?php if (!empty($fieldErrors['email'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['email']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="email" id="email" name="email" class="form-control wi-input <?= !empty($fieldErrors['email']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                    <div class="error-message" id="emailError"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="password">Password</label>
                    <?php if (!empty($fieldErrors['password'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['password']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control wi-input <?= !empty($fieldErrors['password']) ? 'is-invalid' : '' ?>" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="passwordError"></div>
                    <small class="form-text text-muted">Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character.</small>
                </div>

                <div class="form-group mb-3">
                    <label for="confirm_password">Confirm Password</label>
                    <?php if (!empty($fieldErrors['confirm_password'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['confirm_password']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control wi-input <?= !empty($fieldErrors['confirm_password']) ? 'is-invalid' : '' ?>" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="error-message" id="confirmPasswordError"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="birthday">Birthday</label>
                    <?php if (!empty($fieldErrors['birthday'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['birthday']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="date" id="birthday" name="birthday" class="form-control wi-input <?= !empty($fieldErrors['birthday']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['birthday'] ?? '') ?>" required>
                    <div class="error-message" id="birthdayError"></div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="contactnumber">Contact Number</label>
                    <?php if (!empty($fieldErrors['contactnumber'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['contactnumber']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="text" id="contactnumber" name="contactnumber" class="form-control wi-input <?= !empty($fieldErrors['contactnumber']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['contactnumber'] ?? '') ?>" required>
                    <div class="error-message" id="contactnumberError"></div>
                    <small class="form-text text-muted">Format: 09 followed by 9 digits (11 digits total, e.g., 09123456789)</small>
                </div>
                
                <div class="form-group mb-3">
                    <label for="address">Address</label>
                    <?php if (!empty($fieldErrors['address'])): ?>
                        <div class="alert alert-danger p-2 mb-2">
                            <?= htmlspecialchars($fieldErrors['address']) ?>
                        </div>
                    <?php endif; ?>
                    <input type="text" id="address" name="address" class="form-control wi-input <?= !empty($fieldErrors['address']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($formData['address'] ?? '') ?>" required>
                    <div class="error-message" id="addressError"></div>
                </div>
                
                <div class="form-group mb-4">
                    <label for="facebookProfile">Facebook Profile Link</label>
                    <input type="text" id="facebookProfile" name="facebookProfile" class="form-control wi-input">
                    <div class="error-message" id="facebookProfileError"></div>
                </div>
                
                <div class="d-grid text-center">
                    <button type="submit" name="register" class="btn btn-primary" id="submitBtn">SUBMIT</button>
                </div>
            </form>
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
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    
    // Show password toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }
    
    if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }

    // Client-side validation
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    function validateForm() {
        let isValid = true;
        
        // Reset error messages
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Validate required fields
        const requiredFields = ['firstname', 'lastname', 'username', 'email', 'password', 'confirm_password', 'birthday', 'contactnumber', 'address'];
        requiredFields.forEach(field => {
            const element = document.getElementById(field);
            if (!element.value.trim()) {
                document.getElementById(field + 'Error').textContent = 'This field is required';
                element.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        if (!isValid) return false;
        
        // Validate email
        const email = document.getElementById('email').value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            document.getElementById('emailError').textContent = 'Please enter a valid email address.';
            document.getElementById('email').classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate password (using passwordField instead of password)
        const password = passwordField.value;
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        if (!passwordRegex.test(password)) {
            document.getElementById('passwordError').textContent = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
            passwordField.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate password match
        const confirmPassword = confirmPasswordField.value;
        if (password !== confirmPassword) {
            document.getElementById('confirmPasswordError').textContent = 'Passwords do not match.';
            confirmPasswordField.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate birthday
        const birthday = document.getElementById('birthday').value;
        if (birthday) {
            const birthDate = new Date(birthday);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                document.getElementById('birthdayError').textContent = 'You must be at least 18 years old to register.';
                document.getElementById('birthday').classList.add('is-invalid');
                isValid = false;
            }
        }
        
        // Validate contact number
        const contactNumber = document.getElementById('contactnumber').value;
        const contactRegex = /^09\d{9}$/;
        if (!contactRegex.test(contactNumber)) {
            document.getElementById('contactnumberError').textContent = 'Please enter a valid Philippine number starting with 09 followed by 9 digits (11 digits total).';
            document.getElementById('contactnumber').classList.add('is-invalid');
            isValid = false;
        }
        
        // Validate Facebook profile link (optional)
        const facebookProfile = document.getElementById('facebookProfile').value;
        if (facebookProfile.trim() !== '') {
            const facebookRegex = /^(https?:\/\/)?(www\.)?facebook\.com\/[a-zA-Z0-9._-]+\/?$/;
            if (!facebookRegex.test(facebookProfile)) {
                document.getElementById('facebookProfileError').textContent = 'Please enter a valid Facebook profile URL.';
                document.getElementById('facebookProfile').classList.add('is-invalid');
                isValid = false;
            }
        }
        
        return isValid;
    }

    // Add these functions to your existing script
    function checkUsernameAvailability() {
        const username = document.getElementById('username').value.trim();
        if (username.length < 3) return; // Don't check for very short usernames
        
        fetch('check_availability.php?field=username&value=' + encodeURIComponent(username))
            .then(response => response.json())
            .then(data => {
                const errorElement = document.getElementById('usernameError');
                if (data.available) {
                    errorElement.textContent = '';
                    document.getElementById('username').classList.remove('is-invalid');
                } else {
                    errorElement.textContent = 'Username already taken';
                    document.getElementById('username').classList.add('is-invalid');
                }
            });
    }

    function checkEmailAvailability() {
        const email = document.getElementById('email').value.trim();
        if (!email.includes('@')) return; // Basic email format check
        
        fetch('check_availability.php?field=email&value=' + encodeURIComponent(email))
            .then(response => response.json())
            .then(data => {
                const errorElement = document.getElementById('emailError');
                if (data.available) {
                    errorElement.textContent = '';
                    document.getElementById('email').classList.remove('is-invalid');
                } else {
                    errorElement.textContent = 'Email already registered';
                    document.getElementById('email').classList.add('is-invalid');
                }
            });
    }

    function checkContactNumberAvailability() {
        const contactnumber = document.getElementById('contactnumber').value.trim();
        if (contactnumber.length < 11) return; // Don't check incomplete numbers
        
        fetch('check_availability.php?field=contactnumber&value=' + encodeURIComponent(contactnumber))
            .then(response => response.json())
            .then(data => {
                const errorElement = document.getElementById('contactnumberError');
                if (data.available) {
                    errorElement.textContent = '';
                    document.getElementById('contactnumber').classList.remove('is-invalid');
                } else {
                    errorElement.textContent = 'Contact number already registered';
                    document.getElementById('contactnumber').classList.add('is-invalid');
                }
            });
    }

    // Add event listeners for the fields
    document.getElementById('username').addEventListener('blur', checkUsernameAvailability);
    document.getElementById('email').addEventListener('blur', checkEmailAvailability);
    document.getElementById('contactnumber').addEventListener('blur', checkContactNumberAvailability);
});
</script>
</body>
</html>