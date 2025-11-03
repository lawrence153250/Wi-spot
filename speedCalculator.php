<?php
session_start();
include 'chatbot-widget.html';
// Define bandwidth requirements for different websites/services (in Mbps)
$bandwidthRequirements = [
    // Social Media
    'facebook' => 1,
    'twitter' => 1,
    'instagram' => 2,
    'linkedin' => 1,
    
    // Video Platforms
    'netflix' => [
        'sd' => 3,
        'hd' => 5,
        'uhd' => 25
    ],
    'youtube' => [
        'sd' => 1.5,
        'hd' => 2.5,
        'full_hd' => 5,
        'ultra_hd' => 20
    ],
    'tiktok' => 2.5,
    
    // Live Streaming (Watching)
    'twitch_watching' => [
        'low' => 3,
        'medium' => 4.5,
        'high' => 6,
        'source' => 8
    ],
    'youtube_live_watching' => [
        'sd' => 1.5,
        'hd' => 2.5,
        'full_hd' => 5,
        'ultra_hd' => 20
    ],
    'facebook_live_watching' => [
        'sd' => 1.5,
        'hd' => 3,
        'full_hd' => 6
    ],
    
    // Live Streaming (Broadcasting)
    'twitch_broadcasting' => [
        '720p' => 4.5,
        '1080p' => 6,
        '1440p' => 8,
        '4k' => 15
    ],
    'youtube_live_broadcasting' => [
        '720p' => 4.5,
        '1080p' => 6,
        '1440p' => 8,
        '4k' => 15
    ],
    'facebook_live_broadcasting' => [
        '720p' => 4,
        '1080p' => 6,
        '4k' => 12
    ],
    'tiktok_live_broadcasting' => [
        'standard' => 3,
        'hd' => 5
    ],
    
    // Video Conferencing
    'zoom' => [
        'hd' => 2.5,
        'full_hd' => 3.5,
        'group' => 5
    ],
    'teams' => [
        'hd' => 2,
        'full_hd' => 3,
        'group' => 4
    ],
    'google_meet' => [
        'hd' => 2.5,
        'full_hd' => 3.5
    ],
    
    // Other Services
    'spotify' => 0.5,
    'gaming' => [
        'casual' => 3,
        'competitive' => 5,
        'streaming' => 8
    ],
    'downloads' => [
        'small' => 5,
        'medium' => 10,
        'large' => 20,
        'frequent' => 30
    ],
    'cloud_storage' => 5,
    'vpn' => 2
];


// Initialize variables
$selectedServices = [];
$requiredBandwidth = 0;
$qualityLevels = [];
$numberOfUsers = 1;
$showResult = false;


// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calculate'])) {
        $selectedServices = $_POST['services'] ?? [];
        $qualityLevels = $_POST['quality'] ?? [];
        $numberOfUsers = max(1, intval($_POST['users'] ?? 1));
        $requiredBandwidth = 0;
        $showResult = true;
        
        // Calculate bandwidth for each service
        $serviceBandwidths = [];
        foreach ($selectedServices as $service) {
            if (isset($bandwidthRequirements[$service])) {
                if (is_array($bandwidthRequirements[$service])) {
                    $quality = $qualityLevels[$service] ?? array_key_first($bandwidthRequirements[$service]);
                    $serviceBandwidths[$service] = $bandwidthRequirements[$service][$quality];
                } else {
                    $serviceBandwidths[$service] = $bandwidthRequirements[$service];
                }
            }
        }
        
        // Sort services by bandwidth (highest first)
        arsort($serviceBandwidths);
        $services = array_keys($serviceBandwidths);
        $bandwidthValues = array_values($serviceBandwidths);
        
        // Calculate realistic bandwidth needs
        if ($numberOfUsers === 1) {
            // For single user: top 2 most demanding services + 50% of others
            $requiredBandwidth = array_sum(array_slice($bandwidthValues, 0, 2));
            $requiredBandwidth += 0.5 * array_sum(array_slice($bandwidthValues, 2));
        } else {
            // For multiple users: sophisticated scaling
            $activeUsers = min($numberOfUsers, 5); // Assume max 5 truly concurrent users
            
            // Get top services (max 2 per user)
            $topServices = min(count($services), $activeUsers * 2);
            $requiredBandwidth = array_sum(array_slice($bandwidthValues, 0, $topServices));
            
            // Add partial bandwidth for remaining services
            $remainingServices = count($services) - $topServices;
            if ($remainingServices > 0) {
                $requiredBandwidth += 0.3 * array_sum(array_slice($bandwidthValues, $topServices));
            }
            
            // Scale for user count (logarithmic)
            $userScaling = 1 + (log($numberOfUsers) / log(2)) * 0.5;
            $requiredBandwidth *= min($userScaling, 3); // Cap at 3x single-user
        }
        
        // Add 15% buffer (reduced due to more accurate modeling)
        $requiredBandwidth *= 1.25;
        
        // Round up to nearest 5 Mbps with minimum of 5Mbps
        $requiredBandwidth = max(5, ceil($requiredBandwidth / 5) * 5);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Internet Bandwidth Calculator</title>
    <link rel="stylesheet" href="calcustyle.css">
</head>
<body style="background-color: #f0f3fa;"> 
<nav class="navbar navbar-expand-lg navbar-dark" id="grad">
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
<div class="container">
        <div class="calculator">
         <div class="form-container">
            <div class="row">
                <div class="col-sm-3">
                    <a href="booking_customization.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                </div>
                <div class="col-sm-6"><h1 class="form-title">INTERNET BANDWIDTH CALCULATOR</h1></div>
            </div>
       
        <p>Select the websites/services you plan to use to estimate the required bandwidth, including live streaming options.</p>
        
        <form method="post">
            <!-- Number of Users -->
            <div class="users-input">
                <label for="users">Number of Users:</label>
                <input type="number" id="users" name="users" min="1" value="<?= $numberOfUsers ?>" required>
            </div>
            
            <!-- Social Media -->
            <div class="service-group">
                <h3>Social Media</h3>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="facebook" <?= in_array('facebook', $selectedServices) ? 'checked' : '' ?>> Facebook</label>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="twitter" <?= in_array('twitter', $selectedServices) ? 'checked' : '' ?>> Twitter</label>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="instagram" <?= in_array('instagram', $selectedServices) ? 'checked' : '' ?>> Instagram</label>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="linkedin" <?= in_array('linkedin', $selectedServices) ? 'checked' : '' ?>> LinkedIn</label>
                </div>
            </div>
            
            <!-- Video Streaming -->
            <div class="service-group">
                <h3>Video Streaming</h3>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="netflix" <?= in_array('netflix', $selectedServices) ? 'checked' : '' ?>> Netflix</label>
                    <select name="quality[netflix]" class="quality-select">
                        <option value="sd" <?= ($qualityLevels['netflix'] ?? '') === 'sd' ? 'selected' : '' ?>>SD (3 Mbps)</option>
                        <option value="hd" <?= ($qualityLevels['netflix'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (5 Mbps)</option>
                        <option value="uhd" <?= ($qualityLevels['netflix'] ?? '') === 'uhd' ? 'selected' : '' ?>>Ultra HD (25 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="youtube" <?= in_array('youtube', $selectedServices) ? 'checked' : '' ?>> YouTube</label>
                    <select name="quality[youtube]" class="quality-select">
                        <option value="sd" <?= ($qualityLevels['youtube'] ?? '') === 'sd' ? 'selected' : '' ?>>SD (1.5 Mbps)</option>
                        <option value="hd" <?= ($qualityLevels['youtube'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (2.5 Mbps)</option>
                        <option value="full_hd" <?= ($qualityLevels['youtube'] ?? '') === 'full_hd' ? 'selected' : '' ?>>Full HD (5 Mbps)</option>
                        <option value="ultra_hd" <?= ($qualityLevels['youtube'] ?? '') === 'ultra_hd' ? 'selected' : '' ?>>Ultra HD (20 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="tiktok" <?= in_array('tiktok', $selectedServices) ? 'checked' : '' ?>> TikTok</label>
                </div>
            </div>
            
            <!-- Live Streaming (Watching) -->
            <div class="service-group">
                <h3>Watching Live Streams</h3>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="twitch_watching" <?= in_array('twitch_watching', $selectedServices) ? 'checked' : '' ?>> Twitch</label>
                    <select name="quality[twitch_watching]" class="quality-select">
                        <option value="low" <?= ($qualityLevels['twitch_watching'] ?? '') === 'low' ? 'selected' : '' ?>>Low (3 Mbps)</option>
                        <option value="medium" <?= ($qualityLevels['twitch_watching'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium (4.5 Mbps)</option>
                        <option value="high" <?= ($qualityLevels['twitch_watching'] ?? '') === 'high' ? 'selected' : '' ?>>High (6 Mbps)</option>
                        <option value="source" <?= ($qualityLevels['twitch_watching'] ?? '') === 'source' ? 'selected' : '' ?>>Source (8 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="youtube_live_watching" <?= in_array('youtube_live_watching', $selectedServices) ? 'checked' : '' ?>> YouTube Live</label>
                    <select name="quality[youtube_live_watching]" class="quality-select">
                        <option value="sd" <?= ($qualityLevels['youtube_live_watching'] ?? '') === 'sd' ? 'selected' : '' ?>>SD (1.5 Mbps)</option>
                        <option value="hd" <?= ($qualityLevels['youtube_live_watching'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (2.5 Mbps)</option>
                        <option value="full_hd" <?= ($qualityLevels['youtube_live_watching'] ?? '') === 'full_hd' ? 'selected' : '' ?>>Full HD (5 Mbps)</option>
                        <option value="ultra_hd" <?= ($qualityLevels['youtube_live_watching'] ?? '') === 'ultra_hd' ? 'selected' : '' ?>>Ultra HD (20 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="facebook_live_watching" <?= in_array('facebook_live_watching', $selectedServices) ? 'checked' : '' ?>> Facebook Live</label>
                    <select name="quality[facebook_live_watching]" class="quality-select">
                        <option value="sd" <?= ($qualityLevels['facebook_live_watching'] ?? '') === 'sd' ? 'selected' : '' ?>>SD (1.5 Mbps)</option>
                        <option value="hd" <?= ($qualityLevels['facebook_live_watching'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (3 Mbps)</option>
                        <option value="full_hd" <?= ($qualityLevels['facebook_live_watching'] ?? '') === 'full_hd' ? 'selected' : '' ?>>Full HD (6 Mbps)</option>
                    </select>
                </div>
            </div>
            
            <!-- Live Streaming (Broadcasting) -->
            <div class="service-group">
                <h3>Broadcasting Live Streams</h3>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="twitch_broadcasting" <?= in_array('twitch_broadcasting', $selectedServices) ? 'checked' : '' ?>> Twitch Streaming</label>
                    <select name="quality[twitch_broadcasting]" class="quality-select">
                        <option value="720p" <?= ($qualityLevels['twitch_broadcasting'] ?? '') === '720p' ? 'selected' : '' ?>>720p (4.5 Mbps)</option>
                        <option value="1080p" <?= ($qualityLevels['twitch_broadcasting'] ?? '') === '1080p' ? 'selected' : '' ?>>1080p (6 Mbps)</option>
                        <option value="1440p" <?= ($qualityLevels['twitch_broadcasting'] ?? '') === '1440p' ? 'selected' : '' ?>>1440p (8 Mbps)</option>
                        <option value="4k" <?= ($qualityLevels['twitch_broadcasting'] ?? '') === '4k' ? 'selected' : '' ?>>4K (15 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="youtube_live_broadcasting" <?= in_array('youtube_live_broadcasting', $selectedServices) ? 'checked' : '' ?>> YouTube Live Streaming</label>
                    <select name="quality[youtube_live_broadcasting]" class="quality-select">
                        <option value="720p" <?= ($qualityLevels['youtube_live_broadcasting'] ?? '') === '720p' ? 'selected' : '' ?>>720p (4.5 Mbps)</option>
                        <option value="1080p" <?= ($qualityLevels['youtube_live_broadcasting'] ?? '') === '1080p' ? 'selected' : '' ?>>1080p (6 Mbps)</option>
                        <option value="1440p" <?= ($qualityLevels['youtube_live_broadcasting'] ?? '') === '1440p' ? 'selected' : '' ?>>1440p (8 Mbps)</option>
                        <option value="4k" <?= ($qualityLevels['youtube_live_broadcasting'] ?? '') === '4k' ? 'selected' : '' ?>>4K (15 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="facebook_live_broadcasting" <?= in_array('facebook_live_broadcasting', $selectedServices) ? 'checked' : '' ?>> Facebook Live Streaming</label>
                    <select name="quality[facebook_live_broadcasting]" class="quality-select">
                        <option value="720p" <?= ($qualityLevels['facebook_live_broadcasting'] ?? '') === '720p' ? 'selected' : '' ?>>720p (4 Mbps)</option>
                        <option value="1080p" <?= ($qualityLevels['facebook_live_broadcasting'] ?? '') === '1080p' ? 'selected' : '' ?>>1080p (6 Mbps)</option>
                        <option value="4k" <?= ($qualityLevels['facebook_live_broadcasting'] ?? '') === '4k' ? 'selected' : '' ?>>4K (12 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="tiktok_live_broadcasting" <?= in_array('tiktok_live_broadcasting', $selectedServices) ? 'checked' : '' ?>> TikTok Live Streaming</label>
                    <select name="quality[tiktok_live_broadcasting]" class="quality-select">
                        <option value="standard" <?= ($qualityLevels['tiktok_live_broadcasting'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard (3 Mbps)</option>
                        <option value="hd" <?= ($qualityLevels['tiktok_live_broadcasting'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (5 Mbps)</option>
                    </select>
                </div>
            </div>
            
            <!-- Video Conferencing -->
            <div class="service-group">
                <h3>Video Conferencing</h3>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="zoom" <?= in_array('zoom', $selectedServices) ? 'checked' : '' ?>> Zoom</label>
                    <select name="quality[zoom]" class="quality-select">
                        <option value="hd" <?= ($qualityLevels['zoom'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (2.5 Mbps)</option>
                        <option value="full_hd" <?= ($qualityLevels['zoom'] ?? '') === 'full_hd' ? 'selected' : '' ?>>Full HD (3.5 Mbps)</option>
                        <option value="group" <?= ($qualityLevels['zoom'] ?? '') === 'group' ? 'selected' : '' ?>>Group Call (5 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="teams" <?= in_array('teams', $selectedServices) ? 'checked' : '' ?>> Microsoft Teams</label>
                    <select name="quality[teams]" class="quality-select">
                        <option value="hd" <?= ($qualityLevels['teams'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (2 Mbps)</option>
                        <option value="full_hd" <?= ($qualityLevels['teams'] ?? '') === 'full_hd' ? 'selected' : '' ?>>Full HD (3 Mbps)</option>
                        <option value="group" <?= ($qualityLevels['teams'] ?? '') === 'group' ? 'selected' : '' ?>>Group Call (4 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="google_meet" <?= in_array('google_meet', $selectedServices) ? 'checked' : '' ?>> Google Meet</label>
                    <select name="quality[google_meet]" class="quality-select">
                        <option value="hd" <?= ($qualityLevels['google_meet'] ?? '') === 'hd' ? 'selected' : '' ?>>HD (2.5 Mbps)</option>
                        <option value="full_hd" <?= ($qualityLevels['google_meet'] ?? '') === 'full_hd' ? 'selected' : '' ?>>Full HD (3.5 Mbps)</option>
                    </select>
                </div>
            </div>
            
            <!-- Other Services -->
            <div class="service-group">
                <h3>Other Services</h3>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="spotify" <?= in_array('spotify', $selectedServices) ? 'checked' : '' ?>> Spotify/Music Streaming</label>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="gaming" <?= in_array('gaming', $selectedServices) ? 'checked' : '' ?>> Online Gaming</label>
                    <select name="quality[gaming]" class="quality-select">
                        <option value="casual" <?= ($qualityLevels['gaming'] ?? '') === 'casual' ? 'selected' : '' ?>>Casual (3 Mbps)</option>
                        <option value="competitive" <?= ($qualityLevels['gaming'] ?? '') === 'competitive' ? 'selected' : '' ?>>Competitive (5 Mbps)</option>
                        <option value="streaming" <?= ($qualityLevels['gaming'] ?? '') === 'streaming' ? 'selected' : '' ?>>Streaming (8 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="downloads" <?= in_array('downloads', $selectedServices) ? 'checked' : '' ?>> Large File Downloads</label>
                    <select name="quality[downloads]" class="quality-select">
                        <option value="small" <?= ($qualityLevels['downloads'] ?? '') === 'small' ? 'selected' : '' ?>>Occasional (5 Mbps)</option>
                        <option value="medium" <?= ($qualityLevels['downloads'] ?? '') === 'medium' ? 'selected' : '' ?>>Regular (10 Mbps)</option>
                        <option value="large" <?= ($qualityLevels['downloads'] ?? '') === 'large' ? 'selected' : '' ?>>Frequent (20 Mbps)</option>
                        <option value="frequent" <?= ($qualityLevels['downloads'] ?? '') === 'frequent' ? 'selected' : '' ?>>Heavy (30 Mbps)</option>
                    </select>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="cloud_storage" <?= in_array('cloud_storage', $selectedServices) ? 'checked' : '' ?>> Cloud Storage</label>
                </div>
                <div class="service-option">
                    <label><input type="checkbox" name="services[]" value="vpn" <?= in_array('vpn', $selectedServices) ? 'checked' : '' ?>> VPN Usage</label>
                </div>
            </div>
            
            <button type="submit" name="calculate">Calculate Required Bandwidth</button>
        </form>
        
        <?php if ($showResult): ?>
            <div class="result">
                <h3>Bandwidth Calculation Results</h3>
                <p>For <span class="users-value"><?= $numberOfUsers ?> user(s)</span>, the required bandwidth is:</p>
                <p class="bandwidth-value"><?= round($requiredBandwidth, 1) ?> Mbps</p>
                
                <form method="post" action="booking_customization.php">
                    <input type="hidden" name="speed" value="<?= round($requiredBandwidth, 1) ?>">
                    <input type="hidden" name="users" value="<?= $numberOfUsers ?>">
                    <div class="button-group">
                        <button type="button" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">Recalculate</button>
                        <button type="submit" class="return-button">Return to Booking Customization</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

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
</body>
</html>