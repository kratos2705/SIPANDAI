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
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitizeInput($_GET['role']) : 'all';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(nama LIKE '%$search%' OR nik LIKE '%$search%' OR email LIKE '%$search%' OR nomor_telepon LIKE '%$search%')";
}

if ($role_filter !== 'all') {
    $conditions[] = "role = '$role_filter'";
}

if ($status_filter !== 'all') {
    $active = ($status_filter === 'active') ? 'TRUE' : 'FALSE';
    $conditions[] = "active = $active";
}

$condition_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Build sort order
switch ($sort) {
    case 'oldest':
        $sort_sql = "ORDER BY created_at ASC";
        break;
    case 'name_asc':
        $sort_sql = "ORDER BY nama ASC";
        break;
    case 'name_desc':
        $sort_sql = "ORDER BY nama DESC";
        break;
    case 'newest':
    default:
        $sort_sql = "ORDER BY created_at DESC";
        break;
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM users $condition_sql";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get users data
$users_query = "SELECT user_id, nik, nama, email, nomor_telepon, alamat, role, active, created_at 
               FROM users $condition_sql $sort_sql LIMIT $offset, $per_page";
$users_result = mysqli_query($koneksi, $users_query);

// Count users by role for summary
$role_counts_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$role_counts_result = mysqli_query($koneksi, $role_counts_query);
$role_counts = [
    'admin' => 0,
    'kepala_desa' => 0,
    'warga' => 0
];

if ($role_counts_result) {
    while ($row = mysqli_fetch_assoc($role_counts_result)) {
        $role_counts[$row['role']] = $row['count'];
    }
}

// Count active and inactive users
$status_counts_query = "SELECT active, COUNT(*) as count FROM users GROUP BY active";
$status_counts_result = mysqli_query($koneksi, $status_counts_query);
$active_users = 0;
$inactive_users = 0;

if ($status_counts_result) {
    while ($row = mysqli_fetch_assoc($status_counts_result)) {
        if ($row['active'] == 1) {
            $active_users = $row['count'];
        } else {
            $inactive_users = $row['count'];
        }
    }
}

// Success message
$success_message = '';
if (isset($_SESSION['user_success'])) {
    $success_message = $_SESSION['user_success'];
    unset($_SESSION['user_success']);
}

// Error message
$error_message = '';
if (isset($_SESSION['user_error'])) {
    $error_message = $_SESSION['user_error'];
    unset($_SESSION['user_error']);
}

// Set page title
$page_title = "Data Warga";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">


    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Data Warga</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; Data Warga
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
                    <div class="stats-icon">👥</div>
                    <div class="stats-info">
                        <h3>Total Warga</h3>
                        <p class="stats-value"><?php echo $role_counts['warga']; ?></p>
                    </div>
                </div>
                <div class="stats-card info">
                    <div class="stats-icon">👤</div>
                    <div class="stats-info">
                        <h3>Admin</h3>
                        <p class="stats-value"><?php echo $role_counts['admin']; ?></p>
                    </div>
                </div>
                <div class="stats-card warning">
                    <div class="stats-icon">🏢</div>
                    <div class="stats-info">
                        <h3>Kepala Desa</h3>
                        <p class="stats-value"><?php echo $role_counts['kepala_desa']; ?></p>
                    </div>
                </div>
                <div class="stats-card success">
                    <div class="stats-icon">✅</div>
                    <div class="stats-info">
                        <h3>Aktif</h3>
                        <p class="stats-value"><?php echo $active_users; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="filter-container">
            <form action="warga.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="role">Peran:</label>
                    <select name="role" id="role">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>Semua Peran</option>
                        <option value="warga" <?php echo $role_filter === 'warga' ? 'selected' : ''; ?>>Warga</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="kepala_desa" <?php echo $role_filter === 'kepala_desa' ? 'selected' : ''; ?>>Kepala Desa</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
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
                    <input type="text" name="search" placeholder="Cari nama/NIK/email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="warga.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Action Buttons -->
        <div class="button-container">
            <?php if ($user_role === 'admin'): ?>
                <a href="user_tambah.php" class="btn btn-primary">
                    <span class="btn-icon">➕</span> Tambah Pengguna
                </a>
            <?php endif; ?>
            <a href="export_warga.php?role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-success">
                <span class="btn-icon">📥</span> Export Excel
            </a>
            <a href="javascript:void(0);" onclick="window.print();" class="btn btn-secondary">
                <span class="btn-icon">🖨️</span> Cetak Daftar
            </a>
        </div>

        <!-- Users Table -->
        <div class="data-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">NIK</th>
                            <th width="20%">Nama</th>
                            <th width="15%">Kontak</th>
                            <th width="20%">Alamat</th>
                            <th width="10%">Peran</th>
                            <th width="5%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($users_result) > 0) {
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($users_result)) {
                                // Format tanggal
                                $tanggal_registrasi = date('d-m-Y', strtotime($row['created_at']));

                                // Format status
                                $status_class = $row['active'] ? 'status-completed' : 'status-rejected';
                                $status_text = $row['active'] ? 'Aktif' : 'Nonaktif';

                                // Format role
                                $role_text = '';
                                switch ($row['role']) {
                                    case 'admin':
                                        $role_text = 'Admin';
                                        break;
                                    case 'kepala_desa':
                                        $role_text = 'Kepala Desa';
                                        break;
                                    case 'warga':
                                        $role_text = 'Warga';
                                        break;
                                    default:
                                        $role_text = ucfirst($row['role']);
                                }

                                echo '<tr>';
                                echo '<td>' . $no++ . '</td>';
                                echo '<td>' . htmlspecialchars($row['nik']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                                echo '<td>';
                                echo '<div>' . htmlspecialchars($row['email']) . '</div>';
                                echo '<div>' . htmlspecialchars($row['nomor_telepon']) . '</div>';
                                echo '</td>';
                                echo '<td>' . htmlspecialchars($row['alamat'] ?? 'Belum diisi') . '</td>';
                                echo '<td>' . $role_text . '</td>';
                                echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                                echo '<td class="actions">';
                                echo '<a href="user_detail.php?id=' . $row['user_id'] . '" class="btn-sm btn-info">Detail</a>';

                                // Show edit button only for admin
                                if ($user_role === 'admin') {
                                    echo '<a href="user_edit.php?id=' . $row['user_id'] . '" class="btn-sm btn-primary">Edit</a>';
                                }

                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Tidak ada data warga yang ditemukan</td></tr>';
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
                                <a href="?page=1&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a>
                            </li>
                            <li>
                                <a href="?page=<?php echo $page - 1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&lsaquo;</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active_class = $i == $page ? 'active' : '';
                            echo '<li class="' . $active_class . '"><a href="?page=' . $i . '&role=' . $role_filter . '&status=' . $status_filter . '&sort=' . $sort . '&search=' . urlencode($search) . '">' . $i . '</a></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="?page=<?php echo $page + 1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&rsaquo;</a>
                            </li>
                            <li>
                                <a href="?page=<?php echo $total_pages; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a>
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
        document.getElementById('role').addEventListener('change', function() {
            document.querySelector('.filter-form').submit();
        });

        document.getElementById('status').addEventListener('change', function() {
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