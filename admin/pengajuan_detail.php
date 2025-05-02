<?php
// Include necessary files
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa')) {
    $_SESSION['login_error'] = 'Anda tidak memiliki akses ke halaman ini.';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['pengajuan_error'] = 'ID Pengajuan tidak valid.';
    redirect('pengajuan.php');
}

$pengajuan_id = (int)$_GET['id'];

// Get application details
$pengajuan_query = "SELECT pd.*, jd.nama_dokumen, jd.persyaratan, jd.estimasi_waktu, 
                   u.nama, u.nik, u.email, u.nomor_telepon, u.alamat
                   FROM pengajuan_dokumen pd
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                   JOIN users u ON pd.user_id = u.user_id
                   WHERE pd.pengajuan_id = '$pengajuan_id'";
$pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

// Check if application exists
if (mysqli_num_rows($pengajuan_result) == 0) {
    $_SESSION['pengajuan_error'] = 'Data pengajuan tidak ditemukan.';
    redirect('pengajuan.php');
}

$pengajuan = mysqli_fetch_assoc($pengajuan_result);

// Get uploaded requirements documents
$persyaratan_query = "SELECT * FROM dokumen_persyaratan WHERE pengajuan_id = '$pengajuan_id' ORDER BY jenis_persyaratan ASC";
$persyaratan_result = mysqli_query($koneksi, $persyaratan_query);

// Get application history
$riwayat_query = "SELECT rp.*, u.nama as petugas
                 FROM riwayat_pengajuan rp
                 LEFT JOIN users u ON rp.changed_by = u.user_id
                 WHERE rp.pengajuan_id = '$pengajuan_id'
                 ORDER BY rp.tanggal_perubahan ASC";
$riwayat_result = mysqli_query($koneksi, $riwayat_query);

// Process status update if submitted
if (isset($_POST['update_status'])) {
    $new_status = sanitizeInput($_POST['status']);
    $catatan = sanitizeInput($_POST['catatan']);
    
    // Start transaction
    mysqli_begin_transaction($koneksi);
    
    try {
        // Update status
        $update_query = "UPDATE pengajuan_dokumen 
                        SET status = '$new_status', catatan = '$catatan'";
        
        // If status is completed, set completion date
        if ($new_status == 'selesai') {
            $update_query .= ", tanggal_selesai = NOW()";
        }
        
        $update_query .= " WHERE pengajuan_id = '$pengajuan_id'";
        
        if (!mysqli_query($koneksi, $update_query)) {
            throw new Exception("Gagal mengupdate status pengajuan: " . mysqli_error($koneksi));
        }
        
        // Add to history
        $history_query = "INSERT INTO riwayat_pengajuan (pengajuan_id, status, catatan, changed_by)
                         VALUES ('$pengajuan_id', '$new_status', '$catatan', '$user_id')";
        
        if (!mysqli_query($koneksi, $history_query)) {
            throw new Exception("Gagal mencatat riwayat perubahan: " . mysqli_error($koneksi));
        }
        
        // Create notification for the applicant
        $status_text = "";
        switch ($new_status) {
            case 'diajukan':
                $status_text = "menunggu verifikasi";
                break;
            case 'verifikasi':
                $status_text = "dalam proses verifikasi";
                break;
            case 'proses':
                $status_text = "sedang diproses";
                break;
            case 'selesai':
                $status_text = "telah selesai";
                break;
            case 'ditolak':
                $status_text = "ditolak";
                break;
        }
        
        $judul_notif = "Status Pengajuan Berubah";
        $pesan_notif = "Pengajuan dokumen {$pengajuan['nama_dokumen']} dengan nomor {$pengajuan['nomor_pengajuan']} {$status_text}.";
        
        if (!empty($catatan)) {
            $pesan_notif .= " Catatan: $catatan";
        }
        
        $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link)
                       VALUES ('{$pengajuan['user_id']}', '$judul_notif', '$pesan_notif', 'pengajuan', 'detail-pengajuan.php?id=$pengajuan_id')";
        
        if (!mysqli_query($koneksi, $notif_query)) {
            throw new Exception("Gagal membuat notifikasi: " . mysqli_error($koneksi));
        }
        
        // Commit transaction
        mysqli_commit($koneksi);
        
        $_SESSION['pengajuan_success'] = "Status pengajuan berhasil diperbarui.";
        redirect("pengajuan_detail.php?id=$pengajuan_id");
        
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($koneksi);
        $_SESSION['pengajuan_error'] = $e->getMessage();
        redirect("pengajuan_detail.php?id=$pengajuan_id");
    }
}

// Process document upload if submitted
if (isset($_POST['upload_dokumen'])) {
    if (isset($_FILES['dokumen_hasil']) && $_FILES['dokumen_hasil']['error'] == 0) {
        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $file_name = $_FILES['dokumen_hasil']['name'];
        $file_size = $_FILES['dokumen_hasil']['size'];
        $file_tmp = $_FILES['dokumen_hasil']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file extension
        if (in_array($file_ext, $allowed_ext)) {
            // Validate file size (max 5MB)
            if ($file_size <= 5242880) {
                // Generate unique filename
                $new_file_name = 'doc_' . $pengajuan['nomor_pengajuan'] . '_' . time() . '.' . $file_ext;
                $upload_path = '../uploads/hasil/' . $new_file_name;
                
                // Ensure directory exists
                if (!file_exists('../uploads/hasil/')) {
                    mkdir('../uploads/hasil/', 0777, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Update database
                    $update_query = "UPDATE pengajuan_dokumen 
                                    SET dokumen_hasil = '$new_file_name', status = 'selesai', tanggal_selesai = NOW() 
                                    WHERE pengajuan_id = '$pengajuan_id'";
                    
                    if (mysqli_query($koneksi, $update_query)) {
                        // Add to history
                        $history_query = "INSERT INTO riwayat_pengajuan (pengajuan_id, status, catatan, changed_by)
                                         VALUES ('$pengajuan_id', 'selesai', 'Dokumen hasil telah diunggah', '$user_id')";
                        mysqli_query($koneksi, $history_query);
                        
                        // Create notification
                        $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link)
                                       VALUES ('{$pengajuan['user_id']}', 'Dokumen Selesai', 
                                       'Pengajuan dokumen {$pengajuan['nama_dokumen']} dengan nomor {$pengajuan['nomor_pengajuan']} telah selesai. Dokumen dapat diunduh atau diambil di kantor desa.', 
                                       'pengajuan', 'detail-pengajuan.php?id=$pengajuan_id')";
                        mysqli_query($koneksi, $notif_query);
                        
                        $_SESSION['pengajuan_success'] = "Dokumen hasil berhasil diunggah.";
                    } else {
                        $_SESSION['pengajuan_error'] = "Gagal memperbarui data pengajuan.";
                    }
                } else {
                    $_SESSION['pengajuan_error'] = "Gagal mengunggah file.";
                }
            } else {
                $_SESSION['pengajuan_error'] = "Ukuran file terlalu besar (maksimal 5MB).";
            }
        } else {
            $_SESSION['pengajuan_error'] = "Jenis file tidak diizinkan.";
        }
        
        redirect("pengajuan_detail.php?id=$pengajuan_id");
    } else {
        $_SESSION['pengajuan_error'] = "Pilih file untuk diunggah.";
        redirect("pengajuan_detail.php?id=$pengajuan_id");
    }
}

// Success message
$success_message = '';
if (isset($_SESSION['pengajuan_success'])) {
    $success_message = $_SESSION['pengajuan_success'];
    unset($_SESSION['pengajuan_success']);
}

// Error message
$error_message = '';
if (isset($_SESSION['pengajuan_error'])) {
    $error_message = $_SESSION['pengajuan_error'];
    unset($_SESSION['pengajuan_error']);
}

// Format dates
$tanggal_pengajuan = date('d-m-Y H:i', strtotime($pengajuan['tanggal_pengajuan']));
$tanggal_selesai = !empty($pengajuan['tanggal_selesai']) ? date('d-m-Y H:i', strtotime($pengajuan['tanggal_selesai'])) : '-';

// Calculate deadline
$est_days = (int)$pengajuan['estimasi_waktu'];
$pengajuan_date = new DateTime($pengajuan['tanggal_pengajuan']);
$deadline = clone $pengajuan_date;
$deadline->modify("+$est_days days");
$deadline_text = $deadline->format('d-m-Y');

// Check if overdue
$is_overdue = false;
if ($pengajuan['status'] != 'selesai' && $pengajuan['status'] != 'ditolak') {
    $today = new DateTime();
    if ($today > $deadline) {
        $is_overdue = true;
        $days_overdue = $today->diff($deadline)->days;
    }
}

// Set page title
$page_title = "Detail Pengajuan";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">

    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Detail Pengajuan Dokumen</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; 
                <a href="pengajuan.php">Pengajuan Dokumen</a> &raquo; 
                Detail Pengajuan
            </nav>
        </div>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Status & Actions -->
        <div class="detail-header">
            <div class="detail-status">
                <?php 
                $status_class = "";
                $status_text = "";
                switch ($pengajuan['status']) {
                    case 'diajukan':
                        $status_class = "status-pending";
                        $status_text = "Menunggu Verifikasi";
                        break;
                    case 'verifikasi':
                        $status_class = "status-processing";
                        $status_text = "Verifikasi";
                        break;
                    case 'proses':
                        $status_class = "status-processing";
                        $status_text = "Sedang Diproses";
                        break;
                    case 'selesai':
                        $status_class = "status-completed";
                        $status_text = "Selesai";
                        break;
                    case 'ditolak':
                        $status_class = "status-rejected";
                        $status_text = "Ditolak";
                        break;
                    default:
                        $status_class = "status-pending";
                        $status_text = "Menunggu";
                }
                ?>
                <span class="detail-label">Status:</span>
                <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                
                <?php if ($is_overdue): ?>
                <span class="badge badge-danger">Terlambat <?php echo $days_overdue; ?> hari</span>
                <?php endif; ?>
            </div>
            
            <div class="detail-actions">
                <?php if ($pengajuan['status'] != 'selesai' && $pengajuan['status'] != 'ditolak'): ?>
                <button class="btn btn-primary" data-toggle="modal" data-target="#statusModal">
                    <span class="btn-icon">🔄</span> Update Status
                </button>
                <?php endif; ?>
                
                <?php if ($pengajuan['status'] == 'proses'): ?>
                <button class="btn btn-success" data-toggle="modal" data-target="#uploadModal">
                    <span class="btn-icon">📄</span> Upload Dokumen Hasil
                </button>
                <?php endif; ?>
                
                <a href="pengajuan.php" class="btn btn-secondary">
                    <span class="btn-icon">⬅️</span> Kembali ke Daftar
                </a>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="detail-content">
            <!-- Application Info -->
            <div class="detail-section">
                <h3 class="section-title">Informasi Pengajuan</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">No. Pengajuan:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['nomor_pengajuan']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Jenis Dokumen:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['nama_dokumen']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal Pengajuan:</span>
                        <span class="info-value"><?php echo $tanggal_pengajuan; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estimasi Waktu:</span>
                        <span class="info-value"><?php echo $pengajuan['estimasi_waktu']; ?> hari</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tenggat Waktu:</span>
                        <span class="info-value <?php echo $is_overdue ? 'text-danger' : ''; ?>"><?php echo $deadline_text; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal Selesai:</span>
                        <span class="info-value"><?php echo $tanggal_selesai; ?></span>
                    </div>
                    <?php if (!empty($pengajuan['catatan'])): ?>
                    <div class="info-item full-width">
                        <span class="info-label">Catatan:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($pengajuan['catatan'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pengajuan['dokumen_hasil'])): ?>
                    <div class="info-item full-width">
                        <span class="info-label">Dokumen Hasil:</span>
                        <span class="info-value">
                            <a href="../uploads/hasil/<?php echo $pengajuan['dokumen_hasil']; ?>" target="_blank" class="btn-sm btn-info">
                                <span class="btn-icon">📄</span> Lihat Dokumen
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Applicant Info -->
            <div class="detail-section">
                <h3 class="section-title">Informasi Pemohon</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nama:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['nama']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">NIK:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['nik']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">No. Telepon:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['nomor_telepon']); ?></span>
                    </div>
                    <div class="info-item full-width">
                        <span class="info-label">Alamat:</span>
                        <span class="info-value"><?php echo htmlspecialchars($pengajuan['alamat'] ?? '-'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Requirements Documents -->
            <div class="detail-section">
                <h3 class="section-title">Dokumen Persyaratan</h3>
                <?php if (mysqli_num_rows($persyaratan_result) > 0): ?>
                <div class="documents-list">
                    <?php while ($doc = mysqli_fetch_assoc($persyaratan_result)): ?>
                    <div class="document-item">
                        <div class="document-icon">📄</div>
                        <div class="document-info">
                            <h4><?php echo htmlspecialchars($doc['jenis_persyaratan']); ?></h4>
                            <p>Diunggah: <?php echo date('d-m-Y H:i', strtotime($doc['tanggal_upload'])); ?></p>
                        </div>
                        <a href="../uploads/persyaratan/<?php echo $doc['path_file']; ?>" target="_blank" class="btn-sm btn-info">Lihat</a>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>Tidak ada dokumen persyaratan yang diunggah.</p>
                </div>
                <?php endif; ?>
                
                <!-- Requirements List -->
                <div class="requirements-container">
                    <h4>Persyaratan yang Diperlukan:</h4>
                    <div class="requirements-text">
                        <?php echo nl2br(htmlspecialchars($pengajuan['persyaratan'])); ?>
                    </div>
                </div>
            </div>
            
            <!-- Application History -->
            <div class="detail-section">
                <h3 class="section-title">Riwayat Pengajuan</h3>
                <?php if (mysqli_num_rows($riwayat_result) > 0): ?>
                <div class="timeline">
                    <?php while ($history = mysqli_fetch_assoc($riwayat_result)): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <?php 
                                $history_status = "";
                                switch ($history['status']) {
                                    case 'diajukan':
                                        $history_status = "Pengajuan Diterima";
                                        break;
                                    case 'verifikasi':
                                        $history_status = "Verifikasi";
                                        break;
                                    case 'proses':
                                        $history_status = "Diproses";
                                        break;
                                    case 'selesai':
                                        $history_status = "Selesai";
                                        break;
                                    case 'ditolak':
                                        $history_status = "Ditolak";
                                        break;
                                    default:
                                        $history_status = ucfirst($history['status']);
                                }
                                ?>
                                <h4><?php echo $history_status; ?></h4>
                                <span class="timeline-date"><?php echo date('d-m-Y H:i', strtotime($history['tanggal_perubahan'])); ?></span>
                            </div>
                            <?php if (!empty($history['catatan'])): ?>
                            <div class="timeline-note">
                                <?php echo nl2br(htmlspecialchars($history['catatan'])); ?>
                            </div>
                            <?php endif; ?>
                            <div class="timeline-footer">
                                <?php if (!empty($history['petugas'])): ?>
                                <span class="timeline-by">Oleh: <?php echo htmlspecialchars($history['petugas']); ?></span>
                                <?php else: ?>
                                <span class="timeline-by">Oleh: Sistem</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>Belum ada riwayat perubahan status.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Status Pengajuan</h3>
            <span class="close" data-dismiss="modal">&times;</span>
        </div>
        <form action="pengajuan_detail.php?id=<?php echo $pengajuan_id; ?>" method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label for="status">Status Baru:</label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="">- Pilih Status -</option>
                        <option value="diajukan" <?php echo $pengajuan['status'] == 'diajukan' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                        <option value="verifikasi" <?php echo $pengajuan['status'] == 'verifikasi' ? 'selected' : ''; ?>>Verifikasi</option>
                        <option value="proses" <?php echo $pengajuan['status'] == 'proses' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="selesai" <?php echo $pengajuan['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="ditolak" <?php echo $pengajuan['status'] == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="catatan">Catatan:</label>
                    <textarea name="catatan" id="catatan" class="form-control" rows="4" placeholder="Tambahkan catatan jika diperlukan"><?php echo htmlspecialchars($pengajuan['catatan']); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" name="update_status" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal" id="uploadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Dokumen Hasil</h3>
            <span class="close" data-dismiss="modal">&times;</span>
        </div>
        <form action="pengajuan_detail.php?id=<?php echo $pengajuan_id; ?>" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-group">
                    <label for="dokumen_hasil">Dokumen Hasil:</label>
                    <input type="file" name="dokumen_hasil" id="dokumen_hasil" class="form-control" required>
                    <small class="form-text">Format yang diizinkan: PDF, DOC, DOCX, JPG, JPEG, PNG. Ukuran maksimal: 5MB.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" name="upload_dokumen" class="btn btn-primary">Upload</button>
            </div>
        </form>
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
/* Content Sections */
.detail-content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.detail-section {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.section-title {
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #efefef;
    font-size: 1.1rem;
    color: #343a40;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 3px;
}

.info-value {
    font-size: 1rem;
    color: #212529;
}

.text-danger {
    color: #dc3545;
}

/* Documents List */
.documents-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.document-item {
    display: flex;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 12px;
    border: 1px solid #e9ecef;
}

.document-icon {
    font-size: 1.5rem;
    margin-right: 15px;
    color: #6c757d;
}

.document-info {
    flex: 1;
}

.document-info h4 {
    margin: 0 0 5px;
    font-size: 0.95rem;
    color: #343a40;
}

.document-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Requirements List */
.requirements-container {
    margin-top: 20px;
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.requirements-container h4 {
    margin: 0 0 10px;
    font-size: 1rem;
    color: #343a40;
}

.requirements-text {
    font-size: 0.95rem;
    color: #495057;
    white-space: pre-line;
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 10px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    top: 5px;
    left: -30px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: #007bff;
    border: 3px solid white;
    z-index: 1;
}

.timeline-content {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.timeline-header h4 {
    margin: 0;
    font-size: 1rem;
    color: #343a40;
}

.timeline-date {
    font-size: 0.85rem;
    color: #6c757d;
}

.timeline-note {
    font-size: 0.95rem;
    color: #495057;
    padding: 10px;
    background-color: #fff;
    border-radius: 4px;
    margin-bottom: 10px;
    border: 1px solid #e9ecef;
}

.timeline-footer {
    font-size: 0.85rem;
    color: #6c757d;
    text-align: right;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: #6c757d;
    background-color: #f8f9fa;
    border-radius: 6px;
    border: 1px dashed #dee2e6;
}

.empty-state p {
    margin: 0;
    font-size: 0.95rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    animation: modalIn 0.3s;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: #343a40;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

/* Form Styles */
.form-group {
    margin-bottom: 15px;
}

.form-control {
    display: block;
    width: 100%;
    padding: 8px 12px;
    font-size: 0.95rem;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

select.form-control {
    height: calc(2.25rem + 2px);
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Animation */
@keyframes modalIn {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive Styles */
@media (max-width: 768px) {
    .detail-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .detail-status {
        margin-bottom: 15px;
    }
    
    .detail-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .detail-actions .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .documents-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality
    const modals = document.querySelectorAll('.modal');
    const openBtns = document.querySelectorAll('[data-toggle="modal"]');
    const closeBtns = document.querySelectorAll('[data-dismiss="modal"]');
    
    // Open modal
    openBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetModal = document.querySelector(this.getAttribute('data-target'));
            if (targetModal) {
                targetModal.style.display = 'block';
            }
        });
    });
    
    // Close modal with close button
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>