<?php

/**
 * Locations Display View
 * Shows all Davao City locations from the database
 */

$sessionMiddleware = new Session();

// Check if user is authenticated
if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get all locations from database
$locationsQuery = "SELECT location_id, street, barangay, municipality, city 
                   FROM locations 
                   ORDER BY municipality ASC, barangay ASC, street ASC";
$result = $conn->query($locationsQuery);
$locations = $result->fetch_all(MYSQLI_ASSOC);

// Group locations by municipality
$locationsByMunicipality = [];
foreach ($locations as $location) {
    $municipality = $location['municipality'];
    if (!isset($locationsByMunicipality[$municipality])) {
        $locationsByMunicipality[$municipality] = [];
    }
    $locationsByMunicipality[$municipality][] = $location;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Davao City Locations - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .locations-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }
        
        .municipality-section {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .municipality-title {
            color: var(--primary-red);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: var(--spacing-sm);
        }
        
        .barangay-group {
            margin-bottom: var(--spacing-md);
            padding-left: var(--spacing-md);
        }
        
        .barangay-name {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            font-size: 1.05rem;
        }
        
        .street-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .street-item {
            padding: 0.5rem 0 0.5rem var(--spacing-md);
            color: var(--text-gray);
            border-left: 3px solid var(--secondary-red);
            margin-bottom: 0.3rem;
        }
        
        .stats {
            background: var(--pale-red);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
        }
        
        .stat-card {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
        }
        
        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/dashboard" style="text-decoration: none;">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
            <button class="menu-toggle">☰</button>
            <nav>
                <a href="/LechGo_Final/public/dashboard">Dashboard</a>
                <a href="/LechGo_Final/public/locations">Locations</a>
                <a href="/LechGo_Final/public/logout">Logout</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="locations-container">
            <!-- Page Header -->
            <div class="auth-header">
                <h1>Davao City Locations Database</h1>
                <p>Complete list of all municipalities, barangays, and streets</p>
            </div>

            <!-- Statistics -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($locationsByMunicipality); ?></div>
                    <div class="stat-label">Municipalities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_unique(array_map(function($l) { return $l['barangay']; }, $locations))); ?></div>
                    <div class="stat-label">Barangays</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($locations); ?></div>
                    <div class="stat-label">Streets</div>
                </div>
            </div>

            <!-- Locations by Municipality -->
            <?php foreach ($locationsByMunicipality as $municipality => $municipalityLocations): ?>
                <div class="municipality-section">
                    <div class="municipality-title"><?php echo htmlspecialchars($municipality); ?></div>
                    
                    <?php 
                    // Group by barangay
                    $barangayGroups = [];
                    foreach ($municipalityLocations as $location) {
                        $barangay = $location['barangay'];
                        if (!isset($barangayGroups[$barangay])) {
                            $barangayGroups[$barangay] = [];
                        }
                        $barangayGroups[$barangay][] = $location;
                    }
                    ?>
                    
                    <?php foreach ($barangayGroups as $barangay => $barangayLocations): ?>
                        <div class="barangay-group">
                            <div class="barangay-name">📍 <?php echo htmlspecialchars($barangay); ?></div>
                            <ul class="street-list">
                                <?php foreach ($barangayLocations as $location): ?>
                                    <li class="street-item">
                                        🏠 <?php echo htmlspecialchars($location['street']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- No Data Message -->
            <?php if (empty($locations)): ?>
                <div class="alert alert-warning">
                    No locations found in database. Please insert location data first.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>
