<?php
// Include necessary files
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login untuk mengakses halaman ini.';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['anggaran_error'] = 'ID Anggaran tidak valid.';
    redirect('anggaran.php');
}

$anggaran_id = (int)$_GET['id'];

// Get anggaran data
$anggaran_query = "SELECT ad.*, u.nama as created_by_name, 
                   (SELECT COUNT(*) FROM detail_anggaran WHERE anggaran_id = ad.anggaran_id) as jumlah_detail
                   FROM anggaran_desa ad
                   LEFT JOIN users u ON ad.created_by = u.user_id
                   WHERE ad.anggaran_id = $anggaran_id";
$anggaran_result = mysqli_query($koneksi, $anggaran_query);

if (mysqli_num_rows($anggaran_result) == 0) {
    $_SESSION['anggaran_error'] = 'Data anggaran tidak ditemukan.';
    redirect('anggaran.php');
}

$anggaran = mysqli_fetch_assoc($anggaran_result);

// Get details data
$details_query = "SELECT * FROM detail_anggaran WHERE anggaran_id = $anggaran_id 
                  ORDER BY kategori, sub_kategori";
$details_result = mysqli_query($koneksi, $details_query);

// Get realization data (if any)
$realisasi_query = "SELECT r.*, u.nama as created_by_name, da.uraian
                    FROM realisasi_anggaran r
                    JOIN detail_anggaran da ON r.detail_id = da.detail_id
                    LEFT JOIN users u ON r.created_by = u.user_id
                    WHERE da.anggaran_id = $anggaran_id
                    ORDER BY r.tanggal_realisasi DESC";
$realisasi_result = mysqli_query($koneksi, $realisasi_query);

// Format periode
$periode_text = "";
switch ($anggaran['periode']) {
    case 'tahunan':
        $periode_text = "Tahunan";
        break;
    case 'semester1':
        $periode_text = "Semester 1";
        break;
    case 'semester2':
        $periode_text = "Semester 2";
        break;
    case 'triwulan1':
        $periode_text = "Triwulan 1";
        break;
    case 'triwulan2':
        $periode_text = "Triwulan 2";
        break;
    case 'triwulan3':
        $periode_text = "Triwulan 3";
        break;
    case 'triwulan4':
        $periode_text = "Triwulan 4";
        break;
    default:
        $periode_text = ucfirst($anggaran['periode']);
}

// Format status
$status_class = "";
$status_text = "";
switch ($anggaran['status']) {
    case 'rencana':
        $status_class = "status-pending";
        $status_text = "Rencana";
        break;
    case 'disetujui':
        $status_class = "status-processing";
        $status_text = "Disetujui";
        break;
    case 'realisasi':
        $status_class = "status-processing";
        $status_text = "Realisasi";
        break;
    case 'laporan_akhir':
        $status_class = "status-completed";
        $status_text = "Laporan Akhir";
        break;
    default:
        $status_class = "status-pending";
        $status_text = "Rencana";
}

// Set page title
$page_title = "Detail Anggaran";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">
    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Detail Anggaran Desa</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo;
                <a href="anggaran.php">Transparansi Anggaran</a> &raquo;
                Detail Anggaran
            </nav>
        </div>

        <!-- Action Buttons -->
        <div class="button-container">
            <a href="anggaran.php" class="btn btn-secondary">
                <span class="btn-icon">&#8592;</span> Kembali
            </a>

            <?php if ($anggaran['status'] != 'laporan_akhir' && ($user_role == 'admin' || $user_role == 'kepala_desa')): ?>
                <a href="anggaran_edit.php?id=<?php echo $anggaran_id; ?>" class="btn btn-primary">
                    <span class="btn-icon">&#9998;</span> Edit Anggaran
                </a>
            <?php endif; ?>

            <?php if ($user_role == 'admin' && $anggaran['status'] == 'disetujui'): ?>
                <a href="realisasi_tambah.php?anggaran_id=<?php echo $anggaran_id; ?>" class="btn btn-success">
                    <span class="btn-icon">&#43;</span> Tambah Realisasi
                </a>
            <?php endif; ?>

            <a href="export_anggaran_detail.php?id=<?php echo $anggaran_id; ?>" class="btn btn-info">
                <span class="btn-icon">&#8615;</span> Export Excel
            </a>

            <button onclick="window.print()" class="btn btn-secondary">
                <span class="btn-icon">🖨️</span> Cetak
            </button>
        </div>

        <!-- Anggaran Overview -->
        <div class="data-card">
            <div class="card-header">
                <h3>Informasi Umum Anggaran</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="detail-table">
                            <tr>
                                <th width="40%">Tahun Anggaran</th>
                                <td width="60%"><?php echo $anggaran['tahun_anggaran']; ?></td>
                            </tr>
                            <tr>
                                <th>Periode</th>
                                <td><?php echo $periode_text; ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            </tr>
                            <tr>
                                <th>Total Anggaran</th>
                                <td><strong>Rp <?php echo number_format($anggaran['total_anggaran'], 0, ',', '.'); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="detail-table">
                            <tr>
                                <th width="40%">Dibuat Oleh</th>
                                <td width="60%"><?php echo htmlspecialchars($anggaran['created_by_name'] ?? 'Admin'); ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal Dibuat</th>
                                <td><?php echo date('d-m-Y H:i', strtotime($anggaran['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Terakhir Diupdate</th>
                                <td><?php echo date('d-m-Y H:i', strtotime($anggaran['updated_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Dokumen Anggaran</th>
                                <td>
                                    <?php if (!empty($anggaran['dokumen_anggaran'])): ?>
                                        <a href="../uploads/anggaran/<?php echo $anggaran['dokumen_anggaran']; ?>" target="_blank" class="btn-sm btn-info">
                                            Lihat Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Tidak ada dokumen</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Summary -->
        <?php if ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir'): ?>
            <div class="data-card mt-4">
                <div class="card-header">
                    <h3>Ringkasan Realisasi Anggaran</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Get realization summary
                    $summary_query = "SELECT 
                                    SUM(da.jumlah_anggaran) as total_anggaran,
                                    SUM(da.jumlah_realisasi) as total_realisasi,
                                    (SUM(da.jumlah_realisasi) / SUM(da.jumlah_anggaran)) * 100 as persentase
                                FROM detail_anggaran da
                                WHERE da.anggaran_id = $anggaran_id";
                    $summary_result = mysqli_query($koneksi, $summary_query);
                    $summary = mysqli_fetch_assoc($summary_result);

                    $total_anggaran = $summary['total_anggaran'] ?? 0;
                    $total_realisasi = $summary['total_realisasi'] ?? 0;
                    $persentase = $summary['persentase'] ?? 0;
                    $sisa_anggaran = $total_anggaran - $total_realisasi;
                    ?>

                    <div class="summary-container">
                        <div class="summary-card">
                            <div class="summary-title">Total Anggaran</div>
                            <div class="summary-value">Rp <?php echo number_format($total_anggaran, 0, ',', '.'); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-title">Total Realisasi</div>
                            <div class="summary-value">Rp <?php echo number_format($total_realisasi, 0, ',', '.'); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-title">Sisa Anggaran</div>
                            <div class="summary-value">Rp <?php echo number_format($sisa_anggaran, 0, ',', '.'); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-title">Persentase Realisasi</div>
                            <div class="summary-value"><?php echo number_format($persentase, 2); ?>%</div>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo min(100, $persentase); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Detail Anggaran -->
        <div class="data-card mt-4">
            <div class="card-header">
                <h3>Detail Rincian Anggaran</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kategori</th>
                                <th width="15%">Sub Kategori</th>
                                <th width="25%">Uraian</th>
                                <th width="15%">Jumlah Anggaran</th>
                                <?php if ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir'): ?>
                                    <th width="15%">Jumlah Realisasi</th>
                                    <th width="10%">Persentase</th>
                                <?php else: ?>
                                    <th width="25%">Keterangan</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($details_result) > 0) {
                                $no = 1;
                                $current_kategori = '';
                                $subtotal_anggaran = 0;
                                $subtotal_realisasi = 0;

                                while ($detail = mysqli_fetch_assoc($details_result)) {
                                    // Check if this is a new category
                                    $new_kategori = ($current_kategori != $detail['kategori']);

                                    // If new category and not the first row, output subtotal row
                                    if ($new_kategori && $current_kategori != '') {
                                        echo '<tr class="subtotal-row">';
                                        echo '<td colspan="4" class="text-right"><strong>Sub Total ' . $current_kategori . '</strong></td>';
                                        echo '<td class="text-right"><strong>Rp ' . number_format($subtotal_anggaran, 0, ',', '.') . '</strong></td>';

                                        if ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir') {
                                            $subtotal_percentage = ($subtotal_anggaran > 0) ? ($subtotal_realisasi / $subtotal_anggaran * 100) : 0;
                                            echo '<td class="text-right"><strong>Rp ' . number_format($subtotal_realisasi, 0, ',', '.') . '</strong></td>';
                                            echo '<td class="text-right"><strong>' . number_format($subtotal_percentage, 2) . '%</strong></td>';
                                        } else {
                                            echo '<td></td>';
                                        }

                                        echo '</tr>';

                                        // Reset subtotal
                                        $subtotal_anggaran = 0;
                                        $subtotal_realisasi = 0;
                                    }

                                    // Update current category and add to subtotal
                                    $current_kategori = $detail['kategori'];
                                    $subtotal_anggaran += $detail['jumlah_anggaran'];
                                    $subtotal_realisasi += $detail['jumlah_realisasi'];

                                    // Output detail row
                                    echo '<tr' . ($new_kategori ? ' class="new-kategori"' : '') . '>';
                                    echo '<td>' . $no++ . '</td>';
                                    echo '<td>' . htmlspecialchars($detail['kategori']) . '</td>';
                                    echo '<td>' . htmlspecialchars($detail['sub_kategori'] ?: '-') . '</td>';
                                    echo '<td>' . htmlspecialchars($detail['uraian']) . '</td>';
                                    echo '<td class="text-right">Rp ' . number_format($detail['jumlah_anggaran'], 0, ',', '.') . '</td>';

                                    if ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir') {
                                        $percentage = ($detail['jumlah_anggaran'] > 0) ? ($detail['jumlah_realisasi'] / $detail['jumlah_anggaran'] * 100) : 0;
                                        $percentage_class = '';

                                        if ($percentage < 50) {
                                            $percentage_class = 'text-danger';
                                        } elseif ($percentage < 90) {
                                            $percentage_class = 'text-warning';
                                        } else {
                                            $percentage_class = 'text-success';
                                        }

                                        echo '<td class="text-right">Rp ' . number_format($detail['jumlah_realisasi'], 0, ',', '.') . '</td>';
                                        echo '<td class="text-right ' . $percentage_class . '">' . number_format($percentage, 2) . '%</td>';
                                    } else {
                                        echo '<td>' . htmlspecialchars($detail['keterangan'] ?: '-') . '</td>';
                                    }

                                    echo '</tr>';
                                }

                                // Output last subtotal row
                                echo '<tr class="subtotal-row">';
                                echo '<td colspan="4" class="text-right"><strong>Sub Total ' . $current_kategori . '</strong></td>';
                                echo '<td class="text-right"><strong>Rp ' . number_format($subtotal_anggaran, 0, ',', '.') . '</strong></td>';

                                if ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir') {
                                    $subtotal_percentage = ($subtotal_anggaran > 0) ? ($subtotal_realisasi / $subtotal_anggaran * 100) : 0;
                                    echo '<td class="text-right"><strong>Rp ' . number_format($subtotal_realisasi, 0, ',', '.') . '</strong></td>';
                                    echo '<td class="text-right"><strong>' . number_format($subtotal_percentage, 2) . '%</strong></td>';
                                } else {
                                    echo '<td></td>';
                                }

                                echo '</tr>';

                                // Total row
                                echo '<tr class="total-row">';
                                echo '<td colspan="4" class="text-right"><strong>TOTAL ANGGARAN</strong></td>';
                                echo '<td class="text-right"><strong>Rp ' . number_format($anggaran['total_anggaran'], 0, ',', '.') . '</strong></td>';

                                if ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir') {
                                    $total_percentage = ($anggaran['total_anggaran'] > 0) ? ($total_realisasi / $anggaran['total_anggaran'] * 100) : 0;
                                    echo '<td class="text-right"><strong>Rp ' . number_format($total_realisasi, 0, ',', '.') . '</strong></td>';
                                    echo '<td class="text-right"><strong>' . number_format($total_percentage, 2) . '%</strong></td>';
                                } else {
                                    echo '<td></td>';
                                }

                                echo '</tr>';
                            } else {
                                echo '<tr><td colspan="' . ($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir' ? '7' : '6') . '" class="text-center">Tidak ada detail anggaran yang ditemukan</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Realisasi Anggaran -->
        <?php if (($anggaran['status'] == 'realisasi' || $anggaran['status'] == 'laporan_akhir') && mysqli_num_rows($realisasi_result) > 0): ?>
            <div class="data-card mt-4">
                <div class="card-header">
                    <h3>Riwayat Realisasi Anggaran</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">Tanggal</th>
                                    <th width="30%">Uraian</th>
                                    <th width="15%">Jumlah</th>
                                    <th width="20%">Keterangan</th>
                                    <th width="15%">Bukti</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($realisasi = mysqli_fetch_assoc($realisasi_result)) {
                                    echo '<tr>';
                                    echo '<td>' . $no++ . '</td>';
                                    echo '<td>' . date('d-m-Y', strtotime($realisasi['tanggal_realisasi'])) . '</td>';
                                    echo '<td>' . htmlspecialchars($realisasi['uraian']) . '</td>';
                                    echo '<td class="text-right">Rp ' . number_format($realisasi['jumlah'], 0, ',', '.') . '</td>';
                                    echo '<td>' . htmlspecialchars($realisasi['keterangan'] ?: '-') . '</td>';
                                    echo '<td>';

                                    if (!empty($realisasi['bukti_dokumen'])) {
                                        echo '<a href="../uploads/realisasi/' . $realisasi['bukti_dokumen'] . '" target="_blank" class="btn-sm btn-info">Lihat Bukti</a>';
                                    } else {
                                        echo '<span class="text-muted">Tidak ada dokumen</span>';
                                    }

                                    echo '</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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

    /* Detail styling */
    .detail-table {
        width: 100%;
        margin-bottom: 1rem;
    }

    .detail-table th,
    .detail-table td {
        padding: 10px;
        border-bottom: 1px solid #efefef;
    }

    .detail-table th {
        font-weight: 600;
        color: #495057;
    }

    /* Card styling */
    .data-card {
        background-color: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }

    .card-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #efefef;
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .card-body {
        padding: 20px;
    }

    .mt-4 {
        margin-top: 1.5rem;
    }

    /* Row styling */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
    }

    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding-right: 10px;
        padding-left: 10px;
    }

    /* Summary cards */
    .summary-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .summary-card {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .summary-title {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 5px;
    }

    .summary-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: #343a40;
    }

    /* Progress bar */
    .progress-bar {
        height: 8px;
        background-color: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress {
        height: 100%;
        background-color: #28a745;
        border-radius: 4px;
    }

    /* Table styling */
    .table-responsive {
        overflow-x: auto;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th,
    .admin-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #efefef;
    }

    .admin-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    /* Button styling */
    .button-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid transparent;
    }

    .btn-sm {
        padding: 4px 10px;
        font-size: 0.875rem;
        border-radius: 3px;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0069d9;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #218838;
    }

    .btn-info {
        background-color: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        background-color: #138496;
    }

    .btn-icon {
        margin-right: 5px;
    }

    /* Status badges */
    .status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-processing {
        background-color: #cce5ff;
        color: #004085;
    }

    .status-completed {
        background-color: #d4edda;
        color: #155724;
    }

    /* Special rows */
    .new-kategori {
        border-top: 2px solid #dee2e6;
    }

    .subtotal-row {
        background-color: #f8f9fa;
    }

    .total-row {
        background-color: #e9ecef;
        font-weight: bold;
    }

    /* Text colors */
    .text-danger {
        color: #dc3545 !important;
    }

    .text-warning {
        color: #ffc107 !important;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-muted {
        color: #6c757d !important;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .row {
            flex-direction: column;
        }

        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .summary-container {
            grid-template-columns: 1fr;
        }
    }

    /* Print styling */
    @media print {

        .admin-sidebar,
        .button-container,
        .breadcrumb {
            display: none !important;
        }

        .admin-container {
            display: block;
        }

        .admin-content {
            padding: 0;
        }

        .data-card {
            box-shadow: none;
            margin-bottom: 15px;
            break-inside: avoid;
        }

        .admin-table th,
        .admin-table td {
            padding: 8px;
        }

        .admin-header h2 {
            font-size: 1.2rem;
        }

        .card-header h3 {
            font-size: 1rem;
        }
    }
</style>

<?php
// Include footer
include '../includes/admin-footer.php';
?>