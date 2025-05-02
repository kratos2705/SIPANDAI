<?php
// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "Berita dan Pengumuman";

// Get current page for pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$itemsPerPage = 4;
$offset = ($page - 1) * $itemsPerPage;

// Get category filter if any
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Build query to get news
$query_berita = "SELECT 
                    b.berita_id, 
                    b.judul, 
                    LEFT(b.konten, 150) as konten_singkat, 
                    b.thumbnail, 
                    b.kategori, 
                    DATE_FORMAT(b.tanggal_publikasi, '%d %M %Y') as tanggal_format,
                    u.nama as penulis
                FROM 
                    berita b
                LEFT JOIN 
                    users u ON b.created_by = u.user_id
                WHERE 
                    b.status = 'published'";

// Add category filter if selected
if (!empty($kategori)) {
    $query_berita .= " AND b.kategori = '$kategori'";
}

$query_berita .= " ORDER BY b.tanggal_publikasi DESC LIMIT $offset, $itemsPerPage";
$result_berita = mysqli_query($koneksi, $query_berita);

// Get total number of news for pagination
$query_count = "SELECT COUNT(*) as total FROM berita WHERE status = 'published'";
if (!empty($kategori)) {
    $query_count .= " AND kategori = '$kategori'";
}
$result_count = mysqli_query($koneksi, $query_count);
$row_count = mysqli_fetch_assoc($result_count);
$totalItems = $row_count['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Get categories for filter
$query_kategori = "SELECT DISTINCT kategori FROM berita WHERE status = 'published' ORDER BY kategori";
$result_kategori = mysqli_query($koneksi, $query_kategori);

// Include header
include '../includes/header.php';
?>

<main>
    <section class="page-header">
        <h2>Berita dan Pengumuman</h2>
        <p>Informasi terbaru dan pengumuman penting untuk masyarakat desa.</p>
    </section>

    <section class="content-section">
        <!-- Category filter -->
        <div class="kategori-filter" style="margin-bottom: 20px;">
            <form method="GET" action="berita.php" class="form-inline">
                <label for="filter-kategori" style="margin-right: 10px;">Filter kategori:</label>
                <select name="kategori" id="filter-kategori" class="filter-select" style="margin-right: 10px;">
                    <option value="">Semua Kategori</option>
                    <?php while ($kat = mysqli_fetch_assoc($result_kategori)): ?>
                        <option value="<?= $kat['kategori'] ?>" <?= $kategori == $kat['kategori'] ? 'selected' : '' ?>>
                            <?= $kat['kategori'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-outline">Filter</button>
            </form>
        </div>

        <?php if (mysqli_num_rows($result_berita) > 0): ?>
            <?php while ($berita = mysqli_fetch_assoc($result_berita)): ?>
                <div class="berita-card">
                    <!-- Try multiple path options to resolve the 404 error -->
                    <?php
                    // Build the image path based on thumbnail value
                    $imagePath = '';
                    if (!empty($berita['thumbnail'])) {
                        // If thumbnail contains a full path already, use it as is
                        if (strpos($berita['thumbnail'], '/') === 0 || strpos($berita['thumbnail'], 'http') === 0) {
                            $imagePath = $berita['thumbnail'];
                        } else {
                            // Check if thumbnail contains 'uploads/berita' already
                            if (strpos($berita['thumbnail'], 'uploads/berita') !== false) {
                                $imagePath = '../' . $berita['thumbnail'];
                            } else {
                                // Otherwise, construct the path
                                $imagePath = '../uploads/berita/' . $berita['thumbnail'];
                            }
                        }
                    } else {
                        // Default image if no thumbnail
                        $imagePath = '../assets/img/default-thumbnail.jpg';
                    }
                    ?>
                    <img src="<?= $imagePath ?>" 
                         alt="<?= htmlspecialchars($berita['judul']) ?>"
                         onerror="this.src='/assets/img/default-thumbnail.jpg'; this.onerror=null;"
                    >
                    <div class="berita-content">
                        <h4><?= htmlspecialchars($berita['judul']) ?></h4>
                        <p><?= htmlspecialchars($berita['konten_singkat']) ?>... </p>
                        <a href="detail-berita.php?id=<?= $berita['berita_id'] ?>" class="btn btn-outline">Selengkapnya</a>
                        <div class="berita-meta">
                            <span><?= $berita['tanggal_format'] ?></span>
                            <span><?= htmlspecialchars($berita['kategori']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="info-message">
                <p>Tidak ada berita atau pengumuman saat ini.</p>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="text-align: center; margin: 20px 0;">
                <?php if ($page > 1): ?>
                    <a href="berita.php?page=<?= ($page - 1) ?><?= !empty($kategori) ? '&kategori=' . $kategori : '' ?>" 
                       class="btn btn-outline" style="margin-right: 10px;">Sebelumnya</a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="berita.php?page=<?= ($page + 1) ?><?= !empty($kategori) ? '&kategori=' . $kategori : '' ?>" 
                       class="btn">Selanjutnya</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php
// Include footer
include '../includes/footer.php';
?>