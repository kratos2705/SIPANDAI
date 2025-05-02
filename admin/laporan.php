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

// Initialize variables for filtering
$jenis_laporan = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';

// Handle report generation
if (isset($_POST['generate_report'])) {
    $jenis = mysqli_real_escape_string($koneksi, $_POST['jenis_laporan']);
    $judul = mysqli_real_escape_string($koneksi, $_POST['judul']);
    $periode_awal = mysqli_real_escape_string($koneksi, $_POST['periode_awal']);
    $periode_akhir = mysqli_real_escape_string($koneksi, $_POST['periode_akhir']);
    $format = mysqli_real_escape_string($koneksi, $_POST['format']);
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
    
    // Generate report file path (in real implementation, this would create the actual report file)
    $file_name = 'laporan_' . $jenis . '_' . date('YmdHis') . '.' . strtolower($format);
    $path_file = 'reports/' . $file_name;
    
    // Insert into laporan_administrasi table
    $insert_query = "INSERT INTO laporan_administrasi (jenis_laporan, judul, periode_awal, periode_akhir, format, path_file, created_by, catatan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($koneksi, $insert_query);
    mysqli_stmt_bind_param($stmt, "ssssssss", $jenis, $judul, $periode_awal, $periode_akhir, $format, $path_file, $user_id, $catatan);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = 'Laporan berhasil dibuat.';
    } else {
        $_SESSION['error_message'] = 'Gagal membuat laporan: ' . mysqli_error($koneksi);
    }
    
    // Redirect to avoid form resubmission
    header("Location: laporan.php");
    exit();
}

// Handle report deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $laporan_id = intval($_GET['id']);
    
    // Get file path first
    $file_query = "SELECT path_file FROM laporan_administrasi WHERE laporan_id = ?";
    $stmt = mysqli_prepare($koneksi, $file_query);
    mysqli_stmt_bind_param($stmt, "i", $laporan_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $file_path = '../uploads/' . $row['path_file'];
        
        // Delete physical file if exists
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete record from database
    $delete_query = "DELETE FROM laporan_administrasi WHERE laporan_id = ?";
    $stmt = mysqli_prepare($koneksi, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $laporan_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = 'Laporan berhasil dihapus.';
    } else {
        $_SESSION['error_message'] = 'Gagal menghapus laporan: ' . mysqli_error($koneksi);
    }
    
    // Redirect to avoid form resubmission
    header("Location: laporan.php");
    exit();
}

// Build the query based on filters
$query = "SELECT l.*, u.nama AS created_by_name
          FROM laporan_administrasi l
          JOIN users u ON l.created_by = u.user_id
          WHERE 1=1";

if (!empty($jenis_laporan)) {
    $query .= " AND l.jenis_laporan = '" . mysqli_real_escape_string($koneksi, $jenis_laporan) . "'";
}

if (!empty($tahun)) {
    $query .= " AND YEAR(l.periode_awal) = '" . mysqli_real_escape_string($koneksi, $tahun) . "'";
}

if (!empty($bulan)) {
    $query .= " AND MONTH(l.periode_awal) = '" . mysqli_real_escape_string($koneksi, $bulan) . "'";
}

$query .= " ORDER BY l.created_at DESC";
$result = mysqli_query($koneksi, $query);

// Get stats
$total_reports_query = "SELECT COUNT(*) as total FROM laporan_administrasi";
$total_reports_result = mysqli_query($koneksi, $total_reports_query);
$total_reports = mysqli_fetch_assoc($total_reports_result)['total'];

$layanan_reports_query = "SELECT COUNT(*) as total FROM laporan_administrasi WHERE jenis_laporan = 'layanan'";
$layanan_reports_result = mysqli_query($koneksi, $layanan_reports_query);
$layanan_reports = mysqli_fetch_assoc($layanan_reports_result)['total'];

$keuangan_reports_query = "SELECT COUNT(*) as total FROM laporan_administrasi WHERE jenis_laporan = 'keuangan'";
$keuangan_reports_result = mysqli_query($koneksi, $keuangan_reports_query);
$keuangan_reports = mysqli_fetch_assoc($keuangan_reports_result)['total'];

$performa_reports_query = "SELECT COUNT(*) as total FROM laporan_administrasi WHERE jenis_laporan = 'performa'";
$performa_reports_result = mysqli_query($koneksi, $performa_reports_query);
$performa_reports = mysqli_fetch_assoc($performa_reports_result)['total'];

$demografi_reports_query = "SELECT COUNT(*) as total FROM laporan_administrasi WHERE jenis_laporan = 'demografi'";
$demografi_reports_result = mysqli_query($koneksi, $demografi_reports_query);
$demografi_reports = mysqli_fetch_assoc($demografi_reports_result)['total'];

$kegiatan_reports_query = "SELECT COUNT(*) as total FROM laporan_administrasi WHERE jenis_laporan = 'kegiatan'";
$kegiatan_reports_result = mysqli_query($koneksi, $kegiatan_reports_query);
$kegiatan_reports = mysqli_fetch_assoc($kegiatan_reports_result)['total'];

// Get years for filter
$years_query = "SELECT DISTINCT YEAR(periode_awal) as year FROM laporan_administrasi ORDER BY year DESC";
$years_result = mysqli_query($koneksi, $years_query);

// Prepare variables for page
$page_title = "Manajemen Laporan";
$current_page = "laporan";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Manajemen Laporan</h2>
        <p>Kelola laporan administrasi desa</p>
    </div>

    <!-- Display success or error messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats-card primary">
                <div class="stats-icon">📄</div>
                <div class="stats-info">
                    <h3>Total Laporan</h3>
                    <p class="stats-value"><?php echo $total_reports; ?></p>
                </div>
            </div>
            <div class="stats-card info">
                <div class="stats-icon">🏢</div>
                <div class="stats-info">
                    <h3>Laporan Layanan</h3>
                    <p class="stats-value"><?php echo $layanan_reports; ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">💰</div>
                <div class="stats-info">
                    <h3>Laporan Keuangan</h3>
                    <p class="stats-value"><?php echo $keuangan_reports; ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">📊</div>
                <div class="stats-info">
                    <h3>Laporan Lainnya</h3>
                    <p class="stats-value"><?php echo $performa_reports + $demografi_reports + $kegiatan_reports; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <!-- Filter Section -->
        <div class="filter-section">
            <form action="" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="jenis">Jenis Laporan</label>
                    <select name="jenis" id="jenis" class="form-control">
                        <option value="">Semua Jenis</option>
                        <option value="layanan" <?php echo ($jenis_laporan == 'layanan') ? 'selected' : ''; ?>>Layanan</option>
                        <option value="performa" <?php echo ($jenis_laporan == 'performa') ? 'selected' : ''; ?>>Performa</option>
                        <option value="keuangan" <?php echo ($jenis_laporan == 'keuangan') ? 'selected' : ''; ?>>Keuangan</option>
                        <option value="demografi" <?php echo ($jenis_laporan == 'demografi') ? 'selected' : ''; ?>>Demografi</option>
                        <option value="kegiatan" <?php echo ($jenis_laporan == 'kegiatan') ? 'selected' : ''; ?>>Kegiatan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tahun">Tahun</label>
                    <select name="tahun" id="tahun" class="form-control">
                        <option value="">Semua Tahun</option>
                        <?php 
                        $current_year = date('Y');
                        for ($i = $current_year; $i >= $current_year - 5; $i--) {
                            echo '<option value="' . $i . '" ' . ($tahun == $i ? 'selected' : '') . '>' . $i . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="bulan">Bulan</label>
                    <select name="bulan" id="bulan" class="form-control">
                        <option value="">Semua Bulan</option>
                        <?php 
                        $bulan_array = [
                            '1' => 'Januari',
                            '2' => 'Februari',
                            '3' => 'Maret',
                            '4' => 'April',
                            '5' => 'Mei',
                            '6' => 'Juni',
                            '7' => 'Juli',
                            '8' => 'Agustus',
                            '9' => 'September',
                            '10' => 'Oktober',
                            '11' => 'November',
                            '12' => 'Desember'
                        ];
                        foreach ($bulan_array as $key => $value) {
                            echo '<option value="' . $key . '" ' . ($bulan == $key ? 'selected' : '') . '>' . $value . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Filter</button>
                    <a href="laporan.php" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn" id="btn-create-report">Buat Laporan Baru</button>
        </div>

        <!-- Data Table -->
        <div class="data-card">
            <div class="card-header">
                <h3>Daftar Laporan Administrasi</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Judul</th>
                            <th>Jenis Laporan</th>
                            <th>Periode</th>
                            <th>Format</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                // Format dates
                                $periode_awal = date('d-m-Y', strtotime($row['periode_awal']));
                                $periode_akhir = date('d-m-Y', strtotime($row['periode_akhir']));
                                $created_at = date('d-m-Y H:i', strtotime($row['created_at']));
                                
                                // Format jenis laporan
                                $jenis_text = '';
                                switch ($row['jenis_laporan']) {
                                    case 'layanan':
                                        $jenis_text = 'Layanan';
                                        break;
                                    case 'performa':
                                        $jenis_text = 'Performa';
                                        break;
                                    case 'keuangan':
                                        $jenis_text = 'Keuangan';
                                        break;
                                    case 'demografi':
                                        $jenis_text = 'Demografi';
                                        break;
                                    case 'kegiatan':
                                        $jenis_text = 'Kegiatan';
                                        break;
                                    default:
                                        $jenis_text = ucfirst($row['jenis_laporan']);
                                }
                                
                                echo '<tr>';
                                echo '<td>' . $row['laporan_id'] . '</td>';
                                echo '<td>' . htmlspecialchars($row['judul']) . '</td>';
                                echo '<td>' . $jenis_text . '</td>';
                                echo '<td>' . $periode_awal . ' s/d ' . $periode_akhir . '</td>';
                                echo '<td>' . strtoupper($row['format']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['created_by_name']) . '</td>';
                                echo '<td>' . $created_at . '</td>';
                                echo '<td class="action-column">';
                                echo '<a href="../uploads/' . $row['path_file'] . '" class="btn-sm" target="_blank">Unduh</a>';
                                echo '<a href="#" class="btn-sm btn-edit" data-id="' . $row['laporan_id'] . '">Detail</a>';
                                echo '<a href="laporan.php?action=delete&id=' . $row['laporan_id'] . '" class="btn-sm btn-delete" onclick="return confirm(\'Yakin ingin menghapus laporan ini?\')">Hapus</a>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Tidak ada data laporan</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Create Report -->
<div id="report-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Buat Laporan Baru</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="jenis_laporan">Jenis Laporan *</label>
                <select name="jenis_laporan" id="jenis_laporan" class="form-control" required>
                    <option value="">Pilih Jenis Laporan</option>
                    <option value="layanan">Laporan Layanan</option>
                    <option value="performa">Laporan Performa</option>
                    <option value="keuangan">Laporan Keuangan</option>
                    <option value="demografi">Laporan Demografi</option>
                    <option value="kegiatan">Laporan Kegiatan</option>
                </select>
            </div>
            <div class="form-group">
                <label for="judul">Judul Laporan *</label>
                <input type="text" name="judul" id="judul" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group half">
                    <label for="periode_awal">Periode Awal *</label>
                    <input type="date" name="periode_awal" id="periode_awal" class="form-control" required>
                </div>
                <div class="form-group half">
                    <label for="periode_akhir">Periode Akhir *</label>
                    <input type="date" name="periode_akhir" id="periode_akhir" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label for="format">Format File *</label>
                <select name="format" id="format" class="form-control" required>
                    <option value="PDF">PDF</option>
                    <option value="XLSX">Excel (XLSX)</option>
                    <option value="DOCX">Word (DOCX)</option>
                    <option value="CSV">CSV</option>
                </select>
            </div>
            <div class="form-group">
                <label for="catatan">Catatan</label>
                <textarea name="catatan" id="catatan" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="generate_report" class="btn">Generate Laporan</button>
                <button type="button" class="btn btn-light cancel-btn">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality
    var modal = document.getElementById('report-modal');
    var btnCreate = document.getElementById('btn-create-report');
    var span = document.getElementsByClassName('close')[0];
    var cancelBtn = document.querySelector('.cancel-btn');

    btnCreate.onclick = function() {
        modal.style.display = "block";
    }

    span.onclick = function() {
        modal.style.display = "none";
    }

    cancelBtn.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Form validation for period
    var periodeAwal = document.getElementById('periode_awal');
    var periodeAkhir = document.getElementById('periode_akhir');

    periodeAkhir.addEventListener('change', function() {
        if (periodeAwal.value && periodeAkhir.value) {
            if (new Date(periodeAkhir.value) < new Date(periodeAwal.value)) {
                alert('Periode akhir tidak boleh lebih awal dari periode awal!');
                periodeAkhir.value = periodeAwal.value;
            }
        }
    });

    periodeAwal.addEventListener('change', function() {
        if (periodeAwal.value && periodeAkhir.value) {
            if (new Date(periodeAkhir.value) < new Date(periodeAwal.value)) {
                alert('Periode awal tidak boleh lebih akhir dari periode akhir!');
                periodeAwal.value = periodeAkhir.value;
            }
        }
    });

    // Set default dates
    var today = new Date();
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

    var formatDate = function(date) {
        var d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    };

    periodeAwal.value = formatDate(firstDay);
    periodeAkhir.value = formatDate(lastDay);

    // Auto-fill title based on report type
    var jenisLaporan = document.getElementById('jenis_laporan');
    var judulInput = document.getElementById('judul');

    jenisLaporan.addEventListener('change', function() {
        var jenis = this.value;
        var month = today.toLocaleString('id-ID', { month: 'long' });
        var year = today.getFullYear();
        
        if (jenis) {
            var judulText = '';
            switch (jenis) {
                case 'layanan':
                    judulText = 'Laporan Pelayanan Administrasi Desa ' + month + ' ' + year;
                    break;
                case 'performa':
                    judulText = 'Laporan Performa Pelayanan Desa ' + month + ' ' + year;
                    break;
                case 'keuangan':
                    judulText = 'Laporan Keuangan Desa ' + month + ' ' + year;
                    break;
                case 'demografi':
                    judulText = 'Laporan Demografi Penduduk Desa ' + month + ' ' + year;
                    break;
                case 'kegiatan':
                    judulText = 'Laporan Kegiatan Desa ' + month + ' ' + year;
                    break;
            }
            judulInput.value = judulText;
        }
    });

    // Auto-select format based on report type
    jenisLaporan.addEventListener('change', function() {
        var jenis = this.value;
        var formatSelect = document.getElementById('format');
        
        if (jenis) {
            switch (jenis) {
                case 'keuangan':
                case 'demografi':
                    formatSelect.value = 'XLSX';
                    break;
                case 'layanan':
                case 'performa':
                case 'kegiatan':
                    formatSelect.value = 'PDF';
                    break;
            }
        }
    });
});
</script>

<style>
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 700px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
}

.filter-section {
    background-color: #f9fafb;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-form .form-group {
    flex: 1;
    min-width: 200px;
}

.filter-form .form-actions {
    display: flex;
    gap: 10px;
}

.action-buttons {
    margin-bottom: 20px;
    display: flex;
    justify-content: flex-end;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-group.half {
    flex: 1;
}

.action-column {
    display: flex;
    gap: 5px;
}

/* Button Styles */
.btn-delete {
    background-color: #e74a3b;
    color: white;
}

.btn-delete:hover {
    background-color: #d52a1a;
}

.btn-edit {
    background-color: #4e73df;
    color: white;
}

.btn-edit:hover {
    background-color: #2e59d9;
}

/* Responsive adjustment */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .action-column {
        flex-direction: column;
    }
}

.alert {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<?php
// Include footer
include '../includes/admin-footer.php';
?>