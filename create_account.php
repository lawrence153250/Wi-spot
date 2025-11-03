<?php
session_start();

require_once 'config.php';

// Get current admin ID
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

$success_message = '';
$error_message = '';

// Default password for all accounts
$default_password = "Wispot123";
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Get common form data
    $userType = htmlspecialchars($_POST['userType']);
    $username = htmlspecialchars($_POST['username']);
    $email = htmlspecialchars($_POST['email']);

    // Handle different user types
    switch ($userType) {
        case 'admin':
            $fullName = htmlspecialchars($_POST['fullNameAdmin']);
            
            $sql = "INSERT INTO admin (userName, email, password, fullName) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $fullName);
            break;

        case 'staff':
            $fullName = htmlspecialchars($_POST['fullNameStaff']);
            $position = htmlspecialchars($_POST['position']);
            
            $sql = "INSERT INTO staff (userName, email, password, fullName, position, adminId) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $username, $email, $hashed_password, $fullName, $position, $adminId);
            break;

        case 'customer':
            $firstName = htmlspecialchars($_POST['firstName']);
            $lastName = htmlspecialchars($_POST['lastName']);
            $contactNumber = htmlspecialchars($_POST['contactNumber']);
            $address = htmlspecialchars($_POST['address']);
            $birthday = htmlspecialchars($_POST['birthday']);
            $facebookProfile = htmlspecialchars($_POST['facebookProfile']);
            
            $sql = "INSERT INTO customer (userName, email, password, firstName, lastName, 
                    contactNumber, address, birthday, facebookProfile) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $username, $email, $hashed_password, $firstName, $lastName,
                            $contactNumber, $address, $birthday, $facebookProfile);
            break;

        default:
            $error_message = "Invalid user type selected!";
            break;
    }

    if (empty($error_message)) {
        if ($stmt->execute()) {
            $success_message = ucfirst($userType) . " account created successfully with default password!";
            // Clear form if successful
            $_POST = array();
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
            max-width: 800px;
            margin: 30px auto;
        }

        .form-title {
            color: var(--secondary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .form-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background-color: white;
            outline: none;
        }

        .btn-submit {
            background: linear-gradient(to right, var(--primary-color), #2980b9);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .form-col {
            flex: 0 0 100%;
            padding: 0 15px;
        }

        @media (min-width: 768px) {
            .form-col {
                flex: 0 0 50%;
            }
        }

        .user-type-selector {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 15px;
        }

        .user-type-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-type-btn.active {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
            font-weight: 500;
        }

        .conditional-field {
            display: none;
        }

        .conditional-field.active {
            display: block;
        }

        .password-note {
            background-color: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="form-container">
            <div class="row">
                <div class="col-sm-3">
                    <a href="admin_accounts.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                </div>
                <div class="col-sm-6"><h3 class="form-title">User Registration</h3></div>
            </div>
                
            

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                    <div><strong>Default Password:</strong> Wispot123</div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="password-note">
                <i class="bi bi-info-circle"></i> All accounts will be created with the default password: <strong>Wispot123</strong>
            </div>

            <form method="POST" action="">
                <div class="user-type-selector">
                    <label class="user-type-btn <?php echo (!isset($_POST['userType']) || $_POST['userType'] === 'customer') ? 'active' : ''; ?>">
                        <input type="radio" name="userType" value="customer" <?php echo (!isset($_POST['userType']) || $_POST['userType'] === 'customer') ? 'checked' : ''; ?> hidden>
                        Customer
                    </label>
                    <label class="user-type-btn <?php echo (isset($_POST['userType']) && $_POST['userType'] === 'staff') ? 'active' : ''; ?>">
                        <input type="radio" name="userType" value="staff" <?php echo (isset($_POST['userType']) && $_POST['userType'] === 'staff') ? 'checked' : ''; ?> hidden>
                        Staff
                    </label>
                    <label class="user-type-btn <?php echo (isset($_POST['userType']) && $_POST['userType'] === 'admin') ? 'active' : ''; ?>">
                        <input type="radio" name="userType" value="admin" <?php echo (isset($_POST['userType']) && $_POST['userType'] === 'admin') ? 'checked' : ''; ?> hidden>
                        Admin
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo $_POST['username'] ?? ''; ?>" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Admin Fields -->
                <div class="conditional-field <?php echo (isset($_POST['userType'])) && $_POST['userType'] === 'admin' ? 'active' : ''; ?>" id="admin-fields">
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" class="form-control" id="fullNameAdmin" name="fullNameAdmin" value="<?php echo $_POST['fullNameAdmin'] ?? ''; ?>">
                    </div>
                </div>

                <!-- Staff Fields -->
                <div class="conditional-field <?php echo (isset($_POST['userType'])) && $_POST['userType'] === 'staff' ? 'active' : ''; ?>" id="staff-fields">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" class="form-control" id="fullNameStaff" name="fullNameStaff" value="<?php echo $_POST['fullNameStaff'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="position">Position</label>
                                <input type="text" class="form-control" id="position" name="position" value="<?php echo $_POST['position'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Fields -->
                <div class="conditional-field <?php echo (!isset($_POST['userType'])) || $_POST['userType'] === 'customer' ? 'active' : ''; ?>" id="customer-fields">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo $_POST['firstName'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo $_POST['lastName'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="contactNumber">Contact Number</label>
                                <input type="text" class="form-control" id="contactNumber" name="contactNumber" value="<?php echo $_POST['contactNumber'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="birthday">Birthday</label>
                                <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo $_POST['birthday'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea class="form-control" id="address" name="address"><?php echo $_POST['address'] ?? ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="facebookProfile">Facebook Profile URL</label>
                        <input type="url" class="form-control" id="facebookProfile" name="facebookProfile" value="<?php echo $_POST['facebookProfile'] ?? ''; ?>">
                    </div>
                </div>

                <button type="submit" name="register" class="btn-submit">
                    <i class="bi bi-person-plus"></i> Register Account
                </button>
            </form>
        </div>
    </div>

    <script>
        // Show/hide fields based on user type selection
        document.querySelectorAll('input[name="userType"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Hide all conditional fields
                document.querySelectorAll('.conditional-field').forEach(field => {
                    field.classList.remove('active');
                });
                
                // Show the selected one
                const selectedType = this.value;
                document.getElementById(`${selectedType}-fields`).classList.add('active');
                
                // Update button styles
                document.querySelectorAll('.user-type-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.parentElement.classList.add('active');
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>