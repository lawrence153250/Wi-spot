<?php
// Start the session
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'admin') {
    header("Location: login.php");
    exit();
}


// Check if booking ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_agreements.php");
    exit();
}

$bookingId = intval($_GET['id']);

// Database connection
require_once 'config.php';

// Fetch the agreement file path
$stmt = $conn->prepare("SELECT lendingAgreement FROM booking WHERE bookingId = ?");
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_agreements.php?error=not_found");
    exit();
}

$row = $result->fetch_assoc();
$filepath = $row['lendingAgreement'];

$stmt->close();
$conn->close();

// Check if file exists
if (!file_exists($filepath)) {
    header("Location: admin_agreements.php?error=file_missing");
    exit();
}

// Get file info
$filename = basename($filepath);
$filesize = filesize($filepath);

// Set headers for file download
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Length: $filesize");
header("Pragma: no-cache");
header("Expires: 0");

// Clear output buffer
ob_clean();
flush();

// Read the file and output it to the browser
readfile($filepath);
exit();
?>