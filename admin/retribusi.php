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

// Handle filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'all';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$base_query = "SELECT tr.tagihan_id, tr.tanggal_tagihan, tr.jatuh_tempo, tr.nominal, tr.status, tr.denda,
              u.nama, u.nik, jr.nama_retribusi, jr.periode,
              (SELECT COUNT(*) FROM pembayaran_retribusi pr WHERE pr.tagihan_id = tr.tagihan_id) as has_payment
              FROM tagihan_retribusi tr
              JOIN users u ON tr.user_id = u.user_id
              JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
              WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM tagihan_retribusi tr
                JOIN users u ON tr.user_id = u.user_id
                JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                WHERE 1=1";

// Add filters
if ($filter_status != 'all') {
    $base_query .= " AND tr.status = '$filter_status'";
    $count_query .= " AND tr.status = '$filter_status'";
}

if ($filter_jenis != 'all') {
    $base_query .= " AND jr.jenis_retribusi_id = '$filter_jenis'";
    $count_query .= " AND jr.jenis_retribusi_id = '$filter_jenis'";
}

if ($filter_bulan != 'all' && $filter_tahun != 'all') {
    $base_query .= " AND MONTH(tr.tanggal_tagihan) = '$filter_bulan' AND YEAR(tr.tanggal_tagihan) = '$filter_tahun'";
    $count_query .= " AND MONTH(tr.tanggal_tagihan) = '$filter_bulan' AND YEAR(tr.tanggal_tagihan) = '$filter_tahun'";
}

if (!empty($search)) {
    $search_term = mysqli_real_escape_string($koneksi, $search);
    $base_query .= " AND (u.nama LIKE '%$search_term%' OR u.nik LIKE '%$search_term%' OR jr.nama_retribusi LIKE '%$search_term%')";
    $count_query .= " AND (u.nama LIKE '%$search_term%' OR u.nik LIKE '%$search_term%' OR jr.nama_retribusi LIKE '%$search_term%')";
}

// Complete queries
$base_query .= " ORDER BY tr.tanggal_tagihan DESC LIMIT $offset, $per_page";

// Execute queries
$result = mysqli_query($koneksi, $base_query);
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get retribusi types for filter
$jenis_retribusi_query = "SELECT jenis_retribusi_id, nama_retribusi FROM jenis_retribusi WHERE is_active = TRUE ORDER BY nama_retribusi";
$jenis_retribusi_result = mysqli_query($koneksi, $jenis_retribusi_query);

// Get summary statistics
$total_tagihan_query = "SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status = 'belum_bayar' THEN 1 ELSE 0 END) as belum_bayar,
                       SUM(CASE WHEN status = 'proses' THEN 1 ELSE 0 END) as proses,
                       SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END) as lunas,
                       SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) as telat,
                       SUM(nominal) as total_nominal,
                       SUM(CASE WHEN status = 'lunas' THEN nominal ELSE 0 END) as total_terbayar,
                       SUM(CASE WHEN status IN ('belum_bayar', 'proses', 'telat') THEN nominal ELSE 0 END) as total_belum_terbayar
                       FROM tagihan_retribusi";
$total_tagihan_result = mysqli_query($koneksi, $total_tagihan_query);
$stats = mysqli_fetch_assoc($total_tagihan_result);

// Prepare variables for page
$page_title = "Manajemen Retribusi";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Manajemen Retribusi</h2>
        <div class="admin-header-actions">
            <a href="jenis_retribusi.php" class="btn btn-secondary">Kelola Jenis Retribusi</a>
            <a href="tagihan_create.php" class="btn">Buat Tagihan Baru</a>
            <a href="generate_tagihan.php" class="btn">Generate Tagihan Massal</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats-card primary">
                <div class="stats-icon">💰</div>
                <div class="stats-info">
                    <h3>Total Tagihan</h3>
                    <p class="stats-value"><?php echo number_format($stats['total'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">✅</div>
                <div class="stats-info">
                    <h3>Sudah Lunas</h3>
                    <p class="stats-value"><?php echo number_format($stats['lunas'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">⏳</div>
                <div class="stats-info">
                    <h3>Belum Bayar</h3>
                    <p class="stats-value"><?php echo number_format($stats['belum_bayar'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card danger">
                <div class="stats-icon">⚠️</div>
                <div class="stats-info">
                    <h3>Telat Bayar</h3>
                    <p class="stats-value"><?php echo number_format($stats['telat'], 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stats-card info">
                <div class="stats-icon">💲</div>
                <div class="stats-info">
                    <h3>Total Nominal</h3>
                    <p class="stats-value">Rp <?php echo number_format($stats['total_nominal'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">💸</div>
                <div class="stats-info">
                    <h3>Total Terbayar</h3>
                    <p class="stats-value">Rp <?php echo number_format($stats['total_terbayar'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">📝</div>
                <div class="stats-info">
                    <h3>Belum Terbayar</h3>
                    <p class="stats-value">Rp <?php echo number_format($stats['total_belum_terbayar'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">⚙️</div>
                <div class="stats-info">
                    <h3>Jenis Retribusi</h3>
                    <p class="stats-value">
                        <?php 
                        $jenis_count_query = "SELECT COUNT(*) as total FROM jenis_retribusi WHERE is_active = TRUE";
                        $jenis_count_result = mysqli_query($koneksi, $jenis_count_query);
                        echo mysqli_fetch_assoc($jenis_count_result)['total'];
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="filter-container">
        <form action="" method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="belum_bayar" <?php echo $filter_status == 'belum_bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                        <option value="proses" <?php echo $filter_status == 'proses' ? 'selected' : ''; ?>>Proses</option>
                        <option value="lunas" <?php echo $filter_status == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                        <option value="telat" <?php echo $filter_status == 'telat' ? 'selected' : ''; ?>>Telat</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="jenis">Jenis Retribusi:</label>
                    <select name="jenis" id="jenis" class="form-control">
                        <option value="all" <?php echo $filter_jenis == 'all' ? 'selected' : ''; ?>>Semua Jenis</option>
                        <?php 
                        if (mysqli_num_rows($jenis_retribusi_result) > 0) {
                            while ($jenis = mysqli_fetch_assoc($jenis_retribusi_result)) {
                                $selected = $filter_jenis == $jenis['jenis_retribusi_id'] ? 'selected' : '';
                                echo '<option value="' . $jenis['jenis_retribusi_id'] . '" ' . $selected . '>' . htmlspecialchars($jenis['nama_retribusi']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="bulan">Bulan:</label>
                    <select name="bulan" id="bulan" class="form-control">
                        <option value="all" <?php echo $filter_bulan == 'all' ? 'selected' : ''; ?>>Semua Bulan</option>
                        <?php
                        $months = [
                            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                        ];
                        foreach ($months as $num => $name) {
                            $selected = $filter_bulan == $num ? 'selected' : '';
                            echo '<option value="' . $num . '" ' . $selected . '>' . $name . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="tahun">Tahun:</label>
                    <select name="tahun" id="tahun" class="form-control">
                        <option value="all" <?php echo $filter_tahun == 'all' ? 'selected' : ''; ?>>Semua Tahun</option>
                        <?php
                        $current_year = date('Y');
                        for ($year = $current_year; $year >= $current_year - 5; $year--) {
                            $selected = $filter_tahun == $year ? 'selected' : '';
                            echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="search-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, atau jenis retribusi..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn">Cari</button>
                </div>
                <div class="filter-actions">
                    <a href="retribusi.php" class="btn btn-outline">Reset Filter</a>
                    <button type="submit" class="btn">Terapkan Filter</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <div class="action-group">
            <a href="retribusi_export.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-outline"><i class="fas fa-file-export"></i> Export Data</a>
            <a href="pembayaran.php" class="btn btn-outline"><i class="fas fa-money-bill"></i> Lihat Pembayaran</a>
        </div>
        <div class="batch-actions">
            <button type="button" class="btn btn-warning" id="sendReminderBtn"><i class="fas fa-bell"></i> Kirim Pengingat</button>
            <button type="button" class="btn btn-danger" id="calculateFineBtn"><i class="fas fa-calculator"></i> Hitung Denda</button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="data-card">
        <div class="card-header">
            <h3>Data Tagihan Retribusi</h3>
            <span class="card-header-info">Total: <?php echo $total_records; ?> tagihan</span>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Nama Warga</th>
                        <th>NIK</th>
                        <th>Jenis Retribusi</th>
                        <th>Tanggal Tagihan</th>
                        <th>Jatuh Tempo</th>
                        <th>Nominal</th>
                        <th>Denda</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Format dates
                            $tanggal_tagihan = date('d-m-Y', strtotime($row['tanggal_tagihan']));
                            $jatuh_tempo = date('d-m-Y', strtotime($row['jatuh_tempo']));
                            
                            // Determine status class
                            $status_class = "";
                            switch ($row['status']) {
                                case 'belum_bayar':
                                    $status_class = "status-pending";
                                    $status_text = "Belum Bayar";
                                    break;
                                case 'proses':
                                    $status_class = "status-processing";
                                    $status_text = "Proses";
                                    break;
                                case 'lunas':
                                    $status_class = "status-completed";
                                    $status_text = "Lunas";
                                    break;
                                case 'telat':
                                    $status_class = "status-rejected";
                                    $status_text = "Telat";
                                    break;
                                default:
                                    $status_class = "status-pending";
                                    $status_text = "Belum Bayar";
                            }
                            
                            // Has payment icon
                            $payment_icon = $row['has_payment'] > 0 ? '<i class="fas fa-receipt" title="Memiliki pembayaran"></i> ' : '';
                            
                            echo '<tr>';
                            echo '<td><input type="checkbox" class="selectRow" value="' . $row['tagihan_id'] . '"></td>';
                            echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nik']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama_retribusi']) . ' (' . ucfirst($row['periode']) . ')</td>';
                            echo '<td>' . $tanggal_tagihan . '</td>';
                            echo '<td>' . $jatuh_tempo . '</td>';
                            echo '<td>Rp ' . number_format($row['nominal'], 0, ',', '.') . '</td>';
                            echo '<td>' . ($row['denda'] > 0 ? 'Rp ' . number_format($row['denda'], 0, ',', '.') : '-') . '</td>';
                            echo '<td><span class="status ' . $status_class . '">' . $payment_icon . $status_text . '</span></td>';
                            echo '<td class="action-cell">';
                            echo '<a href="tagihan_detail.php?id=' . $row['tagihan_id'] . '" class="btn-action view" title="Lihat Detail"><i class="fas fa-eye"></i></a>';
                            
                            if ($row['status'] !== 'lunas') {
                                echo '<a href="pembayaran_create.php?tagihan_id=' . $row['tagihan_id'] . '" class="btn-action add" title="Tambah Pembayaran"><i class="fas fa-plus"></i></a>';
                            }
                            
                            if ($row['status'] !== 'lunas') {
                                echo '<a href="tagihan_edit.php?id=' . $row['tagihan_id'] . '" class="btn-action edit" title="Edit Tagihan"><i class="fas fa-edit"></i></a>';
                            }
                            
                            echo '<button data-id="' . $row['tagihan_id'] . '" class="btn-action delete deleteBtn" title="Hapus Tagihan"><i class="fas fa-trash"></i></button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="10" class="text-center">Tidak ada data tagihan yang ditemukan</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = $_GET;
            
            // Previous page link
            if ($page > 1) {
                $query_params['page'] = $page - 1;
                echo '<a href="?' . http_build_query($query_params) . '" class="page-link">&laquo; Sebelumnya</a>';
            } else {
                echo '<span class="page-link disabled">&laquo; Sebelumnya</span>';
            }
            
            // Page numbers
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                $query_params['page'] = 1;
                echo '<a href="?' . http_build_query($query_params) . '" class="page-link">1</a>';
                if ($start_page > 2) {
                    echo '<span class="page-link dots">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                $query_params['page'] = $i;
                if ($i == $page) {
                    echo '<span class="page-link active">' . $i . '</span>';
                } else {
                    echo '<a href="?' . http_build_query($query_params) . '" class="page-link">' . $i . '</a>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="page-link dots">...</span>';
                }
                $query_params['page'] = $total_pages;
                echo '<a href="?' . http_build_query($query_params) . '" class="page-link">' . $total_pages . '</a>';
            }
            
            // Next page link
            if ($page < $total_pages) {
                $query_params['page'] = $page + 1;
                echo '<a href="?' . http_build_query($query_params) . '" class="page-link">Selanjutnya &raquo;</a>';
            } else {
                echo '<span class="page-link disabled">Selanjutnya &raquo;</span>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Konfirmasi Hapus</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus tagihan ini?</p>
            <p>Tindakan ini tidak dapat dibatalkan dan akan menghapus semua pembayaran terkait.</p>
        </div>
        <div class="modal-footer">
            <form action="tagihan_delete.php" method="POST">
                <input type="hidden" name="tagihan_id" id="deleteTagihanId">
                <button type="button" class="btn btn-secondary close-modal">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </form>
        </div>
    </div>
</div>

<!-- Send Reminder Modal -->
<div class="modal" id="reminderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Kirim Pengingat Tagihan</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Kirim pengingat kepada warga untuk tagihan yang belum dibayar.</p>
            <form id="reminderForm" action="send_reminder.php" method="POST">
                <div class="form-group">
                    <label for="reminderType">Jenis Pengingat:</label>
                    <select name="reminderType" id="reminderType" class="form-control" required>
                        <option value="email">Email</option>
                        <option value="notification">Notifikasi Aplikasi</option>
                        <option value="both" selected>Keduanya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reminderMessage">Pesan Tambahan (Opsional):</label>
                    <textarea name="reminderMessage" id="reminderMessage" class="form-control" rows="3" placeholder="Masukkan pesan tambahan untuk disertakan dalam pengingat..."></textarea>
                </div>
                <div class="selected-items-container">
                    <p>Tagihan yang dipilih: <span id="selectedCount">0</span></p>
                    <div id="selectedItems" class="selected-items"></div>
                    <input type="hidden" name="selectedTagihan" id="selectedTagihanIds">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Batal</button>
            <button type="button" class="btn" id="sendReminderConfirm">Kirim Pengingat</button>
        </div>
    </div>
</div>

<!-- Calculate Fine Modal -->
<div class="modal" id="fineModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Hitung Denda Keterlambatan</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Hitung dan terapkan denda keterlambatan untuk tagihan yang melewati jatuh tempo.</p>
            <form id="fineForm" action="calculate_fine.php" method="POST">
                <div class="form-group">
                    <label for="fineType">Metode Perhitungan:</label>
                    <select name="fineType" id="fineType" class="form-control" required>
                        <option value="percentage">Persentase dari Tagihan</option>
                        <option value="fixed">Nominal Tetap</option>
                    </select>
                </div>
                <div class="form-group" id="percentageGroup">
                    <label for="finePercentage">Persentase Denda (%):</label>
                    <input type="number" name="finePercentage" id="finePercentage" class="form-control" value="2" min="0" max="100" step="0.1">
                </div>
                <div class="form-group" id="fixedGroup" style="display:none;">
                    <label for="fixedAmount">Nominal Denda (Rp):</label>
                    <input type="number" name="fixedAmount" id="fixedAmount" class="form-control" value="10000" min="0">
                </div>
                <div class="form-group">
                    <label for="applyToAll">Terapkan pada:</label>
                    <select name="applyToAll" id="applyToAll" class="form-control" required>
                        <option value="selected">Hanya Tagihan Terpilih</option>
                        <option value="all_overdue">Semua Tagihan Jatuh Tempo</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="updateStatus" id="updateStatus" checked>
                        <label for="updateStatus">Ubah status menjadi 'Telat'</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="sendNotification" id="sendNotification" checked>
                        <label for="sendNotification">Kirim notifikasi ke warga</label>
                    </div>
                </div>
                <div class="selected-items-container">
                    <p>Tagihan yang dipilih: <span id="selectedFineCount">0</span></p>
                    <div id="selectedFineItems" class="selected-items"></div>
                    <input type="hidden" name="selectedTagihan" id="selectedFineIds">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Batal</button>
            <button type="button" class="btn" id="calculateFineConfirm">Hitung & Terapkan</button>
        </div>
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
    
/* Stats Container */
.stats-container {
    margin-bottom: 2rem;
}

.stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.stats-card {
    flex: 1;
    min-width: 200px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.stats-icon {
    font-size: 2rem;
    width: 3rem;
    height: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.05);
}

.stats-info {
    flex: 1;
}

.stats-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #555;
}

.stats-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: #333;
}

/* Stats Card Colors */
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

.stats-card.info {
    border-left: 4px solid #36b9cc;
}

.stats-card.info .stats-icon {
    color: #36b9cc;
    background-color: rgba(54, 185, 204, 0.1);
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

/* Filter Container */
.filter-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    margin-bottom: 2rem;
}

.filter-form {
    width: 100%;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    align-items: flex-end;
}

.filter-row:last-child {
    margin-bottom: 0;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #555;
}

.search-group {
    flex: 2;
    min-width: 250px;
    display: flex;
    gap: 0.5rem;
}

.search-group .form-control {
    flex: 1;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-group {
    display: flex;
    gap: 0.5rem;
}

.batch-actions {
    display: flex;
    gap: 0.5rem;
}

/* Data Card */
.data-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.card-header-info {
    font-size: 0.875rem;
    color: #6c757d;
}

.card-body {
    padding: 1.25rem;
}

/* Table Styles */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    background-color: #f8f9fc;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #5a5c69;
    border-bottom: 1px solid #e3e6f0;
}

.admin-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e3e6f0;
    color: #5a5c69;
}

.admin-table tbody tr:hover {
    background-color: #f8f9fc;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

.table-responsive {
    overflow-x: auto;
}

/* Status Badges */
.status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.status-pending {
    background-color: #fff4db;
    color: #d39e00;
}

.status-processing {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-completed {
    background-color: #d4edda;
    color: #155724;
}

.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}

/* Action Cell Buttons */
.action-cell {
    white-space: nowrap;
    display: flex;
    gap: 0.25rem;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    background-color: #f8f9fc;
    color: #5a5c69;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-action:hover {
    background-color: #eaecf4;
}

.btn-action.view:hover {
    background-color: #4e73df;
    color: white;
}

.btn-action.edit:hover {
    background-color: #1cc88a;
    color: white;
}

.btn-action.add:hover {
    background-color: #36b9cc;
    color: white;
}

.btn-action.delete:hover {
    background-color: #e74a3b;
    color: white;
}

.btn-action.activate:hover {
    background-color: #1cc88a;
    color: white;
}

.btn-action.deactivate:hover {
    background-color: #f6c23e;
    color: white;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    padding: 1.25rem;
    gap: 0.25rem;
}

.page-link {
    display: inline-block;
    padding: 0.5rem 0.75rem;
    background-color: #fff;
    border: 1px solid #dddfeb;
    color: #4e73df;
    font-size: 0.875rem;
    line-height: 1;
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.2s;
}

.page-link:hover {
    background-color: #eaecf4;
    color: #2e59d9;
}

.page-link.active {
    background-color: #4e73df;
    border-color: #4e73df;
    color: white;
}

.page-link.disabled {
    color: #b7b9cc;
    pointer-events: none;
    cursor: default;
}

.page-link.dots {
    border: none;
    background: transparent;
    cursor: default;
}

.page-link.dots:hover {
    background: transparent;
}

/* Modals */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.modal-content {
    background-color: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.modal-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    padding: 1.25rem;
    border-top: 1px solid #e3e6f0;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.close {
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #e74a3b;
}

/* Forms */
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

/* Selected Items in Modals */
.selected-items-container {
    margin-top: 1rem;
    border-top: 1px solid #e3e6f0;
    padding-top: 1rem;
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.selected-badge {
    background-color: #f8f9fc;
    border: 1px solid #e3e6f0;
    border-radius: 30px;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    white-space: nowrap;
}

.selected-badge.more {
    background-color: #4e73df;
    color: white;
    border-color: #4e73df;
}

/* Usage Stats for Form Detail */
.usage-stats {
    display: flex;
    gap: 1.5rem;
    margin: 1rem 0;
}

.usage-stat {
    background-color: #f8f9fc;
    border-radius: 8px;
    padding: 1rem;
    flex: 1;
    text-align: center;
}

.stat-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #5a5c69;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #4e73df;
}

/* Utility Classes */
.text-center {
    text-align: center;
}

.mt-4 {
    margin-top: 1.5rem;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .filter-group,
    .search-group {
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .stats-row {
        flex-direction: column;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .action-cell {
        flex-wrap: wrap;
    }
    
    .usage-stats {
        flex-direction: column;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete modal functionality
    const deleteModal = document.getElementById('deleteModal');
    const deleteButtons = document.querySelectorAll('.deleteBtn');
    const deleteTagihanIdInput = document.getElementById('deleteTagihanId');
    const closeButtons = document.querySelectorAll('.close, .close-modal');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tagihanId = this.getAttribute('data-id');
            deleteTagihanIdInput.value = tagihanId;
            deleteModal.style.display = 'flex';
        });
    });
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.selectRow');
    
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        rowCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateSelectedItems();
    });
    
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedItems();
            // Update select all checkbox state
            const allChecked = [...rowCheckboxes].every(cb => cb.checked);
            const someChecked = [...rowCheckboxes].some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        });
    });
    
    // Reminder modal functionality
    const reminderModal = document.getElementById('reminderModal');
    const sendReminderBtn = document.getElementById('sendReminderBtn');
    const selectedCount = document.getElementById('selectedCount');
    const selectedItems = document.getElementById('selectedItems');
    const selectedTagihanIds = document.getElementById('selectedTagihanIds');
    const sendReminderConfirm = document.getElementById('sendReminderConfirm');
    const reminderForm = document.getElementById('reminderForm');
    
    sendReminderBtn.addEventListener('click', function() {
        updateSelectedItems();
        const selectedCount = getSelectedTagihanIds().length;
        
        if (selectedCount === 0) {
            alert('Silakan pilih minimal satu tagihan terlebih dahulu.');
            return;
        }
        
        reminderModal.style.display = 'flex';
    });
    
    sendReminderConfirm.addEventListener('click', function() {
        reminderForm.submit();
    });
    
    // Fine calculation modal functionality
    const fineModal = document.getElementById('fineModal');
    const calculateFineBtn = document.getElementById('calculateFineBtn');
    const selectedFineCount = document.getElementById('selectedFineCount');
    const selectedFineItems = document.getElementById('selectedFineItems');
    const selectedFineIds = document.getElementById('selectedFineIds');
    const calculateFineConfirm = document.getElementById('calculateFineConfirm');
    const fineForm = document.getElementById('fineForm');
    const fineType = document.getElementById('fineType');
    const percentageGroup = document.getElementById('percentageGroup');
    const fixedGroup = document.getElementById('fixedGroup');
    
    calculateFineBtn.addEventListener('click', function() {
        updateSelectedFineItems();
        const selectedCount = getSelectedTagihanIds().length;
        
        if (selectedCount === 0) {
            alert('Silakan pilih minimal satu tagihan terlebih dahulu.');
            return;
        }
        
        fineModal.style.display = 'flex';
    });
    
    fineType.addEventListener('change', function() {
        if (this.value === 'percentage') {
            percentageGroup.style.display = 'block';
            fixedGroup.style.display = 'none';
        } else {
            percentageGroup.style.display = 'none';
            fixedGroup.style.display = 'block';
        }
    });
    
    calculateFineConfirm.addEventListener('click', function() {
        fineForm.submit();
    });
    
    // Helper functions
    function getSelectedTagihanIds() {
        const selectedCheckboxes = document.querySelectorAll('.selectRow:checked');
        return Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
    }
    
    function updateSelectedItems() {
        const selectedIds = getSelectedTagihanIds();
        selectedCount.textContent = selectedIds.length;
        selectedTagihanIds.value = selectedIds.join(',');
        
        // Update selected items display
        selectedItems.innerHTML = '';
        if (selectedIds.length > 0) {
            selectedIds.forEach(id => {
                const row = document.querySelector(`.selectRow[value="${id}"]`).closest('tr');
                const nama = row.cells[1].textContent;
                const jenis = row.cells[3].textContent;
                
                const badge = document.createElement('div');
                badge.className = 'selected-badge';
                badge.textContent = `${nama} - ${jenis}`;
                selectedItems.appendChild(badge);
                
                // Only show first 5 items if there are many
                if (selectedItems.children.length >= 5 && selectedIds.length > 5) {
                    const remaining = document.createElement('div');
                    remaining.className = 'selected-badge more';
                    remaining.textContent = `+${selectedIds.length - 5} lainnya`;
                    selectedItems.appendChild(remaining);
                    return;
                }
            });
        }
    }
    
    function updateSelectedFineItems() {
        const selectedIds = getSelectedTagihanIds();
        selectedFineCount.textContent = selectedIds.length;
        selectedFineIds.value = selectedIds.join(',');
        
        // Update selected items display
        selectedFineItems.innerHTML = '';
        if (selectedIds.length > 0) {
            selectedIds.forEach(id => {
                const row = document.querySelector(`.selectRow[value="${id}"]`).closest('tr');
                const nama = row.cells[1].textContent;
                const jenis = row.cells[3].textContent;
                
                const badge = document.createElement('div');
                badge.className = 'selected-badge';
                badge.textContent = `${nama} - ${jenis}`;
                selectedFineItems.appendChild(badge);
                
                // Only show first 5 items if there are many
                if (selectedFineItems.children.length >= 5 && selectedIds.length > 5) {
                    const remaining = document.createElement('div');
                    remaining.className = 'selected-badge more';
                    remaining.textContent = `+${selectedIds.length - 5} lainnya`;
                    selectedFineItems.appendChild(remaining);
                    return;
                }
            });
        }
    }
    
    // Make table rows clickable for details
    const dataRows = document.querySelectorAll('.data-row');
    dataRows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on checkbox or action buttons
            if (e.target.type === 'checkbox' || e.target.closest('.action-cell')) {
                return;
            }
            
            const url = this.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>