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
        <div class="auth-container">
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
                    <button type="button" title="Toggle password visibility">👁️</button>
                    <div id="passwordStrength" class="password-strength"></div>
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

                <!-- Role Selection -->
                <div class="form-group">
                    <label for="role">Account Type</label>
                    <select id="role" name="role" required>
                        <option value="customer">Customer (I want to order lechon)</option>
                        <option value="lechonero">Lechonero (I sell lechon)</option>
                        <option value="livestock_owner">Livestock Owner (I manage a farm)</option>
                        <option value="supplier">Feed Supplier (I supply feeds)</option>
                        <option value="pig_caretaker">Pig Caretaker (I work on a farm)</option>
                        <option value="logistics">Logistics (I deliver orders)</option>
                        <option value="admin">Admin</option>
                    </select>
                    <div class="form-error"></div>
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
                            I agree to the <a href="#">Terms & Conditions</a>
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
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>
