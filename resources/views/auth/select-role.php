<?php

/**
 * Role Selection View
 * Users choose their role after email/OTP verification
 */

$session = new Session();
$user_id = $_SESSION['verification_user_id'] ?? null;
$email = $_SESSION['verification_email'] ?? null;

if (!$user_id || !$email) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Role - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .role-selection-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        .role-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .role-header h1 {
            color: var(--dark-gray);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .role-header p {
            color: var(--text-gray);
            font-size: 1.1rem;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .role-card {
            cursor: pointer;
            border: 2px solid var(--light-red);
            border-radius: var(--radius-lg);
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            background-color: var(--white);
            text-decoration: none;
            color: var(--dark-gray);
        }

        .role-card:hover {
            border-color: var(--primary-red);
            background-color: var(--light-red);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            color: var(--primary-red);
        }

        .role-card.selected {
            border-color: var(--primary-red);
            background-color: var(--primary-red);
            color: var(--white);
        }

        .role-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .role-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .role-description {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn-select {
            padding: 12px 40px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-continue {
            background-color: var(--primary-red);
            color: var(--white);
            flex: 1;
        }

        .btn-continue:hover:not(:disabled) {
            background-color: var(--dark-red);
            transform: scale(1.02);
        }

        .btn-continue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-back {
            background-color: transparent;
            color: var(--primary-red);
            border: 2px solid var(--primary-red);
        }

        .btn-back:hover {
            background-color: var(--light-red);
        }

        .info-box {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--primary-red);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-top: 30px;
            color: var(--dark-gray);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" style="text-decoration: none;">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
        </div>
    </header>

    <main>
        <div class="role-selection-container">
            <!-- Header -->
            <div class="role-header">
                <h1>Choose Your Role</h1>
                <p>Select how you want to use LechGO</p>
            </div>

            <!-- Display Flash Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger show" style="margin-bottom: 30px;">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Role Selection Form -->
            <form id="roleForm" method="POST" action="/LechGo_Final/public/auth/select-role">
                <!-- Roles Grid -->
                <div class="roles-grid">
                    <!-- Customer -->
                    <div class="role-card" onclick="selectRole('customer', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Customer</div>
                        <div class="role-description">Order delicious lechon</div>
                    </div>

                    <!-- Lechonero -->
                    <div class="role-card" onclick="selectRole('lechonero', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Lechonero</div>
                        <div class="role-description">Sell your lechon</div>
                    </div>

                    <!-- Livestock Owner -->
                    <div class="role-card" onclick="selectRole('livestock_owner', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Livestock Owner</div>
                        <div class="role-description">Manage your farm</div>
                    </div>

                    <!-- Feed Supplier -->
                    <div class="role-card" onclick="selectRole('supplier', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Feed Supplier</div>
                        <div class="role-description">Supply feed products</div>
                    </div>

                    <!-- Pig Caretaker -->
                    <div class="role-card" onclick="selectRole('pig_caretaker', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Pig Caretaker</div>
                        <div class="role-description">Care for pigs on farm</div>
                    </div>

                    <!-- Logistics -->
                    <div class="role-card" onclick="selectRole('logistics', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Logistics Driver</div>
                        <div class="role-description">Deliver orders</div>
                    </div>

                    <!-- Feed Distributor -->
                    <div class="role-card" onclick="selectRole('feed_distributor', this)">
                        <span class="role-icon"></span>
                        <div class="role-name">Feed Distributor</div>
                        <div class="role-description">Distribute feed products</div>
                    </div>
                </div>

                <!-- Hidden input for selected role -->
                <input type="hidden" id="selectedRole" name="role" value="">

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn-select btn-back" onclick="history.back()">
                        Back
                    </button>
                    <button type="submit" class="btn-select btn-continue" id="continueBtn" disabled>
                        Continue with Selected Role
                    </button>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <strong>Note:</strong> You can change your role later in your account settings. Choose the role that best describes how you want to use LechGO.
                </div>
            </form>
        </div>
    </main>

    <script>
        // Track selected role
        let selectedRole = null;

        function selectRole(role, element) {
            // Remove previous selection
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selection to clicked card
            element.classList.add('selected');
            selectedRole = role;

            // Update hidden input
            document.getElementById('selectedRole').value = role;

            // Enable continue button
            document.getElementById('continueBtn').disabled = false;
        }

        // Handle form submission
        document.getElementById('roleForm').addEventListener('submit', function(e) {
            if (!selectedRole) {
                e.preventDefault();
                alert('Please select a role to continue');
            }
        });
    </script>
</body>
</html>
