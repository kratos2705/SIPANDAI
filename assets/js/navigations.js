    // Add the new navigation links to the menu
    document.addEventListener('DOMContentLoaded', function() {
        const navMenu = document.querySelector('.nav-menu');
        
        // Create and add the Laporan link
        const laporanLink = document.createElement('li');
        laporanLink.innerHTML = '<a href="#laporan-page" class="nav-link">Laporan</a>';
        navMenu.appendChild(laporanLink);
        
        // Create and add the Pencarian Warga link
        const searchWargaLink = document.createElement('li');
        searchWargaLink.innerHTML = '<a href="#search-warga-page" class="nav-link">Data Warga</a>';
        navMenu.appendChild(searchWargaLink);
        
        // Update the navigation functionality to include the new pages
        const navLinks = document.querySelectorAll('.nav-link');
        const pages = document.querySelectorAll('main');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links
                navLinks.forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Hide all pages
                pages.forEach(page => page.style.display = 'none');
                
                // Show targeted page
                const targetPage = document.querySelector(this.getAttribute('href'));
                if (targetPage) {
                    targetPage.style.display = 'block';
                }
            });
        });

        // Report page functionality
        if (document.getElementById('cetak-laporan-btn')) {
            document.getElementById('cetak-laporan-btn').addEventListener('click', function() {
                alert('Mencetak laporan administrasi...');
            });
        }
        
        if (document.getElementById('ekspor-excel-btn')) {
            document.getElementById('ekspor-excel-btn').addEventListener('click', function() {
                alert('Mengekspor data ke format Excel...');
            });
        }
        
        // Resident search page functionality
        if (document.querySelector('.search-button')) {
            document.querySelector('.search-button').addEventListener('click', function() {
                const searchValue = document.querySelector('.search-input').value;
                if (searchValue) {
                    alert('Mencari data warga dengan kata kunci: ' + searchValue);
                } else {
                    alert('Silakan masukkan kata kunci pencarian terlebih dahulu.');
                }
            });
        }
        
        if (document.querySelectorAll('.tab')) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    alert('Menampilkan tab: ' + this.textContent);
                });
            });
        }
        
        if (document.querySelectorAll('.view-btn')) {
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const row = this.closest('tr');
                    const name = row.cells[1].textContent;
                    
                    alert('Menampilkan detail warga: ' + name);
                    
                    // Show profile section (in a real app, this would load the specific person's data)
                    document.querySelector('.warga-profile').style.display = 'flex';
                });
            });
        }
        
        if (document.getElementById('cetak-data-btn')) {
            document.getElementById('cetak-data-btn').addEventListener('click', function() {
                alert('Mencetak data warga...');
            });
        }
        
        if (document.getElementById('ajax-layanan-btn')) {
            document.getElementById('ajax-layanan-btn').addEventListener('click', function() {
                alert('Mengalihkan ke halaman pengajuan layanan baru...');
                // In a real app, we would navigate to the layanan-page
                document.querySelector('a[href="#layanan-page"]').click();
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Main navigation menu functionality
        const navLinks = document.querySelectorAll('.nav-menu .nav-link');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Don't prevent default for links with full URLs
                if (this.getAttribute('href').includes('.php')) {
                    return true; // Allow the browser to navigate to the URL
                }
                
                e.preventDefault();
                
                // Remove active class from all links
                navLinks.forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // If link is to a section on the same page, scroll to it
                const targetId = this.getAttribute('href');
                if (targetId.startsWith('#')) {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });
    
        // Login modal functionality
        const loginBtn = document.getElementById('loginBtn');
        const loginModal = document.getElementById('loginModal');
        const closeModal = document.getElementById('closeModal');
        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
    
        if (loginBtn && loginModal) {
            loginBtn.addEventListener('click', function() {
                loginModal.style.display = 'block';
            });
        }
    
        if (closeModal && loginModal) {
            closeModal.addEventListener('click', function() {
                loginModal.style.display = 'none';
            });
    
            window.addEventListener('click', function(event) {
                if (event.target == loginModal) {
                    loginModal.style.display = 'none';
                }
            });
        }
    
        if (loginTab && registerTab && loginForm && registerForm) {
            loginTab.addEventListener('click', function() {
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginForm.classList.add('active');
                registerForm.classList.remove('active');
            });
    
            registerTab.addEventListener('click', function() {
                registerTab.classList.add('active');
                loginTab.classList.remove('active');
                registerForm.classList.add('active');
                loginForm.classList.remove('active');
            });
        }
    
        // User dropdown menu functionality
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');
    
        if (userBtn && userDropdown) {
            userBtn.addEventListener('click', function() {
                userDropdown.classList.toggle('show');
            });
    
            // Close dropdown when clicking outside
            window.addEventListener('click', function(event) {
                if (!event.target.matches('.user-btn') && !event.target.parentNode.matches('.user-btn')) {
                    if (userDropdown.classList.contains('show')) {
                        userDropdown.classList.remove('show');
                    }
                }
            });
        }
    
        // Payment modal functionality
        const paymentModal = document.getElementById('paymentModal');
        const bayarBtn = document.getElementById('bayarBtn');
        const closePaymentModal = document.querySelector('#paymentModal .close');
        const cancelBtn = document.getElementById('cancelBtn');
    
        if (bayarBtn && paymentModal) {
            bayarBtn.addEventListener('click', function() {
                paymentModal.style.display = 'block';
            });
        }
    
        if (closePaymentModal && paymentModal) {
            closePaymentModal.addEventListener('click', function() {
                paymentModal.style.display = 'none';
            });
        }
    
        if (cancelBtn && paymentModal) {
            cancelBtn.addEventListener('click', function() {
                paymentModal.style.display = 'none';
            });
        }
    
        if (paymentModal) {
            window.addEventListener('click', function(event) {
                if (event.target == paymentModal) {
                    paymentModal.style.display = 'none';
                }
            });
        }
    });