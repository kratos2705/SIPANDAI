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

// Prepare variables for page
$page_title = "Generate Tagihan Retribusi Massal";
$current_page = "retribusi";

// Fetch jenis retribusi for form
$jenis_query = "SELECT jenis_retribusi_id, nama_retribusi, nominal, periode, deskripsi 
                FROM jenis_retribusi 
                WHERE is_active = TRUE 
                ORDER BY nama_retribusi";
$jenis_result = mysqli_query($koneksi, $jenis_query);

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form data
    $jenis_retribusi_id = isset($_POST['jenis_retribusi_id']) ? mysqli_real_escape_string($koneksi, $_POST['jenis_retribusi_id']) : '';
    $tanggal_tagihan = isset($_POST['tanggal_tagihan']) ? mysqli_real_escape_string($koneksi, $_POST['tanggal_tagihan']) : '';
    $jatuh_tempo = isset($_POST['jatuh_tempo']) ? mysqli_real_escape_string($koneksi, $_POST['jatuh_tempo']) : '';
    $target_warga = isset($_POST['target_warga']) ? mysqli_real_escape_string($koneksi, $_POST['target_warga']) : 'all';
    $custom_nominal = isset($_POST['custom_nominal']) && !empty($_POST['custom_nominal']) ?
        mysqli_real_escape_string($koneksi, $_POST['custom_nominal']) : false;
    $send_notification = isset($_POST['send_notification']) ? true : false;

    // Custom filters
    $filter_jenis_kelamin = isset($_POST['filter_jenis_kelamin']) ? mysqli_real_escape_string($koneksi, $_POST['filter_jenis_kelamin']) : '';
    $filter_alamat = isset($_POST['filter_alamat']) ? mysqli_real_escape_string($koneksi, $_POST['filter_alamat']) : '';

    // Validate required fields
    if (empty($jenis_retribusi_id) || empty($tanggal_tagihan) || empty($jatuh_tempo)) {
        $message = 'Semua field wajib diisi!';
        $message_type = 'error';
    } else {
        // Get jenis retribusi info
        $jenis_info_query = "SELECT nama_retribusi, nominal, periode FROM jenis_retribusi WHERE jenis_retribusi_id = '$jenis_retribusi_id'";
        $jenis_info_result = mysqli_query($koneksi, $jenis_info_query);
        $jenis_info = mysqli_fetch_assoc($jenis_info_result);

        // Prepare base user query
        $users_query = "SELECT user_id, nama FROM users WHERE role = 'warga' AND active = TRUE";

        // Add filters if applicable
        if ($target_warga == 'filtered') {
            if (!empty($filter_jenis_kelamin)) {
                $users_query .= " AND jenis_kelamin = '$filter_jenis_kelamin'";
            }
            if (!empty($filter_alamat)) {
                $users_query .= " AND alamat LIKE '%$filter_alamat%'";
            }
        }

        // Execute user query
        $users_result = mysqli_query($koneksi, $users_query);

        // Check if users found
        if (mysqli_num_rows($users_result) > 0) {
            // Nominal to use (either default or custom)
            $nominal = $custom_nominal ? $custom_nominal : $jenis_info['nominal'];

            // Initialize counters
            $tagihan_created = 0;
            $tagihan_skipped = 0;

            // Start transaction
            mysqli_begin_transaction($koneksi);

            try {
                // Generate tagihan for each user
                while ($user = mysqli_fetch_assoc($users_result)) {
                    // Check if tagihan already exists for this user, jenis, and period
                    $check_query = "SELECT COUNT(*) as count FROM tagihan_retribusi 
                                   WHERE user_id = '{$user['user_id']}' 
                                   AND jenis_retribusi_id = '$jenis_retribusi_id' 
                                   AND MONTH(tanggal_tagihan) = MONTH('$tanggal_tagihan') 
                                   AND YEAR(tanggal_tagihan) = YEAR('$tanggal_tagihan')";
                    $check_result = mysqli_query($koneksi, $check_query);
                    $check_data = mysqli_fetch_assoc($check_result);

                    // If tagihan doesn't exist, create it
                    if ($check_data['count'] == 0) {
                        // Generate nomor pengajuan
                        $nomor_tagihan = 'TRB-' . date('Ymd') . '-' . sprintf('%04d', $user['user_id']) . '-' . rand(1000, 9999);

                        // Insert tagihan
                        $insert_query = "INSERT INTO tagihan_retribusi (user_id, jenis_retribusi_id, tanggal_tagihan, jatuh_tempo, nominal, status) 
                                        VALUES ('{$user['user_id']}', '$jenis_retribusi_id', '$tanggal_tagihan', '$jatuh_tempo', '$nominal', 'belum_bayar')";
                        $insert_result = mysqli_query($koneksi, $insert_query);

                        if ($insert_result) {
                            $tagihan_id = mysqli_insert_id($koneksi);
                            $tagihan_created++;

                            // Send notification if requested
                            if ($send_notification) {
                                $notif_judul = "Tagihan Retribusi Baru";
                                $notif_pesan = "Tagihan {$jenis_info['nama_retribusi']} periode " . date('F Y', strtotime($tanggal_tagihan)) . " telah dibuat. Silakan lakukan pembayaran sebelum tanggal " . date('d-m-Y', strtotime($jatuh_tempo)) . ".";
                                $notif_link = "../warga/tagihan_detail.php?id=$tagihan_id";

                                $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                                              VALUES ('{$user['user_id']}', '$notif_judul', '$notif_pesan', 'tagihan', '$notif_link')";
                                mysqli_query($koneksi, $notif_query);
                            }
                        }
                    } else {
                        $tagihan_skipped++;
                    }
                }

                // Commit transaction
                mysqli_commit($koneksi);

                // Set success message
                $message = "Berhasil membuat $tagihan_created tagihan baru. $tagihan_skipped tagihan dilewati karena sudah ada.";
                $message_type = 'success';
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($koneksi);
                $message = 'Terjadi kesalahan: ' . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = 'Tidak ada warga yang sesuai dengan kriteria.';
            $message_type = 'warning';
        }
    }
}

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Generate Tagihan Retribusi Massal</h2>
        <div class="admin-header-actions">
            <a href="retribusi.php" class="btn btn-secondary">Kembali ke Daftar Tagihan</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="data-card">
        <div class="card-header">
            <h3>Form Generate Tagihan Massal</h3>
        </div>
        <div class="card-body">
            <form action="" method="POST" class="form">
                <div class="form-group">
                    <label for="jenis_retribusi_id">Jenis Retribusi:</label>
                    <select name="jenis_retribusi_id" id="jenis_retribusi_id" class="form-control" required>
                        <option value="">-- Pilih Jenis Retribusi --</option>
                        <?php
                        if (mysqli_num_rows($jenis_result) > 0) {
                            while ($jenis = mysqli_fetch_assoc($jenis_result)) {
                                echo '<option value="' . $jenis['jenis_retribusi_id'] . '" data-nominal="' . $jenis['nominal'] . '" data-periode="' . $jenis['periode'] . '">'
                                    . htmlspecialchars($jenis['nama_retribusi'])
                                    . ' - Rp ' . number_format($jenis['nominal'], 0, ',', '.')
                                    . ' (' . ucfirst($jenis['periode']) . ')'
                                    . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group form-group-half">
                        <label for="tanggal_tagihan">Tanggal Tagihan:</label>
                        <input type="date" name="tanggal_tagihan" id="tanggal_tagihan" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group form-group-half">
                        <label for="jatuh_tempo">Jatuh Tempo:</label>
                        <input type="date" name="jatuh_tempo" id="jatuh_tempo" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="custom_nominal">Nominal (Opsional):</label>
                    <div class="input-group">
                        <span class="input-prefix">Rp</span>
                        <input type="number" name="custom_nominal" id="custom_nominal" class="form-control" placeholder="Kosongkan untuk menggunakan nominal default">
                    </div>
                    <small class="form-text">Jika diisi, nilai ini akan menggantikan nominal default jenis retribusi.</small>
                </div>

                <div class="form-group">
                    <label>Target Warga:</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="target_warga" id="target_all" value="all" checked>
                            <label for="target_all">Semua Warga</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="target_warga" id="target_filtered" value="filtered">
                            <label for="target_filtered">Filter Warga</label>
                        </div>
                    </div>
                </div>

                <div id="filter_options" class="form-section" style="display: none;">
                    <h4>Filter Warga</h4>
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="filter_jenis_kelamin">Jenis Kelamin:</label>
                            <select name="filter_jenis_kelamin" id="filter_jenis_kelamin" class="form-control">
                                <option value="">Semua</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="filter_alamat">Alamat Mengandung:</label>
                            <input type="text" name="filter_alamat" id="filter_alamat" class="form-control" placeholder="Cth: RT 001">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="send_notification" id="send_notification" checked>
                        <label for="send_notification">Kirim notifikasi ke warga</label>
                    </div>
                </div>

                <div class="alert alert-info">
                    <p><strong>Catatan:</strong></p>
                    <ul>
                        <li>Proses ini akan membuat tagihan untuk semua warga yang memenuhi kriteria.</li>
                        <li>Warga yang sudah memiliki tagihan dengan jenis dan periode yang sama akan dilewati.</li>
                        <li>Estimasi jumlah tagihan yang akan dibuat: <span id="estimation_count">-</span></li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn" id="submitBtn">Generate Tagihan</button>
                    <a href="retribusi.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="data-card">
        <div class="card-header">
            <h3>Informasi Generate Tagihan</h3>
        </div>
        <div class="card-body">
            <h4>Cara Kerja Generate Tagihan</h4>
            <ol>
                <li>Pilih jenis retribusi dari daftar</li>
                <li>Tentukan tanggal tagihan dan jatuh tempo</li>
                <li>Isi nominal khusus jika ingin berbeda dari nominal default</li>
                <li>Pilih target warga: semua atau berdasarkan filter</li>
                <li>Aktifkan notifikasi jika ingin memberitahu warga</li>
                <li>Klik tombol Generate Tagihan untuk memproses</li>
            </ol>

            <h4>Periode Tagihan</h4>
            <p>Tagihan akan dibuat sesuai dengan periode jenis retribusi:</p>
            <ul>
                <li><strong>Bulanan:</strong> Tagihan dibuat per bulan</li>
                <li><strong>Tahunan:</strong> Tagihan dibuat per tahun</li>
                <li><strong>Insidentil:</strong> Tagihan dibuat sesuai kebutuhan</li>
            </ul>

            <h4>Denda Keterlambatan</h4>
            <p>Denda keterlambatan dapat dihitung dan diterapkan nanti melalui menu "Hitung Denda" di halaman Manajemen Retribusi.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle target warga radio buttons
        const targetAllRadio = document.getElementById('target_all');
        const targetFilteredRadio = document.getElementById('target_filtered');
        const filterOptions = document.getElementById('filter_options');

        targetAllRadio.addEventListener('change', function() {
            if (this.checked) {
                filterOptions.style.display = 'none';
            }
        });

        targetFilteredRadio.addEventListener('change', function() {
            if (this.checked) {
                filterOptions.style.display = 'block';
            }
        });

        // Handle jenis retribusi selection
        const jenisRetribusiSelect = document.getElementById('jenis_retribusi_id');
        const customNominalInput = document.getElementById('custom_nominal');

        jenisRetribusiSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const nominal = selectedOption.getAttribute('data-nominal');
                customNominalInput.placeholder = 'Nominal default: Rp ' + formatNumber(nominal);
                updateEstimation();
            }
        });

        // Update estimation count
        async function updateEstimation() {
            const jenisRetribusiId = jenisRetribusiSelect.value;
            const targetWarga = document.querySelector('input[name="target_warga"]:checked').value;
            const filterJenisKelamin = document.getElementById('filter_jenis_kelamin').value;
            const filterAlamat = document.getElementById('filter_alamat').value;

            if (!jenisRetribusiId) {
                document.getElementById('estimation_count').textContent = '-';
                return;
            }

            try {
                const response = await fetch('../api/get_estimation_count.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        jenis_retribusi_id: jenisRetribusiId,
                        target_warga: targetWarga,
                        filter_jenis_kelamin: filterJenisKelamin,
                        filter_alamat: filterAlamat
                    })
                });

                const data = await response.json();
                if (data.success) {
                    document.getElementById('estimation_count').textContent = data.count + ' tagihan';
                } else {
                    document.getElementById('estimation_count').textContent = 'Error: ' + data.message;
                }
            } catch (error) {
                document.getElementById('estimation_count').textContent = 'Error fetching data';
            }
        }

        // Format number with thousand separator
        function formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }

        // Add event listeners for filter changes to update estimation
        document.getElementById('filter_jenis_kelamin').addEventListener('change', updateEstimation);
        document.getElementById('filter_alamat').addEventListener('input', updateEstimation);
        targetAllRadio.addEventListener('change', updateEstimation);
        targetFilteredRadio.addEventListener('change', updateEstimation);

        // Jatuh tempo calculation
        const tanggalTagihanInput = document.getElementById('tanggal_tagihan');
        const jatuhTempoInput = document.getElementById('jatuh_tempo');

        tanggalTagihanInput.addEventListener('change', function() {
            const tanggalTagihan = new Date(this.value);
            tanggalTagihan.setDate(tanggalTagihan.getDate() + 30); // Default: tanggal tagihan + 30 hari

            // Format date to YYYY-MM-DD
            const year = tanggalTagihan.getFullYear();
            const month = String(tanggalTagihan.getMonth() + 1).padStart(2, '0');
            const day = String(tanggalTagihan.getDate()).padStart(2, '0');
            jatuhTempoInput.value = `${year}-${month}-${day}`;
        });

        // Form submission confirmation
        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', function(event) {
            const jenis = jenisRetribusiSelect.options[jenisRetribusiSelect.selectedIndex].text;
            if (!confirm(`Anda akan membuat tagihan massal untuk ${jenis}. Lanjutkan?`)) {
                event.preventDefault();
            } else {
                // Disable submit button to prevent double submission
                submitBtn.disabled = true;
                submitBtn.textContent = 'Memproses...';
            }
        });

        // Initialize estimation count
        if (jenisRetribusiSelect.value) {
            updateEstimation();
        }
    });
</script>
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

    /* Data Card Styling */
    .data-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .card-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .card-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.4rem;
        font-weight: 600;
    }

    .card-body {
        padding: 20px;
    }

    /* Form Styling */
    .form {
        width: 100%;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #333;
    }

    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        border-color: #4285f4;
        outline: none;
        box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.25);
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none'%3E%3Cpath stroke='%23666' stroke-linecap='round' stroke-width='1.5' d='m1 1 4 4 4-4'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 30px;
    }

    /* Form Layout */
    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group-half {
        flex: 1;
    }

    /* Input Group Styling */
    .input-group {
        display: flex;
        align-items: stretch;
    }

    .input-prefix {
        display: flex;
        align-items: center;
        padding: 0 12px;
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-right: none;
        border-radius: 4px 0 0 4px;
        color: #495057;
        font-weight: 500;
    }

    .input-group .form-control {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .form-text {
        display: block;
        margin-top: 5px;
        font-size: 0.85rem;
        color: #6c757d;
    }

    /* Radio & Checkbox Styling */
    .radio-group,
    .checkbox-group {
        display: flex;
        gap: 15px;
    }

    .radio-option {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .radio-group input[type="radio"],
    .checkbox-group input[type="checkbox"] {
        margin-right: 5px;
        cursor: pointer;
    }

    .radio-group label,
    .checkbox-group label {
        cursor: pointer;
        margin-bottom: 0;
        font-weight: normal;
    }

    /* Form Section Styling */
    .form-section {
        margin-top: 10px;
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 6px;
        border-left: 3px solid #4285f4;
    }

    .form-section h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
        font-size: 1.1rem;
    }

    /* Button Styling */
    .btn {
        display: inline-block;
        padding: 10px 16px;
        background-color: #4285f4;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.2s;
    }

    .btn:hover {
        background-color: #3367d6;
    }

    .btn:active {
        background-color: #2a56c6;
    }

    .btn:disabled {
        background-color: #a4c2f4;
        cursor: not-allowed;
    }

    .btn-secondary {
        background-color: #6c757d;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .btn-secondary:active {
        background-color: #545b62;
    }

    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }

    /* Alert Styling */
    .alert {
        padding: 12px 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        border-left: 4px solid;
    }

    .alert-success {
        background-color: #d4edda;
        border-color: #28a745;
        color: #155724;
    }

    .alert-error,
    .alert-danger {
        background-color: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }

    .alert-warning {
        background-color: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }

    .alert-info {
        background-color: #d1ecf1;
        border-color: #17a2b8;
        color: #0c5460;
    }

    .alert ul {
        margin-top: 8px;
        margin-bottom: 0;
        padding-left: 20px;
    }

    /* Information Section */
    .data-card h4 {
        color: #2c3e50;
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 1.2rem;
    }

    .data-card ol,
    .data-card ul {
        padding-left: 20px;
        margin-bottom: 15px;
    }

    .data-card li {
        margin-bottom: 5px;
    }

    .data-card p {
        margin-bottom: 15px;
        line-height: 1.5;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .admin-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .admin-header-actions {
            margin-top: 10px;
        }

        .radio-group {
            flex-direction: column;
            gap: 8px;
        }
    }

    /* Estimation Count Styling */
    #estimation_count {
        font-weight: 600;
        color: #4285f4;
    }

    /* Animation for Submit Button */
    @keyframes pulse {
        0% {
            opacity: 1;
        }

        50% {
            opacity: 0.7;
        }

        100% {
            opacity: 1;
        }
    }

    button[disabled] {
        animation: pulse 1.5s infinite;
    }
</style>
<?php
// Include footer
include '../includes/admin-footer.php';
?>