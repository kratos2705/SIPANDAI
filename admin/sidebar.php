<?php
/**
 * SIPANDAI - Admin Sidebar Component
 * This file contains the sidebar structure for the admin panel
 */

// Check if user is authorized
if (!isLoggedIn() || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa')) {
    redirect('../index.php');
}

// Get user role
$role = $_SESSION['user_role'] ?? 'admin';

// Get current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Admin Layout Structure -->
<div class="admin-layout">
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="header-left">
            <button type="button" id="sidebar-toggle" class="toggle-button">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="header-title"><?php echo ucfirst($current_page); ?></h1>
        </div>
        <div class="header-right">
            <!-- User Dropdown -->
            <div class="user-dropdown">
                <button type="button" id="userBtn" class="user-button">
                    <div class="user-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nama'] ?? 'Admin'); ?></span>
                    <i class="fas fa-chevron-down ml-2"></i>
                </button>
                <div id="userDropdown" class="user-dropdown-content">
                    <a href="<?php echo $base_path; ?>admin/profile.php" class="user-dropdown-item">
                        <i class="fas fa-user-circle"></i> Profil
                    </a>
                    <a href="<?php echo $base_path; ?>admin/notifications.php" class="user-dropdown-item">
                        <i class="fas fa-bell"></i> Notifikasi
                    </a>
                    <a href="<?php echo $base_path; ?>logout.php" class="user-dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Admin Sidebar -->
    <div class="admin-sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <img src="<?php echo $base_path; ?>assets/img/logo5.png" alt="SIPANDAI Logo" class="sidebar-logo">
            <h3>SIPANDAI</h3>
            <button class="sidebar-close" id="sidebar-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- User Info -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_nama'] ?? 'Admin'); ?></p>
                <p class="user-role"><?php echo $role === 'kepala_desa' ? 'Kepala Desa' : 'Administrator'; ?></p>
            </div>
        </div>
        
        <!-- Search Box -->
        <div class="sidebar-search">
            <div class="search-container">
                <input type="text" id="sidebar-search-input" placeholder="Cari menu...">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        
        <!-- Main Menu -->
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt sidebar-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="sidebar-title">Pelayanan Dokumen</li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/pengajuan.php" class="sidebar-link <?php echo $current_page === 'pengajuan' || $current_page === 'pengajuan_detail' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt sidebar-icon"></i>
                    <span>Pengajuan Dokumen</span>
                    <?php
                    // Get count of new applications
                    $new_count = 0;
                    $query = "SELECT COUNT(*) AS count FROM pengajuan_dokumen WHERE status = 'diajukan'";
                    $result = mysqli_query($koneksi, $query);
                    if ($result && $row = mysqli_fetch_assoc($result)) {
                        $new_count = $row['count'];
                    }
                    
                    if ($new_count > 0):
                    ?>
                    <span class="menu-badge"><?php echo $new_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/dokumen.php" class="sidebar-link <?php echo $current_page === 'dokumen' ? 'active' : ''; ?>">
                    <i class="fas fa-book sidebar-icon"></i>
                    <span>Jenis Dokumen</span>
                </a>
            </li>
            
            <li class="sidebar-title">Data dan Administrasi</li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/warga.php" class="sidebar-link <?php echo $current_page === 'warga' || $current_page === 'warga_detail' ? 'active' : ''; ?>">
                    <i class="fas fa-users sidebar-icon"></i>
                    <span>Data Warga</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/anggaran.php" class="sidebar-link <?php echo in_array($current_page, ['anggaran', 'anggaran_tambah', 'anggaran_detail']) ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave sidebar-icon"></i>
                    <span>Transparansi Anggaran</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/berita.php" class="sidebar-link <?php echo in_array($current_page, ['berita', 'berita_tambah', 'berita_edit']) ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper sidebar-icon"></i>
                    <span>Berita & Pengumuman</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/retribusi.php" class="sidebar-link <?php echo in_array($current_page, ['retribusi', 'pembayaran', 'pembayaran_detail']) ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-usd sidebar-icon"></i>
                    <span>Pembayaran Retribusi</span>
                    <?php
                    // Get count of pending payments
                    $pending_count = 0;
                    $query = "SELECT COUNT(*) AS count FROM pembayaran_retribusi WHERE status = 'pending'";
                    $result = mysqli_query($koneksi, $query);
                    if ($result && $row = mysqli_fetch_assoc($result)) {
                        $pending_count = $row['count'];
                    }
                    
                    if ($pending_count > 0):
                    ?>
                    <span class="menu-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="sidebar-title">Laporan</li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/laporan.php" class="sidebar-link <?php echo $current_page === 'laporan' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line sidebar-icon"></i>
                    <span>Laporan Administrasi</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/statistik.php" class="sidebar-link <?php echo $current_page === 'statistik' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie sidebar-icon"></i>
                    <span>Statistik Desa</span>
                </a>
            </li>
            
            <?php if ($role === 'admin'): ?>
            <li class="sidebar-title">Administrasi Sistem</li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/users.php" class="sidebar-link <?php echo in_array($current_page, ['users', 'user_tambah', 'user_edit']) ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog sidebar-icon"></i>
                    <span>Kelola Pengguna</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/log_aktivitas.php" class="sidebar-link <?php echo $current_page === 'log_aktivitas' ? 'active' : ''; ?>">
                    <i class="fas fa-history sidebar-icon"></i>
                    <span>Log Aktivitas</span>
                </a>
            </li>
            
            <li class="sidebar-item">
                <a href="<?php echo $base_path; ?>admin/settings.php" class="sidebar-link <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cogs sidebar-icon"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <!-- Footer -->
        <div class="sidebar-footer">
            <div class="version">SIPANDAI v1.0.0</div>
            <div class="copyright">&copy; <?php echo date('Y'); ?> Desa Digital</div>
        </div>
    </div>

    <!-- Admin Content Container -->
    <div class="admin-container" id="admin-container">
        <main class="admin-main">
            <!-- Content will be added here in each specific page -->