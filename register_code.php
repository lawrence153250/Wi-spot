<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Sanitize inputs
    $username = trim(htmlspecialchars($_POST['username']));
    $email = trim(htmlspecialchars($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $firstname = trim(htmlspecialchars($_POST['firstname']));
    $lastname = trim(htmlspecialchars($_POST['lastname']));
    $birthday = $_POST['birthday'];
    $contactnumber = trim(htmlspecialchars($_POST['contactnumber']));
    $address = trim(htmlspecialchars($_POST['address']));
    $facebookProfile = trim(htmlspecialchars($_POST['facebookProfile']));

    // Validate inputs
    $errors = [];
    
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($firstname)) $errors[] = "First name is required";
    if (empty($lastname)) $errors[] = "Last name is required";
    if (empty($birthday)) $errors[] = "Birthday is required";
    if (empty($contactnumber)) $errors[] = "Contact number is required";
    if (empty($address)) $errors[] = "Address is required";
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (strlen($password) < 8 || 
        !preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || 
        !preg_match('/[0-9]/', $password) || 
        !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Password and Confirm Password do not match!";
    }

    if (!empty($birthday)) {
        $birthDate = new DateTime($birthday);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 18) {
            $errors[] = "You must be at least 18 years old to register";
        }
    }

    if (!preg_match('/^09\d{9}$/', $contactnumber)) {
        $errors[] = "Invalid Philippine number format. Must start with 09 followed by 9 digits (11 digits total)";
    }

    // Only check database if no validation errors
    if (empty($errors)) {
        // Check for existing username, email, and contact number separately
        $username_exists = false;
        $email_exists = false;
        $contactnumber_exists = false;
        
        // Check username
        $stmt = $conn->prepare("SELECT username FROM customer WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already in use";
            $username_exists = true;
        }
        $stmt->close();
        
        // Check email
        $stmt = $conn->prepare("SELECT email FROM customer WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already in use";
            $email_exists = true;
        }
        $stmt->close();
        
        // Check contact number
        $stmt = $conn->prepare("SELECT contactnumber FROM customer WHERE contactnumber = ?");
        $stmt->bind_param("s", $contactnumber);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Contact number already in use";
            $contactnumber_exists = true;
        }
        $stmt->close();
        
        // Only proceed with registration if no duplicates found
        if (!$username_exists && !$email_exists && !$contactnumber_exists) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO customer (firstname, lastname, username, password, email, birthday, contactnumber, address, facebookProfile) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $firstname, $lastname, $username, $hashed_password, $email, $birthday, $contactnumber, $address, $facebookProfile);

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: login.php?success=1");
                exit();
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST; // Save form data to repopulate
        header("Location: register.php");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}

$conn->close();
?>