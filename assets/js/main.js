// ============================================
// DOM Ready Function
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initMobileSidebar();
    initModals();
    initFormValidation();
    initFilePreviews();
    initAutoHideAlerts();
    initSearchFilters();
    initConfirmDialogs();
});

// ============================================
// Mobile Sidebar Functions
// ============================================
function initMobileSidebar() {
    // Create overlay if not exists
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    // Check if menu toggle exists, if not add it
    const topHeader = document.querySelector('.top-header');
    if (topHeader && !document.querySelector('.menu-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'menu-toggle';
        toggleBtn.innerHTML = '☰';
        toggleBtn.setAttribute('aria-label', 'Toggle Menu');
        toggleBtn.onclick = toggleSidebar;
        topHeader.insertBefore(toggleBtn, topHeader.firstChild);
    }
    
    // Close sidebar when clicking overlay
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.onclick = closeSidebar;
    }
    
    // Close sidebar on window resize if desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
    if (overlay) {
        overlay.classList.toggle('active');
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.remove('active');
    }
    if (overlay) {
        overlay.classList.remove('active');
    }
}

// Close sidebar when clicking a link on mobile
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
        const link = e.target.closest('.sidebar-nav a');
        if (link) {
            setTimeout(closeSidebar, 150);
        }
    }
});

// ============================================
// Loading State Functions
// ============================================
function showLoading(btn) {
    if (!btn) return null;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading"></span> Loading...';
    return originalText;
}

function hideLoading(btn, originalText) {
    if (!btn) return;
    btn.disabled = false;
    if (originalText) {
        btn.innerHTML = originalText;
    }
}

// ============================================
// Modal Functions
// ============================================
function initModals() {
    // Close modal when clicking close button
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.onclick = function() {
            const modal = this.closest('.modal');
            if (modal) closeModal(modal.id);
        };
    });
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.onclick = function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        };
    });
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal.id);
            });
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus first input if exists
        const firstInput = modal.querySelector('input, textarea, select');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ============================================
// Form Validation
// ============================================
function initFormValidation() {
    // Real-time validation on input
    document.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = '#d1d5db';
            }
        });
        
        input.addEventListener('blur', function() {
            if (!this.value.trim() && this.hasAttribute('required')) {
                this.style.borderColor = '#ef4444';
            }
        });
    });
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        const value = input.value.trim();
        if (!value) {
            input.style.borderColor = '#ef4444';
            isValid = false;
            
            // Show error message
            let errorDiv = input.parentElement.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('small');
                errorDiv.className = 'error-message';
                errorDiv.style.color = '#ef4444';
                errorDiv.style.fontSize = '0.75rem';
                errorDiv.style.marginTop = '0.25rem';
                errorDiv.style.display = 'block';
                input.parentElement.appendChild(errorDiv);
            }
            errorDiv.textContent = 'This field is required';
        } else {
            input.style.borderColor = '#d1d5db';
            const errorDiv = input.parentElement.querySelector('.error-message');
            if (errorDiv) errorDiv.remove();
        }
    });
    
    // Email validation
    const emailInput = form.querySelector('input[type="email"]');
    if (emailInput && emailInput.value.trim()) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value.trim())) {
            emailInput.style.borderColor = '#ef4444';
            isValid = false;
            showAlert('Please enter a valid email address', 'danger');
        }
    }
    
    // Phone validation
    const phoneInput = form.querySelector('input[name="phone"]');
    if (phoneInput && phoneInput.value.trim()) {
        const phoneRegex = /^[0-9+\-\s()]{10,15}$/;
        if (!phoneRegex.test(phoneInput.value.trim())) {
            phoneInput.style.borderColor = '#ef4444';
            isValid = false;
            showAlert('Please enter a valid phone number', 'danger');
        }
    }
    
    // Password confirmation
    const password = form.querySelector('input[name="password"]');
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    if (password && confirmPassword) {
        if (password.value !== confirmPassword.value) {
            confirmPassword.style.borderColor = '#ef4444';
            isValid = false;
            showAlert('Passwords do not match!', 'danger');
        }
    }
    
    // CNIC validation (Pakistan format)
    const cnicInput = form.querySelector('input[name="cnic"]');
    if (cnicInput && cnicInput.value.trim()) {
        const cnicRegex = /^[0-9]{5}-[0-9]{7}-[0-9]$/;
        if (!cnicRegex.test(cnicInput.value.trim())) {
            cnicInput.style.borderColor = '#ef4444';
            isValid = false;
            showAlert('Please enter valid CNIC (format: 12345-1234567-1)', 'danger');
        }
    }
    
    return isValid;
}

// ============================================
// AJAX Functions
// ============================================
async function submitFormAjax(formId, url, onSuccess) {
    const form = document.getElementById(formId);
    if (!validateForm(formId)) return false;
    
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = showLoading(submitBtn);
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            if (onSuccess) onSuccess(data);
            if (form) form.reset();
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'danger');
    } finally {
        hideLoading(submitBtn, originalText);
    }
}

// ============================================
// File Preview Functions
// ============================================
function initFilePreviews() {
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            previewFile(this, previewId);
        });
    });
}

function previewFile(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (input.files[0].type.startsWith('image/')) {
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; border-radius: 8px;">';
                }
                preview.style.display = 'block';
            } else {
                preview.innerHTML = '<p>File: ' + input.files[0].name + '</p>';
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ============================================
// Alert Functions
// ============================================
function showAlert(message, type) {
    type = type || 'success';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.innerHTML = message;
    
    // Find content wrapper or use body
    const container = document.querySelector('.content-wrapper');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    } else {
        document.body.insertBefore(alertDiv, document.body.firstChild);
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transition = 'opacity 0.3s';
        setTimeout(() => {
            if (alertDiv.parentNode) alertDiv.remove();
        }, 300);
    }, 5000);
}

function initAutoHideAlerts() {
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        }, 5000);
    });
}

// ============================================
// Search & Filter Functions
// ============================================
function initSearchFilters() {
    // Search input handler
    document.querySelectorAll('[data-search]').forEach(input => {
        input.addEventListener('keyup', function() {
            const tableId = this.getAttribute('data-search');
            filterTable(tableId, this.value);
        });
    });
}

function filterTable(tableId, searchText) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const filter = searchText.toUpperCase();
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        let txtValue = '';
        const cells = rows[i].getElementsByTagName('td');
        
        for (let j = 0; j < cells.length; j++) {
            txtValue += cells[j].textContent || cells[j].innerText;
        }
        
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
}

// ============================================
// Delete Confirmation
// ============================================
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

function confirmDelete(url, message) {
    message = message || 'Are you sure you want to delete this item?';
    if (confirm(message)) {
        window.location.href = url;
    }
}

// ============================================
// Print Function
// ============================================
function printPage() {
    window.print();
}

// ============================================
// Copy to Clipboard
// ============================================
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showAlert('Copied to clipboard!', 'success');
    }).catch(function() {
        showAlert('Failed to copy', 'danger');
    });
}

// ============================================
// Password Strength Checker
// ============================================
function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColor = ['#ef4444', '#f59e0b', '#f59e0b', '#10b981', '#10b981'];
    
    return {
        score: strength,
        text: strengthText[strength - 1] || strengthText[0],
        color: strengthColor[strength - 1] || strengthColor[0]
    };
}

// ============================================
// Dynamic Year for Footer
// ============================================
function setCurrentYear() {
    const yearElements = document.querySelectorAll('.current-year');
    const currentYear = new Date().getFullYear();
    yearElements.forEach(el => {
        el.textContent = currentYear;
    });
}

// Call on load
setCurrentYear();

// ============================================
// Touch Swipe for Mobile Sidebar
// ============================================
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const swipeDistance = touchEndX - touchStartX;
    
    // Swipe right to open sidebar (on mobile)
    if (swipeDistance > 100 && window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && !sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    }
    
    // Swipe left to close sidebar
    if (swipeDistance < -100 && window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    }
}