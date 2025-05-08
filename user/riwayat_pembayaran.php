<?php
// Function to detect file path
function getBasePath()
{
    if (file_exists('../includes/functions.php')) {
        return '../';
    } elseif (file_exists('includes/functions.php')) {
        return '';
    } else {
        die("Tidak dapat menemukan path includes. Silakan hubungi administrator.");
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determine correct path
$base_path = getBasePath();

// Include necessary files
require_once $base_path . 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login terlebih dahulu';
    redirect($base_path . 'index.php');
}

// Include database connection
require_once $base_path . 'config/koneksi.php';

// Get user's ID
$user_id = $_SESSION['user_id'];

// Set default filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Get current page for pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the query for filtered history
$history_query = "SELECT pr.pembayaran_id, pr.tagihan_id, pr.tanggal_bayar, pr.jumlah_bayar,
                  pr.metode_pembayaran, pr.status, pr.nomor_referensi,
                  jr.nama_retribusi, jr.periode
                  FROM pembayaran_retribusi pr
                  JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
                  JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                  WHERE tr.user_id = ?";

// Add filters
$query_params = array($user_id);

// Status filter
if ($status_filter != 'all') {
    $history_query .= " AND pr.status = ?";
    $query_params[] = $status_filter;
}

// Date filter
switch ($date_filter) {
    case '7days':
        $history_query .= " AND pr.tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $history_query .= " AND pr.tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '3months':
        $history_query .= " AND pr.tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $history_query .= " AND pr.tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $history_query .= " AND pr.tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
}

// Search term
if (!empty($search_term)) {
    $history_query .= " AND (pr.nomor_referensi LIKE ? OR jr.nama_retribusi LIKE ?)";
    $query_params[] = "%$search_term%";
    $query_params[] = "%$search_term%";
}

// Count total rows for pagination
$count_query = $history_query;
$count_stmt = mysqli_prepare($koneksi, $count_query);

// Bind parameters for counting
$types = str_repeat('s', count($query_params));
if (!empty($types)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$query_params);
}

mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_rows = mysqli_num_rows($count_result);
$total_pages = ceil($total_rows / $per_page);

// Add sorting and pagination
$history_query .= " ORDER BY pr.tanggal_bayar DESC LIMIT ? OFFSET ?";
$query_params[] = $per_page;
$query_params[] = $offset;

// Prepare and execute the final query
$history_stmt = mysqli_prepare($koneksi, $history_query);

// Bind parameters
$types = str_repeat('s', count($query_params) - 2) . 'ii'; // Add two integer types for LIMIT and OFFSET
if (!empty($types)) {
    mysqli_stmt_bind_param($history_stmt, $types, ...$query_params);
}

mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);

// Get payment statistics
$stats_query = "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN pr.status = 'berhasil' THEN 1 ELSE 0 END) as successful_payments,
                SUM(CASE WHEN pr.status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                SUM(CASE WHEN pr.status = 'gagal' THEN 1 ELSE 0 END) as failed_payments,
                SUM(pr.jumlah_bayar) as total_amount
                FROM pembayaran_retribusi pr
                JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
                WHERE tr.user_id = ?";

$stats_stmt = mysqli_prepare($koneksi, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include header
$page_title = "Riwayat Pembayaran";
include $base_path . 'includes/header.php';
?>

<div class="main-container">
    <section class="page-header">
        <h2>Riwayat Pembayaran</h2>
        <p>Lihat semua transaksi pembayaran retribusi anda</p>
    </section>

    <!-- Payment Stats -->
    <div class="stats-container">
        <div class="stat-box">
            <div class="stat-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-info">
                <h3>Total Transaksi</h3>
                <p><?php echo $stats['total_payments']; ?></p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Pembayaran Berhasil</h3>
                <p><?php echo $stats['successful_payments']; ?></p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pembayaran Diproses</h3>
                <p><?php echo $stats['pending_payments']; ?></p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Pembayaran Gagal</h3>
                <p><?php echo $stats['failed_payments']; ?></p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon primary">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h3>Total Nilai</h3>
                <p>Rp <?php echo number_format($stats['total_amount'], 0, ',', '.'); ?></p>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form action="" method="GET" id="filter-form">
            <div class="filter-container">
                <div class="filter-item">
                    <label for="status">Status</label>
                    <select name="status" id="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="berhasil" <?php echo $status_filter == 'berhasil' ? 'selected' : ''; ?>>Berhasil</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="gagal" <?php echo $status_filter == 'gagal' ? 'selected' : ''; ?>>Gagal</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="date_range">Rentang Waktu</label>
                    <select name="date_range" id="date_range" onchange="this.form.submit()">
                        <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                        <option value="7days" <?php echo $date_filter == '7days' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                        <option value="30days" <?php echo $date_filter == '30days' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
                        <option value="3months" <?php echo $date_filter == '3months' ? 'selected' : ''; ?>>3 Bulan Terakhir</option>
                        <option value="6months" <?php echo $date_filter == '6months' ? 'selected' : ''; ?>>6 Bulan Terakhir</option>
                        <option value="year" <?php echo $date_filter == 'year' ? 'selected' : ''; ?>>1 Tahun Terakhir</option>
                    </select>
                </div>
                <div class="filter-item search-box">
                    <input type="text" name="search" placeholder="Cari nomor referensi/jenis retribusi..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="search-btn">Cari</button>
                </div>
                <?php if (!empty($search_term) || $status_filter != 'all' || $date_filter != 'all'): ?>
                    <div class="filter-item">
                        <a href="<?php echo $base_path; ?>user/riwayat_pembayaran.php" class="reset-filter">Reset Filter</a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Payment History Table -->
    <div class="payment-history">
        <div style="overflow-x: auto;">
            <table class="payment-history-table">
                <thead>
                    <tr>
                        <th>No. Referensi</th>
                        <th>Tanggal</th>
                        <th>Jenis Retribusi</th>
                        <th>Kategori</th>
                        <th>Metode</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($history_result) > 0) {
                        while ($payment = mysqli_fetch_assoc($history_result)) {
                            $status_class = '';
                            $status_text = '';

                            switch ($payment['status']) {
                                case 'pending':
                                    $status_class = 'status-proses';
                                    $status_text = 'Diproses';
                                    break;
                                case 'berhasil':
                                    $status_class = 'status-lunas';
                                    $status_text = 'Berhasil';
                                    break;
                                case 'gagal':
                                    $status_class = 'status-jatuh-tempo';
                                    $status_text = 'Gagal';
                                    break;
                            }

                            // Get payment details
                            $nomor_referensi = $payment['nomor_referensi'];
                            $tanggal_bayar = date('d-m-Y H:i', strtotime($payment['tanggal_bayar']));
                            $nama_retribusi = $payment['nama_retribusi'];
                            $kategori = ucfirst($payment['periode']);
                            $metode = ucfirst(str_replace('_', ' ', $payment['metode_pembayaran']));
                            $jumlah = number_format($payment['jumlah_bayar'], 0, ',', '.');

                            echo "<tr>";
                            echo "<td>{$nomor_referensi}</td>";
                            echo "<td>{$tanggal_bayar}</td>";
                            echo "<td>{$nama_retribusi}</td>";
                            echo "<td><span class='tag tag-{$payment['periode']}'>{$kategori}</span></td>";
                            echo "<td>{$metode}</td>";
                            echo "<td>Rp {$jumlah}</td>";
                            echo "<td><span class='tag {$status_class}'>{$status_text}</span></td>";
                            echo "<td><a href='{$base_path}user/pembayaran_detail.php?ref={$nomor_referensi}' class='view-btn'>Detail</a>";
                            
                            // Add option to download receipt if payment successful
                            if ($payment['status'] == 'berhasil') {
                                echo " <a href='{$base_path}user/cetak_bukti.php?ref={$nomor_referensi}' class='download-btn' target='_blank'><i class='fas fa-download'></i></a>";
                            }
                            
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Tidak ada data pembayaran yang ditemukan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a href="?page=<?php echo ($page - 1); ?>&status=<?php echo $status_filter; ?>&date_range=<?php echo $date_filter; ?>&search=<?php echo urlencode($search_term); ?>">&laquo;</a></li>
                    <?php else: ?>
                        <li class="disabled"><span>&laquo;</span></li>
                    <?php endif; ?>

                    <?php
                    // Calculate range of pages to show
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    // Always show first page
                    if ($start_page > 1) {
                        echo "<li><a href='?page=1&status={$status_filter}&date_range={$date_filter}&search=" . urlencode($search_term) . "'>1</a></li>";
                        if ($start_page > 2) {
                            echo "<li class='disabled'><span>...</span></li>";
                        }
                    }

                    // Show page links
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo "<li class='active'><span>{$i}</span></li>";
                        } else {
                            echo "<li><a href='?page={$i}&status={$status_filter}&date_range={$date_filter}&search=" . urlencode($search_term) . "'>{$i}</a></li>";
                        }
                    }

                    // Always show last page
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo "<li class='disabled'><span>...</span></li>";
                        }
                        echo "<li><a href='?page={$total_pages}&status={$status_filter}&date_range={$date_filter}&search=" . urlencode($search_term) . "'>{$total_pages}</a></li>";
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <li><a href="?page=<?php echo ($page + 1); ?>&status=<?php echo $status_filter; ?>&date_range=<?php echo $date_filter; ?>&search=<?php echo urlencode($search_term); ?>">&raquo;</a></li>
                    <?php else: ?>
                        <li class="disabled"><span>&raquo;</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Export Options -->
        <div class="export-options">
            <h3>Ekspor Data</h3>
            <div class="export-buttons">
                <a href="<?php echo $base_path; ?>user/export_pembayaran.php?format=pdf&status=<?php echo $status_filter; ?>&date_range=<?php echo $date_filter; ?>&search=<?php echo urlencode($search_term); ?>" class="export-btn pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="<?php echo $base_path; ?>user/export_pembayaran.php?format=excel&status=<?php echo $status_filter; ?>&date_range=<?php echo $date_filter; ?>&search=<?php echo urlencode($search_term); ?>" class="export-btn excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="<?php echo $base_path; ?>user/export_pembayaran.php?format=csv&status=<?php echo $status_filter; ?>&date_range=<?php echo $date_filter; ?>&search=<?php echo urlencode($search_term); ?>" class="export-btn csv">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Payment Tips -->
    <div class="info-box payment-tips">
        <h3><i class="fas fa-lightbulb"></i> Tips Pembayaran</h3>
        <ul>
            <li>Selalu simpan bukti pembayaran untuk validasi jika terjadi masalah.</li>
            <li>Pembayaran akan diverifikasi dalam 1x24 jam kerja.</li>
            <li>Anda dapat memilih beberapa tagihan untuk dibayar sekaligus.</li>
            <li>Jika status pembayaran "Diproses" selama lebih dari 24 jam, silakan hubungi administrator.</li>
        </ul>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle filter form submission
        document.getElementById('filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const status = document.getElementById('status').value;
            const dateRange = document.getElementById('date_range').value;
            const search = document.querySelector('input[name="search"]').value;
            
            // Build URL with query parameters
            let url = '<?php echo $base_path; ?>user/riwayat_pembayaran.php?';
            if (status !== 'all') url += 'status=' + status + '&';
            if (dateRange !== 'all') url += 'date_range=' + dateRange + '&';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            
            // Remove trailing & if present
            if (url.endsWith('&')) {
                url = url.slice(0, -1);
            }
            
            // Navigate to the URL
            window.location.href = url;
        });
    });
</script>

<?php
// Include footer
include $base_path . 'includes/footer.php';
?>
<style>
    /* ==========================================================================
   PAYMENT HISTORY PAGE STYLES
   ========================================================================== */

/* Main Container */
.main-container {
    padding: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    margin-bottom: 2rem;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
}

.page-header h2 {
    color: #2c3e50;
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #7f8c8d;
    font-size: 1rem;
    margin: 0;
}

/* Stats Container */
.stats-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
    justify-content: space-between;
}

.stat-box {
    flex: 1;
    min-width: 200px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    padding: 1.2rem;
    display: flex;
    align-items: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.2rem;
}

.stat-icon.success {
    background-color: #2ecc71;
}

.stat-icon.warning {
    background-color: #f39c12;
}

.stat-icon.danger {
    background-color: #e74c3c;
}

.stat-icon.primary {
    background-color: #9b59b6;
}

.stat-info h3 {
    margin: 0;
    font-size: 0.85rem;
    color: #7f8c8d;
    font-weight: 500;
}

.stat-info p {
    margin: 0.3rem 0 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Filter Section */
.filter-section {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1.2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.filter-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}

.filter-item {
    flex: 1;
    min-width: 180px;
}

.filter-item label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: #5d6778;
    font-weight: 500;
}

.filter-item select, 
.filter-item input[type="text"] {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
    font-size: 0.9rem;
    color: #333;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.filter-item select:focus, 
.filter-item input[type="text"]:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
    outline: none;
}

.search-box {
    position: relative;
    flex: 2;
}

.search-box input {
    padding-right: 40px;
}

.search-btn {
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    width: 40px;
    background-color: #3498db;
    border: none;
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
    color: white;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.search-btn:hover {
    background-color: #2980b9;
}

.reset-filter {
    display: inline-block;
    padding: 0.6rem 1rem;
    background-color: #e74c3c;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: background-color 0.2s ease;
    text-align: center;
}

.reset-filter:hover {
    background-color: #c0392b;
}

/* Payment History Table */
.payment-history {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.payment-history-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5rem;
}

.payment-history-table th, 
.payment-history-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eaeaea;
}

.payment-history-table th {
    background-color: #f8f9fa;
    color: #5d6778;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.payment-history-table tr:last-child td {
    border-bottom: none;
}

.payment-history-table tr:hover {
    background-color: rgba(52, 152, 219, 0.05);
}

/* Status Tags */
.tag {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
    text-align: center;
}

.status-lunas {
    background-color: rgba(46, 204, 113, 0.15);
    color: #2ecc71;
}

.status-proses {
    background-color: rgba(243, 156, 18, 0.15);
    color: #f39c12;
}

.status-jatuh-tempo {
    background-color: rgba(231, 76, 60, 0.15);
    color: #e74c3c;
}

.tag-bulanan {
    background-color: rgba(52, 152, 219, 0.15);
    color: #3498db;
}

.tag-tahunan {
    background-color: rgba(156, 39, 176, 0.15);
    color: #9c27b0;
}

.tag-harian {
    background-color: rgba(0, 150, 136, 0.15);
    color: #009688;
}

/* Action Buttons */
.view-btn, .download-btn {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    font-size: 0.8rem;
    text-decoration: none;
    margin-right: 0.5rem;
    transition: all 0.2s ease;
}

.view-btn {
    background-color: #3498db;
    color: white;
}

.view-btn:hover {
    background-color: #2980b9;
}

.download-btn {
    background-color: #27ae60;
    color: white;
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.download-btn:hover {
    background-color: #219653;
}

/* Pagination */
.pagination-container {
    margin: 1.5rem 0;
}

.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    justify-content: center;
}

.pagination li {
    margin: 0 0.2rem;
}

.pagination li a, 
.pagination li span {
    display: block;
    padding: 0.5rem 0.8rem;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    min-width: 35px;
}

.pagination li a {
    background-color: #f8f9fa;
    color: #3498db;
    border: 1px solid #dee2e6;
    transition: all 0.2s ease;
}

.pagination li a:hover {
    background-color: #e9ecef;
    border-color: #cbd3da;
}

.pagination li.active span {
    background-color: #3498db;
    color: white;
    border: 1px solid #3498db;
}

.pagination li.disabled span {
    background-color: #f8f9fa;
    color: #adb5bd;
    border: 1px solid #dee2e6;
    cursor: not-allowed;
}

/* Export Options */
.export-options {
    margin-top: 2rem;
    border-top: 1px solid #eaeaea;
    padding-top: 1.5rem;
}

.export-options h3 {
    font-size: 1.1rem;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.export-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.export-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.7rem 1.2rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    color: white;
    transition: all 0.2s ease;
}

.export-btn i {
    margin-right: 0.5rem;
}

.export-btn.pdf {
    background-color: #e74c3c;
}

.export-btn.pdf:hover {
    background-color: #c0392b;
}

.export-btn.excel {
    background-color: #27ae60;
}

.export-btn.excel:hover {
    background-color: #219653;
}

.export-btn.csv {
    background-color: #f39c12;
}

.export-btn.csv:hover {
    background-color: #d35400;
}

/* Tips Box */
.info-box {
    background-color: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
}

.payment-tips h3 {
    color: #2c3e50;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.payment-tips h3 i {
    color: #f39c12;
    margin-right: 0.5rem;
}

.payment-tips ul {
    padding-left: 1.5rem;
    margin: 0;
}

.payment-tips li {
    margin-bottom: 0.5rem;
    color: #5d6778;
    line-height: 1.5;
}

.payment-tips li:last-child {
    margin-bottom: 0;
}

/* Empty state */
.text-center {
    text-align: center;
    padding: 2rem 0;
    color: #7f8c8d;
    font-style: italic;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .stat-box {
        min-width: calc(50% - 1rem);
    }
    
    .filter-item {
        min-width: calc(50% - 1rem);
    }
}

@media (max-width: 768px) {
    .stats-container {
        flex-direction: column;
    }
    
    .stat-box {
        min-width: 100%;
    }
    
    .filter-item {
        min-width: 100%;
    }
    
    .search-box {
        flex: 1;
    }
    
    .payment-history-table th, 
    .payment-history-table td {
        padding: 0.8rem 0.5rem;
        font-size: 0.9rem;
    }
    
    .export-buttons {
        flex-direction: column;
    }
    
    .export-btn {
        text-align: center;
        justify-content: center;
    }
}

/* Print media query to hide non-essential elements when printing */
@media print {
    .filter-section,
    .pagination-container,
    .export-options,
    .payment-tips,
    .header,
    .footer {
        display: none !important;
    }
    
    .main-container {
        padding: 0;
    }
    
    .payment-history {
        box-shadow: none;
        padding: 0;
    }
    
    .payment-history-table th,
    .payment-history-table td {
        padding: 0.5rem;
    }
    
    .view-btn,
    .download-btn {
        display: none;
    }
}
</style>