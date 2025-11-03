<?php
// Start the session
session_start();

// Set session timeout to 15 minutes (900 seconds)
$inactive = 900; 

// Check if timeout variable is set
if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}

$_SESSION['timeout'] = time();

// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['userlevel'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Handle report download
if (isset($_GET['download'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_export_'.date('Y-m-d').'.csv"');
    
    $output = fopen('php://output', 'w');
    
    // 1. Visitors data
    $visitors_query = "SELECT 
                        DATE_FORMAT(visit_date, '%Y-%m') AS month, 
                        SUM(visit_count) AS total_visits
                       FROM visitor_counts
                       GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
                       ORDER BY month DESC
                       LIMIT 12";
    $visitors_result = mysqli_query($conn, $visitors_query);
    
    fputcsv($output, ['VISITORS PER MONTH']);
    fputcsv($output, ['Month', 'Total Visits']);
    while ($row = mysqli_fetch_assoc($visitors_result)) {
        fputcsv($output, [date('F Y', strtotime($row['month'] . '-01')), $row['total_visits']]);
    }
    fputcsv($output, []);
    
    // 2. Feedback data
    $feedback_query = "SELECT 
                        AVG(overall_rating) AS avg_rating,
                        sentiment,
                        COUNT(*) AS total_feedbacks
                       FROM feedback
                       GROUP BY sentiment";
    $feedback_result = mysqli_query($conn, $feedback_query);
    
    fputcsv($output, ['FEEDBACK ANALYSIS']);
    fputcsv($output, ['Sentiment', 'Count', 'Average Rating']);
    while ($row = mysqli_fetch_assoc($feedback_result)) {
        fputcsv($output, [$row['sentiment'] ?: 'Not Analyzed', $row['total_feedbacks'], number_format($row['avg_rating'], 1)]);
    }
    fputcsv($output, []);
    
    // 3. Packages data
    $packages_query = "SELECT 
                        p.packageName, 
                        COUNT(b.bookingId) AS booking_count
                       FROM booking b
                       JOIN package p ON b.packageId = p.packageId
                       WHERE b.bookingStatus = 'Completed'
                       GROUP BY b.packageId
                       ORDER BY booking_count DESC
                       LIMIT 5";
    $packages_result = mysqli_query($conn, $packages_query);
    
    fputcsv($output, ['MOST POPULAR PACKAGES']);
    fputcsv($output, ['Package Name', 'Completed Bookings']);
    while ($row = mysqli_fetch_assoc($packages_result)) {
        fputcsv($output, [$row['packageName'], $row['booking_count']]);
    }
    fputcsv($output, []);
    
    // 4. Weekly bookings data
    $weekly_data_query = "SELECT 
                            YEARWEEK(dateOfBooking) AS week,
                            COUNT(*) AS booking_count,
                            SUM(price) AS total_revenue
                          FROM booking
                          WHERE bookingStatus = 'Completed'
                          GROUP BY YEARWEEK(dateOfBooking)
                          ORDER BY week DESC
                          LIMIT 8";
    $weekly_data_result = mysqli_query($conn, $weekly_data_query);
    
    fputcsv($output, ['WEEKLY COMPLETED BOOKINGS']);
    fputcsv($output, ['Week', 'Bookings', 'Revenue']);
    while ($row = mysqli_fetch_assoc($weekly_data_result)) {
        fputcsv($output, [
            date('M d', strtotime(substr($row['week'], 0, 4) . 'W' . substr($row['week'], 4, 2) . '1')),
            $row['booking_count'],
            $row['total_revenue']
        ]);
    }
    
    fclose($output);
    exit();
}

// Original queries for display
$visitors_query = "SELECT 
                    DATE_FORMAT(visit_date, '%Y-%m') AS month, 
                    SUM(visit_count) AS total_visits
                   FROM visitor_counts
                   GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
                   ORDER BY month DESC
                   LIMIT 12";
$visitors_result = mysqli_query($conn, $visitors_query);

$feedback_query = "SELECT 
                    AVG(overall_rating) AS avg_rating,
                    sentiment,
                    COUNT(*) AS total_feedbacks
                   FROM feedback
                   GROUP BY sentiment";
$feedback_result = mysqli_query($conn, $feedback_query);

$packages_query = "SELECT 
                    p.packageName, 
                    COUNT(b.bookingId) AS booking_count
                   FROM booking b
                   JOIN package p ON b.packageId = p.packageId
                   WHERE b.bookingStatus = 'Completed'
                   GROUP BY b.packageId
                   ORDER BY booking_count DESC
                   LIMIT 5";
$packages_result = mysqli_query($conn, $packages_query);

$weekly_data_query = "SELECT 
                        YEARWEEK(dateOfBooking) AS week,
                        COUNT(*) AS booking_count,
                        SUM(price) AS total_revenue
                      FROM booking
                      WHERE bookingStatus = 'Completed'
                      GROUP BY YEARWEEK(dateOfBooking)
                      ORDER BY week DESC
                      LIMIT 8";
$weekly_data_result = mysqli_query($conn, $weekly_data_query);

// Prepare data for display
$weeks = [];
$booking_counts = [];
$revenues = [];

while ($row = mysqli_fetch_assoc($weekly_data_result)) {
    $weeks[] = date('M d', strtotime(substr($row['week'], 0, 4) . 'W' . substr($row['week'], 4, 2) . '1'));
    $booking_counts[] = $row['booking_count'];
    $revenues[] = $row['total_revenue'];
}

$sentiment_counts = [];
$total_feedbacks = 0;
$sum_ratings = 0;

while ($row = mysqli_fetch_assoc($feedback_result)) {
    $sentiment = $row['sentiment'] ?: 'Not Analyzed';
    $sentiment_counts[$sentiment] = $row['total_feedbacks'];
    $total_feedbacks += $row['total_feedbacks'];
    $sum_ratings += $row['avg_rating'] * $row['total_feedbacks'];
}

$average_rating = $total_feedbacks > 0 ? $sum_ratings / $total_feedbacks : 0;

// Reverse to show oldest first
$weeks = array_reverse($weeks);
$booking_counts = array_reverse($booking_counts);
$revenues = array_reverse($revenues);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Reports</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Card Styles */
        .report-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .report-card h3 {
            color: #3498db;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        /* Chart Container */
        .chart-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, 
        .data-table td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .sentiment-score {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }
        
        .download-btn {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .download-btn:hover {
            background-color: #27ae60;
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
            <h2>REPORTS</h2>
            <div class="d-flex align-items-center gap-3">
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> <i class="bi bi-person-circle"></i>
                </div>
                <a href="admin_reports.php?download=1" class="download-btn">
                    <i class="bi bi-download"></i> Download Report
                </a>
            </div>
        </div>
        
        <!-- 1. Website Visitors Report -->
        <div class="report-card">
            <h3>Website Visitors (Last 12 Months)</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Visits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($visitors_result)): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                            <td><?php echo $row['total_visits'] ?? 0; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 2. Sentiment Analysis Report -->
        <div class="report-card">
            <h3>Customer Feedback Analysis</h3>
            
            <!-- Overall Rating Section -->
            <div class="mb-4">
                <h4>Overall Rating</h4>
                <div class="d-flex align-items-center">
                    <div class="display-4 me-3" style="color: #3498db;">
                        <?php echo number_format($average_rating, 1); ?>/5.0
                    </div>
                    <div>
                        <div class="star-rating" style="color: #f1c40f; font-size: 24px;">
                            <?php
                            $full_stars = floor($average_rating);
                            $half_star = ($average_rating - $full_stars) >= 0.5;
                            
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $full_stars) {
                                    echo '<i class="bi bi-star-fill"></i>';
                                } elseif ($i == $full_stars + 1 && $half_star) {
                                    echo '<i class="bi bi-star-half"></i>';
                                } else {
                                    echo '<i class="bi bi-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <small class="text-muted">Based on <?php echo $total_feedbacks; ?> feedback submissions</small>
                    </div>
                </div>
            </div>
            
            <!-- Sentiment Distribution Section -->
            <div>
                <h4>Sentiment Analysis</h4>
                <div class="row">
                    <?php
                    $sentiment_colors = [
                        'Positive' => 'success',
                        'Neutral' => 'warning',
                        'Negative' => 'danger',
                        'Not Analyzed' => 'secondary'
                    ];
                    
                    foreach ($sentiment_counts as $sentiment => $count) {
                        $percentage = $total_feedbacks > 0 ? ($count / $total_feedbacks) * 100 : 0;
                        $color = $sentiment_colors[$sentiment] ?? 'info';
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $sentiment; ?></h5>
                                    <div class="display-5 text-<?php echo $color; ?>">
                                        <?php echo $count; ?>
                                    </div>
                                    <div class="progress mt-2" style="height: 10px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" 
                                             style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- 3. Popular Packages Report -->
        <div class="report-card">
            <h3>Most Popular Packages (Completed Bookings)</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Number of Bookings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($packages_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['packageName']); ?></td>
                            <td><?php echo $row['booking_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 4. Weekly Bookings and Revenue Chart -->
        <div class="chart-container">
            <h3>Weekly Completed Bookings and Revenue</h3>
            <canvas id="weeklyChart"></canvas>
        </div>
    </div>

    <script>
        // Weekly Bookings and Revenue Chart
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weeks); ?>,
                datasets: [
                    {
                        label: 'Number of Completed Bookings',
                        data: <?php echo json_encode($booking_counts); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1,
                        type: 'bar'
                    },
                    {
                        label: 'Total Revenue from Completed Bookings',
                        data: <?php echo json_encode($revenues); ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Bookings'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Revenue'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>