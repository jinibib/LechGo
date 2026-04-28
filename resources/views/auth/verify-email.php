<?php

/**
 * Email Verification View
 */

$sessionMiddleware = new Session();
$verification_email = $sessionMiddleware->getVerificationEmail();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - LechGO</title>
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
        <!-- Email Verification Container -->
        <div class="auth-container">
            <div class="auth-header">
                <h2>Verify Your Email</h2>
                <p>We sent a verification link to</p>
                <p style="font-weight: 600; color: var(--primary-red); margin-top: 0.5rem;">
                    <?php 
                    if ($verification_email) {
                        // Mask email address
                        $parts = explode('@', $verification_email);
                        $masked = substr($parts[0], 0, 1) . '***@' . $parts[1];
                        echo htmlspecialchars($masked);
                    } else {
                        echo 'your email';
                    }
                    ?>
                </p>
            </div>

            <!-- Display Flash Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div style="background-color: var(--pale-red); padding: 1rem; border-radius: var(--radius-lg); margin: 1rem 0; text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;"></div>
                <p style="font-size: 0.95rem; color: var(--text-gray); margin: 0;">
                    Check your email inbox (and spam folder) for the verification link. Click the link to verify your email address and continue to the next step.
                </p>
            </div>

            <!-- Resend Email Section -->
            <div style="text-align: center; margin: 1rem 0;">
                <p style="color: var(--text-gray); margin-bottom: 0.75rem;">Didn't receive the email?</p>
                <button class="btn btn-secondary btn-resend" style="cursor: pointer;">
                    Resend Verification Email
                </button>
            </div>

            <!-- Footer Links -->
            <div class="form-footer">
                <p><a href="/LechGo_Final/public/login">Back to Login</a></p>
            </div>
        </div>

        <!-- Auto-verify if token in URL -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const token = urlParams.get('token');

                if (token) {
                    // Token in URL means user clicked the link
                    // Redirect to verify endpoint
                    window.location.href = '/LechGo_Final/public/verify-email?token=' + encodeURIComponent(token);
                }
            });

            // Handle resend button
            document.querySelector('.btn-resend').addEventListener('click', function(e) {
                e.preventDefault();
                const btn = this;

                if (btn.disabled) return;

                btn.disabled = true;
                let timeRemaining = 60;
                btn.textContent = `Resend in ${timeRemaining}s`;

                const interval = setInterval(() => {
                    timeRemaining--;
                    btn.textContent = `Resend in ${timeRemaining}s`;

                    if (timeRemaining === 0) {
                        clearInterval(interval);
                        btn.disabled = false;
                        btn.textContent = 'Resend Verification Email';
                    }
                }, 1000);

                // Send resend request
                fetch('/LechGo_Final/public/auth/resend-verification', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        showAlert(data.message || 'Failed to resend email', 'error');
                        btn.disabled = false;
                        btn.textContent = 'Resend Verification Email';
                        clearInterval(interval);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Resend Verification Email';
                    clearInterval(interval);
                });
            });

            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} show`;
                alertDiv.textContent = message;
                
                const container = document.querySelector('.auth-container');
                container.insertBefore(alertDiv, container.querySelector('.auth-header').nextElementSibling);

                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        </script>
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
