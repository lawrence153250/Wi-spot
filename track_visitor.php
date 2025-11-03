<?php
require_once 'config.php';

// Get basic non-identifiable info
$page_visited = basename($_SERVER['SCRIPT_NAME']); 
$today = date('Y-m-d');

// Check if record exists for today
$query = "INSERT INTO visitor_counts (visit_date, page_visited, visit_count)
          VALUES (?, ?, 1)
          ON DUPLICATE KEY UPDATE visit_count = visit_count + 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $today, $page_visited);
$stmt->execute();
$stmt->close();
?>