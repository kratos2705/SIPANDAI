document.addEventListener('DOMContentLoaded', function() {
    const dokumenForm = document.getElementById('dokumenForm');
    const fileInput = document.getElementById('dokumen_pendukung');
    const selectedFiles = document.getElementById('selected-files');
    
    // Display selected file names
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                let fileNames = [];
                for (let i = 0; i < fileInput.files.length; i++) {
                    fileNames.push(fileInput.files[i].name);
                }
                selectedFiles.textContent = fileNames.join(', ');
            } else {
                selectedFiles.textContent = 'Belum ada file dipilih';
            }
        });
    }
    
    // Form submission handler
    if (dokumenForm) {
        dokumenForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = dokumenForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            submitBtn.textContent = 'Mengirim...';
            submitBtn.disabled = true;
            
            // Create FormData object
            const formData = new FormData(dokumenForm);
            
            // Send AJAX request
            fetch('proses_pengajuan.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                submitBtn.textContent = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.status) {
                    // Success - show success message and redirect
                    showAlert('success', data.message);
                    
                    // Reset form
                    dokumenForm.reset();
                    selectedFiles.textContent = 'Belum ada file dipilih';
                    
                    // Redirect to status page after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'status-pengajuan.html?id=' + data.pengajuan_id;
                    }, 2000);
                } else {
                    // Error - show error message
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                // Reset button state
                submitBtn.textContent = originalBtnText;
                submitBtn.disabled = false;
                
                // Show error message
                showAlert('error', 'Terjadi kesalahan: ' + error.message);
                console.error('Error:', error);
            });
        });
    }
    
    // Function to show alert messages
    function showAlert(type, message) {
        // Check if alert container exists, if not create it
        let alertContainer = document.querySelector('.alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.className = 'alert-container';
            document.body.appendChild(alertContainer);
        }
        
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <span class="alert-message">${message}</span>
            <button class="alert-close">&times;</button>
        `;
        
        // Add alert to container
        alertContainer.appendChild(alert);
        
        // Add close button functionality
        const closeBtn = alert.querySelector('.alert-close');
        closeBtn.addEventListener('click', function() {
            alert.remove();
        });
        
        // Auto close after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
});