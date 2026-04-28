<?php
/**
 * Payment Success Handler
 * Displays success message and processes payment verification
 */
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--spacing-lg);
            text-align: center;
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--spacing-md);
            margin: var(--spacing-lg) 0;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" class="no-underline">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
            <nav>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                        <p class="email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    </div>
                    <a href="#" class="btn btn-secondary ml-md" id="logoutBtn">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="success-container">
            <div id="processingDiv" style="display:none;">
                <h2>Processing Your Payment</h2>
                <div class="loading">
                    <div class="spinner"></div>
                    <span>Verifying payment...</span>
                </div>
                <p style="color: #666;">Please wait while we confirm your payment and create your order.</p>
            </div>

            <div id="successDiv" style="display:none;">
                <div style="font-size: 48px; margin-bottom: var(--spacing-lg);">✓</div>
                <h2 style="color: var(--primary-color); margin-bottom: var(--spacing-md);">Payment Successful!</h2>
                <p style="font-size: 16px; color: #666; margin-bottom: var(--spacing-lg);">
                    Your order has been placed successfully. You will be redirected to your orders page shortly.
                </p>
                <a href="/LechGo_Final/public/livestock-owner/my-orders" class="btn btn-primary">View My Orders</a>
            </div>

            <div id="errorDiv" style="display:none;">
                <div style="font-size: 48px; margin-bottom: var(--spacing-lg);">✗</div>
                <h2 style="color: #e74c3c; margin-bottom: var(--spacing-md);">Payment Error</h2>
                <p id="errorMessage" style="font-size: 16px; color: #666; margin-bottom: var(--spacing-lg);"></p>
                <a href="/LechGo_Final/public/livestock-owner/checkout" class="btn btn-primary">Return to Checkout</a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script>
        async function processPaymentSuccess() {
            try {
                // Show processing state
                document.getElementById('processingDiv').style.display = 'block';

                // Get payment intent ID from sessionStorage
                const paymentIntentId = sessionStorage.getItem('paymentIntentId');
                
                if (!paymentIntentId) {
                    throw new Error('Payment intent ID not found. Please try again.');
                }

                console.log('Processing payment for intent:', paymentIntentId);

                // Call the server to verify payment and create orders
                const response = await fetch('/LechGo_Final/public/api/process-payment-success', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_intent_id: paymentIntentId
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || `Error: ${response.status}`);
                }

                if (data.success) {
                    // Show success message
                    document.getElementById('processingDiv').style.display = 'none';
                    document.getElementById('successDiv').style.display = 'block';

                    // Clear sessionStorage
                    sessionStorage.removeItem('paymentIntentId');

                    // Redirect to my-orders after 3 seconds
                    setTimeout(() => {
                        window.location.href = '/LechGo_Final/public/livestock-owner/my-orders';
                    }, 3000);
                } else {
                    throw new Error(data.error || 'Payment processing failed');
                }

            } catch (error) {
                console.error('Payment success error:', error);
                document.getElementById('processingDiv').style.display = 'none';
                document.getElementById('errorDiv').style.display = 'block';
                document.getElementById('errorMessage').textContent = error.message;
            }
        }

        // Process payment when page loads
        document.addEventListener('DOMContentLoaded', function() {
            processPaymentSuccess();
        });
    </script>

    <!-- Logout Confirmation Modal -->
    <div class="modal" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Logout</h2>
                <button class="modal-close" id="closeLogoutModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
                <button class="btn btn-primary" id="confirmLogout">Yes, Logout</button>
            </div>
        </div>
    </div>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>
