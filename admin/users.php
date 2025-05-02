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

// Initialize filter parameters with defaults
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($koneksi, $_GET['role']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, $_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Process actions (activate/deactivate user)
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $action_user_id = (int)$_POST['user_id'];
    $action_type = $_POST['action'];
    
    if ($action_type === 'activate') {
        $update_query = "UPDATE users SET active = TRUE WHERE user_id = $action_user_id";
        if (mysqli_query($koneksi, $update_query)) {
            $_SESSION['success_message'] = 'Pengguna berhasil diaktifkan.';
        } else {
            $_SESSION['error_message'] = 'Gagal mengaktifkan pengguna.';
        }
    } elseif ($action_type === 'deactivate') {
        $update_query = "UPDATE users SET active = FALSE WHERE user_id = $action_user_id";
        if (mysqli_query($koneksi, $update_query)) {
            $_SESSION['success_message'] = 'Pengguna berhasil dinonaktifkan.';
        } else {
            $_SESSION['error_message'] = 'Gagal menonaktifkan pengguna.';
        }
    } elseif ($action_type === 'delete' && $user_role === 'admin') {
        // Check if user has any related records before deletion
        $check_query = "SELECT COUNT(*) as count FROM pengajuan_dokumen WHERE user_id = $action_user_id";
        $check_result = mysqli_query($koneksi, $check_query);
        $related_records = mysqli_fetch_assoc($check_result)['count'];
        
        if ($related_records > 0) {
            $_SESSION['error_message'] = 'Tidak dapat menghapus pengguna karena masih memiliki data pengajuan.';
        } else {
            $delete_query = "DELETE FROM users WHERE user_id = $action_user_id";
            if (mysqli_query($koneksi, $delete_query)) {
                $_SESSION['success_message'] = 'Pengguna berhasil dihapus.';
            } else {
                $_SESSION['error_message'] = 'Gagal menghapus pengguna.';
            }
        }
    }
    
    // Redirect to refresh page without resubmitting form
    header("Location: users.php" . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// Build the query with filters
$base_query = "FROM users WHERE 1=1";
if (!empty($search)) {
    $base_query .= " AND (nama LIKE '%$search%' OR email LIKE '%$search%' OR nik LIKE '%$search%')";
}
if (!empty($role_filter)) {
    $base_query .= " AND role = '$role_filter'";
}
if ($status_filter !== '') {
    $active_value = ($status_filter === 'active') ? 'TRUE' : 'FALSE';
    $base_query .= " AND active = $active_value";
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total $base_query";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Get users with pagination
$users_query = "SELECT * $base_query ORDER BY created_at DESC LIMIT $offset, $limit";
$users_result = mysqli_query($koneksi, $users_query);

// Prepare variables for page
$page_title = "Manajemen Pengguna";
$current_page = "users";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Manajemen Pengguna</h2>
        <p>Kelola data pengguna sistem desa digital.</p>
    </div>
<!-- User Statistics -->
<div class="stats-section">
        <div class="stats-row">
            <div class="stats-card">
                <div class="stats-icon">👥</div>
                <div class="stats-info">
                    <h3>Jumlah Pengguna</h3>
                    <p class="stats-value"><?php echo $total_records; ?></p>
                </div>
            </div>
            
            <?php
            // Get user stats by role
            $role_stats_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
            $role_stats_result = mysqli_query($koneksi, $role_stats_query);
            $role_stats = [];
            
            while ($row = mysqli_fetch_assoc($role_stats_result)) {
                $role_stats[$row['role']] = $row['count'];
            }
            
            // Get active/inactive stats
            $status_stats_query = "SELECT active, COUNT(*) as count FROM users GROUP BY active";
            $status_stats_result = mysqli_query($koneksi, $status_stats_query);
            $status_stats = ['active' => 0, 'inactive' => 0];
            
            while ($row = mysqli_fetch_assoc($status_stats_result)) {
                if ($row['active']) {
                    $status_stats['active'] = $row['count'];
                } else {
                    $status_stats['inactive'] = $row['count'];
                }
            }
            ?>
            
            <div class="stats-card">
                <div class="stats-icon">🛠️</div>
                <div class="stats-info">
                    <h3>Admin</h3>
                    <p class="stats-value"><?php echo isset($role_stats['admin']) ? $role_stats['admin'] : 0; ?></p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">🏠</div>
                <div class="stats-info">
                    <h3>Kepala Desa</h3>
                    <p class="stats-value"><?php echo isset($role_stats['kepala_desa']) ? $role_stats['kepala_desa'] : 0; ?></p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">👨‍👩‍👧‍👦</div>
                <div class="stats-info">
                    <h3>Warga</h3>
                    <p class="stats-value"><?php echo isset($role_stats['warga']) ? $role_stats['warga'] : 0; ?></p>
                </div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stats-card success">
                <div class="stats-icon">✅</div>
                <div class="stats-info">
                    <h3>Pengguna Aktif</h3>
                    <p class="stats-value"><?php echo $status_stats['active']; ?></p>
                </div>
            </div>
            
            <div class="stats-card warning">
                <div class="stats-icon">⚠️</div>
                <div class="stats-info">
                    <h3>Pengguna Nonaktif</h3>
                    <p class="stats-value"><?php echo $status_stats['inactive']; ?></p>
                </div>
            </div>
            
            <?php
            // Get new users this month
            $current_month = date('Y-m');
            $new_users_query = "SELECT COUNT(*) as count FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month'";
            $new_users_result = mysqli_query($koneksi, $new_users_query);
            $new_users = mysqli_fetch_assoc($new_users_result)['count'];
            ?>
            
            <div class="stats-card info">
                <div class="stats-icon">📆</div>
                <div class="stats-info">
                    <h3>Pengguna Baru Bulan Ini</h3>
                    <p class="stats-value"><?php echo $new_users; ?></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Notification Messages -->
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

    <!-- Filter and Search Section -->
    <div class="filter-section">
        <form action="users.php" method="GET" class="filter-form">
            <div class="search-box">
                <input type="text" name="search" placeholder="Cari nama, email, NIK..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
            
            <div class="filter-group">
                <label for="role">Peran:</label>
                <select name="role" id="role" onchange="this.form.submit()">
                    <option value="">Semua Peran</option>
                    <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="kepala_desa" <?php echo ($role_filter === 'kepala_desa') ? 'selected' : ''; ?>>Kepala Desa</option>
                    <option value="warga" <?php echo ($role_filter === 'warga') ? 'selected' : ''; ?>>Warga</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status">Status:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Aktif</option>
                    <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn">Filter</button>
                <a href="users.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <div class="action-buttons">
            <a href="user_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Pengguna</a>
            <?php if ($user_role === 'admin'): ?>
            <a href="user_export.php" class="btn btn-outline"><i class="fas fa-download"></i> Export Data</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Users Table -->
    <div class="data-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="12%">NIK</th>
                        <th width="18%">Nama</th>
                        <th width="15%">Email</th>
                        <th width="10%">Telp</th>
                        <th width="10%">Peran</th>
                        <th width="10%">Status</th>
                        <th width="10%">Tgl Daftar</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($users_result) > 0) {
                        $counter = $offset + 1;
                        while ($row = mysqli_fetch_assoc($users_result)) {
                            $join_date = date('d-m-Y', strtotime($row['created_at']));
                            
                            // Determine user status
                            $status_class = $row['active'] ? "status-completed" : "status-rejected";
                            $status_text = $row['active'] ? "Aktif" : "Nonaktif";
                            
                            echo '<tr>';
                            echo '<td>' . $counter++ . '</td>';
                            echo '<td>' . (empty($row['nik']) ? '<span class="text-muted">-</span>' : htmlspecialchars($row['nik'])) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                            echo '<td>' . (empty($row['nomor_telepon']) ? '<span class="text-muted">-</span>' : htmlspecialchars($row['nomor_telepon'])) . '</td>';
                            echo '<td><span class="badge badge-' . ($row['role'] == 'admin' ? 'primary' : ($row['role'] == 'kepala_desa' ? 'success' : 'info')) . '">' . ucfirst($row['role']) . '</span></td>';
                            echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                            echo '<td>' . $join_date . '</td>';
                            echo '<td class="action-cell">';
                            echo '<div class="dropdown">';
                            echo '<button class="btn-sm dropdown-toggle">Aksi</button>';
                            echo '<div class="dropdown-content">';
                            echo '<a href="user_detail.php?id=' . $row['user_id'] . '">Detail</a>';
                            echo '<a href="user_edit.php?id=' . $row['user_id'] . '">Edit</a>';
                            
                            // Status change forms (activate/deactivate)
                            if ($row['active']) {
                                echo '<form action="users.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '') . '" method="POST">';
                                echo '<input type="hidden" name="user_id" value="' . $row['user_id'] . '">';
                                echo '<input type="hidden" name="action" value="deactivate">';
                                echo '<button type="submit" class="dropdown-button">Nonaktifkan</button>';
                                echo '</form>';
                            } else {
                                echo '<form action="users.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '') . '" method="POST">';
                                echo '<input type="hidden" name="user_id" value="' . $row['user_id'] . '">';
                                echo '<input type="hidden" name="action" value="activate">';
                                echo '<button type="submit" class="dropdown-button">Aktifkan</button>';
                                echo '</form>';
                            }
                            
                            // Delete option (admin only)
                            if ($user_role === 'admin' && $row['user_id'] != $user_id) {
                                echo '<form action="users.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '') . '" method="POST" onsubmit="return confirm(\'Anda yakin ingin menghapus pengguna ini?\');">';
                                echo '<input type="hidden" name="user_id" value="' . $row['user_id'] . '">';
                                echo '<input type="hidden" name="action" value="delete">';
                                echo '<button type="submit" class="dropdown-button text-danger">Hapus</button>';
                                echo '</form>';
                            }
                            
                            echo '</div>';
                            echo '</div>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="9" class="text-center">Tidak ada data pengguna yang ditemukan</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($role_filter)) ? '&role='.$role_filter : ''; ?><?php echo ($status_filter !== '') ? '&status='.$status_filter : ''; ?>" class="page-prev">&laquo; Sebelumnya</a>
            <?php endif; ?>
            
            <div class="page-numbers">
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1' . ((!empty($search)) ? '&search='.$search : '') . ((!empty($role_filter)) ? '&role='.$role_filter : '') . (($status_filter !== '') ? '&status='.$status_filter : '') . '">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<a href="?page=' . $i . ((!empty($search)) ? '&search='.$search : '') . ((!empty($role_filter)) ? '&role='.$role_filter : '') . (($status_filter !== '') ? '&status='.$status_filter : '') . '"' . (($page == $i) ? ' class="active"' : '') . '>' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . ((!empty($search)) ? '&search='.$search : '') . ((!empty($role_filter)) ? '&role='.$role_filter : '') . (($status_filter !== '') ? '&status='.$status_filter : '') . '">' . $total_pages . '</a>';
                }
                ?>
            </div>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($role_filter)) ? '&role='.$role_filter : ''; ?><?php echo ($status_filter !== '') ? '&status='.$status_filter : ''; ?>" class="page-next">Selanjutnya &raquo;</a>
            <?php endif; ?>
            
            <div class="page-info">
                Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (Total: <?php echo $total_records; ?> pengguna)
            </div>
        </div>
        <?php endif; ?>
    </div>

    
</div>

<script>
// Handle dropdown menu for actions
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });
    
    // Auto-hide success/error messages after 5 seconds
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