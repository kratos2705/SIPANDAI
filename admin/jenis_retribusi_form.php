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

// Initialize variables
$jenis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = $jenis_id > 0;
$errors = [];
$success_message = '';

// Default values
$jenis_retribusi = [
    'nama_retribusi' => '',
    'deskripsi' => '',
    'nominal' => '',
    'periode' => 'bulanan',
    'is_active' => 1
];

// If editing, fetch existing data
if ($is_edit) {
    $query = "SELECT * FROM jenis_retribusi WHERE jenis_retribusi_id = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, 'i', $jenis_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $jenis_retribusi = mysqli_fetch_assoc($result);
    } else {
        $_SESSION['error_message'] = 'Jenis retribusi tidak ditemukan.';
        redirect('jenis_retribusi.php');
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $nama_retribusi = trim($_POST['nama_retribusi']);
    $deskripsi = trim($_POST['deskripsi']);
    $nominal = str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['nominal']));
    $periode = $_POST['periode'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($nama_retribusi)) {
        $errors[] = 'Nama retribusi tidak boleh kosong.';
    }

    if (!is_numeric($nominal) || $nominal <= 0) {
        $errors[] = 'Nominal harus berupa angka positif.';
    }

    if (!in_array($periode, ['bulanan', 'tahunan', 'insidentil'])) {
        $errors[] = 'Periode tidak valid.';
    }

    // If no errors, process the form
    if (empty($errors)) {
        if ($is_edit) {
            // Update existing record
            $query = "UPDATE jenis_retribusi SET 
                      nama_retribusi = ?, 
                      deskripsi = ?, 
                      nominal = ?, 
                      periode = ?, 
                      is_active = ? 
                      WHERE jenis_retribusi_id = ?";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, 'ssdsii', $nama_retribusi, $deskripsi, $nominal, $periode, $is_active, $jenis_id);
        } else {
            // Insert new record
            $query = "INSERT INTO jenis_retribusi (nama_retribusi, deskripsi, nominal, periode, is_active) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, 'ssdsi', $nama_retribusi, $deskripsi, $nominal, $periode, $is_active);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success_message = $is_edit ? 'Jenis retribusi berhasil diperbarui.' : 'Jenis retribusi baru berhasil ditambahkan.';

            // Log the activity
            $activity = $is_edit ? 'Mengubah jenis retribusi: ' . $nama_retribusi : 'Menambahkan jenis retribusi baru: ' . $nama_retribusi;
            $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($koneksi, $query_log);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($stmt_log, 'iss', $user_id, $activity, $ip_address);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);

            if (!$is_edit) {
                // Clear form after successful insert
                $jenis_retribusi = [
                    'nama_retribusi' => '',
                    'deskripsi' => '',
                    'nominal' => '',
                    'periode' => 'bulanan',
                    'is_active' => 1
                ];
            } else {
                // Reload data after successful update
                $query = "SELECT * FROM jenis_retribusi WHERE jenis_retribusi_id = ?";
                $stmt_reload = mysqli_prepare($koneksi, $query);
                mysqli_stmt_bind_param($stmt_reload, 'i', $jenis_id);
                mysqli_stmt_execute($stmt_reload);
                $result = mysqli_stmt_get_result($stmt_reload);
                $jenis_retribusi = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt_reload);
            }
        } else {
            $errors[] = 'Terjadi kesalahan: ' . mysqli_error($koneksi);
        }

        mysqli_stmt_close($stmt);
    }
}

// Get usage statistics for the retribution type (for edit mode)
$retribusi_stats = [];
if ($is_edit) {
    // Count the total tags using this retribution type
    $query_stats = "SELECT 
                    COUNT(*) as total_tagihan,
                    SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END) as total_lunas,
                    SUM(nominal) as total_nominal,
                    SUM(CASE WHEN status = 'lunas' THEN nominal ELSE 0 END) as total_terbayar
                    FROM tagihan_retribusi
                    WHERE jenis_retribusi_id = ?";
    $stmt_stats = mysqli_prepare($koneksi, $query_stats);
    mysqli_stmt_bind_param($stmt_stats, 'i', $jenis_id);
    mysqli_stmt_execute($stmt_stats);
    $result_stats = mysqli_stmt_get_result($stmt_stats);
    $retribusi_stats = mysqli_fetch_assoc($result_stats);
    mysqli_stmt_close($stmt_stats);
}

// Prepare variables for page
$page_title = $is_edit ? "Edit Jenis Retribusi" : "Tambah Jenis Retribusi Baru";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2><?php echo $page_title; ?></h2>
        <div class="admin-header-actions">
            <a href="jenis_retribusi.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <strong>Sukses!</strong> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form class="admin-form" method="POST" action="">
            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nama_retribusi">Nama Retribusi <span class="required">*</span></label>
                        <input type="text" id="nama_retribusi" name="nama_retribusi" class="form-control" value="<?php echo htmlspecialchars($jenis_retribusi['nama_retribusi']); ?>" required>
                        <small class="form-text">Contoh: Retribusi Sampah, Iuran Keamanan, dll.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nominal">Nominal <span class="required">*</span></label>
                        <input type="text" id="nominal" name="nominal" class="form-control currency-input" value="<?php echo $jenis_retribusi['nominal'] ? number_format($jenis_retribusi['nominal'], 0, ',', '.') : ''; ?>" required>
                        <small class="form-text">Nominal default untuk jenis retribusi ini.</small>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="periode">Periode Tagihan <span class="required">*</span></label>
                        <select id="periode" name="periode" class="form-control" required>
                            <option value="bulanan" <?php echo $jenis_retribusi['periode'] == 'bulanan' ? 'selected' : ''; ?>>Bulanan</option>
                            <option value="tahunan" <?php echo $jenis_retribusi['periode'] == 'tahunan' ? 'selected' : ''; ?>>Tahunan</option>
                            <option value="insidentil" <?php echo $jenis_retribusi['periode'] == 'insidentil' ? 'selected' : ''; ?>>Insidentil (Tidak Berkala)</option>
                        </select>
                        <small class="form-text">Tentukan periode pembayaran retribusi ini.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="is_active">Status</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" <?php echo $jenis_retribusi['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">Aktif</label>
                        </div>
                        <small class="form-text">Hanya jenis retribusi yang aktif yang dapat digunakan untuk membuat tagihan baru.</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="4"><?php echo htmlspecialchars($jenis_retribusi['deskripsi']); ?></textarea>
                <small class="form-text">Jelaskan tentang retribusi ini, termasuk tujuan dan penggunaannya.</small>
            </div>

            <?php if ($is_edit && isset($retribusi_stats)): ?>
                <div class="usage-stats">
                    <div class="usage-stat">
                        <div class="stat-label">Total Tagihan</div>
                        <div class="stat-value"><?php echo number_format($retribusi_stats['total_tagihan'], 0, ',', '.'); ?></div>
                    </div>
                    <div class="usage-stat">
                        <div class="stat-label">Sudah Lunas</div>
                        <div class="stat-value"><?php echo number_format($retribusi_stats['total_lunas'], 0, ',', '.'); ?></div>
                    </div>
                    <div class="usage-stat">
                        <div class="stat-label">Total Nominal</div>
                        <div class="stat-value">Rp <?php echo number_format($retribusi_stats['total_nominal'], 0, ',', '.'); ?></div>
                    </div>
                    <div class="usage-stat">
                        <div class="stat-label">Total Terbayar</div>
                        <div class="stat-value">Rp <?php echo number_format($retribusi_stats['total_terbayar'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <a href="jenis_retribusi.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn"><?php echo $is_edit ? 'Perbarui' : 'Simpan'; ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Format currency inputs
        const currencyInputs = document.querySelectorAll('.currency-input');
        currencyInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = this.value.replace(/[^\d]/g, '');
                if (value != '') {
                    value = parseInt(value, 10).toLocaleString('id-ID');
                    this.value = value;
                }
            });
        });

        // Auto-hide alert messages after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        if (alerts.length > 0) {
            setTimeout(function() {
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 5000);
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

    /* Form Styles */
    .form-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .admin-form {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .form-group {
        margin-bottom: 0.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #333;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        border: 1px solid #d1d3e2;
        border-radius: 4px;
        color: #6e707e;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        border-color: #4e73df;
        outline: none;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    .form-text {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #6c757d;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .col-md-6 {
        flex: 1;
        min-width: 250px;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .required {
        color: #e74a3b;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
    }

    /* Alert Messages */
    .alert {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: opacity 0.5s;
    }

    .alert ul {
        margin: 0.5rem 0 0 1.5rem;
        padding: 0;
    }

    .alert-success {
        background-color: #d4edda;
        border-left: 4px solid #1cc88a;
        color: #155724;
    }

    .alert-danger {
        background-color: #f8d7da;
        border-left: 4px solid #e74a3b;
        color: #721c24;
    }

    /* Usage Stats */
    .usage-stats {
        display: flex;
        gap: 1.5rem;
        margin: 1rem 0;
        flex-wrap: wrap;
    }

    .usage-stat {
        background-color: #f8f9fc;
        border-radius: 8px;
        padding: 1rem;
        flex: 1;
        min-width: 150px;
        text-align: center;
    }

    .stat-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #5a5c69;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #4e73df;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }

        .col-md-6 {
            width: 100%;
        }

        .usage-stats {
            flex-direction: column;
        }

        .usage-stat {
            width: 100%;
        }
    }
</style>

<?php
// Include footer
include '../includes/admin-footer.php';
?>