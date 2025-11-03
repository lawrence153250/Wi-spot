<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $area_size = filter_input(INPUT_POST, 'area_size', FILTER_VALIDATE_FLOAT) ?? 0;
    $recommended_package = filter_input(INPUT_POST, 'recommended_package', FILTER_SANITIZE_STRING) ?? '';
    $additional_eaps = filter_input(INPUT_POST, 'additional_eaps', FILTER_VALIDATE_INT) ?? 0;
    $starlink_count = filter_input(INPUT_POST, 'starlink_count', FILTER_VALIDATE_INT) ?? 0;
    $eap_count = filter_input(INPUT_POST, 'eap_count', FILTER_VALIDATE_INT) ?? 0;
    $coverage_status = filter_input(INPUT_POST, 'coverage_status', FILTER_SANITIZE_STRING) ?? 'unknown';
    $from_booking = filter_input(INPUT_POST, 'from_booking', FILTER_VALIDATE_INT) ?? 0;

    // Store all coverage data in session
    $_SESSION['coverage_data'] = [
        'area_size' => $area_size,
        'recommended_package' => $recommended_package,
        'additional_eaps' => $additional_eaps,
        'starlink_count' => $starlink_count,
        'eap_count' => $eap_count,
        'coverage_status' => $coverage_status
    ];

    // Debug output (remove in production)
    error_log("Saved coverage data: " . print_r($_SESSION['coverage_data'], true));

    // Redirect back to appropriate page
    if ($from_booking) {
        header('Location: booking_customization.php');
    } else {
        header('Location: mapcoverage.php');
    }
    exit;
}

// If not a POST request, redirect back
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'mapcoverage.php'));
exit;
?>