<?php
// Start output buffering to catch accidental output
ob_start();

// Start session and handle timeouts
session_start();
$inactive = 900; // 15 minutes

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

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Now include HTML/other output
include 'chatbot-widget.html';

// Rest of your code...
$username = $_SESSION['username'];
require_once 'config.php';
require_once 'api_keys.php';

// Fetch user information safely using prepared statements
$stmt = $conn->prepare("SELECT * FROM customer WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $customerId = $user['customerId'];
} else {
    die("User not found.");
}

$stmt->close();

// Set the default time zone to Philippine time
date_default_timezone_set('Asia/Manila');

// Get the current date in the desired format 
$effectiveDate = date("F j, Y");

// Function to get all booked date ranges from the database
function getBookedDateRanges($conn) {
    $bookedRanges = array();
    $result = $conn->query("SELECT dateOfBooking, dateOfReturn FROM booking");
    
    while ($row = $result->fetch_assoc()) {
        $bookedRanges[] = array(
            'start' => $row['dateOfBooking'],
            'end' => $row['dateOfReturn']
        );
    }
    
    return $bookedRanges;
}

// Get all booked date ranges
$bookedRanges = getBookedDateRanges($conn);
$bookedRangesJson = json_encode($bookedRanges);

// Initialize $bookedDates array
$bookedDates = array();

// Populate $bookedDates with all individual booked dates
foreach ($bookedRanges as $range) {
    $start = new DateTime($range['start']);
    $end = new DateTime($range['end']);
    
    // Include the end date in the range
    $end = $end->modify('+1 day');
    
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    
    foreach ($period as $date) {
        $bookedDates[] = $date->format('Y-m-d');
    }
}

function formatDate($dateString) {
    return date("F j, Y", strtotime($dateString));
}

// Function to generate the agreement content
function generateAgreementContent($user, $effectiveDate, $signatureData) {
    $agreementContent = "Starlink Device Lending Agreement\n\n";
    $agreementContent .= "This Device Lending Agreement (\"Agreement\") is entered into on this day $effectiveDate between:\n\n";
    $agreementContent .= "Lender: Joshua Ed Napila, an individual residing at 52 Eagle Street, Don Mariano Subdivision, Cainta, Rizal (\"Lender\").\n\n";
    $agreementContent .= "Borrower: {$user['firstName']} {$user['lastName']}, an individual residing at {$user['address']}.\n\n";
    $agreementContent .= "Terms and Conditions:\n\n";
    $agreementContent .= "1. Device Description:\n";
    $agreementContent .= "   - Model: KIT303105607/Gen 2\n";
    $agreementContent .= "   - Serial Number: 2DWC235000042417\n";
    $agreementContent .= "   - Router ID: 01000000000000000044E2CD\n\n";
    $agreementContent .= "2. Loan Period: The Borrower acknowledges and agrees that the Device is being loaned for a period commencing on the Start Date and ending on the agreed-upon return date.\n\n";
    $agreementContent .= "3. Purpose: The Borrower agrees to use the Device solely for personal use and not for any commercial purposes.\n\n";
    $agreementContent .= "4. Care and Maintenance: The Borrower shall use the Device in a careful and proper manner.\n\n";
    $agreementContent .= "5. Return Condition: The Borrower shall return the Device to the Lender in the same condition as it was received.\n\n";
    $agreementContent .= "6. Loss or Damage: The Borrower shall be liable for any loss, theft, or damage to the Device.\n\n";
    $agreementContent .= "7. Indemnification: The Borrower agrees to indemnify and hold harmless the Lender from any claims.\n\n";
    $agreementContent .= "8. Ownership: The Borrower acknowledges that the Device is and shall remain the property of the Lender.\n\n";
    $agreementContent .= "9. Termination: The Lender reserves the right to terminate this Agreement.\n\n";
    $agreementContent .= "10. Entire Agreement: This Agreement constitutes the entire agreement between the parties.\n\n";
    $agreementContent .= "Borrower's Signature:\n\n";
    $agreementContent .= "[Signature Image Data: $signatureData]\n\n";
    $agreementContent .= "Borrower's Name: {$user['firstName']} {$user['lastName']}\n";
    $agreementContent .= "Date: $effectiveDate\n";
    
    return $agreementContent;
}

// Function to compute price based on package and number of days
function computePrice($packageId, $dateOfBooking, $dateOfReturn) {
    $packagePrices = [1 => 1000, 2 => 1500, 3 => 4000, 4 => 5000];

    $startDate = new DateTime($dateOfBooking);
    $endDate = new DateTime($dateOfReturn);
    $interval = $startDate->diff($endDate);
    $numberOfDays = $interval->days + 1; // Add 1 to include both start and end days

    return $packagePrices[$packageId] * $numberOfDays;
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $packageId = $_POST['packageId'];
    $dateOfBooking = $_POST['dateOfBooking'];
    $dateOfReturn = $_POST['dateOfReturn'];
    $eventLocation = $_POST['eventLocation'];
    $signatureData = $_POST['lendingAgreement'];
    $totalPrice = $_POST['totalPrice'];

    // Validate date range
    if (new DateTime($dateOfBooking) > new DateTime($dateOfReturn)) {
        echo '<div class="alert alert-danger">Error: Return date must be on or after the booking date.</div>';
    } else {
        // Check if any dates in the selected range are already booked
        $start = new DateTime($dateOfBooking);
        $end = new DateTime($dateOfReturn);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        $conflict = false;
        foreach ($period as $date) {
            if (in_array($date->format('Y-m-d'), $bookedDates)) {
                $conflict = true;
                break;
            }
        }
        
        if ($conflict) {
            echo '<div class="alert alert-danger">Error: One or more dates in your selected range are already booked. Please choose different dates.</div>';
        } else {
            // Generate the agreement content
            $agreementContent = generateAgreementContent($user, $effectiveDate, $signatureData);
            
            // Create a unique filename
            $filename = "agreement_" . $customerId . "_" . time() . ".txt";
            $filepath = "agreements/" . $filename;
            
            // Save the agreement to a file
            if (!is_dir("agreements")) {
                mkdir("agreements", 0755, true);
            }
            
            file_put_contents($filepath, $agreementContent);
            
            // Insert booking with the total price, payment balance, and agreement file path
            $stmt = $conn->prepare("INSERT INTO booking (customerId, packageId, dateOfBooking, dateOfReturn, eventLocation, price, lendingAgreement, paymentBalance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssdsd", $customerId, $packageId, $dateOfBooking, $dateOfReturn, $eventLocation, $totalPrice, $filepath, $totalPrice);

            if ($stmt->execute()) {
                // Clear the custom equipment from session after successful booking
                unset($_SESSION['selected_equipment']);
                echo '<div class="alert alert-success">Booking successfully created!</div>';
            } else {
                echo '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
}

// Initialize equipment variables
$equipment_name = $_POST['equipment_name'] ?? null;
$equipment_price = $_POST['equipment_price'] ?? null;
$equipment_quantity = $_POST['equipment_quantity'] ?? 1; // Default to 1 if not specified

// If receiving multiple equipment items (array format)
$equipment_list = $_POST['equipment'] ?? []; // Array of equipment items

// Process single equipment item
if ($equipment_name && $equipment_price) {
    $equipment_item = [
        'name' => $equipment_name,
        'price' => (float)$equipment_price,
        'quantity' => (int)$equipment_quantity
    ];
    
    // Add to session or process as needed
    $_SESSION['selected_equipment'] = $equipment_item;
}

// Process multiple equipment items
if (!empty($equipment_list)) {
    $processed_equipment = [];
    
    foreach ($equipment_list as $item) {
        // Ensure we have the required fields
        if (!empty($item['name']) && isset($item['price'])) {
            $processed_equipment[] = [
                'name' => htmlspecialchars($item['name']),
                'price' => (float)$item['price'],
                'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 1
            ];
        }
    }
    
    if (!empty($processed_equipment)) {
        $_SESSION['selected_equipment'] = $processed_equipment;
    }
}

function validatePhilippineAddress($address) {
    // Use Google Geocoding API with Philippines restriction
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . 
       urlencode($address) . "&components=country:PH&key=" . $google_maps_api_key;
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data['status'] === 'OK') {
        // Additional check to ensure it's in Philippines
        foreach ($data['results'][0]['address_components'] as $component) {
            if (in_array('country', $component['types']) && 
                strtoupper($component['short_name']) === 'PH') {
                return true;
            }
        }
    }
    
    return false;
}

// Usage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['eventLocation']);
    
    if (empty($address)) {
        $errors['eventLocation'] = 'Address is required';
    } elseif (!validatePhilippineAddress($address)) {
        $errors['eventLocation'] = 'Please enter a valid Philippine address';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking page</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="bookingstyle.css">
    <!-- Include Signature Pad library -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        /* Style for disabled dates in date picker */
        input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 1;
        }

        input[type="date"]:disabled::-webkit-calendar-picker-indicator {
            opacity: 0.5;
        }

        /* Custom message for booked dates */
        .date-picker-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

    </style>
</head>
<body style="background-color: #f0f3fa;"> <nav class="navbar navbar-expand-lg navbar-dark" id="grad">
    <div class="container">
        <a class="navbar-brand" href="index.php"><img src="logoo.png" class="logo"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link" href="booking.php">BOOKING</a></li>
                <li class="nav-item"><a class="nav-link" href="mapcoverage.php">MAP COVERAGE</a></li>
                <li class="nav-item"><a class="nav-link" href="customer_voucher.php">VOUCHERS</a></li>
                <li class="nav-item"><a class="nav-link" href="aboutus.php">ABOUT US</a></li>
            </ul>

            <?php if (isset($_SESSION['username'])): ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><?= htmlspecialchars($_SESSION['username']) ?> <i class="bi bi-person-circle"></i></a>
                    </li>
                </ul>
            <?php else: ?>
                <div class="auth-buttons d-flex flex-column flex-lg-row ms-lg-auto gap-2 mt-2 mt-lg-0">
                    <a class="btn btn-primary" href="login.php">LOGIN</a>
                    <a class="nav-link" href="register.php">SIGN UP</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
    
  <div class="booking-header">
      <h1>BOOKING RESERVATION FORM</h1>
  </div>

 <h3>CLIENTS BASIC INFORMATION</h3>
<div class="user-info-section">
    <p><strong>First Name:</strong> <span><?php echo $user['firstName']; ?></span></p>
    <p><strong>Last Name:</strong> <span><?php echo $user['lastName']; ?></span></p>
    <p><strong>Email Address:</strong> <span><?php echo $user['email']; ?></span></p>
    <p><strong>Phone Number:</strong> <span><?php echo $user['contactNumber']; ?></span></p>
    <p><strong>User Role:</strong> <span><?php echo $user['role'] ?? 'User'; ?></span></p>
</div>

    <h3>LOAN PERIOD</h3>
    <form id="bookingForm" method="POST">
        <div class="form-group">
            <label for="dateOfBooking">Rental Date (Start): </label>
            <input type="date" id="dateOfBooking" name="dateOfBooking" required>
            <div id="bookingDateMessage" class="date-picker-message"></div>
        </div>
        <div class="form-group">
            <label for="dateOfReturn">Rental Date (End): </label>
            <input type="date" id="dateOfReturn" name="dateOfReturn" required>
            <div id="returnDateMessage" class="date-picker-message"></div>
        </div>
        <div class="form-group">
            <label for="eventLocation">Event's Location Address:</label>
            <input type="text" id="eventLocation" name="eventLocation" required autocomplete="off">
            <small id="addressHelp" class="form-text text-muted">Please enter a complete Philippine address</small>
            <div id="addressValidationFeedback" class="invalid-feedback"></div>
            <div id="apiStatus"></div>
        </div>

        <h4>Want something more personalized? Use our <a href="booking_customization.php">Booking Customization</a> to build a package that fits your needs.</h4>
        <div class="form-group">
            <label>Choose a Package:</label>
            <div class="package-selection">
                <label class="package-option">
                    <input type="radio" name="packageId" value="1">
                    <img src="package1.png" alt="Package 1" class="package-img">
                    <span>Package 1</span>
                    <p>Price: ₱1000 per day</p>
                </label>

                <label class="package-option">
                    <input type="radio" name="packageId" value="2">
                    <img src="package2.png" alt="Package 2" class="package-img">
                    <span>Package 2</span>
                    <p>Price: ₱1500 per day</p>
                </label>

                <label class="package-option">
                    <input type="radio" name="packageId" value="3">
                    <img src="package3.png" alt="Package 3" class="package-img">
                    <span>Package 3</span>
                    <p>Price: ₱4000 per day</p>
                </label>

                <label class="package-option">
                    <input type="radio" name="packageId" value="4">
                    <img src="package4.png" alt="Package 4" class="package-img">
                    <span>Package 4</span>
                    <p>Price: ₱5000 per day</p>
                </label>
            </div>
        </div>

        <div class="user-info-section">
        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#lending" required>View Lending Agreement</button>
        </div>

        <!-- Hidden input to store signature -->
        <input type="hidden" id="lendingAgreement" name="lendingAgreement">

        <div class="form-group">
        <input type="hidden" id="totalPrice" name="totalPrice">
        <button type="button" class="btn btn-primary">Book Now</button>
        </div>
    </form>

   <div class="foot-container">
    <div class="foot-logo" style="text-align: center; margin-bottom: 1rem;">
    <img src="logofooter.png" alt="Wi-Spot Logo" style="width: 140px;">
  </div>
  <div class="foot-icons">
    <a href="https://www.facebook.com/WiSpotServices" class="bi bi-facebook" target="_blank"></a>
  </div>

  <hr>

  <div class="foot-policy">
    <div class="policy-links">
      <a href="termsofservice.php" target="_blank">TERMS OF SERVICE</a>
      <a href="copyrightpolicy.php" target="_blank">COPYRIGHT POLICY</a>
      <a href="privacypolicy.php" target="_blank">PRIVACY POLICY</a>
      <a href="contactus.php" target="_blank">CONTACT US</a>
    </div>
  </div>

  <hr>

  <div class="foot_text">
    <br>
    <p>&copy;2025 Wi-spot. All rights reserved. Wi-spot and related trademarks and logos are the property of Wi-spot. All other trademarks are the property of their respective owners.</p><br>
  </div>
</div>
</div>

    <!-- Lending Agreement Modal with Signature Pad -->
    <div class="modal fade" id="lending" tabindex="-1" aria-labelledby="lendingLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lendingLabel">Starlink Device Lending Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                <p>This Device Lending Agreement ("Agreement") is entered into on this day <strong><?php echo $effectiveDate; ?></strong> between:</p>

                <p><strong>Lender:</strong> Joshua Ed Napila, an individual residing at 52 Eagle Street, Don Mariano Subdivision, Cainta, Rizal ("Lender").</p>

                <p><strong>Borrower:</strong> <strong><?php echo $user['firstName'] .' '. $user['lastName']; ?></strong> , an individual residing at <strong><?php echo $user['address']; ?></strong> .</p>

                <h2>Background:</h2>
                <p>The Lender is the owner of a Starlink device, hereinafter referred to as the "Device," and is willing to lend it to the Borrower subject to the terms and conditions outlined in this Agreement.</p>

                <h2>Terms and Conditions:</h2>

                <ol>
                    <li>
                        <strong>Device Description:</strong> The Device subject to this Agreement is described as follows:
                        <ul>
                            <li><strong>Model:</strong> KIT303105607/Gen 2</li>
                            <li><strong>Serial Number:</strong> 2DWC235000042417</li>
                            <li><strong>Router ID:</strong> 01000000000000000044E2CD</li>
                        </ul>
                    </li>

                    <li>
                        <strong>Loan Period:</strong> The Borrower acknowledges and agrees that the Device is being loaned for a period commencing on the Start Date and ending on the agreed-upon return date, unless otherwise extended in writing by the Lender ("Loan Period").
                    </li>

                    <li>
                        <strong>Purpose:</strong> The Borrower agrees to use the Device solely for personal use and not for any commercial purposes.
                    </li>

                    <li>
                        <strong>Care and Maintenance:</strong> The Borrower shall use the Device in a careful and proper manner, following all instructions provided by the manufacturer. The Borrower shall be responsible for any damage to the Device beyond normal wear and tear during the Loan Period.
                    </li>

                    <li>
                        <strong>Return Condition:</strong> At the end of the Loan Period or upon demand by the Lender, the Borrower shall return the Device to the Lender in the same condition as it was received, ordinary wear and tear excepted.
                    </li>

                    <li>
                        <strong>Loss or Damage:</strong> The Borrower shall be liable for any loss, theft, or damage to the Device that occurs during the Loan Period, and shall reimburse the Lender for the cost of repair or replacement of the Device.
                    </li>

                    <li>
                        <strong>Indemnification:</strong> The Borrower agrees to indemnify and hold harmless the Lender from any claims, damages, liabilities, or expenses arising out of or in connection with the Borrower's use of the Device.
                    </li>

                    <li>
                        <strong>Ownership:</strong> The Borrower acknowledges that the Device is and shall remain the property of the Lender, and that the Borrower has no ownership interest or rights therein except as expressly provided in this Agreement.
                    </li>

                    <li>
                        <strong>Termination:</strong> The Lender reserves the right to terminate this Agreement and demand the immediate return of the Device at any time for any reason, upon written notice to the Borrower.
                    </li>

                    <li>
                        <strong>Entire Agreement:</strong> This Agreement constitutes the entire agreement between the parties with respect to the subject matter hereof, and supersedes all prior and contemporaneous agreements and understandings, whether written or oral, relating to such subject matter.
                    </li>
                </ol>

                    <p>Please sign if you agree</p>
                    <div id="signature-pad" class="signature-pad">
                        <canvas id="signature-canvas"></canvas><br>
                        <strong><?php echo $user['firstName'] .' '. $user['lastName']; ?></strong>
                    </div>
                    <button type="button" id="clear-signature" class="btn btn-danger mt-2">Clear Signature</button>
                </div>
                    
                <div class="modal-footer">
                    <button type="button" id="save-signature" class="btn btn-primary" data-bs-dismiss="modal">Agree</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmationModalLabel">Confirm Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Customer Name:</strong> <span id="modalCustomerName"></span></p>
                <p><strong>Rental Date (Start): </strong> <span id="modalDateOfBooking"></span></p>
                <p><strong>Rental Date (End): </strong> <span id="modalDateOfReturn"></span></p>
                <p><strong>Event Location:</strong> <span id="modalEventLocation"></span></p>
                <p><strong>Package Chosen:</strong> <span id="modalPackageChosen"></span></p>
                
                <!-- Updated Equipment Section -->
                <div class="equipment-summary mt-3">
                    <h6><strong>Additional Equipment:</strong></h6>
                    <div id="modalEquipmentList" class="mb-2">
                        <!-- Equipment items will be inserted here by JavaScript -->
                    </div>
                    <p class="text-end"><strong>Equipment Total: </strong><span id="modalEquipmentTotal">₱0.00</span></p>
                </div>
                
                <p class="mt-3"><strong>Total Price:</strong> <span id="modalTotalPrice"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                <button type="submit" form="bookingForm" name="register" class="btn btn-primary">Confirm Booking</button>
            </div>
        </div>
    </div>
</div>

    
    <!-- Signature Pad Script -->
<script>
// Main document ready function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Signature Pad
    const canvas = document.getElementById('signature-canvas');
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)'
    });

    // Clear signature button
    document.getElementById('clear-signature').addEventListener('click', function() {
        signaturePad.clear();
    });

    // Save signature button
    document.getElementById('save-signature').addEventListener('click', function() {
        if (signaturePad.isEmpty()) {
            alert('Please provide a signature first.');
        } else {
            const signatureData = signaturePad.toDataURL();
            document.getElementById('lendingAgreement').value = signatureData;
            alert('Lending Agreement has been signed successfully.');
        }
    });

    // Load Google Maps API
    loadGoogleMapsAPI();

    // Initialize date pickers
    setupDatePickers();

    // Book Now button handler
    document.querySelector('.btn-primary').addEventListener('click', handleBookingClick);
});

// Google Maps API loader
function loadGoogleMapsAPI() {
    const apiKey = '<?php echo $google_maps_api_key; ?>';
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&callback=initAutocomplete`;
    script.async = true;
    script.defer = true;
    script.onerror = function() {
        showApiError('Failed to load Google Maps API. Using basic location input.');
        setupBasicAutocomplete();
    };
    document.head.appendChild(script);
}

// Initialize Google Maps Autocomplete
function initAutocomplete() {
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        showApiError('Google Maps not available. Using basic location input.');
        setupBasicAutocomplete();
        return;
    }

    try {
        const input = document.getElementById('eventLocation');
        const options = {
            types: ['geocode'],
            componentRestrictions: { country: 'ph' },
            fields: ['address_components', 'geometry', 'formatted_address']
        };
        
        const autocomplete = new google.maps.places.Autocomplete(input, options);
        
        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                showApiError('Location not found. Please try again.');
            }
        });
        
        document.getElementById('apiStatus').textContent = '';
    } catch (error) {
        showApiError('Error initializing location search. Using basic input.');
        setupBasicAutocomplete();
    }
}

// Basic autocomplete fallback
function setupBasicAutocomplete() {
    const philippineCities = [
        "Manila", "Quezon City", "Makati", "Taguig", "Pasig",
        "Mandaluyong", "San Juan", "Pasay", "Parañaque", "Las Piñas"
    ];

    const input = document.getElementById('eventLocation');
    const datalist = document.createElement('datalist');
    datalist.id = 'citySuggestions';
    
    philippineCities.forEach(city => {
        const option = document.createElement('option');
        option.value = city;
        datalist.appendChild(option);
    });
    
    document.body.appendChild(datalist);
    input.setAttribute('list', 'citySuggestions');
}

// Date picker setup and validation
function setupDatePickers() {
    const today = new Date();
    const dateOfBookingInput = document.getElementById('dateOfBooking');
    const dateOfReturnInput = document.getElementById('dateOfReturn');
    const bookingDateMessage = document.getElementById('bookingDateMessage');
    const returnDateMessage = document.getElementById('returnDateMessage');

    // Set min date to today
    const minDate = today.toISOString().split('T')[0];
    dateOfBookingInput.min = minDate;
    dateOfReturnInput.min = minDate;

    // Get booked dates from PHP
    const bookedRanges = <?php echo $bookedRangesJson; ?>;
    const bookedDates = bookedRanges.map(range => {
        return {
            start: new Date(range.start),
            end: new Date(range.end)
        };
    });

    // Booking date change handler
    dateOfBookingInput.addEventListener('change', function() {
        dateOfReturnInput.min = this.value;
        hideMessage(bookingDateMessage);
        
        if (this.value) {
            const selectedDate = new Date(this.value);
            if (isDateBooked(selectedDate, bookedDates)) {
                showMessage(bookingDateMessage, 'This date is already booked. Please choose another date.');
                this.value = '';
            }
        }
    });

    // Return date change handler
    dateOfReturnInput.addEventListener('change', function() {
        hideMessage(returnDateMessage);
        
        if (this.value && dateOfBookingInput.value) {
            const startDate = new Date(dateOfBookingInput.value);
            const endDate = new Date(this.value);
            
            if (isDateRangeBooked(startDate, endDate, bookedDates)) {
                showMessage(returnDateMessage, 'One or more dates in your selected range are already booked. Please choose different dates.');
                this.value = '';
            }
        }
    });
}

// Date validation functions
function isDateBooked(date, bookedDates) {
    return bookedDates.some(range => {
        return date >= range.start && date <= range.end;
    });
}

function isDateRangeBooked(startDate, endDate, bookedDates) {
    for (let date = new Date(startDate); date <= endDate; date.setDate(date.getDate() + 1)) {
        if (isDateBooked(date, bookedDates)) {
            return true;
        }
    }
    return false;
}

// Booking form submission handler
function handleBookingClick(event) {
    event.preventDefault();

    // Get form values
    const signatureData = document.getElementById('lendingAgreement').value;
    const dateOfBooking = document.getElementById('dateOfBooking').value;
    const dateOfReturn = document.getElementById('dateOfReturn').value;
    const eventLocation = document.getElementById('eventLocation').value;
    const packageRadio = document.querySelector('input[name="packageId"]:checked');

    // Validation checks
    if (!signatureData) {
        alert('You need to sign the agreement first before proceeding.');
        return;
    }

    if (!dateOfBooking || !dateOfReturn) {
        alert('Please select both start and end dates.');
        return;
    }

    if (new Date(dateOfBooking) > new Date(dateOfReturn)) {
        alert('Return date must be on or after the booking date.');
        return;
    }

    if (!packageRadio) {
        alert('Please select a package.');
        return;
    }

    if (!eventLocation) {
        alert('Please enter an event location.');
        return;
    }

    // Prepare confirmation modal
    prepareConfirmationModal({
        dateOfBooking,
        dateOfReturn,
        eventLocation,
        packageId: packageRadio.value
    });

    // Show confirmation modal
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    confirmationModal.show();
}

// Prepare confirmation modal data
function prepareConfirmationModal(data) {
    const customerName = "<?php echo $user['firstName'] . ' ' . $user['lastName']; ?>";
    const packageName = document.querySelector(`input[name="packageId"][value="${data.packageId}"] + .package-img + span`).textContent;
    const packagePrice = computePrice(data.packageId, data.dateOfBooking, data.dateOfReturn);
    
    // Get equipment data
    const customEquipment = <?php echo isset($_SESSION['selected_equipment']) ? json_encode($_SESSION['selected_equipment']) : '[]'; ?>;
    let equipmentTotal = 0;
    let equipmentHTML = '';
    
    if (customEquipment.length > 0) {
        customEquipment.forEach(item => {
            const itemTotal = item.price * item.quantity;
            equipmentTotal += itemTotal;
            equipmentHTML += `
                <div class="d-flex justify-content-between">
                    <span>${item.name} x ${item.quantity}</span>
                    <span>₱${itemTotal.toFixed(2)}</span>
                </div>
            `;
        });
    } else {
        equipmentHTML = '<p class="text-muted">No additional equipment selected</p>';
    }
    
    // Update modal content
    document.getElementById('modalCustomerName').textContent = customerName;
    document.getElementById('modalDateOfBooking').textContent = formatDate(data.dateOfBooking);
    document.getElementById('modalDateOfReturn').textContent = formatDate(data.dateOfReturn);
    document.getElementById('modalEventLocation').textContent = data.eventLocation;
    document.getElementById('modalPackageChosen').textContent = packageName;
    document.getElementById('modalEquipmentList').innerHTML = equipmentHTML;
    document.getElementById('modalEquipmentTotal').textContent = `₱${equipmentTotal.toFixed(2)}`;
    
    // Calculate and display total price
    const totalPrice = packagePrice + equipmentTotal;
    document.getElementById('modalTotalPrice').textContent = `₱${totalPrice.toFixed(2)}`;
    document.getElementById('totalPrice').value = totalPrice;
}

// Helper functions
function showMessage(element, message) {
    element.textContent = message;
    element.style.display = 'block';
}

function hideMessage(element) {
    element.style.display = 'none';
}

function showApiError(message) {
    const apiStatus = document.getElementById('apiStatus');
    apiStatus.textContent = message;
    apiStatus.style.color = '#dc3545';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function computePrice(packageId, dateOfBooking, dateOfReturn) {
    const packagePrices = { 1: 1000, 2: 1500, 3: 4000, 4: 5000 };
    const startDate = new Date(dateOfBooking);
    const endDate = new Date(dateOfReturn);
    const numberOfDays = (endDate - startDate) / (1000 * 60 * 60 * 24) + 1;
    return packagePrices[packageId] * numberOfDays;
}

// Booking Page Tour - Fixed Version
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the booking page
    if (window.location.pathname.includes('booking.php')) {
        // Load Intro.js CSS dynamically
        const introCss = document.createElement('link');
        introCss.rel = 'stylesheet';
        introCss.href = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css';
        document.head.appendChild(introCss);

        // Create restart tour button
        const bookingTourBtn = document.createElement('button');
        bookingTourBtn.className = 'booking-tour-btn';
        bookingTourBtn.innerHTML = `
            <i class="bi bi-info-circle"></i> Booking Help
        `;
        bookingTourBtn.onclick = function() {
            localStorage.removeItem('bookingTourShown');
            startBookingTour();
        };
        document.body.appendChild(bookingTourBtn);

        // Add styles for the tour button
        const buttonStyle = document.createElement('style');
        buttonStyle.textContent = `
            .booking-tour-btn {
                position: fixed;
                bottom: 20px;
                left: 20px;
                z-index: 9999;
                background-color: #0d6efd;
                color: white;
                border: none;
                border-radius: 50px;
                padding: 10px 16px;
                font-size: 14px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                transition: all 0.3s;
            }
            .booking-tour-btn:hover {
                background-color: #0b5ed7;
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
            }
        `;
        document.head.appendChild(buttonStyle);

        // Add this style for the tour buttons
        const tourStyles = document.createElement('style');
        tourStyles.textContent = `
            .booking-tooltip {
                max-width: 350px;
                border-radius: 12px;
                font-family: 'Outfit', sans-serif;
            }
            .booking-tooltip .introjs-tooltip-header {
                background-color: #0d6efd;
                color: white;
                border-radius: 12px 12px 0 0;
                padding: 12px 16px;
            }
            .booking-highlight {
                box-shadow: 0 0 0 1000px rgba(0,0,0,0.7),
                            0 0 0 4px rgba(13, 110, 253, 0.8),
                            0 0 20px 10px rgba(13, 110, 253, 0.4);
            }
            /* Custom skip button styles */
            .introjs-skipbutton {
                background-color: #dc3545 !important;
                color: white !important;
                padding: 4px 8px !important;
                font-size: 12px !important;
                margin-right: 5px !important;
            }
            .introjs-skipbutton:hover {
                background-color: #c82333 !important;
            }
            /* Adjust other buttons to match */
            .introjs-nextbutton, 
            .introjs-prevbutton, 
            .introjs-donebutton {
                padding: 4px 8px !important;
                font-size: 12px !important;
            }
        `;
        document.head.appendChild(tourStyles);

        // Check if booking tour was previously skipped
        if (!localStorage.getItem('bookingTourShown')) {
            // Load Intro.js dynamically
            const introJsScript = document.createElement('script');
            introJsScript.src = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/intro.min.js';
            introJsScript.onload = function() {
                setTimeout(startBookingTour, 1500);
            };
            document.head.appendChild(introJsScript);
        }
    }
});


function startBookingTour() {
    // Ensure introJs is available
    if (typeof introJs === 'undefined') {
        console.error('Intro.js not loaded');
        return;
    }

    const steps = [
        {
            title: "📋 Booking Process Guide",
            intro: "Let us walk you through the booking form to make your reservation easy and clear!",
            position: 'center'
        },
        {
            element: document.querySelector('.user-info-section'),
            title: "👤 Your Information",
            intro: "This section shows your account details that we'll use for the booking. Please verify they're correct.",
            position: 'right'
        },
        {
            element: document.querySelector('#dateOfBooking'),
            title: "📅 Rental Start Date",
            intro: "Select when you need the WiFi service to begin. Choose a date at least 2 days from today for processing.",
            position: 'right'
        },
        {
            element: document.querySelector('#dateOfReturn'),
            title: "📅 Rental End Date",
            intro: "Select when you'll return the equipment. Rental duration affects the total price.",
            position: 'right'
        },
        {
            element: document.querySelector('#eventLocation'),
            title: "📍 Event Location",
            intro: "Enter the complete address where the WiFi will be used. This helps us check coverage and delivery options.",
            position: 'right'
        },
        {
            element: document.querySelector('a[href="booking_customization.php"]'),
            title: "✨ Customization Options",
            intro: "Need special requirements? Click here to customize your package beyond our standard options.",
            position: 'right'
        },
        {
            element: document.querySelector('.package-selection'),
            title: "📦 Package Selection",
            intro: "Choose the package that fits your needs. Each includes different equipment and bandwidth options.",
            position: 'top'
        },
        {
            element: document.querySelector('[data-bs-target="#lending"]'),
            title: "📜 Lending Agreement",
            intro: "Please review our terms and conditions before proceeding with your booking.",
            position: 'top'
        },
        {
            element: document.querySelector('.btn-primary'),
            title: "✅ Final Step",
            intro: "After selecting your package and reviewing the agreement, click here to complete your booking!",
            position: 'top'
        },
        {
            title: "🎉 Ready to Book!",
            intro: "You're now familiar with the booking process! Complete all fields to reserve your WiFi package.",
            position: 'center'
        }
    ];

    // Filter out any null elements and log warnings
    const validSteps = steps.filter(step => {
        if (step.element && !document.body.contains(step.element)) {
            console.warn('Tour element not found:', step.title, step.element);
            return false;
        }
        return true;
    });

    // Debug: Log the steps we're about to show
    console.log('Starting tour with steps:', validSteps);

    try {
        introJs().setOptions({
            steps: validSteps,
            nextLabel: 'Next →',
            prevLabel: '← Back',
            skipLabel: 'Skip',
            doneLabel: 'Finish',
            exitOnOverlayClick: false,
            tooltipClass: 'booking-tooltip',
            highlightClass: 'booking-highlight'
        })
        .oncomplete(function() {
            localStorage.setItem('bookingTourShown', 'true');
        })
        .onexit(function() {
            localStorage.setItem('bookingTourShown', 'true');
        })
        .start();
    } catch (e) {
        console.error('Error starting tour:', e);
    }
}

// Add tour styles
const tourStyles = document.createElement('style');
tourStyles.textContent = `
    .booking-tooltip {
        max-width: 350px;
        border-radius: 12px;
        font-family: 'Outfit', sans-serif;
    }
    .booking-tooltip .introjs-tooltip-header {
        background-color: #0d6efd;
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 12px 16px;
    }
    .booking-highlight {
        box-shadow: 0 0 0 1000px rgba(0,0,0,0.7),
                    0 0 0 4px rgba(13, 110, 253, 0.8),
                    0 0 20px 10px rgba(13, 110, 253, 0.4);
    }
`;
document.head.appendChild(tourStyles);
</script>
</body>
</html>