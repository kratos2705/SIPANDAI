<?php
// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('berita.php');
}

$berita_id = intval($_GET['id']);

// Get berita details
$query = "SELECT 
            b.*, 
            DATE_FORMAT(b.tanggal_publikasi, '%d %M %Y') as tanggal_format,
            u.nama as penulis
          FROM 
            berita b
          LEFT JOIN 
            users u ON b.created_by = u.user_id
          WHERE 
            b.berita_id = $berita_id AND b.status = 'published'";
$result = mysqli_query($koneksi, $query);

// Check if berita exists and is published
if (mysqli_num_rows($result) == 0) {
    redirect('berita.php');
}

$berita = mysqli_fetch_assoc($result);

// Increment view count
$update_views = "UPDATE berita SET view_count = view_count + 1 WHERE berita_id = $berita_id";
mysqli_query($koneksi, $update_views);

// Get related berita
$kategori = mysqli_real_escape_string($koneksi, $berita['kategori']);
$query_related = "SELECT 
                    berita_id, 
                    judul, 
                    thumbnail, 
                    DATE_FORMAT(tanggal_publikasi, '%d %M %Y') as tanggal_format
                  FROM 
                    berita 
                  WHERE 
                    kategori = '$kategori' AND 
                    berita_id != $berita_id AND 
                    status = 'published'
                  ORDER BY 
                    tanggal_publikasi DESC 
                  LIMIT 3";
$result_related = mysqli_query($koneksi, $query_related);

// Get berita attachments if any
$query_lampiran = "SELECT * FROM lampiran_berita WHERE berita_id = $berita_id";
$result_lampiran = mysqli_query($koneksi, $query_lampiran);

// Set page title
$pageTitle = $berita['judul'];

// Include header
include '../includes/header.php';

// Function to handle thumbnail paths
function getImagePath($thumbnailPath) {
    if (empty($thumbnailPath)) {
        return '../assets/img/default-thumbnail.jpg';
    }
    
    // If thumbnail contains a full path already, use it as is
    if (strpos($thumbnailPath, '/') === 0 || strpos($thumbnailPath, 'http') === 0) {
        return $thumbnailPath;
    }
    
    // Check if thumbnail contains 'uploads/berita' already
    if (strpos($thumbnailPath, 'uploads/berita') !== false) {
        return '../' . $thumbnailPath;
    }
    
    // Otherwise, construct the path
    return '../uploads/berita/' . $thumbnailPath;
}
?>

<main>
    <section class="page-header">
        <h2><?= htmlspecialchars($berita['judul']) ?></h2>
        <div class="berita-meta" style="margin-top: 10px;">
            <span><i class="fa fa-calendar"></i> <?= $berita['tanggal_format'] ?></span>
            <span><i class="fa fa-user"></i> <?= htmlspecialchars($berita['penulis']) ?></span>
            <span><i class="fa fa-tag"></i> <?= htmlspecialchars($berita['kategori']) ?></span>
            <span><i class="fa fa-eye"></i> <?= $berita['view_count'] ?> kali dilihat</span>
        </div>
    </section>

    <section class="content-section">
        <div class="berita-detail">
            <?php if (!empty($berita['thumbnail'])): ?>
                <div class="berita-thumbnail">
                    <img src="<?= getImagePath($berita['thumbnail']) ?>" alt="<?= htmlspecialchars($berita['judul']) ?>" onerror="this.src='../assets/img/default-thumbnail.jpg'; this.onerror=null;">
                </div>
            <?php endif; ?>

            <div class="berita-content">
                <?= nl2br($berita['konten']) ?>
            </div>

            <?php if (mysqli_num_rows($result_lampiran) > 0): ?>
                <div class="berita-lampiran">
                    <h4>Lampiran:</h4>
                    <ul>
                        <?php while ($lampiran = mysqli_fetch_assoc($result_lampiran)): ?>
                            <?php 
                            $lampiran_path = !empty($lampiran['path_file']) ? 
                                (strpos($lampiran['path_file'], '../') === 0 ? 
                                    $lampiran['path_file'] : 
                                    '../' . (strpos($lampiran['path_file'], 'uploads/') === 0 ? 
                                        $lampiran['path_file'] : 
                                        'uploads/lampiran/' . $lampiran['path_file'])
                                ) : 
                                '#';
                            ?>
                            <li>
                                <a href="<?= $lampiran_path ?>" target="_blank" download>
                                    <?= htmlspecialchars($lampiran['judul']) ?> 
                                    (<?= formatFileSize($lampiran['ukuran']) ?>)
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="berita-share">
                <h4>Bagikan:</h4>
                <div class="social-share-buttons">
                    <a href="#" class="btn btn-outline social-btn">Facebook</a>
                    <a href="#" class="btn btn-outline social-btn">Twitter</a>
                    <a href="#" class="btn btn-outline social-btn">WhatsApp</a>
                    <a href="#" class="btn btn-outline social-btn">Email</a>
                </div>
            </div>
        </div>

        <?php if (mysqli_num_rows($result_related) > 0): ?>
            <div class="berita-related">
                <h3>Berita Terkait</h3>
                <div class="berita-related-grid">
                    <?php while ($related = mysqli_fetch_assoc($result_related)): ?>
                        <div class="berita-related-item">
                            <a href="detail-berita.php?id=<?= $related['berita_id'] ?>">
                                <div class="berita-related-img">
                                    <img src="<?= !empty($related['thumbnail']) ? getImagePath($related['thumbnail']) : '../assets/img/default-thumbnail.jpg' ?>" 
                                         alt="<?= htmlspecialchars($related['judul']) ?>"
                                         onerror="this.src='../assets/img/default-thumbnail.jpg'; this.onerror=null;">
                                </div>
                                <h4><?= htmlspecialchars($related['judul']) ?></h4>
                                <div class="berita-meta">
                                    <span><?= $related['tanggal_format'] ?></span>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="berita.php" class="btn">Kembali ke Daftar Berita</a>
        </div>
    </section>
</main>
<style>
/* Custom Image Styles for detail-berita.php */

/* Main Berita Thumbnail */
.berita-thumbnail {
    width: 100%;
    margin-bottom: 25px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.berita-thumbnail img {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.5s ease;
}

.berita-thumbnail:hover img {
    transform: scale(1.02);
}

/* Images within Berita Content */
.berita-content img {
    max-width: 100%;
    height: auto;
    margin: 15px 0;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

/* Make image full width on small screens */
@media (max-width: 768px) {
    .berita-content img {
        width: 100%;
    }
}

/* Related News Image Styling */
.berita-related-img {
    height: 180px;
    overflow: hidden;
    border-radius: 6px;
    margin-bottom: 12px;
    position: relative;
}

.berita-related-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease, filter 0.4s ease;
}

.berita-related-item:hover .berita-related-img img {
    transform: scale(1.05);
    filter: brightness(1.05);
}

/* Fix for IE object-fit */
@media all and (-ms-high-contrast: none), (-ms-high-contrast: active) {
    .berita-related-img img {
        height: auto;
        min-height: 100%;
    }
}

/* Image Caption Support */
.berita-content figure {
    margin: 20px 0;
    width: 100%;
}

.berita-content figure img {
    width: 100%;
    border-radius: 6px 6px 0 0;
    margin-bottom: 0;
}

.berita-content figcaption {
    background-color: #f5f5f5;
    padding: 10px 15px;
    font-size: 14px;
    color: #666;
    border-radius: 0 0 6px 6px;
    text-align: center;
    border: 1px solid #eee;
    border-top: none;
}

/* Image Alignment Options */
.berita-content img.align-left {
    float: left;
    margin-right: 20px;
    margin-bottom: 10px;
    max-width: 50%;
}

.berita-content img.align-right {
    float: right;
    margin-left: 20px;
    margin-bottom: 10px;
    max-width: 50%;
}

.berita-content img.align-center {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

/* Clear floats after aligned images */
.berita-content p:after {
    content: "";
    display: table;
    clear: both;
}

/* Image Gallery Support */
.berita-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 25px 0;
}

.berita-gallery-item {
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    cursor: pointer;
}

.berita-gallery-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    transition: transform 0.3s ease, filter 0.3s ease;
    margin: 0; /* Override margin from other img styles */
    border-radius: 0; /* Override border-radius from other img styles */
    box-shadow: none; /* Override box-shadow from other img styles */
}

.berita-gallery-item:hover img {
    transform: scale(1.08);
    filter: brightness(1.1);
}

/* Image Lightbox Overlay Effect */
.berita-thumbnail img:active,
.berita-content img:active {
    cursor: zoom-in;
    filter: brightness(1.1);
}

/* Responsive Image Adjustments */
@media (max-width: 576px) {
    .berita-content img.align-left,
    .berita-content img.align-right {
        float: none;
        max-width: 100%;
        margin-left: 0;
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .berita-gallery {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
}

/* Custom coloring for image borders */
.berita-thumbnail,
.berita-content img,
.berita-related-img {
    border: 1px solid rgba(40, 167, 69, 0.2); /* Matching SIPANDAI green theme */
}

/* Loading animation for images */
.berita-thumbnail img,
.berita-content img,
.berita-related-img img {
    opacity: 0;
    animation: fadeIn 0.5s ease forwards;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Loaded images */
.berita-thumbnail img.loaded,
.berita-content img.loaded,
.berita-related-img img.loaded {
    opacity: 1;
}
</style>

<!-- Add this script at the bottom of your page, before the closing </body> tag -->
<script>
// Add loading animation for images
document.addEventListener('DOMContentLoaded', function() {
    // Get all images that need animation
    const images = document.querySelectorAll('.berita-thumbnail img, .berita-content img, .berita-related-img img');
    
    // Add loaded class to each image after it loads
    images.forEach(function(img) {
        // If image is already loaded
        if (img.complete) {
            img.classList.add('loaded');
        } else {
            // Add event listener for when it loads
            img.addEventListener('load', function() {
                img.classList.add('loaded');
            });
        }
    });
});
</script>
<?php
// Function to format file size
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// Include footer
include '../includes/footer.php';
?>