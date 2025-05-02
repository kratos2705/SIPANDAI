<?php
// Include necessary files
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/koneksi.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('berita.php');
}

$berita_id = intval($_GET['id']);

// Get berita data
$query = "SELECT b.*, u.nama as author_name 
          FROM berita b 
          LEFT JOIN users u ON b.created_by = u.user_id
          WHERE b.berita_id = ?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $berita_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Check if berita exists and is published (allow viewing unpublished for admins)
if (mysqli_num_rows($result) == 0) {
    redirect('berita.php');
}

$berita = mysqli_fetch_assoc($result);

// Only admins can see unpublished content
$is_admin = isLoggedIn() && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'kepala_desa');
if ($berita['status'] != 'published' && !$is_admin) {
    redirect('berita.php');
}

// Update view count
$update_query = "UPDATE berita SET view_count = view_count + 1 WHERE berita_id = ?";
$update_stmt = mysqli_prepare($koneksi, $update_query);
mysqli_stmt_bind_param($update_stmt, "i", $berita_id);
mysqli_stmt_execute($update_stmt);

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

// Get related news (same category, excluding current)
$related_query = "SELECT berita_id, judul, tanggal_publikasi, thumbnail 
                 FROM berita 
                 WHERE kategori = ? AND berita_id != ? AND status = 'published'
                 ORDER BY tanggal_publikasi DESC
                 LIMIT 3";
$related_stmt = mysqli_prepare($koneksi, $related_query);
mysqli_stmt_bind_param($related_stmt, "si", $berita['kategori'], $berita_id);
mysqli_stmt_execute($related_stmt);
$related_result = mysqli_stmt_get_result($related_stmt);
$related_berita = [];

while ($row = mysqli_fetch_assoc($related_result)) {
    $related_berita[] = $row;
}

// Get comments
$komentar_query = "SELECT k.*, u.nama as user_name 
                  FROM komentar_berita k
                  LEFT JOIN users u ON k.user_id = u.user_id
                  WHERE k.berita_id = ? AND k.status = 'approved'
                  ORDER BY k.tanggal_komentar DESC";
$komentar_stmt = mysqli_prepare($koneksi, $komentar_query);
mysqli_stmt_bind_param($komentar_stmt, "i", $berita_id);
mysqli_stmt_execute($komentar_stmt);
$komentar_result = mysqli_stmt_get_result($komentar_stmt);
$komentar = [];

while ($row = mysqli_fetch_assoc($komentar_result)) {
    $komentar[] = $row;
}

// Process comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_komentar'])) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        $_SESSION['error_message'] = "Anda harus login untuk menambahkan komentar.";
        redirect('login.php?redirect=berita_detail.php?id=' . $berita_id);
    }
    
    $user_id = $_SESSION['user_id'];
    $komentar_text = mysqli_real_escape_string($koneksi, $_POST['komentar']);
    
    // Default status (auto-approve for admins)
    $status = ($is_admin) ? 'approved' : 'pending';
    
    $insert_query = "INSERT INTO komentar_berita (berita_id, user_id, komentar, tanggal_komentar, status) 
                    VALUES (?, ?, ?, NOW(), ?)";
    $insert_stmt = mysqli_prepare($koneksi, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "iiss", $berita_id, $user_id, $komentar_text, $status);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        if ($status == 'approved') {
            $_SESSION['success_message'] = "Komentar berhasil ditambahkan!";
        } else {
            $_SESSION['success_message'] = "Komentar berhasil dikirim dan menunggu persetujuan admin.";
        }
    } else {
        $_SESSION['error_message'] = "Gagal menambahkan komentar: " . mysqli_error($koneksi);
    }
    
    // Redirect to prevent resubmission
    redirect('berita_detail.php?id=' . $berita_id);
}

// Format date
$publikasi_date = date('d F Y', strtotime($berita['tanggal_publikasi']));

// Prepare for page
$page_title = $berita['judul'] . " - Berita Desa";
$current_page = "berita";

// Include header and navbar
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="main-content">
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Beranda</a>
            <span class="separator">/</span>
            <a href="berita.php">Berita</a>
            <span class="separator">/</span>
            <span class="active"><?= htmlspecialchars($berita['judul']) ?></span>
        </div>
        
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
        
        <!-- Admin notice if viewing unpublished content -->
        <?php if ($berita['status'] != 'published' && $is_admin): ?>
            <div class="alert alert-warning">
                <strong>Peringatan Admin:</strong> Anda sedang melihat berita yang belum dipublikasikan (Status: <?= ucfirst($berita['status']) ?>). Berita ini tidak terlihat oleh pengunjung umum.
            </div>
        <?php endif; ?>
        
        <div class="content-wrapper">
            <main class="main-column">
                <article class="berita-detail">
                    <header class="berita-header">
                        <h1 class="berita-title"><?= htmlspecialchars($berita['judul']) ?></h1>
                        <div class="berita-meta">
                            <span class="author">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($berita['author_name']) ?>
                            </span>
                            <span class="date">
                                <i class="fas fa-calendar-alt"></i> <?= $publikasi_date ?>
                            </span>
                            <span class="category">
                                <i class="fas fa-folder"></i> <?= htmlspecialchars($berita['kategori']) ?>
                            </span>
                            <span class="views">
                                <i class="fas fa-eye"></i> <?= number_format($berita['view_count']) ?> kali dilihat
                            </span>
                        </div>
                        
                        <?php if (!empty($berita['tag'])): ?>
                            <div class="berita-tags">
                                <?php 
                                $tags = explode(',', $berita['tag']);
                                foreach ($tags as $tag): 
                                    $tag = trim($tag);
                                    if (!empty($tag)):
                                ?>
                                    <a href="berita.php?tag=<?= urlencode($tag) ?>" class="tag">
                                        #<?= htmlspecialchars($tag) ?>
                                    </a>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                    </header>
                    
                    <?php if (!empty($berita['thumbnail']) && file_exists($berita['thumbnail'])): ?>
                        <div class="berita-thumbnail">
                            <img src="<?= $berita['thumbnail'] ?>" alt="<?= htmlspecialchars($berita['judul']) ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="berita-content">
                        <?= $berita['konten'] ?>
                    </div>
                    
                    <?php if (count($lampiran) > 0): ?>
                        <div class="berita-attachments">
                            <h3>Lampiran</h3>
                            <ul class="attachment-list">
                                <?php foreach ($lampiran as $file): ?>
                                    <li class="attachment-item">
                                        <?php
                                        $extension = pathinfo($file['path_file'], PATHINFO_EXTENSION);
                                        $icon = '📄';
                                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) $icon = '🖼️';
                                        else if (in_array($extension, ['pdf'])) $icon = '📑';
                                        else if (in_array($extension, ['doc', 'docx'])) $icon = '📝';
                                        else if (in_array($extension, ['xls', 'xlsx'])) $icon = '📊';
                                        else if (in_array($extension, ['ppt', 'pptx'])) $icon = '📽️';
                                        else if (in_array($extension, ['zip', 'rar'])) $icon = '🗜️';
                                        ?>
                                        <a href="<?= $file['path_file'] ?>" target="_blank" class="attachment-link">
                                            <span class="attachment-icon"><?= $icon ?></span>
                                            <span class="attachment-name"><?= htmlspecialchars($file['judul']) ?></span>
                                            <span class="attachment-action">Download</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="social-share">
                        <h4>Bagikan Berita</h4>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(getCurrentUrl()) ?>" target="_blank" class="share-button facebook">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?= urlencode(getCurrentUrl()) ?>&text=<?= urlencode($berita['judul']) ?>" target="_blank" class="share-button twitter">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <a href="https://api.whatsapp.com/send?text=<?= urlencode($berita['judul'] . ' - ' . getCurrentUrl()) ?>" target="_blank" class="share-button whatsapp">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        </div>
                    </div>
                </article>
                
                <!-- Comments Section -->
                <section class="comments-section">
                    <h3>Komentar (<?= count($komentar) ?>)</h3>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="comment-form">
                            <form action="" method="POST">
                                <div class="form-group">
                                    <label for="komentar">Tulis Komentar</label>
                                    <textarea name="komentar" id="komentar" rows="4" class="form-control" required></textarea>
                                </div>
                                <button type="submit" name="submit_komentar" class="btn btn-primary">Kirim Komentar</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p>Silakan <a href="login.php?redirect=berita_detail.php?id=<?= $berita_id ?>">login</a> untuk menambahkan komentar.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="comments-list">
                        <?php if (count($komentar) > 0): ?>
                            <?php foreach ($komentar as $k): ?>
                                <div class="comment-item">
                                    <div class="comment-avatar">
                                        <div class="avatar-placeholder"><?= substr($k['user_name'], 0, 1) ?></div>
                                    </div>
                                    <div class="comment-content">
                                        <div class="comment-header">
                                            <div class="comment-author"><?= htmlspecialchars($k['user_name']) ?></div>
                                            <div class="comment-date"><?= date('d M Y, H:i', strtotime($k['tanggal_komentar'])) ?></div>
                                        </div>
                                        <div class="comment-text">
                                            <?= nl2br(htmlspecialchars($k['komentar'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-comments">
                                <p>Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
            
            <aside class="sidebar">
                <!-- Related News -->
                <?php if (count($related_berita) > 0): ?>
                    <div class="sidebar-widget">
                        <h3 class="widget-title">Berita Terkait</h3>
                        <div class="related-news">
                            <?php foreach ($related_berita as $rb): ?>
                                <div class="related-item">
                                    <?php 
                                    $thumbnail = !empty($rb['thumbnail']) && file_exists($rb['thumbnail']) 
                                                ? $rb['thumbnail'] 
                                                : 'assets/img/default-news.jpg';
                                    ?>
                                    <a href="berita_detail.php?id=<?= $rb['berita_id'] ?>" class="related-link">
                                        <div class="related-thumbnail">
                                            <img src="<?= $thumbnail ?>" alt="<?= htmlspecialchars($rb['judul']) ?>">
                                        </div>
                                        <div class="related-content">
                                            <h4 class="related-title"><?= htmlspecialchars($rb['judul']) ?></h4>
                                            <span class="related-date"><?= date('d M Y', strtotime($rb['tanggal_publikasi'])) ?></span>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Latest News -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">Berita Terbaru</h3>
                    <?php
                    $latest_query = "SELECT berita_id, judul, tanggal_publikasi, thumbnail 
                                    FROM berita 
                                    WHERE status = 'published' AND berita_id != ?
                                    ORDER BY tanggal_publikasi DESC
                                    LIMIT 5";
                    $latest_stmt = mysqli_prepare($koneksi, $latest_query);
                    mysqli_stmt_bind_param($latest_stmt, "i", $berita_id);
                    mysqli_stmt_execute($latest_stmt);
                    $latest_result = mysqli_stmt_get_result($latest_stmt);
                    ?>
                    
                    <div class="latest-news-list">
                        <?php while ($latest = mysqli_fetch_assoc($latest_result)): ?>
                            <?php 
                            $thumbnail = !empty($latest['thumbnail']) && file_exists($latest['thumbnail']) 
                                        ? $latest['thumbnail'] 
                                        : 'assets/img/default-news.jpg';
                            ?>
                            <div class="latest-news-item">
                                <a href="berita_detail.php?id=<?= $latest['berita_id'] ?>" class="latest-news-link">
                                    <div class="latest-news-thumbnail">
                                        <img src="<?= $thumbnail ?>" alt="<?= htmlspecialchars($latest['judul']) ?>">
                                    </div>
                                    <div class="latest-news-content">
                                        <h4 class="latest-news-title"><?= htmlspecialchars($latest['judul']) ?></h4>
                                        <span class="latest-news-date"><?= date('d M Y', strtotime($latest['tanggal_publikasi'])) ?></span>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <!-- Categories -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">Kategori</h3>
                    <?php
                    $kategori_query = "SELECT kategori, COUNT(*) as total 
                                      FROM berita 
                                      WHERE status = 'published' AND kategori IS NOT NULL AND kategori != ''
                                      GROUP BY kategori 
                                      ORDER BY total DESC";
                    $kategori_result = mysqli_query($koneksi, $kategori_query);
                    ?>
                    
                    <ul class="category-list">
                        <?php while ($kat = mysqli_fetch_assoc($kategori_result)): ?>
                            <li class="category-item">
                                <a href="berita.php?category=<?= urlencode($kat['kategori']) ?>" class="category-link">
                                    <?= htmlspecialchars($kat['kategori']) ?>
                                    <span class="category-count">(<?= $kat['total'] ?>)</span>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</div>

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
});
</script>

<?php
// Include footer
include 'includes/footer.php';

/**
 * Helper function to get current URL
 */
function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . $host . $uri;
}
?>