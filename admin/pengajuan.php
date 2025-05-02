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

// Set default filter values
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$jenis_filter = isset($_GET['jenis']) ? $_GET['jenis'] : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];

if ($status_filter !== 'all') {
    $conditions[] = "pd.status = '$status_filter'";
}

if ($jenis_filter !== 'all') {
    $conditions[] = "pd.jenis_id = '$jenis_filter'";
}

if (!empty($search)) {
    $conditions[] = "(u.nama LIKE '%$search%' OR pd.nomor_pengajuan LIKE '%$search%' OR jd.nama_dokumen LIKE '%$search%')";
}

$condition_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Build sort order
switch ($sort) {
    case 'oldest':
        $sort_sql = "ORDER BY pd.tanggal_pengajuan ASC";
        break;
    case 'name_asc':
        $sort_sql = "ORDER BY u.nama ASC";
        break;
    case 'name_desc':
        $sort_sql = "ORDER BY u.nama DESC";
        break;
    case 'newest':
    default:
        $sort_sql = "ORDER BY pd.tanggal_pengajuan DESC";
        break;
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total 
               FROM pengajuan_dokumen pd
               JOIN users u ON pd.user_id = u.user_id
               JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
               $condition_sql";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get application data
$pengajuan_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status, pd.tanggal_selesai,
                   u.nama as pemohon, u.nik, jd.nama_dokumen, jd.estimasi_waktu
                   FROM pengajuan_dokumen pd
                   JOIN users u ON pd.user_id = u.user_id
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                   $condition_sql
                   $sort_sql
                   LIMIT $offset, $per_page";
$pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

// Get all document types for filter
$jenis_dokumen_query = "SELECT jenis_id, nama_dokumen FROM jenis_dokumen WHERE is_active = TRUE ORDER BY nama_dokumen ASC";
$jenis_dokumen_result = mysqli_query($koneksi, $jenis_dokumen_query);

// Count applications by status for summary
$summary_query = "SELECT status, COUNT(*) as count FROM pengajuan_dokumen GROUP BY status";
$summary_result = mysqli_query($koneksi, $summary_query);
$status_counts = [
    'diajukan' => 0,
    'verifikasi' => 0,
    'proses' => 0,
    'selesai' => 0,
    'ditolak' => 0
];

if ($summary_result) {
    while ($row = mysqli_fetch_assoc($summary_result)) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Total applications
$total_applications = array_sum($status_counts);

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

// Set page title
$page_title = "Pengajuan Dokumen";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">
    <!-- Admin Sidebar -->

    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Kelola Pengajuan Dokumen</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; Pengajuan Dokumen
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

        <!-- Status Summary -->
        <div class="stats-container">
            <div class="stats-row">
                <div class="stats-card">
                    <div class="stats-info">
                        <h3>Total Pengajuan</h3>
                        <p class="stats-value"><?php echo $total_applications; ?></p>
                    </div>
                </div>
                <div class="stats-card warning">
                    <div class="stats-info">
                        <h3>Menunggu</h3>
                        <p class="stats-value"><?php echo $status_counts['diajukan']; ?></p>
                    </div>
                </div>
                <div class="stats-card info">
                    <div class="stats-info">
                        <h3>Verifikasi & Proses</h3>
                        <p class="stats-value"><?php echo $status_counts['verifikasi'] + $status_counts['proses']; ?></p>
                    </div>
                </div>
                <div class="stats-card success">
                    <div class="stats-info">
                        <h3>Selesai</h3>
                        <p class="stats-value"><?php echo $status_counts['selesai']; ?></p>
                    </div>
                </div>
                <div class="stats-card danger">
                    <div class="stats-info">
                        <h3>Ditolak</h3>
                        <p class="stats-value"><?php echo $status_counts['ditolak']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="filter-container">
            <form action="pengajuan.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="diajukan" <?php echo $status_filter === 'diajukan' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="verifikasi" <?php echo $status_filter === 'verifikasi' ? 'selected' : ''; ?>>Verifikasi</option>
                        <option value="proses" <?php echo $status_filter === 'proses' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="ditolak" <?php echo $status_filter === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="jenis">Jenis Dokumen:</label>
                    <select name="jenis" id="jenis">
                        <option value="all" <?php echo $jenis_filter === 'all' ? 'selected' : ''; ?>>Semua Jenis</option>
                        <?php 
                        mysqli_data_seek($jenis_dokumen_result, 0);
                        while ($jenis = mysqli_fetch_assoc($jenis_dokumen_result)) {
                            $selected = $jenis_filter == $jenis['jenis_id'] ? 'selected' : '';
                            echo '<option value="' . $jenis['jenis_id'] . '" ' . $selected . '>' . htmlspecialchars($jenis['nama_dokumen']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort">Urutkan:</label>
                    <select name="sort" id="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Nama (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Nama (Z-A)</option>
                    </select>
                </div>
                
                <div class="search-group">
                    <input type="text" name="search" placeholder="Cari nama/nomor..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="pengajuan.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="data-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">No. Pengajuan</th>
                            <th width="20%">Pemohon</th>
                            <th width="15%">Jenis Dokumen</th>
                            <th width="10%">Tanggal</th>
                            <th width="10%">Status</th>
                            <th width="10%">Estimasi</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($pengajuan_result) > 0) {
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($pengajuan_result)) {
                                // Format dates
                                $tanggal_pengajuan = date('d-m-Y', strtotime($row['tanggal_pengajuan']));
                                $tanggal_selesai = !empty($row['tanggal_selesai']) ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-';
                                
                                // Determine status class and text
                                $status_class = "";
                                $status_text = "";
                                switch ($row['status']) {
                                    case 'diajukan':
                                        $status_class = "status-pending";
                                        $status_text = "Menunggu";
                                        break;
                                    case 'verifikasi':
                                        $status_class = "status-processing";
                                        $status_text = "Verifikasi";
                                        break;
                                    case 'proses':
                                        $status_class = "status-processing";
                                        $status_text = "Diproses";
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
                                
                                // Check if document is overdue (only for non-completed documents)
                                $is_overdue = false;
                                if ($row['status'] != 'selesai' && $row['status'] != 'ditolak') {
                                    $est_days = (int)$row['estimasi_waktu'];
                                    $pengajuan_date = new DateTime($row['tanggal_pengajuan']);
                                    $due_date = clone $pengajuan_date;
                                    $due_date->modify("+$est_days days");
                                    $today = new DateTime();
                                    
                                    if ($today > $due_date) {
                                        $is_overdue = true;
                                    }
                                }
                                
                                echo '<tr' . ($is_overdue ? ' class="overdue"' : '') . '>';
                                echo '<td>' . $no++ . '</td>';
                                echo '<td>' . htmlspecialchars($row['nomor_pengajuan']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['pemohon']) . '<br><small>NIK: ' . htmlspecialchars($row['nik']) . '</small></td>';
                                echo '<td>' . htmlspecialchars($row['nama_dokumen']) . '</td>';
                                echo '<td>' . $tanggal_pengajuan . '</td>';
                                echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                                echo '<td>' . $row['estimasi_waktu'] . ' hari' . ($is_overdue ? '<br><span class="badge badge-danger">Terlambat</span>' : '') . '</td>';
                                echo '<td class="actions">';
                                echo '<a href="pengajuan_detail.php?id=' . $row['pengajuan_id'] . '" class="btn-sm btn-info">Detail</a>';
                                
                                // Show process button only for applications with appropriate status
                                if ($row['status'] == 'diajukan') {
                                    echo '<a href="pengajuan_proses.php?id=' . $row['pengajuan_id'] . '&action=verify" class="btn-sm btn-primary">Verifikasi</a>';
                                } elseif ($row['status'] == 'verifikasi') {
                                    echo '<a href="pengajuan_proses.php?id=' . $row['pengajuan_id'] . '&action=process" class="btn-sm btn-primary">Proses</a>';
                                } elseif ($row['status'] == 'proses') {
                                    echo '<a href="pengajuan_proses.php?id=' . $row['pengajuan_id'] . '&action=complete" class="btn-sm btn-success">Selesaikan</a>';
                                }
                                
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Tidak ada data pengajuan yang ditemukan</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Menampilkan <?php echo ($offset + 1); ?> - <?php echo min($offset + $per_page, $total_records); ?> dari <?php echo $total_records; ?> data
                </div>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li>
                        <a href="?page=1&status=<?php echo $status_filter; ?>&jenis=<?php echo $jenis_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a>
                    </li>
                    <li>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&jenis=<?php echo $jenis_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&lsaquo;</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = $i == $page ? 'active' : '';
                        echo '<li class="' . $active_class . '"><a href="?page=' . $i . '&status=' . $status_filter . '&jenis=' . $jenis_filter . '&sort=' . $sort . '&search=' . urlencode($search) . '">' . $i . '</a></li>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&jenis=<?php echo $jenis_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&rsaquo;</a>
                    </li>
                    <li>
                        <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $status_filter; ?>&jenis=<?php echo $jenis_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="button-container">
            <a href="laporan_pengajuan.php" class="btn btn-primary">
                <span class="btn-icon">📊</span> Laporan Pengajuan
            </a>
            <a href="javascript:void(0);" onclick="window.print();" class="btn btn-secondary">
                <span class="btn-icon">🖨️</span> Cetak Daftar
            </a>
            <a href="export_pengajuan.php?status=<?php echo $status_filter; ?>&jenis=<?php echo $jenis_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-success">
                <span class="btn-icon">📥</span> Export Excel
            </a>
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
/* Stats Cards */
.stats-container {
    margin-bottom: 20px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.stats-card {
    background-color: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
}

.stats-icon {
    font-size: 2rem;
    margin-right: 15px;
    color: #6c757d;
}

.stats-info {
    flex: 1;
}

.stats-info h3 {
    margin: 0;
    font-size: 1rem;
    color: #6c757d;
}

.stats-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #343a40;
    margin: 5px 0 0;
}

/* Status colors */
.stats-card.primary {
    border-left: 4px solid #007bff;
}

.stats-card.warning {
    border-left: 4px solid #ffc107;
}

.stats-card.info {
    border-left: 4px solid #17a2b8;
}

.stats-card.success {
    border-left: 4px solid #28a745;
}

.stats-card.danger {
    border-left: 4px solid #dc3545;
}

/* Filter and Search */
.filter-container {
    background-color: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
    flex: 1;
}

.filter-group label {
    margin-bottom: 5px;
    font-size: 0.9rem;
    color: #495057;
}

.filter-group select,
.search-group input {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9rem;
}

.search-group {
    display: flex;
    gap: 10px;
    flex-grow: 2;
    align-items: center;
}

.search-group input {
    flex: 1;
}

/* Data Card */
.data-card {
    background-color: white;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #efefef;
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

/* Table */
.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #efefef;
}

.admin-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.admin-table tr:last-child td {
    border-bottom: none;
}

.admin-table tr:hover {
    background-color: #f8f9fa;
}

/* Status badges */
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

/* Alert Badges */
.badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-top: 1px solid #efefef;
}

.pagination-info {
    font-size: 0.9rem;
    color: #6c757d;
}

.pagination {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
}

.pagination li {
    margin: 0 3px;
}

.pagination li a {
    display: block;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    color: #007bff;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
}

.pagination li.active a {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

.pagination li a:hover {
    background-color: #e9ecef;
}

/* Buttons */
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

.btn-sm {
    padding: 4px 10px;
    font-size: 0.875rem;
    border-radius: 3px;
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

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
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

/* Action buttons */
.actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.button-container {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

/* Alert messages */
.alert {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    border: 1px solid transparent;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

/* Overdue row highlight */
.overdue {
    background-color: #fff8f8;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        min-width: unset;
    }
    
    .search-group {
        flex-wrap: wrap;
    }
}

@media (max-width: 768px) {
    .admin-container {
        flex-direction: column;
    }
    
    .stats-row {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .stats-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-icon {
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .button-container {
        flex-direction: column;
    }
}

@media print {
    .admin-sidebar, 
    .filter-container, 
    .button-container, 
    .pagination-container,
    .actions,
    .breadcrumb {
        display: none !important;
    }
    
    .admin-container {
        display: block;
    }
    
    .admin-content {
        padding: 0;
    }
    
    .admin-table th, 
    .admin-table td {
        padding: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit when status, jenis, or sort changes
    document.getElementById('status').addEventListener('change', function() {
        document.querySelector('.filter-form').submit();
    });
    
    document.getElementById('jenis').addEventListener('change', function() {
        document.querySelector('.filter-form').submit();
    });
    
    document.getElementById('sort').addEventListener('change', function() {
        document.querySelector('.filter-form').submit();
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>