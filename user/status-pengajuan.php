<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login terlebih dahulu';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get user's ID
$user_id = $_SESSION['user_id'];

// Get pengajuan_id from URL or form
$pengajuan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$nomor_pengajuan = isset($_GET['nomor']) ? sanitizeInput($_GET['nomor']) : '';
$search_nik = isset($_GET['nik']) ? sanitizeInput($_GET['nik']) : '';

// Fetch pengajuan data if ID is provided
$pengajuan_data = [];
$is_owner = false;

if ($pengajuan_id > 0) {
    // Query to get pengajuan data by ID
    $pengajuan_query = "SELECT pd.*, jd.nama_dokumen, jd.deskripsi, jd.estimasi_waktu,
                      u.nama as user_nama, u.nik, u.alamat, u.nomor_telepon as telepon, u.email
                      FROM pengajuan_dokumen pd
                      JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                      JOIN users u ON pd.user_id = u.user_id
                      WHERE pd.pengajuan_id = '$pengajuan_id'";
    $pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

    if (mysqli_num_rows($pengajuan_result) > 0) {
        $pengajuan_data = mysqli_fetch_assoc($pengajuan_result);
        // Check if the current user is the owner of this application
        $is_owner = ($pengajuan_data['user_id'] == $user_id);

        // If user is not owner and not admin/staff, redirect to error page
        if (!$is_owner && $_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa') {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat pengajuan ini';
            redirect('dashboard.php');
        }
    } else {
        $_SESSION['error'] = 'Data pengajuan tidak ditemukan';
        redirect('dashboard.php');
    }
} elseif (!empty($nomor_pengajuan)) {
    // Search by nomor pengajuan
    $pengajuan_query = "SELECT pd.*, jd.nama_dokumen, jd.deskripsi, jd.estimasi_waktu,
                      u.nama as user_nama, u.nik, u.alamat, u.nomor_telepon as telepon, u.email
                      FROM pengajuan_dokumen pd
                      JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                      JOIN users u ON pd.user_id = u.user_id
                      WHERE pd.nomor_pengajuan = '$nomor_pengajuan'";
    $pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

    if (mysqli_num_rows($pengajuan_result) > 0) {
        $pengajuan_data = mysqli_fetch_assoc($pengajuan_result);
        $pengajuan_id = $pengajuan_data['pengajuan_id'];
        // Check if the current user is the owner of this application
        $is_owner = ($pengajuan_data['user_id'] == $user_id);

        // If user is not owner and not admin/staff, redirect to error page
        if (!$is_owner && $_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa') {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat pengajuan ini';
            redirect('dashboard.php');
        }
    } else {
        $_SESSION['error'] = 'Data pengajuan tidak ditemukan';
        redirect('dashboard.php');
    }
} elseif (!empty($search_nik)) {
    // Search by NIK
    $pengajuan_query = "SELECT pd.*, jd.nama_dokumen, jd.deskripsi, jd.estimasi_waktu,
                      u.nama as user_nama, u.nik, u.alamat, u.nomor_telepon as telepon, u.email
                      FROM pengajuan_dokumen pd
                      JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                      JOIN users u ON pd.user_id = u.user_id
                      WHERE u.nik = '$search_nik'
                      ORDER BY pd.tanggal_pengajuan DESC
                      LIMIT 1";
    $pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

    if (mysqli_num_rows($pengajuan_result) > 0) {
        $pengajuan_data = mysqli_fetch_assoc($pengajuan_result);
        $pengajuan_id = $pengajuan_data['pengajuan_id'];
        // Check if the current user is the owner of this application
        $is_owner = ($pengajuan_data['user_id'] == $user_id);

        // If user is not owner and not admin/staff, redirect to error page
        if (!$is_owner && $_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa') {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat pengajuan ini';
            redirect('dashboard.php');
        }
    } else {
        $_SESSION['error'] = 'Data pengajuan tidak ditemukan';
        redirect('dashboard.php');
    }
}

// Get timeline data for this application
$timeline_data = [];
if ($pengajuan_id > 0) {
    $timeline_query = "SELECT rp.*, u.nama as changed_by_name
    FROM riwayat_pengajuan rp
    LEFT JOIN users u ON rp.changed_by = u.user_id
    WHERE rp.pengajuan_id = '$pengajuan_id'
    ORDER BY rp.tanggal_perubahan ASC";
    $timeline_result = mysqli_query($koneksi, $timeline_query);

    if (mysqli_num_rows($timeline_result) > 0) {
        while ($row = mysqli_fetch_assoc($timeline_result)) {
            $timeline_data[] = $row;
        }
    }
}

// Get uploaded documents
$documents_data = [];
if ($pengajuan_id > 0) {
    $documents_query = "SELECT * FROM dokumen_persyaratan WHERE pengajuan_id = '$pengajuan_id'";
    $documents_result = mysqli_query($koneksi, $documents_query);

    if (mysqli_num_rows($documents_result) > 0) {
        while ($row = mysqli_fetch_assoc($documents_result)) {
            $documents_data[] = $row;
        }
    }
}

// Set page title
$page_title = "Status Pengajuan Dokumen";

// Include header
include '../includes/header.php';

?>
<style>
        .status-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .status-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .status-header h2 {
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .status-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .detail-section {
            margin-bottom: 2rem;
        }
        
        .detail-section h3 {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .detail-label {
            flex: 0 0 40%;
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            flex: 0 0 60%;
            color: #333;
        }
        
        .status-progress {
            margin: 2rem 0;
        }
        
        .progress-title {
            margin-bottom: 1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .progress-track {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 2rem;
        }
        
        .progress-track::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 1;
        }
        
        .progress-step {
            position: relative;
            z-index: 2;
            text-align: center;
            min-width: 100px;
        }
        
        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: white;
            font-size: 14px;
        }
        
        .step-label {
            font-size: 14px;
            color: #777;
        }
        
        .step-active .step-icon {
            background-color: #3498db;
        }
        
        .step-active .step-label {
            color: #3498db;
            font-weight: 600;
        }
        
        .step-done .step-icon {
            background-color: #2ecc71;
        }
        
        .step-done .step-label {
            color: #2ecc71;
        }
        
        .progress-track .progress-line {
            position: absolute;
            top: 15px;
            left: 0;
            height: 2px;
            background-color: #2ecc71;
            z-index: 1;
            transition: width 0.3s ease;
        }
        
        .status-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .timeline {
            margin-top: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 30px;
            padding-bottom: 1.5rem;
            border-left: 2px solid #e0e0e0;
            margin-left: 20px;
        }
        
        .timeline-item:last-child {
            border-left: 2px solid transparent;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: #3498db;
        }
        
        .timeline-date {
            font-size: 14px;
            color: #888;
            margin-bottom: 0.25rem;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .timeline-desc {
            font-size: 14px;
            color: #555;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
        }
        
        .loading:after {
            content: " .";
            animation: dots 1s steps(5, end) infinite;
        }
        
        @keyframes dots {
            0%, 20% {
                color: rgba(0,0,0,0);
                text-shadow: .25em 0 0 rgba(0,0,0,0), .5em 0 0 rgba(0,0,0,0);
            }
            40% {
                color: #333;
                text-shadow: .25em 0 0 rgba(0,0,0,0), .5em 0 0 rgba(0,0,0,0);
            }
            60% {
                text-shadow: .25em 0 0 #333, .5em 0 0 rgba(0,0,0,0);
            }
            80%, 100% {
                text-shadow: .25em 0 0 #333, .5em 0 0 #333;
            }
        }

        .dokumen-hasil {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .dokumen-hasil-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .dokumen-hasil-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-download {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-download:hover {
            background-color: #218838;
        }

        .btn-view {
            background-color: #17a2b8;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-view:hover {
            background-color: #138496;
        }
    </style>
<div class="main-container">
    <section class="page-header">
        <h2>Status Pengajuan Dokumen</h2>
        <p>Pantau status permohonan dokumen Anda</p>
    </section>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($pengajuan_data)): ?>
        <!-- Search Form -->
        <div class="status-container">
            <div class="status-header">
                <h2>Cek Status Pengajuan</h2>
                <p>Masukkan nomor pengajuan atau NIK untuk melihat status pengajuan Anda</p>
            </div>

            <form id="searchForm" class="search-form" method="GET" action="status-pengajuan.php">
                <div class="form-group">
                    <label for="search_type">Cari Berdasarkan</label>
                    <select id="search_type" name="search_type" required>
                        <option value="nomor">Nomor Pengajuan</option>
                        <option value="nik">NIK</option>
                    </select>
                </div>

                <div class="form-group search-nomor">
                    <label for="nomor">Nomor Pengajuan</label>
                    <input type="text" id="nomor" name="nomor" placeholder="Contoh: DOK-20250401-12345">
                </div>

                <div class="form-group search-nik" style="display: none;">
                    <label for="nik">NIK (Nomor Induk Kependudukan)</label>
                    <input type="text" id="nik" name="nik" placeholder="Masukkan 16 digit NIK">
                </div>

                <div style="text-align: center;">
                    <button type="submit" class="btn">Cari Pengajuan</button>
                </div>
            </form>

            <div class="recent-applications">
                <h3>Pengajuan Terbaru Anda</h3>
                <?php
                // Get user's recent applications
                $recent_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status,
                               jd.nama_dokumen
                               FROM pengajuan_dokumen pd
                               JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                               WHERE pd.user_id = '$user_id'
                               ORDER BY pd.tanggal_pengajuan DESC
                               LIMIT 5";
                $recent_result = mysqli_query($koneksi, $recent_query);

                if (mysqli_num_rows($recent_result) > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table class="recent-table">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>No. Pengajuan</th>';
                    echo '<th>Jenis Dokumen</th>';
                    echo '<th>Tanggal</th>';
                    echo '<th>Status</th>';
                    echo '<th>Aksi</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';

                    while ($row = mysqli_fetch_assoc($recent_result)) {
                        echo '<tr>';
                        echo '<td>' . $row['nomor_pengajuan'] . '</td>';
                        echo '<td>' . $row['nama_dokumen'] . '</td>';
                        echo '<td>' . date('d-m-Y', strtotime($row['tanggal_pengajuan'])) . '</td>';
                        echo '<td>' . getStatusBadge($row['status']) . '</td>';
                        echo '<td><a href="status-pengajuan.php?id=' . $row['pengajuan_id'] . '" class="btn-small">Detail</a></td>';
                        echo '</tr>';
                    }

                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<p>Anda belum memiliki pengajuan dokumen</p>';
                    echo '<a href="pengajuan.php" class="btn">Buat Pengajuan Baru</a>';
                }
                ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Status Detail -->
        <div class="status-container">
            <div class="status-header">
                <h2>Detail Pengajuan Dokumen</h2>
                <p>Nomor Pengajuan: <strong><?php echo htmlspecialchars($pengajuan_data['nomor_pengajuan']); ?></strong></p>
            </div>

            <div class="status-detail">
                <div class="detail-section">
                    <h3>Informasi Pengajuan</h3>
                    <div class="detail-item">
                        <div class="detail-label">Jenis Dokumen</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['nama_dokumen']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tanggal Pengajuan</div>
                        <div class="detail-value"><?php echo date('d-m-Y H:i', strtotime($pengajuan_data['tanggal_pengajuan'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><?php echo getStatusBadge($pengajuan_data['status']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Estimasi Waktu</div>
                        <div class="detail-value"><?php echo $pengajuan_data['estimasi_waktu'] ? $pengajuan_data['estimasi_waktu'] . ' hari kerja' : 'Tidak ditentukan'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Keperluan</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['catatan']); ?></div>
                    </div>
                </div>

                <div class="detail-section">
                    <h3>Data Pemohon</h3>
                    <div class="detail-item">
                        <div class="detail-label">Nama</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['user_nama']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">NIK</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['nik']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Alamat</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['alamat']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Telepon</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['telepon']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pengajuan_data['email']); ?></div>
                    </div>
                </div>
            </div>

            <div class="status-progress">
                <div class="progress-title">Status Pengajuan</div>
                <?php
                // Define status mapping for progress tracking
                $status_mapping = [
                    'diajukan' => 0,
                    'verifikasi' => 1,
                    'proses' => 2,
                    'selesai' => 3,
                    'ditolak' => -1
                ];

                $current_step = $status_mapping[$pengajuan_data['status']];
                $progress_width = $current_step >= 0 ? (($current_step / 3) * 100) : 0;
                ?>
                <div class="progress-track">
                    <div class="progress-line" style="width: <?php echo $progress_width; ?>%;"></div>
                    <div class="progress-step <?php echo $current_step >= 0 ? 'step-done' : 'step-active'; ?>">
                        <div class="step-icon">1</div>
                        <div class="step-label">Diajukan</div>
                    </div>
                    <div class="progress-step <?php echo $current_step >= 1 ? 'step-done' : ($current_step == 0 ? 'step-active' : ''); ?>">
                        <div class="step-icon">2</div>
                        <div class="step-label">Verifikasi</div>
                    </div>
                    <div class="progress-step <?php echo $current_step >= 2 ? 'step-done' : ($current_step == 1 ? 'step-active' : ''); ?>">
                        <div class="step-icon">3</div>
                        <div class="step-label">Diproses</div>
                    </div>
                    <div class="progress-step <?php echo $current_step >= 3 ? 'step-done' : ($current_step == 2 ? 'step-active' : ''); ?>">
                        <div class="step-icon">4</div>
                        <div class="step-label">Selesai</div>
                    </div>
                </div>
            </div>

            <!-- Tambahkan section dokumen hasil -->
            <?php if ($pengajuan_data['status'] == 'selesai' && !empty($pengajuan_data['dokumen_hasil'])): ?>
            <div class="detail-section">
                <h3>Dokumen Hasil</h3>
                <div class="dokumen-hasil">
                    <div class="dokumen-hasil-title">
                        <i class="fa fa-file-pdf"></i> 
                        <?php echo basename($pengajuan_data['dokumen_hasil']); ?>
                    </div>
                    <div class="dokumen-hasil-actions">
                        <a href="../uploads/hasil/<?php echo $pengajuan_data['dokumen_hasil']; ?>" target="_blank" class="btn-view">
                            <i class="fa fa-eye"></i> Lihat Dokumen
                        </a>
                        <a href="download.php?file=<?php echo urlencode($pengajuan_data['dokumen_hasil']); ?>" class="btn-download">
                            <i class="fa fa-download"></i> Unduh Dokumen
                        </a>
                        <a href="print.php?file=<?php echo urlencode($pengajuan_data['dokumen_hasil']); ?>" target="_blank" class="btn-view">
                            <i class="fa fa-print"></i> Cetak Dokumen
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="detail-section">
                <h3>Dokumen Pendukung</h3>
                <div class="documents-list">
                    <?php
                    if (!empty($documents_data)) {
                        echo '<ul class="document-items">';
                        foreach ($documents_data as $doc) {
                            echo '<li class="document-item">';
                            echo '<div class="document-icon">';
                            $ext = strtolower(pathinfo($doc['nama_file'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                echo '📷';
                            } elseif ($ext === 'pdf') {
                                echo '📄';
                            } else {
                                echo '📎';
                            }
                            echo '</div>';
                            echo '<div class="document-info">';
                            echo '<div class="document-name">' . htmlspecialchars($doc['nama_file']) . '</div>';
                            echo '<div class="document-date">Diunggah: ' . date('d-m-Y H:i', strtotime($doc['tanggal_upload'])) . '</div>';
                            echo '</div>';
                            echo '<div class="document-actions">';
                            echo '<a href="' . str_replace('../', '../../', $doc['path_file']) . '" target="_blank" class="btn-small">Lihat</a>';
                            echo '</div>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p>Tidak ada dokumen pendukung</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="detail-section">
                <h3>Riwayat Status</h3>
                <div class="timeline">
                    <?php
                    if (!empty($timeline_data)) {
                        foreach ($timeline_data as $item) {
                            echo '<div class="timeline-item">';
                            echo '<div class="timeline-date">' . date('d-m-Y H:i', strtotime($item['tanggal_perubahan'])) . '</div>';
                            echo '<div class="timeline-title">' . ucfirst($item['status']) . '</div>';
                            echo '<div class="timeline-desc">' . htmlspecialchars($item['catatan']) . '</div>';
                            if (!empty($item['changed_by_name'])) {
                                echo '<div class="timeline-by">Oleh: ' . htmlspecialchars($item['changed_by_name']) . '</div>';
                            }
                            echo '</div>';
                        }
                    } else {
                        echo '<p>Belum ada riwayat status</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="status-actions">
                <?php if ($pengajuan_data['status'] == 'selesai' && !empty($pengajuan_data['dokumen_hasil'])): ?>
                    <a href="download.php?file=<?php echo urlencode($pengajuan_data['dokumen_hasil']); ?>" class="btn">Unduh Dokumen Hasil</a>
                <?php else: ?>
                    <button class="btn" onclick="window.print()">Cetak Halaman</button>
                <?php endif; ?>
                <a href="pengajuan.php" class="btn btn-outline">Ajukan Dokumen Baru</a>
                <?php if ($pengajuan_data['status'] === 'diajukan' && $is_owner): ?>
                    <a href="cancel-pengajuan.php?id=<?php echo $pengajuan_id; ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan pengajuan ini?')">Batalkan Pengajuan</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>



<!-- Tambahkan file print.php untuk menangani cetak dokumen -->


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
                <li><a href="index.html">Beranda</a></li>
                <li><a href="layanan-pembayaran.html">Layanan</a></li>
                <li><a href="pengajuan.html">Pengajuan</a></li>
                <li><a href="transparansi.html">Transparansi</a></li>
                <li><a href="berita.html">Berita & Pengumuman</a></li>
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
        &copy; 2025 SIPANDAI - Sistem Informasi Pelayanan Administrasi Desa. Hak Cipta Dilindungi.
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date
        const currentDate = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        
        if (document.getElementById('current-date')) {
            document.getElementById('current-date').textContent = currentDate.toLocaleDateString('id-ID', options);
        }

        // Toggling search form fields based on search type
        const searchTypeSelect = document.getElementById('search_type');
        if (searchTypeSelect) {
            searchTypeSelect.addEventListener('change', function() {
                const searchNomor = document.querySelector('.search-nomor');
                const searchNik = document.querySelector('.search-nik');
                
                if (this.value === 'nomor') {
                    searchNomor.style.display = 'block';
                    searchNik.style.display = 'none';
                    document.getElementById('nik').value = '';
                } else {
                    searchNomor.style.display = 'none';
                    searchNik.style.display = 'block';
                    document.getElementById('nomor').value = '';
                }
            });
        }
    });
</script>
</body>

</html>