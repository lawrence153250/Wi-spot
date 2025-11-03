<?php
// Enable error reporting (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set content type for JSON response
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer files
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';


try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed");
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    $action = $input['action'] ?? '';
    $email = trim($input['email'] ?? '');

    // Database connection
    require_once 'config.php';

    if ($action === 'send_otp') {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address received: '$email'");
        }


        // Generate OTP
        $otp_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Clear previous OTPs for the email
        $conn->query("DELETE FROM otp_verification WHERE email = '$email'");

        // Insert new OTP
        $stmt = $conn->prepare("INSERT INTO otp_verification (email, otp_code, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $otp_code, $expires_at);
        $stmt->execute();

        // Send OTP via email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'wispot.servicesph@gmail.com';
            $mail->Password = 'dzij hshz xbqt hwlb'; // Consider using an App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('wispot.servicesph@gmail.com', 'Wi-Spot');
            $mail->addAddress($email);
            $mail->Subject = 'Your OTP Code';
            $mail->Body = "Your OTP code is: $otp_code\nValid for 15 minutes.";

            $mail->send();

            echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $mail->ErrorInfo);
        }

        } elseif ($action === 'verify_otp') {
            $otp = trim($input['otp'] ?? '');

            if (empty($email) || empty($otp)) {
                throw new Exception("Email and OTP are required");
            }

            if (!preg_match('/^\d{6}$/', $otp)) {
                throw new Exception("OTP must be a 6-digit number");
            }

            $current_time = date('Y-m-d H:i:s');

            // Check OTP in database
            $stmt = $conn->prepare("SELECT id, expires_at FROM otp_verification WHERE email = ? AND otp_code = ? AND is_verified = 0");
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                file_put_contents('security.log', date('Y-m-d H:i:s') . " - Failed OTP for $email\n", FILE_APPEND);
                throw new Exception("Invalid OTP or already verified");
            }

            $row = $result->fetch_assoc();

            if ($current_time > $row['expires_at']) {
                throw new Exception("OTP has expired");
            }

            // Mark as verified
            $update = $conn->prepare("UPDATE otp_verification SET is_verified = 1, verified_at = NOW() WHERE id = ?");
            $update->bind_param("i", $row['id']);
            $update->execute();

            // Update emailVerification status in customer table
            $updateCustomer = $conn->prepare("UPDATE customer SET emailVerification = 'verified' WHERE email = ?");
            $updateCustomer->bind_param("s", $email);
            $updateCustomer->execute();

            // Cleanup expired
            $conn->query("DELETE FROM otp_verification WHERE expires_at < NOW()");

            // Success response
            echo json_encode([
                'status' => 'success',
                'message' => 'OTP verified successfully',
                'data' => [
                    'email' => $email,
                    'verified_at' => date('Y-m-d H:i:s')
                ]
            ]);

            file_put_contents('verifications.log', date('Y-m-d H:i:s') . " - Verified $email\n", FILE_APPEND);
    } else {
        throw new Exception("Invalid action requested");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log("OTP Error: " . $e->getMessage());
}
