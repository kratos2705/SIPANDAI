<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (!hasUserAccess()) {
    $_SESSION['login_error'] = 'Anda tidak memiliki akses ke halaman ini.';
    redirect('../index.php');
    exit;
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

// Handle mark as read action
if (isset($_POST['mark_read']) && isset($_POST['notifikasi_id'])) {
    $notifikasi_id = mysqli_real_escape_string($koneksi, $_POST['notifikasi_id']);
    
    // Verify the notification belongs to the current user
    $check_query = "SELECT notifikasi_id FROM notifikasi WHERE notifikasi_id = '$notifikasi_id' AND user_id = '$user_id'";
    $check_result = executeQuery($koneksi, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $update_query = "UPDATE notifikasi SET is_read = TRUE WHERE notifikasi_id = '$notifikasi_id'";
        $update_result = executeQuery($koneksi, $update_query);
        
        if ($update_result) {
            $success_message = "Notifikasi ditandai sebagai telah dibaca.";
        } else {
            $error_message = "Gagal menandai notifikasi. Silakan coba lagi.";
        }
    } else {
        $error_message = "Notifikasi tidak ditemukan.";
    }
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $update_all_query = "UPDATE notifikasi SET is_read = TRUE WHERE user_id = '$user_id' AND is_read = FALSE";
    $update_all_result = executeQuery($koneksi, $update_all_query);
    
    if ($update_all_result) {
        $success_message = "Semua notifikasi telah ditandai sebagai dibaca.";
    } else {
        $error_message = "Gagal menandai semua notifikasi. Silakan coba lagi.";
    }
}

// Handle delete notification action
if (isset($_POST['delete_notifikasi']) && isset($_POST['notifikasi_id'])) {
    $notifikasi_id = mysqli_real_escape_string($koneksi, $_POST['notifikasi_id']);
    
    // Verify the notification belongs to the current user
    $check_query = "SELECT notifikasi_id FROM notifikasi WHERE notifikasi_id = '$notifikasi_id' AND user_id = '$user_id'";
    $check_result = executeQuery($koneksi, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $delete_query = "DELETE FROM notifikasi WHERE notifikasi_id = '$notifikasi_id'";
        $delete_result = executeQuery($koneksi, $delete_query);
        
        if ($delete_result) {
            $success_message = "Notifikasi berhasil dihapus.";
        } else {
            $error_message = "Gagal menghapus notifikasi. Silakan coba lagi.";
        }
    } else {
        $error_message = "Notifikasi tidak ditemukan.";
    }
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Set up filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_clause = "";

if ($filter === 'unread') {
    $filter_clause = "AND is_read = FALSE";
} elseif ($filter === 'read') {
    $filter_clause = "AND is_read = TRUE";
}

// Set up search
$search = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($koneksi, $_GET['search']);
    $search = "AND (judul LIKE '%$search_term%' OR pesan LIKE '%$search_term%')";
}

// Count total notifications for pagination
$count_query = "SELECT COUNT(*) as total FROM notifikasi 
                WHERE user_id = '$user_id' $filter_clause $search";
$count_result = executeQuery($koneksi, $count_query);
$total_records = 0;

if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
    $total_records = $row['total'];
}

$total_pages = ceil($total_records / $limit);

// Get notifications with pagination
$notifikasi_query = "SELECT notifikasi_id, judul, pesan, jenis, link, is_read, created_at 
                    FROM notifikasi 
                    WHERE user_id = '$user_id' $filter_clause $search
                    ORDER BY created_at DESC 
                    LIMIT $offset, $limit";
$notifikasi_result = executeQuery($koneksi, $notifikasi_query);

// Count unread notifications
$unread_query = "SELECT COUNT(*) as unread FROM notifikasi WHERE user_id = '$user_id' AND is_read = FALSE";
$unread_result = executeQuery($koneksi, $unread_query);
$unread_count = 0;

if ($unread_result && $row = mysqli_fetch_assoc($unread_result)) {
    $unread_count = $row['unread'];
}

// Include header
include '../includes/header.php';
?>

<main class="dashboard-content">
    <div class="container">
        <h1 class="page-title">Notifikasi</h1>
        
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
                        <form method="GET" action="notifikasi.php" class="filter-form">
                            <div class="form-group">
                                <label for="filter">Tampilkan:</label>
                                <select name="filter" id="filter" class="form-control" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                                    <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Belum Dibaca</option>
                                    <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Sudah Dibaca</option>
                                </select>
                            </div>
                            
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="search-container">
                        <form method="GET" action="notifikasi.php" class="search-form">
                            <div class="form-group">
                                <input type="text" name="search" class="form-control" placeholder="Cari notifikasi..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            
                            <?php if (isset($_GET['filter'])): ?>
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="button-container">
                    <?php if ($unread_count > 0): ?>
                    <form method="POST" action="notifikasi.php" class="d-inline">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="btn btn-outline-success">
                            <i class="fas fa-check-double"></i> Tandai Semua Dibaca
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body">
                <div class="notifikasi-summary">
                    <div class="notifikasi-count">
                        <span class="badge <?php echo $unread_count > 0 ? 'badge-danger' : 'badge-secondary'; ?>">
                            <?php echo $unread_count; ?> belum dibaca
                        </span>
                    </div>
                </div>
                
                <?php if ($notifikasi_result && mysqli_num_rows($notifikasi_result) > 0): ?>
                <div class="notifikasi-list">
                    <?php while ($row = mysqli_fetch_assoc($notifikasi_result)): ?>
                    <div class="notifikasi-item <?php echo $row['is_read'] ? 'read' : 'unread'; ?>">
                        <div class="notifikasi-icon">
                            <?php
                            $icon_class = "fa-bell";
                            switch($row['jenis']) {
                                case 'pengajuan':
                                    $icon_class = "fa-file-alt";
                                    break;
                                case 'berita':
                                    $icon_class = "fa-newspaper";
                                    break;
                                case 'retribusi':
                                    $icon_class = "fa-money-bill";
                                    break;
                                case 'akun':
                                    $icon_class = "fa-user";
                                    break;
                            }
                            ?>
                            <i class="fas <?php echo $icon_class; ?>"></i>
                        </div>
                        
                        <div class="notifikasi-content">
                            <h3 class="notifikasi-title"><?php echo htmlspecialchars($row['judul']); ?></h3>
                            <p class="notifikasi-message"><?php echo htmlspecialchars($row['pesan']); ?></p>
                            <div class="notifikasi-meta">
                                <span class="notifikasi-time">
                                    <i class="far fa-clock"></i> 
                                    <?php echo time_elapsed_string($row['created_at']); ?>
                                </span>
                                <?php if (!empty($row['jenis'])): ?>
                                <span class="notifikasi-type">
                                    <?php echo ucfirst(htmlspecialchars($row['jenis'])); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notifikasi-actions">
                            <?php if (!empty($row['link'])): ?>
                            <a href="<?php echo htmlspecialchars($row['link']); ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!$row['is_read']): ?>
                            <form method="POST" action="notifikasi.php" class="d-inline">
                                <input type="hidden" name="notifikasi_id" value="<?php echo $row['notifikasi_id']; ?>">
                                <button type="submit" name="mark_read" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <form method="POST" action="notifikasi.php" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus notifikasi ini?');">
                                <input type="hidden" name="notifikasi_id" value="<?php echo $row['notifikasi_id']; ?>">
                                <button type="submit" name="delete_notifikasi" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Next">
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
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h3>Tidak Ada Notifikasi</h3>
                    <p>
                        <?php if ($filter === 'unread'): ?>
                            Tidak ada notifikasi yang belum dibaca.
                        <?php elseif ($filter === 'read'): ?>
                            Tidak ada notifikasi yang sudah dibaca.
                        <?php else: ?>
                            Anda belum memiliki notifikasi. Notifikasi akan muncul di sini ketika ada pembaruan penting.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
// Function to display time elapsed since notification
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
?>

<style>
/* Notifikasi Page Styles */
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

.notifikasi-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.badge {
    padding: 8px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.notifikasi-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notifikasi-item {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
    position: relative;
}

.notifikasi-item.unread {
    background-color: #e8f4fd;
    border-left: 4px solid #28a745;
}

.notifikasi-item.read {
    background-color: #f8f9fa;
    border-left: 4px solid #dee2e6;
    opacity: 0.9;
}

.notifikasi-item:hover {
    background-color: #f8f9fa;
}

.notifikasi-icon {
    font-size: 1.5rem;
    color: #28a745;
    margin-right: 15px;
    min-width: 40px;
    text-align: center;
}

.notifikasi-content {
    flex: 1;
}

.notifikasi-title {
    font-size: 1.1rem;
    margin-bottom: 5px;
    color: #333;
}

.notifikasi-item.unread .notifikasi-title {
    font-weight: 600;
}

.notifikasi-message {
    color: #6c757d;
    margin-bottom: 10px;
    line-height: 1.5;
}

.notifikasi-meta {
    display: flex;
    gap: 15px;
    font-size: 0.85rem;
    color: #adb5bd;
}

.notifikasi-time i,
.notifikasi-type i {
    margin-right: 5px;
}

.notifikasi-actions {
    display: flex;
    gap: 5px;
    margin-left: 10px;
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
    .notifikasi-item {
        flex-direction: column;
    }
    
    .notifikasi-icon {
        margin-bottom: 10px;
    }
    
    .notifikasi-actions {
        position: absolute;
        top: 15px;
        right: 15px;
    }
    
    .card-body {
        padding: 15px;
    }
}

@media (max-width: 576px) {
    .dashboard-content {
        padding: 20px 0;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .notifikasi-meta {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php
// Free result sets
if ($notifikasi_result) mysqli_free_result($notifikasi_result);
if ($count_result) mysqli_free_result($count_result);
if ($unread_result) mysqli_free_result($unread_result);

// Include footer
include '../includes/footer.php';
?>