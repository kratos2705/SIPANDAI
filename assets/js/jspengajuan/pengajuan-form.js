document.addEventListener('DOMContentLoaded', function() {
    const dokumenForm = document.getElementById('dokumenForm');
    const dokumenInput = document.getElementById('dokumen_pendukung');
    const selectedFilesDisplay = document.getElementById('selected-files');
    
    // Form validation
    dokumenForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Reset previous validation
        const errorElements = document.querySelectorAll('.error-message');
        errorElements.forEach(el => el.remove());
        
        // Validate fields
        let valid = true;
        
        // Check required fields
        const requiredFields = dokumenForm.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                showError(field, 'Kolom ini wajib diisi');
                valid = false;
            }
        });
        
        // Validate NIK
        const nikField = document.getElementById('nik');
        if (nikField.value.trim() && !validateNIK(nikField.value)) {
            showError(nikField, 'NIK harus terdiri dari 16 digit angka');
            valid = false;
        }
        
        // Validate phone number
        const teleponField = document.getElementById('telepon');
        if (teleponField.value.trim() && !validatePhoneNumber(teleponField.value)) {
            showError(teleponField, 'Format nomor telepon tidak valid');
            valid = false;
        }
        
        // Validate email if provided
        const emailField = document.getElementById('email');
        if (emailField.value.trim() && !validateEmail(emailField.value)) {
            showError(emailField, 'Format email tidak valid');
            valid = false;
        }
        
        // Validate document upload
        if (dokumenInput.files.length === 0) {
            showError(dokumenInput.parentElement, 'Dokumen pendukung wajib diunggah');
            valid = false;
        }
        
        // If form is valid, submit it
        if (valid) {
            // Show loading
            const submitBtn = dokumenForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Memproses...';
            
            // Submit the form
            dokumenForm.submit();
        } else {
            // Scroll to first error
            const firstError = document.querySelector('.error-message');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
    
    // File upload preview
    dokumenInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const fileList = Array.from(this.files)
                .map(file => {
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                    const valid = validTypes.includes(file.type);
                    
                    // Validate file size (max 5MB)
                    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                    const validSize = file.size <= maxSize;
                    
                    return `<li class="${!valid || !validSize ? 'invalid' : ''}">
                        ${file.name} (${formatFileSize(file.size)})
                        ${!valid ? '<span class="error">Format file tidak valid</span>' : ''}
                        ${!validSize ? '<span class="error">Ukuran file melebihi 5MB</span>' : ''}
                    </li>`;
                })
                .join('');
            
            selectedFilesDisplay.innerHTML = `<ul class="file-list">${fileList}</ul>`;
        } else {
            selectedFilesDisplay.innerHTML = 'Belum ada file dipilih';
        }
    });
    
    // Utility functions
    function showError(field, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = message;
        
        field.classList.add('invalid');
        field.parentElement.appendChild(errorElement);
    }
    
    function validateNIK(nik) {
        const regex = /^\d{16}$/;
        return regex.test(nik);
    }
    
    function validatePhoneNumber(phone) {
        const regex = /^(0|\+62)[0-9]{9,13}$/;
        return regex.test(phone);
    }
    
    function validateEmail(email) {
        const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return regex.test(email);
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' bytes';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
        else return (bytes / 1048576).toFixed(2) + ' MB';
    }
    
    // Form tab switching
    const formTabs = document.querySelectorAll('.form-tab');
    formTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            formTabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Change form content based on tab
            const formContent = document.querySelector('.form-content');
            const tabName = this.textContent.trim();
            
            if (tabName === 'Dokumen Kependudukan') {
                // Current form is already shown
            } else if (tabName === 'Dokumen Usaha') {
                // Load form for business documents
                loadFormContent('usaha');
            } else if (tabName === 'Dokumen Lainnya') {
                // Load form for other documents
                loadFormContent('lainnya');
            }
        });
    });
    
    // Function to load different form content via AJAX
    function loadFormContent(type) {
        const formContent = document.querySelector('.form-content');
        
        // Show loading
        formContent.innerHTML = '<div class="loading-indicator">Memuat formulir...</div>';
        
        // Make AJAX request to get form content
        fetch(`get-form-content.php?type=${type}`)
            .then(response => response.text())
            .then(html => {
                formContent.innerHTML = html;
                // Reinitialize any event listeners for the new form
                initializeNewForm();
            })
            .catch(error => {
                formContent.innerHTML = `<div class="error-message">Gagal memuat formulir. Silakan coba lagi.</div>`;
                console.error('Error loading form:', error);
            });
    }
    
    function initializeNewForm() {
        // Add event listeners for the new form elements
        const newDokumenInput = document.getElementById('dokumen_pendukung');
        if (newDokumenInput) {
            newDokumenInput.addEventListener('change', handleFileChange);
        }
        
        // Other initialization code...
    }
    
    function handleFileChange() {
        // File change handler for dynamically loaded forms
        // Same logic as the original file input change handler
    }
});