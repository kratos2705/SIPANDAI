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
$filter_metode = isset($_GET['metode']) ? $_GET['metode'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters - NOTE: Changed to match correct column names from schema
$base_query = "SELECT pr.pembayaran_id, pr.tagihan_id, pr.tanggal_bayar as tanggal_pembayaran, pr.jumlah_bayar, 
               pr.metode_pembayaran, pr.status as status_pembayaran, pr.catatan, pr.bukti_pembayaran,
               tr.nominal as tagihan_nominal, tr.denda as tagihan_denda, tr.status as tagihan_status,
               u.nama as nama_warga, u.nik, jr.nama_retribusi, jr.periode
               FROM pembayaran_retribusi pr
               JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
               JOIN users u ON tr.user_id = u.user_id
               JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
               WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM pembayaran_retribusi pr
               JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
               JOIN users u ON tr.user_id = u.user_id
               JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
               WHERE 1=1";

// Add filters - Using prepared statements for security
$filter_conditions = [];
$filter_params = [];

if ($filter_status != 'all') {
    // Map UI status to database status
    $status_map = [
        'konfirmasi' => 'pending',
        'proses' => 'pending', // Adjust based on your actual mapping
        'selesai' => 'berhasil',
        'ditolak' => 'gagal'
    ];
    
    $db_status = isset($status_map[$filter_status]) ? $status_map[$filter_status] : $filter_status;
    $filter_conditions[] = "pr.status = ?";
    $filter_params[] = $db_status;
}

if ($filter_jenis != 'all') {
    $filter_conditions[] = "jr.jenis_retribusi_id = ?";
    $filter_params[] = $filter_jenis;
}

if ($filter_metode != 'all') {
    $filter_conditions[] = "pr.metode_pembayaran = ?";
    $filter_params[] = $filter_metode;
}

if ($filter_bulan != 'all' && $filter_tahun != 'all') {
    $filter_conditions[] = "MONTH(pr.tanggal_bayar) = ? AND YEAR(pr.tanggal_bayar) = ?";
    $filter_params[] = $filter_bulan;
    $filter_params[] = $filter_tahun;
}

if (!empty($search)) {
    $filter_conditions[] = "(u.nama LIKE ? OR u.nik LIKE ? OR jr.nama_retribusi LIKE ?)";
    $search_param = "%$search%";
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
}

// Add conditions to queries
if (!empty($filter_conditions)) {
    $conditions_str = implode(" AND ", $filter_conditions);
    $base_query .= " AND " . $conditions_str;
    $count_query .= " AND " . $conditions_str;
}

// Complete queries
$base_query .= " ORDER BY pr.tanggal_bayar DESC LIMIT ?, ?";
$filter_params[] = $offset;
$filter_params[] = $per_page;

// Prepare and execute the main query
$stmt = mysqli_prepare($koneksi, $base_query);
if ($stmt) {
    if (!empty($filter_params)) {
        // Create the type string for bind_param
        $types = str_repeat('s', count($filter_params));
        // Convert array to references as required by bind_param
        $bind_params = [];
        $bind_params[] = &$types;
        foreach ($filter_params as $key => $value) {
            $bind_params[] = &$filter_params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    die("Error in query preparation: " . mysqli_error($koneksi));
}

// Prepare and execute the count query
$count_stmt = mysqli_prepare($koneksi, $count_query);
if ($count_stmt) {
    // Remove the last two parameters (offset and limit) for count query
    array_pop($filter_params);
    array_pop($filter_params);
    
    if (!empty($filter_params)) {
        $types = str_repeat('s', count($filter_params));
        $bind_params = [];
        $bind_params[] = &$types;
        foreach ($filter_params as $key => $value) {
            $bind_params[] = &$filter_params[$key];
        }
        call_user_func_array([$count_stmt, 'bind_param'], $bind_params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
} else {
    die("Error in count query preparation: " . mysqli_error($koneksi));
}

$total_pages = ceil($total_records / $per_page);

// Get retribusi types for filter
$jenis_retribusi_query = "SELECT jenis_retribusi_id, nama_retribusi FROM jenis_retribusi WHERE is_active = TRUE ORDER BY nama_retribusi";
$jenis_retribusi_result = mysqli_query($koneksi, $jenis_retribusi_query);

// Get summary statistics
$pembayaran_stats_query = "SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as konfirmasi,
                       SUM(CASE WHEN status = 'pending' AND confirmed_by IS NOT NULL THEN 1 ELSE 0 END) as proses,
                       SUM(CASE WHEN status = 'berhasil' THEN 1 ELSE 0 END) as selesai,
                       SUM(CASE WHEN status = 'gagal' THEN 1 ELSE 0 END) as ditolak,
                       SUM(jumlah_bayar) as total_pembayaran
                       FROM pembayaran_retribusi";
$pembayaran_stats_result = mysqli_query($koneksi, $pembayaran_stats_query);
$stats = mysqli_fetch_assoc($pembayaran_stats_result);

// Prepare variables for page
$page_title = "Manajemen Pembayaran Retribusi";
$current_page = "pembayaran";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Manajemen Pembayaran Retribusi</h2>
        <div class="admin-header-actions">
            <a href="retribusi.php" class="btn btn-secondary">Kembali ke Retribusi</a>
            <a href="pembayaran_create.php" class="btn">Tambah Pembayaran</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats-card primary">
                <div class="stats-icon">💰</div>
                <div class="stats-info">
                    <h3>Total Pembayaran</h3>
                    <p class="stats-value"><?php echo number_format($stats['total'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">✅</div>
                <div class="stats-info">
                    <h3>Pembayaran Selesai</h3>
                    <p class="stats-value"><?php echo number_format($stats['selesai'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">⏳</div>
                <div class="stats-info">
                    <h3>Menunggu Konfirmasi</h3>
                    <p class="stats-value"><?php echo number_format($stats['konfirmasi'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card info">
                <div class="stats-icon">⚙️</div>
                <div class="stats-info">
                    <h3>Dalam Proses</h3>
                    <p class="stats-value"><?php echo number_format($stats['proses'], 0, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stats-card danger">
                <div class="stats-icon">❌</div>
                <div class="stats-info">
                    <h3>Pembayaran Ditolak</h3>
                    <p class="stats-value"><?php echo number_format($stats['ditolak'], 0, ',', '.'); ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">💸</div>
                <div class="stats-info">
                    <h3>Total Nominal</h3>
                    <p class="stats-value">Rp <?php echo number_format($stats['total_pembayaran'], 0, ',', '.'); ?></p>
                </div>
            </div>
            
            <?php
            // Get payment method statistics
            $metode_query = "SELECT metode_pembayaran, COUNT(*) as jumlah 
                           FROM pembayaran_retribusi 
                           GROUP BY metode_pembayaran 
                           ORDER BY jumlah DESC 
                           LIMIT 1";
            $metode_result = mysqli_query($koneksi, $metode_query);
            $metode_top = mysqli_fetch_assoc($metode_result);
            ?>
            <div class="stats-card">
                <div class="stats-icon">💳</div>
                <div class="stats-info">
                    <h3>Metode Terpopuler</h3>
                    <p class="stats-value">
                        <?php echo $metode_top ? ucfirst(str_replace('_', ' ', $metode_top['metode_pembayaran'])) : 'Tidak ada data'; ?>
                    </p>
                </div>
            </div>
            
            <?php
            // Get current month statistics
            $bulan_ini = date('m');
            $tahun_ini = date('Y');
            $bulan_ini_query = "SELECT COUNT(*) as jumlah 
                              FROM pembayaran_retribusi 
                              WHERE MONTH(tanggal_bayar) = ? 
                              AND YEAR(tanggal_bayar) = ?";
            $bulan_stmt = mysqli_prepare($koneksi, $bulan_ini_query);
            mysqli_stmt_bind_param($bulan_stmt, "ss", $bulan_ini, $tahun_ini);
            mysqli_stmt_execute($bulan_stmt);
            $bulan_ini_result = mysqli_stmt_get_result($bulan_stmt);
            $bulan_ini_data = mysqli_fetch_assoc($bulan_ini_result);
            ?>
            <div class="stats-card">
                <div class="stats-icon">📅</div>
                <div class="stats-info">
                    <h3>Pembayaran Bulan Ini</h3>
                    <p class="stats-value"><?php echo number_format($bulan_ini_data['jumlah'], 0, ',', '.'); ?></p>
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
                        <option value="konfirmasi" <?php echo $filter_status == 'konfirmasi' ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                        <option value="proses" <?php echo $filter_status == 'proses' ? 'selected' : ''; ?>>Dalam Proses</option>
                        <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="ditolak" <?php echo $filter_status == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
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
                    <label for="metode">Metode Pembayaran:</label>
                    <select name="metode" id="metode" class="form-control">
                        <option value="all" <?php echo $filter_metode == 'all' ? 'selected' : ''; ?>>Semua Metode</option>
                        <option value="transfer_bank" <?php echo $filter_metode == 'transfer_bank' ? 'selected' : ''; ?>>Transfer Bank</option>
                        <option value="tunai" <?php echo $filter_metode == 'tunai' ? 'selected' : ''; ?>>Tunai</option>
                        <option value="e_wallet" <?php echo $filter_metode == 'e_wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                        <option value="qris" <?php echo $filter_metode == 'qris' ? 'selected' : ''; ?>>QRIS</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-row">
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
                <div class="search-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, atau jenis retribusi..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn">Cari</button>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-actions">
                    <a href="pembayaran.php" class="btn btn-outline">Reset Filter</a>
                    <button type="submit" class="btn">Terapkan Filter</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <div class="action-group">
            <a href="pembayaran_export.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-outline"><i class="fas fa-file-export"></i> Export Data</a>
            <a href="laporan_pembayaran.php" class="btn btn-outline"><i class="fas fa-chart-bar"></i> Laporan Pembayaran</a>
        </div>
        <div class="batch-actions">
            <button type="button" class="btn btn-success" id="batchApproveBtn"><i class="fas fa-check"></i> Setujui Terpilih</button>
            <button type="button" class="btn btn-danger" id="batchRejectBtn"><i class="fas fa-times"></i> Tolak Terpilih</button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="data-card">
        <div class="card-header">
            <h3>Data Pembayaran Retribusi</h3>
            <span class="card-header-info">Total: <?php echo $total_records; ?> pembayaran</span>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Nama Warga</th>
                        <th>NIK</th>
                        <th>Jenis Retribusi</th>
                        <th>Tanggal Bayar</th>
                        <th>Jumlah Bayar</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Format date
                            $tanggal_bayar = date('d-m-Y', strtotime($row['tanggal_pembayaran']));
                            
                            // Format metode pembayaran
                            $metode_pembayaran = ucfirst(str_replace('_', ' ', $row['metode_pembayaran']));
                            
                            // Map database status to UI status
                            $ui_status = "";
                            $status_class = "";
                            switch ($row['status_pembayaran']) {
                                case 'pending':
                                    if (isset($row['confirmed_by']) && !empty($row['confirmed_by'])) {
                                        $status_class = "status-processing";
                                        $status_text = "Dalam Proses";
                                    } else {
                                        $status_class = "status-pending";
                                        $status_text = "Menunggu Konfirmasi";
                                    }
                                    break;
                                case 'berhasil':
                                    $status_class = "status-completed";
                                    $status_text = "Selesai";
                                    break;
                                case 'gagal':
                                    $status_class = "status-rejected";
                                    $status_text = "Ditolak";
                                    break;
                                default:
                                    $status_class = "status-pending";
                                    $status_text = "Menunggu Konfirmasi";
                            }
                            
                            // Calculate total payment (if there's a fine)
                            $total_tagihan = $row['tagihan_nominal'] + $row['tagihan_denda'];
                            $payment_status = "";
                            
                            if ($row['jumlah_bayar'] >= $total_tagihan) {
                                $payment_status = '<span class="payment-status full" title="Lunas"><i class="fas fa-check-circle"></i></span>';
                            } else {
                                $payment_status = '<span class="payment-status partial" title="Sebagian"><i class="fas fa-adjust"></i></span>';
                            }
                            
                            echo '<tr>';
                            echo '<td><input type="checkbox" class="selectRow" value="' . $row['pembayaran_id'] . '"></td>';
                            echo '<td>' . htmlspecialchars($row['nama_warga']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nik']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama_retribusi']) . ' (' . ucfirst($row['periode']) . ')</td>';
                            echo '<td>' . $tanggal_bayar . '</td>';
                            echo '<td>' . $payment_status . ' Rp ' . number_format($row['jumlah_bayar'], 0, ',', '.') . '</td>';
                            echo '<td>' . $metode_pembayaran . '</td>';
                            echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                            echo '<td class="action-cell">';
                            
                            echo '<a href="pembayaran_detail.php?id=' . $row['pembayaran_id'] . '" class="btn-action view" title="Lihat Detail"><i class="fas fa-eye"></i></a>';
                            
                            // Only show verify button for payments in konfirmasi or proses status
                            if ($row['status_pembayaran'] == 'pending') {
                                echo '<a href="pembayaran_verification.php?id=' . $row['pembayaran_id'] . '" class="btn-action verify" title="Verifikasi Pembayaran"><i class="fas fa-check-double"></i></a>';
                            }
                            
                            // Don't allow editing for completed payments
                            if ($row['status_pembayaran'] != 'berhasil') {
                                echo '<a href="pembayaran_edit.php?id=' . $row['pembayaran_id'] . '" class="btn-action edit" title="Edit Pembayaran"><i class="fas fa-edit"></i></a>';
                            }
                            
                            echo '<button data-id="' . $row['pembayaran_id'] . '" class="btn-action delete deleteBtn" title="Hapus Pembayaran"><i class="fas fa-trash"></i></button>';
                            
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="9" class="text-center">Tidak ada data pembayaran yang ditemukan</td></tr>';
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
            <p>Apakah Anda yakin ingin menghapus data pembayaran ini?</p>
            <p>Tindakan ini tidak dapat dibatalkan dan dapat mempengaruhi status tagihan terkait.</p>
        </div>
        <div class="modal-footer">
            <form action="pembayaran_delete.php" method="POST">
                <input type="hidden" name="pembayaran_id" id="deletePembayaranId">
                <button type="button" class="btn btn-secondary close-modal">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </form>
        </div>
    </div>
</div>

<!-- Batch Approve Modal -->
<div class="modal" id="approveModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Konfirmasi Persetujuan</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Anda akan menyetujui <span id="approveCount">0</span> pembayaran terpilih.</p>
            <p>Apakah Anda yakin ingin melanjutkan?</p>
            <form id="approveForm" action="pembayaran_batch_approve.php" method="POST" method="POST">
                <div id="approveItemsContainer"></div>
                <div class="form-group">
                    <label for="approve_notes">Catatan (Opsional):</label>
                    <textarea name="approve_notes" id="approve_notes" class="form-control" rows="3" placeholder="Tambahkan catatan untuk pembayaran yang disetujui"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Batal</button>
            <button type="button" class="btn btn-success" id="submitApproveBtn">Setujui Pembayaran</button>
        </div>
    </div>
</div>

<!-- Batch Reject Modal -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Konfirmasi Penolakan</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Anda akan menolak <span id="rejectCount">0</span> pembayaran terpilih.</p>
            <p>Mohon berikan alasan penolakan:</p>
            <form id="rejectForm" action="pembayaran_batch_reject.php" method="POST">
                <div id="rejectItemsContainer"></div>
                <div class="form-group">
                    <label for="reject_reason">Alasan Penolakan <span class="required">*</span>:</label>
                    <textarea name="reject_reason" id="reject_reason" class="form-control" rows="3" placeholder="Alasan pembayaran ditolak" required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Batal</button>
            <button type="button" class="btn btn-danger" id="submitRejectBtn">Tolak Pembayaran</button>
        </div>
    </div>
</div>
<style>
/* Layout */
.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -0.75rem;
}

.col-md-8 {
    width: 66.666667%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

.col-md-4 {
    width: 33.333333%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

.col-md-6 {
    width: 50%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

/* Form Styles */
.form-card, .detail-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
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

.card-body {
    padding: 1.25rem;
}

.admin-form {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    margin-bottom: 0.75rem;
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

.form-control-file {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    border: 1px solid #d1d3e2;
    border-radius: 4px;
    color: #6e707e;
    transition: border-color 0.2s;
    background-color: #f8f9fc;
}

.form-control:focus, .form-control-file:focus {
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
    margin: 0 -0.75rem;
    margin-bottom: 1rem;
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

/* Tagihan Info Styles */
.tagihan-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tagihan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tagihan-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.tagihan-status {
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

.tagihan-period {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: -0.5rem;
}

.tagihan-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-bottom: 1px solid #e3e6f0;
    padding-bottom: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.detail-label {
    color: #6c757d;
    font-weight: 500;
}

.detail-value {
    font-weight: 500;
    color: #333;
}

.tagihan-amount {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.amount-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.amount-item.total {
    border-top: 1px solid #e3e6f0;
    padding-top: 0.5rem;
    margin-top: 0.25rem;
    font-weight: 600;
}

.amount-label {
    color: #6c757d;
}

.amount-value {
    font-weight: 600;
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

.alert-warning {
    background-color: #fff3cd;
    border-left: 4px solid #f6c23e;
    color: #856404;
}

/* Text Colors */
.text-success {
    color: #1cc88a;
}

.text-danger {
    color: #e74a3b;
}

.text-muted {
    color: #6c757d;
}

/* Button Styles */
.btn, .btn:visited {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 400;
    line-height: 1.5;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    background-color: #4e73df;
    color: white;
    text-decoration: none;
}

.btn:hover {
    background-color: #2e59d9;
    color: white;
}

.btn-secondary, .btn-secondary:visited {
    color: white;
    background-color: #6c757d;
    border-color: #6c757d;
    text-decoration: none;
}

.btn-secondary:hover {
    color: white;
    background-color: #5a6268;
    border-color: #545b62;
}

.btn-outline, .btn-outline:visited {
    color: #4e73df;
    background-color: transparent;
    border-color: #4e73df;
    text-decoration: none;
}

.btn-outline:hover {
    color: white;
    background-color: #4e73df;
    border-color: #4e73df;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-8, .col-md-4, .col-md-6 {
        width: 100%;
        padding: 0;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .admin-header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .admin-header-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete Modal Functionality
    var deleteModal = document.getElementById('deleteModal');
    var deleteBtns = document.querySelectorAll('.deleteBtn');
    var closeButtons = document.querySelectorAll('.close, .close-modal');
    var deletePembayaranId = document.getElementById('deletePembayaranId');
    
    deleteBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var pembayaranId = this.getAttribute('data-id');
            deletePembayaranId.value = pembayaranId;
            deleteModal.style.display = 'block';
        });
    });
    
    closeButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            deleteModal.style.display = 'none';
            approveModal.style.display = 'none';
            rejectModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target == deleteModal) {
            deleteModal.style.display = 'none';
        }
        if (event.target == approveModal) {
            approveModal.style.display = 'none';
        }
        if (event.target == rejectModal) {
            rejectModal.style.display = 'none';
        }
    });
    
    // Select All Functionality
    var selectAll = document.getElementById('selectAll');
    var selectRows = document.querySelectorAll('.selectRow');
    
    selectAll.addEventListener('change', function() {
        var isChecked = this.checked;
        selectRows.forEach(function(checkbox) {
            checkbox.checked = isChecked;
        });
        updateBatchButtonsState();
    });
    
    selectRows.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateSelectAllStatus();
            updateBatchButtonsState();
        });
    });
    
    function updateSelectAllStatus() {
        var allChecked = true;
        var anyChecked = false;
        
        selectRows.forEach(function(checkbox) {
            if (!checkbox.checked) {
                allChecked = false;
            } else {
                anyChecked = true;
            }
        });
        
        selectAll.checked = allChecked;
        selectAll.indeterminate = !allChecked && anyChecked;
    }
    
    function updateBatchButtonsState() {
        var checkedCount = document.querySelectorAll('.selectRow:checked').length;
        var batchApproveBtn = document.getElementById('batchApproveBtn');
        var batchRejectBtn = document.getElementById('batchRejectBtn');
        
        if (checkedCount > 0) {
            batchApproveBtn.removeAttribute('disabled');
            batchRejectBtn.removeAttribute('disabled');
            batchApproveBtn.classList.remove('disabled');
            batchRejectBtn.classList.remove('disabled');
        } else {
            batchApproveBtn.setAttribute('disabled', 'disabled');
            batchRejectBtn.setAttribute('disabled', 'disabled');
            batchApproveBtn.classList.add('disabled');
            batchRejectBtn.classList.add('disabled');
        }
    }
    
    // Batch Approve Functionality
    var approveModal = document.getElementById('approveModal');
    var approveCount = document.getElementById('approveCount');
    var approveForm = document.getElementById('approveForm');
    var approveItemsContainer = document.getElementById('approveItemsContainer');
    var batchApproveBtn = document.getElementById('batchApproveBtn');
    var submitApproveBtn = document.getElementById('submitApproveBtn');
    
    batchApproveBtn.addEventListener('click', function() {
        var selectedIds = getSelectedIds();
        
        if (selectedIds.length > 0) {
            approveCount.textContent = selectedIds.length;
            approveItemsContainer.innerHTML = '';
            
            selectedIds.forEach(function(id) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'pembayaran_ids[]';
                hiddenInput.value = id;
                approveItemsContainer.appendChild(hiddenInput);
            });
            
            approveModal.style.display = 'block';
        }
    });
    
    submitApproveBtn.addEventListener('click', function() {
        approveForm.submit();
    });
    
    // Batch Reject Functionality
    var rejectModal = document.getElementById('rejectModal');
    var rejectCount = document.getElementById('rejectCount');
    var rejectForm = document.getElementById('rejectForm');
    var rejectItemsContainer = document.getElementById('rejectItemsContainer');
    var batchRejectBtn = document.getElementById('batchRejectBtn');
    var submitRejectBtn = document.getElementById('submitRejectBtn');
    
    batchRejectBtn.addEventListener('click', function() {
        var selectedIds = getSelectedIds();
        
        if (selectedIds.length > 0) {
            rejectCount.textContent = selectedIds.length;
            rejectItemsContainer.innerHTML = '';
            
            selectedIds.forEach(function(id) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'pembayaran_ids[]';
                hiddenInput.value = id;
                rejectItemsContainer.appendChild(hiddenInput);
            });
            
            rejectModal.style.display = 'block';
        }
    });
    
    submitRejectBtn.addEventListener('click', function() {
        if (document.getElementById('reject_reason').value.trim() === '') {
            alert('Alasan penolakan harus diisi!');
            return;
        }
        rejectForm.submit();
    });
    
    function getSelectedIds() {
        var selectedIds = [];
        var checkboxes = document.querySelectorAll('.selectRow:checked');
        
        checkboxes.forEach(function(checkbox) {
            selectedIds.push(checkbox.value);
        });
        
        return selectedIds;
    }
    
    // Initialize state on page load
    updateSelectAllStatus();
    updateBatchButtonsState();
    
    // Add auto-submit for filter form when changing selects
    const filterSelects = document.querySelectorAll('.filter-form select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Only auto-submit if not the year or month selects (which should work together)
            if (this.id !== 'bulan' && this.id !== 'tahun') {
                document.querySelector('.filter-form').submit();
            }
        });
    });
    
    // Link year and month selects
    const yearSelect = document.getElementById('tahun');
    const monthSelect = document.getElementById('bulan');
    
    if (yearSelect && monthSelect) {
        monthSelect.addEventListener('change', function() {
            if (yearSelect.value === 'all' && this.value !== 'all') {
                yearSelect.value = '<?php echo date('Y'); ?>';
            }
            // Don't auto-submit to allow selecting both month and year
        });
        
        yearSelect.addEventListener('change', function() {
            if (this.value !== 'all' && monthSelect.value === 'all') {
                monthSelect.value = '<?php echo date('m'); ?>';
            }
            // Auto-submit after year changes as both year and month should be set now
            document.querySelector('.filter-form').submit();
        });
    }
});
</script>

<?php include '../includes/admin-footer.php'; ?>