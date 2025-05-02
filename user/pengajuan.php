<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../config/koneksi.php';

// Check if user is logged in
$is_logged_in = isLoggedIn();

// Get all document types from database
$jenis_dokumen_query = "SELECT jenis_id, nama_dokumen, deskripsi, persyaratan, estimasi_waktu 
                       FROM jenis_dokumen 
                       WHERE is_active = TRUE 
                       ORDER BY nama_dokumen ASC";
$jenis_dokumen_result = mysqli_query($koneksi, $jenis_dokumen_query);

// Set page title
$page_title = "Pengajuan Dokumen Online";

// Include header
include '../includes/header.php';
?>

<main>
    <section class="page-header">
        <h2>Pengajuan Dokumen Online</h2>
        <p>Ajukan dan pantau status permohonan dokumen secara online</p>
    </section>

    <!-- Info Box - Show different messages based on login status -->
    <?php if (!$is_logged_in): ?>
        <div class="alert alert-warning">
            <p>Anda harus <a href="#" id="loginPrompt">masuk</a> terlebih dahulu untuk mengajukan dokumen.</p>
        </div>
    <?php endif; ?>

    <div class="progress-steps">
        <div class="step active">
            <div class="step-number">1</div>
            <div class="step-name">Isi Formulir</div>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <div class="step-name">Unggah Dokumen</div>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <div class="step-name">Verifikasi</div>
        </div>
        <div class="step">
            <div class="step-number">4</div>
            <div class="step-name">Selesai</div>
        </div>
    </div>

    <div class="pengajuan-container">
        <div class="pengajuan-forms">
            <div class="form-tabs">
                <div class="form-tab active">Dokumen Kependudukan</div>
                <div class="form-tab">Dokumen Usaha</div>
                <div class="form-tab">Dokumen Lainnya</div>
            </div>

            <div class="form-content">
                <h2>Form Pengajuan Dokumen Kependudukan</h2>

                <?php if ($is_logged_in): ?>
                    <form id="dokumenForm" action="proses_pengajuan.php" method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nama" class="required">Nama Lengkap</label>
                                <input type="text" id="nama" name="nama" required placeholder="Sesuai KTP" value="<?php echo htmlspecialchars($_SESSION['user_nama'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="nik" class="required">NIK</label>
                                <input type="text" id="nik" name="nik" required maxlength="16" placeholder="16 digit NIK">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="tempat_lahir" class="required">Tempat Lahir</label>
                                <input type="text" id="tempat_lahir" name="tempat_lahir" required>
                            </div>
                            <div class="form-group">
                                <label for="tanggal_lahir" class="required">Tanggal Lahir</label>
                                <input type="date" id="tanggal_lahir" name="tanggal_lahir" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="jenis_kelamin" class="required">Jenis Kelamin</label>
                                <select id="jenis_kelamin" name="jenis_kelamin" required>
                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="agama" class="required">Agama</label>
                                <select id="agama" name="agama" required>
                                    <option value="">-- Pilih Agama --</option>
                                    <option value="islam">Islam</option>
                                    <option value="kristen">Kristen</option>
                                    <option value="katolik">Katolik</option>
                                    <option value="hindu">Hindu</option>
                                    <option value="buddha">Buddha</option>
                                    <option value="konghucu">Konghucu</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="status_perkawinan" class="required">Status Perkawinan</label>
                                <select id="status_perkawinan" name="status_perkawinan" required>
                                    <option value="">-- Pilih Status --</option>
                                    <option value="belum_kawin">Belum Kawin</option>
                                    <option value="kawin">Kawin</option>
                                    <option value="cerai_hidup">Cerai Hidup</option>
                                    <option value="cerai_mati">Cerai Mati</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="pekerjaan" class="required">Pekerjaan</label>
                                <input type="text" id="pekerjaan" name="pekerjaan" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="alamat" class="required">Alamat Lengkap</label>
                            <textarea id="alamat" name="alamat" required placeholder="Sesuai KTP"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="rt" class="required">RT</label>
                                <input type="text" id="rt" name="rt" required maxlength="3">
                            </div>
                            <div class="form-group">
                                <label for="rw" class="required">RW</label>
                                <input type="text" id="rw" name="rw" required maxlength="3">
                            </div>
                            <div class="form-group">
                                <label for="dusun">Dusun</label>
                                <input type="text" id="dusun" name="dusun">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telepon" class="required">Nomor Telepon</label>
                                <input type="tel" id="telepon" name="telepon" required placeholder="Contoh: 08123456789">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Untuk notifikasi status pengajuan"
                                    value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="jenis_dokumen" class="required">Jenis Dokumen yang Diajukan</label>
                            <select id="jenis_dokumen" name="jenis_dokumen" required>
                                <option value="">-- Pilih Jenis Dokumen --</option>
                                <?php
                                if (mysqli_num_rows($jenis_dokumen_result) > 0) {
                                    while ($row = mysqli_fetch_assoc($jenis_dokumen_result)) {
                                        echo '<option value="' . $row['jenis_id'] . '">' . htmlspecialchars($row['nama_dokumen']) . '</option>';
                                    }
                                } else {
                                    // Fallback options if no data in database
                                ?>
                                    <option value="surat_keterangan_domisili">Surat Keterangan Domisili</option>
                                    <option value="surat_pengantar_ktp">Surat Pengantar KTP</option>
                                    <option value="surat_pengantar_kk">Surat Pengantar KK</option>
                                    <option value="surat_keterangan_tidak_mampu">Surat Keterangan Tidak Mampu</option>
                                    <option value="surat_pengantar_nikah">Surat Pengantar Nikah</option>
                                    <option value="surat_keterangan_pindah">Surat Keterangan Pindah</option>
                                    <option value="surat_keterangan_kelahiran">Surat Keterangan Kelahiran</option>
                                    <option value="surat_keterangan_kematian">Surat Keterangan Kematian</option>
                                <?php
                                }
                                // Reset the result pointer
                                mysqli_data_seek($jenis_dokumen_result, 0);
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="keperluan" class="required">Keperluan</label>
                            <textarea id="keperluan" name="keperluan" required placeholder="Jelaskan keperluan pengajuan dokumen"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="required">Dokumen Pendukung</label>
                            <div class="file-upload">
                                <p>Unggah dokumen pendukung seperti KTP, KK, dll</p>
                                <p>Format: JPG, PNG, atau PDF (Maks. 5MB)</p>
                                <input type="file" id="dokumen_pendukung" name="dokumen_pendukung[]" multiple accept=".jpg,.jpeg,.png,.pdf" required>
                                <p id="selected-files">Belum ada file dipilih</p>
                            </div>
                        </div>

                        <div id="persyaratan-container" class="requirements-box" style="display: none;">
                            <h3>Persyaratan Dokumen</h3>
                            <div id="persyaratan-content"></div>
                        </div>

                        <div style="text-align: right;">
                            <button type="submit" class="btn" <?php echo $is_logged_in ? '' : 'disabled'; ?>>Kirim Pengajuan</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="login-required">
                        <p>Untuk mengajukan dokumen, silakan login terlebih dahulu.</p>
                        <button id="loginRequiredBtn" class="btn">Masuk ke Akun</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="pengajuan-info">
            <div class="info-card">
                <h3>Informasi Pengajuan</h3>
                <ul class="step-list">
                    <li>Isi formulir pengajuan dengan lengkap dan benar</li>
                    <li>Unggah dokumen pendukung yang dibutuhkan</li>
                    <li>Verifikasi data dan dokumen yang telah diisi</li>
                    <li>Kirim pengajuan dan tunggu proses verifikasi</li>
                    <li>Terima notifikasi status pengajuan</li>
                    <li>Ambil dokumen sesuai jadwal yang ditentukan</li>
                </ul>
            </div>

            <div class="info-card" id="dokumen-info">
                <h3>Dokumen yang Diperlukan</h3>
                <div id="default-requirements">
                    <ul class="document-list">
                        <li>Fotokopi KTP</li>
                        <li>Fotokopi Kartu Keluarga</li>
                        <li>Pas Foto 3x4 (2 lembar)</li>
                        <li>Surat Pengantar RT/RW</li>
                        <li>Dokumen pendukung lainnya sesuai jenis pengajuan</li>
                    </ul>
                </div>
                <div id="specific-requirements" style="display: none;"></div>
            </div>

            <div class="info-card">
                <h3>Butuh Bantuan?</h3>
                <div class="contact-info">
                    <span class="contact-icon">📞</span>
                    <span>+62 8123 4567 890</span>
                </div>
                <div class="contact-info">
                    <span class="contact-icon">✉️</span>
                    <span>layanan@sipandai.desa.id</span>
                </div>
                <div class="contact-info">
                    <span class="contact-icon">🏢</span>
                    <span>Kantor Desa, Jam Kerja: 08.00 - 16.00</span>
                </div>
                <a href="#" class="btn" style="margin-top: 15px; width: 100%; text-align: center;">Chat dengan Admin</a>
            </div>

            <?php if (isLoggedIn()): ?>
                <div class="info-card">
                    <h3>Pengajuan Terakhir Anda</h3>
                    <?php
                    // Get user's last application with proper error handling
                    if (isset($_SESSION['user_id'])) {
                        $user_id = $_SESSION['user_id'];

                        try {
                            // Include database connection if not already included
                            if (!isset($koneksi)) {
                                require_once '../config/koneksi.php';
                            }

                            $last_pengajuan_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status,
                                        jd.nama_dokumen
                                        FROM pengajuan_dokumen pd
                                        JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                                        WHERE pd.user_id = ?
                                        ORDER BY pd.tanggal_pengajuan DESC
                                        LIMIT 1";

                            // Use prepared statement to prevent SQL injection
                            $stmt = mysqli_prepare($koneksi, $last_pengajuan_query);
                            mysqli_stmt_bind_param($stmt, "i", $user_id);
                            mysqli_stmt_execute($stmt);
                            $last_pengajuan_result = mysqli_stmt_get_result($stmt);

                            if ($last_pengajuan_result && mysqli_num_rows($last_pengajuan_result) > 0) {
                                $last_pengajuan = mysqli_fetch_assoc($last_pengajuan_result);
                                echo '<div class="last-application">';
                                echo '<p><strong>Dokumen:</strong> ' . htmlspecialchars($last_pengajuan['nama_dokumen']) . '</p>';
                                echo '<p><strong>Nomor:</strong> #' . htmlspecialchars($last_pengajuan['nomor_pengajuan']) . '</p>';
                                echo '<p><strong>Tanggal:</strong> ' . date('d-m-Y', strtotime($last_pengajuan['tanggal_pengajuan'])) . '</p>';

                                // Make sure the getStatusClass and getStatusText functions are available
                                if (function_exists('getStatusClass') && function_exists('getStatusText')) {
                                    echo '<p><strong>Status:</strong> <span class="status ' . getStatusClass($last_pengajuan['status']) . '">' . getStatusText($last_pengajuan['status']) . '</span></p>';
                                } else {
                                    // Fallback if functions are not available
                                    echo '<p><strong>Status:</strong> <span class="status">' . ucfirst(htmlspecialchars($last_pengajuan['status'])) . '</span></p>';
                                }

                                echo '<a href="status-pengajuan.php?id=' . $last_pengajuan['pengajuan_id'] . '" class="btn-small">Lihat Detail</a>';
                                echo '</div>';
                            } else {
                                echo '<p>Anda belum memiliki pengajuan dokumen sebelumnya.</p>';
                                echo '<a href="pengajuan.php" class="btn-small">Buat Pengajuan</a>';
                            }

                            // Close the statement
                            mysqli_stmt_close($stmt);
                        } catch (Exception $e) {
                            // Log the error and show a friendly message
                            error_log("Error fetching last application: " . $e->getMessage());
                            echo '<p>Terjadi kesalahan saat memuat data pengajuan terakhir.</p>';
                            echo '<a href="pengajuan.php" class="btn-small">Buat Pengajuan</a>';
                        }
                    } else {
                        echo '<p>Sesi pengguna tidak tersedia. Silakan login kembali.</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// Helper functions for this page
function getStatusClass($status)
{
    switch ($status) {
        case 'diajukan':
            return 'status-pending';
        case 'verifikasi':
        case 'proses':
            return 'status-processing';
        case 'selesai':
            return 'status-completed';
        case 'ditolak':
            return 'status-rejected';
        default:
            return 'status-pending';
    }
}

function getStatusText($status)
{
    switch ($status) {
        case 'diajukan':
            return 'Menunggu';
        case 'verifikasi':
            return 'Verifikasi';
        case 'proses':
            return 'Diproses';
        case 'selesai':
            return 'Selesai';
        case 'ditolak':
            return 'Ditolak';
        default:
            return 'Menunggu';
    }
}

// Include footer
include '../includes/footer.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Login prompt functionality
        const loginPrompt = document.getElementById('loginPrompt');
        const loginRequiredBtn = document.getElementById('loginRequiredBtn');
        const loginBtn = document.getElementById('loginBtn');

        if (loginPrompt && loginBtn) {
            loginPrompt.addEventListener('click', function(e) {
                e.preventDefault();
                loginBtn.click();
            });
        }

        if (loginRequiredBtn && loginBtn) {
            loginRequiredBtn.addEventListener('click', function() {
                loginBtn.click();
            });
        }

        // Document type change handler
        const jenisDokumen = document.getElementById('jenis_dokumen');
        const persyaratanContainer = document.getElementById('persyaratan-container');
        const persyaratanContent = document.getElementById('persyaratan-content');
        const specificRequirements = document.getElementById('specific-requirements');
        const defaultRequirements = document.getElementById('default-requirements');

        if (jenisDokumen) {
            jenisDokumen.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const selectedValue = this.value;

                if (selectedValue) {
                    // Fetch document requirements
                    fetch('get_dokumen_info.php?jenis_id=' + selectedValue)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status) {
                                // Show persyaratan in the form
                                if (persyaratanContainer && persyaratanContent) {
                                    persyaratanContent.innerHTML = data.persyaratan;
                                    persyaratanContainer.style.display = 'block';
                                }

                                // Show specific requirements in the sidebar
                                if (specificRequirements && defaultRequirements) {
                                    specificRequirements.innerHTML = data.persyaratan;
                                    specificRequirements.style.display = 'block';
                                    defaultRequirements.style.display = 'none';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching document info:', error);
                        });
                } else {
                    // Hide persyaratan if no document type selected
                    if (persyaratanContainer) {
                        persyaratanContainer.style.display = 'none';
                    }

                    // Reset requirements in sidebar
                    if (specificRequirements && defaultRequirements) {
                        specificRequirements.style.display = 'none';
                        defaultRequirements.style.display = 'block';
                    }
                }
            });
        }



        // File upload and display selected files
        const fileInput = document.getElementById('dokumen_pendukung');
        const selectedFiles = document.getElementById('selected-files');

        if (fileInput && selectedFiles) {
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    let fileNames = [];
                    for (let i = 0; i < fileInput.files.length; i++) {
                        fileNames.push(fileInput.files[i].name);
                    }
                    selectedFiles.textContent = fileNames.join(', ');
                } else {
                    selectedFiles.textContent = 'Belum ada file dipilih';
                }
            });
        }

        // Form tab switching
        const formTabs = document.querySelectorAll('.form-tab');

        formTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                formTabs.forEach(t => t.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Display different form content based on selected tab
                // This would typically change the form's content or fields
                // For now, just show an alert
                alert('Switching to tab: ' + this.textContent);
            });
        });

        // Add this to your document.addEventListener('DOMContentLoaded', function() {...}) block
        // or directly in a <script> tag at the end of your form page

        const dokumenForm = document.getElementById('dokumenForm');

        if (dokumenForm) {
            dokumenForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Validate form inputs
                // Check NIK format
                const nikInput = document.getElementById('nik');
                if (nikInput && nikInput.value.length !== 16) {
                    showNotification('error', 'NIK harus terdiri dari 16 digit angka.');
                    nikInput.focus();
                    return false;
                }

                // Check file size
                const fileInput = document.getElementById('dokumen_pendukung');
                if (fileInput && fileInput.files.length > 0) {
                    let totalSize = 0;
                    for (let i = 0; i < fileInput.files.length; i++) {
                        totalSize += fileInput.files[i].size;
                    }

                    // Check if total size exceeds 5MB (5 * 1024 * 1024 bytes)
                    if (totalSize > 5242880) {
                        showNotification('error', 'Ukuran total file tidak boleh melebihi 5MB.');
                        return false;
                    }
                }

                // If validation passes, submit the form via AJAX
                const formData = new FormData(this);

                // Show loading notification
                showNotification('info', 'Sedang memproses pengajuan...');

                fetch('proses_pengajuan.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            // Success
                            showNotification('success', data.message);

                            // After 2 seconds, redirect to the status page
                            setTimeout(function() {
                                window.location.href = 'status-pengajuan.php?id=' + data.pengajuan_id;
                            }, 2000);
                        } else {
                            // Error
                            showNotification('error', data.message);
                        }
                    })
                    .catch(error => {
                        showNotification('error', 'Terjadi kesalahan. Silakan coba lagi.');
                        console.error('Error:', error);
                    });
            });
        }

        // Function to show notification popup
        function showNotification(type, message) {
            // Remove existing notifications
            const existingNotif = document.querySelector('.notification-popup');
            if (existingNotif) {
                existingNotif.remove();
            }

            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification-popup notification-${type}`;

            // Add icon based on notification type
            let icon = '';
            switch (type) {
                case 'success':
                    icon = '✓';
                    break;
                case 'error':
                    icon = '✗';
                    break;
                case 'info':
                    icon = 'ℹ';
                    break;
                default:
                    icon = '!';
            }

            notification.innerHTML = `
        <div class="notification-icon">${icon}</div>
        <div class="notification-message">${message}</div>
        <div class="notification-close">×</div>
    `;

            // Add notification to the DOM
            document.body.appendChild(notification);

            // Show notification with animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Add close button functionality
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });

            // Auto-hide after 5 seconds for success and info notifications
            if (type !== 'error') {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 5000);
            }
        }
    });
</script>