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
// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Process display status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedbackId = intval($_POST['feedback_id']);
    $displayStatus = $conn->real_escape_string($_POST['display_status']);
    
    $updateStmt = $conn->prepare("UPDATE feedback SET displayStatus = ? WHERE feedbackId = ?");
    $updateStmt->bind_param("si", $displayStatus, $feedbackId);
    
    if ($updateStmt->execute()) {
        $_SESSION['success_message'] = "Display status updated successfully!";
        header("Location: admin_feedbacks.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to update status. Please try again.";
    }
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'timestamp';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Get all feedback with customer information
$feedbackQuery = "
    SELECT f.*, c.username, c.email, b.packageId 
    FROM feedback f
    JOIN customer c ON f.customerId = c.customerId
    JOIN booking b ON f.bookingId = b.bookingId
";

// Add sorting
switch ($sort) {
    case 'package':
        $feedbackQuery .= " ORDER BY b.packageId $order";
        break;
    case 'sentiment':
        $feedbackQuery .= " ORDER BY f.sentiment $order";
        break;
    case 'status':
        $feedbackQuery .= " ORDER BY f.displayStatus $order";
        break;
    default:
        $feedbackQuery .= " ORDER BY f.timestamp $order";
        break;
}

$feedbackResult = $conn->query($feedbackQuery);

// Calculate average ratings
$avgRatingQuery = "
    SELECT 
        AVG(internet_speed) as avg_speed,
        AVG(reliability) as avg_reliability,
        AVG(signal_strength) as avg_signal,
        AVG(customer_service) as avg_service,
        AVG(installation_service) as avg_installation,
        AVG(equipment_quality) as avg_equipment,
        AVG(overall_rating) as avg_overall
    FROM feedback
";
$avgRatings = $conn->query($avgRatingQuery)->fetch_assoc();

// Count feedback by sentiment
$sentimentCountQuery = "
    SELECT sentiment, COUNT(*) as count 
    FROM feedback 
    GROUP BY sentiment
    ORDER BY count DESC
";
$sentimentCounts = $conn->query($sentimentCountQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            position: fixed;
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden; /* Hide horizontal scrollbar */
        }
        
        .sidebar-content {
            padding: 20px 0;
            min-height: 100%; /* Ensure content takes full height */
        }

        /* Custom scrollbar for webkit browsers */
                .sidebar::-webkit-scrollbar {
                    width: 6px;
                }
                
                .sidebar::-webkit-scrollbar-track {
                    background: #34495e;
                }
                
                .sidebar::-webkit-scrollbar-thumb {
                    background: #5a6c7d;
                    border-radius: 3px;
                }
                
                .sidebar::-webkit-scrollbar-thumb:hover {
                    background: #7f8c8d;
                }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
                padding: 15px;
            }

            .sidebar-menu li {
                padding: 8px 10px;
                font-size: 1.8vh;
            }
        }

        .sidebar-header {
            padding: 0 15px 15px;
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
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        /* Feedback Table Styles */
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .feedback-table th, 
        .feedback-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .feedback-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .feedback-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .rating-cell {
            text-align: center;
            font-weight: bold;
        }
        
        .rating-1 { color: #e74c3c; }
        .rating-2 { color: #e67e22; }
        .rating-3 { color: #f1c40f; }
        .rating-4 { color: #2ecc71; }
        .rating-5 { color: #27ae60; }
        
        .sentiment-excellent { color: #27ae60; font-weight: bold; }
        .sentiment-good { color: #2ecc71; font-weight: bold; }
        .sentiment-average { color: #f1c40f; font-weight: bold; }
        .sentiment-poor { color: #e67e22; font-weight: bold; }
        .sentiment-terrible { color: #e74c3c; font-weight: bold; }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card h5 {
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        /* Status Badges */
        .status-display { background-color: #2ecc71; color: white; }
        .status-hide { background-color: #e74c3c; color: white; }
        .status-pending { background-color: #f39c12; color: white; }
        
        /* Sort Dropdown Styles */
        .sort-dropdown .dropdown-toggle {
            background-color: #3498db;
            border: none;
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
            }
            
            .feedback-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a class="navbar-brand" href="adminhome.php"><img src="logo.png"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a class="nav-link" href="adminhome.php">DASHBOARD</a></li>
            <li><a class="nav-link" href="admin_accounts.php">ACCOUNTS</a></li>
            <li><a class="nav-link" href="admin_services.php">SERVICES</a></li>
            <li><a class="nav-link" href="admin_booking.php">BOOKING MANAGEMENT</a></li>
            <li class="active"><a class="nav-link" href="admin_management.php">REPORTS MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="admin_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>FEEDBACK MANAGEMENT</h2>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Feedback Statistics -->
        <div class="stats-grid">
            <div class="stats-card">
                <h5>Average Ratings</h5>
                <div>Internet Speed: <span class="rating-<?= round($avgRatings['avg_speed']) ?>"><?= number_format($avgRatings['avg_speed'], 1) ?></span></div>
                <div>Reliability: <span class="rating-<?= round($avgRatings['avg_reliability']) ?>"><?= number_format($avgRatings['avg_reliability'], 1) ?></span></div>
                <div>Signal Strength: <span class="rating-<?= round($avgRatings['avg_signal']) ?>"><?= number_format($avgRatings['avg_signal'], 1) ?></span></div>
                <div>Customer Service: <span class="rating-<?= round($avgRatings['avg_service']) ?>"><?= number_format($avgRatings['avg_service'], 1) ?></span></div>
                <div>Installation: <span class="rating-<?= round($avgRatings['avg_installation']) ?>"><?= number_format($avgRatings['avg_installation'], 1) ?></span></div>
                <div>Equipment: <span class="rating-<?= round($avgRatings['avg_equipment']) ?>"><?= number_format($avgRatings['avg_equipment'], 1) ?></span></div>
                <div>Overall: <span class="rating-<?= round($avgRatings['avg_overall']) ?>"><?= number_format($avgRatings['avg_overall'], 1) ?></span></div>
            </div>
            
            <div class="stats-card">
                <h5>Feedback Sentiment</h5>
                <?php while($sentiment = $sentimentCounts->fetch_assoc()): ?>
                    <div>
                        <?= htmlspecialchars($sentiment['sentiment']) ?>: 
                        <span class="sentiment-<?= strtolower($sentiment['sentiment']) ?>">
                            <?= $sentiment['count'] ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="stats-card">
                <h5>Total Feedback</h5>
                <div><?= $feedbackResult->num_rows ?> feedback entries</div>
                <h5 class="mt-3">Response Status</h5>
                <?php 
                    $responseStatusQuery = "SELECT responseStatus, COUNT(*) as count FROM feedback GROUP BY responseStatus";
                    $responseStatus = $conn->query($responseStatusQuery);
                    while($status = $responseStatus->fetch_assoc()):
                ?>
                    <div><?= ucfirst($status['responseStatus']) ?>: <?= $status['count'] ?></div>
                <?php endwhile; ?>
            </div>
        </div>
        
                <!-- Sort Dropdown -->
        <div class="dropdown sort-dropdown mb-3">
            <button class="btn btn-primary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-sort-alpha-down"></i> Sort By
            </button>
            <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                <li><h6 class="dropdown-header">Sort Options</h6></li>
                <li><a class="dropdown-item" href="?sort=timestamp&order=desc">Newest First</a></li>
                <li><a class="dropdown-item" href="?sort=timestamp&order=asc">Oldest First</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?sort=package&order=asc">Package (A-Z)</a></li>
                <li><a class="dropdown-item" href="?sort=package&order=desc">Package (Z-A)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?sort=sentiment&order=asc">Sentiment (A-Z)</a></li>
                <li><a class="dropdown-item" href="?sort=sentiment&order=desc">Sentiment (Z-A)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?sort=status&order=asc">Display Status (A-Z)</a></li>
                <li><a class="dropdown-item" href="?sort=status&order=desc">Display Status (Z-A)</a></li>
            </ul>
        </div>
        
        <!-- Feedback Table -->
        <div class="table-responsive">
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Ratings</th>
                        <th>Sentiment</th>
                        <th>Comment</th>
                        <th>Date</th>
                        <th>Response</th>
                        <th>Display Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($feedback = $feedbackResult->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($feedback['username']) ?></strong><br>
                            <small><?= htmlspecialchars($feedback['email']) ?></small>
                        </td>
                        <td>Package #<?= $feedback['packageId'] ?></td>
                        <td>
                            <div>Speed: <span class="rating-<?= $feedback['internet_speed'] ?>"><?= $feedback['internet_speed'] ?></span></div>
                            <div>Reliability: <span class="rating-<?= $feedback['reliability'] ?>"><?= $feedback['reliability'] ?></span></div>
                            <div>Signal: <span class="rating-<?= $feedback['signal_strength'] ?>"><?= $feedback['signal_strength'] ?></span></div>
                            <div>Overall: <span class="rating-<?= $feedback['overall_rating'] ?>"><?= $feedback['overall_rating'] ?></span></div>
                        </td>
                        <td class="sentiment-<?= strtolower($feedback['sentiment']) ?>">
                            <?= htmlspecialchars($feedback['sentiment']) ?>
                        </td>
                        <td>
                            <?= nl2br(htmlspecialchars(substr($feedback['comment'], 0, 100))) ?>
                            <?php if(strlen($feedback['comment']) > 100) echo '...'; ?>
                        </td>
                        <td><?= date('M d, Y h:i A', strtotime($feedback['timestamp'])) ?></td>
                        <td>
                            <?php if(!empty($feedback['response'])): ?>
                                <?= nl2br(htmlspecialchars(substr($feedback['response'], 0, 50))) ?>
                                <?php if(strlen($feedback['response']) > 50) echo '...'; ?>
                            <?php else: ?>
                                <span class="text-muted">No response</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge status-<?= $feedback['displayStatus'] ?>">
                                <?= ucfirst($feedback['displayStatus']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal" 
                                data-feedback-id="<?= $feedback['feedbackId'] ?>"
                                data-current-status="<?= $feedback['displayStatus'] ?>">
                                <i class="bi bi-gear"></i> Update Status
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Update Feedback Display Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="admin_feedbacks.php">
                    <div class="modal-body">
                        <input type="hidden" name="feedback_id" id="modalFeedbackId">
                        
                        <div class="mb-3">
                            <label for="display_status" class="form-label">Display Status</label>
                            <select class="form-select" id="display_status" name="display_status" required>
                                <option value="display">Display (Visible to public)</option>
                                <option value="hide">Hide (Not visible to public)</option>
                                <option value="pending">Pending (Default status)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize modal with feedback data
        document.addEventListener('DOMContentLoaded', function() {
            var statusModal = document.getElementById('statusModal');
            statusModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var feedbackId = button.getAttribute('data-feedback-id');
                var currentStatus = button.getAttribute('data-current-status');
                
                var modal = this;
                modal.querySelector('#modalFeedbackId').value = feedbackId;
                modal.querySelector('#display_status').value = currentStatus;
            });
        });
    </script>
</body>
</html>
