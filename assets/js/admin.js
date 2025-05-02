/**
 * SIPANDAI Admin Dashboard JavaScript
 * This file handles all interactive functionality for the admin dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    const closeSidebar = document.getElementById('closeSidebar');
    
    if (menuToggle && adminSidebar) {
        menuToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('show');
        });
    }
    
    if (closeSidebar && adminSidebar) {
        closeSidebar.addEventListener('click', function() {
            adminSidebar.classList.remove('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                !adminSidebar.contains(e.target) && 
                !menuToggle.contains(e.target) && 
                adminSidebar.classList.contains('show')) {
                adminSidebar.classList.remove('show');
            }
        });
    }

    // Notification dropdown
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            
            // Close user dropdown if open
            if (userDropdown && userDropdown.classList.contains('show')) {
                userDropdown.classList.remove('show');
            }
        });
    }
    
    // User dropdown
    const userBtn = document.getElementById('userBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userBtn && userDropdown) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
            
            // Close notification dropdown if open
            if (notificationDropdown && notificationDropdown.classList.contains('show')) {
                notificationDropdown.classList.remove('show');
            }
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (notificationDropdown && !notificationBtn.contains(e.target) && notificationDropdown.classList.contains('show')) {
            notificationDropdown.classList.remove('show');
        }
        
        if (userDropdown && !userBtn.contains(e.target) && userDropdown.classList.contains('show')) {
            userDropdown.classList.remove('show');
        }
    });

    // Table row click functionality
    const dataRows = document.querySelectorAll('.data-row');
    dataRows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button or link inside the row
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
                e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            
            const url = this.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });
    });

    // Modal functionality
    const confirmationModal = document.getElementById('confirmationModal');
    const closeConfirmModal = document.getElementById('closeConfirmModal');
    const cancelAction = document.getElementById('cancelAction');
    
    if (confirmationModal) {
        if (closeConfirmModal) {
            closeConfirmModal.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
            });
        }
        
        if (cancelAction) {
            cancelAction.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
            });
        }
        
        // Close modal when clicking outside of it
        window.addEventListener('click', function(e) {
            if (e.target == confirmationModal) {
                confirmationModal.style.display = 'none';
            }
        });
    }

    // Toast notification functionality
    const toastNotification = document.getElementById('toastNotification');
    
    // Global function to show toast message
    window.showToast = function(message, type = 'success') {
        if (!toastNotification) return;
        
        const toastIcon = toastNotification.querySelector('.toast-icon');
        const toastMessage = toastNotification.querySelector('.toast-message');
        const progressBar = toastNotification.querySelector('.progress-bar');
        
        // Set message
        toastMessage.textContent = message;
        
        // Set icon and class based on type
        toastNotification.className = 'toast toast-' + type;
        
        if (type === 'success') {
            toastIcon.textContent = '✓';
        } else if (type === 'error') {
            toastIcon.textContent = '✕';
        } else if (type === 'warning') {
            toastIcon.textContent = '⚠';
        } else if (type === 'info') {
            toastIcon.textContent = 'ℹ';
        }
        
        // Show toast
        toastNotification.classList.add('show');
        
        // Reset and start progress bar
        progressBar.style.width = '0%';
        
        // Animate progress bar
        let width = 0;
        const interval = setInterval(function() {
            if (width >= 100) {
                clearInterval(interval);
                toastNotification.classList.remove('show');
            } else {
                width++;
                progressBar.style.width = width + '%';
            }
        }, 30); // 3 seconds total duration
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            toastNotification.classList.remove('show');
        }, 3000);
    };
    
    // Global function to show confirmation dialog
    window.showConfirmation = function(title, message, callback) {
        if (!confirmationModal) return;
        
        const confirmTitle = document.getElementById('confirmTitle');
        const confirmMessage = document.getElementById('confirmMessage');
        const confirmAction = document.getElementById('confirmAction');
        
        confirmTitle.textContent = title;
        confirmMessage.textContent = message;
        
        confirmationModal.style.display = 'block';
        
        // Set new click handler for confirmation button
        if (confirmAction) {
            // Remove existing handlers
            const newConfirmAction = confirmAction.cloneNode(true);
            confirmAction.parentNode.replaceChild(newConfirmAction, confirmAction);
            
            // Add new handler
            newConfirmAction.addEventListener('click', function() {
                callback();
                confirmationModal.style.display = 'none';
            });
        }
    };

    // Data filters functionality
    const filterInputs = document.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const filterBtn = this.closest('.filter-group').querySelector('.filter-btn');
                if (filterBtn) {
                    filterBtn.click();
                }
            }
        });
    });

    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filterForm = this.closest('form');
            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    const clearFilterBtns = document.querySelectorAll('.clear-filter-btn');
    clearFilterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filterForm = this.closest('form');
            if (filterForm) {
                const inputs = filterForm.querySelectorAll('input[type="text"], input[type="date"], select');
                inputs.forEach(input => {
                    input.value = '';
                });
                filterForm.submit();
            }
        });
    });

    // Bulk action functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionVisibility();
        });
        
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionVisibility);
        });
    }

    function updateBulkActionVisibility() {
        const bulkActionContainer = document.querySelector('.bulk-actions');
        if (!bulkActionContainer) return;
        
        const checkedItems = document.querySelectorAll('.item-checkbox:checked');
        
        if (checkedItems.length > 0) {
            bulkActionContainer.classList.add('show');
            const itemCount = bulkActionContainer.querySelector('.item-count');
            if (itemCount) {
                itemCount.textContent = checkedItems.length;
            }
        } else {
            bulkActionContainer.classList.remove('show');
        }
    }

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            form.classList.add('was-validated');
            
            // Show error messages for invalid fields
            const invalidFields = form.querySelectorAll(':invalid');
            invalidFields.forEach(field => {
                const feedbackEl = field.nextElementSibling;
                if (feedbackEl && feedbackEl.classList.contains('invalid-feedback')) {
                    feedbackEl.style.display = 'block';
                }
            });
        });
    });

    // File input preview for image uploads
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.value.split('\\').pop();
            const label = this.nextElementSibling;
            
            if (label && fileName) {
                label.textContent = fileName;
            }
            
            // Image preview
            const previewElement = document.getElementById(this.getAttribute('data-preview-id'));
            if (previewElement && this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewElement.src = e.target.result;
                    previewElement.style.display = 'block';
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // Initialize date pickers if any
    const datePickers = document.querySelectorAll('.date-picker');
    if (datePickers.length > 0 && typeof flatpickr !== 'undefined') {
        datePickers.forEach(picker => {
            flatpickr(picker, {
                dateFormat: "Y-m-d",
                locale: "id"
            });
        });
    }

    // Show success or error messages from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const successMsg = urlParams.get('success');
    const errorMsg = urlParams.get('error');
    
    if (successMsg && window.showToast) {
        window.showToast(decodeURIComponent(successMsg), 'success');
        
        // Clean URL by removing the success parameter
        const url = new URL(window.location);
        url.searchParams.delete('success');
        window.history.replaceState({}, '', url);
    }
    
    if (errorMsg && window.showToast) {
        window.showToast(decodeURIComponent(errorMsg), 'error');
        
        // Clean URL by removing the error parameter
        const url = new URL(window.location);
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    }
});