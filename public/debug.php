<?php
/**
 * Debug Helper Page
 * Helps identify issues with pig_caretaker setup
 */

// Start session
session_start();

// Load database connection
require_once dirname(__FILE__) . '/../../config/db.php';

// Check if we have authentication
$user = $_SESSION['user'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - LechGO</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #dc3545;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 20px;
            border-left: 4px solid #dc3545;
            padding-left: 10px;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-weight: bold;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 5px;
            border-radius: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #dc3545;
            color: white;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐛 LechGO Pig Caretaker Debug Dashboard</h1>

        <h2>1. Database Connection Status</h2>
        <?php
            if ($conn && !$conn->connect_error) {
                echo '<div class="status success">✓ Database connected successfully</div>';
            } else {
                echo '<div class="status error">✗ Database connection failed: ' . $conn->connect_error . '</div>';
            }
        ?>

        <h2>2. Required Tables Check</h2>
        <?php
            $tables_to_check = [
                'pig_caretakers',
                'pig_cages',
                'pig_details',
                'feed_inventory',
                'feeding_schedule'
            ];

            $tables_result = $conn->query("SHOW TABLES FROM lechgo_db");
            $existing_tables = [];
            while ($row = $tables_result->fetch_row()) {
                $existing_tables[] = $row[0];
            }

            echo '<table>';
            echo '<tr><th>Table Name</th><th>Status</th></tr>';
            foreach ($tables_to_check as $table) {
                $exists = in_array($table, $existing_tables);
                $status = $exists ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>';
                echo "<tr><td>$table</td><td>$status</td></tr>";
            }
            echo '</table>';

            if (count(array_diff($tables_to_check, $existing_tables)) > 0) {
                echo '<div class="status warning">⚠ Some tables are missing. You need to run the schema SQL file.</div>';
                echo '<p>Please run this SQL in your database:</p>';
                echo '<code>schema-pig-caretaker.sql</code>';
            } else {
                echo '<div class="status success">✓ All required tables exist</div>';
            }
        ?>

        <h2>3. User Authentication Status</h2>
        <?php
            if ($user) {
                echo '<div class="status info">Current User: ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['role']) . ')</div>';
                
                if ($user['role'] === 'pig_caretaker') {
                    echo '<div class="status success">✓ User has pig_caretaker role</div>';
                    
                    // Check if pig caretaker has been set up
                    $query = "SELECT * FROM pig_caretakers WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $caretaker = $result->fetch_assoc();
                        echo '<div class="status success">✓ Pig caretaker profile found</div>';
                        echo '<p><strong>Farm Name:</strong> ' . htmlspecialchars($caretaker['farm_name']) . '</p>';
                        echo '<p><strong>Location:</strong> ' . htmlspecialchars($caretaker['location']) . '</p>';
                    } else {
                        echo '<div class="status error">✗ No pig caretaker profile found</div>';
                        echo '<p>User needs to complete profile setup at: <code>/LechGo_Final/public/complete-profile</code></p>';
                    }
                    $stmt->close();
                } else {
                    echo '<div class="status warning">⚠ Current user is not a pig caretaker. Role: ' . htmlspecialchars($user['role']) . '</div>';
                }
            } else {
                echo '<div class="status error">✗ Not authenticated</div>';
                echo '<p>Please log in at: <code>/LechGo_Final/public/login</code></p>';
            }
        ?>

        <h2>4. Quick Links</h2>
        <p>
            <a href="/LechGo_Final/public/dashboard" class="back-link">← Back to Dashboard</a>
            <a href="/LechGo_Final/public/login" class="back-link">Login</a>
            <a href="/LechGo_Final/public/pig-caretaker/feed-inventory" class="back-link">Feed Inventory</a>
            <a href="/LechGo_Final/public/pig-caretaker/pigs" class="back-link">Pig Inventory</a>
            <a href="/LechGo_Final/public/pig-caretaker/feeding-schedule" class="back-link">Feeding Schedule</a>
            <a href="/LechGo_Final/public/pig-caretaker/farm-profile" class="back-link">Farm Profile</a>
        </p>

        <h2>5. How to Fix Common Issues</h2>
        <div class="info">
            <strong>Issue: "Tables don't exist"</strong>
            <p>Solution: Open phpMyAdmin, select database <code>lechgo_db</code>, go to SQL tab, and copy-paste the contents of <code>schema-pig-caretaker.sql</code> file, then click Execute.</p>
        </div>
        <div class="info">
            <strong>Issue: "Not authenticated"</strong>
            <p>Solution: Make sure you're logged in as a pig_caretaker user. Register a new account with role "Pig Caretaker" and complete your profile.</p>
        </div>
        <div class="info">
            <strong>Issue: "Profile not found"</strong>
            <p>Solution: Go to <code>/LechGo_Final/public/complete-profile</code> and fill in your farm details.</p>
        </div>
    </div>
</body>
</html>
