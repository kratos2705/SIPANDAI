<?php
// Define base path for proper includes
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], 'admin/') !== false) {
    $base_path = '../';
}

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
$user_role = $is_logged_in && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

// Current page handling
$current_page = isset($current_page) ? $current_page : '';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SIPANDAI - Sistem Informasi Pelayanan Administrasi Desa</title>
    <link href="<?php echo $base_path; ?>assets/css/admin-styles.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/alert.css" rel="stylesheet" />
    <link href="<?php echo $base_path; ?>assets/css/admin-responsive.css" rel="stylesheet" />
    <script src="<?php echo $base_path; ?>assets/js/chart.min.js"></script>
    <script src="<?php echo $base_path; ?>assets/js/admin.js" defer></script>
</head>

<body>
    <div class="admin-container">
        <!-- Admin Topbar -->
        <div class="admin-topbar">
            <div class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
            
            <div class="date-info">
                <?php echo $tanggal_indo; ?>
            </div>
            
            <div class="topbar-right">
                <div class="notifications-dropdown">
                    <button class="notification-btn" id="notificationBtn">
                        <span class="notification-icon">🔔</span>
                        <?php
                        // Get unread notifications count
                        if ($is_logged_in) {
                            $user_id = $_SESSION['user_id'];
                            $notif_query = "SELECT COUNT(*) as count FROM notifikasi WHERE is_read = FALSE AND (user_id = '$user_id' OR user_id IS NULL)";
                            $notif_result = mysqli_query($koneksi, $notif_query);
                            $unread_count = mysqli_fetch_assoc($notif_result)['count'];
                            
                            if ($unread_count > 0) {
                                echo '<span class="notification-badge">' . $unread_count . '</span>';
                            }
                        }
                        ?>
                    </button>
                    <div class="dropdown-content" id="notificationDropdown">
                        <div class="dropdown-header">
                            <h3>Notifikasi</h3>
                            <a href="<?php echo $base_path; ?>admin/notifikasi.php">Lihat Semua</a>
                        </div>
                        <div class="dropdown-items">
                            <?php
                            if ($is_logged_in) {
                                $notif_list_query = "SELECT * FROM notifikasi 
                                                     WHERE (user_id = '$user_id' OR user_id IS NULL) 
                                                     ORDER BY created_at DESC LIMIT 5";
                                $notif_list_result = mysqli_query($koneksi, $notif_list_query);
                                
                                if (mysqli_num_rows($notif_list_result) > 0) {
                                    while ($notif = mysqli_fetch_assoc($notif_list_result)) {
                                        $is_read_class = $notif['is_read'] ? '' : 'unread';
                                        $notif_time = time_elapsed_string($notif['created_at']);
                                        
                                        echo '<a href="' . $base_path . 'admin/notifikasi_detail.php?id=' . $notif['notifikasi_id'] . '" class="dropdown-item ' . $is_read_class . '">';
                                        echo '<div class="item-icon">📌</div>';
                                        echo '<div class="item-content">';
                                        echo '<div class="item-title">' . htmlspecialchars($notif['judul']) . '</div>';
                                        echo '<div class="item-description">' . htmlspecialchars(substr($notif['pesan'], 0, 60)) . (strlen($notif['pesan']) > 60 ? '...' : '') . '</div>';
                                        echo '<div class="item-time">' . $notif_time . '</div>';
                                        echo '</div>';
                                        echo '</a>';
                                    }
                                } else {
                                    echo '<div class="empty-state">Tidak ada notifikasi</div>';
                                }
                            } else {
                                echo '<div class="empty-state">Silakan login untuk melihat notifikasi</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="user-dropdown">
                    <button class="user-btn" id="userBtn">
                        <div class="user-avatar">👤</div>
                        <div class="user-name">
                            <?php echo htmlspecialchars($user_name); ?>
                            <span class="user-role"><?php echo ucfirst($user_role); ?></span>
                        </div>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-content" id="userDropdown">
                        <a href="<?php echo $base_path; ?>admin/profil.php" class="dropdown-item">
                            <div class="item-icon">👤</div>
                            <div class="item-content">Profil Saya</div>
                        </a>
                        <a href="<?php echo $base_path; ?>admin/pengaturan.php" class="dropdown-item">
                            <div class="item-icon">⚙️</div>
                            <div class="item-content">Pengaturan</div>
                        </a>
                        <a href="<?php echo $base_path; ?>admin/activity_log.php" class="dropdown-item">
                            <div class="item-icon">📋</div>
                            <div class="item-content">Log Aktivitas</div>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo $base_path; ?>auth/logout.php" class="dropdown-item">
                            <div class="item-icon">🚪</div>
                            <div class="item-content">Keluar</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

<?php
/**
 * Format a MySQL datetime to "time ago" format
 * 
 * @param string $datetime MySQL datetime
 * @return string Formatted time
 */
function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) {
        return $diff->y . ' tahun yang lalu';
    } elseif ($diff->m > 0) {
        return $diff->m . ' bulan yang lalu';
    } elseif ($diff->d > 0) {
        return $diff->d . ' hari yang lalu';
    } elseif ($diff->h > 0) {
        return $diff->h . ' jam yang lalu';
    } elseif ($diff->i > 0) {
        return $diff->i . ' menit yang lalu';
    } else {
        return 'Baru saja';
    }
}
?>