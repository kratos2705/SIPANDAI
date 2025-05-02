<?php
// Include necessary files
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn() || $_SESSION['user_role'] !== 'warga') {
    redirect('../auth/proses_login.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get user's ID
$user_id = $_SESSION['user_id'];

// Get user's applications
$pengajuan_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status,
                   jd.nama_dokumen, pd.keterangan
                   FROM pengajuan_dokumen pd
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                   WHERE pd.user_id = '$user_id'
                   ORDER BY pd.tanggal_pengajuan DESC
                   LIMIT 5";
$pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

// Get user's notifications
$notifikasi_query = "SELECT notifikasi_id, judul, pesan, is_read, created_at
                     FROM notifikasi
                     WHERE user_id = '$user_id'
                     ORDER BY created_at DESC
                     LIMIT 5";
$notifikasi_result = mysqli_query($koneksi, $notifikasi_query);

// Get applications by status
$status_query = "SELECT status, COUNT(*) as count
                FROM pengajuan_dokumen
                WHERE user_id = '$user_id'
                GROUP BY status";
$status_result = mysqli_query($koneksi, $status_query);

$status_counts = [
    'diajukan' => 0,
    'verifikasi' => 0,
    'proses' => 0,
    'selesai' => 0,
    'ditolak' => 0
];

if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Get user's information
$user_query = "SELECT nama, email, nomor_telepon, alamat, tanggal_registrasi
               FROM users
               WHERE user_id = '$user_id'";
$user_result = mysqli_query($koneksi, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

// Include header
$page_title = "Dashboard Pengguna";
include '../includes/header.php';

// Include sidebar
include '../includes/sidebar.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['user_nama']); ?></h2>
        <p>Dashboard Pengguna | <?php echo date('d F Y'); ?></p>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo $status_counts['diajukan']; ?></div>
            <div class="stat-label">Menunggu</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔍</div>
            <div class="stat-value"><?php echo $status_counts['verifikasi'] + $status_counts['proses']; ?></div>
            <div class="stat-label">Diproses</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $status_counts['selesai']; ?></div>
            <div class="stat-label">Selesai</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">❌</div>
            <div class="stat-value"><?php echo $status_counts['ditolak']; ?></div>
            <div class="stat-label">Ditolak</div>
        </div>
    </div>

    <div class="dashboard-sections">
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Pengajuan Terbaru</h3>
                <a href="../pengajuan-saya.php" class="view-all">Lihat Semua</a>
            </div>
            <div class="section-content">
                <?php
                if (mysqli_num_rows($pengajuan_result) > 0) {
                    while ($row = mysqli_fetch_assoc($pengajuan_result)) {
                        // Format date
                        $tanggal = date('d-m-Y', strtotime($row['tanggal_pengajuan']));
                        
                        echo '<div class="info-card">';
                        echo '<div class="info-header">';
                        echo '<h4>' . htmlspecialchars($row['nama_dokumen']) . '</h4>';
                        echo '<span class="status ' . getStatusClass($row['status']) . '">' . getStatusText($row['status']) . '</span>';
                        echo '</div>';
                        echo '<div class="info-details">';
                        echo '<p><strong>No. Pengajuan:</strong> #' . htmlspecialchars($row['nomor_pengajuan']) . '</p>';
                        echo '<p><strong>Tanggal:</strong> ' . $tanggal . '</p>';
                        
                        if (!empty($row['keterangan'])) {
                            echo '<p><strong>Keterangan:</strong> ' . htmlspecialchars($row['keterangan']) . '</p>';
                        }
                        
                        echo '</div>';
                        echo '<div class="info-actions">';
                        echo '<a href="../detail-pengajuan.php?id=' . $row['pengajuan_id'] . '" class="btn-small">Detail</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<p>Anda belum memiliki pengajuan dokumen.</p>';
                    echo '<a href="../pengajuan.php" class="btn">Buat Pengajuan</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h3>Notifikasi Terbaru</h3>
                <a href="../notifikasi.php" class="view-all">Lihat Semua</a>
            </div>
            <div class="section-content">
                <?php
                if (mysqli_num_rows($notifikasi_result) > 0) {
                    while ($row = mysqli_fetch_assoc($notifikasi_result)) {
                        // Format date
                        $tanggal = date('d-m-Y H:i', strtotime($row['created_at']));
                        
                        $read_class = $row['is_read'] ? 'notification-read' : 'notification-unread';
                        
                        echo '<div class="notification-card ' . $read_class . '">';
                        echo '<div class="notification-header">';
                        echo '<h4>' . htmlspecialchars($row['judul']) . '</h4>';
                        echo '<span class="notification-time">' . $tanggal . '</span>';
                        echo '</div>';
                        echo '<div class="notification-content">';
                        echo '<p>' . htmlspecialchars($row['pesan']) . '</p>';
                        echo '</div>';
                        if (!$row['is_read']) {
                            echo '<div class="notification-actions">';
                            echo '<a href="../mark-read.php?id=' . $row['notifikasi_id'] . '" class="btn-small">Tandai Dibaca</a>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<p>Tidak ada notifikasi baru.</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="dashboard-sections">
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Layanan Cepat</h3>
            </div>
            <div class="section-content">
                <div class="quick-actions">
                    <a href="../pengajuan.php" class="quick-action-card">
                        <div class="quick-action-icon">📄</div>
                        <div class="quick-action-text">Pengajuan Baru</div>
                    </a>
                    <a href="user/layanan-pembayaran.php" class="quick-action-card">
                        <div class="quick-action-icon">💰</div>
                        <div class="quick-action-text">Pembayaran</div>
                    </a>
                    <a href="../status-pengajuan.php" class="quick-action-card">
                        <div class="quick-action-icon">🔍</div>
                        <div class="quick-action-text">Cek Status</div>
                    </a>
                    <a href="../profil.php" class="quick-action-card">
                        <div class="quick-action-icon">👤</div>
                        <div class="quick-action-text">Profil</div>
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h3>Informasi Profil</h3>
                <a href="../profil.php" class="view-all">Edit</a>
            </div>
            <div class="section-content">
                <div class="profile-info">
                    <table class="profile-table">
                        <tr>
                            <th>Nama</th>
                            <td><?php echo htmlspecialchars($user_data['nama']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($user_data['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Nomor Telepon</th>
                            <td><?php echo htmlspecialchars($user_data['nomor_telepon']); ?></td>
                        </tr>
                        <tr>
                            <th>Alamat</th>
                            <td><?php echo htmlspecialchars($user_data['alamat'] ?? 'Belum diisi'); ?></td>
                        </tr>
                        <tr>
                            <th>Terdaftar Sejak</th>
                            <td><?php echo date('d-m-Y', strtotime($user_data['tanggal_registrasi'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions for this page
function getStatusClass($status) {
    switch ($status) {
        case 'diajukan':
            return 'status-pending';
        case 'verifikasi':
        case 'proses':
            return 'status-processing';
        case 'selesai':
            return 'status-completed';
        case 'ditolak':
            return 'status-rejected';
        default:
            return 'status-pending';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'diajukan':
            return 'Menunggu';
        case 'verifikasi':
            return 'Verifikasi';
        case 'proses':
            return 'Diproses';
        case 'selesai':
            return 'Selesai';
        case 'ditolak':
            return 'Ditolak';
        default:
            return 'Menunggu';
    }
}

// Include footer
include '../includes/footer.php';
?>