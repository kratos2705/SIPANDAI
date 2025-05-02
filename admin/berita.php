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

// Process Actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($action === 'publish' && $berita_id > 0) {
        // Publish news
        $update_query = "UPDATE berita SET status = 'published', tanggal_publikasi = NOW() WHERE berita_id = ?";
        $stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $berita_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Berita berhasil dipublikasikan!";
        } else {
            $_SESSION['error_message'] = "Gagal mempublikasikan berita: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    } 
    elseif ($action === 'unpublish' && $berita_id > 0) {
        // Unpublish (archive) news
        $update_query = "UPDATE berita SET status = 'archived' WHERE berita_id = ?";
        $stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $berita_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Berita berhasil diarsipkan!";
        } else {
            $_SESSION['error_message'] = "Gagal mengarsipkan berita: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
    elseif ($action === 'delete' && $berita_id > 0) {
        // Delete news
        // First check if there are related attachments to delete
        $attachment_query = "SELECT path_file FROM lampiran_berita WHERE berita_id = ?";
        $stmt = mysqli_prepare($koneksi, $attachment_query);
        mysqli_stmt_bind_param($stmt, "i", $berita_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Delete the file if it exists
            $file_path = '../' . $row['path_file'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete the news entry
        $delete_query = "DELETE FROM berita WHERE berita_id = ?";
        $stmt = mysqli_prepare($koneksi, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $berita_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Berita berhasil dihapus!";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus berita: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
    
    // Redirect to prevent resubmission
    redirect('berita.php');
}

// Handle search and filtering
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($koneksi, $_GET['category']) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, $_GET['status']) : '';

// Build the query
$query = "SELECT b.*, u.nama as author_name 
          FROM berita b 
          LEFT JOIN users u ON b.created_by = u.user_id
          WHERE 1=1";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (b.judul LIKE '%$search%' OR b.konten LIKE '%$search%' OR b.tag LIKE '%$search%')";
}

if (!empty($category)) {
    $query .= " AND b.kategori = '$category'";
}

if (!empty($status)) {
    $query .= " AND b.status = '$status'";
}

// Get unique categories for filter
$categories_query = "SELECT DISTINCT kategori FROM berita WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori";
$categories_result = mysqli_query($koneksi, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['kategori'];
}

// Count total number of berita for pagination
$count_query = str_replace("b.*, u.nama as author_name", "COUNT(*) as total", $query);
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$records_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// Finalize the query with sorting and pagination
$query .= " ORDER BY b.created_at DESC LIMIT $offset, $records_per_page";
$result = mysqli_query($koneksi, $query);

// Stats
$total_berita_query = "SELECT COUNT(*) as total FROM berita";
$total_berita_result = mysqli_query($koneksi, $total_berita_query);
$total_berita = mysqli_fetch_assoc($total_berita_result)['total'];

$published_berita_query = "SELECT COUNT(*) as total FROM berita WHERE status = 'published'";
$published_berita_result = mysqli_query($koneksi, $published_berita_query);
$published_berita = mysqli_fetch_assoc($published_berita_result)['total'];

$draft_berita_query = "SELECT COUNT(*) as total FROM berita WHERE status = 'draft'";
$draft_berita_result = mysqli_query($koneksi, $draft_berita_query);
$draft_berita = mysqli_fetch_assoc($draft_berita_result)['total'];

$archived_berita_query = "SELECT COUNT(*) as total FROM berita WHERE status = 'archived'";
$archived_berita_result = mysqli_query($koneksi, $archived_berita_query);
$archived_berita = mysqli_fetch_assoc($archived_berita_result)['total'];

// Prepare variables for page
$page_title = "Kelola Berita & Pengumuman";
$current_page = "berita";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Kelola Berita & Pengumuman</h2>
        <p>Manajemen berita dan pengumuman desa</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success_message']; ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error_message']; ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats-card primary">
                <div class="stats-icon">📰</div>
                <div class="stats-info">
                    <h3>Total Berita</h3>
                    <p class="stats-value"><?php echo $total_berita; ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">✅</div>
                <div class="stats-info">
                    <h3>Dipublikasikan</h3>
                    <p class="stats-value"><?php echo $published_berita; ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">📝</div>
                <div class="stats-info">
                    <h3>Draft</h3>
                    <p class="stats-value"><?php echo $draft_berita; ?></p>
                </div>
            </div>
            <div class="stats-card info">
                <div class="stats-icon">📦</div>
                <div class="stats-info">
                    <h3>Diarsipkan</h3>
                    <p class="stats-value"><?php echo $archived_berita; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons & Search -->
    <div class="admin-actions">
        <a href="berita_tambah.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Berita Baru
        </a>
        <div class="search-filters">
            <form action="" method="GET" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" id="search" placeholder="Cari judul, konten, tag..." value="<?= htmlspecialchars($search) ?>" class="form-control">
                </div>
                <div class="form-group">
                    <select name="category" id="category" class="form-control">
                        <option value="">- Semua Kategori -</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="status" id="status" class="form-control">
                        <option value="">- Semua Status -</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Dipublikasikan</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Diarsipkan</option>
                    </select>
                </div>
                <button type="submit" class="btn">Filter</button>
                <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                    <a href="berita.php" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- News List -->
    <div class="data-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="15%">Thumbnail</th>
                        <th width="25%">Judul</th>
                        <th width="10%">Kategori</th>
                        <th width="10%">Status</th>
                        <th width="15%">Tanggal</th>
                        <th width="10%">Dilihat</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                            // Determine status class
                            $status_class = "";
                            switch ($row['status']) {
                                case 'draft':
                                    $status_class = "status-pending";
                                    $status_text = "Draft";
                                    break;
                                case 'published':
                                    $status_class = "status-completed";
                                    $status_text = "Dipublikasikan";
                                    break;
                                case 'archived':
                                    $status_class = "status-rejected";
                                    $status_text = "Diarsipkan";
                                    break;
                                default:
                                    $status_class = "status-pending";
                                    $status_text = "Draft";
                            }
                            
                            // Format date
                            $created_date = date('d-m-Y', strtotime($row['created_at']));
                            $published_date = $row['tanggal_publikasi'] 
                                            ? date('d-m-Y', strtotime($row['tanggal_publikasi']))
                                            : "-";
                            
                            // Thumbnail
                            $thumbnail = !empty($row['thumbnail']) && file_exists('../' . $row['thumbnail'])
                                        ? '../' . $row['thumbnail'] 
                                        : '../assets/img/default-news.jpg';
                            ?>
                            <tr>
                                <td><?= $row['berita_id'] ?></td>
                                <td>
                                    <div class="news-thumbnail">
                                        <img src="<?= $thumbnail ?>" alt="<?= htmlspecialchars($row['judul']) ?>">
                                    </div>
                                </td>
                                <td>
                                    <div class="news-title">
                                        <h4><?= htmlspecialchars($row['judul']) ?></h4>
                                        <small>Penulis: <?= htmlspecialchars($row['author_name']) ?></small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['kategori'] ?: '-') ?></td>
                                <td><span class="status <?= $status_class ?>"><?= $status_text ?></span></td>
                                <td>
                                    <div class="date-info">
                                        <div>Dibuat: <?= $created_date ?></div>
                                        <div>Dipublikasi: <?= $published_date ?></div>
                                    </div>
                                </td>
                                <td><i class="fas fa-eye"></i> <?= number_format($row['view_count']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="berita_edit.php?id=<?= $row['berita_id'] ?>" class="btn-sm btn-info" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($row['status'] == 'draft' || $row['status'] == 'archived'): ?>
                                            <a href="berita.php?action=publish&id=<?= $row['berita_id'] ?>" class="btn-sm btn-success" title="Publikasikan" onclick="return confirm('Publikasikan berita ini?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php elseif ($row['status'] == 'published'): ?>
                                            <a href="berita.php?action=unpublish&id=<?= $row['berita_id'] ?>" class="btn-sm btn-warning" title="Arsipkan" onclick="return confirm('Arsipkan berita ini?')">
                                                <i class="fas fa-archive"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="berita.php?action=delete&id=<?= $row['berita_id'] ?>" class="btn-sm btn-danger" title="Hapus" onclick="return confirm('Anda yakin ingin menghapus berita ini? Tindakan ini tidak dapat dibatalkan.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <!-- <a href="../user/detail-berita.php?id=<?= $row['berita_id'] ?>" class="btn-sm" title="Lihat" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a> -->
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada data berita yang ditemukan</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= ($current_page - 1) ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" class="page-link">&laquo; Sebelumnya</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&category=' . urlencode($category) . '&status=' . urlencode($status) . '" class="page-link">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active_class = ($i == $current_page) ? 'active' : '';
                    echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&category=' . urlencode($category) . '&status=' . urlencode($status) . '" class="page-link ' . $active_class . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&category=' . urlencode($category) . '&status=' . urlencode($status) . '" class="page-link">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= ($current_page + 1) ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>" class="page-link">Selanjutnya &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Helpful Tips Section -->
    <div class="info-card">
        <h3>Tips Pengelolaan Berita</h3>
        <div class="tips-grid">
            <div class="tip-item">
                <div class="tip-icon">✏️</div>
                <div class="tip-content">
                    <h4>Draft</h4>
                    <p>Berita dalam status draft hanya dapat dilihat oleh admin dan belum dipublikasikan di website.</p>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">📢</div>
                <div class="tip-content">
                    <h4>Publikasi</h4>
                    <p>Berita yang dipublikasikan akan langsung terlihat di halaman berita website desa.</p>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">🗂️</div>
                <div class="tip-content">
                    <h4>Arsip</h4>
                    <p>Berita yang diarsipkan tidak akan ditampilkan di website tetapi masih tersimpan dalam database.</p>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">🔍</div>
                <div class="tip-content">
                    <h4>SEO</h4>
                    <p>Gunakan judul yang singkat dan deskriptif. Tambahkan tag yang relevan untuk meningkatkan visibilitas.</p>
                </div>
            </div>
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
    margin-bottom: 25px;
}

.stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.stats-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    padding: 15px;
    flex: 1;
    min-width: 200px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stats-icon {
    font-size: 24px;
    margin-right: 15px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.05);
}

.stats-info h3 {
    font-size: 14px;
    margin: 0 0 5px 0;
    color: #555;
    font-weight: 600;
}

.stats-value {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
    color: #333;
}

/* Card colors */
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

.stats-card.info {
    border-left: 4px solid #36b9cc;
}
.stats-card.info .stats-icon {
    color: #36b9cc;
    background-color: rgba(54, 185, 204, 0.1);
}

/* Action Buttons & Search */
.admin-actions {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.search-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-form .form-group {
    margin-bottom: 0;
}

/* Data Card */
.data-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px;
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

/* News List Table */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th, 
.admin-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e3e6f0;
}

.admin-table th {
    background-color: #f8f9fc;
    color: #5a5c69;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.admin-table tr:last-child td {
    border-bottom: none;
}

.admin-table tr:hover td {
    background-color: #f8f9fc;
}

/* News thumbnails in table */
.news-thumbnail {
    width: 100px;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
}

.news-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.news-title h4 {
    margin: 0 0 5px 0;
    font-size: 15px;
    color: #333;
}

.news-title small {
    color: #777;
    font-size: 12px;
}

/* Status badges */
.status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: #f6c23e;
}

.status-processing {
    background-color: rgba(54, 185, 204, 0.1);
    color: #36b9cc;
}

.status-completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: #1cc88a;
}

.status-rejected {
    background-color: rgba(231, 74, 59, 0.1);
    color: #e74a3b;
}

/* Date info */
.date-info {
    font-size: 13px;
    color: #666;
}

/* Action buttons container */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Base style for action buttons */
.btn-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 4px;
    font-size: 16px;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    color: #ffffff;
    padding: 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-sm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
}

.btn-sm i {
    margin: 0; /* Remove any margin to center the icon */
}

/* View button - Blue */
.btn-sm.btn-info, 
.btn-sm.btn-view {
    background-color: #0d6efd; /* Bootstrap blue */
    color: white;
}
.btn-sm.btn-info:hover,
.btn-sm.btn-view:hover {
    background-color: #0b5ed7;
}

/* Edit button - Yellow/Orange */
.btn-sm.btn-warning,
.btn-sm.btn-edit {
    background-color: #ffc107; /* Bootstrap yellow */
    color: white;
}
.btn-sm.btn-warning:hover,
.btn-sm.btn-edit:hover {
    background-color: #e0a800;
}

/* Delete button - Red */
.btn-sm.btn-danger,
.btn-sm.btn-delete {
    background-color: #dc3545; /* Bootstrap red */
    color: white;
}
.btn-sm.btn-danger:hover,
.btn-sm.btn-delete:hover {
    background-color: #bb2d3b;
}

/* Success/Approve button - Green */
.btn-sm.btn-success {
    background-color: #198754; /* Bootstrap green */
    color: white;
}
.btn-sm.btn-success:hover {
    background-color: #157347;
}

/* Make sure icons are rendered properly */
.fas {
    display: inline-block;
    font-style: normal;
    font-variant: normal;
    text-rendering: auto;
    line-height: 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Tooltip effect on hover */
.btn-sm[title] {
    position: relative;
}

.btn-sm[title]:hover:after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 5px;
    font-weight: normal;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 30px;
    flex-wrap: wrap;
    gap: 5px;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    border-radius: 4px;
    background-color: #fff;
    color: #4e73df;
    text-decoration: none;
    border: 1px solid #e3e6f0;
    transition: all 0.2s;
}

.page-link:hover {
    background-color: #eaecf4;
    border-color: #e3e6f0;
}

.page-link.active {
    background-color: #4e73df;
    color: white;
    border-color: #4e73df;
}

.page-ellipsis {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #858796;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.col-span-2 {
    grid-column: span 2;
}

/* Form Controls */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #444;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #d1d3e2;
    border-radius: 4px;
    font-size: 15px;
    color: #6e707e;
    transition: border-color 0.2s;
}

.form-control:focus {
    border-color: #4e73df;
    outline: none;
}

.form-control-file {
    padding: 8px 0;
}

small.form-text {
    display: block;
    margin-top: 5px;
    color: #858796;
    font-size: 12px;
}

.required {
    color: #e74a3b;
}

/* File Upload */
.file-upload {
    margin-top: 10px;
}

.preview-container {
    margin-top: 10px;
    width: 100%;
    height: 150px;
    border: 2px dashed #d1d3e2;
    border-radius: 4px;
    background-color: #f8f9fc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.no-preview {
    color: #858796;
    font-size: 14px;
}

.preview-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* File List */
.file-list {
    margin-top: 10px;
}

.file-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    background-color: #f8f9fc;
    border-radius: 4px;
    margin-bottom: 5px;
}

.file-icon {
    margin-right: 10px;
    font-size: 18px;
}

.file-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 10px;
}

.file-size {
    color: #858796;
    font-size: 12px;
}

/* Radio Group */
.radio-group {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.radio-option {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
}

.radio-option input[type="radio"] {
    margin-top: 3px;
    margin-right: 10px;
}

.radio-label {
    font-weight: 600;
    color: #444;
    margin-right: 8px;
}

.radio-option small {
    color: #858796;
    font-size: 12px;
    display: block;
    margin-top: 3px;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e3e6f0;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.btn i {
    margin-right: 8px;
}

.btn-primary {
    background-color: #4e73df;
    color: white;
}
.btn-primary:hover {
    background-color: #3a5fc8;
}

.btn-success {
    background-color: #1cc88a;
    color: white;
}
.btn-success:hover {
    background-color: #18a878;
}

.btn-warning {
    background-color: #f6c23e;
    color: white;
}
.btn-warning:hover {
    background-color: #e8b72c;
}

.btn-danger {
    background-color: #e74a3b;
    color: white;
}
.btn-danger:hover {
    background-color: #d52a1a;
}

.btn-outline {
    background-color: transparent;
    color: #4e73df;
    border: 1px solid #4e73df;
}
.btn-outline:hover {
    background-color: #4e73df;
    color: white;
}

/* Current Attachments */
.current-attachments {
    max-height: 300px;
    overflow-y: auto;
    margin-top: 10px;
}

.attachment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background-color: #f8f9fc;
    border-radius: 4px;
    margin-bottom: 5px;
}

.attachment-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.attachment-icon {
    margin-right: 10px;
    font-size: 18px;
}

.attachment-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 10px;
}

.attachment-size {
    color: #858796;
    font-size: 12px;
    margin-left: 10px;
}

.attachment-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Checkbox Container */
.checkbox-container {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 13px;
    color: #e74a3b;
}

.checkbox-container input {
    margin-right: 5px;
}

/* Info Card */
.info-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px;
    padding: 20px;
}

.info-card h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #333;
    border-bottom: 1px solid #e3e6f0;
    padding-bottom: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 13px;
    color: #858796;
    margin-bottom: 5px;
}

.info-value {
    font-size: 15px;
    color: #333;
    font-weight: 500;
}

.view-more-action {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}

/* Tips Grid */
.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    background-color: #f8f9fc;
    border-radius: 8px;
    transition: transform 0.2s;
}

.tip-item:hover {
    transform: translateY(-3px);
}

.tip-icon {
    font-size: 24px;
    margin-right: 15px;
}

.tip-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #333;
}

.tip-content p {
    margin: 0;
    font-size: 14px;
    color: #666;
}

/* Alert Messages */
.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    opacity: 1;
    transition: opacity 0.5s;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border-left: 4px solid #1cc88a;
    color: #1cc88a;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border-left: 4px solid #e74a3b;
    color: #e74a3b;
}

.alert-warning {
    background-color: rgba(246, 194, 62, 0.1);
    border-left: 4px solid #f6c23e;
    color: #f6c23e;
}

.alert-info {
    background-color: rgba(54, 185, 204, 0.1);
    border-left: 4px solid #36b9cc;
    color: #36b9cc;
}

/* Input with dropdown */
.input-with-dropdown {
    position: relative;
}

datalist {
    max-height: 200px;
    overflow-y: auto;
}

/* Text Utilities */
.text-center {
    text-align: center;
}

.text-muted {
    color: #858796;
}

/* Table responsive */
.table-responsive {
    overflow-x: auto;
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 20px;
    text-align: center;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 20px;
    color: #d1d3e2;
}

.empty-state h3 {
    font-size: 18px;
    color: #5a5c69;
    margin-bottom: 10px;
}

.empty-state p {
    color: #858796;
    margin-bottom: 20px;
}

/* Filter Container */
.filter-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px;
    padding: 20px;
}

.filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

/* Comment Styles */
.comments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.comment-item {
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    overflow: hidden;
}

.comment-header {
    background-color: #f8f9fc;
    padding: 12px 15px;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.comment-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.comment-author {
    font-size: 15px;
    color: #333;
}

.comment-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.comment-date {
    font-size: 13px;
    color: #858796;
}

.comment-berita {
    font-size: 13px;
    color: #666;
}

.comment-berita a {
    color: #4e73df;
    text-decoration: none;
    font-weight: 500;
}

.comment-berita a:hover {
    text-decoration: underline;
}

.comment-content {
    padding: 15px;
    font-size: 14px;
    color: #333;
    background-color: white;
}

.comment-actions {
    padding: 10px 15px;
    background-color: #f8f9fc;
    border-top: 1px solid #e3e6f0;
    display: flex;
    gap: 10px;
}

/* Batch Actions */
.batch-actions {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px;
    padding: 20px;
    border-left: 4px solid #4e73df;
}

.batch-actions h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #333;
}

.batch-actions p {
    margin: 0 0 15px 0;
    color: #666;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .col-span-2 {
        grid-column: auto;
    }
    
    .stats-card {
        min-width: 100%;
    }
    
    .admin-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-filters {
        width: 100%;
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
    }
    
    .admin-table th, 
    .admin-table td {
        padding: 8px 10px;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
    
    // Make entire row clickable to edit news
    const dataRows = document.querySelectorAll('.data-row');
    dataRows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Only redirect if the click wasn't on a button
            if (!e.target.closest('.btn-sm')) {
                window.location.href = this.dataset.url;
            }
        });
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>