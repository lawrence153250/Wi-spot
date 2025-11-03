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

// Fetch all bookings with lending agreements
$query = "SELECT b.bookingId, b.dateOfBooking, b.dateOfReturn, b.lendingAgreement, 
                 c.firstName, c.lastName, c.email, c.contactNumber
          FROM booking b
          JOIN customer c ON b.customerId = c.customerId
          WHERE b.lendingAgreement IS NOT NULL
          ORDER BY b.dateOfBooking DESC";
$result = $conn->query($query);

$agreements = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Read the agreement content
        $agreementContent = file_exists($row['lendingAgreement']) ? 
                            file_get_contents($row['lendingAgreement']) : 
                            "Agreement file not found";
        $row['agreementContent'] = $agreementContent;
        $agreements[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Lending Agreement View</title>
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
        
        .agreement-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .agreement-table th, 
        .agreement-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .agreement-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .agreement-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .btn-action {
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
            border: none;
        }
        
        .btn-view:hover {
            background-color: #138496;
        }
        
        .btn-download {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-download:hover {
            background-color: #218838;
        }
        
        .no-agreements {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        /* Agreement Preview Modal */
        .agreement-preview {
            white-space: pre-wrap;
            font-family: monospace;
            max-height: 60vh;
            overflow-y: auto;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .signature-display {
            margin-top: 20px;
            padding: 10px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
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
            
            .agreement-table {
                display: block;
                overflow-x: auto;
            }
            
            .btn-action {
                display: block;
                margin-bottom: 5px;
                width: 100%;
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
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h2>Lending Agreements</h2>
        </div>
        
        <?php if (count($agreements) > 0): ?>
            <div class="table-responsive">
                <table class="agreement-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer Name</th>
                            <th>Contact Info</th>
                            <th>Booking Period</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($agreement['bookingId']); ?></td>
                                <td><?php echo htmlspecialchars($agreement['firstName'] . ' ' . $agreement['lastName']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($agreement['email']); ?><br>
                                    <?php echo htmlspecialchars($agreement['contactNumber']); ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($agreement['dateOfBooking'])); ?> to<br>
                                    <?php echo date('M j, Y', strtotime($agreement['dateOfReturn'])); ?>
                                </td>
                                <td>
                                    <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#agreementModal" 
                                            data-content="<?php echo htmlspecialchars($agreement['agreementContent']); ?>"
                                            data-bookingid="<?php echo $agreement['bookingId']; ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <a href="download_agreement.php?id=<?php echo $agreement['bookingId']; ?>" class="btn-action btn-download">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Agreement Preview Modal -->
            <div class="modal fade" id="agreementModal" tabindex="-1" aria-labelledby="agreementModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="agreementModalLabel">Lending Agreement - Booking #<span id="modalBookingId"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="agreement-preview" id="agreementContent"></div>
                        </div>
                        <div class="modal-footer">
                            <a href="#" class="btn btn-primary" id="modalDownloadBtn">
                                <i class="bi bi-download"></i> Download
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="no-agreements">
                <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                <h3>No Lending Agreements Found</h3>
                <p>There are currently no signed lending agreements in the system.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize the modal with agreement content
        document.addEventListener('DOMContentLoaded', function() {
            var agreementModal = document.getElementById('agreementModal');
            if (agreementModal) {
                agreementModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var content = button.getAttribute('data-content');
                    var bookingId = button.getAttribute('data-bookingid');
                    
                    var modalTitle = agreementModal.querySelector('.modal-title');
                    var modalBody = agreementModal.querySelector('.agreement-preview');
                    var downloadBtn = agreementModal.querySelector('#modalDownloadBtn');
                    
                    modalTitle.textContent = 'Lending Agreement - Booking #' + bookingId;
                    modalBody.textContent = content;
                    downloadBtn.href = 'download_agreement.php?id=' + bookingId;
                });
            }
            
            // Format the agreement content for better display
            var modal = document.getElementById('agreementModal');
            if (modal) {
                modal.addEventListener('shown.bs.modal', function() {
                    var contentDiv = document.getElementById('agreementContent');
                    var text = contentDiv.textContent;
                    
                    // Extract signature if it exists (looks like [Signature Image Data: ...])
                    var signatureMatch = text.match(/\[Signature Image Data: (data:image\/[^;]+;base64,[^\]]+)\]/);
                    if (signatureMatch) {
                        var signatureData = signatureMatch[1];
                        text = text.replace(signatureMatch[0], '');
                        
                        // Create signature display
                        var signatureHtml = '<div class="signature-display">';
                        signatureHtml += '<h6>Customer Signature:</h6>';
                        signatureHtml += '<img src="' + signatureData + '" style="max-width: 100%; border: 1px solid #ddd;"/>';
                        signatureHtml += '</div>';
                        
                        contentDiv.innerHTML = text + signatureHtml;
                    }
                });
            }
        });
    </script>
</body>
</html>