<?php
/**
 * Manage Caretakers View
 * Allow livestock owners to assign/remove pig caretakers
 */

$currentPage = 'manage-caretakers';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated or not livestock owner
if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'livestock_owner') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

require_once APP_PATH . '/models/LivestockOwner.php';

$owner = new LivestockOwner($GLOBALS['conn']);
if (!$owner->findByUserId($user['id'])) {
    $_SESSION['error'] = 'Please complete your profile first.';
    header('Location: /LechGo_Final/public/complete-profile');
    exit;
}

$unassignedCaretakers = $owner->getUnassignedCaretakers();
$assignedCaretakers = $owner->getAssignedCaretakers();

// Display success/error messages
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Caretakers - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .caretaker-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .caretaker-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-color: #dee2e6;
        }

        .caretaker-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .caretaker-details h5 {
            margin-bottom: 0.25rem;
            font-weight: 600;
            color: #212529;
        }

        .caretaker-details p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
            margin-bottom: 1.5rem;
        }

        .section-header h3 {
            margin: 0;
            color: #212529;
            font-weight: 700;
        }

        .badge-count {
            background-color: #007bff;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }

        .farm-info {
            background: linear-gradient(135deg, #c9b4b4ff 0%, #fcdbdbff 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .farm-info h4 {
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .farm-info p {
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .no-caretakers-alert {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .assigned-success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <div class="container my-5">
        <!-- Page Title -->
        <div class="mb-4">
            <h1>Manage Pig Caretakers</h1>
            <p class="text-muted">Assign and manage pig caretakers for your farm</p>
        </div>

        <!-- Farm Information -->
        <div class="farm-info">
            <h4>📍 Your Farm Information</h4>
            <p><strong>Farm Name:</strong> <?= htmlspecialchars($owner->farm_name); ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($owner->location); ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($owner->contact_number); ?></p>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="assigned-success" role="alert">
                ✓ <?= $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                ⚠ <?= $error; ?>
            </div>
        <?php endif; ?>

        <!-- Unassigned Caretakers Section -->
        <div class="section-header">
            <h3>Available Caretakers</h3>
            <span class="badge-count"><?= count($unassignedCaretakers); ?></span>
        </div>

        <?php if (count($unassignedCaretakers) > 0): ?>
            <div class="mb-4">
                <?php foreach ($unassignedCaretakers as $caretaker): ?>
                    <div class="caretaker-card">
                        <div class="caretaker-info">
                            <div class="caretaker-details flex-grow-1">
                                <h5><?= htmlspecialchars($caretaker['name']); ?></h5>
                                <p>
                                    <strong>Farm/Piggery:</strong> <?= htmlspecialchars($caretaker['farm_name']); ?>
                                </p>
                                <p>
                                    <strong>Location:</strong> <?= htmlspecialchars($caretaker['location']); ?>
                                </p>
                                <p>
                                    <strong>Email:</strong> <?= htmlspecialchars($caretaker['email']); ?>
                                </p>
                                <p>
                                    <strong>Contact:</strong> <?= htmlspecialchars($caretaker['contact_number']); ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="/LechGo_Final/public/livestock-owner/assign-caretaker" style="display: inline;">
                                    <input type="hidden" name="caretaker_id" value="<?= $caretaker['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-action">
                                        Assign
                                    </button>
                                </form>
                                <form method="POST" action="/LechGo_Final/public/livestock-owner/reject-caretaker" style="display: inline;">
                                    <input type="hidden" name="caretaker_id" value="<?= $caretaker['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-action" 
                                            onclick="return confirm('Are you sure you want to reject this caretaker request?');">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;">✓</p>
                <p>All registered caretakers have been assigned!</p>
                <p style="font-size: 0.9rem;">If you need more caretakers, ask them to register on the platform.</p>
            </div>
        <?php endif; ?>


        <!-- Assigned Caretakers Section -->
        <div class="section-header">
            <h3>Assigned Caretakers</h3>
            <span class="badge-count"><?= count($assignedCaretakers); ?></span>
        </div>

        <?php if (count($assignedCaretakers) > 0): ?>
            <div class="mb-4">
                <?php foreach ($assignedCaretakers as $caretaker): ?>
                    <div class="caretaker-card">
                        <div class="caretaker-info">
                            <div class="caretaker-details flex-grow-1">
                                <h5><?= htmlspecialchars($caretaker['name']); ?></h5>
                                <p>
                                    <strong>Farm/Piggery:</strong> <?= htmlspecialchars($caretaker['farm_name']); ?>
                                </p>
                                <p>
                                    <strong>Location:</strong> <?= htmlspecialchars($caretaker['location']); ?>
                                </p>
                                <p>
                                    <strong>Email:</strong> <?= htmlspecialchars($caretaker['email']); ?>
                                </p>
                                <p>
                                    <strong>Contact:</strong> <?= htmlspecialchars($caretaker['contact_number']); ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="/LechGo_Final/public/livestock-owner/caretaker-details/<?= $caretaker['id']; ?>" 
                                   class="btn btn-primary btn-action">
                                    View Details
                                </a>
                                <form method="POST" action="/LechGo_Final/public/livestock-owner/remove-caretaker" style="display: inline;">
                                    <input type="hidden" name="caretaker_id" value="<?= $caretaker['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-action" 
                                            onclick="return confirm('Are you sure you want to remove this caretaker?');">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;"></p>
                <p>You haven't assigned any caretakers yet.</p>
                <p style="font-size: 0.9rem;">Assign caretakers below to manage your farm's operations.</p>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div style="background-color: #e7f3ff; border-left: 4px solid #007bff; padding: 1rem; border-radius: 4px; margin-top: 2rem;">
            <h5 style="margin-bottom: 0.5rem; color: #004085;">ℹ How It Works</h5>
            <ul style="margin-bottom: 0; color: #004085; font-size: 0.95rem;">
                <li><strong>Assign Caretakers:</strong> Choose available caretakers from the list to manage your farm's pigs and feed inventory.</li>
                <li><strong>View Details:</strong> Click "View Details" to see assigned caretaker schedules and reports.</li>
                <li><strong>Feed Inventory:</strong> Once assigned, caretakers can import feed from your orders into their inventory.</li>
                <li><strong>Remove:</strong> Use the "Remove" button to unassign a caretaker from your farm.</li>
            </ul>
        </div>
            </div>
    </main>

    </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
