<?php
// Include necessary files
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has appropriate privileges
if (!isLoggedIn() || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa')) {
    $_SESSION['login_error'] = 'Anda tidak memiliki akses ke halaman ini.';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get current user info
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Get user ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['user_error'] = 'ID Pengguna tidak valid.';
    redirect('warga.php');
}

$user_id = (int)$_GET['id'];

// Get user data
$user_query = "SELECT u.*, 
              (SELECT COUNT(*) FROM pengajuan_dokumen WHERE user_id = u.user_id) as total_pengajuan,
              (SELECT COUNT(*) FROM pengajuan_dokumen WHERE user_id = u.user_id AND status = 'selesai') as pengajuan_selesai,
              (SELECT COUNT(*) FROM tagihan_retribusi WHERE user_id = u.user_id) as total_tagihan,
              (SELECT COUNT(*) FROM tagihan_retribusi WHERE user_id = u.user_id AND status = 'lunas') as tagihan_lunas
              FROM users u
              WHERE u.user_id = $user_id";
$user_result = mysqli_query($koneksi, $user_query);

if (mysqli_num_rows($user_result) == 0) {
    $_SESSION['user_error'] = 'Pengguna tidak ditemukan.';
    redirect('warga.php');
}

$user_data = mysqli_fetch_assoc($user_result);

// Get recent activity logs
$logs_query = "SELECT * FROM log_aktivitas 
               WHERE user_id = $user_id 
               ORDER BY created_at DESC 
               LIMIT 5";
$logs_result = mysqli_query($koneksi, $logs_query);

// Get recent document applications
$docs_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status, jd.nama_dokumen 
               FROM pengajuan_dokumen pd
               JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
               WHERE pd.user_id = $user_id
               ORDER BY pd.tanggal_pengajuan DESC
               LIMIT 5";
$docs_result = mysqli_query($koneksi, $docs_query);

// Get recent payment history
$payments_query = "SELECT tr.tagihan_id, tr.nominal, tr.status as tagihan_status, tr.tanggal_tagihan, 
                  jr.nama_retribusi, pr.tanggal_bayar, pr.status as pembayaran_status
                  FROM tagihan_retribusi tr
                  LEFT JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                  LEFT JOIN pembayaran_retribusi pr ON tr.tagihan_id = pr.tagihan_id
                  WHERE tr.user_id = $user_id
                  ORDER BY tr.tanggal_tagihan DESC
                  LIMIT 5";
$payments_result = mysqli_query($koneksi, $payments_query);

// Set page title
$page_title = "Detail Pengguna";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">
    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Detail Pengguna</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo;
                <a href="warga.php">Data Warga</a> &raquo;
                Detail Pengguna
            </nav>
        </div>

        <div class="button-container">
            <a href="warga.php" class="btn btn-secondary">
                <span class="btn-icon">↩</span> Kembali
            </a>
            <?php if ($current_user_role === 'admin'): ?>
                <a href="user_edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                    <span class="btn-icon">✏️</span> Edit Pengguna
                </a>
            <?php endif; ?>
            <a href="javascript:void(0);" onclick="window.print();" class="btn btn-secondary">
                <span class="btn-icon">🖨️</span> Cetak
            </a>
        </div>

        <!-- User Profile Card -->
        <div class="profile-container">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if (!empty($user_data['foto_profil'])): ?>
                            <img src="../uploads/profil/<?php echo htmlspecialchars($user_data['foto_profil']); ?>" alt="Foto profil">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo substr(htmlspecialchars($user_data['nama']), 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user_data['nama']); ?></h3>
                        <p class="nik"><?php echo htmlspecialchars($user_data['nik']); ?></p>
                        <p class="role">
                            <?php
                            $role_text = '';
                            switch ($user_data['role']) {
                                case 'admin':
                                    $role_text = '<span class="badge badge-primary">Admin</span>';
                                    break;
                                case 'kepala_desa':
                                    $role_text = '<span class="badge badge-warning">Kepala Desa</span>';
                                    break;
                                case 'warga':
                                    $role_text = '<span class="badge badge-info">Warga</span>';
                                    break;
                                default:
                                    $role_text = '<span class="badge badge-secondary">' . ucfirst($user_data['role']) . '</span>';
                            }
                            echo $role_text;
                            ?>

                            <?php if ($user_data['active']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_data['total_pengajuan']; ?></div>
                        <div class="stat-label">Total Pengajuan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_data['pengajuan_selesai']; ?></div>
                        <div class="stat-label">Pengajuan Selesai</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_data['total_tagihan']; ?></div>
                        <div class="stat-label">Total Tagihan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user_data['tagihan_lunas']; ?></div>
                        <div class="stat-label">Tagihan Lunas</div>
                    </div>
                </div>

                <div class="profile-body">
                    <div class="profile-section">
                        <h4>Informasi Pribadi</h4>
                        <div class="profile-details">
                            <div class="detail-row">
                                <div class="detail-label">NIK</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_data['nik']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Nama Lengkap</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_data['nama']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Nomor Telepon</div>
                                <div class="detail-value"><?php echo !empty($user_data['nomor_telepon']) ? htmlspecialchars($user_data['nomor_telepon']) : '-'; ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Jenis Kelamin</div>
                                <div class="detail-value"><?php echo !empty($user_data['jenis_kelamin']) ? htmlspecialchars($user_data['jenis_kelamin']) : '-'; ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Tanggal Lahir</div>
                                <div class="detail-value">
                                    <?php
                                    echo !empty($user_data['tanggal_lahir']) ? date('d-m-Y', strtotime($user_data['tanggal_lahir'])) : '-';
                                    ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Alamat</div>
                                <div class="detail-value"><?php echo !empty($user_data['alamat']) ? htmlspecialchars($user_data['alamat']) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section">
                        <h4>Informasi Akun</h4>
                        <div class="profile-details">
                            <div class="detail-row">
                                <div class="detail-label">Peran</div>
                                <div class="detail-value">
                                    <?php
                                    switch ($user_data['role']) {
                                        case 'admin':
                                            echo 'Admin';
                                            break;
                                        case 'kepala_desa':
                                            echo 'Kepala Desa';
                                            break;
                                        case 'warga':
                                            echo 'Warga';
                                            break;
                                        default:
                                            echo ucfirst($user_data['role']);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <?php echo $user_data['active'] ? '<span class="text-success">Aktif</span>' : '<span class="text-danger">Nonaktif</span>'; ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Tanggal Registrasi</div>
                                <div class="detail-value"><?php echo date('d-m-Y H:i', strtotime($user_data['created_at'])); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Terakhir Diperbarui</div>
                                <div class="detail-value"><?php echo date('d-m-Y H:i', strtotime($user_data['updated_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities and Records -->
            <div class="records-container">
                <!-- Recent Applications -->
                <div class="records-card">
                    <div class="records-header">
                        <h4>Pengajuan Dokumen Terakhir</h4>
                        <a href="../admin/pengajuan.php?user_id=<?php echo $user_id; ?>" class="view-all">Lihat Semua</a>
                    </div>
                    <div class="records-body">
                        <?php if (mysqli_num_rows($docs_result) > 0): ?>
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Nomor</th>
                                        <th>Jenis Dokumen</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($doc = mysqli_fetch_assoc($docs_result)): ?>
                                        <tr>
                                            <td><a href="../admin/pengajuan_detail.php?id=<?php echo $doc['pengajuan_id']; ?>"><?php echo htmlspecialchars($doc['nomor_pengajuan']); ?></a></td>
                                            <td><?php echo htmlspecialchars($doc['nama_dokumen']); ?></td>
                                            <td><?php echo date('d-m-Y', strtotime($doc['tanggal_pengajuan'])); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                $status_text = '';

                                                switch ($doc['status']) {
                                                    case 'diajukan':
                                                        $status_class = 'status-pending';
                                                        $status_text = 'Diajukan';
                                                        break;
                                                    case 'verifikasi':
                                                        $status_class = 'status-processing';
                                                        $status_text = 'Verifikasi';
                                                        break;
                                                    case 'proses':
                                                        $status_class = 'status-processing';
                                                        $status_text = 'Diproses';
                                                        break;
                                                    case 'selesai':
                                                        $status_class = 'status-completed';
                                                        $status_text = 'Selesai';
                                                        break;
                                                    case 'ditolak':
                                                        $status_class = 'status-rejected';
                                                        $status_text = 'Ditolak';
                                                        break;
                                                    default:
                                                        $status_class = '';
                                                        $status_text = ucfirst($doc['status']);
                                                }
                                                ?>
                                                <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-records">
                                <p>Tidak ada data pengajuan dokumen</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="records-card">
                    <div class="records-header">
                        <h4>Pembayaran Retribusi Terakhir</h4>
                        <a href="../admin/retribusi.php?user_id=<?php echo $user_id; ?>" class="view-all">Lihat Semua</a>
                    </div>
                    <div class="records-body">
                        <?php if (mysqli_num_rows($payments_result) > 0): ?>
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Nominal</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payment = mysqli_fetch_assoc($payments_result)): ?>
                                        <tr>
                                            <td><a href="../admin/retribusi_detail.php?id=<?php echo $payment['tagihan_id']; ?>"><?php echo htmlspecialchars($payment['nama_retribusi']); ?></a></td>
                                            <td>Rp <?php echo number_format($payment['nominal'], 0, ',', '.'); ?></td>
                                            <td><?php echo date('d-m-Y', strtotime($payment['tanggal_tagihan'])); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                $status_text = '';

                                                switch ($payment['tagihan_status']) {
                                                    case 'belum_bayar':
                                                        $status_class = 'status-pending';
                                                        $status_text = 'Belum Bayar';
                                                        break;
                                                    case 'proses':
                                                        $status_class = 'status-processing';
                                                        $status_text = 'Diproses';
                                                        break;
                                                    case 'lunas':
                                                        $status_class = 'status-completed';
                                                        $status_text = 'Lunas';
                                                        break;
                                                    case 'telat':
                                                        $status_class = 'status-rejected';
                                                        $status_text = 'Terlambat';
                                                        break;
                                                    default:
                                                        $status_class = '';
                                                        $status_text = ucfirst($payment['tagihan_status']);
                                                }
                                                ?>
                                                <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-records">
                                <p>Tidak ada data pembayaran retribusi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity Logs -->
                <div class="records-card">
                    <div class="records-header">
                        <h4>Aktivitas Terakhir</h4>
                    </div>
                    <div class="records-body">
                        <?php if (mysqli_num_rows($logs_result) > 0): ?>
                            <div class="activity-timeline">
                                <?php while ($log = mysqli_fetch_assoc($logs_result)): ?>
                                    <div class="activity-item">
                                        <div class="activity-time">
                                            <?php echo date('d-m-Y H:i', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?php echo htmlspecialchars($log['aktivitas']); ?></div>
                                            <div class="activity-desc"><?php echo htmlspecialchars($log['deskripsi']); ?></div>
                                            <div class="activity-meta">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-records">
                                <p>Tidak ada data aktivitas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    /* Memperbaiki masalah pada aplikasi dengan layout fixed-width */
    .app-container,
    [class*="container"],
    .dashboard-content,
    .main-wrapper,
    .content-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        padding-right: 0 !important;
        margin-right: 0 !important;
        box-sizing: border-box !important;
    }

    /* Profile styles */
    .profile-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    .profile-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .profile-header {
        display: flex;
        padding: 20px;
        border-bottom: 1px solid #eee;
        background-color: #f8f9fa;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 20px;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: #6c757d;
    }

    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #007bff;
        color: white;
        font-size: 40px;
        text-transform: uppercase;
    }

    .profile-info {
        flex: 1;
    }

    .profile-info h3 {
        margin: 0 0 5px 0;
        font-size: 1.5rem;
        color: #343a40;
    }

    .profile-info .nik {
        margin: 0 0 10px 0;
        font-size: 1rem;
        color: #6c757d;
    }

    .profile-info .role {
        display: flex;
        gap: 10px;
    }

    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-primary {
        background-color: #007bff;
        color: white;
    }

    .badge-secondary {
        background-color: #6c757d;
        color: white;
    }

    .badge-success {
        background-color: #28a745;
        color: white;
    }

    .badge-danger {
        background-color: #dc3545;
        color: white;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
        color: white;
    }

    .profile-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        padding: 15px 0;
        background-color: #f8f9fa;
        border-bottom: 1px solid #eee;
    }

    .stat-item {
        text-align: center;
        padding: 0 10px;
        border-right: 1px solid #eee;
    }

    .stat-item:last-child {
        border-right: none;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: #343a40;
    }

    .stat-label {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .profile-body {
        padding: 20px;
    }

    .profile-section {
        margin-bottom: 30px;
    }

    .profile-section:last-child {
        margin-bottom: 0;
    }

    .profile-section h4 {
        margin: 0 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
        color: #343a40;
        font-size: 1.1rem;
    }

    .profile-details {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .detail-row {
        display: flex;
        font-size: 0.95rem;
    }

    .detail-label {
        flex: 0 0 150px;
        color: #6c757d;
        font-weight: 500;
    }

    .detail-value {
        flex: 1;
        color: #212529;
    }

    .text-success {
        color: #28a745;
    }

    .text-danger {
        color: #dc3545;
    }

    /* Records styles */
    .records-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .records-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 20px;
    }

    .records-card:last-child {
        margin-bottom: 0;
    }

    .records-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        background-color: #f8f9fa;
    }

    .records-header h4 {
        margin: 0;
        font-size: 1.1rem;
        color: #343a40;
    }

    .view-all {
        font-size: 0.85rem;
        color: #007bff;
        text-decoration: none;
    }

    .view-all:hover {
        text-decoration: underline;
    }

    .records-body {
        padding: 15px 20px;
    }

    .records-table {
        width: 100%;
        border-collapse: collapse;
    }

    .records-table th,
    .records-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .records-table th {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
    }

    .records-table td {
        font-size: 0.9rem;
    }

    .records-table tr:last-child td {
        border-bottom: none;
    }

    .empty-records {
        text-align: center;
        padding: 30px 20px;
        color: #6c757d;
    }

    /* Activity timeline */
    .activity-timeline {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .activity-item {
        display: flex;
        border-left: 3px solid #dee2e6;
        padding-left: 15px;
    }

    .activity-time {
        flex: 0 0 130px;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        font-weight: 500;
        margin-bottom: 3px;
        color: #343a40;
    }

    .activity-desc {
        font-size: 0.9rem;
        color: #495057;
        margin-bottom: 5px;
    }

    .activity-meta {
        font-size: 0.8rem;
        color: #6c757d;
    }

    /* Status styles */
    .status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-processing {
        background-color: #cce5ff;
        color: #004085;
    }

    .status-completed {
        background-color: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background-color: #f8d7da;
        color: #721c24;
    }

    /* Button styles */
    .button-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .btn {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid transparent;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0069d9;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .btn-info {
        background-color: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        background-color: #138496;
    }

    .btn-icon {
        margin-right: 5px;
    }

    /* Responsive adjustments */
    @media (min-width: 992px) {
        .profile-container {
            grid-template-columns: 350px 1fr;
            align-items: start;
        }

        .profile-card {
            position: sticky;
            top: 20px;
        }
    }

    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .profile-avatar {
            margin-right: 0;
            margin-bottom: 15px;
        }

        .profile-info .role {
            justify-content: center;
        }

        .profile-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-item {
            border-right: none;
            padding: 10px;
        }

        .detail-row {
            flex-direction: column;
        }

        .detail-label {
            flex: 0 0 auto;
            margin-bottom: 5px;
        }

        .activity-item {
            flex-direction: column;
        }

        .activity-time {
            flex: 0 0 auto;
            margin-bottom: 5px;
        }

        .button-container {
            flex-wrap: wrap;
        }
    }

    @media print {

        .admin-sidebar,
        .button-container,
        .view-all,
        .breadcrumb {
            display: none !important;
        }

        .admin-container {
            display: block;
        }

        .profile-container,
        .records-container {
            display: block;
        }

        .profile-card,
        .records-card {
            margin-bottom: 20px;
            break-inside: avoid;
        }

        .profile-stats {
            grid-template-columns: repeat(4, 1fr);
        }

        .admin-content {
            padding: 0;
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add any necessary JavaScript functionality

        // Print functionality is already handled by the print button

        // Highlight the current user in the sidebar (if applicable)
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            if (link.getAttribute('href') === 'warga.php') {
                link.parentElement.classList.add('active');
            }
        });
    });
</script>