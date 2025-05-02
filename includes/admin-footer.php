</div> <!-- End of admin-container -->
    
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeConfirmModal">&times;</span>
            <h2 id="confirmTitle">Konfirmasi</h2>
            <p id="confirmMessage">Apakah Anda yakin ingin melakukan tindakan ini?</p>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="cancelAction">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmAction">Ya, Lakukan</button>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toastNotification" class="toast">
        <div class="toast-content">
            <div class="toast-icon">✓</div>
            <div class="toast-message">Operasi berhasil dilakukan</div>
        </div>
        <div class="toast-progress">
            <div class="progress-bar"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu toggle functionality for mobile
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
            }
            
            // Notification dropdown functionality
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (notificationBtn && notificationDropdown) {
                notificationBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                    
                    // Close other dropdowns
                    if (userDropdown && userDropdown.classList.contains('show')) {
                        userDropdown.classList.remove('show');
                    }
                });
            }
            
            // User dropdown functionality
            const userBtn = document.getElementById('userBtn');
            const userDropdown = document.getElementById('userDropdown');
            
            if (userBtn && userDropdown) {
                userBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                    
                    // Close other dropdowns
                    if (notificationDropdown && notificationDropdown.classList.contains('show')) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationDropdown && !notificationBtn.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
                
                if (userDropdown && !userBtn.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
            
            // Confirmation modal functionality
            const confirmationModal = document.getElementById('confirmationModal');
            const closeConfirmModal = document.getElementById('closeConfirmModal');
            const cancelAction = document.getElementById('cancelAction');
            const confirmAction = document.getElementById('confirmAction');
            
            if (confirmationModal && closeConfirmModal) {
                closeConfirmModal.addEventListener('click', function() {
                    confirmationModal.style.display = 'none';
                });
            }
            
            if (confirmationModal && cancelAction) {
                cancelAction.addEventListener('click', function() {
                    confirmationModal.style.display = 'none';
                });
            }
            
            // Data row click functionality for tables
            const dataRows = document.querySelectorAll('.data-row');
            dataRows.forEach(row => {
                row.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    if (url) {
                        window.location.href = url;
                    }
                });
            });
            
            // Toast notification functionality
            const toastNotification = document.getElementById('toastNotification');
            
            // Function to show toast message
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
            
            // Confirmation dialog helper
            window.showConfirmation = function(title, message, callback) {
                if (!confirmationModal) return;
                
                const confirmTitle = document.getElementById('confirmTitle');
                const confirmMessage = document.getElementById('confirmMessage');
                
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
            
            // Display success message if exists in URL
            const urlParams = new URLSearchParams(window.location.search);
            const successMsg = urlParams.get('success');
            const errorMsg = urlParams.get('error');
            
            if (successMsg) {
                window.showToast(decodeURIComponent(successMsg), 'success');
            }
            
            if (errorMsg) {
                window.showToast(decodeURIComponent(errorMsg), 'error');
            }
        });
    </script>
</body>
</html>