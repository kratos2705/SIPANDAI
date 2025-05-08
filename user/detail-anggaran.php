<?php
// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "Detail Anggaran Desa";

// Get filter parameters with proper sanitization
$tahun = isset($_GET['tahun']) ? mysqli_real_escape_string($koneksi, $_GET['tahun']) : date('Y');
$kategori_filter = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';

// Prepare statements for better security and performance
// Get years for dropdown
$stmt_tahun = mysqli_prepare($koneksi, "SELECT DISTINCT tahun_anggaran FROM anggaran_desa ORDER BY tahun_anggaran DESC");
mysqli_stmt_execute($stmt_tahun);
$result_tahun = mysqli_stmt_get_result($stmt_tahun);

// Get budget information for the selected year
$stmt_anggaran = mysqli_prepare($koneksi, "SELECT * FROM anggaran_desa WHERE tahun_anggaran = ? AND status = 'disetujui'");
mysqli_stmt_bind_param($stmt_anggaran, "s", $tahun);
mysqli_stmt_execute($stmt_anggaran);
$result_anggaran = mysqli_stmt_get_result($stmt_anggaran);
$anggaran_info = mysqli_fetch_assoc($result_anggaran);

// Get all categories for the selected year
$stmt_kategori = mysqli_prepare($koneksi, "SELECT DISTINCT kategori 
                                          FROM detail_anggaran da 
                                          JOIN anggaran_desa ad ON da.anggaran_id = ad.anggaran_id 
                                          WHERE ad.tahun_anggaran = ? AND ad.status = 'disetujui' 
                                          ORDER BY da.kategori ASC");
mysqli_stmt_bind_param($stmt_kategori, "s", $tahun);
mysqli_stmt_execute($stmt_kategori);
$result_kategori = mysqli_stmt_get_result($stmt_kategori);

// Get summary of each category (total budgeted and realized)
$stmt_summary = mysqli_prepare($koneksi, "SELECT 
                                        da.kategori,
                                        SUM(da.jumlah_anggaran) as total_anggaran,
                                        SUM(da.jumlah_realisasi) as total_realisasi,
                                        CASE 
                                            WHEN SUM(da.jumlah_anggaran) > 0 THEN (SUM(da.jumlah_realisasi) / SUM(da.jumlah_anggaran)) * 100 
                                            ELSE 0 
                                        END as persentase
                                    FROM 
                                        detail_anggaran da 
                                    JOIN 
                                        anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                                    WHERE 
                                        ad.tahun_anggaran = ? AND ad.status = 'disetujui'
                                    GROUP BY 
                                        da.kategori
                                    ORDER BY 
                                        da.kategori ASC");
mysqli_stmt_bind_param($stmt_summary, "s", $tahun);
mysqli_stmt_execute($stmt_summary);
$result_summary = mysqli_stmt_get_result($stmt_summary);

// Get detailed budget data for each sub-category
$stmt_detail = mysqli_prepare($koneksi, "SELECT 
                                        da.kategori,
                                        da.sub_kategori,
                                        SUM(da.jumlah_anggaran) as total_anggaran,
                                        SUM(da.jumlah_realisasi) as total_realisasi,
                                        CASE 
                                            WHEN SUM(da.jumlah_anggaran) > 0 THEN (SUM(da.jumlah_realisasi) / SUM(da.jumlah_anggaran)) * 100 
                                            ELSE 0 
                                        END as persentase
                                    FROM 
                                        detail_anggaran da 
                                    JOIN 
                                        anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                                    WHERE 
                                        ad.tahun_anggaran = ? AND ad.status = 'disetujui'
                                    GROUP BY 
                                        da.kategori, da.sub_kategori
                                    ORDER BY 
                                        da.kategori ASC, da.sub_kategori ASC");
mysqli_stmt_bind_param($stmt_detail, "s", $tahun);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

// Get latest realization items
$stmt_realisasi = mysqli_prepare($koneksi, "SELECT 
                                        r.tanggal_realisasi,
                                        da.kategori,
                                        da.uraian,
                                        r.jumlah,
                                        r.keterangan,
                                        u.nama as petugas
                                    FROM 
                                        realisasi_anggaran r
                                    JOIN 
                                        detail_anggaran da ON r.detail_id = da.detail_id
                                    JOIN 
                                        anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                                    JOIN 
                                        users u ON r.created_by = u.user_id
                                    WHERE 
                                        ad.tahun_anggaran = ? AND ad.status = 'disetujui'
                                    ORDER BY 
                                        r.tanggal_realisasi DESC
                                    LIMIT 10");
mysqli_stmt_bind_param($stmt_realisasi, "s", $tahun);
mysqli_stmt_execute($stmt_realisasi);
$result_realisasi = mysqli_stmt_get_result($stmt_realisasi);

// Get quarterly realization data for chart
$stmt_quarterly = mysqli_prepare($koneksi, "SELECT 
                                            QUARTER(r.tanggal_realisasi) as kuartal,
                                            SUM(r.jumlah) as total_realisasi
                                        FROM 
                                            realisasi_anggaran r
                                        JOIN 
                                            detail_anggaran da ON r.detail_id = da.detail_id
                                        JOIN 
                                            anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                                        WHERE 
                                            ad.tahun_anggaran = ? AND ad.status = 'disetujui'
                                        GROUP BY 
                                            QUARTER(r.tanggal_realisasi)
                                        ORDER BY 
                                            kuartal ASC");
mysqli_stmt_bind_param($stmt_quarterly, "s", $tahun);
mysqli_stmt_execute($stmt_quarterly);
$result_quarterly = mysqli_stmt_get_result($stmt_quarterly);

// Prepare quarterly data for chart
$quarterly_labels = ["Triwulan I", "Triwulan II", "Triwulan III", "Triwulan IV"];
$quarterly_data = [0, 0, 0, 0]; // Default values

while ($row = mysqli_fetch_assoc($result_quarterly)) {
    $index = $row['kuartal'] - 1; // Adjust for 0-based array
    if ($index >= 0 && $index < 4) {
        $quarterly_data[$index] = (float)$row['total_realisasi'];
    }
}

// Get category-specific details if category filter is applied
$category_details = [];
if (!empty($kategori_filter)) {
    $stmt_cat_detail = mysqli_prepare($koneksi, "SELECT 
                                                da.sub_kategori,
                                                da.uraian,
                                                da.jumlah_anggaran,
                                                da.jumlah_realisasi,
                                                CASE 
                                                    WHEN da.jumlah_anggaran > 0 THEN (da.jumlah_realisasi / da.jumlah_anggaran) * 100 
                                                    ELSE 0 
                                                END as persentase,
                                                da.keterangan
                                            FROM 
                                                detail_anggaran da 
                                            JOIN 
                                                anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                                            WHERE 
                                                ad.tahun_anggaran = ? AND 
                                                ad.status = 'disetujui' AND
                                                da.kategori = ?
                                            ORDER BY 
                                                da.sub_kategori ASC, da.uraian ASC");
    mysqli_stmt_bind_param($stmt_cat_detail, "ss", $tahun, $kategori_filter);
    mysqli_stmt_execute($stmt_cat_detail);
    $result_cat_detail = mysqli_stmt_get_result($stmt_cat_detail);
    
    while ($row = mysqli_fetch_assoc($result_cat_detail)) {
        $category_details[] = $row;
    }
    
    mysqli_stmt_close($stmt_cat_detail);
}

// Include header
include '../includes/header.php';
?>

<main>
    <section class="page-header">
        <h2>Detail Anggaran Desa Tahun <?= htmlspecialchars($tahun) ?></h2>
        <p>Rincian informasi anggaran dan realisasi anggaran desa berdasarkan kategori dan sub-kategori untuk transparansi dan akuntabilitas pemerintah desa.</p>
    </section>

    <section class="content-section">
        <div class="anggaran-filter">
            <form method="GET" action="detail-anggaran.php">
                <select name="tahun" class="filter-select">
                    <?php while ($row = mysqli_fetch_assoc($result_tahun)): ?>
                        <option value="<?= htmlspecialchars($row['tahun_anggaran']) ?>" <?= $tahun == $row['tahun_anggaran'] ? 'selected' : '' ?>>
                            Tahun <?= htmlspecialchars($row['tahun_anggaran']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if (!empty($kategori_filter)): ?>
                    <a href="detail-anggaran.php?tahun=<?= htmlspecialchars($tahun) ?>" class="btn btn-outline">Tampilkan Semua Kategori</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-outline">Filter</button>
            </form>
        </div>

        <?php if ($anggaran_info): ?>
            <div class="anggaran-overview">
                <div class="anggaran-card">
                    <h4>Total Anggaran Tahun <?= htmlspecialchars($tahun) ?></h4>
                    <div class="value">Rp <?= number_format($anggaran_info['total_anggaran'], 0, ',', '.') ?></div>
                    <div class="description">Status: <?= ucwords(str_replace('_', ' ', $anggaran_info['status'])) ?></div>
                </div>
                
                <?php 
                // Calculate total realization
                $total_realisasi = 0;
                $total_anggaran = $anggaran_info['total_anggaran'];
                
                // Reset result pointer
                mysqli_data_seek($result_summary, 0);
                while ($row = mysqli_fetch_assoc($result_summary)) {
                    $total_realisasi += $row['total_realisasi'];
                }
                
                $persentase_realisasi = $total_anggaran > 0 ? ($total_realisasi / $total_anggaran) * 100 : 0;
                ?>
                
                <div class="anggaran-card">
                    <h4>Realisasi Anggaran</h4>
                    <div class="value">Rp <?= number_format($total_realisasi, 0, ',', '.') ?></div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= min($persentase_realisasi, 100) ?>%"></div>
                    </div>
                    <div class="description"><?= number_format($persentase_realisasi, 1) ?>% dari total anggaran</div>
                </div>
                
                <div class="anggaran-card">
                    <h4>Sisa Anggaran</h4>
                    <div class="value">Rp <?= number_format($total_anggaran - $total_realisasi, 0, ',', '.') ?></div>
                    <div class="description"><?= number_format(100 - $persentase_realisasi, 1) ?>% dari total anggaran</div>
                </div>
            </div>

            <?php if (empty($kategori_filter)): ?>
                <!-- Summary by category with visualization -->
                <h3>Ringkasan Anggaran per Kategori</h3>
                <div class="chart-container">
                    <canvas id="kategoriChart" width="800" height="400"></canvas>
                </div>

                <div class="grid-container">
                    <?php 
                    mysqli_data_seek($result_summary, 0);
                    while ($row = mysqli_fetch_assoc($result_summary)): 
                        $percent = $row['persentase'];
                        $status_class = '';
                        
                        if ($percent < 25) {
                            $status_class = 'status-pending';
                        } elseif ($percent < 75) {
                            $status_class = 'status-processing';
                        } else {
                            $status_class = 'status-completed';
                        }
                    ?>
                        <div class="kategori-card">
                            <h4><?= htmlspecialchars($row['kategori']) ?></h4>
                            <div class="budget-info">
                                <div class="allocated">
                                    <span class="label">Anggaran:</span>
                                    <span class="value">Rp <?= number_format($row['total_anggaran'], 0, ',', '.') ?></span>
                                </div>
                                <div class="realized">
                                    <span class="label">Realisasi:</span>
                                    <span class="value">Rp <?= number_format($row['total_realisasi'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar <?= $status_class ?>" style="width: <?= min($percent, 100) ?>%"></div>
                            </div>
                            <div class="percent"><?= number_format($percent, 1) ?>%</div>
                            <a href="detail-anggaran.php?tahun=<?= htmlspecialchars($tahun) ?>&kategori=<?= urlencode($row['kategori']) ?>" class="btn btn-sm">Lihat Detail</a>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Quarterly realization -->
                <h3>Realisasi Anggaran per Triwulan</h3>
                <div class="chart-container">
                    <canvas id="quarterlyChart" width="800" height="400"></canvas>
                </div>

                <!-- Sub-Category breakdown -->
                <h3>Rincian Anggaran per Sub-Kategori</h3>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Sub-Kategori</th>
                                <th>Anggaran</th>
                                <th>Realisasi</th>
                                <th>Persentase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($result_detail, 0);
                            if (mysqli_num_rows($result_detail) > 0):
                                while ($row = mysqli_fetch_assoc($result_detail)): 
                                    $percent = $row['persentase'];
                                    $status = '';
                                    $status_class = '';
                                    
                                    if ($percent < 1) {
                                        $status = 'Belum';
                                        $status_class = 'status-pending';
                                    } elseif ($percent < 100) {
                                        $status = 'Berjalan';
                                        $status_class = 'status-processing';
                                    } else {
                                        $status = 'Selesai';
                                        $status_class = 'status-completed';
                                    }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['kategori']) ?></td>
                                    <td><?= htmlspecialchars($row['sub_kategori'] ?: 'Umum') ?></td>
                                    <td class="number">Rp <?= number_format($row['total_anggaran'], 0, ',', '.') ?></td>
                                    <td class="number">Rp <?= number_format($row['total_realisasi'], 0, ',', '.') ?></td>
                                    <td class="number"><?= number_format($percent, 1) ?>%</td>
                                    <td><span class="status <?= $status_class ?>"><?= $status ?></span></td>
                                </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada data anggaran untuk tahun yang dipilih</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Latest realization -->
                <h3>Realisasi Anggaran Terbaru</h3>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kategori</th>
                                <th>Uraian</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($result_realisasi) > 0):
                                while ($row = mysqli_fetch_assoc($result_realisasi)): 
                            ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_realisasi'])) ?></td>
                                    <td><?= htmlspecialchars($row['kategori']) ?></td>
                                    <td><?= htmlspecialchars($row['uraian']) ?></td>
                                    <td class="number">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($row['petugas']) ?></td>
                                </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada data realisasi untuk tahun yang dipilih</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Category-specific details -->
                <h3>Detail Anggaran: <?= htmlspecialchars($kategori_filter) ?></h3>
                
                <?php
                // Get summary for this category
                mysqli_data_seek($result_summary, 0);
                $category_summary = null;
                while ($row = mysqli_fetch_assoc($result_summary)) {
                    if ($row['kategori'] == $kategori_filter) {
                        $category_summary = $row;
                        break;
                    }
                }
                
                if ($category_summary):
                ?>
                <div class="anggaran-overview">
                    <div class="anggaran-card">
                        <h4>Total Anggaran <?= htmlspecialchars($kategori_filter) ?></h4>
                        <div class="value">Rp <?= number_format($category_summary['total_anggaran'], 0, ',', '.') ?></div>
                        <div class="description"><?= number_format(($category_summary['total_anggaran'] / $total_anggaran) * 100, 1) ?>% dari total anggaran desa</div>
                    </div>
                    
                    <div class="anggaran-card">
                        <h4>Realisasi <?= htmlspecialchars($kategori_filter) ?></h4>
                        <div class="value">Rp <?= number_format($category_summary['total_realisasi'], 0, ',', '.') ?></div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?= min($category_summary['persentase'], 100) ?>%"></div>
                        </div>
                        <div class="description"><?= number_format($category_summary['persentase'], 1) ?>% dari anggaran kategori</div>
                    </div>
                    
                    <div class="anggaran-card">
                        <h4>Sisa Anggaran</h4>
                        <div class="value">Rp <?= number_format($category_summary['total_anggaran'] - $category_summary['total_realisasi'], 0, ',', '.') ?></div>
                        <div class="description"><?= number_format(100 - $category_summary['persentase'], 1) ?>% dari anggaran kategori</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Detailed items for this category -->
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Sub-Kategori</th>
                                <th>Uraian</th>
                                <th>Anggaran</th>
                                <th>Realisasi</th>
                                <th>Persentase</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (count($category_details) > 0):
                                $no = 1;
                                foreach ($category_details as $item): 
                                    $percent = $item['persentase'];
                                    $status_class = '';
                                    
                                    if ($percent < 1) {
                                        $status_class = 'status-pending';
                                    } elseif ($percent < 100) {
                                        $status_class = 'status-processing';
                                    } else {
                                        $status_class = 'status-completed';
                                    }
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($item['sub_kategori'] ?: 'Umum') ?></td>
                                    <td><?= htmlspecialchars($item['uraian']) ?></td>
                                    <td class="number">Rp <?= number_format($item['jumlah_anggaran'], 0, ',', '.') ?></td>
                                    <td class="number">Rp <?= number_format($item['jumlah_realisasi'], 0, ',', '.') ?></td>
                                    <td class="number">
                                        <div class="mini-progress">
                                            <div class="mini-bar <?= $status_class ?>" style="width: <?= min($percent, 100) ?>%"></div>
                                            <span class="mini-text"><?= number_format($percent, 1) ?>%</span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['keterangan'] ?: '-') ?></td>
                                </tr>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada detail anggaran untuk kategori yang dipilih</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Get realizations specific to this category -->
                <?php
                $stmt_cat_realisasi = mysqli_prepare($koneksi, "SELECT 
                                                                r.tanggal_realisasi,
                                                                da.sub_kategori,
                                                                da.uraian,
                                                                r.jumlah,
                                                                r.keterangan,
                                                                u.nama as petugas
                                                            FROM 
                                                                realisasi_anggaran r
                                                            JOIN 
                                                                detail_anggaran da ON r.detail_id = da.detail_id
                                                            JOIN 
                                                                anggaran_desa ad ON da.anggaran_id = ad.anggaran_id
                                                            JOIN 
                                                                users u ON r.created_by = u.user_id
                                                            WHERE 
                                                                ad.tahun_anggaran = ? AND 
                                                                ad.status = 'disetujui' AND
                                                                da.kategori = ?
                                                            ORDER BY 
                                                                r.tanggal_realisasi DESC
                                                            LIMIT 10");
                mysqli_stmt_bind_param($stmt_cat_realisasi, "ss", $tahun, $kategori_filter);
                mysqli_stmt_execute($stmt_cat_realisasi);
                $result_cat_realisasi = mysqli_stmt_get_result($stmt_cat_realisasi);
                ?>
                
                <h3>Realisasi Anggaran <?= htmlspecialchars($kategori_filter) ?></h3>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Sub-Kategori</th>
                                <th>Uraian</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($result_cat_realisasi) > 0):
                                while ($row = mysqli_fetch_assoc($result_cat_realisasi)): 
                            ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_realisasi'])) ?></td>
                                    <td><?= htmlspecialchars($row['sub_kategori'] ?: 'Umum') ?></td>
                                    <td><?= htmlspecialchars($row['uraian']) ?></td>
                                    <td class="number">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($row['petugas']) ?></td>
                                </tr>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada data realisasi untuk kategori ini</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php mysqli_stmt_close($stmt_cat_realisasi); ?>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="transparansi.php?tahun=<?= htmlspecialchars($tahun) ?>" class="btn btn-outline">Kembali ke Transparansi Anggaran</a>
                <a href="cetak-anggaran.php?tahun=<?= htmlspecialchars($tahun) ?><?= !empty($kategori_filter) ? '&kategori=' . urlencode($kategori_filter) : '' ?>" class="btn" target="_blank">Unduh Laporan</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <p>Tidak ada data anggaran ditemukan untuk tahun <?= htmlspecialchars($tahun) ?>.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Add Chart.js script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (empty($kategori_filter)): ?>
    // Category breakdown chart
    const ctxKategori = document.getElementById('kategoriChart').getContext('2d');
    const kategoriData = {
        labels: [
            <?php 
            mysqli_data_seek($result_summary, 0);
            while ($row = mysqli_fetch_assoc($result_summary)): 
                echo "'" . addslashes($row['kategori']) . "', ";
            endwhile; 
            ?>
        ],
        datasets: [
            {
                label: 'Anggaran',
                data: [
                    <?php 
                    mysqli_data_seek($result_summary, 0);
                    while ($row = mysqli_fetch_assoc($result_summary)): 
                        echo $row['total_anggaran'] . ", ";
                    endwhile; 
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Realisasi',
                data: [
                    <?php 
                    mysqli_data_seek($result_summary, 0);
                    while ($row = mysqli_fetch_assoc($result_summary)): 
                        echo $row['total_realisasi'] . ", ";
                    endwhile; 
                    ?>
                ],
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }
        ]
    };
    
    new Chart(ctxKategori, {
        type: 'bar',
        data: kategoriData,
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
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.raw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            }
        }
    });
    
    // Quarterly realization chart
    const ctxQuarterly = document.getElementById('quarterlyChart').getContext('2d');
    const quarterlyData = {
        labels: <?= json_encode($quarterly_labels) ?>,
        datasets: [
            {
                label: 'Realisasi Anggaran',
                data: <?= json_encode($quarterly_data) ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.7)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }
        ]
    };
    
    new Chart(ctxQuarterly, {
        type: 'bar',
        data: quarterlyData,
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
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.raw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php
// Close database connections
mysqli_stmt_close($stmt_tahun);
mysqli_stmt_close($stmt_anggaran);
mysqli_stmt_close($stmt_kategori);
mysqli_stmt_close($stmt_summary);
mysqli_stmt_close($stmt_detail);
mysqli_stmt_close($stmt_realisasi);
mysqli_stmt_close($stmt_quarterly);

// Include footer
include '../includes/footer.php';
?>

