<?php

/**
 * Email Configuration
 * 
 * Gmail SMTP Settings for LechGO
 * 
 * Setup Instructions:
 * 1. Enable 2-Factor Authentication on your Google Account
 * 2. Generate an App Password at https://myaccount.google.com/apppasswords
 * 3. Replace SMTP_USER and SMTP_PASS below with your credentials
 * 4. DO NOT commit this file to version control with real credentials
 */

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');

// Gmail credentials - Use App Password (not your regular password)
define('SMTP_USER', 'jennyvievemahinay@gmail.com');  // Change to your Gmail
define('SMTP_PASS', '');      // 16-character app password

// Sender details
define('MAIL_FROM_NAME', 'LechGO');
define('MAIL_FROM_EMAIL', 'noreply@lechgo.com');

// Email Templates Settings
define('EMAIL_VERIFICATION_SUBJECT', 'Verify Your LechGO Email Address');
define('OTP_VERIFICATION_SUBJECT', 'Your LechGO Login Code');

// Token & OTP Settings
define('EMAIL_TOKEN_EXPIRY', 86400);  // 24 hours in seconds
define('OTP_EXPIRY', 300);            // 5 minutes in seconds
define('OTP_LENGTH', 6);              // 6-digit OTP
define('MAX_OTP_ATTEMPTS', 5);        // Max failed attempts before lockout
define('OTP_LOCKOUT_TIME', 3600);     // 1 hour lockout
