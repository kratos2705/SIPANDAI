<?php
// Include necessary functions and components
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/koneksi.php';

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

// Get latest announcements/news with error handling
$berita_query = "SELECT berita_id, judul, konten, thumbnail, kategori, tanggal_publikasi 
                FROM berita 
                WHERE status = 'published' 
                ORDER BY tanggal_publikasi DESC 
                LIMIT 5";
$result_berita = executeQuery($koneksi, $berita_query);

// Get latest document applications with error handling
$pengajuan_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status,
                    u.nama, jd.nama_dokumen
                    FROM pengajuan_dokumen pd
                    JOIN users u ON pd.user_id = u.user_id
                    JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                    ORDER BY pd.tanggal_pengajuan DESC
                    LIMIT 5";
$pengajuan_result = executeQuery($koneksi, $pengajuan_query);

// Get available document types for information with error handling
$dokumen_query = "SELECT jenis_id, nama_dokumen, deskripsi, persyaratan 
                 FROM jenis_dokumen 
                 WHERE is_active = TRUE
                 ORDER BY nama_dokumen ASC";
$dokumen_result = executeQuery($koneksi, $dokumen_query);

// Get summary counts for dashboard with error handling
$total_pengajuan = 0;
$menunggu = 0;
$proses = 0;
$selesai = 0;

$total_pengajuan_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen";
$total_pengajuan_result = executeQuery($koneksi, $total_pengajuan_query);
if ($total_pengajuan_result && $row = mysqli_fetch_assoc($total_pengajuan_result)) {
    $total_pengajuan = $row['total'];
}

$menunggu_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen WHERE status = 'diajukan'";
$menunggu_result = executeQuery($koneksi, $menunggu_query);
if ($menunggu_result && $row = mysqli_fetch_assoc($menunggu_result)) {
    $menunggu = $row['total'];
}

$proses_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen WHERE status IN ('verifikasi', 'proses')";
$proses_result = executeQuery($koneksi, $proses_query);
if ($proses_result && $row = mysqli_fetch_assoc($proses_result)) {
    $proses = $row['total'];
}

$selesai_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen WHERE status = 'selesai'";
$selesai_result = executeQuery($koneksi, $selesai_query);
if ($selesai_result && $row = mysqli_fetch_assoc($selesai_result)) {
    $selesai = $row['total'];
}

// Include header
include 'includes/header.php';
?>

<!-- Home Page Content -->
<main>
    <section class="hero" id="home">
        <h2>Sistem Informasi dan Pelayanan Desa</h2>
        <p>SIPANDAI menyediakan layanan digital terpadu untuk mempermudah masyarakat desa dalam mengakses pelayanan administrasi secara cepat, transparan, dan efisien.</p>
        <div class="hero-buttons">
            <a href="user/pengajuan.php" class="btn">Buat Pengajuan</a>
            <a href="#status" class="btn btn-outline">Cek Status</a>
        </div>
    </section>

    <!-- Service Information -->
    <section class="layanan-info" id="layanan">
        <div class="container">
            <h2 class="section-title">Layanan SIPANDAI</h2>
            <p class="section-description">SIPANDAI menyediakan berbagai layanan administrasi desa yang dapat diakses secara online. Berikut adalah layanan yang tersedia:</p>
            
            <div class="layanan-cards">
                <div class="layanan-card">
                    <div class="icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Pengajuan Dokumen</h3>
                    <p>Ajukan berbagai dokumen administrasi seperti Surat Keterangan Domisili, Surat Pengantar KTP, Surat Keterangan Tidak Mampu, dan lainnya secara online tanpa perlu antre di kantor desa.</p>
                </div>
                <div class="layanan-card">
                    <div class="icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Pelacakan Status</h3>
                    <p>Pantau status pengajuan dokumen Anda secara real-time. Anda akan mendapatkan notifikasi ketika dokumen sudah siap diambil atau jika ada persyaratan tambahan yang dibutuhkan.</p>
                </div>
                <div class="layanan-card">
                    <div class="icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Informasi Desa</h3>
                    <p>Dapatkan informasi terbaru tentang kegiatan desa, pengumuman penting, jadwal posyandu, dan program pembangunan desa lainnya.</p>
                </div>
                <div class="layanan-card">
                    <div class="icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Jadwal Pelayanan</h3>
                    <p>Informasi jadwal pelayanan kantor desa, termasuk jadwal pelayanan khusus dan hari libur.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Document Types -->
    <section class="dokumen-info" id="dokumen">
        <div class="container">
            <h2 class="section-title">Jenis Dokumen yang Tersedia</h2>
            <p class="section-description">Berikut adalah jenis-jenis dokumen yang dapat diajukan melalui SIPANDAI:</p>
            
            <div class="dokumen-list">
                <?php
                if ($dokumen_result && mysqli_num_rows($dokumen_result) > 0) {
                    while ($row = mysqli_fetch_assoc($dokumen_result)) {
                        echo '<div class="dokumen-item">';
                        echo '<h3>' . htmlspecialchars($row['nama_dokumen']) . '</h3>';
                        echo '<p>' . htmlspecialchars($row['deskripsi']) . '</p>';
                        echo '<div class="persyaratan">';
                        echo '<h4>Persyaratan:</h4>';
                        echo '<p>' . nl2br(htmlspecialchars($row['persyaratan'])) . '</p>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    // Display sample data if no records found
                ?>
                    <div class="dokumen-item">
                        <h3>Surat Keterangan Domisili</h3>
                        <p>Surat yang menerangkan tempat tinggal seseorang secara resmi.</p>
                        <div class="persyaratan">
                            <h4>Persyaratan:</h4>
                            <p>1. Fotokopi KTP<br>2. Fotokopi Kartu Keluarga<br>3. Surat Pengantar RT/RW</p>
                        </div>
                    </div>
                    <div class="dokumen-item">
                        <h3>Surat Pengantar KTP</h3>
                        <p>Surat pengantar untuk pembuatan atau perpanjangan KTP.</p>
                        <div class="persyaratan">
                            <h4>Persyaratan:</h4>
                            <p>1. Fotokopi Kartu Keluarga<br>2. Surat Pengantar RT/RW<br>3. Pas foto 3x4 (2 lembar)</p>
                        </div>
                    </div>
                    <div class="dokumen-item">
                        <h3>Surat Keterangan Tidak Mampu</h3>
                        <p>Surat yang menerangkan bahwa seseorang termasuk keluarga tidak mampu.</p>
                        <div class="persyaratan">
                            <h4>Persyaratan:</h4>
                            <p>1. Fotokopi KTP<br>2. Fotokopi Kartu Keluarga<br>3. Surat Pengantar RT/RW<br>4. Surat Pernyataan Tidak Mampu</p>
                        </div>
                    </div>
                <?php
                }
                ?>
            </div>
            
            <div class="dokumen-info-footer">
                <p>Untuk informasi lebih lanjut mengenai persyaratan dan tata cara pengajuan dokumen, silakan hubungi kantor desa atau klik tombol di bawah ini:</p>
                <a href="user/panduan.php" class="btn">Panduan Pengajuan</a>
            </div>
        </div>
    </section>

    <!-- Status Check -->
    <section class="status-check" id="status">
        <div class="container">
            <h2 class="section-title">Cek Status Pengajuan</h2>
            <p class="section-description">Masukkan nomor pengajuan untuk melihat status dokumen Anda:</p>
            
            <form class="status-form" action="cek-status.php" method="GET">
                <input type="text" name="nomor_pengajuan" placeholder="Masukkan Nomor Pengajuan" required>
                <button type="submit" class="btn">Cek Status</button>
            </form>
            
            <div class="status-summary">
                <div class="status-card">
                    <h3><?php echo $total_pengajuan; ?></h3>
                    <p>Total Pengajuan</p>
                </div>
                <div class="status-card">
                    <h3><?php echo $menunggu; ?></h3>
                    <p>Menunggu Verifikasi</p>
                </div>
                <div class="status-card">
                    <h3><?php echo $proses; ?></h3>
                    <p>Sedang Diproses</p>
                </div>
                <div class="status-card">
                    <h3><?php echo $selesai; ?></h3>
                    <p>Dokumen Selesai</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest News -->
    <section class="berita" id="berita">
        <div class="container">
            <h2 class="section-title">Pengumuman Terbaru</h2>
            
            <div class="berita-list">
                <?php if (mysqli_num_rows($result_berita) > 0): ?>
                    <?php while ($berita = mysqli_fetch_assoc($result_berita)): ?>
                        <?php 
                        // Format the date
                        $berita['tanggal_format'] = date('d-m-Y', strtotime($berita['tanggal_publikasi']));
                        
                        // Create content preview
                        $content_preview = strip_tags(htmlspecialchars_decode($berita['konten']));
                        $berita['konten_singkat'] = substr($content_preview, 0, 150);
                        ?>
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
                                        $imagePath = $berita['thumbnail'];
                                    } else {
                                        // Otherwise, construct the path
                                        $imagePath = 'uploads/berita/' . $berita['thumbnail'];
                                    }
                                }
                            } else {
                                // Default image if no thumbnail
                                $imagePath = 'assets/img/default-thumbnail.jpg';
                            }
                            ?>
                            <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($berita['judul']) ?>" onerror="this.src='assets/img/default-thumbnail.jpg'; this.onerror=null;">
                            <div class="berita-content">
                                <h4><?= htmlspecialchars($berita['judul']) ?></h4>
                                <p><?= htmlspecialchars($berita['konten_singkat']) ?>... </p>
                                <a href="detail-berita.php?id=<?= $berita['berita_id'] ?>" class="btn btn-outline">Selengkapnya</a>
                                <div class="berita-meta">
                                    <span><i class="fas fa-calendar"></i> <?= $berita['tanggal_format'] ?></span>
                                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($berita['kategori']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <!-- Display sample data if no records found -->
                    <div class="berita-card">
                        <img src="assets/img/placeholder.jpg" alt="Jadwal Posyandu">
                        <div class="berita-content">
                            <h4>Jadwal Posyandu Bulan April 2025</h4>
                            <p>Berikut jadwal Posyandu untuk bulan April 2025. Posyandu Balita akan dilaksanakan pada tanggal 10 April 2025, sementara Posyandu Lansia akan dilaksanakan pada tanggal 15 April 2025...</p>
                            <a href="#" class="btn btn-outline">Selengkapnya</a>
                            <div class="berita-meta">
                                <span><i class="fas fa-calendar"></i> 01-04-2025</span>
                                <span><i class="fas fa-tag"></i> Kesehatan</span>
                            </div>
                        </div>
                    </div>
                    <div class="berita-card">
                        <img src="assets/img/placeholder.jpg" alt="Bantuan Sembako">
                        <div class="berita-content">
                            <h4>Pendataan Penerima Bantuan Sembako</h4>
                            <p>Pemerintah Desa akan melakukan pendataan ulang penerima bantuan sembako. Warga yang termasuk dalam kategori kurang mampu dapat mendaftarkan diri di kantor desa mulai 1-15 April 2025...</p>
                            <a href="#" class="btn btn-outline">Selengkapnya</a>
                            <div class="berita-meta">
                                <span><i class="fas fa-calendar"></i> 28-03-2025</span>
                                <span><i class="fas fa-tag"></i> Bantuan Sosial</span>
                            </div>
                        </div>
                    </div>
                    <div class="berita-card">
                        <img src="assets/img/placeholder.jpg" alt="Gotong Royong">
                        <div class="berita-content">
                            <h4>Gotong Royong Pembersihan Saluran Air</h4>
                            <p>Dalam rangka mengantisipasi musim hujan, akan diadakan gotong royong pembersihan saluran air di seluruh RT pada hari Minggu, 6 April 2025. Seluruh warga diharapkan partisipasinya...</p>
                            <a href="#" class="btn btn-outline">Selengkapnya</a>
                            <div class="berita-meta">
                                <span><i class="fas fa-calendar"></i> 25-03-2025</span>
                                <span><i class="fas fa-tag"></i> Kegiatan Desa</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="berita-footer">
                <a href="user/berita.php" class="btn">Lihat Semua Pengumuman</a>
            </div>
        </div>
    </section>
</main>
<style>
    /* Main Content Styles for SIPANDAI */

/* Global Styles for Main Content */
main {
    font-family: 'Roboto', sans-serif;
    color: #28a745;
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.section-title {
    text-align: center;
    font-size: 2.2rem;
    margin-bottom: 10px;
    color: #28a745;
    font-weight: 600;
}

.section-description {
    text-align: center;
    margin-bottom: 40px;
    color: #28a745;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    background-color: #28a745;
    color: white;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 2px solid #28a745;
    cursor: pointer;
}

.btn:hover {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-outline {
    background-color: transparent;
    color: #28a745;
}

.btn-outline:hover {
    background-color: #28a745;
    color: white;
}

/* Hero Section */
.hero {
    background-image: linear-gradient(rgba(255, 255, 255, 0.52), rgba(42, 42, 42, 0.6)), url('assets/img/logo1.jpg');
    background-size: cover;
    background-position: center;
    color: white;
    text-align: center;
    padding: 120px 20px;
    margin-bottom: 60px;
}

.hero h2 {
    font-size: 3rem;
    margin-bottom: 20px;
    font-weight: 700;
}

.hero p {
    font-size: 1.2rem;
    margin-bottom: 40px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.hero-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

/* Service Information Section */
.layanan-info {
    padding: 80px 0;
    background-color: #f9f9f9;
}

.layanan-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.layanan-card {
    background-color: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.layanan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.layanan-card .icon {
    font-size: 2.5rem;
    color: #28a745;
    margin-bottom: 20px;
}

.layanan-card h3 {
    font-size: 1.5rem;
    margin-bottom: 15px;
    color:rgb(0, 0, 0);
}

.layanan-card p {
    color:rgb(0, 0, 0);
}

/* Document Types Section */
.dokumen-info {
    padding: 80px 0;
}

.dokumen-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.dokumen-item {
    background-color: #f9f9f9;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
    border-left: 4px solid #28a745;
}

.dokumen-item h3 {
    font-size: 1.3rem;
    color:rgb(0, 0, 0);
    margin-bottom: 10px;
}

.dokumen-item p {
    margin-bottom: 15px;
    color: #7f8c8d;
}

.persyaratan h4 {
    font-size: 1.1rem;
    color:rgb(0, 0, 0);
    margin-bottom: 10px;
    padding-top: 5px;
    border-top: 1px dashed #e0e0e0;
}

.dokumen-info-footer {
    text-align: center;
    margin-top: 20px;
}

/* Status Check Section */
.status-check {
    padding: 80px 0;
    background-color: #ecf0f1;
}

.status-form {
    display: flex;
    max-width: 600px;
    margin: 0 auto 40px;
    gap: 10px;
}

.status-form input {
    flex-grow: 1;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.status-form input:focus {
    outline: none;
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.status-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 40px;
}

.status-card {
    background-color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
}

.status-card h3 {
    font-size: 2.5rem;
    color: #28a745;
    margin-bottom: 10px;
}

.status-card p {
    color: #7f8c8d;
    font-size: 1rem;
}

/* Latest News Section */
.berita {
    padding: 80px 0;
}

.berita-list {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-bottom: 40px;
}

/* Updated styles for berita-card format */
.berita-card {
    display: flex;
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.berita-card img {
    display: flex;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.berita-card:hover img {
    transform: scale(1.05);
}

.berita-card .berita-content {
    flex: 1;
    padding: 25px;
}

.berita-card .berita-content h4 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #2c3e50;
}

.berita-card .berita-meta {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    color: #95a5a6;
    font-size: 0.9rem;
}

.berita-card .berita-meta i {
    margin-right: 5px;
}

.berita-card .berita-content p {
    margin-bottom: 20px;
    color: #7f8c8d;
}

.berita-footer {
    text-align: center;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .mobile-preview {
        flex-direction: column;
    }
    
    .mobile-device {
        margin-bottom: 40px;
    }
    
    .mobile-text {
        text-align: center;
    }
    
    .mobile-features ul {
        display: inline-block;
        text-align: left;
    }
}

@media (max-width: 768px) {
    .hero h2 {
        font-size: 2.5rem;
    }
    
    .berita-card {
        flex-direction: column;
    }
    
    .berita-card img {
        width: 100%;
        height: 200px;
    }
    
    .status-form {
        flex-direction: column;
    }
    
    .status-form button {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .hero h2 {
        font-size: 2rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .section-title {
        font-size: 1.8rem;
    }
}
</style>
<?php
// Free results to prevent memory leaks
if ($result_berita) mysqli_free_result($result_berita);
if ($pengajuan_result) mysqli_free_result($pengajuan_result);
if ($dokumen_result) mysqli_free_result($dokumen_result);
if ($total_pengajuan_result) mysqli_free_result($total_pengajuan_result);
if ($menunggu_result) mysqli_free_result($menunggu_result);
if ($proses_result) mysqli_free_result($proses_result);
if ($selesai_result) mysqli_free_result($selesai_result);

// Include footer
include 'includes/footer.php';
?>