# OTP Redirect Flow Fix - Bugfix Design

## Overview

This design addresses a critical authentication flow bug in the `verifyOTP()` method of `AuthController.php`. Currently, new users who register and verify their OTP are incorrectly redirected directly to the dashboard, bypassing the required role selection and profile completion steps. This causes incomplete user data and potential application errors.

The fix ensures that:
- New users (role 'customer' after registration) follow the complete onboarding flow: OTP → Select Role → Complete Profile → Dashboard
- Existing users continue to be redirected directly to their dashboard based on their existing role
- The fix is minimal and targeted, changing only the redirect logic in `verifyOTP()`

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when a user with role 'customer' successfully verifies OTP
- **Property (P)**: The desired behavior for new users - redirect to select-role.php instead of dashboard
- **Preservation**: Existing redirect behavior for non-'customer' roles that must remain unchanged by the fix
- **verifyOTP()**: The method in `AuthController.php` (line ~280-340) that handles OTP verification and post-verification redirects
- **New User**: A user who just completed registration and has role 'customer' (the default role assigned during registration)
- **Existing User**: A user who previously selected a role (role is not 'customer') and is logging in again

## Bug Details

### Bug Condition

The bug manifests when a new user (with role 'customer') successfully verifies their OTP. The `verifyOTP()` method currently checks if the profile is complete and redirects to dashboard or complete-profile, but it never checks if the user needs to select a role first. This causes new users to skip the role selection step entirely.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type { user_role: string, otp_verified: boolean }
  OUTPUT: boolean
  
  RETURN input.user_role == 'customer'
         AND input.otp_verified == true
         AND userIsAtOTPVerificationStep()
         AND NOT redirectedToSelectRole()
END FUNCTION
```

### Examples

- **New User Registration Flow (BUGGY)**: User registers → verifies email → verifies OTP → **redirected to dashboard** (WRONG - should go to select-role.php)
- **New User Registration Flow (CORRECT)**: User registers → verifies email → verifies OTP → **redirected to select-role.php** → selects role → completes profile → dashboard
- **Existing User Login Flow (CORRECT)**: User logs in → verifies OTP → redirected to dashboard (this should continue to work)
- **Edge Case - Customer Role After Role Selection**: User selects 'customer' role on select-role.php → redirected to dashboard (correct - customers don't need profile completion)

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Existing users with non-'customer' roles must continue to be redirected directly to their dashboard after OTP verification
- Users with complete profiles must continue to skip the profile completion step
- Users with roles that don't require profile data ('customer', 'admin', 'logistics') must continue to go directly to dashboard after role selection
- OTP verification failure handling must remain unchanged
- Invalid session handling must remain unchanged

**Scope:**
All inputs that do NOT involve a user with role 'customer' verifying OTP should be completely unaffected by this fix. This includes:
- Existing users logging in (role is 'lechonero', 'supplier', 'livestock_owner', 'pig_caretaker', 'admin', 'logistics')
- OTP verification failures
- Invalid session scenarios
- Profile completion flows

## Hypothesized Root Cause

Based on the bug description and code analysis, the root cause is:

1. **Missing Role Check in verifyOTP()**: The `verifyOTP()` method (lines ~280-340 in AuthController.php) does not check if the user's role is 'customer' before deciding where to redirect. It only checks if the profile is complete.

2. **Incorrect Flow Logic**: The current logic is:
   - Verify OTP → Check if profile complete → Redirect to dashboard or complete-profile
   - **Missing step**: Check if user needs to select role first

3. **Default Role Assignment**: New users are assigned role 'customer' during registration (line ~30 in AuthController.php), but this role is never used as a signal to redirect to role selection.

4. **Session Data Not Set**: The code sets temporary session variables (`temp_user_id`, `temp_user_email`, `temp_user_name`) but these are set AFTER the redirect decision is made, so they're never used.

## Correctness Properties

Property 1: Bug Condition - New Users Redirected to Role Selection

_For any_ user who successfully verifies OTP and has role 'customer', the fixed verifyOTP function SHALL redirect them to select-role.php, setting temporary session variables (temp_user_id, temp_user_email, temp_user_name) and displaying a success message prompting role selection.

**Validates: Requirements 2.1, 2.2**

Property 2: Preservation - Existing User Redirect Behavior

_For any_ user who successfully verifies OTP and has a role other than 'customer', the fixed verifyOTP function SHALL produce exactly the same redirect behavior as the original function, preserving the existing logic that checks profile completion and redirects to either the dashboard or complete-profile page.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `app/controllers/AuthController.php`

**Function**: `verifyOTP()` (approximately lines 280-340)

**Specific Changes**:
1. **Add Role Check After OTP Verification**: After successfully verifying the OTP and retrieving the user, add a conditional check for `$user->role === 'customer'`

2. **Redirect New Users to Role Selection**: If the user's role is 'customer', set temporary session variables and redirect to `/select-role` with an appropriate success message

3. **Move Existing Logic to Else Block**: Move the current profile completion check and dashboard redirect logic into an else block so it only executes for non-'customer' roles

4. **Set Temporary Session Variables**: Ensure `temp_user_id`, `temp_user_email`, and `temp_user_name` are set before redirecting to select-role.php

5. **Update Success Message**: Change the success message for new users to indicate they should select their role

### Pseudocode for Fix

```
FUNCTION verifyOTP_fixed()
  // ... existing OTP verification logic ...
  
  IF otp_verified THEN
    user = getUserByEmail(email)
    session.setUser(user.id, user.email, user.name, user.role)
    session.clearOTP()
    
    // NEW: Check if this is a new registration (role is 'customer')
    IF user.role == 'customer' THEN
      // New registration - redirect to role selection
      session.set('temp_user_id', user.id)
      session.set('temp_user_email', user.email)
      session.set('temp_user_name', user.name)
      session.set('success', 'Email verified! Now select your role to continue.')
      redirect('/select-role')
    ELSE
      // Existing user - check if profile is complete
      IF isProfileComplete(user.id, user.role) THEN
        session.set('success', 'Welcome back! You are logged in.')
        dashboard = getRoleDashboard(user.role)
        redirect(dashboard)
      ELSE
        session.set('success', 'Please complete your profile to continue.')
        redirect('/complete-profile')
      END IF
    END IF
  ELSE
    // ... existing error handling ...
  END IF
END FUNCTION
```

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that simulate the new user registration flow (register → verify email → verify OTP) and assert that the user is redirected to select-role.php. Run these tests on the UNFIXED code to observe failures and understand the root cause.

**Test Cases**:
1. **New User OTP Verification Test**: Simulate a new user with role 'customer' verifying OTP (will fail on unfixed code - redirects to dashboard instead of select-role)
2. **Session Variables Test**: Verify that temp_user_id, temp_user_email, temp_user_name are set after OTP verification for new users (will fail on unfixed code - variables not set or set too late)
3. **Success Message Test**: Verify the success message prompts role selection (will fail on unfixed code - wrong message)
4. **Complete Flow Test**: Test the full flow from registration to dashboard (will fail on unfixed code - skips role selection)

**Expected Counterexamples**:
- New users with role 'customer' are redirected to dashboard or complete-profile instead of select-role.php
- Temporary session variables are not set or are set after redirect
- Success message does not mention role selection

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := verifyOTP_fixed(input)
  ASSERT result.redirect_url == '/select-role'
  ASSERT result.session['temp_user_id'] == input.user_id
  ASSERT result.session['temp_user_email'] == input.user_email
  ASSERT result.session['temp_user_name'] == input.user_name
  ASSERT result.session['success'] CONTAINS 'select your role'
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT verifyOTP_original(input) = verifyOTP_fixed(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for existing user logins, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Existing User Login Preservation**: Observe that existing users (role != 'customer') are redirected to dashboard after OTP verification on unfixed code, then write test to verify this continues after fix
2. **Profile Completion Preservation**: Observe that users with incomplete profiles are redirected to complete-profile on unfixed code, then write test to verify this continues after fix
3. **OTP Failure Preservation**: Observe that OTP verification failures display error messages on unfixed code, then write test to verify this continues after fix
4. **Invalid Session Preservation**: Observe that invalid session scenarios redirect to login on unfixed code, then write test to verify this continues after fix

### Unit Tests

- Test OTP verification for new users (role 'customer') redirects to select-role.php
- Test OTP verification for existing users (role != 'customer') redirects to dashboard or complete-profile
- Test that temporary session variables are set correctly for new users
- Test that success messages are appropriate for each scenario
- Test edge cases (invalid OTP, expired OTP, missing session data)

### Property-Based Tests

- Generate random user states (various roles, profile completion states) and verify OTP verification redirects correctly
- Generate random OTP verification scenarios and verify preservation of existing behavior for non-'customer' roles
- Test that all error handling paths continue to work across many scenarios

### Integration Tests

- Test full new user registration flow: register → verify email → verify OTP → select role → complete profile → dashboard
- Test full existing user login flow: login → verify OTP → dashboard
- Test that role selection updates the user's role in the database
- Test that profile completion works correctly after role selection
