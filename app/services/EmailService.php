<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Service
 * Handles email sending using PHPMailer with Gmail SMTP
 */

class EmailService
{
    private $mail;

    public function __construct()
    {
        // Require PHPMailer
        require_once BASE_PATH . '/PHPMailer-master/src/Exception.php';
        require_once BASE_PATH . '/PHPMailer-master/src/PHPMailer.php';
        require_once BASE_PATH . '/PHPMailer-master/src/SMTP.php';

        $this->mail = new PHPMailer(true);

        // Configure SMTP
        try {
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SMTP_USER;
            $this->mail->Password = SMTP_PASS;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = SMTP_PORT;

            // Set sender
            $this->mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        } catch (Exception $e) {
            throw new Exception("SMTP Configuration Error: " . $e->getMessage());
        }
    }

    /**
     * Send email verification
     */
    public function sendVerificationEmail($recipientEmail, $recipientName, $token)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);

            $baseUrl = 'http://localhost/LechGo_Final/public';
            $verificationLink = $baseUrl . '/verify-email?token=' . urlencode($token);

            $subject = EMAIL_VERIFICATION_SUBJECT;
            
            $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                    .header { background-color: #D1332D; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 30px 20px; }
                    .verification-btn { display: inline-block; background-color: #D1332D; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
                    .warning { background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>LechGO Verification</h1>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                        <p>Welcome to LechGO! To complete your registration and verify your email address, please click the button below:</p>
                        
                        <a href='" . htmlspecialchars($verificationLink) . "' class='verification-btn'>Verify Your Email</a>
                        
                        <p>Or copy and paste this link in your browser:</p>
                        <p style='word-break: break-all; background-color: #f5f5f5; padding: 10px; border-radius: 4px;'>
                            " . htmlspecialchars($verificationLink) . "
                        </p>
                        
                        <div class='warning'>
                            <strong>Security Note:</strong> This link will expire in 24 hours. If you didn't request this email, please ignore it.
                        </div>
                        
                        <p>If that doesn't work, please try clicking the link or copying and pasting it in your browser.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 LechGO. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = "Welcome to LechGO! Please verify your email by visiting: " . $verificationLink;

            return $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $e->getMessage());
        }
    }

    /**
     * Send OTP email
     */
    public function sendOTPEmail($recipientEmail, $recipientName, $otp)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);

            $subject = OTP_VERIFICATION_SUBJECT;
            
            $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                    .header { background-color: #D1332D; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 30px 20px; }
                    .otp-box { background-color: #f5f5f5; border: 2px solid #D1332D; padding: 20px; text-align: center; border-radius: 8px; margin: 30px 0; }
                    .otp-box .otp-code { font-size: 36px; font-weight: bold; color: #D1332D; letter-spacing: 8px; font-family: 'Courier New', monospace; }
                    .otp-box .expiry { font-size: 12px; color: #666; margin-top: 10px; }
                    .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
                    .warning { background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Your LechGO Login Code</h1>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                        <p>You requested to log in to your LechGO account. Use the code below to complete your login:</p>
                        
                        <div class='otp-box'>
                            <div class='otp-code'>" . htmlspecialchars($otp) . "</div>
                            <div class='expiry'>This code expires in 5 minutes</div>
                        </div>
                        
                        <div class='warning'>
                            <strong>Security Warning:</strong> Never share this code with anyone. LechGO staff will never ask for your code.
                        </div>
                        
                        <p>If you didn't request this code, please ignore this email. Your account will remain secure.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 LechGO. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = "Your LechGO login code is: " . $otp . "\nThis code expires in 5 minutes.";

            return $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $e->getMessage());
        }
    }

    /**
     * Send password reset email (for future use)
     */
    public function sendPasswordResetEmail($recipientEmail, $recipientName, $resetLink)
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);

            $subject = "Reset Your LechGO Password";
            
            $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                    .header { background-color: #D1332D; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 30px 20px; }
                    .reset-btn { display: inline-block; background-color: #D1332D; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Password Reset Request</h1>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                        <p>We received a request to reset your password. Click the button below to create a new password:</p>
                        
                        <a href='" . htmlspecialchars($resetLink) . "' class='reset-btn'>Reset Password</a>
                        
                        <p>This link will expire in 24 hours. If you didn't request this, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 LechGO. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = "Click here to reset your password: " . $resetLink;

            return $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $e->getMessage());
        }
    }

    /**
     * Send generic email with custom subject and body
     */
    public function sendEmail($recipientEmail, $subject, $htmlBody, $recipientName = 'User')
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);
            
            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = strip_tags($htmlBody);
            
            return $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Email sending failed: " . $e->getMessage());
        }
    }
}
