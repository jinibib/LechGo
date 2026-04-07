<?php
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

// Get livestock owner ID
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /');
    exit;
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Livestock owner profile not found';
    header('Location: /');
    exit;
}

// Get caretaker's pig inventory
$query = "SELECT pd.*, pc.full_name, pc.farm_name, pc.location, pg.cage_number, pg.current_pig_count
          FROM pig_details pd
          JOIN pig_cages pg ON pd.cage_id = pg.id
          JOIN pig_caretakers pc ON pg.caretaker_id = pc.id
          WHERE pc.livestock_owner_id = ?
          ORDER BY pc.farm_name, pg.cage_number, pd.id";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /');
    exit;
}
$stmt->bind_param('i', $owner['id']);
$stmt->execute();
$result = $stmt->get_result();
$pigs = $result->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

// Group by caretaker
$pigs_by_caretaker = [];
foreach ($pigs as $pig) {
    $caretaker_key = $pig['farm_name'];
    if (!isset($pigs_by_caretaker[$caretaker_key])) {
        $pigs_by_caretaker[$caretaker_key] = [
            'name' => $pig['full_name'] ?? $pig['farm_name'],
            'location' => $pig['location'],
            'pigs' => []
        ];
    }
    $pigs_by_caretaker[$caretaker_key]['pigs'][] = $pig;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caretaker Pig Inventory - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
<div class="inventory-container">
    <div class="inventory-header">
        <h1>🐷 Caretaker Pig Status & Availability</h1>
        <p>View the pigs your caretakers currently have and their health status</p>
    </div>

    <?php if (empty($pigs_by_caretaker)): ?>
        <div class="no-data">
            <p>📭 No pig inventory data available</p>
            <p>Your caretakers haven't recorded any pigs yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($pigs_by_caretaker as $caretaker_key => $caretaker_data): ?>
            <div class="caretaker-section">
                <h2><?php echo htmlspecialchars($caretaker_data['name']); ?></h2>
                <p class="location-info">📍 <?php echo htmlspecialchars($caretaker_data['location']); ?></p>
                
                <table class="pig-inventory-table">
                    <thead>
                        <tr>
                            <th>Cage</th>
                            <th>Pig Tag ID</th>
                            <th>Breed</th>
                            <th>Age (months)</th>
                            <th>Weight (kg)</th>
                            <th>Health Status</th>
                            <th>Date Added</th>
                            <th>Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caretaker_data['pigs'] as $pig): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pig['cage_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pig['pig_tag_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pig['breed'] ?? 'N/A'); ?></td>
                                <td><?php echo $pig['age_months'] ?? 'N/A'; ?></td>
                                <td><?php echo $pig['weight_kg'] ?? 'N/A'; ?> kg</td>
                                <td>
                                    <span class="health-badge health-<?php echo strtolower($pig['health_status']); ?>">
                                        <?php echo ucfirst($pig['health_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($pig['date_added'])); ?></td>
                                <td>
                                    <span class="availability-badge availability-<?php echo strtolower($pig['status']); ?>">
                                        <?php echo ucfirst($pig['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
