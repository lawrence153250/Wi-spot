<?php
// Start the session
session_start();

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

// Check if user is logged in and is staff
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'staff') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Reset password function
$message = '';
$messageType = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    $current_password = htmlspecialchars($_POST['current_password']);
    $new_password = htmlspecialchars($_POST['new_password']);
    $confirm_new_password = htmlspecialchars($_POST['confirm_new_password']);
    $username = $_SESSION['username'];

    $sql = "SELECT * FROM staff WHERE username = '$username'";
    $result = $conn->query($sql);
    $user = $result->fetch_assoc();

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_new_password) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = "UPDATE staff SET password = '$hashed_new_password' WHERE username = '$username'";
            if ($conn->query($sql) === TRUE) {
                $message = "Password has been reset successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating password: " . $conn->error;
                $messageType = "error";
            }
        } else {
            $message = "New password and Confirm new password do not match!";
            $messageType = "error";
        }
    } else {
        $message = "Current password is incorrect!";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Sidebar Styles */
        .sidebar .sidebar-menu li a.nav-link {
    color: #FFFFFF !important;
}
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            height: 100vh;
            padding: 20px 0;
            position: fixed;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            font-size: 2vh;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu li:hover {
            background-color: #34495e;
        }
        
        .sidebar-menu li a.nav-link {
            color: #FFFFFF;
        }

        .sidebar-menu li.active {
            background-color: #34485f;
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 24px;
            margin: 0;
        }
        
        .user-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .user-info i {
            margin-left: 8px;
            font-size: 18px;
        }
        
        /* Password Reset Card Styles */
        .password-reset-container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 70vh;
            padding-top: 40px;
        }
        
        .password-reset-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            border: 1px solid #e9ecef;
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .card-header h3 {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .card-header p {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }
        
        .form-control:hover {
            border-color: #bdc3c7;
        }
        
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-reset:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            border: none;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        

        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h1, 
            .sidebar-menu li span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
                padding: 15px;
            }
            
            .password-reset-card {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="staff_dashboard.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a class="nav-link" href="staff_dashboard.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="staff_accounts.php">ACCOUNTS</a></li>
            <li><a class="nav-link" href="staff_booking.php">BOOKINGS</a></li>
            <li><a class="nav-link" href="staff_services.php">SERVICES</a></li>
            <li><a class="nav-link" href="staff_landingReport.php">REPORTS</a></li>
            <li><a class="nav-link" href="staff_announcements.php">ANNOUNCEMENTS</a></li>
            <li class="active"><a class="nav-link" href="staff_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>ACCOUNT MANAGEMENT</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <!-- Password Reset Container -->
        <div class="password-reset-container">
            <div class="password-reset-card">
                <div class="card-header">
                    <h3><i class="bi bi-shield-lock"></i> Reset Password</h3>
                    <p>Update your account password for security</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">
                            <i class="bi bi-lock"></i> Current Password
                        </label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter your current password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">
                            <i class="bi bi-key"></i> New Password
                        </label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Enter your new password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_new_password">
                            <i class="bi bi-key-fill"></i> Confirm New Password
                        </label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required placeholder="Confirm your new password">
                    </div>
                    

                    
                    <div class="form-group">
                        <button type="submit" name="reset" class="btn-reset">
                            <i class="bi bi-shield-check"></i> Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
