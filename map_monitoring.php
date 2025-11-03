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

require_once('config.php');
require_once('api_keys.php');

// Check if this is an AJAX request for locations data
if (isset($_GET['get_locations']) && $_GET['get_locations'] == '1') {
    header('Content-Type: application/json');
    
    try {
        // Get event locations with booking details
        $sql = "SELECT 
                    b.bookingId,
                    b.eventLocation,
                    b.dateOfBooking,
                    b.dateOfReturn,
                    b.bookingStatus,
                    b.connectionStatus,
                    c.username AS customerName,
                    p.packageName
                FROM booking b
                LEFT JOIN customer c ON b.customerId = c.customerId
                LEFT JOIN package p ON b.packageId = p.packageId
                WHERE b.eventLocation IS NOT NULL AND b.eventLocation != ''";
        
        // Add status filter if provided
        if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
            $status = $conn->real_escape_string($_GET['status_filter']);
            $sql .= " AND b.bookingStatus = '$status'";
        }
                
        $result = $conn->query($sql);
        
        $locations = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
        }
        
        echo json_encode($locations);
        exit();
    } catch(Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Realtime Monitoring</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
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
        
        .sidebar-menu li.active {
            background-color: #34485f;
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


    body, html {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        height: 100%;
        background-color: #f8f9fa;
        color: #333;
    }
    
    #map-container {
        margin-left: 250px;
        width: calc(100% - 250px);
        height: 100vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        background: white;
        overflow: hidden;
    }
    
    #map {
        flex: 1;
        width: 100%;
        min-height: 0; /* Fix for flexbox in some browsers */
    }
    
    .controls {
        background: #3498db;
        padding: 15px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
        flex-wrap: wrap;
        gap: 15px;
        flex-shrink: 0;
    }
    
    .controls h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }
    
    .controls-left, .controls-right {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    #refresh, #filter-btn, #search-btn {
        background: white;
        color: #3498db;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    #refresh:hover, #filter-btn:hover, #search-btn:hover {
        background: #f1f1f1;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.15);
    }
    
    #status {
        font-size: 14px;
        background: rgba(255,255,255,0.2);
        padding: 5px 10px;
        border-radius: 4px;
    }
    
    .loader {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
        display: none;
        margin: 20px auto;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .map-info-window {
        padding: 15px;
        min-width: 250px;
    }
    
    .map-info-window h3 {
        margin-top: 0;
        color: #3498db;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
        font-size: 1.1rem;
    }
    
    .booking-detail {
        margin-bottom: 10px;
        line-height: 1.4;
    }
    
    .booking-detail-label {
        font-weight: 600;
        color: #555;
        display: inline-block;
        min-width: 90px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-pending {
        background-color: #FFF3CD;
        color: #856404;
    }
    
    .status-confirmed {
        background-color: #D4EDDA;
        color: #155724;
    }
    
    .status-in-progress {
        background-color: #CCE5FF;
        color: #004085;
    }
    
    .status-completed {
        background-color: #D1ECF1;
        color: #0C5460;
    }
    
    .status-cancelled {
        background-color: #F8D7DA;
        color: #721C24;
    }
    
    .status-for-approval {
        background-color: #E2E3E5;
        color: #383D41;
    }
    
    .connection-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .connected {
        background-color: #d4edda;
        color: #155724;
    }

    .connecting {
        background-color: #cce5ff;
        color: #004085;
    }

    .connection-error {
        background-color: #f8d7da;
        color: #721c24;
    }

    .unknown {
        background-color: #e2e3e5;
        color: #383d41;
    }
    
    #status-filter, #location-search {
        padding: 8px 12px;
        border-radius: 4px;
        border: none;
        font-size: 14px;
        width: 200px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    #status-filter:focus, #location-search:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(52,152,219,0.3);
    }
    
    .highlighted-marker {
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.3);
            opacity: 0.7;
        }
        100% {
            transform: scale(1);
            opacity: 1;
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
            <li class="active"><a class="nav-link" href="admin_booking.php">BOOKING MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_management.php">REPORTS MANAGEMENT</a></li>
            <li><a class="nav-link" href="admin_announcements.php">ANNOUNCEMENTS</a></li>
            <li><a class="nav-link" href="admin_resetpass.php">RESET PASSWORD</a></li>
            <li><span><a class="nav-link" href="logout.php">LOGOUT</a></span></li>
        </ul>
    </div>

    <div id="map-container">
        <div class="controls">
            <div class="controls-left">
                <h2>Booking Realtime Monitoring</h2>
                <select id="status-filter">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="In-progress">In-progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="For Approval">For Approval</option>
                </select>
                <button id="filter-btn">Filter</button>
            </div>
            <div class="controls-right">
                <input type="text" id="location-search" placeholder="Search location...">
                <button id="search-btn">Search</button>
                <button id="refresh">Refresh</button>
                <span id="status">Loading map...</span>
            </div>
        </div>
        <div id="map"></div>
        <div class="loader" id="loader"></div>
    </div>
    
    <script>
        let map;
        let markers = [];
        let infoWindows = [];
        const philippines = { lat: 12.8797, lng: 121.7740 };

        // Status badge classes mapping
        const statusClasses = {
            'Pending': 'status-pending',
            'Confirmed': 'status-confirmed',
            'In-progress': 'status-in-progress',
            'Completed': 'status-completed',
            'Cancelled': 'status-cancelled',
            'For Approval': 'status-for-approval'
        };

        // Initialize the map
        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 6,
                center: philippines,
                mapTypeId: 'roadmap'
            });
            
            // Load initial markers
            loadMarkers();
            
            // Set up refresh button
            document.getElementById('refresh').addEventListener('click', loadMarkers);
            
            // Set up filter button
            document.getElementById('filter-btn').addEventListener('click', loadMarkers);
            
            // Set up search button
            document.getElementById('search-btn').addEventListener('click', searchLocation);
            
            // Also search when pressing Enter in the search field
            document.getElementById('location-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchLocation();
                }
            });
            
            // Auto-refresh every 5 minutes
            setInterval(loadMarkers, 300000);
        }

        // Search for a specific location
        function searchLocation() {
            const searchQuery = document.getElementById('location-search').value.trim();
            if (!searchQuery) return;
            
            document.getElementById('status').textContent = 'Searching for location...';
            document.getElementById('loader').style.display = 'block';
            
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ address: searchQuery + ', Philippines' }, (results, status) => {
                document.getElementById('loader').style.display = 'none';
                
                if (status === "OK" && results[0]) {
                    const location = results[0].geometry.location;
                    const bounds = new google.maps.LatLngBounds();
                    
                    // First try to find an exact match among our markers
                    let foundExactMatch = false;
                    markers.forEach(markerObj => {
                        const marker = markerObj.marker;
                        if (marker.getTitle().toLowerCase().includes(searchQuery.toLowerCase())) {
                            bounds.extend(marker.getPosition());
                            foundExactMatch = true;
                            
                            // Highlight the matching marker
                            marker.setAnimation(google.maps.Animation.BOUNCE);
                            setTimeout(() => {
                                marker.setAnimation(null);
                            }, 2000);
                            
                            // Open its info window
                            markerObj.infowindow.open(map, marker);
                        }
                    });
                    
                    if (foundExactMatch) {
                        map.fitBounds(bounds);
                        if (map.getZoom() > 15) map.setZoom(15);
                        document.getElementById('status').textContent = `Showing results for "${searchQuery}"`;
                    } else {
                        // If no exact match found, just center on the searched location
                        map.setCenter(location);
                        map.setZoom(15);
                        document.getElementById('status').textContent = `Showing area for "${searchQuery}"`;
                    }
                } else {
                    document.getElementById('status').textContent = 'Location not found';
                }
            });
        }

        // Load markers from server
        function loadMarkers() {
            document.getElementById('status').textContent = 'Loading locations...';
            document.getElementById('loader').style.display = 'block';
            
            // Clear existing markers
            clearMarkers();
            
            // Get selected status filter
            const statusFilter = document.getElementById('status-filter').value;
            
            // Fetch locations from server
            let url = window.location.href + '?get_locations=1';
            if (statusFilter) {
                url += '&status_filter=' + encodeURIComponent(statusFilter);
            }
            
            fetch(url)
                .then(response => response.json())
                .then(locations => {
                    if (locations.error) {
                        document.getElementById('status').textContent = 'Error: ' + locations.error;
                        return;
                    }
                    
                    document.getElementById('status').textContent = `Showing ${locations.length} locations (updated: ${new Date().toLocaleTimeString()})`;
                    
                    if (locations.length === 0) {
                        document.getElementById('loader').style.display = 'none';
                        return;
                    }
                    
                    // Geocode each location and add marker
                    let geocodedCount = 0;
                    const geocoder = new google.maps.Geocoder();
                    
                    locations.forEach(location => {
                        const address = location.eventLocation + ', Philippines';
                        geocoder.geocode({ address: address }, (results, status) => {
                            if (status === "OK" && results[0]) {
                                const position = results[0].geometry.location;

                                // Create marker
                                const marker = new google.maps.Marker({
                                    map: map,
                                    position: position,
                                    title: location.eventLocation
                                });

                                // Determine radius by package (in meters)
                                let radius = 0;
                                switch ((location.packageName || '').toLowerCase()) {
                                    case 'basic kit':
                                        radius = 9.73; // 1km radius
                                        break;
                                    case 'boost kit':
                                        radius = 12.58; // 2km radius
                                        break;
                                    case 'robust kit':
                                        radius = 16.88; // 3km radius
                                        break;
                                    case 'advanced kit':
                                        radius = 20.33; // 5km radius
                                        break;
                                    default:
                                        radius = 10; // default 500m radius
                                }

                                // Determine circle color based on connectionStatus
                                let circleColor;
                                if (location.connectionStatus) {
                                    switch (location.connectionStatus.toLowerCase()) {
                                        case 'connected':
                                            circleColor = '#28a745'; // Green
                                            break;
                                        case 'connecting':
                                            circleColor = '#007BFF'; // Blue
                                            break;
                                        case 'connection error':
                                            circleColor = '#dc3545'; // Red
                                            break;
                                        default:
                                            circleColor = '#6c757d'; // Gray (default)
                                    }
                                } else {
                                    circleColor = '#6c757d'; // Gray if no status
                                }

                                // Draw circle
                                const circle = new google.maps.Circle({
                                    strokeColor: circleColor,
                                    strokeOpacity: 0.8,
                                    strokeWeight: 2,
                                    fillColor: circleColor,
                                    fillOpacity: 0.2,
                                    map: map,
                                    center: position,
                                    radius: radius // in meters
                                });

                                // Info window setup
                                const statusClass = statusClasses[location.bookingStatus] || 'status-pending';
                                const connectionStatus = location.connectionStatus || 'Unknown';
                                const connectionStatusClass = connectionStatus.toLowerCase().replace(' ', '-');
                                
                                const infoContent = `
                                    <div class="map-info-window">
                                        <h3>${location.packageName || 'Package'}</h3>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Customer:</span>
                                            ${location.customerName || 'N/A'}
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Location:</span>
                                            ${location.eventLocation || 'N/A'}
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Status:</span>
                                            <span class="status-badge ${statusClass}">
                                                ${location.bookingStatus || 'Pending'}
                                            </span>
                                        </div>
                                        <div class="booking-detail">
                                            <span class="booking-detail-label">Connection:</span>
                                            <span class="connection-status ${connectionStatusClass}">
                                                ${connectionStatus}
                                            </span>
                                        </div>
                                        
                                    </div>
                                `;

                                const infowindow = new google.maps.InfoWindow({
                                    content: infoContent
                                });

                                marker.addListener("click", () => {
                                    infowindow.open(map, marker);
                                });

                                // Store both marker and circle if you need to clear them later
                                markers.push({
                                    marker: marker,
                                    circle: circle,
                                    infowindow: infowindow
                                });
                            }

                            geocodedCount++;
                            if (geocodedCount === locations.length) {
                                document.getElementById('loader').style.display = 'none';
                                if (markers.length > 0) {
                                    const bounds = new google.maps.LatLngBounds();
                                    markers.forEach(obj => {
                                        bounds.extend(obj.marker.getPosition());
                                    });
                                    map.fitBounds(bounds);
                                    if (map.getZoom() > 15) map.setZoom(15);
                                }
                            }
                        });
                    });
                })
                .catch(error => {
                    document.getElementById('status').textContent = 'Error loading locations';
                    document.getElementById('loader').style.display = 'none';
                    console.error('Error:', error);
                });
        }

        // Format date for display
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }

        // Clear all markers and circles from the map
        function clearMarkers() {
            markers.forEach(obj => {
                if (obj.marker) obj.marker.setMap(null);
                if (obj.circle) obj.circle.setMap(null);
                if (obj.infowindow) obj.infowindow.close();
            });
            markers = [];
        } 
    </script>
    
    <!-- Load Google Maps API with your API key -->
    <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_api_key; ?>&callback=initMap">
    </script>
</body>
</html>