<?php
// config.php - Database Configuration

// Enable error reporting (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// $conn = new mysqli('sql311.infinityfree.com', 'if0_38382334', 'PAyQbh24YXB', 'if0_38382334_wispotdb');

$conn = new mysqli('localhost', 'root', '', 'capstonesample');
// Create database connection


// Check connection
if ($conn->connect_error) {
    // Optional: log error to a file
    error_log("Database connection failed: " . $conn->connect_error);
    die("We are currently experiencing technical issues. Please try again later.");
}
?>