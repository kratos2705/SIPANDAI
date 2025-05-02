<?php

// Get current date
$current_date = date('l, j F Y');
// Translate to Indonesian
$hari = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$bulan = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];
$tanggal_indo = strtr(date('l, j F Y'), $hari);
$tanggal_indo = strtr($tanggal_indo, $bulan);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_name = $is_logged_in && isset($_SESSION['user_nama']) ? $_SESSION['user_nama'] : '';

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

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SIPANDAI - Sistem Informasi Pelayanan Administrasi Desa</title>
    <link href="<?php echo $base_path; ?>assets/css/styles1.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/styles2.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/styles3.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/styles4.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/styles5.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/styles6.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/alert.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/user-buttons.css" rel="stylesheet" />
    <script src="<?php echo $base_path; ?>assets/js/navigation.js" defer></script>
</head>

<body>
    <!-- Header -->
    <header>
        <div class="top-bar">
            <div class="contact-info">
                <span>Email: info@sipandai.desa.id</span>
                <span>Telp: (021) 1234-5678</span>
            </div>
            <div class="date-info">
                <?php echo $tanggal_indo; ?>
            </div>
        </div>
        <div class="nav-container">
            <div class="logo">
                <img src="<?php echo $base_path; ?>assets/img/logo5.png" alt="Sipandai Logo">
                <h1>SIPANDAI</h1>
            </div>
            <ul class="nav-menu">
                <li><a href="<?php echo $base_path; ?>index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Beranda</a></li>
                <li><a href="<?php echo $base_path; ?>user/layanan-pembayaran.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'layanan-pembayaran.php' ? 'active' : ''; ?>">Layanan Pembayaran Retibusi</a></li>
                <li><a href="<?php echo $base_path; ?>user/pengajuan.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pengajuan.php' ? 'active' : ''; ?>">Pengajuan</a></li>
                <li><a href="<?php echo $base_path; ?>user/transparansi.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transparansi.php' ? 'active' : ''; ?>">Transparansi</a></li>
                <li><a href="<?php echo $base_path; ?>user/berita.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'berita.php' ? 'active' : ''; ?>">Berita</a></li>
                <li><a href="<?php echo $base_path; ?>user/data-warga.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'data-warga.php' ? 'active' : ''; ?>">Data Warga</a></li>
            </ul>
            
            <!-- For logged in users -->
            <?php if ($is_logged_in): ?>
                <div class="user-menu">
                    <button class="user-btn" id="userBtn">
                        <span class="user-icon">👤</span>
                        <?php echo htmlspecialchars($user_name); ?> ▼
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="<?php echo $base_path; ?>user/dashboard.php" class="profile-link">Dashboard</a>
                        <a href="<?php echo $base_path; ?>user/profil.php">Profil Saya</a>
                        <a href="<?php echo $base_path; ?>user/pengajuan-saya.php">Pengajuan Saya</a>
                        <a href="<?php echo $base_path; ?>user/notifikasi.php">Notifikasi</a>
                        <a href="<?php echo $base_path; ?>auth/logout.php" class="logout-link">Keluar</a>
                    </div>
                </div>
            <?php else: ?>
                <button class="login-btn" id="loginBtn">Masuk</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Login/Register Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <span class="close" id="closeModal">&times;</span>

            <!-- Logo di dalam modal -->
            <div class="modal-logo">
                <img src="<?php echo $base_path; ?>assets/img/logo5.png" alt="Sipandai Logo">
                <h2>SIPANDAI</h2>
            </div>

            <div class="tabs">
                <div class="tab active" id="loginTab">Masuk</div>
                <div class="tab" id="registerTab">Daftar</div>
            </div>

            <!-- Login Form -->
            <div class="form-container active" id="loginForm">
                <form action="<?php echo $base_path; ?>auth/proses_login.php" method="POST">
                    <?php if (isset($_SESSION['login_error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $_SESSION['login_error']; ?>
                        </div>
                        <?php unset($_SESSION['login_error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['register_success'])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION['register_success']; ?>
                        </div>
                        <?php unset($_SESSION['register_success']); ?>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="loginEmail">Email atau Nomor HP</label>
                        <input type="text" class="form-control" id="loginEmail" name="loginEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Kata Sandi</label>
                        <input type="password" class="form-control" id="loginPassword" name="loginPassword" required>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="rememberMe" name="rememberMe">
                        <label for="rememberMe">Ingat saya</label>
                    </div>
                    <button type="submit" class="submit-btn">Masuk</button>
                    <div class="forgot-password">
                        <a href="#" id="forgotPassword">Lupa kata sandi?</a>
                    </div>
                </form>
            </div>

            <!-- Register Form -->
            <div class="form-container" id="registerForm">
                <form action="<?php echo $base_path; ?>auth/proses_register.php" method="POST">
                    <?php if (isset($_SESSION['register_errors'])): ?>
                        <div class="alert alert-danger">
                            <ul>
                                <?php foreach ($_SESSION['register_errors'] as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php unset($_SESSION['register_errors']); ?>
                    <?php endif; ?>

                    <?php 
                    // Get saved registration data if exists
                    $register_data = isset($_SESSION['register_data']) ? $_SESSION['register_data'] : [
                        'nama' => '',
                        'nik' => '',
                        'email' => '',
                        'nomor_telepon' => ''
                    ];
                    
                    if (isset($_SESSION['register_data'])) {
                        unset($_SESSION['register_data']);
                    }
                    ?>

                    <div class="form-group">
                        <label for="registerName">Nama Lengkap</label>
                        <input type="text" class="form-control" id="registerName" name="nama" value="<?php echo htmlspecialchars($register_data['nama']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="registerNIK">NIK (Nomor Induk Kependudukan)</label>
                        <input type="text" class="form-control" id="registerNIK" name="nik" value="<?php echo htmlspecialchars($register_data['nik']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="registerEmail">Email</label>
                        <input type="email" class="form-control" id="registerEmail" name="email" value="<?php echo htmlspecialchars($register_data['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="registerPhone">Nomor HP</label>
                        <input type="tel" class="form-control" id="registerPhone" name="nomor_telepon" value="<?php echo htmlspecialchars($register_data['nomor_telepon']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="registerPassword">Kata Sandi</label>
                        <input type="password" class="form-control" id="registerPassword" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="registerConfirmPassword">Konfirmasi Kata Sandi</label>
                        <input type="password" class="form-control" id="registerConfirmPassword" name="confirm_password" required>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="termsAgree" name="terms" required>
                        <label for="termsAgree">Saya setuju dengan <a href="#">Ketentuan Layanan</a> dan <a href="#">Kebijakan Privasi</a></label>
                    </div>
                    <button type="submit" class="submit-btn">Daftar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Login/Logout Notification Popup -->
<div id="notificationPopup" class="notification-popup">
    <div class="notification-content">
        <span class="notification-close">&times;</span>
        <div id="notificationIcon"></div>
        <div id="notificationMessage"></div>
    </div>
</div>

<style>
/* Notification Popup Styles */
.notification-popup {
    display: none;
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    min-width: 300px;
    max-width: 400px;
    background-color: white;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    transition: all 0.3s ease;
}

.notification-popup.show {
    display: block;
    animation: slideIn 0.5s forwards;
}

.notification-content {
    display: flex;
    align-items: center;
    padding: 15px 20px;
}

.notification-close {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 18px;
    color: #999;
    cursor: pointer;
}

#notificationIcon {
    margin-right: 15px;
    font-size: 24px;
}

#notificationMessage {
    flex: 1;
    font-size: 14px;
}

.notification-success {
    border-left: 4px solid #28a745;
}

.notification-success #notificationIcon::before {
    content: "✓";
    color: #28a745;
}

.notification-error {
    border-left: 4px solid #dc3545;
}

.notification-error #notificationIcon::before {
    content: "✕";
    color: #dc3545;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
</style>

<script>
// Notification Popup JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Check for login/logout messages in session
    <?php if (isset($_SESSION['login_success'])): ?>
        showNotification('<?php echo $_SESSION['login_success']; ?>', 'success');
        <?php unset($_SESSION['login_success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['login_error'])): ?>
        showNotification('<?php echo $_SESSION['login_error']; ?>', 'error');
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['logout_success'])): ?>
        showNotification('<?php echo $_SESSION['logout_success']; ?>', 'success');
        <?php unset($_SESSION['logout_success']); ?>
    <?php endif; ?>
    
    // Close button functionality
    const closeBtn = document.querySelector('.notification-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            hideNotification();
        });
    }
});

function showNotification(message, type) {
    const popup = document.getElementById('notificationPopup');
    const messageEl = document.getElementById('notificationMessage');
    
    // Set message and type
    messageEl.textContent = message;
    popup.className = 'notification-popup notification-' + type + ' show';
    
    // Auto hide after 5 seconds
    setTimeout(hideNotification, 5000);
}

function hideNotification() {
    const popup = document.getElementById('notificationPopup');
    popup.style.animation = 'slideOut 0.5s forwards';
    
    // Remove the element from DOM after animation completes
    setTimeout(function() {
        popup.className = 'notification-popup';
        popup.style.animation = '';
    }, 500);
}
</script>