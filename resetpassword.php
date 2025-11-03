<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize variables
$error_message = '';
$success_message = '';
$show_form = false;
$email = '';
$token = '';

// Check if token and email are provided in URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = trim($_GET['token']);
    $email = trim($_GET['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address format";
    } else {
        require_once 'config.php';
        
        try {
            // Check if token is valid and not expired
            $current_time = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("SELECT email FROM password_reset_tokens WHERE email = ? AND token = ? AND used = 0 AND expires_at > ?");
            $stmt->bind_param("sss", $email, $token, $current_time);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $show_form = true;
            } else {
                $error_message = "Invalid or expired reset link. Please request a new password reset.";
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $email = trim($_POST['email']);
    $token = trim($_POST['token']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    require_once 'config.php';
    
    try {
        // Validate inputs
        if (empty($new_password)) {
            throw new Exception("New password is required");
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }
        
        // Verify token again (prevent CSRF)
        $current_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("SELECT id FROM password_reset_tokens WHERE email = ? AND token = ? AND used = 0 AND expires_at > ?");
        $stmt->bind_param("sss", $email, $token, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            throw new Exception("Invalid or expired reset token");
        }
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in customer table
        $update_stmt = $conn->prepare("UPDATE customer SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $hashed_password, $email);
        $update_stmt->execute();
        
        // Mark token as used
        $update_token = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE email = ? AND token = ?");
        $update_token->bind_param("ss", $email, $token);
        $update_token->execute();
        
        $success_message = "Your password has been updated successfully!";
        $show_form = false;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $show_form = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Wi-Spot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f3fa; }
        .reset-wrapper { max-width: 500px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .form-control { padding: 10px; margin-bottom: 15px; }
        .btn-primary { width: 100%; padding: 10px; }
        .alert { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-wrapper">
            <h2 class="text-center mb-4">Reset Your Password</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary">Login Now</a>
                </div>
            <?php elseif ($show_form): ?>
                <form method="POST" action="resetpassword.php">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Please use the password reset link sent to your email.</div>
                <div class="text-center mt-3">
                    <a href="forgotpassword.php" class="btn btn-primary">Request New Reset Link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>