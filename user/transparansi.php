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

// Get filter parameters
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'semua';

// Get years for dropdown
$query_tahun = "SELECT DISTINCT tahun_anggaran FROM anggaran_desa ORDER BY tahun_anggaran DESC";
$result_tahun = mysqli_query($koneksi, $query_tahun);

// Get categories for dropdown
$query_kategori = "SELECT DISTINCT kategori FROM detail_anggaran ORDER BY kategori ASC";
$result_kategori = mysqli_query($koneksi, $query_kategori);

// Get total budget info
$query_total = "SELECT 
                    SUM(total_anggaran) as total_anggaran 
                FROM 
                    anggaran_desa 
                WHERE 
                    tahun_anggaran = '$tahun' AND
                    status = 'disetujui'";
$result_total = mysqli_query($koneksi, $query_total);
$total_data = mysqli_fetch_assoc($result_total);
$total_anggaran = $total_data['total_anggaran'] ?? 0;

// Get anggaran by source
$query_sources = "SELECT 
                    kategori,
                    SUM(jumlah_anggaran) as jumlah 
                FROM 
                    detail_anggaran da 
                JOIN 
                    anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                WHERE 
                    ad.tahun_anggaran = '$tahun' AND
                    ad.status = 'disetujui' AND
                    da.kategori IN ('Dana Desa', 'Pendapatan Asli Desa', 'Alokasi Dana Desa')
                GROUP BY 
                    kategori";
$result_sources = mysqli_query($koneksi, $query_sources);

// Get realization total
$query_realisasi = "SELECT 
                        SUM(jumlah_realisasi) as total_realisasi 
                    FROM 
                        detail_anggaran da 
                    JOIN 
                        anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                    WHERE 
                        ad.tahun_anggaran = '$tahun' AND
                        ad.status = 'disetujui'";
$result_realisasi = mysqli_query($koneksi, $query_realisasi);
$realisasi_data = mysqli_fetch_assoc($result_realisasi);
$total_realisasi = $realisasi_data['total_realisasi'] ?? 0;
$persentase_realisasi = $total_anggaran > 0 ? ($total_realisasi / $total_anggaran) * 100 : 0;

// Get detailed budget items
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
                    ad.tahun_anggaran = '$tahun' AND
                    ad.status = 'disetujui'";

// Add category filter if selected
if ($kategori != 'semua') {
    $query_detail .= " AND da.kategori = '$kategori'";
}

$query_detail .= " ORDER BY da.kategori, da.uraian";
$result_detail = mysqli_query($koneksi, $query_detail);

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
                        <option value="<?= $row['tahun_anggaran'] ?>" <?= $tahun == $row['tahun_anggaran'] ? 'selected' : '' ?>>
                            Tahun <?= $row['tahun_anggaran'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select name="kategori" class="filter-select">
                    <option value="semua" <?= $kategori == 'semua' ? 'selected' : '' ?>>Semua Kategori</option>
                    <?php while ($row = mysqli_fetch_assoc($result_kategori)): ?>
                        <option value="<?= $row['kategori'] ?>" <?= $kategori == $row['kategori'] ? 'selected' : '' ?>>
                            <?= $row['kategori'] ?>
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
                <div class="description">Anggaran Desa Tahun <?= $tahun ?></div>
            </div>
            
            <?php 
            // Reset pointer
            mysqli_data_seek($result_sources, 0);
            while ($source = mysqli_fetch_assoc($result_sources)): 
                $percentage = $total_anggaran > 0 ? ($source['jumlah'] / $total_anggaran) * 100 : 0;
            ?>
                <div class="anggaran-card">
                    <h4><?= $source['kategori'] ?></h4>
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
                <div class="progress-bar" style="width: <?= $persentase_realisasi ?>%"></div>
            </div>
            <div class="description">Update terakhir: <?= date('d F Y') ?></div>
        </div>

        <div class="chart-container">
            <?php
            // Here you would typically include chart visualization code
            // For now, we'll keep the placeholder text
            ?>
            <p style="text-align: center; padding-top: 180px; color: var(--text-gray);">
                [Visualisasi Grafik Penggunaan Anggaran]</p>
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
                            <td><?= $row['kategori'] ?></td>
                            <td><?= $row['uraian'] ?></td>
                            <td class="number">Rp <?= number_format($row['jumlah_anggaran'], 0, ',', '.') ?></td>
                            <td class="number">Rp <?= number_format($row['jumlah_realisasi'], 0, ',', '.') ?></td>
                            <td class="number"><?= number_format($row['persentase'], 1) ?>%</td>
                            <td><span class="status <?= $statusClass ?>"><?= $row['status'] ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if (mysqli_num_rows($result_detail) == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada data anggaran untuk filter yang dipilih</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="cetak-anggaran.php?tahun=<?= $tahun ?>&kategori=<?= $kategori ?>" class="btn" target="_blank">Unduh Laporan Lengkap</a>
            <a href="detail-anggaran.php?tahun=<?= $tahun ?>" class="btn btn-outline">Lihat Detail</a>
        </div>
    </section>
</main>

<?php
// Include footer
include '../includes/footer.php';
?>