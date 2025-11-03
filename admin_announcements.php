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

// Function to delete expired announcements
function deleteExpiredAnnouncements($conn) {
    $currentDateTime = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("DELETE FROM announcement WHERE endDate < ?");
    $stmt->bind_param("s", $currentDateTime);
    $stmt->execute();
    $stmt->close();
}

// Delete expired announcements on page load
deleteExpiredAnnouncements($conn);

// Get admin ID from session username
$username = $_SESSION['username'];
$adminQuery = $conn->prepare("SELECT adminId FROM admin WHERE username = ?");
$adminQuery->bind_param("s", $username);
$adminQuery->execute();
$adminResult = $adminQuery->get_result();

if ($adminResult->num_rows === 0) {
    die("Error: Admin account not found for username: " . htmlspecialchars($username));
}

$adminData = $adminResult->fetch_assoc();
$adminId = $adminData['adminId'];
$adminQuery->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create'])) {
        // Create new announcement
        $title = $conn->real_escape_string($_POST['title']);
        $category = $conn->real_escape_string($_POST['category']);
        $description = $conn->real_escape_string($_POST['description']);
        $isPriority = isset($_POST['isPriority']) ? 1 : 0;
        $startDate = $conn->real_escape_string($_POST['startDate']);
        $endDate = $conn->real_escape_string($_POST['endDate']);

        // Validate end date is in the future
        if (strtotime($endDate) < time()) {
            header("Location: admin_announcements.php?error=" . urlencode("End date must be in the future"));
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO announcement (adminId, title, category, description, isPriority, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiss", $adminId, $title, $category, $description, $isPriority, $startDate, $endDate);
        
        if ($stmt->execute()) {
            header("Location: admin_announcements.php?success=created");
        } else {
            header("Location: admin_announcements.php?error=" . urlencode("Failed to create announcement"));
        }
        $stmt->close();
        exit();
    } elseif (isset($_POST['update'])) {
        // Update existing announcement
        $id = intval($_POST['id']);
        $title = $conn->real_escape_string($_POST['title']);
        $category = $conn->real_escape_string($_POST['category']);
        $description = $conn->real_escape_string($_POST['description']);
        $isPriority = isset($_POST['isPriority']) ? 1 : 0;
        $startDate = $conn->real_escape_string($_POST['startDate']);
        $endDate = $conn->real_escape_string($_POST['endDate']);

        // Validate end date is in the future
        if (strtotime($endDate) < time()) {
            header("Location: admin_announcements.php?error=" . urlencode("End date must be in the future"));
            exit();
        }

        $stmt = $conn->prepare("UPDATE announcement SET title=?, category=?, description=?, isPriority=?, startDate=?, endDate=? WHERE announcementId=?");
        $stmt->bind_param("sssissi", $title, $category, $description, $isPriority, $startDate, $endDate, $id);
        
        if ($stmt->execute()) {
            header("Location: admin_announcements.php?success=updated");
        } else {
            header("Location: admin_announcements.php?error=" . urlencode("Failed to update announcement"));
        }
        $stmt->close();
        exit();
    }
} elseif (isset($_GET['delete'])) {
    // Delete announcement
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM announcement WHERE announcementId=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: admin_announcements.php?success=deleted");
    } else {
        header("Location: admin_announcements.php?error=" . urlencode("Failed to delete announcement"));
    }
    $stmt->close();
    exit();
}

// Fetch all active announcements (those not yet expired) with admin details
$currentDateTime = date('Y-m-d H:i:s');
$query = "SELECT a.*, ad.username AS admin_username 
          FROM announcement a 
          JOIN admin ad ON a.adminId = ad.adminId 
          WHERE a.endDate >= ?
          ORDER BY a.isPriority DESC, a.date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $currentDateTime);
$stmt->execute();
$announcements = $stmt->get_result();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Announcements</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>

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
        
        /* Table Styles */
        .announcements-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .announcements-table th, 
        .announcements-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .announcements-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .announcements-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .priority-high {
            color: #e74c3c;
            font-weight: 600;
        }
        
        .category-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .category-employee {
            background-color: #3498db;
            color: white;
        }
        
        .category-promotional {
            background-color: #2ecc71;
            color: white;
        }
        
        .category-regular {
            background-color: #f39c12;
            color: white;
        }
        
        .category-maintenance {
            background-color: #9b59b6;
            color: white;
        }
        
        .category-event {
            background-color: #e74c3c;
            color: white;
        }
        
        .category-policy {
            background-color: #34495e;
            color: white;
        }
        
        /* Modal Styles */
        .modal-header {
            background-color: #3498db;
            color: white;
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
            
            .announcements-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Expiring soon warning */
        .expiring-soon {
            background-color: #fff3cd;
        }
        
        .expired {
            display: none; /* Expired announcements are now automatically deleted */
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
            <li><a class="nav-link" href="admin_management.php">REPORTS MANAGEMENT</a></li>
            <li class="active"><a class="nav-link" href="admin_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="admin_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="page-header">
            <h2>ANNOUNCEMENTS</h2>
            <div class="user-info">
                Welcome, <?php echo $_SESSION['username']; ?> <i class="bi bi-person-circle"></i>
            </div>
        </div>

        <!-- Add Announcement Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
            <i class="bi bi-plus-circle"></i> Add Announcement
        </button>

        <!-- Announcements Table -->
        <table class="announcements-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>End Date</th>
                    <th>Priority</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($announcement = $announcements->fetch_assoc()): 
                    $endDate = strtotime($announcement['endDate']);
                    $isExpiringSoon = ($endDate - time()) < (24 * 60 * 60); // Less than 24 hours remaining
                ?>
                <tr class="<?php echo $isExpiringSoon ? 'expiring-soon' : ''; ?>">
                    <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                    <td>
                        <span class="category-badge category-<?php echo strtolower($announcement['category']); ?>">
                            <?php echo htmlspecialchars($announcement['category']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y h:i A', strtotime($announcement['date'])); ?></td>
                    <td><?php echo date('M d, Y h:i A', $endDate); ?></td>
                    <td>
                        <?php if ($announcement['isPriority']): ?>
                            <span class="priority-high"><i class="bi bi-exclamation-triangle-fill"></i> High</span>
                        <?php else: ?>
                            <span>Normal</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?php echo $announcement['announcementId']; ?>">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <a href="admin_announcements.php?delete=<?php echo $announcement['announcementId']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    </td>
                </tr>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Announcement <?php echo htmlspecialchars($_GET['success']); ?> successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Error: <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit Announcement Modal -->
                <div class="modal fade" id="editAnnouncementModal<?php echo $announcement['announcementId']; ?>" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="admin_announcements.php">
                                <div class="modal-body">
                                    <input type="hidden" name="id" value="<?php echo $announcement['announcementId']; ?>">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="Employee" <?php echo $announcement['category'] == 'Employee' ? 'selected' : ''; ?>>Employee</option>
                                            <option value="Promotional" <?php echo $announcement['category'] == 'Promotional' ? 'selected' : ''; ?>>Promotional</option>
                                            <option value="Regular" <?php echo $announcement['category'] == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                            <option value="Maintenance" <?php echo $announcement['category'] == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                            <option value="Event" <?php echo $announcement['category'] == 'Event' ? 'selected' : ''; ?>>Event</option>
                                            <option value="Policy" <?php echo $announcement['category'] == 'Policy' ? 'selected' : ''; ?>>Policy</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($announcement['description']); ?></textarea>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="isPriority" name="isPriority" <?php echo $announcement['isPriority'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isPriority">High Priority</label>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="startDate" class="form-label">Start Date</label>
                                            <input type="datetime-local" class="form-control" id="startDate" name="startDate" value="<?php echo date('Y-m-d\TH:i', strtotime($announcement['startDate'])); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="endDate" class="form-label">End Date</label>
                                            <input type="datetime-local" class="form-control" id="endDate" name="endDate" value="<?php echo date('Y-m-d\TH:i', strtotime($announcement['endDate'])); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Add Announcement Modal -->
        <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAnnouncementModalLabel">Add New Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="admin_announcements.php">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="Employee">Employee</option>
                                    <option value="Promotional">Promotional</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Event">Event</option>
                                    <option value="Policy">Policy</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="isPriority" name="isPriority">
                                <label class="form-check-label" for="isPriority">High Priority</label>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="datetime-local" class="form-control" id="startDate" name="startDate" min="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="datetime-local" class="form-control" id="endDate" name="endDate" min="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="create" class="btn btn-primary">Create Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Client-side validation for end date
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            
            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                });
                
                endDateInput.addEventListener('change', function() {
                    if (new Date(this.value) < new Date(startDateInput.value)) {
                        alert('End date must be after start date');
                        this.value = '';
                    }
                });
            }
            
            // Set default start date to now if not set
            if (startDateInput && !startDateInput.value) {
                const now = new Date();
                const timezoneOffset = now.getTimezoneOffset() * 60000;
                const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
                startDateInput.value = localISOTime;
            }
            
            // Set default end date to 1 day from now if not set
            if (endDateInput && !endDateInput.value) {
                const now = new Date();
                now.setDate(now.getDate() + 1);
                const timezoneOffset = now.getTimezoneOffset() * 60000;
                const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
                endDateInput.value = localISOTime;
            }
        });
    </script>
</body>
</html>
