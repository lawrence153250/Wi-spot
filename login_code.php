<?php
session_start();
require_once 'config.php';

echo '<meta http-equiv="refresh" content="30">';

function minutesRemaining($lockout_until) {
    $now = new DateTime();
    $until = new DateTime($lockout_until);
    $diff = $until->getTimestamp() - $now->getTimestamp();
    return max(0, ceil($diff / 60));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $user = null;
    $userType = null;
    $userColumn = null;

    $tables = [
        'customer' => 'username',
        'admin'    => 'userName',
        'staff'    => 'userName'
    ];

    // 1. Check user in all 3 tables
    foreach ($tables as $table => $column) {
        $stmt = $conn->prepare("SELECT * FROM $table WHERE $column = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userType = $table;
            $userColumn = $column;
            break;
        }
    }

    if (!$user) {
        $error_message = "Invalid username or password.";
    } else {
        $accountStatus = strtolower($user['accountStatus']);
        $failed_attempts = (int)$user['failed_attempts'];
        $lockout_count = (int)$user['lockout_count'];
        $lockout_until = $user['lockout_until'];

        // 2. Handle account status
        if ($accountStatus === 'blocked') {
            $error_message = "Your account is blocked.";
        } elseif ($accountStatus === 'locked') {
            $error_message = "Your account is locked.";
        } else {
            $now = new DateTime();

            // 3. Check temporary lockout
            if (!empty($lockout_until)) {
                $lock_until_time = new DateTime($lockout_until);
                if ($now < $lock_until_time) {
                    $remaining = minutesRemaining($lockout_until);
                    $error_message = "Too many failed attempts. Please try again after {$remaining} minute(s).";
                } else {
                    // Lockout expired, reset attempts
                    $stmt = $conn->prepare("UPDATE $userType SET failed_attempts = 0, lockout_until = NULL WHERE $userColumn = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $failed_attempts = 0;
                    $lockout_until = null;
                }
            }

            // 4. Proceed to check password if no lock error
            if (!isset($error_message)) {
                if (password_verify($password, $user['password'])) {
                    // Successful login
                    $stmt = $conn->prepare("UPDATE $userType SET failed_attempts = 0, lockout_until = NULL, lockout_count = 0 WHERE $userColumn = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();

                    $_SESSION['username'] = $username;
                    $_SESSION['userType'] = $userType;
                    $_SESSION['userlevel'] = ($userType === 'customer') ? ($user['userLevel'] ?? 'customer') : $userType;

                    // Redirect
                    switch ($_SESSION['userlevel']) {
                        case 'admin':
                            header("Location: adminhome.php");
                            break;
                        case 'staff':
                            header("Location: staff_dashboard.php");
                            break;
                        default:
                            header("Location: index.php");
                    }
                    exit();
                } else {
                    // Wrong password
                    $failed_attempts++;

                    if ($failed_attempts >= 5) {
                        if ($lockout_count === 0) {
                            // Set 10-minute lockout
                            $lockout_time = (new DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');
                            $stmt = $conn->prepare("UPDATE $userType SET failed_attempts = 0, lockout_until = ?, lockout_count = 1 WHERE $userColumn = ?");
                            $stmt->bind_param("ss", $lockout_time, $username);
                            $stmt->execute();
                            $error_message = "Too many failed attempts. Please try again after 10 minutes.";
                        } else {
                            // Second round of failure → lock account
                            $stmt = $conn->prepare("UPDATE $userType SET accountStatus = 'locked', failed_attempts = 0 WHERE $userColumn = ?");
                            $stmt->bind_param("s", $username);
                            $stmt->execute();
                            $error_message = "Too many failed attempts. Your account is now locked. Please contact customer support.";
                        }
                    } else {
                        // Just update failed_attempts
                        $stmt = $conn->prepare("UPDATE $userType SET failed_attempts = ? WHERE $userColumn = ?");
                        $stmt->bind_param("is", $failed_attempts, $username);
                        $stmt->execute();
                        $error_message = "Invalid password. Attempt {$failed_attempts} of 5.";
                    }
                }
            }
        }
    }

    // Show error message
    if (isset($error_message)) {
        echo "<div class='alert alert-danger'>{$error_message}</div>";
    }
}

$conn->close();
?>
