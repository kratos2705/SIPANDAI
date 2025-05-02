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
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$periode_filter = isset($_GET['periode']) ? sanitizeInput($_GET['periode']) : 'all';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];

if ($tahun_filter > 0) {
    $conditions[] = "tahun_anggaran = '$tahun_filter'";
}

if ($periode_filter !== 'all') {
    $conditions[] = "periode = '$periode_filter'";
}

if ($status_filter !== 'all') {
    $conditions[] = "status = '$status_filter'";
}

$condition_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM anggaran_desa $condition_sql";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get anggaran data
$anggaran_query = "SELECT ad.*, u.nama as created_by_name
                   FROM anggaran_desa ad
                   LEFT JOIN users u ON ad.created_by = u.user_id
                   $condition_sql
                   ORDER BY ad.tahun_anggaran DESC, FIELD(ad.periode, 'tahunan', 'semester1', 'semester2', 'triwulan1', 'triwulan2', 'triwulan3', 'triwulan4')
                   LIMIT $offset, $per_page";
$anggaran_result = mysqli_query($koneksi, $anggaran_query);

// Get available years for filter
$years_query = "SELECT DISTINCT tahun_anggaran FROM anggaran_desa ORDER BY tahun_anggaran DESC";
$years_result = mysqli_query($koneksi, $years_query);

// Count budgets by status for summary
$summary_query = "SELECT status, COUNT(*) as count, SUM(total_anggaran) as total FROM anggaran_desa GROUP BY status";
$summary_result = mysqli_query($koneksi, $summary_query);
$status_counts = [
    'rencana' => ['count' => 0, 'total' => 0],
    'disetujui' => ['count' => 0, 'total' => 0],
    'realisasi' => ['count' => 0, 'total' => 0],
    'laporan_akhir' => ['count' => 0, 'total' => 0]
];

$total_anggaran = 0;

if ($summary_result) {
    while ($row = mysqli_fetch_assoc($summary_result)) {
        $status_counts[$row['status']]['count'] = $row['count'];
        $status_counts[$row['status']]['total'] = $row['total'];
        $total_anggaran += $row['total'];
    }
}

// Success message
$success_message = '';
if (isset($_SESSION['anggaran_success'])) {
    $success_message = $_SESSION['anggaran_success'];
    unset($_SESSION['anggaran_success']);
}

// Error message
$error_message = '';
if (isset($_SESSION['anggaran_error'])) {
    $error_message = $_SESSION['anggaran_error'];
    unset($_SESSION['anggaran_error']);
}

// Set page title
$page_title = "Transparansi Anggaran";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">

    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Transparansi Anggaran Desa</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; Transparansi Anggaran
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
                <div class="stats-card primary">
                    <div class="stats-info">
                        <h3>Total Anggaran</h3>
                        <p class="stats-value">Rp <?php echo number_format($total_anggaran, 0, ',', '.'); ?></p>
                    </div>
                </div>
                <div class="stats-card warning">
                    <div class="stats-info">
                        <h3>Rencana Anggaran</h3>
                        <p class="stats-value">Rp <?php echo number_format($status_counts['rencana']['total'], 0, ',', '.'); ?></p>
                    </div>
                </div>
                <div class="stats-card info">
                    <div class="stats-info">
                        <h3>Disetujui</h3>
                        <p class="stats-value">Rp <?php echo number_format($status_counts['disetujui']['total'], 0, ',', '.'); ?></p>
                    </div>
                </div>
                <div class="stats-card success">
                    <div class="stats-info">
                        <h3>Realisasi</h3>
                        <p class="stats-value">Rp <?php echo number_format($status_counts['realisasi']['total'], 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="filter-container">
            <form action="anggaran.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="tahun">Tahun:</label>
                    <select name="tahun" id="tahun">
                        <option value="0" <?php echo $tahun_filter === 0 ? 'selected' : ''; ?>>Semua Tahun</option>
                        <?php
                        while ($year = mysqli_fetch_assoc($years_result)) {
                            $selected = $tahun_filter == $year['tahun_anggaran'] ? 'selected' : '';
                            echo '<option value="' . $year['tahun_anggaran'] . '" ' . $selected . '>' . $year['tahun_anggaran'] . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="periode">Periode:</label>
                    <select name="periode" id="periode">
                        <option value="all" <?php echo $periode_filter === 'all' ? 'selected' : ''; ?>>Semua Periode</option>
                        <option value="tahunan" <?php echo $periode_filter === 'tahunan' ? 'selected' : ''; ?>>Tahunan</option>
                        <option value="semester1" <?php echo $periode_filter === 'semester1' ? 'selected' : ''; ?>>Semester 1</option>
                        <option value="semester2" <?php echo $periode_filter === 'semester2' ? 'selected' : ''; ?>>Semester 2</option>
                        <option value="triwulan1" <?php echo $periode_filter === 'triwulan1' ? 'selected' : ''; ?>>Triwulan 1</option>
                        <option value="triwulan2" <?php echo $periode_filter === 'triwulan2' ? 'selected' : ''; ?>>Triwulan 2</option>
                        <option value="triwulan3" <?php echo $periode_filter === 'triwulan3' ? 'selected' : ''; ?>>Triwulan 3</option>
                        <option value="triwulan4" <?php echo $periode_filter === 'triwulan4' ? 'selected' : ''; ?>>Triwulan 4</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="rencana" <?php echo $status_filter === 'rencana' ? 'selected' : ''; ?>>Rencana</option>
                        <option value="disetujui" <?php echo $status_filter === 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                        <option value="realisasi" <?php echo $status_filter === 'realisasi' ? 'selected' : ''; ?>>Realisasi</option>
                        <option value="laporan_akhir" <?php echo $status_filter === 'laporan_akhir' ? 'selected' : ''; ?>>Laporan Akhir</option>
                    </select>
                </div>

                <div class="search-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="anggaran.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Buttons -->
        <div class="button-container">
            <a href="anggaran_tambah.php" class="btn btn-primary">
                <span class="btn-icon">➕</span> Tambah Anggaran
            </a>
            <a href="laporan_anggaran.php" class="btn btn-info">
                <span class="btn-icon">📊</span> Laporan Anggaran
            </a>
            <a href="export_anggaran.php?tahun=<?php echo $tahun_filter; ?>&periode=<?php echo $periode_filter; ?>&status=<?php echo $status_filter; ?>" class="btn btn-success">
                <span class="btn-icon">📥</span> Export Excel
            </a>
        </div>

        <!-- Budget Table -->
        <div class="data-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Tahun</th>
                            <th width="15%">Periode</th>
                            <th width="20%">Total Anggaran</th>
                            <th width="15%">Status</th>
                            <th width="20%">Dibuat Oleh</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($anggaran_result) > 0) {
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($anggaran_result)) {
                                // Format periode
                                $periode_text = "";
                                switch ($row['periode']) {
                                    case 'tahunan':
                                        $periode_text = "Tahunan";
                                        break;
                                    case 'semester1':
                                        $periode_text = "Semester 1";
                                        break;
                                    case 'semester2':
                                        $periode_text = "Semester 2";
                                        break;
                                    case 'triwulan1':
                                        $periode_text = "Triwulan 1";
                                        break;
                                    case 'triwulan2':
                                        $periode_text = "Triwulan 2";
                                        break;
                                    case 'triwulan3':
                                        $periode_text = "Triwulan 3";
                                        break;
                                    case 'triwulan4':
                                        $periode_text = "Triwulan 4";
                                        break;
                                    default:
                                        $periode_text = ucfirst($row['periode']);
                                }

                                // Determine status class and text
                                $status_class = "";
                                $status_text = "";
                                switch ($row['status']) {
                                    case 'rencana':
                                        $status_class = "status-pending";
                                        $status_text = "Rencana";
                                        break;
                                    case 'disetujui':
                                        $status_class = "status-processing";
                                        $status_text = "Disetujui";
                                        break;
                                    case 'realisasi':
                                        $status_class = "status-processing";
                                        $status_text = "Realisasi";
                                        break;
                                    case 'laporan_akhir':
                                        $status_class = "status-completed";
                                        $status_text = "Laporan Akhir";
                                        break;
                                    default:
                                        $status_class = "status-pending";
                                        $status_text = "Rencana";
                                }

                                echo '<tr>';
                                echo '<td>' . $no++ . '</td>';
                                echo '<td>' . $row['tahun_anggaran'] . '</td>';
                                echo '<td>' . $periode_text . '</td>';
                                echo '<td>Rp ' . number_format($row['total_anggaran'], 0, ',', '.') . '</td>';
                                echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                                echo '<td>' . htmlspecialchars($row['created_by_name'] ?? 'Admin') . '<br><small>' . date('d-m-Y', strtotime($row['created_at'])) . '</small></td>';
                                echo '<td class="actions">';
                                echo '<a href="anggaran_detail.php?id=' . $row['anggaran_id'] . '" class="btn-sm btn-info">Detail</a>';

                                // Show edit button only for non-finalized budgets
                                if ($row['status'] != 'laporan_akhir' && ($user_role == 'admin' || $user_role == 'kepala_desa')) {
                                    echo '<a href="anggaran_edit.php?id=' . $row['anggaran_id'] . '" class="btn-sm btn-primary">Edit</a>';
                                }

                                // Show delete button only for admin users
                                if ($user_role == 'admin' && $row['status'] == 'rencana') {
                                    echo '<a href="anggaran_hapus.php?id=' . $row['anggaran_id'] . '" class="btn-sm btn-danger" onclick="return confirm(\'Apakah Anda yakin ingin menghapus anggaran ini?\')">Hapus</a>';
                                }

                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="text-center">Tidak ada data anggaran yang ditemukan</td></tr>';
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
                                <a href="?page=1&tahun=<?php echo $tahun_filter; ?>&periode=<?php echo $periode_filter; ?>&status=<?php echo $status_filter; ?>">&laquo;</a>
                            </li>
                            <li>
                                <a href="?page=<?php echo $page - 1; ?>&tahun=<?php echo $tahun_filter; ?>&periode=<?php echo $periode_filter; ?>&status=<?php echo $status_filter; ?>">&lsaquo;</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active_class = $i == $page ? 'active' : '';
                            echo '<li class="' . $active_class . '"><a href="?page=' . $i . '&tahun=' . $tahun_filter . '&periode=' . $periode_filter . '&status=' . $status_filter . '">' . $i . '</a></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="?page=<?php echo $page + 1; ?>&tahun=<?php echo $tahun_filter; ?>&periode=<?php echo $periode_filter; ?>&status=<?php echo $status_filter; ?>">&rsaquo;</a>
                            </li>
                            <li>
                                <a href="?page=<?php echo $total_pages; ?>&tahun=<?php echo $tahun_filter; ?>&periode=<?php echo $periode_filter; ?>&status=<?php echo $status_filter; ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
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
        // Auto-submit when filters change
        document.getElementById('tahun').addEventListener('change', function() {
            document.querySelector('.filter-form').submit();
        });

        document.getElementById('periode').addEventListener('change', function() {
            document.querySelector('.filter-form').submit();
        });

        document.getElementById('status').addEventListener('change', function() {
            document.querySelector('.filter-form').submit();
        });
    });
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>