<?php
// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "Transparansi Anggaran Desa";

// Get filter parameters with proper sanitization
$tahun = isset($_GET['tahun']) ? mysqli_real_escape_string($koneksi, $_GET['tahun']) : date('Y');
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : 'semua';

// Prepare statements for better security and performance
// Get years for dropdown
$stmt_tahun = mysqli_prepare($koneksi, "SELECT DISTINCT tahun_anggaran FROM anggaran_desa ORDER BY tahun_anggaran DESC");
mysqli_stmt_execute($stmt_tahun);
$result_tahun = mysqli_stmt_get_result($stmt_tahun);

// Get categories for dropdown
$stmt_kategori = mysqli_prepare($koneksi, "SELECT DISTINCT kategori FROM detail_anggaran ORDER BY kategori ASC");
mysqli_stmt_execute($stmt_kategori);
$result_kategori = mysqli_stmt_get_result($stmt_kategori);

// Get total budget info
$stmt_total = mysqli_prepare($koneksi, "SELECT 
                    SUM(total_anggaran) as total_anggaran 
                FROM 
                    anggaran_desa 
                WHERE 
                    tahun_anggaran = ? AND
                    status = 'disetujui'");
mysqli_stmt_bind_param($stmt_total, "s", $tahun);
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_data = mysqli_fetch_assoc($result_total);
$total_anggaran = $total_data['total_anggaran'] ?? 0;

// Get anggaran by source
$stmt_sources = mysqli_prepare($koneksi, "SELECT 
                    kategori,
                    SUM(jumlah_anggaran) as jumlah 
                FROM 
                    detail_anggaran da 
                JOIN 
                    anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                WHERE 
                    ad.tahun_anggaran = ? AND
                    ad.status = 'disetujui' AND
                    da.kategori IN ('Dana Desa', 'Pendapatan Asli Desa', 'Alokasi Dana Desa')
                GROUP BY 
                    kategori");
mysqli_stmt_bind_param($stmt_sources, "s", $tahun);
mysqli_stmt_execute($stmt_sources);
$result_sources = mysqli_stmt_get_result($stmt_sources);

// Get realization total
$stmt_realisasi = mysqli_prepare($koneksi, "SELECT 
                        SUM(jumlah_realisasi) as total_realisasi 
                    FROM 
                        detail_anggaran da 
                    JOIN 
                        anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                    WHERE 
                        ad.tahun_anggaran = ? AND
                        ad.status = 'disetujui'");
mysqli_stmt_bind_param($stmt_realisasi, "s", $tahun);
mysqli_stmt_execute($stmt_realisasi);
$result_realisasi = mysqli_stmt_get_result($stmt_realisasi);
$realisasi_data = mysqli_fetch_assoc($result_realisasi);
$total_realisasi = $realisasi_data['total_realisasi'] ?? 0;
$persentase_realisasi = $total_anggaran > 0 ? ($total_realisasi / $total_anggaran) * 100 : 0;

// Get detailed budget items with prepared statement
$query_detail = "SELECT 
                    da.kategori,
                    da.sub_kategori,
                    da.uraian,
                    da.jumlah_anggaran,
                    da.jumlah_realisasi,
                    CASE 
                        WHEN da.jumlah_anggaran > 0 THEN (da.jumlah_realisasi / da.jumlah_anggaran) * 100 
                        ELSE 0 
                    END as persentase,
                    CASE 
                        WHEN da.jumlah_realisasi = 0 THEN 'Belum' 
                        WHEN da.jumlah_realisasi >= da.jumlah_anggaran THEN 'Selesai' 
                        ELSE 'Berjalan' 
                    END as status
                FROM 
                    detail_anggaran da 
                JOIN 
                    anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                WHERE 
                    ad.tahun_anggaran = ? AND
                    ad.status = 'disetujui'";

$params = array($tahun);
$types = "s";

// Add category filter if selected
if ($kategori != 'semua') {
    $query_detail .= " AND da.kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

$query_detail .= " ORDER BY da.kategori, da.uraian";

$stmt_detail = mysqli_prepare($koneksi, $query_detail);
// Dynamically bind parameters
if (count($params) > 0) {
    // Create a reference array for bind_param
    $refs = array();
    $refs[] = &$types;
    for ($i = 0; $i < count($params); $i++) {
        $refs[] = &$params[$i];
    }
    call_user_func_array(array($stmt_detail, 'bind_param'), $refs);
}
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

// Get chart data (new section for visualization)
$stmt_chart = mysqli_prepare($koneksi, "SELECT 
                    da.kategori,
                    SUM(da.jumlah_anggaran) as total_anggaran,
                    SUM(da.jumlah_realisasi) as total_realisasi
                FROM 
                    detail_anggaran da 
                JOIN 
                    anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                WHERE 
                    ad.tahun_anggaran = ? AND
                    ad.status = 'disetujui'
                GROUP BY 
                    da.kategori");
mysqli_stmt_bind_param($stmt_chart, "s", $tahun);
mysqli_stmt_execute($stmt_chart);
$result_chart = mysqli_stmt_get_result($stmt_chart);

// Create chart data arrays
$chart_labels = [];
$chart_anggaran = [];
$chart_realisasi = [];

while ($chart_data = mysqli_fetch_assoc($result_chart)) {
    $chart_labels[] = $chart_data['kategori'];
    $chart_anggaran[] = $chart_data['total_anggaran'];
    $chart_realisasi[] = $chart_data['total_realisasi'];
}

// Include header
include '../includes/header.php';
?>

<main>
    <section class="page-header">
        <h2>Transparansi Anggaran Desa</h2>
        <p>Pantau penggunaan anggaran desa secara transparan dengan visualisasi data yang mudah dipahami. 
           Masyarakat dapat melihat dari mana sumber anggaran dan untuk apa saja dana desa digunakan.</p>
    </section>

    <section class="content-section">
        <div class="anggaran-filter">
            <form method="GET" action="transparansi.php">
                <select name="tahun" class="filter-select">
                    <?php while ($row = mysqli_fetch_assoc($result_tahun)): ?>
                        <option value="<?= htmlspecialchars($row['tahun_anggaran']) ?>" <?= $tahun == $row['tahun_anggaran'] ? 'selected' : '' ?>>
                            Tahun <?= htmlspecialchars($row['tahun_anggaran']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select name="kategori" class="filter-select">
                    <option value="semua" <?= $kategori == 'semua' ? 'selected' : '' ?>>Semua Kategori</option>
                    <?php while ($row = mysqli_fetch_assoc($result_kategori)): ?>
                        <option value="<?= htmlspecialchars($row['kategori']) ?>" <?= $kategori == $row['kategori'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['kategori']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-outline">Filter</button>
            </form>
        </div>

        <div class="anggaran-overview">
            <div class="anggaran-card">
                <h4>Total Anggaran</h4>
                <div class="value">Rp <?= number_format($total_anggaran, 0, ',', '.') ?></div>
                <div class="description">Anggaran Desa Tahun <?= htmlspecialchars($tahun) ?></div>
            </div>
            
            <?php 
            // Reset pointer for sources result
            mysqli_data_seek($result_sources, 0);
            while ($source = mysqli_fetch_assoc($result_sources)): 
                $percentage = $total_anggaran > 0 ? ($source['jumlah'] / $total_anggaran) * 100 : 0;
            ?>
                <div class="anggaran-card">
                    <h4><?= htmlspecialchars($source['kategori']) ?></h4>
                    <div class="value">Rp <?= number_format($source['jumlah'], 0, ',', '.') ?></div>
                    <div class="description"><?= number_format($percentage, 1) ?>% dari total anggaran</div>
                </div>
            <?php endwhile; ?>
        </div>

        <h3>Realisasi Anggaran</h3>
        <div class="anggaran-card">
            <h4>Realisasi Anggaran</h4>
            <div class="value">
                Rp <?= number_format($total_realisasi, 0, ',', '.') ?> 
                (<?= number_format($persentase_realisasi, 1) ?>%)
            </div>
            <div class="progress-container">
                <div class="progress-bar" style="width: <?= min($persentase_realisasi, 100) ?>%"></div>
            </div>
            <div class="description">Update terakhir: <?= date('d F Y') ?></div>
        </div>

        <div class="chart-container">
            <!-- Chart.js implementation -->
            <canvas id="anggaranChart" width="800" height="400"></canvas>
        </div>

        <h3>Daftar Rincian Anggaran</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kategori</th>
                        <th>Uraian</th>
                        <th>Anggaran</th>
                        <th>Realisasi</th>
                        <th>Persentase</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if (mysqli_num_rows($result_detail) > 0):
                        while ($row = mysqli_fetch_assoc($result_detail)): 
                            $statusClass = '';
                            switch ($row['status']) {
                                case 'Belum':
                                    $statusClass = 'status-pending';
                                    break;
                                case 'Berjalan':
                                    $statusClass = 'status-processing';
                                    break;
                                case 'Selesai':
                                    $statusClass = 'status-completed';
                                    break;
                            }
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['kategori']) ?></td>
                            <td><?= htmlspecialchars($row['uraian']) ?></td>
                            <td class="number">Rp <?= number_format($row['jumlah_anggaran'], 0, ',', '.') ?></td>
                            <td class="number">Rp <?= number_format($row['jumlah_realisasi'], 0, ',', '.') ?></td>
                            <td class="number"><?= number_format($row['persentase'], 1) ?>%</td>
                            <td><span class="status <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada data anggaran untuk filter yang dipilih</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="cetak-anggaran.php?tahun=<?= htmlspecialchars($tahun) ?>&kategori=<?= htmlspecialchars($kategori) ?>" class="btn" target="_blank">Unduh Laporan Lengkap</a>
            <a href="detail-anggaran.php?tahun=<?= htmlspecialchars($tahun) ?>" class="btn btn-outline">Lihat Detail</a>
        </div>
    </section>
</main>

<!-- Add Chart.js script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('anggaranChart').getContext('2d');
    
    // Chart data from PHP
    const labels = <?= json_encode($chart_labels) ?>;
    const anggaranData = <?= json_encode($chart_anggaran) ?>;
    const realisasiData = <?= json_encode($chart_realisasi) ?>;
    
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Anggaran',
                    data: anggaranData,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Realisasi',
                    data: realisasiData,
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + 
                                   context.raw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
// Close all prepared statements
mysqli_stmt_close($stmt_tahun);
mysqli_stmt_close($stmt_kategori);
mysqli_stmt_close($stmt_total);
mysqli_stmt_close($stmt_sources);
mysqli_stmt_close($stmt_realisasi);
mysqli_stmt_close($stmt_detail);
mysqli_stmt_close($stmt_chart);

// Include footer
include '../includes/footer.php';
?>