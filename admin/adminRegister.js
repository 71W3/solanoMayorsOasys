// Admin Registration JavaScript
document.addEventListener('DOMContentLoaded', function() {
    
    // Enhanced password toggle functionality
    const passwordToggles = document.querySelectorAll('.toggle-password');
    
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Toggle icon
            this.classList.toggle('bi-eye-slash');
            this.classList.toggle('bi-eye');
            
            // Add animation
            this.style.transform = 'scale(1.2)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 200);
        });
    });
    
    // Enhanced password strength checker with animations
    const passwordInput = document.getElementById('registerPassword');
    const strengthMeter = document.getElementById('strengthMeter');
    const strengthText = document.getElementById('strengthText');
    
    if (passwordInput && strengthMeter && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) strength += 1;
            
            // Check for lowercase letters
            if (/[a-z]/.test(password)) strength += 1;
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) strength += 1;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength += 1;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength meter with animation
            strengthMeter.className = 'strength-meter';
            if (strength > 0) {
                strengthMeter.classList.add('strength-' + (strength - 1));
            }
            
            // Update text with color transition
            const strengthLabels = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
            const strengthColors = ['#dc2626', '#dc2626', '#ffc107', '#ffc107', '#059669'];
            
            if (password.length > 0) {
                strengthText.textContent = 'Password strength: ' + strengthLabels[Math.max(0, strength - 1)];
                strengthText.style.color = strengthColors[Math.max(0, strength - 1)];
                
                // Add pulse animation for strong passwords
                if (strength >= 4) {
                    strengthText.style.animation = 'pulse 2s infinite';
                } else {
                    strengthText.style.animation = 'none';
                }
            } else {
                strengthText.textContent = 'Password strength: weak';
                strengthText.style.color = 'var(--text-light)';
                strengthText.style.animation = 'none';
            }
        });
    }
    
    // Enhanced real-time password match feedback with animations
    const registerPassword = document.getElementById('registerPassword');
    const registerConfirmPassword = document.getElementById('registerConfirmPassword');
    const confirmPasswordFeedback = document.getElementById('confirmPasswordFeedback');

    function checkPasswordMatch() {
        if (!registerConfirmPassword.value) {
            confirmPasswordFeedback.textContent = '';
            confirmPasswordFeedback.style.color = 'var(--text-light)';
            return;
        }
        
        if (registerPassword.value === registerConfirmPassword.value) {
            confirmPasswordFeedback.textContent = '✓ Passwords match!';
            confirmPasswordFeedback.style.color = '#059669';
            confirmPasswordFeedback.style.animation = 'bounce 0.6s ease';
        } else {
            confirmPasswordFeedback.textContent = '✗ Passwords do not match.';
            confirmPasswordFeedback.style.color = '#dc2626';
            confirmPasswordFeedback.style.animation = 'shake 0.6s ease';
        }
    }
    
    if (registerPassword && registerConfirmPassword && confirmPasswordFeedback) {
        registerPassword.addEventListener('input', checkPasswordMatch);
        registerConfirmPassword.addEventListener('input', checkPasswordMatch);
    }
    
    // Enhanced form submission handler
    const registerSubmitBtn = document.getElementById('registerSubmit');
    const form = document.querySelector('form');

    if (registerSubmitBtn && form) {
        registerSubmitBtn.addEventListener('click', function(e) {
            // Prevent default if form validation fails
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }
            
            // Show loading state with animation
            this.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Creating admin account...';
            this.disabled = true;
            this.classList.add('loading');
            
            // Add success animation after a delay (simulating processing)
            setTimeout(() => {
                this.innerHTML = '<i class="bi bi-check-circle"></i> Account Created!';
                this.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
            }, 2000);
        });
    }
    
    // Enhanced alert auto-hide functionality
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Auto-hide success/error alerts after 5 seconds
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
            setTimeout(() => {
                alert.classList.add('fade-out');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }, 5000);
            
            // Also hide on click
            alert.addEventListener('click', function() {
                this.classList.add('fade-out');
                setTimeout(() => {
                    if (this.parentNode) {
                        this.remove();
                    }
                }, 500);
            });
        }
    });
    
    // Add subtle hover effects to form elements
    const formControls = document.querySelectorAll('.form-control, .form-select');
    formControls.forEach(control => {
        control.addEventListener('mouseenter', function() {
            if (!this.matches(':focus')) {
                this.style.borderColor = '#cbd5e1';
                this.style.transform = 'translateY(-1px)';
            }
        });
        
        control.addEventListener('mouseleave', function() {
            if (!this.matches(':focus')) {
                this.style.borderColor = 'var(--border)';
                this.style.transform = 'translateY(0)';
            }
        });
        
        // Add focus effects
        control.addEventListener('focus', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(0, 85, 164, 0.15)';
        });
        
        control.addEventListener('blur', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Role selection enhancement
    const roleSelect = document.querySelector('select[name="role"]');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value;
            const submitBtn = document.getElementById('registerSubmit');
            
            if (selectedRole) {
                // Update button text based on role
                const roleText = selectedRole.charAt(0).toUpperCase() + selectedRole.slice(1);
                submitBtn.innerHTML = `<i class="bi bi-person-plus me-2"></i>Create ${roleText} Account`;
                
                // Add role-specific styling
                submitBtn.style.background = 'linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%)';
                
                // Add success animation
                submitBtn.style.animation = 'pulse 1s ease';
                setTimeout(() => {
                    submitBtn.style.animation = 'none';
                }, 1000);
            }
        });
    }
    
    // Form validation enhancement
    const inputs = document.querySelectorAll('input[required], select[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.checkValidity()) {
                this.style.borderColor = '#28a745';
                this.style.background = '#f8fff9';
            } else {
                this.style.borderColor = '#dc3545';
                this.style.background = '#fff8f8';
            }
        });
        
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.style.borderColor = 'var(--border)';
                this.style.background = 'white';
            }
        });
    });
    
    // Add smooth scrolling for better UX
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add keyboard navigation support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            const nextInput = e.target.parentElement.nextElementSibling?.querySelector('input, select');
            if (nextInput) {
                nextInput.focus();
                e.preventDefault();
            }
        }
    });
    
    // Add form auto-save functionality (localStorage)
    const formData = {};
    
    function saveFormData() {
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name && input.value) {
                formData[input.name] = input.value;
            }
        });
        localStorage.setItem('adminRegistrationForm', JSON.stringify(formData));
    }
    
    function loadFormData() {
        const saved = localStorage.getItem('adminRegistrationForm');
        if (saved) {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = data[key];
                    // Trigger validation
                    input.dispatchEvent(new Event('input'));
                }
            });
        }
    }
    
    // Auto-save every 2 seconds
    setInterval(saveFormData, 2000);
    
    // Load saved data on page load
    loadFormData();
    
    // Clear saved data on successful submission
    if (form) {
        form.addEventListener('submit', function() {
            localStorage.removeItem('adminRegistrationForm');
        });
    }
});

// CSS Animations
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    @keyframes bounce {
        0%, 20%, 53%, 80%, 100% { transform: translate3d(0,0,0); }
        40%, 43% { transform: translate3d(0,-8px,0); }
        70% { transform: translate3d(0,-4px,0); }
        90% { transform: translate3d(0,-2px,0); }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    .form-control:valid {
        border-color: #28a745 !important;
        background: #f8fff9 !important;
    }
    
    .form-control:invalid:not(:placeholder-shown) {
        border-color: #dc3545 !important;
        background: #fff8f8 !important;
    }
    
    .form-select:valid {
        border-color: #28a745 !important;
        background: #f8fff9 !important;
    }
    
    .form-select:invalid:not([value=""]) {
        border-color: #dc3545 !important;
        background: #fff8f8 !important;
    }
`;
document.head.appendChild(style);
