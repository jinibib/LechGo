<?php

/**
 * Complete Profile View
 * Collect additional user information based on role
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated
if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" class="no-underline">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
        </div>
    </header>

    <main>
        <!-- Complete Profile Form -->
        <div class="auth-container">
            <div class="auth-header">
                <h2>Complete Your Profile</h2>
                <p>Just a few more details to get started</p>
            </div>

            <!-- Display Flash Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form id="completeProfileForm" class="auth-form" method="POST" action="/LechGo_Final/public/auth/complete-profile">
                <?php if ($user['role'] === 'lechonero'): ?>
                    <!-- Lechonero Fields -->
                    <div class="form-group">
                        <label for="business_name">Business Name</label>
                        <input
                            type="text"
                            id="business_name"
                            name="business_name"
                            placeholder="Enter your business name"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="specialty">Specialty</label>
                        <select id="specialty" name="specialty" required>
                            <option value="">Select your specialty</option>
                            <option value="Traditional Lechon">Traditional Lechon</option>
                            <option value="Modern Lechon">Modern Lechon</option>
                            <option value="Spicy Lechon">Spicy Lechon</option>
                            <option value="Herb-infused Lechon">Herb-infused Lechon</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="form-error"></div>
                    </div>

                <?php elseif ($user['role'] === 'supplier'): ?>
                    <!-- Supplier Fields -->
                    <div class="form-group">
                        <label for="farm_name">Store Name</label>
                        <input
                            type="text"
                            id="farm_name"
                            name="farm_name"
                            placeholder="Enter your store name"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label>City</label>
                        <p class="display-field">Davao City</p>
                        <input type="hidden" id="city" name="city" value="Davao City" required>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="municipality">Municipality/District</label>
                        <select id="municipality" name="municipality" required>
                            <option value="">Select municipality/district</option>
                        </select>
                        <div class  ="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <select id="barangay" name="barangay" required>
                            <option value="">Select barangay</option>
                        </select>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="street">Street</label>
                        <select id="street" name="street" required>
                            <option value="">Select street</option>
                        </select>
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input
                            type="tel"
                            id="contact_number"
                            name="contact_number"
                            placeholder="Enter contact number"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                <?php elseif ($user['role'] === 'pig_caretaker'): ?>
                    <!-- Pig Caretaker Fields -->
                    <div class="form-group">
                        <label for="farm_name">Farm/Piggery Name</label>
                        <input
                            type="text"
                            id="farm_name"
                            name="farm_name"
                            placeholder="Enter your farm/piggery name"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="location">Location/Address</label>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            placeholder="Enter farm location"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input
                            type="tel"
                            id="contact_number"
                            name="contact_number"
                            placeholder="Enter contact number"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                <?php elseif ($user['role'] === 'livestock_owner'): ?>
                    <!-- Livestock Owner Fields -->
                    <div class="form-group">
                        <label for="farm_name">Farm Name</label>
                        <input
                            type="text"
                            id="farm_name"
                            name="farm_name"
                            placeholder="Enter your farm name"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="location">Location/Address</label>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            placeholder="Enter farm location"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input
                            type="tel"
                            id="contact_number"
                            name="contact_number"
                            placeholder="Enter contact number"
                            required
                        >
                        <div class="form-error"></div>
                    </div>

                <?php else: ?>
                    <!-- No additional fields needed -->
                    <p>You don't need to provide additional information. Click submit to continue.</p>
                <?php endif; ?>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-block">
                    Complete Profile
                </button>
            </form>

            <!-- Footer Links -->
            <div class="form-footer">
                <p><a href="/LechGo_Final/public/logout">Logout</a></p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: none; padding: var(--spacing-md); text-align: center;">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script src="/LechGo_Final/public/script.js"></script>

    <script>
        // Only load location scripts for supplier role
        <?php if ($user['role'] === 'supplier'): ?>

        const BASE_API = '/LechGo_Final/public/api/locations';

        // Load municipalities on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMunicipalities();

            // Load barangays when municipality changes
            document.getElementById('municipality').addEventListener('change', function() {
                const municipality = this.value;
                if (municipality) {
                    loadBarangays(municipality);
                } else {
                    clearSelect('barangay');
                    clearSelect('street');
                }
            });

            // Load streets when barangay changes
            document.getElementById('barangay').addEventListener('change', function() {
                const municipality = document.getElementById('municipality').value;
                const barangay = this.value;
                if (municipality && barangay) {
                    loadStreets(municipality, barangay);
                } else {
                    clearSelect('street');
                }
            });
        });

        /**
         * Load municipalities from database
         */
        function loadMunicipalities() {
            fetch(`${BASE_API}?action=municipalities`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('municipality');
                        select.innerHTML = '<option value="">Select municipality/district</option>';
                        data.data.forEach(municipality => {
                            const option = document.createElement('option');
                            option.value = municipality;
                            option.textContent = municipality;
                            select.appendChild(option);
                        });
                    } else {
                        console.error('Failed to load municipalities:', data.message);
                    }
                })
                .catch(error => console.error('Error loading municipalities:', error));
        }

        /**
         * Load barangays for selected municipality
         */
        function loadBarangays(municipality) {
            fetch(`${BASE_API}?action=barangays&municipality=${encodeURIComponent(municipality)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('barangay');
                        select.innerHTML = '<option value="">Select barangay</option>';
                        data.data.forEach(barangay => {
                            const option = document.createElement('option');
                            option.value = barangay;
                            option.textContent = barangay;
                            select.appendChild(option);
                        });
                        clearSelect('street');
                    } else {
                        console.error('Failed to load barangays:', data.message);
                    }
                })
                .catch(error => console.error('Error loading barangays:', error));
        }

        /**
         * Load streets for selected municipality and barangay
         */
        function loadStreets(municipality, barangay) {
            fetch(`${BASE_API}?action=streets&municipality=${encodeURIComponent(municipality)}&barangay=${encodeURIComponent(barangay)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('street');
                        select.innerHTML = '<option value="">Select street</option>';
                        data.data.forEach(street => {
                            const option = document.createElement('option');
                            option.value = street;
                            option.textContent = street;
                            select.appendChild(option);
                        });
                    } else {
                        console.error('Failed to load streets:', data.message);
                    }
                })
                .catch(error => console.error('Error loading streets:', error));
        }

        /**
         * Clear a select dropdown (reset to default option)
         */
        function clearSelect(selectId) {
            const select = document.getElementById(selectId);
            if (selectId === 'barangay') {
                select.innerHTML = '<option value="">Select barangay</option>';
            } else if (selectId === 'street') {
                select.innerHTML = '<option value="">Select street</option>';
            }
        }

        <?php endif; ?>
    </script>
</body>
</html>