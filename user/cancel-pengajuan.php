<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/koneksi.php';

// Error handling function
function handleDatabaseError($query, $error) {
    error_log("Database query error: $query - Error: $error");
    return false;
}

// Prepare and execute query safely
function executeQuery($koneksi, $query) {
    $result = mysqli_query($koneksi, $query);
    if (!$result) {
        handleDatabaseError($query, mysqli_error($koneksi));
        return false;
    }
    return $result;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Check if request is submitted via POST method and pengajuan_id is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pengajuan_id'])) {
    $pengajuan_id = mysqli_real_escape_string($koneksi, $_POST['pengajuan_id']);
    
    // Check if the application belongs to the current user
    $check_query = "SELECT pd.pengajuan_id, pd.status, pd.nomor_pengajuan, jd.nama_dokumen 
                   FROM pengajuan_dokumen pd 
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id 
                   WHERE pd.pengajuan_id = '$pengajuan_id' AND pd.user_id = '$user_id'";
    $check_result = executeQuery($koneksi, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $pengajuan_data = mysqli_fetch_assoc($check_result);
        
        // Only allow cancellation if status is 'diajukan' or 'verifikasi'
        if (in_array($pengajuan_data['status'], ['diajukan', 'verifikasi'])) {
            // Get cancellation reason if provided
            $cancellation_reason = isset($_POST['cancellation_reason']) ? 
                mysqli_real_escape_string($koneksi, $_POST['cancellation_reason']) : 
                'Dibatalkan oleh pemohon';
            
            // Update status to 'ditolak' (in this case used as 'canceled')
            $update_query = "UPDATE pengajuan_dokumen 
                           SET status = 'ditolak', 
                               catatan = CONCAT(IFNULL(catatan, ''), ' - Dibatalkan oleh pemohon: ', '$cancellation_reason', ' (', NOW(), ')') 
                           WHERE pengajuan_id = '$pengajuan_id'";
            $update_result = executeQuery($koneksi, $update_query);
            
            // Add to application history
            $history_query = "INSERT INTO riwayat_pengajuan (pengajuan_id, status, catatan, changed_by) 
                             VALUES ('$pengajuan_id', 'ditolak', 'Dibatalkan oleh pemohon: $cancellation_reason', '$user_id')";
            $history_result = executeQuery($koneksi, $history_query);
            
            if ($update_result) {
                $message = "Pengajuan nomor " . htmlspecialchars($pengajuan_data['nomor_pengajuan']) . 
                          " (" . htmlspecialchars($pengajuan_data['nama_dokumen']) . ") berhasil dibatalkan.";
                $message_type = 'success';
            } else {
                $message = "Gagal membatalkan pengajuan. Silakan coba lagi.";
                $message_type = 'danger';
            }
        } else {
            $message = "Pengajuan tidak dapat dibatalkan karena sudah diproses (status: " . htmlspecialchars($pengajuan_data['status']) . ").";
            $message_type = 'warning';
        }
    } else {
        $message = "Anda tidak memiliki akses untuk membatalkan pengajuan ini atau pengajuan tidak ditemukan.";
        $message_type = 'danger';
    }
} elseif (isset($_GET['id'])) {
    // If accessing via GET with id parameter, display confirmation form
    $pengajuan_id = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Check if the application belongs to the current user
    $check_query = "SELECT pd.pengajuan_id, pd.status, pd.nomor_pengajuan, jd.nama_dokumen 
                   FROM pengajuan_dokumen pd 
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id 
                   WHERE pd.pengajuan_id = '$pengajuan_id' AND pd.user_id = '$user_id'";
    $check_result = executeQuery($koneksi, $check_query);
    
    if (!$check_result || mysqli_num_rows($check_result) === 0) {
        $message = "Pengajuan tidak ditemukan atau Anda tidak memiliki akses untuk membatalkan pengajuan ini.";
        $message_type = 'danger';
    } else {
        $pengajuan_data = mysqli_fetch_assoc($check_result);
        
        // Check if status allows cancellation
        if (!in_array($pengajuan_data['status'], ['diajukan', 'verifikasi'])) {
            $message = "Pengajuan tidak dapat dibatalkan karena sudah diproses (status: " . htmlspecialchars($pengajuan_data['status']) . ").";
            $message_type = 'warning';
        }
    }
} else {
    // Redirect to pengajuan-saya.php if accessed directly without parameters
    header("Location: pengajuan-saya.php");
    exit();
}

// Include header
include '../includes/header.php';
?>

<main class="dashboard-content">
    <div class="container">
        <h1 class="page-title">Batalkan Pengajuan Dokumen</h1>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        
        <?php if ($message_type === 'success'): ?>
        <div class="text-center mt-4">
            <a href="pengajuan-saya.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pengajuan
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($pengajuan_data) && in_array($pengajuan_data['status'], ['diajukan', 'verifikasi']) && $message_type !== 'success'): ?>
        <div class="card">
            <div class="card-header">
                <h5>Konfirmasi Pembatalan</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Perhatian: Tindakan ini tidak dapat dibatalkan.
                </div>
                
                <p>Anda akan membatalkan pengajuan dokumen berikut:</p>
                <table class="table table-bordered">
                    <tr>
                        <th>Nomor Pengajuan</th>
                        <td><?php echo htmlspecialchars($pengajuan_data['nomor_pengajuan']); ?></td>
                    </tr>
                    <tr>
                        <th>Jenis Dokumen</th>
                        <td><?php echo htmlspecialchars($pengajuan_data['nama_dokumen']); ?></td>
                    </tr>
                    <tr>
                        <th>Status Saat Ini</th>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($pengajuan_data['status']); ?>">
                                <?php echo htmlspecialchars($pengajuan_data['status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
                
                <form method="POST" action="cancel-pengajuan.php" class="mt-4">
                    <input type="hidden" name="pengajuan_id" value="<?php echo $pengajuan_id; ?>">
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Alasan Pembatalan:</label>
                        <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" placeholder="Berikan alasan pembatalan pengajuan (opsional)"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Batalkan Pengajuan
                        </button>
                        <a href="pengajuan-saya.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($message_type !== 'success'): ?>
        <div class="text-center mt-4">
            <a href="pengajuan-saya.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pengajuan
            </a>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Cancel Pengajuan Page Styles */
.dashboard-content {
    padding: 40px 0;
    background-color: #f9f9f9;
    min-height: calc(100vh - 140px);
}

.page-title {
    color: #28a745;
    margin-bottom: 30px;
    font-weight: 600;
}

.card {
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
    overflow: hidden;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid #eee;
    padding: 20px;
}

.card-header h5 {
    margin: 0;
    color: #343a40;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.alert {
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.alert i {
    margin-right: 5px;
}

.table {
    background-color: #fff;
}

.table th {
    width: 30%;
    color: #28a745;
    font-weight: 600;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}

.status-diajukan {
    background-color: #f8f9fa;
    color: #6c757d;
}

.status-verifikasi {
    background-color: #fff3cd;
    color: #856404;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
}

.form-control {
    display: block;
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #28a745;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 30px;
}

.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    background-color: #0069d9;
    border-color: #0062cc;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.btn-secondary:hover {
    background-color: #5a6268;
    border-color: #545b62;
}

.btn-danger {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

.btn i {
    margin-right: 5px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .table th {
        width: 40%;
    }
}

@media (max-width: 576px) {
    .dashboard-content {
        padding: 20px 0;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .card-body {
        padding: 15px;
    }
}
</style>

<?php
// Free result set if it exists
if (isset($check_result) && $check_result) mysqli_free_result($check_result);

// Include footer
include '../includes/footer.php';
?>