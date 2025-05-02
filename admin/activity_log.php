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

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Initialize filters
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_activity = isset($_GET['activity']) ? $_GET['activity'] : '';
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Build query with filters
$query_conditions = [];
$query_params = [];

// Base query
$base_query = "SELECT la.log_id, la.aktivitas, la.deskripsi, la.ip_address, la.user_agent, la.created_at, u.nama, u.role
               FROM log_aktivitas la
               LEFT JOIN users u ON la.user_id = u.user_id";

// Apply filters
if (!empty($filter_user)) {
    $query_conditions[] = "(u.nama LIKE ? OR u.user_id = ?)";
    $query_params[] = "%$filter_user%";
    $query_params[] = $filter_user;
}

if (!empty($filter_activity)) {
    $query_conditions[] = "la.aktivitas LIKE ?";
    $query_params[] = "%$filter_activity%";
}

if (!empty($filter_date_start)) {
    $query_conditions[] = "DATE(la.created_at) >= ?";
    $query_params[] = $filter_date_start;
}

if (!empty($filter_date_end)) {
    $query_conditions[] = "DATE(la.created_at) <= ?";
    $query_params[] = $filter_date_end;
}

// Combine conditions
$where_clause = '';
if (!empty($query_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $query_conditions);
}

// Count total records with filters
$count_query = $base_query . $where_clause;
$stmt = mysqli_prepare($koneksi, $count_query);

if (!empty($query_params)) {
    $types = str_repeat('s', count($query_params));
    mysqli_stmt_bind_param($stmt, $types, ...$query_params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_records = mysqli_num_rows($result);
$total_pages = ceil($total_records / $records_per_page);

// Get log data with pagination
$data_query = $base_query . $where_clause . " ORDER BY la.created_at DESC LIMIT ?, ?";
$stmt = mysqli_prepare($koneksi, $data_query);

if (!empty($query_params)) {
    $params = $query_params;
    $params[] = $offset;
    $params[] = $records_per_page;
    $types = str_repeat('s', count($query_params)) . 'ii';
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $offset, $records_per_page);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get recent users for filter dropdown
$users_query = "SELECT DISTINCT u.user_id, u.nama 
               FROM users u 
               INNER JOIN log_aktivitas la ON u.user_id = la.user_id 
               ORDER BY u.nama ASC 
               LIMIT 100";
$users_result = mysqli_query($koneksi, $users_query);

// Get common activities for filter dropdown
$activities_query = "SELECT DISTINCT aktivitas 
                    FROM log_aktivitas 
                    GROUP BY aktivitas 
                    ORDER BY COUNT(*) DESC 
                    LIMIT 15";
$activities_result = mysqli_query($koneksi, $activities_query);

// Prepare variables for page
$page_title = "Log Aktivitas";
$current_page = "activity_log";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Log Aktivitas Sistem</h2>
        <p>Pantau aktivitas pengguna dalam sistem</p>
    </div>

    <!-- Filter Section -->
    <div class="filter-container">
        <form action="" method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="user">Pengguna</label>
                    <select name="user" id="user" class="form-control">
                        <option value="">Semua Pengguna</option>
                        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nama']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="activity">Jenis Aktivitas</label>
                    <select name="activity" id="activity" class="form-control">
                        <option value="">Semua Aktivitas</option>
                        <?php while ($activity = mysqli_fetch_assoc($activities_result)): ?>
                            <option value="<?php echo $activity['aktivitas']; ?>" <?php echo $filter_activity == $activity['aktivitas'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($activity['aktivitas']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_start">Tanggal Mulai</label>
                    <input type="date" name="date_start" id="date_start" class="form-control" value="<?php echo $filter_date_start; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_end">Tanggal Akhir</label>
                    <input type="date" name="date_end" id="date_end" class="form-control" value="<?php echo $filter_date_end; ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn">Filter</button>
                    <a href="activity_log.php" class="btn btn-outline">Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Activity Log Table -->
    <div class="data-card">
        <div class="card-header">
            <h3>Daftar Aktivitas</h3>
            <div class="header-actions">
                <button id="exportLogBtn" class="btn btn-sm">Export Log CSV</button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal & Waktu</th>
                        <th>Pengguna</th>
                        <th>Role</th>
                        <th>Aktivitas</th>
                        <th>IP Address</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        $row_number = ($page - 1) * $records_per_page + 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Format datetime
                            $tanggal = date('d-m-Y H:i', strtotime($row['created_at']));
                            
                            echo '<tr>';
                            echo '<td>' . $row_number++ . '</td>';
                            echo '<td>' . $tanggal . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama'] ?? 'System') . '</td>';
                            echo '<td>' . ucfirst($row['role'] ?? '-') . '</td>';
                            echo '<td>' . htmlspecialchars($row['aktivitas']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['ip_address']) . '</td>';
                            echo '<td><button class="btn-sm detail-log" data-id="' . $row['log_id'] . '">Detail</button></td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">Tidak ada data aktivitas</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" class="page-link">«</a>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" class="page-link">‹</a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" class="page-link">›</a>
                <a href="?page=<?php echo $total_pages; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>" class="page-link">»</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Detail Modal -->
<div id="logDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Detail Log Aktivitas</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="logDetailContent">
            <div class="loading">Memuat...</div>
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
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality
    const modal = document.getElementById('logDetailModal');
    const modalContent = document.getElementById('logDetailContent');
    const closeBtn = modal.querySelector('.close');
    const detailButtons = document.querySelectorAll('.detail-log');
    
    detailButtons.forEach(button => {
        button.addEventListener('click', function() {
            const logId = this.getAttribute('data-id');
            fetchLogDetail(logId);
        });
    });
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Fetch log detail function
    function fetchLogDetail(logId) {
        modalContent.innerHTML = '<div class="loading">Memuat...</div>';
        modal.style.display = 'block';
        
        fetch(`log_detail.php?id=${logId}`)
            .then(response => response.text())
            .then(data => {
                modalContent.innerHTML = data;
            })
            .catch(error => {
                modalContent.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            });
    }
    
    // Export to CSV functionality
    const exportBtn = document.getElementById('exportLogBtn');
    
    exportBtn.addEventListener('click', function() {
        // Prepare URL with current filters
        let params = new URLSearchParams(window.location.search);
        params.delete('page'); // Don't need page for export
        params.append('export', 'csv');
        
        // Redirect to export endpoint
        window.location.href = `export_log.php?${params.toString()}`;
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>