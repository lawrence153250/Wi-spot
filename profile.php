<?php
// Start the session
session_start();

if (isset($_SESSION['selected_equipment'])) {
    unset($_SESSION['selected_equipment']);
}
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

$sql = "SELECT * FROM customer WHERE username = '$username'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();


// Upload Profile Image Function
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profileImage'])) {
    $profileImage = $_FILES['profileImage']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($profileImage);
    if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $target_file)) {
        $sql = "UPDATE customer SET profileImage = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $target_file, $_SESSION['username']);
        $stmt->execute();
        $message = "Image uploaded successfully!";
        $messageType = "success";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
    } else {
        $message = "Error Uploading Image!";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
    }
}

// Update Profile function with security enhancements
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    // Sanitize inputs
    $firstName = trim(htmlspecialchars($_POST['firstName']));
    $lastName = trim(htmlspecialchars($_POST['lastName']));
    $email = trim(htmlspecialchars($_POST['email']));
    $birthday = $_POST['birthday'];
    $contactNumber = trim(htmlspecialchars($_POST['contactNumber']));
    $address = trim(htmlspecialchars($_POST['address']));
    $facebookProfile = trim(htmlspecialchars($_POST['facebookProfile']));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Validate age (must be at least 18)
    $birthDate = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    if ($age < 18) {
        $message = "You must be at least 18 years old";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Validate Philippine contact number format
    if (!preg_match('/^09\d{9}$/', $contactNumber)) {
    $message = "Invalid Philippine number format. Must start with 09 followed by 9 digits (11 digits total)";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Validate Facebook profile URL
    if (!preg_match('/^(https?:\/\/)?(www\.)?facebook\.com\/[a-zA-Z0-9._-]+\/?$/', $facebookProfile)) {
        $message = "Invalid Facebook profile URL";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Check if email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT * FROM customer WHERE email = ? AND username != ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "Email already exists";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Update query using prepared statement
    $sql = "UPDATE customer SET 
                firstName = ?, 
                lastName = ?, 
                email = ?, 
                birthday = ?, 
                contactNumber = ?, 
                address = ?, 
                facebookProfile = ? 
            WHERE username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $birthday, $contactNumber, $address, $facebookProfile, $username);

    if ($stmt->execute()) {
        $message = "Profile Updated successfully!";
        $messageType = "success";
        // Update session variables if needed
        $_SESSION['email'] = $email;
    } else {
        $message = "Error Updating Profile: " . $conn->error;
        $messageType = "error";
    }
    
    $stmt->close();
    echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
    exit();
}

// Reset password function with security enhancements
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // Validate new password requirements
    if (strlen($new_password) < 8 || 
        !preg_match('/[A-Z]/', $new_password) || 
        !preg_match('/[a-z]/', $new_password) || 
        !preg_match('/[0-9]/', $new_password) || 
        !preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $message = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Validate password match
    if ($new_password !== $confirm_new_password) {
        $message = "New password and Confirm new password do not match!";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        exit();
    }

    // Get user data using prepared statement
    $stmt = $conn->prepare("SELECT * FROM customer WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (password_verify($current_password, $user['password'])) {
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password using prepared statement
        $stmt = $conn->prepare("UPDATE customer SET password = ? WHERE username = ?");
        $stmt->bind_param("ss", $hashed_new_password, $username);

        if ($stmt->execute()) {
            $message = "Password has been reset successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating password: " . $conn->error;
            $messageType = "error";
        }
    } else {
        $message = "Current password is incorrect!";
        $messageType = "error";
    }
    
    $stmt->close();
    echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
    exit();
}

//  Upload Id Function
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uploadId'])) {
    $idNumber = htmlspecialchars($_POST['idNumber']);

    // Check if file is uploaded
    if (isset($_FILES['validId']) && $_FILES['validId']['error'] == 0) {
        $fileTmpPath = $_FILES['validId']['tmp_name'];
        $fileType = $_FILES['validId']['type'];

        // Allowed file types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($fileType, $allowedTypes)) {
            $message = "Invalid file type. Only JPG and PNG are allowed";
            $messageType = "error";
            echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
            exit();
        }

        // Read the image file as binary data
        $validId = file_get_contents($fileTmpPath);

        // Prepare SQL query using prepared statement
        $stmt = $conn->prepare("UPDATE customer SET validId = ?, idNumber = ? WHERE username = ?");
        $stmt->bind_param("bss", $null, $idNumber, $username);

        // Send binary dataW
        $stmt->send_long_data(0, $validId);

        if ($stmt->execute()) {
            $message = "ID uploaded successfully!";
            $messageType = "success";
            echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
            
        } else {
            $message = "Error uploading ID: " . $stmt->error . "";
            $messageType = "error";
            echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        }

        $stmt->close();
    } else {
        $message = "Please upload a valid ID image.";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
    }
} 

// Upload Proof of Billing Function
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uploadProofBilling'])) {
    // Check if file is uploaded
    if (isset($_FILES['ProofOfBilling']) && $_FILES['ProofOfBilling']['error'] == 0) {
        $fileTmpPath = $_FILES['ProofOfBilling']['tmp_name'];
        $fileType = $_FILES['ProofOfBilling']['type'];

        // Allowed file types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($fileType, $allowedTypes)) {
            $message = "Invalid file type. Only JPG and PNG are allowed.";
            $messageType = "error";
            echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
            exit();
        }

        // Read the image file as binary data
        $proofOfBilling = file_get_contents($fileTmpPath);

        // Prepare SQL query using prepared statement
        $stmt = $conn->prepare("UPDATE customer SET proofOfBilling = ? WHERE username = ?");
        $stmt->bind_param("bs", $null, $username);

        // Send binary data
        $stmt->send_long_data(0, $proofOfBilling);

        if ($stmt->execute()) {
            $message = "Proof of Billing uploaded successfully!";
            $messageType = "success";
            echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        } else {
            $message = "Error uploading Proof of Billing: " . $stmt->error . "";
            $messageType = "error";
            echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
        }

        $stmt->close();
    } else {
        $message = "Please upload a valid Proof of Billing image.";
        $messageType = "error";
        echo "<script>setTimeout(() => { window.location.href = 'profile.php'; }, 3000);</script>";
    }
}

function formatDate($dateString) {
    return date("F j, Y", strtotime($dateString));
}

// Format the user's birthday
$formattedBirthday = formatDate($user['birthday']);

// Retrieve stored ID and ID number
$sql = "SELECT validId, idNumber, proofOfBilling FROM customer WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($validId, $idNumber, $proofOfBilling);
$stmt->fetch();
$stmt->close();
// $conn->close(); cinomment ko lang, nag eerror eh.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="profilestyle.css">
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

    <div class="container">
        <div class="row gutters">
            <div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="account-settings">
                            <div class="user-profile">
                                <div class="user-avatar">
                                    <h2>User Account</h2>
                                    <div class="profile-image">
                                        <?php
                                        require 'config.php';
                                        $sql = "SELECT profileImage FROM customer WHERE username = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->bind_param("s", $_SESSION['username']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result->num_rows > 0) {
                                            $row = $result->fetch_assoc();
                                            if (!empty($row['profileImage'])) {
                                                echo "<img src='{$row['profileImage']}' alt='Image' width='100'>";
                                                echo '<br><a class="bi bi-pencil text-altlight" data-bs-toggle="collapse" href="#uploadForm" role="button" aria-expanded="false" aria-controls="uploadForm"></a>';;
                                                
                                           }else { ?>
                                                <form method="post" enctype="multipart/form-data">
                                                <input type="file" name="profileImage" id="profileImage" required> <br>
                                                <div class="form-group">
                                                    <input type="submit" value="Upload Image">
                                                </div>
                                                </form>
                                            <?php
                                            }
                                        }
                                        $conn->close();
                                        ?>
                                        <div class="collapse mt-2" id="uploadForm">
                                            <div class="card-cont">
                                                <form method="post" enctype="multipart/form-data">
                                                    <input type="file" name="profileImage" id="profileImage" required> <br>
                                                    <div class="form-group">
                                                        <input type="submit" name="uploadProfile" value="Upload Image" class="btn btn-success">
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="form-group">
                                        <a href="logout.php" class="btn btn-primary mt-2 custom-button">Logout</a><br>
                                        <a href="viewtransactions.php" class="btn btn-primary mt-2 custom-button">View Transactions</a><br>
                                        <a href="customer_feedbackView.php" class="btn btn-primary mt-2 custom-button">View Feedback</a><br>
                                        <a href="#" class="btn btn-primary mt-2 custom-button" data-bs-toggle="modal" data-bs-target="#resetPass">Reset Password</a><br>
                                        <a href="#" class="btn btn-primary mt-2 custom-button" data-bs-toggle="modal" data-bs-target="#resetProfile">Edit Profile Information</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            <div class="col-xl-9 col-lg-9 col-md-12 col-sm-12 col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <h4>User Information</h4>
                        <hr>
                        <div class="user-info-section">
                            <p><strong>Name: </strong> <?php echo $user['firstName'] .' '. $user['lastName']; ?></p>
                        </div>
                        <div class="user-info-section">
                            <p><strong>Birthday: </strong> <?php echo $formattedBirthday; ?></p>
                        </div>
                        <div class="user-info-section">
                            <p><strong>Address: </strong><?php echo $user['address']; ?></p>
                        </div>
                        <div class="user-info-section">
                            <p><strong>&nbsp; Email: </strong><?php echo htmlspecialchars($user['email']); ?>
                            <?php if ($user['emailVerification'] === "verified"): ?>
                                <span style="color: green;"> (Email Verified)</span>
                            <?php else: ?>
                                <span style="color: red;"> (Email Unverified)</span><br>
                                <form action="otp_form.php" method="POST">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                    <button type="submit" class="btn btn-primary mt-2 custom-button">Verify Your Email</button>
                                </form>
                            <?php endif; ?>
                            </p>
                        </div>
                        <div class="user-info-section">
                            <p><strong>&nbsp; Contact: </strong><?php echo $user['contactNumber'] ?></p>
                        </div>
                        <div class="user-info-section">
                            <p><strong>&nbsp; Facebook Profile Link: </strong><?php echo $user['facebookProfile'] ?></p>
                        </div>
                        <div class="user-info-section">
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#viewIdModal">View Uploaded ID</button>
                        </div>
                        <div class="user-info-section">
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#viewproofModal">View Proof of Billing</button>
                        </div>
                    </div>
                </div>  
            </div>
        </div>
    </div>
    <div class="background">
    <p style="background-image: url('img/Background.png');" >
    </p>
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
</div>

    <!-- Reset password modal -->
    <div class="modal fade" id="resetPass" tabindex="-1" aria-labelledby="resetPassLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPassLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="resetPasswordForm">
                        <div class="form-group mb-3">
                            <label for="current_password">Enter Current Password:</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            <div class="invalid-feedback" id="currentPasswordError"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <div class="invalid-feedback" id="newPasswordError"></div>
                            <small class="form-text text-muted">Password must be at least 8 characters with uppercase, lowercase, number, and special character.</small>
                        </div>
                        <div class="form-group mb-3">
                            <label for="confirm_new_password">Confirm New Password:</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required>
                            <div class="invalid-feedback" id="confirmPasswordError"></div>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="reset" class="btn btn-primary">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="resetProfile" tabindex="-1" aria-labelledby="resetProfileLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetProfileLabel">Update Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="updateProfileForm">
                        <div class="form-group mb-3">
                            <label for="firstName">First Name:</label>
                            <input type="text" id="firstName" name="firstName" class="form-control" required>
                            <div class="invalid-feedback" id="firstNameError"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="lastName">Last Name:</label>
                            <input type="text" id="lastName" name="lastName" class="form-control" required>
                            <div class="invalid-feedback" id="lastNameError"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                            <div class="invalid-feedback" id="emailError"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="birthday">Birthday: </label>
                            <input type="date" id="birthday" name="birthday" class="form-control" value="<?php echo htmlspecialchars($user['birthday']); ?>" required>
                            <div class="invalid-feedback" id="birthdayError"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="contactNumber">Contact number:</label>
                            <input type="text" id="contactNumber" name="contactNumber" class="form-control" required>
                            <div class="invalid-feedback" id="contactNumberError"></div>
                            <small class="form-text text-muted">Format: 09 followed by 9 digits (11 digits total, e.g., 09123456789)</small>
                        </div>
                        <div class="form-group mb-3">
                            <label for="address">Address:</label>
                            <input type="text" id="address" name="address" class="form-control" required>
                            <div class="invalid-feedback" id="addressError"></div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="facebookProfile">Facebook Profile link:</label>
                            <input type="text" id="facebookProfile" name="facebookProfile" class="form-control" required>
                            <div class="invalid-feedback" id="facebookProfileError"></div>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="update" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <!-- Upload ID modal -->
    <div class="modal fade" id="uploadIdModal" tabindex="-1" aria-labelledby="uploadIdModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadIdModalLabel">Upload Your ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <!-- File Upload -->
                        <div class="mb-3">
                            <label for="validId" class="form-label">Upload ID Image (JPG/PNG)</label>
                            <input type="file" class="form-control" id="validId" name="validId" accept="image/*" required>
                        </div>

                        <!-- ID Number -->
                        <div class="mb-3">
                            <label for="idNumber" class="form-label">Enter ID Number</label>
                            <input type="text" class="form-control" id="idNumber" name="idNumber" required>
                        </div>

                        <button type="submit" class="btn btn-success" name="uploadId">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Uploaded ID modal -->
    <div class="modal fade" id="viewIdModal" tabindex="-1" aria-labelledby="viewIdModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewIdModalLabel">Your Uploaded ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($validId) && !empty($idNumber)): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($validId) ?>" alt="Uploaded ID" class="img-fluid mb-3" style="max-width: 300px;">
                        <p><strong>ID Number:</strong> <?= htmlspecialchars($idNumber) ?></p>
                    <?php else: ?>
                        <p>You don't have any ID uploaded yet!</p><br>
                        <p>Verify Your Account by uploading your ID:</p><br>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadIdModal">
                            Upload ID
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Proof of Billing Modal -->
    <div class="modal fade" id="uploadproofModal" tabindex="-1" aria-labelledby="uploadproofModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadproofModalLabel">Upload Your Proof of Billing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="ProofOfBilling" class="form-label">Upload Proof of Billing Image (JPG/PNG)</label>
                            <input type="file" class="form-control" id="ProofOfBilling" name="ProofOfBilling" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn btn-success" name="uploadProofBilling">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Uploaded Proof of Billing Modal -->
    <div class="modal fade" id="viewproofModal" tabindex="-1" aria-labelledby="viewproofModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewproofModalLabel">Your Uploaded Proof of Billing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($proofOfBilling)): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($proofOfBilling) ?>" alt="Uploaded Proof of Billing" class="img-fluid mb-3" style="max-width: 300px;">
                    <?php else: ?>
                        <p>You haven't uploaded a Proof of Billing yet!</p><br>
                        <p>Please upload one for verification:</p><br>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadproofModal">
                            Upload Proof of Billing
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
     
     <?php if (!empty($message)) : ?>
        <div id="messagePopup" class="popup <?= $messageType ?>">
            <p><?= $message ?></p>
            <button onclick="closePopup()">OK</button>
        </div>

        <script>
            document.getElementById("messagePopup").style.display = "block";
            setTimeout(() => { document.getElementById("messagePopup").style.display = "none"; }, 3000);
        </script>
    <?php endif; ?>

    <script>
        function closePopup() {
            document.getElementById("messagePopup").style.display = "none";
        }
        // Properly handle modal closing
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all modals
            var modals = document.querySelectorAll('.modal');
            
            modals.forEach(function(modal) {
                modal.addEventListener('hidden.bs.modal', function () {
                    // Remove backdrop when modal is closed
                    var backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(function(backdrop) {
                        backdrop.parentNode.removeChild(backdrop);
                    });
                    
                    // Enable body scrolling
                    document.body.style.overflow = 'auto';
                    document.body.style.paddingRight = '0';
                });
            });
        });

        // Password Reset Form Validation
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let isValid = true;
            
            // Reset error states
            document.querySelectorAll('#resetPasswordForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            // Validate new password
            const newPassword = document.getElementById('new_password').value;
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!passwordRegex.test(newPassword)) {
                document.getElementById('new_password').classList.add('is-invalid');
                document.getElementById('newPasswordError').textContent = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
                isValid = false;
            }
            
            // Validate password match
            const confirmPassword = document.getElementById('confirm_new_password').value;
            if (newPassword !== confirmPassword) {
                document.getElementById('confirm_new_password').classList.add('is-invalid');
                document.getElementById('confirmPasswordError').textContent = 'Passwords do not match.';
                isValid = false;
            }
            
            if (isValid) {
                this.submit();
            }
        });

        // Profile Update Form Validation
        document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let isValid = true;
            
            // Reset error states
            document.querySelectorAll('#updateProfileForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            // Validate email
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                document.getElementById('email').classList.add('is-invalid');
                document.getElementById('emailError').textContent = 'Please enter a valid email address.';
                isValid = false;
            }
            
            // Validate birthday (must be at least 18 years old)
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
                    document.getElementById('birthday').classList.add('is-invalid');
                    document.getElementById('birthdayError').textContent = 'You must be at least 18 years old.';
                    isValid = false;
                }
            }
            
            // Validate contact number (Philippine format: +63 followed by 10 digits)
            const contactNumber = document.getElementById('contactNumber').value;
            const contactRegex = /^09\d{9}$/;
            if (!contactRegex.test(contactNumber)) {
                document.getElementById('contactNumber').classList.add('is-invalid');
                document.getElementById('contactNumberError').textContent = 'Please enter a valid Philippine number starting with 09 followed by 9 digits (11 digits total).';
                isValid = false;
            }
            
            // Validate Facebook profile link
            const facebookProfile = document.getElementById('facebookProfile').value;
            const facebookRegex = /^(https?:\/\/)?(www\.)?facebook\.com\/[a-zA-Z0-9._-]+\/?$/;
            if (!facebookRegex.test(facebookProfile)) {
                document.getElementById('facebookProfile').classList.add('is-invalid');
                document.getElementById('facebookProfileError').textContent = 'Please enter a valid Facebook profile URL.';
                isValid = false;
            }
            
            if (isValid) {
                this.submit();
            }
        });
    </script>
</body>
</html>
