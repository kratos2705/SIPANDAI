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

// Handle delete action
if (isset($_POST['delete_retribusi']) && !empty($_POST['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    
    // Check if retribusi is used in any tagihan
    $check_query = "SELECT COUNT(*) as count FROM tagihan_retribusi WHERE jenis_retribusi_id = '$id'";
    $check_result = mysqli_query($koneksi, $check_query);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        // Don't delete, just set inactive
        $update_query = "UPDATE jenis_retribusi SET is_active = FALSE WHERE jenis_retribusi_id = '$id'";
        if (mysqli_query($koneksi, $update_query)) {
            $_SESSION['success_message'] = 'Jenis retribusi berhasil dinonaktifkan.';
        } else {
            $_SESSION['error_message'] = 'Gagal menonaktifkan jenis retribusi: ' . mysqli_error($koneksi);
        }
    } else {
        // Delete if not used
        $delete_query = "DELETE FROM jenis_retribusi WHERE jenis_retribusi_id = '$id'";
        if (mysqli_query($koneksi, $delete_query)) {
            $_SESSION['success_message'] = 'Jenis retribusi berhasil dihapus.';
        } else {
            $_SESSION['error_message'] = 'Gagal menghapus jenis retribusi: ' . mysqli_error($koneksi);
        }
    }
    
    redirect('jenis_retribusi.php');
}

// Handle activate/deactivate action
if (isset($_POST['toggle_status']) && !empty($_POST['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $status = $_POST['status'] == 'activate' ? 'TRUE' : 'FALSE';
    
    $update_query = "UPDATE jenis_retribusi SET is_active = $status WHERE jenis_retribusi_id = '$id'";
    if (mysqli_query($koneksi, $update_query)) {
        $_SESSION['success_message'] = $_POST['status'] == 'activate' ? 
            'Jenis retribusi berhasil diaktifkan.' : 
            'Jenis retribusi berhasil dinonaktifkan.';
    } else {
        $_SESSION['error_message'] = 'Gagal mengubah status jenis retribusi: ' . mysqli_error($koneksi);
    }
    
    redirect('jenis_retribusi.php');
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$query = "SELECT * FROM jenis_retribusi WHERE 1=1";

if ($filter_status == 'active') {
    $query .= " AND is_active = TRUE";
} else if ($filter_status == 'inactive') {
    $query .= " AND is_active = FALSE";
}

if (!empty($search)) {
    $search_term = mysqli_real_escape_string($koneksi, $search);
    $query .= " AND (nama_retribusi LIKE '%$search_term%' OR deskripsi LIKE '%$search_term%')";
}

$query .= " ORDER BY is_active DESC, nama_retribusi ASC";

$result = mysqli_query($koneksi, $query);

// Get summary statistics
$stats_query = "SELECT 
               COUNT(*) as total,
               SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active,
               SUM(CASE WHEN is_active = FALSE THEN 1 ELSE 0 END) as inactive,
               SUM(CASE WHEN periode = 'bulanan' THEN 1 ELSE 0 END) as bulanan,
               SUM(CASE WHEN periode = 'tahunan' THEN 1 ELSE 0 END) as tahunan,
               SUM(CASE WHEN periode = 'insidentil' THEN 1 ELSE 0 END) as insidentil
               FROM jenis_retribusi";
               
$stats_result = mysqli_query($koneksi, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Prepare variables for page
$page_title = "Manajemen Jenis Retribusi";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Manajemen Jenis Retribusi</h2>
        <div class="admin-header-actions">
            <a href="retribusi.php" class="btn btn-secondary">Kembali ke Retribusi</a>
            <a href="jenis_retribusi_form.php" class="btn">Tambah Jenis Retribusi</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats-card primary">
                <div class="stats-icon">📊</div>
                <div class="stats-info">
                    <h3>Total Jenis</h3>
                    <p class="stats-value"><?php echo $stats['total']; ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">✅</div>
                <div class="stats-info">
                    <h3>Aktif</h3>
                    <p class="stats-value"><?php echo $stats['active']; ?></p>
                </div>
            </div>
            <div class="stats-card danger">
                <div class="stats-icon">❌</div>
                <div class="stats-info">
                    <h3>Tidak Aktif</h3>
                    <p class="stats-value"><?php echo $stats['inactive']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stats-card info">
                <div class="stats-icon">📅</div>
                <div class="stats-info">
                    <h3>Bulanan</h3>
                    <p class="stats-value"><?php echo $stats['bulanan']; ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">📆</div>
                <div class="stats-info">
                    <h3>Tahunan</h3>
                    <p class="stats-value"><?php echo $stats['tahunan']; ?></p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">🔄</div>
                <div class="stats-info">
                    <h3>Insidentil</h3>
                    <p class="stats-value"><?php echo $stats['insidentil']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="filter-container">
        <form action="" method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                    </select>
                </div>
                <div class="search-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama retribusi..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn">Cari</button>
                </div>
                <div class="filter-actions">
                    <a href="jenis_retribusi.php" class="btn btn-outline">Reset Filter</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Success and Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Data Card -->
    <div class="data-card">
        <div class="card-header">
            <h3>Daftar Jenis Retribusi</h3>
            <span class="card-header-info">Total: <?php echo mysqli_num_rows($result); ?> jenis</span>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Retribusi</th>
                        <th>Deskripsi</th>
                        <th>Nominal</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Format periode
                            $periode_text = "";
                            switch($row['periode']) {
                                case 'bulanan':
                                    $periode_text = "Bulanan";
                                    break;
                                case 'tahunan':
                                    $periode_text = "Tahunan";
                                    break;
                                case 'insidentil':
                                    $periode_text = "Insidentil";
                                    break;
                                default:
                                    $periode_text = ucfirst($row['periode']);
                            }
                            
                            // Status class
                            $status_class = $row['is_active'] ? 'status-completed' : 'status-rejected';
                            $status_text = $row['is_active'] ? 'Aktif' : 'Tidak Aktif';
                            
                            echo '<tr>';
                            echo '<td>' . $no++ . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama_retribusi']) . '</td>';
                            echo '<td>' . (empty($row['deskripsi']) ? '<em>Tidak ada deskripsi</em>' : htmlspecialchars(substr($row['deskripsi'], 0, 100)) . (strlen($row['deskripsi']) > 100 ? '...' : '')) . '</td>';
                            echo '<td>Rp ' . number_format($row['nominal'], 0, ',', '.') . '</td>';
                            echo '<td>' . $periode_text . '</td>';
                            echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                            echo '<td class="action-cell">';
                            echo '<a href="jenis_retribusi_form.php?id=' . $row['jenis_retribusi_id'] . '" class="btn-action edit" title="Edit"><i class="fas fa-edit"></i></a>';
                            
                            // Toggle status button
                            if ($row['is_active']) {
                                echo '<form method="POST" style="display:inline;">';
                                echo '<input type="hidden" name="id" value="' . $row['jenis_retribusi_id'] . '">';
                                echo '<input type="hidden" name="status" value="deactivate">';
                                echo '<button type="submit" name="toggle_status" class="btn-action deactivate" title="Nonaktifkan"><i class="fas fa-toggle-off"></i></button>';
                                echo '</form>';
                            } else {
                                echo '<form method="POST" style="display:inline;">';
                                echo '<input type="hidden" name="id" value="' . $row['jenis_retribusi_id'] . '">';
                                echo '<input type="hidden" name="status" value="activate">';
                                echo '<button type="submit" name="toggle_status" class="btn-action activate" title="Aktifkan"><i class="fas fa-toggle-on"></i></button>';
                                echo '</form>';
                            }
                            
                            // Delete button
                            echo '<button data-id="' . $row['jenis_retribusi_id'] . '" class="btn-action delete deleteBtn" title="Hapus"><i class="fas fa-trash"></i></button>';
                            
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">Tidak ada data jenis retribusi yang ditemukan</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Konfirmasi Hapus</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus jenis retribusi ini?</p>
            <p>Jika jenis retribusi ini telah digunakan dalam tagihan, maka statusnya akan diubah menjadi tidak aktif.</p>
        </div>
        <div class="modal-footer">
            <form action="" method="POST">
                <input type="hidden" name="id" id="deleteRetribusiId">
                <button type="button" class="btn btn-secondary close-modal">Batal</button>
                <button type="submit" name="delete_retribusi" class="btn btn-danger">Hapus</button>
            </form>
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
/* Stats Container */
.stats-container {
    margin-bottom: 2rem;
}

.stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.stats-card {
    flex: 1;
    min-width: 200px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.stats-icon {
    font-size: 2rem;
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.05);
}

.stats-info {
    flex: 1;
}

.stats-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #555;
}

.stats-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: #333;
}

/* Stats Card Colors */
.stats-card.primary {
    border-left: 4px solid #4e73df;
}

.stats-card.primary .stats-icon {
    color: #4e73df;
    background-color: rgba(78, 115, 223, 0.1);
}

.stats-card.success {
    border-left: 4px solid #1cc88a;
}

.stats-card.success .stats-icon {
    color: #1cc88a;
    background-color: rgba(28, 200, 138, 0.1);
}

.stats-card.info {
    border-left: 4px solid #36b9cc;
}

.stats-card.info .stats-icon {
    color: #36b9cc;
    background-color: rgba(54, 185, 204, 0.1);
}

.stats-card.warning {
    border-left: 4px solid #f6c23e;
}

.stats-card.warning .stats-icon {
    color: #f6c23e;
    background-color: rgba(246, 194, 62, 0.1);
}

.stats-card.danger {
    border-left: 4px solid #e74a3b;
}

.stats-card.danger .stats-icon {
    color: #e74a3b;
    background-color: rgba(231, 74, 59, 0.1);
}

/* Filter Container */
.filter-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    margin-bottom: 2rem;
}

.filter-form {
    width: 100%;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: flex-end;
}

.filter-row:last-child {
    margin-bottom: 0;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #555;
}

.search-group {
    flex: 2;
    min-width: 250px;
    display: flex;
    gap: 0.5rem;
}

.search-group .form-control {
    flex: 1;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-group {
    display: flex;
    gap: 0.5rem;
}

.batch-actions {
    display: flex;
    gap: 0.5rem;
}

/* Data Card */
.data-card {
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

.card-header-info {
    font-size: 0.875rem;
    color: #6c757d;
}

.card-body {
    padding: 1.25rem;
}

/* Table Styles */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    background-color: #f8f9fc;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #5a5c69;
    border-bottom: 1px solid #e3e6f0;
}

.admin-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e3e6f0;
    color: #5a5c69;
}

.admin-table tbody tr:hover {
    background-color: #f8f9fc;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

.table-responsive {
    overflow-x: auto;
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

/* Action Cell Buttons */
.action-cell {
    white-space: nowrap;
    display: flex;
    gap: 0.25rem;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    background-color: #f8f9fc;
    color: #5a5c69;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-action:hover {
    background-color: #eaecf4;
}

.btn-action.view:hover {
    background-color: #4e73df;
    color: white;
}

.btn-action.edit:hover {
    background-color: #1cc88a;
    color: white;
}

.btn-action.add:hover {
    background-color: #36b9cc;
    color: white;
}

.btn-action.delete:hover {
    background-color: #e74a3b;
    color: white;
}

.btn-action.activate:hover {
    background-color: #1cc88a;
    color: white;
}

.btn-action.deactivate:hover {
    background-color: #f6c23e;
    color: white;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    padding: 1.25rem;
    gap: 0.25rem;
}

.page-link {
    display: inline-block;
    padding: 0.5rem 0.75rem;
    background-color: #fff;
    border: 1px solid #dddfeb;
    color: #4e73df;
    font-size: 0.875rem;
    line-height: 1;
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.2s;
}

.page-link:hover {
    background-color: #eaecf4;
    color: #2e59d9;
}

.page-link.active {
    background-color: #4e73df;
    border-color: #4e73df;
    color: white;
}

.page-link.disabled {
    color: #b7b9cc;
    pointer-events: none;
    cursor: default;
}

.page-link.dots {
    border: none;
    background: transparent;
    cursor: default;
}

.page-link.dots:hover {
    background: transparent;
}

/* Modals */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.modal-content {
    background-color: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.modal-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    padding: 1.25rem;
    border-top: 1px solid #e3e6f0;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.close {
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #e74a3b;
}

/* Forms */
.form-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.admin-form {
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
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

/* Selected Items in Modals */
.selected-items-container {
    margin-top: 1rem;
    border-top: 1px solid #e3e6f0;
    padding-top: 1rem;
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.selected-badge {
    background-color: #f8f9fc;
    border: 1px solid #e3e6f0;
    border-radius: 30px;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    white-space: nowrap;
}

.selected-badge.more {
    background-color: #4e73df;
    color: white;
    border-color: #4e73df;
}

/* Usage Stats for Form Detail */
.usage-stats {
    display: flex;
    gap: 1.5rem;
    margin: 1rem 0;
}

.usage-stat {
    background-color: #f8f9fc;
    border-radius: 8px;
    padding: 1rem;
    flex: 1;
    text-align: center;
}

.stat-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #5a5c69;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #4e73df;
}

/* Utility Classes */
.text-center {
    text-align: center;
}

.mt-4 {
    margin-top: 1.5rem;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .filter-group,
    .search-group {
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .stats-row {
        flex-direction: column;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .action-cell {
        flex-wrap: wrap;
    }
    
    .usage-stats {
        flex-direction: column;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete modal functionality
    const deleteModal = document.getElementById('deleteModal');
    const deleteButtons = document.querySelectorAll('.deleteBtn');
    const deleteRetribusiIdInput = document.getElementById('deleteRetribusiId');
    const closeButtons = document.querySelectorAll('.close, .close-modal');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const retribusiId = this.getAttribute('data-id');
            deleteRetribusiIdInput.value = retribusiId;
            deleteModal.style.display = 'flex';
        });
    });
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>