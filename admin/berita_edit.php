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

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID berita tidak valid.";
    redirect('berita.php');
}

$berita_id = intval($_GET['id']);

// Get berita data
$query = "SELECT * FROM berita WHERE berita_id = ?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $berita_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error_message'] = "Berita tidak ditemukan.";
    redirect('berita.php');
}

$berita = mysqli_fetch_assoc($result);

// Get attachments
$lampiran_query = "SELECT * FROM lampiran_berita WHERE berita_id = ? ORDER BY tanggal_upload DESC";
$lampiran_stmt = mysqli_prepare($koneksi, $lampiran_query);
mysqli_stmt_bind_param($lampiran_stmt, "i", $berita_id);
mysqli_stmt_execute($lampiran_stmt);
$lampiran_result = mysqli_stmt_get_result($lampiran_stmt);
$lampiran = [];

while ($row = mysqli_fetch_assoc($lampiran_result)) {
    $lampiran[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $judul = mysqli_real_escape_string($koneksi, $_POST['judul']);
    $konten = $_POST['konten']; // Rich text content
    $kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $tag = mysqli_real_escape_string($koneksi, $_POST['tag']);
    $status = mysqli_real_escape_string($koneksi, $_POST['status']);
    
    // Handle thumbnail upload
    $thumbnail_path = $berita['thumbnail']; // Keep existing thumbnail by default
    
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $upload_dir = '../uploads/berita/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['thumbnail']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is valid
        $valid_extensions = array("jpg", "jpeg", "png", "gif");
        if (in_array($file_type, $valid_extensions)) {
            // Check file size (max 5MB)
            if ($_FILES['thumbnail']['size'] <= 5000000) {
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $target_file)) {
                    // Delete old thumbnail if exists
                    if (!empty($berita['thumbnail']) && file_exists('../' . $berita['thumbnail'])) {
                        unlink('../' . $berita['thumbnail']);
                    }
                    
                    $thumbnail_path = 'uploads/berita/' . $file_name;
                } else {
                    $_SESSION['error_message'] = "Gagal mengunggah gambar thumbnail.";
                }
            } else {
                $_SESSION['error_message'] = "Ukuran file terlalu besar. Maksimal 5MB.";
            }
        } else {
            $_SESSION['error_message'] = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.";
        }
    }
    
    // Delete attachments if requested
    if (isset($_POST['delete_lampiran']) && is_array($_POST['delete_lampiran'])) {
        foreach ($_POST['delete_lampiran'] as $lampiran_id) {
            // Get file path
            $file_query = "SELECT path_file FROM lampiran_berita WHERE lampiran_id = ?";
            $file_stmt = mysqli_prepare($koneksi, $file_query);
            mysqli_stmt_bind_param($file_stmt, "i", $lampiran_id);
            mysqli_stmt_execute($file_stmt);
            $file_result = mysqli_stmt_get_result($file_stmt);
            
            if ($file_row = mysqli_fetch_assoc($file_result)) {
                // Delete physical file if exists
                if (file_exists('../' . $file_row['path_file'])) {
                    unlink('../' . $file_row['path_file']);
                }
                
                // Delete database record
                $delete_query = "DELETE FROM lampiran_berita WHERE lampiran_id = ?";
                $delete_stmt = mysqli_prepare($koneksi, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "i", $lampiran_id);
                mysqli_stmt_execute($delete_stmt);
                mysqli_stmt_close($delete_stmt);
            }
            mysqli_stmt_close($file_stmt);
        }
    }
    
    // Handle status change and publication date
    $update_publication = "";
    if ($berita['status'] != 'published' && $status == 'published') {
        // Change from draft/archived to published - set publication date to now
        $update_publication = ", tanggal_publikasi = NOW()";
    } elseif ($berita['status'] == 'published' && $status != 'published') {
        // Keep the existing publication date when unpublishing
        $update_publication = "";
    }
    
    // If no error during upload, update database
    if (!isset($_SESSION['error_message'])) {
        // Update berita
        $query = "UPDATE berita SET 
                  judul = ?, 
                  konten = ?, 
                  thumbnail = ?, 
                  kategori = ?, 
                  tag = ?, 
                  status = ?,
                  updated_at = NOW()
                  $update_publication
                  WHERE berita_id = ?";
        
        $stmt = mysqli_prepare($koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $judul, $konten, $thumbnail_path, $kategori, $tag, $status, $berita_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Handle new attachments
                if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'][0] != 4) { // 4 = no file uploaded
                    $upload_dir = '../uploads/lampiran/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_count = count($_FILES['lampiran']['name']);
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['lampiran']['error'][$i] == 0) {
                            $file_name = time() . '_' . basename($_FILES['lampiran']['name'][$i]);
                            $target_file = $upload_dir . $file_name;
                            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                            $file_size = $_FILES['lampiran']['size'][$i]; // in bytes
                            
                            // Move file
                            if (move_uploaded_file($_FILES['lampiran']['tmp_name'][$i], $target_file)) {
                                $lampiran_path = 'uploads/lampiran/' . $file_name;
                                $lampiran_judul = $_FILES['lampiran']['name'][$i];
                                
                                // Insert attachment info
                                $lampiran_query = "INSERT INTO lampiran_berita (berita_id, judul, jenis_file, path_file, ukuran, tanggal_upload) 
                                                VALUES (?, ?, ?, ?, ?, NOW())";
                                
                                $lampiran_stmt = mysqli_prepare($koneksi, $lampiran_query);
                                mysqli_stmt_bind_param($lampiran_stmt, "isssi", $berita_id, $lampiran_judul, $file_type, $lampiran_path, $file_size);
                                mysqli_stmt_execute($lampiran_stmt);
                                mysqli_stmt_close($lampiran_stmt);
                            }
                        }
                    }
                }
                
                $_SESSION['success_message'] = "Berita berhasil diperbarui!";
                redirect('berita.php');
            } else {
                $_SESSION['error_message'] = "Gagal memperbarui berita: " . mysqli_error($koneksi);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error: " . mysqli_error($koneksi);
        }
    }
}

// Get all categories from database for dropdown
$categories_query = "SELECT DISTINCT kategori FROM berita WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori";
$categories_result = mysqli_query($koneksi, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['kategori'];
}

// Prepare variables for page
$page_title = "Edit Berita";
$current_page = "berita";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Edit Berita</h2>
        <p>Perbarui informasi berita</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error_message']; ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="data-card">
        <form action="" method="POST" enctype="multipart/form-data" id="newsForm">
            <div class="form-grid">
                <div class="form-group col-span-2">
                    <label for="judul">Judul Berita <span class="required">*</span></label>
                    <input type="text" name="judul" id="judul" class="form-control" required value="<?= htmlspecialchars($berita['judul']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="kategori">Kategori</label>
                    <div class="input-with-dropdown">
                        <input type="text" name="kategori" id="kategori" class="form-control" list="kategori-list" value="<?= htmlspecialchars($berita['kategori']) ?>">
                        <datalist id="kategori-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <small class="form-text">Masukkan kategori baru atau pilih dari daftar yang ada</small>
                </div>
                
                <div class="form-group">
                    <label for="tag">Tag</label>
                    <input type="text" name="tag" id="tag" class="form-control" value="<?= htmlspecialchars($berita['tag']) ?>">
                    <small class="form-text">Pisahkan dengan koma (cth: ekonomi, pembangunan, pendidikan)</small>
                </div>
                
                <div class="form-group col-span-2">
                    <label for="konten">Konten Berita <span class="required">*</span></label>
                    <textarea name="konten" id="konten" class="form-control editor" rows="10" required><?= htmlspecialchars($berita['konten']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="thumbnail">Thumbnail</label>
                    <div class="file-upload">
                        <input type="file" name="thumbnail" id="thumbnail" class="form-control-file" accept="image/*">
                        <div class="preview-container" id="thumbnailPreview">
                            <?php if (!empty($berita['thumbnail']) && file_exists('../' . $berita['thumbnail'])): ?>
                                <img src="../<?= $berita['thumbnail'] ?>" class="preview-image" alt="Thumbnail">
                            <?php else: ?>
                                <div class="no-preview">Tidak ada thumbnail</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="form-text">Format: JPG, PNG, GIF. Ukuran maks: 5MB. Dimensi ideal: 1200x630px</small>
                </div>
                
                <div class="form-group">
                    <label>Lampiran Saat Ini</label>
                    <div class="current-attachments">
                        <?php if (count($lampiran) > 0): ?>
                            <?php foreach ($lampiran as $file): ?>
                                <div class="attachment-item">
                                    <div class="attachment-info">
                                        <?php
                                        // Get file icon based on extension
                                        $extension = pathinfo($file['path_file'], PATHINFO_EXTENSION);
                                        $icon = '📄';
                                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) $icon = '🖼️';
                                        else if (in_array($extension, ['pdf'])) $icon = '📑';
                                        else if (in_array($extension, ['doc', 'docx'])) $icon = '📝';
                                        else if (in_array($extension, ['xls', 'xlsx'])) $icon = '📊';
                                        else if (in_array($extension, ['ppt', 'pptx'])) $icon = '📽️';
                                        else if (in_array($extension, ['zip', 'rar'])) $icon = '🗜️';
                                        
                                        // Format file size
                                        $fileSize = $file['ukuran'];
                                        $sizeUnit = 'bytes';
                                        if ($fileSize > 1024) {
                                            $fileSize = round($fileSize / 1024, 2);
                                            $sizeUnit = 'KB';
                                        }
                                        if ($fileSize > 1024) {
                                            $fileSize = round($fileSize / 1024, 2);
                                            $sizeUnit = 'MB';
                                        }
                                        ?>
                                        <span class="attachment-icon"><?= $icon ?></span>
                                        <span class="attachment-name"><?= htmlspecialchars($file['judul']) ?></span>
                                        <span class="attachment-size"><?= $fileSize ?> <?= $sizeUnit ?></span>
                                    </div>
                                    <div class="attachment-actions">
                                        <a href="../<?= $file['path_file'] ?>" target="_blank" class="btn-sm btn-info">Lihat</a>
                                        <label class="checkbox-container">
                                            <input type="checkbox" name="delete_lampiran[]" value="<?= $file['lampiran_id'] ?>">
                                            <span class="custom-checkbox"></span>
                                            Hapus
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Tidak ada lampiran</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="lampiran">Tambah Lampiran Baru (Opsional)</label>
                    <div class="file-upload">
                        <input type="file" name="lampiran[]" id="lampiran" class="form-control-file" multiple>
                        <div id="fileList" class="file-list"></div>
                    </div>
                    <small class="form-text">Anda dapat mengunggah beberapa file sekaligus. Ukuran maks total: 10MB</small>
                </div>
                
                <div class="form-group col-span-2">
                    <label for="status">Status Publikasi <span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="status" value="draft" <?= $berita['status'] == 'draft' ? 'checked' : '' ?>>
                            <span class="radio-label">Draft</span>
                            <small>Simpan sebagai draft (tidak dipublikasikan)</small>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="status" value="published" <?= $berita['status'] == 'published' ? 'checked' : '' ?>>
                            <span class="radio-label">Publikasikan</span>
                            <small>Dipublikasikan di website</small>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="status" value="archived" <?= $berita['status'] == 'archived' ? 'checked' : '' ?>>
                            <span class="radio-label">Arsipkan</span>
                            <small>Arsipkan berita (tidak ditampilkan di website)</small>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Perbarui Berita</button>
                <a href="berita.php" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>

    <!-- Info Card -->
    <div class="info-card">
        <h3>Informasi Berita</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Dibuat pada:</div>
                <div class="info-value"><?= date('d-m-Y H:i', strtotime($berita['created_at'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Terakhir diperbarui:</div>
                <div class="info-value"><?= date('d-m-Y H:i', strtotime($berita['updated_at'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Tanggal publikasi:</div>
                <div class="info-value">
                    <?= $berita['tanggal_publikasi'] ? date('d-m-Y H:i', strtotime($berita['tanggal_publikasi'])) : 'Belum dipublikasikan' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Jumlah dilihat:</div>
                <div class="info-value"><?= number_format($berita['view_count']) ?> kali</div>
            </div>
            <?php
            // Get author name
            $author_query = "SELECT nama FROM users WHERE user_id = ?";
            $author_stmt = mysqli_prepare($koneksi, $author_query);
            mysqli_stmt_bind_param($author_stmt, "i", $berita['created_by']);
            mysqli_stmt_execute($author_stmt);
            $author_result = mysqli_stmt_get_result($author_stmt);
            $author_name = mysqli_fetch_assoc($author_result)['nama'] ?? 'Unknown';
            ?>
            <div class="info-item">
                <div class="info-label">Penulis:</div>
                <div class="info-value"><?= htmlspecialchars($author_name) ?></div>
            </div>
            
            <?php
            // Get comment count
            $comment_query = "SELECT COUNT(*) as total FROM komentar_berita WHERE berita_id = ?";
            $comment_stmt = mysqli_prepare($koneksi, $comment_query);
            mysqli_stmt_bind_param($comment_stmt, "i", $berita_id);
            mysqli_stmt_execute($comment_stmt);
            $comment_result = mysqli_stmt_get_result($comment_stmt);
            $comment_count = mysqli_fetch_assoc($comment_result)['total'] ?? 0;
            ?>
            <div class="info-item">
                <div class="info-label">Jumlah komentar:</div>
                <div class="info-value"><?= number_format($comment_count) ?></div>
            </div>
        </div>
        
        <?php if ($comment_count > 0): ?>
            <div class="view-more-action">
                <a href="komentar.php?berita_id=<?= $berita_id ?>" class="btn btn-sm">Lihat Komentar</a>
            </div>
        <?php endif; ?>
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

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* Small buttons */
.btn-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    background-color: #eaecf4;
    color: #6e707e;
    border: none;
}

.btn-sm:hover {
    background-color: #dddfeb;
}

.btn-sm i {
    margin-right: 4px;
}

.btn-sm.btn-info {
    background-color: rgba(54, 185, 204, 0.1);
    color: #36b9cc;
}
.btn-sm.btn-info:hover {
    background-color: rgba(54, 185, 204, 0.2);
}

.btn-sm.btn-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: #1cc88a;
}
.btn-sm.btn-success:hover {
    background-color: rgba(28, 200, 138, 0.2);
}

.btn-sm.btn-warning {
    background-color: rgba(246, 194, 62, 0.1);
    color: #f6c23e;
}
.btn-sm.btn-warning:hover {
    background-color: rgba(246, 194, 62, 0.2);
}

.btn-sm.btn-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: #e74a3b;
}
.btn-sm.btn-danger:hover {
    background-color: rgba(231, 74, 59, 0.2);
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
    // Initialize WYSIWYG editor
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '.editor',
            height: 400,
            menubar: true,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    }
    
    // Thumbnail preview
    const thumbnailInput = document.getElementById('thumbnail');
    const thumbnailPreview = document.getElementById('thumbnailPreview');
    
    thumbnailInput.addEventListener('change', function() {
        thumbnailPreview.innerHTML = '';
        
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-image';
                thumbnailPreview.appendChild(img);
            };
            
            reader.readAsDataURL(file);
        } else {
            <?php if (!empty($berita['thumbnail']) && file_exists('../' . $berita['thumbnail'])): ?>
                thumbnailPreview.innerHTML = '<img src="../<?= $berita['thumbnail'] ?>" class="preview-image" alt="Thumbnail">';
            <?php else: ?>
                thumbnailPreview.innerHTML = '<div class="no-preview">Tidak ada thumbnail</div>';
            <?php endif; ?>
        }
    });
    
    // File attachment list
    const lampiranInput = document.getElementById('lampiran');
    const fileList = document.getElementById('fileList');
    
    lampiranInput.addEventListener('change', function() {
        fileList.innerHTML = '';
        
        if (this.files && this.files.length > 0) {
            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                // Get file icon based on extension
                const extension = file.name.split('.').pop().toLowerCase();
                let icon = '📄';
                if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) icon = '🖼️';
                else if (['pdf'].includes(extension)) icon = '📑';
                else if (['doc', 'docx'].includes(extension)) icon = '📝';
                else if (['xls', 'xlsx'].includes(extension)) icon = '📊';
                else if (['ppt', 'pptx'].includes(extension)) icon = '📽️';
                else if (['zip', 'rar'].includes(extension)) icon = '🗜️';
                
                // Format file size
                let fileSize = file.size;
                let sizeUnit = 'bytes';
                if (fileSize > 1024) {
                    fileSize = (fileSize / 1024).toFixed(2);
                    sizeUnit = 'KB';
                }
                if (fileSize > 1024) {
                    fileSize = (fileSize / 1024).toFixed(2);
                    sizeUnit = 'MB';
                }
                
                fileItem.innerHTML = `
                    <span class="file-icon">${icon}</span>
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${fileSize} ${sizeUnit}</span>
                `;
                
                fileList.appendChild(fileItem);
            }
        }
    });
    
    // Form validation
    const newsForm = document.getElementById('newsForm');
    
    newsForm.addEventListener('submit', function(e) {
        let errors = [];
        const judul = document.getElementById('judul').value.trim();
        
        // Check title length
        if (judul.length < 5) {
            errors.push('Judul berita terlalu pendek. Minimal 5 karakter.');
        }
        
        // Check if editor content is empty (if TinyMCE is available)
        if (typeof tinymce !== 'undefined') {
            const kontenValue = tinymce.get('konten').getContent().trim();
            if (kontenValue === '') {
                errors.push('Konten berita tidak boleh kosong.');
            }
        }
        
        // Display errors if any
        if (errors.length > 0) {
            e.preventDefault();
            const errorHtml = errors.map(err => `<li>${err}</li>`).join('');
            const alertElement = document.createElement('div');
            alertElement.className = 'alert alert-danger';
            alertElement.innerHTML = `<ul>${errorHtml}</ul>`;
            
            // Insert alert before form
            newsForm.parentNode.insertBefore(alertElement, newsForm);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertElement.style.opacity = '0';
                setTimeout(() => {
                    alertElement.remove();
                }, 500);
            }, 5000);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>