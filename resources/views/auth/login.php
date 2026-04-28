<?php

/**
 * Login Form View
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <!-- Header/Navigation -->
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
        <!-- Login Form -->
        <div class="auth-container-split">
            <!-- Left Side - Red Background with Logo -->
            <div class="auth-welcome-side">
                <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="auth-logo">
                <h2>Welcome Back!</h2>
                <p>Log in to continue your LechGO experience.</p>
                <div class="auth-benefits">
                </div>
            </div>

            <!-- Right Side - White Background with Form -->
            <div class="auth-form-side">
                <div class="auth-header">
                    <h2>Welcome Back</h2>
                    <p>Log in to your LechGO account</p>
                </div>

                <!-- Display Flash Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning show">
                        <?php echo htmlspecialchars($_SESSION['warning']); ?>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>

                <form id="loginForm" class="auth-form" method="POST" action="/LechGo_Final/public/auth/login">
                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input 
                            type="email" 
                            id="loginEmail" 
                            name="email" 
                            placeholder="Enter your email" 
                            required
                            autocomplete="email"
                        >
                        <div class="form-error"></div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group password-toggle">
                        <label for="loginPassword">Password</label>
                        <input 
                            type="password" 
                            id="loginPassword" 
                            name="password" 
                            placeholder="Enter your password" 
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle-btn" title="Toggle password visibility">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        <div class="form-error"></div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-block">
                        Log In
                    </button>
                </form>

                <!-- Footer Links -->
                <div class="form-footer">
                    <p>Don't have an account? <a href="/LechGo_Final/public/register">Create one</a></p>
                    <p><a href="#">Forgot your password?</a></p>
                </div>
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
</body>
</html>
