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

// Get all active retribution types
$query_jenis = "SELECT jenis_retribusi_id, nama_retribusi, nominal, periode FROM jenis_retribusi WHERE is_active = TRUE ORDER BY nama_retribusi";
$result_jenis = mysqli_query($koneksi, $query_jenis);
$jenis_retribusi = [];
if (mysqli_num_rows($result_jenis) > 0) {
    while ($row = mysqli_fetch_assoc($result_jenis)) {
        $jenis_retribusi[] = $row;
    }
}

// Get users for dropdown (exclude admin and kepala_desa roles)
$query_users = "SELECT user_id, nama, nik FROM users WHERE role = 'warga' AND active = TRUE ORDER BY nama";
$result_users = mysqli_query($koneksi, $query_users);
$users = [];
if (mysqli_num_rows($result_users) > 0) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $users[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warga_id = isset($_POST['warga_id']) ? intval($_POST['warga_id']) : 0;
    $jenis_retribusi_id = isset($_POST['jenis_retribusi_id']) ? intval($_POST['jenis_retribusi_id']) : 0;
    $tanggal_tagihan = isset($_POST['tanggal_tagihan']) ? $_POST['tanggal_tagihan'] : date('Y-m-d');
    $jatuh_tempo = isset($_POST['jatuh_tempo']) ? $_POST['jatuh_tempo'] : '';
    $nominal = str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['nominal']));
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    
    // Validation
    if ($warga_id <= 0) {
        $errors[] = 'Pilih warga terlebih dahulu.';
    }
    
    if ($jenis_retribusi_id <= 0) {
        $errors[] = 'Pilih jenis retribusi terlebih dahulu.';
    }
    
    if (empty($tanggal_tagihan) || !validateDate($tanggal_tagihan)) {
        $errors[] = 'Tanggal tagihan tidak valid.';
    }
    
    if (empty($jatuh_tempo) || !validateDate($jatuh_tempo)) {
        $errors[] = 'Tanggal jatuh tempo tidak valid.';
    }
    
    if (strtotime($jatuh_tempo) < strtotime($tanggal_tagihan)) {
        $errors[] = 'Tanggal jatuh tempo tidak boleh sebelum tanggal tagihan.';
    }
    
    if (!is_numeric($nominal) || $nominal <= 0) {
        $errors[] = 'Nominal harus berupa angka positif.';
    }
    
    // Check if there's already an active bill for this user with the same retribution type for the same month
    if (empty($errors)) {
        $check_duplicate_query = "SELECT COUNT(*) AS count FROM tagihan_retribusi 
                                 WHERE user_id = ? AND jenis_retribusi_id = ? 
                                 AND MONTH(tanggal_tagihan) = MONTH(?) 
                                 AND YEAR(tanggal_tagihan) = YEAR(?)
                                 AND status != 'lunas'";
        $stmt_check = mysqli_prepare($koneksi, $check_duplicate_query);
        mysqli_stmt_bind_param($stmt_check, 'iiss', $warga_id, $jenis_retribusi_id, $tanggal_tagihan, $tanggal_tagihan);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $row_check = mysqli_fetch_assoc($result_check);
        
        if ($row_check['count'] > 0) {
            $errors[] = 'Warga ini sudah memiliki tagihan yang belum lunas untuk jenis retribusi dan bulan yang sama.';
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // If no errors, create the bill
    if (empty($errors)) {
        $query = "INSERT INTO tagihan_retribusi (user_id, jenis_retribusi_id, tanggal_tagihan, jatuh_tempo, nominal, status) 
                  VALUES (?, ?, ?, ?, ?, 'belum_bayar')";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, 'iissd', $warga_id, $jenis_retribusi_id, $tanggal_tagihan, $jatuh_tempo, $nominal);
        
        if (mysqli_stmt_execute($stmt)) {
            $tagihan_id = mysqli_insert_id($koneksi);
            
            // Create notification for the user
            $get_jenis_nama = "SELECT nama_retribusi FROM jenis_retribusi WHERE jenis_retribusi_id = ?";
            $stmt_jenis = mysqli_prepare($koneksi, $get_jenis_nama);
            mysqli_stmt_bind_param($stmt_jenis, 'i', $jenis_retribusi_id);
            mysqli_stmt_execute($stmt_jenis);
            $result_jenis = mysqli_stmt_get_result($stmt_jenis);
            $jenis_nama = mysqli_fetch_assoc($result_jenis)['nama_retribusi'];
            mysqli_stmt_close($stmt_jenis);
            
            $bulan_tahun = date('F Y', strtotime($tanggal_tagihan));
            $notif_judul = "Tagihan Baru: " . $jenis_nama;
            $notif_pesan = "Anda memiliki tagihan baru untuk " . $jenis_nama . " periode " . $bulan_tahun . " sebesar Rp " . number_format($nominal, 0, ',', '.') . ". Silakan melakukan pembayaran sebelum " . date('d-m-Y', strtotime($jatuh_tempo)) . ".";
            
            $query_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                            VALUES (?, ?, ?, 'tagihan', '/tagihan_detail.php?id=".$tagihan_id."')";
            $stmt_notif = mysqli_prepare($koneksi, $query_notif);
            mysqli_stmt_bind_param($stmt_notif, 'iss', $warga_id, $notif_judul, $notif_pesan);
            mysqli_stmt_execute($stmt_notif);
            mysqli_stmt_close($stmt_notif);
            
            // Log activity
            $get_warga_nama = "SELECT nama FROM users WHERE user_id = ?";
            $stmt_warga = mysqli_prepare($koneksi, $get_warga_nama);
            mysqli_stmt_bind_param($stmt_warga, 'i', $warga_id);
            mysqli_stmt_execute($stmt_warga);
            $result_warga = mysqli_stmt_get_result($stmt_warga);
            $warga_nama = mysqli_fetch_assoc($result_warga)['nama'];
            mysqli_stmt_close($stmt_warga);
            
            $aktivitas = "Membuat tagihan retribusi " . $jenis_nama . " untuk " . $warga_nama;
            $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($koneksi, $query_log);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($stmt_log, 'iss', $user_id, $aktivitas, $ip_address);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
            
            $success_message = 'Tagihan retribusi berhasil dibuat.';
            
            // Redirect to the bill detail page
            $_SESSION['success_message'] = $success_message;
            redirect('tagihan_detail.php?id=' . $tagihan_id);
        } else {
            $errors[] = 'Terjadi kesalahan: ' . mysqli_error($koneksi);
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Helper function to validate date format
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Prepare variables for page
$page_title = "Buat Tagihan Baru";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Buat Tagihan Retribusi Baru</h2>
        <div class="admin-header-actions">
            <a href="retribusi.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            <a href="generate_tagihan.php" class="btn"><i class="fas fa-cog"></i> Generate Tagihan Massal</a>
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

    <?php if (empty($jenis_retribusi)): ?>
    <div class="alert alert-warning">
        <strong>Perhatian!</strong> Belum ada jenis retribusi yang aktif. Silakan <a href="jenis_retribusi_form.php">tambahkan jenis retribusi</a> terlebih dahulu.
    </div>
    <?php endif; ?>

    <?php if (empty($users)): ?>
    <div class="alert alert-warning">
        <strong>Perhatian!</strong> Belum ada warga yang terdaftar. Silakan tambahkan data warga terlebih dahulu.
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="card-header">
            <h3>Form Tagihan Baru</h3>
        </div>
        <form class="admin-form" method="POST" action="">
            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="warga_id">Warga <span class="required">*</span></label>
                        <select id="warga_id" name="warga_id" class="form-control select2" required <?php echo empty($users) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Warga --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['nama'] . ' (' . $user['nik'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Pilih warga yang akan ditagih.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="jenis_retribusi_id">Jenis Retribusi <span class="required">*</span></label>
                        <select id="jenis_retribusi_id" name="jenis_retribusi_id" class="form-control" required <?php echo empty($jenis_retribusi) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Jenis Retribusi --</option>
                            <?php foreach ($jenis_retribusi as $jenis): ?>
                                <option value="<?php echo $jenis['jenis_retribusi_id']; ?>" data-nominal="<?php echo $jenis['nominal']; ?>" data-periode="<?php echo $jenis['periode']; ?>">
                                    <?php echo htmlspecialchars($jenis['nama_retribusi'] . ' - ' . ucfirst($jenis['periode'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Pilih jenis retribusi yang akan ditagihkan.</small>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_tagihan">Tanggal Tagihan <span class="required">*</span></label>
                        <input type="date" id="tanggal_tagihan" name="tanggal_tagihan" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        <small class="form-text">Tanggal pembuatan tagihan.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="jatuh_tempo">Tanggal Jatuh Tempo <span class="required">*</span></label>
                        <input type="date" id="jatuh_tempo" name="jatuh_tempo" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        <small class="form-text">Batas waktu pembayaran tagihan.</small>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nominal">Nominal <span class="required">*</span></label>
                        <input type="text" id="nominal" name="nominal" class="form-control currency-input" required>
                        <small class="form-text">Jumlah yang harus dibayarkan.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <input type="text" class="form-control" value="Belum Bayar" disabled>
                        <small class="form-text">Status awal tagihan akan diset sebagai "Belum Bayar".</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi (Opsional)</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3"></textarea>
                <small class="form-text">Informasi tambahan tentang tagihan ini (opsional).</small>
            </div>

            <div class="form-actions">
                <a href="retribusi.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn" <?php echo (empty($jenis_retribusi) || empty($users)) ? 'disabled' : ''; ?>>Buat Tagihan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format currency inputs
    const currencyInput = document.querySelector('.currency-input');
    currencyInput.addEventListener('input', function(e) {
        let value = this.value.replace(/[^\d]/g, '');
        if (value !== '') {
            value = parseInt(value, 10).toLocaleString('id-ID');
            this.value = value;
        }
    });
    
    // Set nominal when jenis retribusi changes
    const jenisRetribusiSelect = document.getElementById('jenis_retribusi_id');
    const nominalInput = document.getElementById('nominal');
    const tanggalTagihanInput = document.getElementById('tanggal_tagihan');
    const jatuhTempoInput = document.getElementById('jatuh_tempo');
    
    jenisRetribusiSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value !== '') {
            const nominal = selectedOption.getAttribute('data-nominal');
            const periode = selectedOption.getAttribute('data-periode');
            
            // Format nominal
            nominalInput.value = parseInt(nominal).toLocaleString('id-ID');
            
            // Set jatuh tempo based on periode
            const tagihanDate = new Date(tanggalTagihanInput.value);
            let jatuhtempo = new Date(tagihanDate);
            
            if (periode === 'bulanan') {
                // 30 days from tagihan date
                jatuhtempo.setDate(jatuhtempo.getDate() + 30);
            } else if (periode === 'tahunan') {
                // 60 days from tagihan date
                jatuhtempo.setDate(jatuhtempo.getDate() + 60);
            } else {
                // 14 days for insidentil
                jatuhtempo.setDate(jatuhtempo.getDate() + 14);
            }
            
            // Format jatuh tempo date to YYYY-MM-DD
            const year = jatuhtempo.getFullYear();
            const month = String(jatuhtempo.getMonth() + 1).padStart(2, '0');
            const day = String(jatuhtempo.getDate()).padStart(2, '0');
            jatuhTempoInput.value = `${year}-${month}-${day}`;
        }
    });
    
    // Auto-hide alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-warning)');
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
    
    // Initialize select2 for better dropdown experience
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            placeholder: "-- Pilih Warga --",
            allowClear: true
        });
    }
});
</script>

<style>
/* Form Styles */
.form-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.admin-form {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    margin-bottom: 0.5rem;
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

.form-control:focus {
    border-color: #4e73df;
    outline: none;
}

.form-control:disabled {
    background-color: #f8f9fc;
    cursor: not-allowed;
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
    gap: 1rem;
    margin-bottom: 1rem;
}

.col-md-6 {
    flex: 1;
    min-width: 250px;
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

.alert a {
    font-weight: 600;
    color: inherit;
    text-decoration: underline;
}

/* Select2 Customization */
.select2-container--default .select2-selection--single {
    height: calc(1.5em + 1.5rem + 2px);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    border: 1px solid #d1d3e2;
    border-radius: 4px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 1;
    padding-left: 0;
    color: #6e707e;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100%;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .col-md-6 {
        width: 100%;
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