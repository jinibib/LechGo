/**
 * LechGO - Main JavaScript
 * Form validation, OTP input handling, and UX enhancements
 */

// ========== Utility Functions ==========

/**
 * Show error message on form field
 */
function showFieldError(fieldId, message) {
  const field = document.getElementById(fieldId);
  const errorElement = field.nextElementSibling;

  if (errorElement && errorElement.classList.contains('form-error')) {
    errorElement.textContent = message;
    errorElement.classList.add('show');
    field.classList.add('error');
  }
}

/**
 * Clear error message from form field
 */
function clearFieldError(fieldId) {
  const field = document.getElementById(fieldId);
  const errorElement = field.nextElementSibling;

  if (errorElement && errorElement.classList.contains('form-error')) {
    errorElement.classList.remove('show');
    field.classList.remove('error');
  }
}

/**
 * Validate email format
 */
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

/**
 * Validate password strength
 * Requirements: min 8 chars, at least 1 uppercase, 1 lowercase, 1 number
 */
function getPasswordStrength(password) {
  let strength = 0;
  
  if (password.length >= 8) strength++;
  if (/[A-Z]/.test(password)) strength++;
  if (/[a-z]/.test(password)) strength++;
  if (/[0-9]/.test(password)) strength++;
  if (/[^A-Za-z0-9]/.test(password)) strength++;

  return strength;
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} show`;
  alertDiv.textContent = message;

  const container = document.querySelector('.auth-container') || document.querySelector('main');
  if (container) {
    container.insertBefore(alertDiv, container.firstChild);

    setTimeout(() => {
      alertDiv.remove();
    }, 5000);
  }
}

/**
 * Format time display (MM:SS)
 */
function formatTime(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
}

// ========== Password Toggle ==========

document.addEventListener('DOMContentLoaded', function () {
  // Password toggle functionality
  const passwordFields = document.querySelectorAll('.password-toggle');
  passwordFields.forEach(field => {
    const input = field.querySelector('input');
    const button = field.querySelector('button');

    if (button) {
      button.addEventListener('click', function (e) {
        e.preventDefault();
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        
        // Toggle SVG icon
        const svg = button.querySelector('svg');
        if (svg) {
          if (isPassword) {
            // Show "eye-slash" icon (password visible)
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
          } else {
            // Show "eye" icon (password hidden)
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
          }
        }
      });
    }
  });

  // ========== Registration Form Validation ==========

  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    const nameField = document.getElementById('name');
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    const phoneField = document.getElementById('phone');
    const passwordStrengthIndicator = document.getElementById('passwordStrength');

    // Real-time validation for name
    if (nameField) {
      nameField.addEventListener('blur', function () {
        if (this.value.trim().length < 3) {
          showFieldError('name', 'Name must be at least 3 characters');
        } else {
          clearFieldError('name');
        }
      });
    }

    // Real-time validation for email
    if (emailField) {
      emailField.addEventListener('blur', function () {
        if (!isValidEmail(this.value)) {
          showFieldError('email', 'Please enter a valid email address');
        } else {
          clearFieldError('email');
        }
      });
    }

    // Real-time validation for password with strength meter
    if (passwordField) {
      passwordField.addEventListener('input', function () {
        const strength = getPasswordStrength(this.value);
        
        if (passwordStrengthIndicator) {
          if (this.value.length === 0) {
            passwordStrengthIndicator.style.display = 'none';
          } else {
            passwordStrengthIndicator.style.display = 'block';
            
            let strengthLevel = 'Weak';
            let strengthColor = '#E74C3C';
            
            if (strength >= 4) {
              strengthLevel = 'Strong';
              strengthColor = '#27AE60';
            } else if (strength >= 3) {
              strengthLevel = 'Good';
              strengthColor = '#F39C12';
            }

            passwordStrengthIndicator.textContent = `Password Strength: ${strengthLevel}`;
            passwordStrengthIndicator.style.color = strengthColor;
          }
        }

        clearFieldError('password');
      });

      passwordField.addEventListener('blur', function () {
        if (this.value.length < 8) {
          showFieldError('password', 'Password must be at least 8 characters');
        } else if (!/[A-Z]/.test(this.value)) {
          showFieldError('password', 'Password must contain at least 1 uppercase letter');
        } else if (!/[0-9]/.test(this.value)) {
          showFieldError('password', 'Password must contain at least 1 number');
        }
      });
    }

    // Real-time validation for phone
    if (phoneField) {
      phoneField.addEventListener('blur', function () {
        if (this.value.trim().length < 10) {
          showFieldError('phone', 'Please enter a valid phone number');
        } else {
          clearFieldError('phone');
        }
      });
    }

    // Form submission
    registerForm.addEventListener('submit', function (e) {
      e.preventDefault();

      // Validate all fields
      let isValid = true;

      if (!nameField || nameField.value.trim().length < 3) {
        showFieldError('name', 'Name must be at least 3 characters');
        isValid = false;
      }

      if (!emailField || !isValidEmail(emailField.value)) {
        showFieldError('email', 'Please enter a valid email address');
        isValid = false;
      }

      if (!passwordField || passwordField.value.length < 8) {
        showFieldError('password', 'Password must be at least 8 characters');
        isValid = false;
      }

      if (!phoneField || phoneField.value.trim().length < 10) {
        showFieldError('phone', 'Please enter a valid phone number');
        isValid = false;
      }

      if (isValid) {
        // Show loading state
        const submitBtn = registerForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');

        // Submit form
        setTimeout(() => {
          registerForm.submit();
        }, 500);
      }
    });
  }
  // ========== Location-dependent dropdowns (Complete Profile) ==========
  const municipalitySelect = document.getElementById('municipality');
  const barangaySelect = document.getElementById('barangay');
  const streetSelect = document.getElementById('street');

  function updateSelectOptions(selectElement, options, placeholder) {
    selectElement.innerHTML = '';
    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    selectElement.appendChild(placeholderOption);

    if (Array.isArray(options)) {
      options.forEach(item => {
        const option = document.createElement('option');
        option.value = item;
        option.textContent = item;
        selectElement.appendChild(option);
      });
    }
    selectElement.value = '';
  }

  // Initialize location dropdowns from database
  if (municipalitySelect && barangaySelect && streetSelect) {
    const baseUrl = '/LechGo_Final/public/api/locations';

    // Fetch municipalities on page load
    fetch(baseUrl + '?action=municipalities')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateSelectOptions(municipalitySelect, data.data, 'Select municipality/district');
        }
      })
      .catch(error => console.error('Error fetching municipalities:', error));

    municipalitySelect.addEventListener('change', () => {
      const municipality = municipalitySelect.value;
      if (municipality) {
        // Fetch barangays for selected municipality
        fetch(baseUrl + '?action=barangays&municipality=' + encodeURIComponent(municipality))
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              updateSelectOptions(barangaySelect, data.data, 'Select barangay');
            }
          })
          .catch(error => console.error('Error fetching barangays:', error));
        
        updateSelectOptions(streetSelect, [], 'Select street');
      }
    });

    barangaySelect.addEventListener('change', () => {
      const municipality = municipalitySelect.value;
      const barangay = barangaySelect.value;
      if (barangay) {
        // Fetch streets for selected barangay
        fetch(baseUrl + '?action=streets&municipality=' + encodeURIComponent(municipality) + '&barangay=' + encodeURIComponent(barangay))
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              updateSelectOptions(streetSelect, data.data, 'Select street');
            }
          })
          .catch(error => console.error('Error fetching streets:', error));
      }
    });
  }
  // ========== Login Form Validation ==========

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    const emailField = document.getElementById('loginEmail');

    if (emailField) {
      emailField.addEventListener('blur', function () {
        if (!isValidEmail(this.value)) {
          showFieldError('loginEmail', 'Please enter a valid email address');
        } else {
          clearFieldError('loginEmail');
        }
      });
    }

    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();

      let isValid = true;

      if (!emailField || !isValidEmail(emailField.value)) {
        showFieldError('loginEmail', 'Please enter a valid email address');
        isValid = false;
      }

      if (isValid) {
        const submitBtn = loginForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');

        setTimeout(() => {
          loginForm.submit();
        }, 500);
      }
    });
  }

  // ========== OTP Input Handling ==========

  const otpInputs = document.querySelectorAll('.otp-inputs input');
  if (otpInputs.length > 0) {
    otpInputs.forEach((input, index) => {
      // Allow only numbers
      input.addEventListener('input', function (e) {
        if (!/^[0-9]$/.test(this.value)) {
          this.value = '';
          return;
        }

        // Move to next input
        if (this.value !== '' && index < otpInputs.length - 1) {
          otpInputs[index + 1].focus();
        }
      });

      // Handle backspace
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && this.value === '' && index > 0) {
          otpInputs[index - 1].focus();
        }
      });

      // Handle paste
      input.addEventListener('paste', function (e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const otpValues = pastedText.slice(0, otpInputs.length).split('');

        otpValues.forEach((value, i) => {
          if (/^[0-9]$/.test(value)) {
            otpInputs[i].value = value;
          }
        });

        otpInputs[otpInputs.length - 1].focus();
      });
    });
  }

  // ========== OTP Timer ==========

  const otpTimer = document.getElementById('otpTimer');
  if (otpTimer) {
    let timeRemaining = 300; // 5 minutes

    const timerInterval = setInterval(() => {
      timeRemaining--;

      if (timeRemaining >= 0) {
        otpTimer.textContent = `Code expires in ${formatTime(timeRemaining)}`;
      }

      if (timeRemaining === 0) {
        clearInterval(timerInterval);
        otpTimer.textContent = 'Code has expired. Please request a new one.';
        otpTimer.style.color = '#E74C3C';

        // Disable OTP inputs
        otpInputs.forEach(input => {
          input.disabled = true;
        });
      }
    }, 1000);
  }

  // ========== Resend Button Debounce ==========

  const resendButtons = document.querySelectorAll('.btn-resend');
  resendButtons.forEach(button => {
    button.addEventListener('click', function (e) {
      e.preventDefault();

      if (this.disabled) return;

      this.disabled = true;
      let timeRemaining = 60;
      this.textContent = `Resend in ${timeRemaining}s`;

      const interval = setInterval(() => {
        timeRemaining--;
        this.textContent = `Resend in ${timeRemaining}s`;

        if (timeRemaining === 0) {
          clearInterval(interval);
          this.disabled = false;
          this.textContent = 'Resend Code';
        }
      }, 1000);

      // Actually send the resend request
      const originalClick = this.onclick;
      this.onclick = null;
      fetch(this.href || this.dataset.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showAlert('Code resent successfully!', 'success');
          } else {
            showAlert(data.message || 'Failed to resend code', 'error');
            this.disabled = false;
            this.textContent = 'Resend Code';
            clearInterval(interval);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('An error occurred', 'error');
          this.disabled = false;
          this.textContent = 'Resend Code';
          clearInterval(interval);
        });
    });
  });

  // ========== Mobile Menu Toggle ==========

  const menuToggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('nav');

  if (menuToggle && nav) {
    menuToggle.addEventListener('click', function () {
      nav.classList.toggle('active');
    });

    // Close menu when a link is clicked
    const navLinks = nav.querySelectorAll('a');
    navLinks.forEach(link => {
      link.addEventListener('click', function () {
        nav.classList.remove('active');
      });
    });
  }

  // ========== Smooth Scroll for Anchor Links ==========

  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  // ========== Global Logout Confirmation ==========

  // Initialize logout confirmation for any page that has the modal
  function initLogoutConfirmation() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const closeLogoutModal = document.getElementById('closeLogoutModal');
    const cancelLogout = document.getElementById('cancelLogout');
    const confirmLogout = document.getElementById('confirmLogout');

    if (logoutBtn && logoutModal) {
      // Show modal when logout is clicked
      logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        logoutModal.classList.add('active');
      });

      // Hide modal functions
      function hideLogoutModal() {
        logoutModal.classList.remove('active');
      }

      // Close modal events
      if (closeLogoutModal) closeLogoutModal.addEventListener('click', hideLogoutModal);
      if (cancelLogout) cancelLogout.addEventListener('click', hideLogoutModal);

      // Close modal when clicking outside
      logoutModal.addEventListener('click', function(e) {
        if (e.target === logoutModal) {
          hideLogoutModal();
        }
      });

      // Confirm logout
      if (confirmLogout) {
        confirmLogout.addEventListener('click', function() {
          window.location.href = '/LechGo_Final/public/logout';
        });
      }

      // Close modal with Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
          hideLogoutModal();
        }
      });
    }
  }

  // Initialize logout confirmation
  initLogoutConfirmation();
});
