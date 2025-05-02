<?php
// Include necessary functions file first
require_once __DIR__ . '/functions.php';

// Check if user is authorized
if (!isLoggedIn()) {
    redirect('../index.php');
}

// Get user role
$role = $_SESSION['user_role'] ?? 'warga';

// Define base path for proper includes
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], 'admin/') !== false) {
    $base_path = '../';
}

// Check if current page is set, if not use the filename
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SIPANDAI Admin</title>
    <!-- Admin styles CSS (will be included in admin-header.php, just here for standalone testing) -->
    <?php if (!isset($admin_header_included)): ?>
        <link href="<?php echo $base_path; ?>assets/css/admin-styles.css" rel="stylesheet" />
        <link href="<?php echo $base_path; ?>assets/css/alert.css" rel="stylesheet" />
        <link href="<?php echo $base_path; ?>assets/css/admin-responsive.css" rel="stylesheet" />
        <script src="<?php echo $base_path; ?>assets/js/chart.min.js"></script>
        <script src="<?php echo $base_path; ?>assets/js/admin.js" defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <img src="<?php echo $base_path; ?>assets/img/logo5.png" alt="SIPANDAI Logo" class="sidebar-logo">
                <h3>SIPANDAI</h3>
                <span class="close-sidebar" id="closeSidebar">×</span>
            </div>
            
            <div class="sidebar-user">
                <div class="user-avatar">👤</div>
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_nama'] ?? 'Admin'); ?></p>
                    <p class="user-role"><?php echo ucfirst($role); ?></p>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li class="sidebar-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/dashboard.php" class="sidebar-link">
                        <span class="sidebar-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="sidebar-item <?php echo $current_page == 'pengajuan' || $current_page == 'pengajuan_detail' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/pengajuan.php" class="sidebar-link">
                        <span class="sidebar-icon">📝</span>
                        <span>Pengajuan Dokumen</span>
                    </a>
                </li>
                
                <li class="sidebar-item <?php echo $current_page == 'warga' || $current_page == 'warga_detail' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/warga.php" class="sidebar-link">
                        <span class="sidebar-icon">👥</span>
                        <span>Data Warga</span>
                    </a>
                </li>
                
                <li class="sidebar-item <?php echo $current_page == 'anggaran' || $current_page == 'anggaran_detail' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/anggaran.php" class="sidebar-link">
                        <span class="sidebar-icon">💰</span>
                        <span>Transparansi Anggaran</span>
                    </a>
                </li>
                
                <li class="sidebar-item <?php echo $current_page == 'berita' || $current_page == 'berita_edit' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/berita.php" class="sidebar-link">
                        <span class="sidebar-icon">📰</span>
                        <span>Berita & Pengumuman</span>
                    </a>
                </li>
                
                <li class="sidebar-item <?php echo $current_page == 'retribusi' || $current_page == 'pembayaran' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/retribusi.php" class="sidebar-link">
                        <span class="sidebar-icon">💲</span>
                        <span>Retribusi</span>
                    </a>
                </li>
                
                <!-- Only show user management for admin -->
                <?php if ($role === 'admin'): ?>
                <li class="sidebar-item <?php echo $current_page == 'users' || $current_page == 'user_detail' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/users.php" class="sidebar-link">
                        <span class="sidebar-icon">👤</span>
                        <span>Manajemen Pengguna</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="sidebar-item <?php echo $current_page == 'laporan' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/laporan.php" class="sidebar-link">
                        <span class="sidebar-icon">📊</span>
                        <span>Laporan</span>
                    </a>
                </li>
                
                <!-- Only show settings for admin -->
                <?php if ($role === 'admin'): ?>
                <li class="sidebar-item <?php echo $current_page == 'pengaturan' ? 'active' : ''; ?>">
                    <a href="<?php echo $base_path; ?>admin/pengaturan.php" class="sidebar-link">
                        <span class="sidebar-icon">⚙️</span>
                        <span>Pengaturan Sistem</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="sidebar-item">
                    <a href="<?php echo $base_path; ?>auth/logout.php" class="sidebar-link">
                        <span class="sidebar-icon">🚪</span>
                        <span>Keluar</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <p>© <?php echo date('Y'); ?> SIPANDAI</p>
                <p>Versi 1.0.0</p>
            </div>
        </div>
        
        <?php $admin_header_included = true; ?>