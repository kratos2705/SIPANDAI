<?php
// Define base path for proper includes
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], 'user/') !== false) {
    $base_path = '../';
} elseif (strpos($_SERVER['PHP_SELF'], 'admin/') !== false) {
    $base_path = '../';
} elseif (strpos($_SERVER['PHP_SELF'], 'auth/') !== false) {
    $base_path = '../';
}
?>
<!-- Footer -->
<footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Tentang SIPANDAI</h3>
                <p>SIPANDAI (Sistem Informasi Pelayanan Administrasi Desa) adalah platform digital untuk memudahkan pelayanan administrasi desa dan meningkatkan transparansi pemerintahan desa.</p>
            </div>
            <div class="footer-section">
                <h3>Navigasi Cepat</h3>
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="layanan-pembayaran.php">Layanan</a></li>
                    <li><a href="pengajuan.php">Pengajuan</a></li>
                    <li><a href="transparansi.php">Transparansi</a></li>
                    <li><a href="berita.php">Berita & Pengumuman</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Hubungi Kami</h3>
                <p>Kantor Desa Contoh, Jl. Raya Desa No. 123, Kecamatan Contoh, Kabupaten Contoh, Provinsi Contoh</p>
                <p>Email: info@sipandai.desa.id</p>
                <p>Telp: (021) 1234-5678</p>
            </div>
            <div class="footer-section">
                <h3>Ikuti Kami</h3>
                <div class="social-icons">
                    <a href="#" class="social-icon">FB</a>
                    <a href="#" class="social-icon">IG</a>
                    <a href="#" class="social-icon">TW</a>
                    <a href="#" class="social-icon">YT</a>
                </div>
            </div>
        </div>
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> SIPANDAI - Sistem Informasi Pelayanan Administrasi Desa. Hak Cipta Dilindungi.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Login modal functionality
            const loginBtn = document.getElementById('loginBtn');
            const loginModal = document.getElementById('loginModal');
            const closeModal = document.getElementById('closeModal');
            const loginTab = document.getElementById('loginTab');
            const registerTab = document.getElementById('registerTab');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');

            if (loginBtn) {
                loginBtn.addEventListener('click', function() {
                    loginModal.style.display = 'block';
                });
            }

            closeModal.addEventListener('click', function() {
                loginModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == loginModal) {
                    loginModal.style.display = 'none';
                }
            });

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

            // User dropdown menu functionality
            const userBtn = document.getElementById('userBtn');
            const userDropdown = document.getElementById('userDropdown');

            if (userBtn) {
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
        });
    </script>
</body>
</html>