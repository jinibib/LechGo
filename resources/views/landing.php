<?php

/**
 * Landing Page View
 * Main entry point for unauthenticated users
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LechGO - Lechon Supply Chain & Ordering Made Easy</title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['baseUrl'] ?? '/LechGo_Final/public'; ?>/styles.css">
</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                <div class="logo-text">LechGO</div>
            </div>
            <button class="menu-toggle">☰</button>
            <nav>
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="/LechGo_Final/public/login" class="btn btn-login">Log In</a>
                <a href="/LechGo_Final/public/register" class="btn btn-primary">Get Started</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <h1>Lechon On The Go</h1>
                <p>The modern way to order authentic lechon. Connect with trusted lechoneros, suppliers, and logistics partners.</p>
                <div class="hero-actions">
                    <a href="/LechGo_Final/public/register" class="btn btn-hero-primary">Start Ordering</a>
                    <a href="#features" class="btn btn-hero-secondary">Learn More</a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features" id="features">
            <div class="features-container">
                <h2>Why Choose LechGO?</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3>Easy Ordering</h3>
                        <p>Browse, select, and order your lechon in just a few clicks. Simple, fast, and transparent.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">✅</div>
                        <h3>Verified Partners</h3>
                        <p>All lechoneros and suppliers are verified and rated by our community for quality assurance.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📍</div>
                        <h3>Real-Time Tracking</h3>
                        <p>Track your order from cooking to delivery with live updates and estimated arrival times.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💳</div>
                        <h3>Secure Payments</h3>
                        <p>Flexible payment options with secure processing and buyer protection.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">⭐</div>
                        <h3>Ratings & Reviews</h3>
                        <p>See honest reviews from other customers to make informed decisions.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🚀</div>
                        <h3>Fast Delivery</h3>
                        <p>Reliable delivery network ensuring your lechon arrives hot and fresh.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section style="padding: var(--spacing-xl) var(--spacing-md); background-color: var(--white);">
            <div style="max-width: 1200px; margin: 0 auto;">
                <h2 style="text-align: center; color: var(--primary-red); margin-bottom: var(--spacing-xl);">How It Works</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-lg);">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">1️⃣</div>
                        <h4>Create Account</h4>
                        <p style="color: var(--text-gray);">Sign up and verify your email</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">2️⃣</div>
                        <h4>Browse Menu</h4>
                        <p style="color: var(--text-gray);">Explore available lechon options</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">3️⃣</div>
                        <h4>Place Order</h4>
                        <p style="color: var(--text-gray);">Select and confirm your order</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">4️⃣</div>
                        <h4>Pay & Wait</h4>
                        <p style="color: var(--text-gray);">Complete payment and receive updates</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">5️⃣</div>
                        <h4>Track Status</h4>
                        <p style="color: var(--text-gray);">Monitor cooking and delivery progress</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">6️⃣</div>
                        <h4>Enjoy & Review</h4>
                        <p style="color: var(--text-gray);">Receive your order and share feedback</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="cta-section">
            <h2>Ready to Experience LechGO?</h2>
            <p>Join thousands of satisfied customers enjoying fresh, authentic lechon delivered right to your door.</p>
            <a href="/LechGo_Final/public/register" class="btn btn-primary" style="background-color: var(--white); color: var(--primary-red);">Register Now</a>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>LechGO</h4>
                <p>Making lechon ordering simple, transparent, and accessible to everyone.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="#">Contact</a>
            </div>
            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-links">
                    <a href="#">f</a>
                    <a href="#">tw</a>
                    <a href="#">ig</a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Contact</h4>
                <p>Email: info@lechgo.com</p>
                <p>Phone: (63) 123-4567</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 LechGO. All rights reserved. | <a href="#" style="color: rgba(255,255,255,0.8);">Privacy Policy</a> | <a href="#" style="color: rgba(255,255,255,0.8);">Terms of Service</a></p>
        </div>
    </footer>

    <!-- Display Session Messages -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success'])): ?>
                showAlert("<?php echo htmlspecialchars($_SESSION['success']); ?>", 'success');
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                showAlert("<?php echo htmlspecialchars($_SESSION['error']); ?>", 'error');
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['warning'])): ?>
                showAlert("<?php echo htmlspecialchars($_SESSION['warning']); ?>", 'warning');
                <?php unset($_SESSION['warning']); ?>
            <?php endif; ?>
        });

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} show`;
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.maxWidth = '400px';
            alertDiv.textContent = message;

            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>

    <script src="<?php echo $GLOBALS['baseUrl'] ?? '/LechGo_Final/public'; ?>/script.js"></script>
</body>
</html>
