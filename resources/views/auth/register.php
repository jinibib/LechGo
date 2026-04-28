<?php

/**
 * Registration Form View
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <!-- Registration Form -->
        <div class="auth-container-split">
            <!-- Left Side - Red Background with Logo -->
            <div class="auth-welcome-side">
                <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="auth-logo">
                <h2>Join LechGO!</h2>
                <p>Create your account and start your journey with LechGO.</p>
                <div class="auth-benefits">
                </div>
            </div>

            <!-- Right Side - White Background with Form -->
            <div class="auth-form-side">
                <div class="auth-header">
                    <h2>Create Account</h2>
                    <p>Join LechGO today</p>
                </div>

                <!-- Display Flash Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form id="registerForm" class="auth-form" method="POST" action="/LechGo_Final/public/auth/register">
                    <div class="auth-form-grid">
                        <!-- Left Column -->
                        <div class="auth-form-column">
                            <!-- Full Name Field -->
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    placeholder="Enter your full name" 
                                    required
                                    autocomplete="name"
                                >
                                <div class="form-error"></div>
                            </div>

                            <!-- Phone Field -->
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    placeholder="Enter your phone number" 
                                    required
                                    autocomplete="tel"
                                >
                                <div class="form-error"></div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="auth-form-column">
                            <!-- Email Field -->
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    placeholder="Enter your email" 
                                    required
                                    autocomplete="email"
                                >
                                <div class="form-error"></div>
                            </div>

                            <!-- Password Field -->
                            <div class="form-group password-toggle">
                                <label for="password">Password</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Create a strong password" 
                                    required
                                    autocomplete="new-password"
                                >
                                <button type="button" class="password-toggle-btn" title="Toggle password visibility">
                                    <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <div id="passwordStrength" class="password-strength"></div>
                                <div class="form-error"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Password Requirements Info -->
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>At least 8 characters</li>
                            <li>At least 1 uppercase letter</li>
                            <li>At least 1 number</li>
                        </ul>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="form-group checkbox-row">
                        <div class="checkbox-inline">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                I agree to the <a href="#" onclick="openTermsModal(event)">Terms & Conditions</a>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-block">
                        Create Account
                    </button>
                </form>

                <!-- Footer Links -->
                <div class="form-footer">
                    <p>Already have an account? <a href="/LechGo_Final/public/login">Log in</a></p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h2>Terms and Conditions</h2>
                <button class="modal-close" onclick="closeTermsModal()">&times;</button>
            </div>
            <div class="modal-body" style="text-align: left; padding: 20px;">
                <h3>Data Privacy and User Agreement</h3>
                <p style="margin-bottom: 15px;">
                    Welcome to LechGO! By creating an account and using our platform, you agree to the following terms and conditions regarding the collection, use, and protection of your personal information.
                </p>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">1. Information We Collect</h4>
                <p style="margin-bottom: 10px;">We collect the following personal information:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Full name</li>
                    <li>Email address</li>
                    <li>Phone number</li>
                    <li>Business information (for suppliers, livestock owners, and caretakers)</li>
                    <li>Transaction and order history</li>
                    <li>Location data for delivery purposes</li>
                </ul>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">2. How We Use Your Information</h4>
                <p style="margin-bottom: 10px;">Your personal data will be used for:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Account creation and authentication</li>
                    <li>Processing orders and payments</li>
                    <li>Facilitating communication between users</li>
                    <li>Sending notifications about orders and deliveries</li>
                    <li>Improving our services and user experience</li>
                    <li>Compliance with legal obligations</li>
                </ul>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">3. Data Protection</h4>
                <p style="margin-bottom: 15px;">
                    We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction. Your password is encrypted, and sensitive data is transmitted securely.
                </p>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">4. Data Sharing</h4>
                <p style="margin-bottom: 10px;">We may share your information with:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Other users as necessary for transactions (e.g., suppliers, caretakers)</li>
                    <li>Payment processors for secure payment handling</li>
                    <li>Delivery services for order fulfillment</li>
                    <li>Legal authorities when required by law</li>
                </ul>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">5. Your Rights</h4>
                <p style="margin-bottom: 10px;">You have the right to:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Access your personal data</li>
                    <li>Request correction of inaccurate information</li>
                    <li>Request deletion of your account and data</li>
                    <li>Withdraw consent at any time</li>
                    <li>File a complaint with data protection authorities</li>
                </ul>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">6. User Responsibilities</h4>
                <p style="margin-bottom: 10px;">By using LechGO, you agree to:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Provide accurate and truthful information</li>
                    <li>Keep your account credentials secure</li>
                    <li>Use the platform responsibly and legally</li>
                    <li>Respect other users' privacy and rights</li>
                    <li>Comply with all applicable laws and regulations</li>
                </ul>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">7. Consent</h4>
                <p style="margin-bottom: 15px;">
                    By checking the "I agree to the Terms & Conditions" box and creating an account, you explicitly consent to the collection, processing, and use of your personal data as described in this agreement.
                </p>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">8. Changes to Terms</h4>
                <p style="margin-bottom: 15px;">
                    We reserve the right to update these terms at any time. Continued use of the platform after changes constitutes acceptance of the updated terms.
                </p>

                <h4 style="margin-top: 20px; margin-bottom: 10px;">9. Contact Us</h4>
                <p style="margin-bottom: 15px;">
                    If you have questions about these terms or your personal data, please contact us through the platform's support system.
                </p>

                <p style="margin-top: 20px; font-weight: 600;">
                    Last Updated: April 21, 2026
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeTermsModal()">Close</button>
                <button class="btn btn-primary" onclick="acceptTerms()">I Accept</button>
            </div>
        </div>
    </div>

    <script>
        function openTermsModal(event) {
            event.preventDefault();
            document.getElementById('termsModal').classList.add('active');
        }

        function closeTermsModal() {
            document.getElementById('termsModal').classList.remove('active');
        }

        function acceptTerms() {
            document.getElementById('terms').checked = true;
            closeTermsModal();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target === modal) {
                closeTermsModal();
            }
        }
    </script>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>
