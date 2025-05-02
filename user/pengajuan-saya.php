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

// Process cancel request if submitted
if (isset($_POST['cancel_pengajuan']) && isset($_POST['pengajuan_id'])) {
    $pengajuan_id = mysqli_real_escape_string($koneksi, $_POST['pengajuan_id']);
    
    // Check if the application belongs to the current user
    $check_query = "SELECT pengajuan_id FROM pengajuan_dokumen WHERE pengajuan_id = '$pengajuan_id' AND user_id = '$user_id'";
    $check_result = executeQuery($koneksi, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        // Only allow cancellation if status is 'diajukan' or 'verifikasi'
        $status_query = "SELECT status FROM pengajuan_dokumen WHERE pengajuan_id = '$pengajuan_id'";
        $status_result = executeQuery($koneksi, $status_query);
        
        if ($status_result && $status_row = mysqli_fetch_assoc($status_result)) {
            if (in_array($status_row['status'], ['diajukan', 'verifikasi'])) {
                // Update status to 'ditolak' (in this case used as 'canceled')
                $update_query = "UPDATE pengajuan_dokumen SET status = 'ditolak', catatan = CONCAT(catatan, ' - Dibatalkan oleh pemohon pada ', NOW()) WHERE pengajuan_id = '$pengajuan_id'";
                $update_result = executeQuery($koneksi, $update_query);
                
                // Add to application history
                $history_query = "INSERT INTO riwayat_pengajuan (pengajuan_id, status, catatan, changed_by) 
                                 VALUES ('$pengajuan_id', 'ditolak', 'Dibatalkan oleh pemohon', '$user_id')";
                $history_result = executeQuery($koneksi, $history_query);
                
                if ($update_result) {
                    $success_message = "Pengajuan berhasil dibatalkan.";
                } else {
                    $error_message = "Gagal membatalkan pengajuan. Silakan coba lagi.";
                }
            } else {
                $error_message = "Pengajuan tidak dapat dibatalkan karena sudah diproses.";
            }
        }
    } else {
        $error_message = "Anda tidak memiliki akses untuk membatalkan pengajuan ini.";
    }
}

// Get user's applications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter by status if requested
$status_filter = "";
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = mysqli_real_escape_string($koneksi, $_GET['status']);
    $status_filter = "AND pd.status = '$status'";
}

// Search by document number or document type if requested
$search_filter = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($koneksi, $_GET['search']);
    $search_filter = "AND (pd.nomor_pengajuan LIKE '%$search%' OR jd.nama_dokumen LIKE '%$search%')";
}

// Count total applications for pagination
$count_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen pd 
                JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id 
                WHERE pd.user_id = '$user_id' $status_filter $search_filter";
$count_result = executeQuery($koneksi, $count_query);
$total_records = 0;

if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
    $total_records = $row['total'];
}

$total_pages = ceil($total_records / $limit);

// Get user's applications
$pengajuan_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status, 
                   pd.catatan, pd.tanggal_selesai, pd.dokumen_hasil, jd.nama_dokumen 
                   FROM pengajuan_dokumen pd 
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id 
                   WHERE pd.user_id = '$user_id' $status_filter $search_filter
                   ORDER BY pd.tanggal_pengajuan DESC 
                   LIMIT $offset, $limit";
$pengajuan_result = executeQuery($koneksi, $pengajuan_query);

// Include header
include '../includes/header.php';
?>

<main class="dashboard-content">
    <div class="container">
        <h1 class="page-title">Pengajuan Dokumen Saya</h1>
        
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <div class="filter-search-container">
                    <div class="filter-container">
                        <form method="GET" action="pengajuan-saya.php" class="filter-form">
                            <div class="form-group">
                                <label for="status">Filter Status:</label>
                                <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="diajukan" <?php echo (isset($_GET['status']) && $_GET['status'] == 'diajukan') ? 'selected' : ''; ?>>Diajukan</option>
                                    <option value="verifikasi" <?php echo (isset($_GET['status']) && $_GET['status'] == 'verifikasi') ? 'selected' : ''; ?>>Verifikasi</option>
                                    <option value="proses" <?php echo (isset($_GET['status']) && $_GET['status'] == 'proses') ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai" <?php echo (isset($_GET['status']) && $_GET['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="ditolak" <?php echo (isset($_GET['status']) && $_GET['status'] == 'ditolak') ? 'selected' : ''; ?>>Ditolak/Dibatalkan</option>
                                </select>
                            </div>
                            
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="search-container">
                        <form method="GET" action="pengajuan-saya.php" class="search-form">
                            <div class="form-group">
                                <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan nomor atau jenis dokumen..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            
                            <?php if (isset($_GET['status']) && !empty($_GET['status'])): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status']); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="button-container">
                    <a href="pengajuan.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Buat Pengajuan Baru
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <?php if ($pengajuan_result && mysqli_num_rows($pengajuan_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No. Pengajuan</th>
                                <th>Jenis Dokumen</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Status</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($pengajuan_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nomor_pengajuan']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_dokumen']); ?></td>
                                <td><?php echo date('d-m-Y H:i', strtotime($row['tanggal_pengajuan'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php 
                                        switch($row['status']) {
                                            case 'diajukan':
                                                echo 'Diajukan';
                                                break;
                                            case 'verifikasi':
                                                echo 'Verifikasi';
                                                break;
                                            case 'proses':
                                                echo 'Diproses';
                                                break;
                                            case 'selesai':
                                                echo 'Selesai';
                                                break;
                                            case 'ditolak':
                                                echo 'Ditolak/Dibatalkan';
                                                break;
                                            default:
                                                echo $row['status'];
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="status-pengajuan.php?id=<?php echo $row['pengajuan_id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                    
                                    <?php if ($row['status'] == 'diajukan' || $row['status'] == 'verifikasi'): ?>
                                    <form method="POST" action="pengajuan-saya.php" class="d-inline" onsubmit="return confirm('Anda yakin ingin membatalkan pengajuan ini?');">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo $row['pengajuan_id']; ?>">
                                        <button type="submit" name="cancel_pengajuan" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Batalkan
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['status'] == 'selesai' && !empty($row['dokumen_hasil'])): ?>
                                    <a href="../uploads/dokumen_hasil/<?php echo $row['dokumen_hasil']; ?>" class="btn btn-success btn-sm" download>
                                        <i class="fas fa-download"></i> Unduh
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Belum Ada Pengajuan</h3>
                    <p>Anda belum membuat pengajuan dokumen. Klik tombol di bawah untuk membuat pengajuan baru.</p>
                    <a href="pengajuan.php" class="btn btn-primary">Buat Pengajuan Baru</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
/* Pengajuan Saya Page Styles */
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-search-container {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    flex: 1;
}

.filter-container, 
.search-container {
    flex: 1;
    min-width: 200px;
}

.filter-form,
.search-form {
    display: flex;
    align-items: center;
}

.search-form .form-group {
    display: flex;
    width: 100%;
}

.search-form .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.search-form .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.button-container {
    display: flex;
    gap: 10px;
}

.card-body {
    padding: 20px;
}

.table {
    margin-bottom: 0;
}

.table th {
    color: #28a745;
    font-weight: 600;
    border-top: none;
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

.status-proses {
    background-color: #cce5ff;
    color: #004085;
}

.status-selesai {
    background-color: #d4edda;
    color: #155724;
}

.status-ditolak {
    background-color: #f8d7da;
    color: #721c24;
}

.action-buttons {
    white-space: nowrap;
}

.action-buttons .btn {
    margin-right: 5px;
}

.pagination {
    margin-top: 20px;
    margin-bottom: 0;
}

.pagination .page-link {
    color: #28a745;
}

.pagination .page-item.active .page-link {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state-icon {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #343a40;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
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

/* Responsive adjustments */
@media (max-width: 992px) {
    .card-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-search-container {
        flex-direction: column;
    }
    
    .button-container {
        margin-top: 15px;
    }
}

@media (max-width: 768px) {
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .action-buttons .btn {
        margin-right: 0;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .table th, .table td {
        padding: 10px;
    }
}

@media (max-width: 576px) {
    .dashboard-content {
        padding: 20px 0;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
}
</style>

<?php
// Free result set
if ($pengajuan_result) mysqli_free_result($pengajuan_result);
if ($count_result) mysqli_free_result($count_result);

// Include footer
include '../includes/footer.php';
?>