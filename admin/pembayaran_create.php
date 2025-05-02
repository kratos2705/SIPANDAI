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
$user_name = $_SESSION['user_nama'];

// Initialize variables
$errors = [];
$success_message = '';
$tagihan_id = isset($_GET['tagihan_id']) ? intval($_GET['tagihan_id']) : 0;

// If no tagihan_id, redirect to pembayaran list
if ($tagihan_id <= 0) {
    $_SESSION['error_message'] = 'ID tagihan tidak valid.';
    redirect('pembayaran.php');
}

// Get tagihan details
$query = "SELECT tr.*, u.nama AS nama_warga, u.nik, jr.nama_retribusi 
          FROM tagihan_retribusi tr
          JOIN users u ON tr.user_id = u.user_id
          JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
          WHERE tr.tagihan_id = ?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, 'i', $tagihan_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error_message'] = 'Tagihan tidak ditemukan.';
    redirect('pembayaran.php');
}

$tagihan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Check if bill is already paid
if ($tagihan['status'] == 'lunas') {
    $_SESSION['error_message'] = 'Tagihan ini sudah lunas.';
    redirect('tagihan_detail.php?id=' . $tagihan_id);
}

// Get existing payments and calculate total paid
$query_payments = "SELECT SUM(jumlah_bayar) as total_dibayar 
                  FROM pembayaran_retribusi 
                  WHERE tagihan_id = ? AND status = 'berhasil'";
$stmt_payments = mysqli_prepare($koneksi, $query_payments);
mysqli_stmt_bind_param($stmt_payments, 'i', $tagihan_id);
mysqli_stmt_execute($stmt_payments);
$result_payments = mysqli_stmt_get_result($stmt_payments);
$payments_data = mysqli_fetch_assoc($result_payments);
$total_dibayar = $payments_data['total_dibayar'] ?: 0;
mysqli_stmt_close($stmt_payments);

// Calculate remaining amount
$total_tagihan = $tagihan['nominal'] + $tagihan['denda'];
$sisa_tagihan = $total_tagihan - $total_dibayar;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_bayar = isset($_POST['tanggal_bayar']) ? $_POST['tanggal_bayar'] : date('Y-m-d H:i:s');
    $jumlah_bayar = str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['jumlah_bayar']));
    $metode_pembayaran = isset($_POST['metode_pembayaran']) ? trim($_POST['metode_pembayaran']) : '';
    $nomor_referensi = isset($_POST['nomor_referensi']) ? trim($_POST['nomor_referensi']) : '';
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';
    $status = isset($_POST['konfirmasi_langsung']) && $_POST['konfirmasi_langsung'] == '1' ? 'berhasil' : 'pending';
    
    // Validation
    if (empty($tanggal_bayar) || !validateDateTime($tanggal_bayar)) {
        $errors[] = 'Tanggal bayar tidak valid.';
    }
    
    if (!is_numeric($jumlah_bayar) || $jumlah_bayar <= 0) {
        $errors[] = 'Jumlah bayar harus berupa angka positif.';
    }
    
    if ($jumlah_bayar > $sisa_tagihan) {
        $errors[] = 'Jumlah bayar melebihi sisa tagihan.';
    }
    
    if (empty($metode_pembayaran)) {
        $errors[] = 'Metode pembayaran harus dipilih.';
    }
    
    // Handle file upload if there's a file
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['name']) {
        $file_name = $_FILES['bukti_pembayaran']['name'];
        $file_size = $_FILES['bukti_pembayaran']['size'];
        $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
        $file_type = $_FILES['bukti_pembayaran']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = 'Format file bukti pembayaran tidak didukung. Format yang didukung: ' . implode(', ', $allowed_extensions);
        }
        
        if ($file_size > 2097152) { // 2MB
            $errors[] = 'Ukuran file bukti pembayaran tidak boleh lebih dari 2MB.';
        }
        
        if (empty($errors)) {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/bukti_pembayaran/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $bukti_pembayaran = 'bukti_' . $tagihan_id . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $bukti_pembayaran;
            
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $errors[] = 'Gagal mengunggah file bukti pembayaran.';
            }
        }
    }
    
    // If no errors, create payment record
    if (empty($errors)) {
        $confirmed_by = null;
        $confirmed_at = null;
        
        // If direct confirmation, set the confirmator
        if ($status == 'berhasil') {
            $confirmed_by = $user_id;
            $confirmed_at = date('Y-m-d H:i:s');
        }
        
        $query = "INSERT INTO pembayaran_retribusi (
                    tagihan_id, tanggal_bayar, jumlah_bayar, metode_pembayaran, 
                    bukti_pembayaran, nomor_referensi, status, catatan, 
                    confirmed_by, confirmed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param(
            $stmt, 
            'isdsssssis', 
            $tagihan_id, $tanggal_bayar, $jumlah_bayar, $metode_pembayaran, 
            $bukti_pembayaran, $nomor_referensi, $status, $catatan, 
            $confirmed_by, $confirmed_at
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $pembayaran_id = mysqli_insert_id($koneksi);
            
            // If payment is confirmed, update tagihan status if needed
            if ($status == 'berhasil') {
                // Get updated total paid
                $query_total = "SELECT SUM(jumlah_bayar) as total_dibayar 
                              FROM pembayaran_retribusi 
                              WHERE tagihan_id = ? AND status = 'berhasil'";
                $stmt_total = mysqli_prepare($koneksi, $query_total);
                mysqli_stmt_bind_param($stmt_total, 'i', $tagihan_id);
                mysqli_stmt_execute($stmt_total);
                $result_total = mysqli_stmt_get_result($stmt_total);
                $total_data = mysqli_fetch_assoc($result_total);
                $new_total_paid = $total_data['total_dibayar'] ?: 0;
                mysqli_stmt_close($stmt_total);
                
                // If total paid equals or exceeds total bill, mark as paid
                if ($new_total_paid >= $total_tagihan) {
                    $update_query = "UPDATE tagihan_retribusi SET status = 'lunas' WHERE tagihan_id = ?";
                    $stmt_update = mysqli_prepare($koneksi, $update_query);
                    mysqli_stmt_bind_param($stmt_update, 'i', $tagihan_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                } 
                // If partially paid, update to process
                else if ($new_total_paid > 0 && $tagihan['status'] == 'belum_bayar') {
                    $update_query = "UPDATE tagihan_retribusi SET status = 'proses' WHERE tagihan_id = ?";
                    $stmt_update = mysqli_prepare($koneksi, $update_query);
                    mysqli_stmt_bind_param($stmt_update, 'i', $tagihan_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                }
                
                // Create notification for user
                $notif_judul = "Pembayaran Dikonfirmasi";
                $notif_pesan = "Pembayaran Anda untuk tagihan " . $tagihan['nama_retribusi'] . " periode " . date('F Y', strtotime($tagihan['tanggal_tagihan'])) . " telah dikonfirmasi.";
                $notif_pesan .= "\n\nJumlah: Rp " . number_format($jumlah_bayar, 0, ',', '.');
                $notif_pesan .= "\nMetode: " . $metode_pembayaran;
                $notif_pesan .= "\nTanggal: " . date('d-m-Y H:i', strtotime($tanggal_bayar));
                
                if ($new_total_paid >= $total_tagihan) {
                    $notif_pesan .= "\n\nStatus tagihan Anda sekarang: LUNAS";
                } else {
                    $notif_pesan .= "\n\nSisa tagihan: Rp " . number_format($total_tagihan - $new_total_paid, 0, ',', '.');
                }
                
                $query_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                                VALUES (?, ?, ?, 'pembayaran', '/tagihan_detail.php?id=".$tagihan_id."')";
                $stmt_notif = mysqli_prepare($koneksi, $query_notif);
                mysqli_stmt_bind_param($stmt_notif, 'iss', $tagihan['user_id'], $notif_judul, $notif_pesan);
                mysqli_stmt_execute($stmt_notif);
                mysqli_stmt_close($stmt_notif);
            }
            
            // Log activity
            $aktivitas = "Menambahkan pembayaran untuk tagihan #" . $tagihan_id . " (" . $tagihan['nama_retribusi'] . ")";
            $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($koneksi, $query_log);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($stmt_log, 'iss', $user_id, $aktivitas, $ip_address);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
            
            $success_message = 'Pembayaran berhasil ditambahkan.';
            
            // Redirect to pembayaran detail
            $_SESSION['success_message'] = $success_message;
            redirect('pembayaran_detail.php?id=' . $pembayaran_id);
        } else {
            $errors[] = 'Terjadi kesalahan: ' . mysqli_error($koneksi);
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Helper function to validate datetime format
function validateDateTime($datetime, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $datetime);
    return $d && $d->format($format) === $datetime;
}

// Prepare variables for page
$page_title = "Tambah Pembayaran";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Tambah Pembayaran Retribusi</h2>
        <div class="admin-header-actions">
            <a href="tagihan_detail.php?id=<?php echo $tagihan_id; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Sukses!</strong> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Form Column -->
        <div class="col-md-8">
            <div class="form-card">
                <div class="card-header">
                    <h3>Form Pembayaran</h3>
                </div>
                <form class="admin-form" method="POST" action="" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tanggal_bayar">Tanggal Pembayaran <span class="required">*</span></label>
                                <input type="datetime-local" id="tanggal_bayar" name="tanggal_bayar" class="form-control" 
                                       value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                <small class="form-text">Tanggal dan waktu pembayaran dilakukan.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jumlah_bayar">Jumlah Bayar <span class="required">*</span></label>
                                <input type="text" id="jumlah_bayar" name="jumlah_bayar" class="form-control currency-input" 
                                       value="<?php echo number_format($sisa_tagihan, 0, ',', '.'); ?>" required>
                                <small class="form-text">Jumlah yang dibayarkan. Maksimal: Rp <?php echo number_format($sisa_tagihan, 0, ',', '.'); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="metode_pembayaran">Metode Pembayaran <span class="required">*</span></label>
                                <select id="metode_pembayaran" name="metode_pembayaran" class="form-control" required>
                                    <option value="">-- Pilih Metode Pembayaran --</option>
                                    <option value="Cash">Cash / Tunai</option>
                                    <option value="Transfer Bank">Transfer Bank</option>
                                    <option value="QRIS">QRIS</option>
                                    <option value="E-Wallet">E-Wallet (OVO, GoPay, Dana, dll)</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nomor_referensi">Nomor Referensi</label>
                                <input type="text" id="nomor_referensi" name="nomor_referensi" class="form-control">
                                <small class="form-text">Nomor referensi pembayaran (opsional).</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bukti_pembayaran">Bukti Pembayaran</label>
                        <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" class="form-control-file">
                        <small class="form-text">Format yang didukung: JPG, JPEG, PNG, PDF. Maksimal 2MB.</small>
                    </div>

                    <div class="form-group">
                        <label for="catatan">Catatan</label>
                        <textarea id="catatan" name="catatan" class="form-control" rows="3"></textarea>
                        <small class="form-text">Informasi tambahan tentang pembayaran ini (opsional).</small>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="konfirmasi_langsung" name="konfirmasi_langsung" value="1" checked>
                            <label for="konfirmasi_langsung">Konfirmasi pembayaran langsung</label>
                        </div>
                        <small class="form-text">Jika dicentang, pembayaran akan langsung dikonfirmasi dan status tagihan akan diperbarui jika sudah lunas.</small>
                    </div>

                    <div class="form-actions">
                        <a href="tagihan_detail.php?id=<?php echo $tagihan_id; ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tagihan Info Column -->
        <div class="col-md-4">
            <div class="detail-card">
                <div class="card-header">
                    <h3>Informasi Tagihan</h3>
                </div>
                <div class="card-body">
                    <div class="tagihan-info">
                        <div class="tagihan-header">
                            <h4><?php echo htmlspecialchars($tagihan['nama_retribusi']); ?></h4>
                            <div class="tagihan-status <?php echo getStatusClass($tagihan['status']); ?>">
                                <?php echo getStatusText($tagihan['status']); ?>
                            </div>
                        </div>
                        
                        <div class="tagihan-period">
                            Periode: <?php echo date('F Y', strtotime($tagihan['tanggal_tagihan'])); ?>
                        </div>
                        
                        <div class="tagihan-details">
                            <div class="detail-item">
                                <span class="detail-label">Warga</span>
                                <span class="detail-value"><?php echo htmlspecialchars($tagihan['nama_warga']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">NIK</span>
                                <span class="detail-value"><?php echo htmlspecialchars($tagihan['nik']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Tanggal Tagihan</span>
                                <span class="detail-value"><?php echo date('d-m-Y', strtotime($tagihan['tanggal_tagihan'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Jatuh Tempo</span>
                                <span class="detail-value"><?php echo date('d-m-Y', strtotime($tagihan['jatuh_tempo'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="tagihan-amount">
                            <div class="amount-item">
                                <span class="amount-label">Nominal</span>
                                <span class="amount-value">Rp <?php echo number_format($tagihan['nominal'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="amount-item">
                                <span class="amount-label">Denda</span>
                                <span class="amount-value">Rp <?php echo number_format($tagihan['denda'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="amount-item total">
                                <span class="amount-label">Total Tagihan</span>
                                <span class="amount-value">Rp <?php echo number_format($total_tagihan, 0, ',', '.'); ?></span>
                            </div>
                            <div class="amount-item">
                                <span class="amount-label">Total Dibayar</span>
                                <span class="amount-value text-success">Rp <?php echo number_format($total_dibayar, 0, ',', '.'); ?></span>
                            </div>
                            <div class="amount-item total">
                                <span class="amount-label">Sisa Tagihan</span>
                                <span class="amount-value text-danger">Rp <?php echo number_format($sisa_tagihan, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions for status display
function getStatusClass($status) {
    switch ($status) {
        case 'belum_bayar': return 'status-pending';
        case 'proses': return 'status-processing';
        case 'lunas': return 'status-completed';
        case 'telat': return 'status-rejected';
        default: return 'status-pending';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'belum_bayar': return 'Belum Bayar';
        case 'proses': return 'Proses';
        case 'lunas': return 'Lunas';
        case 'telat': return 'Telat';
        default: return 'Belum Bayar';
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format currency inputs
    const currencyInputs = document.querySelectorAll('.currency-input');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d]/g, '');
            if (value !== '') {
                value = parseInt(value, 10).toLocaleString('id-ID');
                this.value = value;
            }
        });
    });
    
    // Validate payment amount
    const jumlahBayarInput = document.getElementById('jumlah_bayar');
    const maxPayment = <?php echo $sisa_tagihan; ?>;
    
    jumlahBayarInput.addEventListener('change', function() {
        const value = parseInt(this.value.replace(/[^\d]/g, '') || 0);
        if (value > maxPayment) {
            alert('Jumlah bayar tidak boleh melebihi sisa tagihan (Rp ' + maxPayment.toLocaleString('id-ID') + ')');
            this.value = maxPayment.toLocaleString('id-ID');
        }
    });
    
    // Auto-hide alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert-success, .alert-danger');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }
});
</script>

<style>
/* Layout */
.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -0.75rem;
}

.col-md-8 {
    width: 66.666667%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

.col-md-4 {
    width: 33.333333%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

.col-md-6 {
    width: 50%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

/* Form Styles */
.form-card, .detail-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.card-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.card-body {
    padding: 1.25rem;
}

.admin-form {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    margin-bottom: 0.75rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    border: 1px solid #d1d3e2;
    border-radius: 4px;
    color: #6e707e;
    transition: border-color 0.2s;
}

.form-control-file {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    border: 1px solid #d1d3e2;
    border-radius: 4px;
    color: #6e707e;
    transition: border-color 0.2s;
    background-color: #f8f9fc;
}

.form-control:focus, .form-control-file:focus {
    border-color: #4e73df;
    outline: none;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.form-text {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #6c757d;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -0.75rem;
    margin-bottom: 1rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1rem;
}

.required {
    color: #e74a3b;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

/* Tagihan Info Styles */
.tagihan-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tagihan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tagihan-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.tagihan-status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.status-pending {
    background-color: #fff4db;
    color: #d39e00;
}

.status-processing {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-completed {
    background-color: #d4edda;
    color: #155724;
}

.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.tagihan-period {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: -0.5rem;
}

.tagihan-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-bottom: 1px solid #e3e6f0;
    padding-bottom: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.detail-label {
    color: #6c757d;
    font-weight: 500;
}

.detail-value {
    font-weight: 500;
    color: #333;
}

.tagihan-amount {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.amount-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.amount-item.total {
    border-top: 1px solid #e3e6f0;
    padding-top: 0.5rem;
    margin-top: 0.25rem;
    font-weight: 600;
}

.amount-label {
    color: #6c757d;
}

.amount-value {
    font-weight: 600;
}

/* Alert Messages */
.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: opacity 0.5s;
}

.alert ul {
    margin: 0.5rem 0 0 1.5rem;
    padding: 0;
}

.alert-success {
    background-color: #d4edda;
    border-left: 4px solid #1cc88a;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-left: 4px solid #e74a3b;
    color: #721c24;
}

.alert-warning {
    background-color: #fff3cd;
    border-left: 4px solid #f6c23e;
    color: #856404;
}

/* Text Colors */
.text-success {
    color: #1cc88a;
}

.text-danger {
    color: #e74a3b;
}

.text-muted {
    color: #6c757d;
}

/* Button Styles */
.btn, .btn:visited {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 400;
    line-height: 1.5;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    background-color: #4e73df;
    color: white;
    text-decoration: none;
}

.btn:hover {
    background-color: #2e59d9;
    color: white;
}

.btn-secondary, .btn-secondary:visited {
    color: white;
    background-color: #6c757d;
    border-color: #6c757d;
    text-decoration: none;
}

.btn-secondary:hover {
    color: white;
    background-color: #5a6268;
    border-color: #545b62;
}

.btn-outline, .btn-outline:visited {
    color: #4e73df;
    background-color: transparent;
    border-color: #4e73df;
    text-decoration: none;
}

.btn-outline:hover {
    color: white;
    background-color: #4e73df;
    border-color: #4e73df;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-8, .col-md-4, .col-md-6 {
        width: 100%;
        padding: 0;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .admin-header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .admin-header-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<?php
// Include footer
include '../includes/admin-footer.php';
?>