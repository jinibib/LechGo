<?php

/**
 * OTP Verification View
 */

$sessionMiddleware = new Session();
$otp_email = $sessionMiddleware->getOTPEmail();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - LechGO</title>
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
        <!-- OTP Verification Container -->
        <div class="auth-container">
            <div class="auth-header">
                <h2>Enter Your Code</h2>
                <p>We sent a 6-digit code to</p>
                <p style="font-weight: 600; color: var(--primary-red); margin-top: var(--spacing-sm);">
                    <?php 
                    if ($otp_email) {
                        // Mask email address
                        $parts = explode('@', $otp_email);
                        $masked = substr($parts[0], 0, 1) . '***@' . $parts[1];
                        echo htmlspecialchars($masked);
                    } else {
                        echo 'your email';
                    }
                    ?>
                </p>
            </div>

            <!-- Display Flash Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    ✗ <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form id="otpForm" method="POST" action="/LechGo_Final/public/auth/verify-otp" class="auth-form">
                <input type="hidden" name="otp" id="otpCombined" value="">
                <!-- OTP Input Boxes -->
                <div class="otp-container">
                    <p class="otp-label">Enter the 6-digit code</p>
                    <div class="otp-inputs">
                        <input type="text" name="otp_1" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required>
                        <input type="text" name="otp_2" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required>
                        <input type="text" name="otp_3" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required>
                        <input type="text" name="otp_4" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required>
                        <input type="text" name="otp_5" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required>
                        <input type="text" name="otp_6" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required>
                    </div>
                </div>

                <!-- OTP Timer -->
                <div id="otpTimer" class="otp-timer">
                    Code expires in 5:00
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-block">
                    Verify Code
                </button>
            </form>

            <!-- Resend Code Section -->
            <div class="otp-resend-section">
                <p>Didn't receive the code?</p>
                <button class="btn btn-secondary btn-resend" style="cursor: pointer;">
                    Resend Code
                </button>
            </div>

            <!-- Security Info -->
            <div class="otp-security-info">
                <p>
                    🔒 <strong>Never share your code with anyone.</strong> LechGO staff will never ask for your code.
                </p>
            </div>

            <!-- Footer Links -->
            <div class="form-footer">
                <p><a href="/LechGo_Final/public/login">Back to Login</a></p>
            </div>
        </div>

        <!-- OTP Resend Handler -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle OTP input boxes - auto-focus and numeric-only
                const otpInputs = document.querySelectorAll('.otp-inputs input');
                
                console.log('Found ' + otpInputs.length + ' OTP inputs');
                
                otpInputs.forEach((input, index) => {
                    input.addEventListener('input', function(e) {
                        // Only allow numbers
                        this.value = this.value.replace(/[^0-9]/g, '');
                        
                        // Move to next field if digit entered
                        if (this.value.length === 1 && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }
                    });

                    input.addEventListener('keydown', function(e) {
                        // Handle backspace - go to previous field
                        if (e.key === 'Backspace' && this.value === '' && index > 0) {
                            otpInputs[index - 1].focus();
                            otpInputs[index - 1].value = '';
                        }
                        
                        // Only allow numbers and backspace/delete keys
                        if (!/[0-9]|Backspace|Delete|ArrowLeft|ArrowRight|Tab]/.test(e.key)) {
                            e.preventDefault();
                        }
                    });

                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const pastedData = (e.clipboardData || window.clipboardData).getData('text');
                        const pasted = pastedData.replace(/[^0-9]/g, '').substring(0, 6);
                        
                        // Fill in all fields with pasted numbers
                        for (let i = 0; i < pasted.length; i++) {
                            if (i < otpInputs.length) {
                                otpInputs[i].value = pasted[i];
                            }
                        }
                        
                        // Focus last filled input or the 6th input
                        otpInputs[Math.min(pasted.length, otpInputs.length - 1)].focus();
                    });
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
                            btn.textContent = 'Resend Code';
                        }
                    }, 1000);

                    // Send resend request
                    fetch('/LechGo_Final/public/auth/resend-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            showAlert(data.message || 'Failed to resend code', 'error');
                            btn.disabled = false;
                            btn.textContent = 'Resend Code';
                            clearInterval(interval);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('An error occurred', 'error');
                        btn.disabled = false;
                        btn.textContent = 'Resend Code';
                        clearInterval(interval);
                    });
                });

                // Combine OTP input values into hidden field before submission
                const otpForm = document.getElementById('otpForm');
                if (otpForm) {
                    otpForm.addEventListener('submit', function (e) {
                        const otpCombined = document.getElementById('otpCombined');
                        const otpInputs = document.querySelectorAll('.otp-inputs input');
                        let code = '';
                        otpInputs.forEach(input => {
                            code += (input.value || '');
                        });
                        otpCombined.value = code.trim();

                        if (code.length !== 6 || !/^\d{6}$/.test(code)) {
                            e.preventDefault();
                            showAlert('Please enter a valid 6-digit OTP code', 'error');
                            return false;
                        }
                    });
                }

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
            });
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
