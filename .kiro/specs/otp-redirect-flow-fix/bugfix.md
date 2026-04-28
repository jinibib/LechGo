# Bugfix Requirements Document

## Introduction

This document addresses a critical authentication flow bug where new users who register and verify their OTP are incorrectly redirected directly to the dashboard, bypassing the required role selection and profile completion steps. This causes problems because new accounts haven't selected their role or completed their profile yet, leading to incomplete user data and potential application errors.

The bug affects only new account registrations (users with role 'customer' after OTP verification), while existing users who log in should continue to be redirected directly to their dashboard based on their existing role.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a new user completes registration, verifies email, and verifies OTP THEN the system redirects them directly to the dashboard without role selection or profile completion

1.2 WHEN a new user with role 'customer' verifies OTP THEN the system skips the select-role.php page

1.3 WHEN a new user with role 'customer' verifies OTP THEN the system skips the complete-profile.php page

### Expected Behavior (Correct)

2.1 WHEN a new user completes registration, verifies email, and verifies OTP THEN the system SHALL redirect them to select-role.php for role selection

2.2 WHEN a new user selects a role on select-role.php THEN the system SHALL redirect them to complete-profile.php if their role requires additional profile data

2.3 WHEN a new user completes their profile on complete-profile.php THEN the system SHALL redirect them to the appropriate dashboard based on their selected role

2.4 WHEN an existing user logs in and verifies OTP THEN the system SHALL redirect them directly to their dashboard based on their existing role

### Unchanged Behavior (Regression Prevention)

3.1 WHEN an existing user with a non-'customer' role logs in and verifies OTP THEN the system SHALL CONTINUE TO redirect them directly to their role-specific dashboard

3.2 WHEN an existing user with a complete profile logs in and verifies OTP THEN the system SHALL CONTINUE TO skip the profile completion step

3.3 WHEN a user with role 'customer', 'admin', or 'logistics' verifies OTP THEN the system SHALL CONTINUE TO redirect them to the dashboard without requiring profile completion (as these roles don't need additional profile data)

3.4 WHEN the OTP verification fails THEN the system SHALL CONTINUE TO display an error message and remain on the verify-otp.php page

3.5 WHEN session data is invalid during OTP verification THEN the system SHALL CONTINUE TO redirect to the login page with an error message
