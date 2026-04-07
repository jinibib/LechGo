# LechGO Authentication System - Implementation Guide

## 🚀 Quick Start Setup

This guide will walk you through setting up the LechGO email verification + OTP authentication system.

---

## 📋 Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for PHPMailer)
- XAMPP or similar local development environment
- Gmail account with 2FA enabled (for email sending)

---

## 🔧 Step 1: Database Setup

### 1.1 Create Database via phpMyAdmin

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click **New** and create database named `lechgo_db`
3. Set collation to `utf8mb4_unicode_ci`

### 1.2 Import Existing Tables

1. Select `lechgo_db` database
2. Import the main schema from `lechgo_db.sql` (already provided)
3. This creates base user tables and other entities

### 1.3 Add Extension Tables for Authentication

1. Go to **SQL** tab in phpMyAdmin
2. Copy and paste the contents of `schema-extensions.sql` from the root folder
3. Execute the query
4. Verify three tables are created:
   - `email_verification_tokens`
   - `otp_verification`
   - `users` (modified with new columns)

**Tables Created:**
```
✓ email_verification_tokens - Stores email verification tokens (24-hour expiry)
✓ otp_verification - Stores OTP codes for login (5-minute expiry, attempt tracking)
✓ users (extended) - Added email_verified and email_verified_at columns
```

---

## 📧 Step 2: Gmail SMTP Configuration

### 2.1 Enable 2-Factor Authentication on Gmail

1. Go to `https://myaccount.google.com/`
2. Click **Security** on left sidebar
3. Enable **2-Step Verification**

### 2.2 Generate Google App Password

1. After enabling 2FA, go back to **Security**
2. Find **App passwords** section (appears after 2FA is enabled)
3. Select:
   - App: **Mail**
   - Device: **Windows Computer** (or your OS)
4. Google generates a **16-character password**
5. Copy this password (you'll use it in next step)

### 2.3 Update Email Configuration

1. Open `/config/email.php`
2. Replace these values:

```php
define('SMTP_USER', 'your-gmail@gmail.com');     // Your Gmail address
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx');       // 16-char app password from step 2.4
```

**Example:**
```php
define('SMTP_USER', 'lechgo.system@gmail.com');
define('SMTP_PASS', 'abcd efgh ijkl mnop');  // WITHOUT spaces in production
```

**⚠️ Important:** Never commit passwords to Git. Consider using environment variables in production.

---

## 🗄️ Step 3: Database Connection

1. Open `/config/db.php`
2. Verify connection details (usually default for XAMPP):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP uses empty password
define('DB_NAME', 'lechgo_db');
define('DB_PORT', 3306);
```

---

## 🌐 Step 4: Access the Application

### Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**

### Browse Application
- **Landing Page:** `http://localhost/LechGo_Final/public/`
- **Register:** `http://localhost/LechGo_Final/public/register`
- **Login:** `http://localhost/LechGo_Final/public/login`

---

## 🔑 Step 5: Test Authentication Flow

### Test Registration
1. Go to register page
2. Fill in form:
   - **Name:** John Doe
   - **Email:** your-email@gmail.com (use a valid email you check)
   - **Password:** Test123456 (must have 8+ chars, 1 uppercase, 1 number)
   - **Phone:** 09123456789
   - **Role:** Customer
3. Click **Create Account**
4. Check your email for verification link
5. Click the link in the email

### Test Email Verification
1. After clicking, you should receive OTP email
2. Go to verify-otp page
3. Enter the 6-digit OTP from email
4. You're now logged in!

### Test Login
1. Log out: `/logout`
2. Go to login page
3. Enter email and password
4. You'll receive OTP email
5. Enter OTP to complete login

---

## 📂 Project Structure

```
LechGo_Final/
├── config/
│   ├── db.php                    ← Database configuration
│   └── email.php                 ← Email/SMTP configuration
├── app/
│   ├── controllers/
│   │   └── AuthController.php    ← Authentication logic
│   ├── models/
│   │   ├── User.php              ← User database operations
│   │   ├── EmailVerification.php  ← Token management
│   │   └── OTP.php               ← OTP management
│   ├── services/
│   │   └── EmailService.php      ← PHPMailer wrapper
│   └── middleware/
│       └── Session.php           ← Session management
├── public/
│   ├── index.php                 ← Front controller/router
│   ├── styles.css                ← RED-themed CSS
│   └── script.js                 ← Form validation & OTP handling
├── resources/
│   └── views/
│       ├── landing.php           ← Landing page
│       └── auth/
│           ├── login.php         ← Login form
│           ├── register.php      ← Registration form
│           ├── verify-email.php  ← Email verification page
│           └── verify-otp.php    ← OTP entry page
├── schema-extensions.sql         ← DB extension script
└── composer.json                 ← PHPMailer dependency
```

---

## 🎨 UI Features

### RED Theme Colors
- **Primary Red:** `#D1332D`
- **Dark Red:** `#A00D0A`
- **Light Red:** `#F4D4D2`

### Responsive Design
- ✓ Mobile-first approach
- ✓ Works on all devices (320px to 4K)
- ✓ Touch-friendly buttons and inputs

### Form Features
- ✓ Real-time email validation
- ✓ Password strength indicator
- ✓ Show/hide password toggle
- ✓ Client-side form validation
- ✓ Error message display
- ✓ Loading states

### OTP Features
- ✓ 6-digit auto-focusing input
- ✓ Copy-paste support
- ✓ Auto-advance to next digit
- ✓ 5-minute countdown timer
- ✓ Resend button with cooldown

---

## 🔐 Security Features

### Password Security
- Bcrypt hashing (PHP native)
- 8+ characters required
- Must contain: uppercase, lowercase, number
- Password strength indicator

### Token Security
- Email tokens: 24-hour expiry
- OTP tokens: 5-minute expiry
- Unique tokens (cryptographically random)
- One-time use enforcement

### OTP Security
- 6-digit random codes
- Max 5 failed attempts → 1-hour account lock
- Automatic cleanup (expired tokens deleted)
- Session-based tracking

### Session Security
- Secure PHP sessions
- CSRF token generation
- Automatic logout detection
- Protected dashboard routes

### Email Security
- Valid email format validation
- Domain/MX record checking
- Gmail SMTP with TLS encryption
- No sensitive data in logs

---

## 🧪 Testing Checklist

- [ ] Database created and tables imported
- [ ] Gmail SMTP credentials configured
- [ ] Landing page loads with RED theme
- [ ] Register page form validation works
- [ ] Registration creates user and sends verification email
- [ ] Verification email link works
- [ ] OTP email received after email verification
- [ ] OTP entry accepts 6 digits
- [ ] OTP timer counts down correctly
- [ ] Valid OTP logs user in
- [ ] Invalid OTP shows error
- [ ] Login page requires email verification first
- [ ] Logout clears session
- [ ] Dashboard only accessible when logged in
- [ ] Resend buttons work with cooldown
- [ ] Mobile responsiveness looks good

---

## 🐛 Troubleshooting

### Issue: "Connection refused" or blank page
**Solution:** Verify XAMPP Apache and MySQL are running

### Issue: 404 errors on routes
**Solution:** Check `.htaccess` exists in `/public/` (or use Apache `mod_rewrite`)

### Issue: Email not sending
**Solution:** 
1. Verify Gmail credentials in `/config/email.php`
2. Check Gmail 2FA and App Password setup
3. Test SMTP: Try sending from mail client with same credentials
4. Check firewall - port 587 might be blocked

### Issue: "Email already registered"
**Solution:** 
1. Check database for duplicate email
2. Delete test user in phpMyAdmin if needed

### Issue: OTP expires immediately
**Solution:** 
1. Check system time is correct
2. Verify database datetime settings
3. Check `OTP_EXPIRY` constant in `/config/email.php`

### Issue: Session not persisting
**Solution:**
1. Verify `session_start()` is first line in index.php
2. Check PHP `session.save_path` is writable
3. Clear browser cookies and try again

---

## 📞 Support & Customization

### To Customize:

**Change RED to different color theme:**
- Edit CSS variables in `/public/styles.css` (`:root` section)

**Modify email templates:**
- Edit HTML in `/app/services/EmailService.php`

**Add more registration fields:**
- Update `/app/models/User.php` create method
- Modify `/resources/views/auth/register.php` form
- Extend users table in database

**Change OTP length (currently 6 digits):**
- Update `OTP_LENGTH` in `/config/email.php`
- Update OTP input count in `/resources/views/auth/verify-otp.php`

---

## 🎯 Next Steps

After authentication is working, you can:

1. **Implement Dashboard Features**
   - Browse lechon listings
   - Create orders
   - Track order status

2. **Add Role-Based Views**
   - Customer dashboard
   - Lechonero management panel
   - Supplier inventory
   - Logistics tracking

3. **Payment Integration**
   - Add payment gateway (Stripe, PayMongo, etc.)
   - Order payment tracking
   - Refund handling

4. **Production Deployment**
   - Use environment variables for secrets
   - Enable HTTPS
   - Set up automated backups
   - Configure production email service

---

## 📄 Files Created/Modified

### New Files:
- ✅ `/config/db.php` - Database config
- ✅ `/config/email.php` - Email config
- ✅ `/public/index.php` - Front controller
- ✅ `/public/styles.css` - RED theme CSS
- ✅ `/public/script.js` - Frontend validation
- ✅ `/app/models/User.php` - User model
- ✅ `/app/models/EmailVerification.php` - Token model
- ✅ `/app/models/OTP.php` - OTP model
- ✅ `/app/middleware/Session.php` - Session management
- ✅ `/app/controllers/AuthController.php` - Auth logic
- ✅ `/app/services/EmailService.php` - Email service
- ✅ `/resources/views/landing.php` - Landing page
- ✅ `/resources/views/auth/login.php` - Login form
- ✅ `/resources/views/auth/register.php` - Register form
- ✅ `/resources/views/auth/verify-email.php` - Email verification
- ✅ `/resources/views/auth/verify-otp.php` - OTP entry
- ✅ `/resources/views/home.php` - Dashboard
- ✅ `/schema-extensions.sql` - Database extensions

---

## ✨ Features Summary

| Feature | Status | Details |
|---------|--------|---------|
| Email Verification | ✅ Complete | 24-hour tokens, resend support |
| OTP Login | ✅ Complete | 6-digit codes, 5-min expiry, auto-lock |
| User Registration | ✅ Complete | Role-based, password strength |
| Responsive Design | ✅ Complete | Mobile-first, RED theme |
| Form Validation | ✅ Complete | Client-side and server-side |
| Session Management | ✅ Complete | Secure PHP sessions |
| Error Handling | ✅ Complete | User-friendly messages |
| Security | ✅ Complete | Bcrypt, rate limiting, CSRF |

---

**Version:** 1.0  
**Last Updated:** March 24, 2026  
**Framework:** Plain PHP MVC  
**Status:** Production Ready ✨
