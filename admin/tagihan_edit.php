
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

// Get tagihan ID from URL
$tagihan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tagihan_id <= 0) {
    $_SESSION['error_message'] = 'ID tagihan tidak valid.';
    redirect('retribusi.php');
}

// Get tagihan details
$query = "SELECT tr.*, u.nama AS nama_warga, jr.nama_retribusi, jr.periode AS periode_retribusi 
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
    redirect('retribusi.php');
}

$tagihan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Check if bill has payments
$query_payment = "SELECT COUNT(*) as payment_count FROM pembayaran_retribusi WHERE tagihan_id = ?";
$stmt_payment = mysqli_prepare($koneksi, $query_payment);
mysqli_stmt_bind_param($stmt_payment, 'i', $tagihan_id);
mysqli_stmt_execute($stmt_payment);
$result_payment = mysqli_stmt_get_result($stmt_payment);
$payment_info = mysqli_fetch_assoc($result_payment);
$has_payments = $payment_info['payment_count'] > 0;
mysqli_stmt_close($stmt_payment);

// If bill is already paid, redirect to detail page
if ($tagihan['status'] == 'lunas') {
    $_SESSION['error_message'] = 'Tagihan yang sudah lunas tidak dapat diubah.';
    redirect('tagihan_detail.php?id=' . $tagihan_id);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warga_id = isset($_POST['warga_id']) ? intval($_POST['warga_id']) : 0;
    $jenis_retribusi_id = isset($_POST['jenis_retribusi_id']) ? intval($_POST['jenis_retribusi_id']) : 0;
    $tanggal_tagihan = isset($_POST['tanggal_tagihan']) ? $_POST['tanggal_tagihan'] : '';
    $jatuh_tempo = isset($_POST['jatuh_tempo']) ? $_POST['jatuh_tempo'] : '';
    $nominal = str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['nominal']));
    $denda = str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['denda']));
    $status = isset($_POST['status']) ? $_POST['status'] : 'belum_bayar';
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
    
    if (!is_numeric($denda) || $denda < 0) {
        $errors[] = 'Denda harus berupa angka positif atau nol.';
    }
    
    if (!in_array($status, ['belum_bayar', 'proses', 'lunas', 'telat'])) {
        $errors[] = 'Status tidak valid.';
    }
    
    // If warga or jenis retribusi changed, check for duplicate
    if ($warga_id != $tagihan['user_id'] || $jenis_retribusi_id != $tagihan['jenis_retribusi_id']) {
        $check_duplicate_query = "SELECT COUNT(*) AS count FROM tagihan_retribusi 
                                 WHERE user_id = ? AND jenis_retribusi_id = ? 
                                 AND MONTH(tanggal_tagihan) = MONTH(?) 
                                 AND YEAR(tanggal_tagihan) = YEAR(?)
                                 AND tagihan_id != ?
                                 AND status != 'lunas'";
        $stmt_check = mysqli_prepare($koneksi, $check_duplicate_query);
        mysqli_stmt_bind_param($stmt_check, 'iissi', $warga_id, $jenis_retribusi_id, $tanggal_tagihan, $tanggal_tagihan, $tagihan_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $row_check = mysqli_fetch_assoc($result_check);
        
        if ($row_check['count'] > 0) {
            $errors[] = 'Warga ini sudah memiliki tagihan yang belum lunas untuk jenis retribusi dan bulan yang sama.';
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // If there are payments, restrict some changes
    if ($has_payments) {
        if ($warga_id != $tagihan['user_id']) {
            $errors[] = 'Tidak dapat mengubah warga karena tagihan ini sudah memiliki data pembayaran.';
        }
        
        if ($jenis_retribusi_id != $tagihan['jenis_retribusi_id']) {
            $errors[] = 'Tidak dapat mengubah jenis retribusi karena tagihan ini sudah memiliki data pembayaran.';
        }
    }
    
    // If no errors, update the bill
    if (empty($errors)) {
        // Check if notification needed
        $send_notification = false;
        $notif_judul = "Perubahan Tagihan";
        $notif_pesan = "Tagihan " . getRetribusiName($koneksi, $jenis_retribusi_id) . " periode " . date('F Y', strtotime($tanggal_tagihan)) . " telah diperbarui.\n\n";
        
        // Significant changes to notify
        if ($jatuh_tempo != $tagihan['jatuh_tempo']) {
            $notif_pesan .= "- Tanggal jatuh tempo diubah menjadi: " . date('d-m-Y', strtotime($jatuh_tempo)) . "\n";
            $send_notification = true;
        }
        
        if ($nominal != $tagihan['nominal']) {
            $notif_pesan .= "- Nominal diubah menjadi: Rp " . number_format($nominal, 0, ',', '.') . "\n";
            $send_notification = true;
        }
        
        if ($denda != $tagihan['denda'] && $denda > $tagihan['denda']) {
            $notif_pesan .= "- Denda diubah menjadi: Rp " . number_format($denda, 0, ',', '.') . "\n";
            $send_notification = true;
        }
        
        if ($status != $tagihan['status']) {
            $status_text = '';
            switch ($status) {
                case 'belum_bayar':
                    $status_text = 'Belum Bayar';
                    break;
                case 'proses':
                    $status_text = 'Dalam Proses';
                    break;
                case 'lunas':
                    $status_text = 'Lunas';
                    break;
                case 'telat':
                    $status_text = 'Telat';
                    break;
            }
            $notif_pesan .= "- Status diubah menjadi: " . $status_text . "\n";
            $send_notification = true;
        }
        
        // Update the tagihan
        $query = "UPDATE tagihan_retribusi SET 
                  user_id = ?, 
                  jenis_retribusi_id = ?, 
                  tanggal_tagihan = ?, 
                  jatuh_tempo = ?, 
                  nominal = ?, 
                  denda = ?, 
                  status = ? 
                  WHERE tagihan_id = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, 'iissddsi', $warga_id, $jenis_retribusi_id, $tanggal_tagihan, $jatuh_tempo, $nominal, $denda, $status, $tagihan_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Send notification if needed
            if ($send_notification) {
                $query_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                                VALUES (?, ?, ?, 'tagihan', '/tagihan_detail.php?id=".$tagihan_id."')";
                $stmt_notif = mysqli_prepare($koneksi, $query_notif);
                mysqli_stmt_bind_param($stmt_notif, 'iss', $warga_id, $notif_judul, $notif_pesan);
                mysqli_stmt_execute($stmt_notif);
                mysqli_stmt_close($stmt_notif);
            }
            
            // Log activity
            $aktivitas = "Mengubah tagihan #" . $tagihan_id . " (" . getRetribusiName($koneksi, $jenis_retribusi_id) . ")";
            $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($koneksi, $query_log);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($stmt_log, 'iss', $user_id, $aktivitas, $ip_address);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
            
            $success_message = 'Tagihan berhasil diperbarui.';
            
            // Redirect to detail page after success
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

// Helper function to get retribusi name
function getRetribusiName($koneksi, $id) {
    $query = "SELECT nama_retribusi FROM jenis_retribusi WHERE jenis_retribusi_id = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['nama_retribusi'] : '';
}

// Prepare variables for page
$page_title = "Edit Tagihan Retribusi";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Edit Tagihan Retribusi</h2>
        <div class="admin-header-actions">
            <a href="tagihan_detail.php?id=<?php echo $tagihan_id; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            <a href="tagihan_cetak.php?id=<?php echo $tagihan_id; ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
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

    <?php if ($has_payments): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian!</strong> Tagihan ini sudah memiliki data pembayaran. Beberapa data seperti Warga dan Jenis Retribusi tidak dapat diubah.
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="card-header">
            <h3>Form Edit Tagihan</h3>
            <div class="status <?php echo 'status-' . getStatusClass($tagihan['status']); ?>">
                <?php echo getStatusText($tagihan['status']); ?>
            </div>
        </div>
        <form class="admin-form" method="POST" action="">
            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="warga_id">Warga <span class="required">*</span></label>
                        <select id="warga_id" name="warga_id" class="form-control select2" required <?php echo $has_payments ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Warga --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $user['user_id'] == $tagihan['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nama'] . ' (' . $user['nik'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($has_payments): ?>
                            <input type="hidden" name="warga_id" value="<?php echo $tagihan['user_id']; ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="jenis_retribusi_id">Jenis Retribusi <span class="required">*</span></label>
                        <select id="jenis_retribusi_id" name="jenis_retribusi_id" class="form-control" required <?php echo $has_payments ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih Jenis Retribusi --</option>
                            <?php foreach ($jenis_retribusi as $jenis): ?>
                                <option value="<?php echo $jenis['jenis_retribusi_id']; ?>" 
                                        data-nominal="<?php echo $jenis['nominal']; ?>" 
                                        data-periode="<?php echo $jenis['periode']; ?>"
                                        <?php echo $jenis['jenis_retribusi_id'] == $tagihan['jenis_retribusi_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($jenis['nama_retribusi'] . ' - ' . ucfirst($jenis['periode'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($has_payments): ?>
                            <input type="hidden" name="jenis_retribusi_id" value="<?php echo $tagihan['jenis_retribusi_id']; ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tanggal_tagihan">Tanggal Tagihan <span class="required">*</span></label>
                        <input type="date" id="tanggal_tagihan" name="tanggal_tagihan" class="form-control" 
                               value="<?php echo $tagihan['tanggal_tagihan']; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="jatuh_tempo">Tanggal Jatuh Tempo <span class="required">*</span></label>
                        <input type="date" id="jatuh_tempo" name="jatuh_tempo" class="form-control" 
                               value="<?php echo $tagihan['jatuh_tempo']; ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="nominal">Nominal <span class="required">*</span></label>
                        <input type="text" id="nominal" name="nominal" class="form-control currency-input" 
                               value="<?php echo number_format($tagihan['nominal'], 0, ',', '.'); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="denda">Denda</label>
                        <input type="text" id="denda" name="denda" class="form-control currency-input" 
                               value="<?php echo number_format($tagihan['denda'], 0, ',', '.'); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="belum_bayar" <?php echo $tagihan['status'] == 'belum_bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                            <option value="proses" <?php echo $tagihan['status'] == 'proses' ? 'selected' : ''; ?>>Proses</option>
                            <option value="lunas" <?php echo $tagihan['status'] == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                            <option value="telat" <?php echo $tagihan['status'] == 'telat' ? 'selected' : ''; ?>>Telat</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi (Opsional)</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3"><?php echo isset($tagihan['deskripsi']) ? htmlspecialchars($tagihan['deskripsi']) : ''; ?></textarea>
            </div>

            <div class="form-group total-section">
                <div class="total-row">
                    <div class="total-label">Total Tagihan</div>
                    <div class="total-value" id="totalAmount">
                        Rp <?php echo number_format($tagihan['nominal'] + $tagihan['denda'], 0, ',', '.'); ?>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="tagihan_detail.php?id=<?php echo $tagihan_id; ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn">Perbarui Tagihan</button>
            </div>
        </form>
    </div>
</div>

<?php
// Helper functions for status display
function getStatusClass($status) {
    switch ($status) {
        case 'belum_bayar': return 'pending';
        case 'proses': return 'processing';
        case 'lunas': return 'completed';
        case 'telat': return 'rejected';
        default: return 'pending';
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
    
    // Calculate total when amount or denda changes
    const nominalInput = document.getElementById('nominal');
    const dendaInput = document.getElementById('denda');
    const totalAmount = document.getElementById('totalAmount');
    
    function updateTotal() {
        const nominal = parseInt(nominalInput.value.replace(/[^\d]/g, '') || 0);
        const denda = parseInt(dendaInput.value.replace(/[^\d]/g, '') || 0);
        totalAmount.textContent = 'Rp ' + (nominal + denda).toLocaleString('id-ID');
    }
    
    nominalInput.addEventListener('input', updateTotal);
    dendaInput.addEventListener('input', updateTotal);
    
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
    
    // Set jatuh tempo based on jenis retribusi if changed
    const jenisRetribusiSelect = document.getElementById('jenis_retribusi_id');
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
            
            // Update total
            updateTotal();
        }
    });
    
    // Update jatuh tempo if tanggal tagihan changes
    tanggalTagihanInput.addEventListener('change', function() {
        const selectedOption = jenisRetribusiSelect.options[jenisRetribusiSelect.selectedIndex];
        if (selectedOption.value !== '') {
            const periode = selectedOption.getAttribute('data-periode');
            
            // Set jatuh tempo based on periode
            const tagihanDate = new Date(this.value);
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
    
    // Initialize select2 for better dropdown experience
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            placeholder: "-- Pilih Warga --",
            allowClear: true
        });
    }
});
</script>

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

.col-md-4 {
    flex: 1;
    min-width: 200px;
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

/* Total Section */
.total-section {
    background-color: #f8f9fc;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 0.5rem;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-weight: 600;
    font-size: 1.1rem;
    color: #333;
}

.total-value {
    font-weight: 700;
    font-size: 1.25rem;
    color: #4e73df;
}

/* Status Badges */
.status {
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

.alert i {
    margin-right: 0.5rem;
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
    
    .col-md-4, .col-md-6 {
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