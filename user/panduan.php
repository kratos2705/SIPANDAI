<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../config/koneksi.php';

// Error handling function to log and display errors
function handleDatabaseError($query, $error) {
    error_log("Database query error: $query - Error: $error");
    return false;
}

// Get document types for reference with proper error handling
$dokumen_result = false;
$dokumen_types = [];
try {
    $dokumen_query = "SELECT jenis_id, nama_dokumen, deskripsi, persyaratan 
                     FROM jenis_dokumen 
                     WHERE is_active = TRUE
                     ORDER BY nama_dokumen ASC";
    $dokumen_result = mysqli_query($koneksi, $dokumen_query);
    
    if (!$dokumen_result) {
        handleDatabaseError($dokumen_query, mysqli_error($koneksi));
    } else {
        // Store results in array for easier access
        while ($row = mysqli_fetch_assoc($dokumen_result)) {
            $dokumen_types[] = $row;
        }
        // Reset pointer for later use if needed
        if (count($dokumen_types) > 0) {
            mysqli_data_seek($dokumen_result, 0);
        }
    }
} catch (Exception $e) {
    error_log("Exception in dokumen query: " . $e->getMessage());
}

// Get FAQs related to document submission with proper error handling
$faq_result = false;
$faqs = [];
try {
    $faq_query = "SELECT faq_id, pertanyaan, jawaban 
                  FROM faq 
                  WHERE kategori = 'pengajuan' 
                  ORDER BY urutan ASC";
    $faq_result = mysqli_query($koneksi, $faq_query);
    
    if (!$faq_result) {
        handleDatabaseError($faq_query, mysqli_error($koneksi));
    } else {
        // Store results in array for easier access
        while ($row = mysqli_fetch_assoc($faq_result)) {
            $faqs[] = $row;
        }
        // Reset pointer for later use if needed
        if (count($faqs) > 0) {
            mysqli_data_seek($faq_result, 0);
        }
    }
} catch (Exception $e) {
    error_log("Exception in FAQ query: " . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<!-- Panduan Pengajuan Content -->
<main>
    <section class="page-header">
        <div class="container">
            <h1>Panduan Pengajuan Dokumen</h1>
        </div>
    </section>

    <section class="panduan-section">
        <div class="container">
            <div class="panduan-nav">
                <ul>
                    <li><a href="#alur-pengajuan">Alur Pengajuan</a></li>
                    <li><a href="#syarat-dokumen">Persyaratan Dokumen</a></li>
                    <li><a href="#cara-pengajuan">Cara Mengajukan</a></li>
                    <li><a href="#status-tracking">Pelacakan Status</a></li>
                    <li><a href="#faq">Pertanyaan Umum</a></li>
                </ul>
            </div>

            <div class="panduan-content">
                <!-- Alur Pengajuan -->
                <div id="alur-pengajuan" class="panduan-item">
                    <h2>Alur Pengajuan Dokumen</h2>
                    <div class="alur-steps">
                        <div class="alur-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h3>Persiapan Dokumen</h3>
                                <p>Siapkan semua persyaratan dokumen yang dibutuhkan sesuai dengan jenis dokumen yang akan diajukan. Pastikan file dokumen dalam format yang didukung (JPG, PNG, atau PDF) dengan ukuran maksimal 2MB per file.</p>
                            </div>
                        </div>
                        <div class="alur-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h3>Pengajuan Online</h3>
                                <p>Login ke akun SIPANDAI Anda dan pilih menu "Buat Pengajuan". Isi formulir dengan lengkap dan unggah semua dokumen pendukung yang diperlukan.</p>
                            </div>
                        </div>
                        <div class="alur-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h3>Verifikasi</h3>
                                <p>Petugas desa akan melakukan verifikasi terhadap pengajuan dan dokumen yang telah Anda unggah. Jika ada kekurangan, Anda akan dihubungi melalui kontak yang terdaftar.</p>
                            </div>
                        </div>
                        <div class="alur-step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h3>Pemrosesan</h3>
                                <p>Setelah verifikasi berhasil, pengajuan Anda akan diproses oleh petugas desa. Proses ini membutuhkan waktu 1-3 hari kerja tergantung jenis dokumen yang diajukan.</p>
                            </div>
                        </div>
                        <div class="alur-step">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <h3>Pengambilan Dokumen</h3>
                                <p>Setelah dokumen selesai diproses, Anda akan mendapatkan notifikasi. Anda dapat mengambil dokumen di kantor desa dengan membawa bukti pengajuan dan KTP asli.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Persyaratan Dokumen -->
                <div id="syarat-dokumen" class="panduan-item">
                    <h2>Persyaratan Dokumen</h2>
                    <p>Berikut adalah persyaratan untuk setiap jenis dokumen yang dapat diajukan melalui SIPANDAI:</p>
                    
                    <div class="dokumen-accordion">
                        <?php
                        if (count($dokumen_types) > 0) {
                            foreach ($dokumen_types as $dokumen) {
                                echo '<div class="dokumen-item">';
                                echo '<div class="dokumen-header">';
                                echo '<h3>' . htmlspecialchars($dokumen['nama_dokumen']) . '</h3>';
                                echo '<span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>';
                                echo '</div>';
                                echo '<div class="dokumen-body">';
                                echo '<p>' . htmlspecialchars($dokumen['deskripsi']) . '</p>';
                                echo '<div class="persyaratan-list">';
                                echo '<h4>Persyaratan:</h4>';
                                echo '<div class="persyaratan-content">' . nl2br(htmlspecialchars($dokumen['persyaratan'])) . '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            // Sample data if no records found
                            ?>
                            <div class="dokumen-item">
                                <div class="dokumen-header">
                                    <h3>Surat Keterangan Domisili</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="dokumen-body">
                                    <p>Surat yang menerangkan tempat tinggal seseorang secara resmi.</p>
                                    <div class="persyaratan-list">
                                        <h4>Persyaratan:</h4>
                                        <div class="persyaratan-content">
                                            1. Fotokopi KTP<br>
                                            2. Fotokopi Kartu Keluarga<br>
                                            3. Surat Pengantar RT/RW
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dokumen-item">
                                <div class="dokumen-header">
                                    <h3>Surat Pengantar KTP</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="dokumen-body">
                                    <p>Surat pengantar untuk pembuatan atau perpanjangan KTP.</p>
                                    <div class="persyaratan-list">
                                        <h4>Persyaratan:</h4>
                                        <div class="persyaratan-content">
                                            1. Fotokopi Kartu Keluarga<br>
                                            2. Surat Pengantar RT/RW<br>
                                            3. Pas foto 3x4 (2 lembar)
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="dokumen-item">
                                <div class="dokumen-header">
                                    <h3>Surat Keterangan Tidak Mampu</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="dokumen-body">
                                    <p>Surat yang menerangkan bahwa seseorang termasuk keluarga tidak mampu.</p>
                                    <div class="persyaratan-list">
                                        <h4>Persyaratan:</h4>
                                        <div class="persyaratan-content">
                                            1. Fotokopi KTP<br>
                                            2. Fotokopi Kartu Keluarga<br>
                                            3. Surat Pengantar RT/RW<br>
                                            4. Surat Pernyataan Tidak Mampu
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>

                <!-- Cara Mengajukan -->
                <div id="cara-pengajuan" class="panduan-item">
                    <h2>Cara Mengajukan Dokumen</h2>
                    <div class="cara-steps">
                        <div class="cara-step">
                            <div class="step-icon"><i class="fas fa-user-circle"></i></div>
                            <div class="step-content">
                                <h3>Login ke Akun</h3>
                                <p>Masuk ke akun SIPANDAI Anda menggunakan NIK dan password yang telah terdaftar. Jika belum memiliki akun, silakan mendaftar terlebih dahulu.</p>
                                <a href="../user/login.php" class="btn btn-outline">Login</a>
                                <a href="../user/register.php" class="btn btn-outline">Daftar</a>
                            </div>
                        </div>
                        <div class="cara-step">
                            <div class="step-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="step-content">
                                <h3>Pilih Jenis Dokumen</h3>
                                <p>Pilih menu "Buat Pengajuan" dan pilih jenis dokumen yang ingin diajukan. Baca dengan teliti persyaratan yang dibutuhkan.</p>
                            </div>
                        </div>
                        <div class="cara-step">
                            <div class="step-icon"><i class="fas fa-edit"></i></div>
                            <div class="step-content">
                                <h3>Isi Formulir</h3>
                                <p>Isi formulir pengajuan dengan data yang benar dan lengkap. Pastikan semua informasi sesuai dengan dokumen identitas resmi Anda.</p>
                            </div>
                        </div>
                        <div class="cara-step">
                            <div class="step-icon"><i class="fas fa-upload"></i></div>
                            <div class="step-content">
                                <h3>Unggah Dokumen</h3>
                                <p>Unggah semua dokumen persyaratan sesuai dengan format yang ditentukan (JPG, PNG, atau PDF) dengan ukuran maksimal 2MB per file.</p>
                            </div>
                        </div>
                        <div class="cara-step">
                            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="step-content">
                                <h3>Kirim Pengajuan</h3>
                                <p>Periksa kembali semua data dan dokumen yang telah diisi, kemudian klik tombol "Kirim Pengajuan". Anda akan mendapatkan nomor pengajuan yang dapat digunakan untuk melacak status dokumen.</p>
                            </div>
                        </div>
                    </div>

                    <div class="video-tutorial">
                        <h3>Video Tutorial</h3>
                        <p>Untuk memudahkan proses pengajuan, Anda dapat menyaksikan video tutorial berikut:</p>
                        <div class="video-container">
                            <!-- Placeholder for video tutorial -->
                            <div class="video-placeholder">
                                <div class="play-button">
                                    <i class="fas fa-play"></i>
                                </div>
                                <p>Video Tutorial Pengajuan Dokumen</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Tracking -->
                <div id="status-tracking" class="panduan-item">
                    <h2>Pelacakan Status Pengajuan</h2>
                    <p>Setelah mengirimkan pengajuan, Anda dapat melacak status dokumen dengan cara berikut:</p>
                    
                    <div class="tracking-methods">
                        <div class="tracking-method">
                            <div class="method-icon"><i class="fas fa-search"></i></div>
                            <div class="method-content">
                                <h3>Cek Status di Website</h3>
                                <p>Masukkan nomor pengajuan pada form "Cek Status" di halaman utama SIPANDAI untuk melihat status terkini dokumen Anda.</p>
                                <a href="../index.php#status" class="btn btn-outline">Cek Status</a>
                            </div>
                        </div>
                        <div class="tracking-method">
                            <div class="method-icon"><i class="fas fa-user"></i></div>
                            <div class="method-content">
                                <h3>Melalui Akun Pribadi</h3>
                                <p>Login ke akun SIPANDAI dan lihat daftar pengajuan Anda. Anda dapat melihat detail status dan histori pemrosesan dokumen.</p>
                            </div>
                        </div>
                        <div class="tracking-method">
                            <div class="method-icon"><i class="fas fa-bell"></i></div>
                            <div class="method-content">
                                <h3>Notifikasi</h3>
                                <p>Anda akan menerima notifikasi melalui email atau SMS ketika ada perubahan status pada dokumen yang Anda ajukan.</p>
                            </div>
                        </div>
                    </div>

                    <div class="status-explanation">
                        <h3>Penjelasan Status</h3>
                        <div class="status-list">
                            <div class="status-item">
                                <span class="status-badge diajukan">Diajukan</span>
                                <p>Pengajuan telah diterima dan sedang menunggu verifikasi awal oleh petugas.</p>
                            </div>
                            <div class="status-item">
                                <span class="status-badge verifikasi">Verifikasi</span>
                                <p>Petugas sedang melakukan verifikasi terhadap dokumen yang Anda unggah.</p>
                            </div>
                            <div class="status-item">
                                <span class="status-badge proses">Proses</span>
                                <p>Dokumen sedang dalam proses pembuatan oleh petugas desa.</p>
                            </div>
                            <div class="status-item">
                                <span class="status-badge selesai">Selesai</span>
                                <p>Dokumen telah selesai dan siap untuk diambil di kantor desa.</p>
                            </div>
                            <div class="status-item">
                                <span class="status-badge ditolak">Ditolak</span>
                                <p>Pengajuan ditolak karena tidak memenuhi persyaratan. Anda akan mendapatkan informasi alasan penolakan.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ -->
                <div id="faq" class="panduan-item">
                    <h2>Pertanyaan Umum (FAQ)</h2>
                    <div class="faq-container">
                        <?php
                        if (count($faqs) > 0) {
                            foreach ($faqs as $faq) {
                                echo '<div class="faq-item">';
                                echo '<div class="faq-question">';
                                echo '<h3>' . htmlspecialchars($faq['pertanyaan']) . '</h3>';
                                echo '<span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>';
                                echo '</div>';
                                echo '<div class="faq-answer">';
                                echo '<p>' . nl2br(htmlspecialchars($faq['jawaban'])) . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            // Sample FAQ data if no records found
                            ?>
                            <div class="faq-item">
                                <div class="faq-question">
                                    <h3>Berapa lama proses pengajuan dokumen?</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="faq-answer">
                                    <p>Proses pengajuan dokumen membutuhkan waktu 1-3 hari kerja tergantung jenis dokumen dan kelengkapan persyaratan. Setelah status berubah menjadi "Selesai", dokumen dapat diambil di kantor desa.</p>
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question">
                                    <h3>Apa saja format file yang didukung untuk unggah dokumen?</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="faq-answer">
                                    <p>SIPANDAI mendukung format file JPG, PNG, dan PDF dengan ukuran maksimal 2MB per file. Pastikan dokumen yang diunggah terlihat jelas dan tidak buram.</p>
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question">
                                    <h3>Apakah saya perlu datang ke kantor desa untuk mengajukan dokumen?</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="faq-answer">
                                    <p>Tidak perlu. Anda dapat mengajukan dokumen secara online melalui SIPANDAI. Namun, untuk pengambilan dokumen asli, Anda tetap perlu datang ke kantor desa dengan membawa bukti pengajuan dan KTP asli.</p>
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question">
                                    <h3>Apa yang harus dilakukan jika status pengajuan ditolak?</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="faq-answer">
                                    <p>Jika pengajuan ditolak, Anda akan mendapatkan notifikasi dengan alasan penolakan. Anda dapat memperbaiki kekurangan dan mengajukan kembali dokumen tersebut. Untuk bantuan lebih lanjut, Anda dapat menghubungi kantor desa.</p>
                                </div>
                            </div>
                            <div class="faq-item">
                                <div class="faq-question">
                                    <h3>Dapatkah orang lain mengambil dokumen yang saya ajukan?</h3>
                                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                                </div>
                                <div class="faq-answer">
                                    <p>Ya, dokumen dapat diambil oleh orang lain dengan membawa surat kuasa asli yang ditandatangani oleh pemohon, fotokopi KTP pemohon, dan KTP asli pengambil.</p>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>

                <!-- Contact for Help -->
                <div class="panduan-contact">
                    <h2>Butuh Bantuan?</h2>
                    <p>Jika Anda memiliki pertanyaan lain atau membutuhkan bantuan dalam proses pengajuan, silakan hubungi kami:</p>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <p>(021) 12345678</p>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <p>info@sipandai.desa.id</p>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Kantor Desa, Jl. Utama No. 1, Kode Pos 12345</p>
                        </div>
                    </div>
                    <div class="contact-hours">
                        <h3>Jam Pelayanan</h3>
                        <p>Senin - Jumat: 08.00 - 16.00</p>
                        <p>Sabtu: 08.00 - 12.00</p>
                        <p>Minggu & Hari Libur Nasional: Tutup</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section">
        <div class="container">
            <h2>Siap Mengajukan Dokumen?</h2>
            <p>Pastikan Anda sudah mempersiapkan semua persyaratan yang dibutuhkan.</p>
            <div class="cta-buttons">
                <a href="../user/pengajuan.php" class="btn">Ajukan Sekarang</a>
                <a href="#syarat-dokumen" class="btn btn-outline">Cek Persyaratan</a>
            </div>
        </div>
    </section>
</main>

<style>
/* Panduan Pengajuan Specific Styles */
.page-header {
    background-color: #28a745;
    color: white;
    padding: 60px 0;
    text-align: center;
    margin-bottom: 40px;
}

.page-header h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.panduan-section {
    padding: 40px 0 80px;
}

.panduan-nav {
    background-color: #f9f9f9;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 40px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
}

.panduan-nav ul {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 20px;
    list-style: none;
    padding: 0;
    margin: 0;
}

.panduan-nav a {
    text-decoration: none;
    color: #2c3e50;
    font-weight: 500;
    padding: 8px 15px;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.panduan-nav a:hover {
    background-color: #e0f2e9;
    color: #28a745;
}

.panduan-item {
    margin-bottom: 60px;
}

.panduan-item h2 {
    font-size: 1.8rem;
    color: #28a745;
    margin-bottom: 30px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0f2e9;
}

/* Alur Pengajuan Styles */
.alur-steps {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.alur-step {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: #28a745;
    color: white;
    border-radius: 50%;
    font-size: 1.2rem;
    font-weight: bold;
    flex-shrink: 0;
}

.step-content h3 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

/* Dokumen Accordion Styles */
.dokumen-accordion {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.dokumen-item {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.dokumen-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f9f9f9;
    cursor: pointer;
}

.dokumen-header h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin: 0;
}

.toggle-icon {
    font-size: 1rem;
    color: #7f8c8d;
    transition: transform 0.3s ease;
}

.dokumen-item.active .toggle-icon {
    transform: rotate(180deg);
}

.dokumen-body {
    padding: 0 20px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
}

.dokumen-item.active .dokumen-body {
    padding: 20px;
    max-height: 500px;
}

.persyaratan-list h4 {
    font-size: 1.1rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

.persyaratan-content {
    padding-left: 15px;
    border-left: 3px solid #e0f2e9;
    color: #7f8c8d;
}

/* Cara Pengajuan Styles */
.cara-steps {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-bottom: 40px;
}

.cara-step {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.step-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    background-color: #e0f2e9;
    color: #28a745;
    border-radius: 50%;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.cara-step .btn {
    margin-right: 10px;
    margin-top: 15px;
}

.video-tutorial {
    background-color: #f9f9f9;
    border-radius: 10px;
    padding: 30px;
    margin-top: 40px;
}

.video-tutorial h3 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 15px;
}

.video-container {
    margin-top: 20px;
}

.video-placeholder {
    background-color: #e0e0e0;
    border-radius: 8px;
    height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.play-button {
    width: 70px;
    height: 70px;
    background-color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.play-button i {
    font-size: 2rem;
    color: #28a745;
}

/* Status Tracking Styles */
.tracking-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.tracking-method {
    background-color: #f9f9f9;
    border-radius: 10px;
    padding: 25px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.method-icon {
    width: 50px;
    height: 50px;
    background-color: #e0f2e9;
    color: #28a745;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.method-content h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin-bottom: 10margin-bottom: 10px;
}

.method-content .btn {
    margin-top: 15px;
}

.status-explanation {
    margin-top: 40px;
}

.status-explanation h3 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 20px;
}

.status-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    color: white;
    min-width: 100px;
    text-align: center;
}

.status-badge.diajukan {
    background-color: #3498db;
}

.status-badge.verifikasi {
    background-color: #f39c12;
}

.status-badge.proses {
    background-color: #9b59b6;
}

.status-badge.selesai {
    background-color: #2ecc71;
}

.status-badge.ditolak {
    background-color: #e74c3c;
}

/* FAQ Styles */
.faq-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.faq-item {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.faq-question {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f9f9f9;
    cursor: pointer;
}

.faq-question h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin: 0;
}

.faq-answer {
    padding: 0 20px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
}

.faq-item.active .faq-answer {
    padding: 20px;
    max-height: 500px;
}

/* Contact Info Styles */
.panduan-contact {
    background-color: #f9f9f9;
    border-radius: 10px;
    padding: 30px;
    margin-top: 60px;
}

.panduan-contact h2 {
    margin-top: 0;
}

.contact-info {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    margin: 20px 0;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-item i {
    font-size: 1.5rem;
    color: #28a745;
}

.contact-item p {
    margin: 0;
    color: #2c3e50;
}

.contact-hours {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px dashed #e0e0e0;
}

.contact-hours h3 {
    font-size: 1.2rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

.contact-hours p {
    margin: 5px 0;
    color: #7f8c8d;
}

/* CTA Section */
.cta-section {
    background-color: #e0f2e9;
    padding: 60px 0;
    text-align: center;
}

.cta-section h2 {
    font-size: 2rem;
    color: #2c3e50;
    margin-bottom: 15px;
}

.cta-buttons {
    margin-top: 30px;
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .tracking-methods {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .alur-step, .cara-step, .tracking-method {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .step-content, .method-content {
        width: 100%;
    }
    
    .panduan-nav ul {
        flex-direction: column;
        align-items: center;
    }
    
    .panduan-nav a {
        display: block;
        width: 100%;
        text-align: center;
    }
    
    .contact-info {
        flex-direction: column;
        gap: 15px;
    }
}

@media (max-width: 576px) {
    .page-header h1 {
        font-size: 2rem;
    }
    
    .panduan-item h2 {
        font-size: 1.5rem;
    }
    
    .cta-section h2 {
        font-size: 1.7rem;
    }
    
    .cta-buttons {
        flex-direction: column;
        gap: 15px;
    }
    
    .cta-buttons .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion functionality for document items
    const documenItems = document.querySelectorAll('.dokumen-item');
    documenItems.forEach(item => {
        const header = item.querySelector('.dokumen-header');
        header.addEventListener('click', () => {
            // Close all other items
            documenItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            // Toggle current item
            item.classList.toggle('active');
        });
    });
    
    // Accordion functionality for FAQ items
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.addEventListener('click', () => {
            // Close all other items
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            // Toggle current item
            item.classList.toggle('active');
        });
    });
    
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 100, // Offset for fixed header if any
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Highlight active navigation item on scroll
    const navLinks = document.querySelectorAll('.panduan-nav a');
    const sections = document.querySelectorAll('.panduan-item');
    
    window.addEventListener('scroll', () => {
        let currentSection = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (window.pageYOffset >= sectionTop - 150) {
                currentSection = '#' + section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === currentSection) {
                link.classList.add('active');
            }
        });
    });
    
    // Video placeholder click handler (demo only)
    const videoPlaceholder = document.querySelector('.video-placeholder');
    if (videoPlaceholder) {
        videoPlaceholder.addEventListener('click', () => {
            alert('Video tutorial akan diputar. Fitur ini sedang dalam pengembangan.');
        });
    }
});
</script>

<?php
// Free results to prevent memory leaks
if ($dokumen_result) mysqli_free_result($dokumen_result);
if ($faq_result) mysqli_free_result($faq_result);

// Include footer
include '../includes/footer.php';
?>