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

// Get summary counts for dashboard
// Pengajuan Dokumen
$total_pengajuan_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen";
$total_pengajuan_result = mysqli_query($koneksi, $total_pengajuan_query);
$total_pengajuan = mysqli_fetch_assoc($total_pengajuan_result)['total'];

$menunggu_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen WHERE status = 'diajukan'";
$menunggu_result = mysqli_query($koneksi, $menunggu_query);
$menunggu = mysqli_fetch_assoc($menunggu_result)['total'];

$proses_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen WHERE status IN ('verifikasi', 'proses')";
$proses_result = mysqli_query($koneksi, $proses_query);
$proses = mysqli_fetch_assoc($proses_result)['total'];

$selesai_query = "SELECT COUNT(*) as total FROM pengajuan_dokumen WHERE status = 'selesai'";
$selesai_result = mysqli_query($koneksi, $selesai_query);
$selesai = mysqli_fetch_assoc($selesai_result)['total'];

// User stats
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = mysqli_query($koneksi, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$active_users_query = "SELECT COUNT(*) as total FROM users WHERE active = TRUE";
$active_users_result = mysqli_query($koneksi, $active_users_query);
$active_users = mysqli_fetch_assoc($active_users_result)['total'];

// Berita stats
$total_berita_query = "SELECT COUNT(*) as total FROM berita";
$total_berita_result = mysqli_query($koneksi, $total_berita_query);
$total_berita = mysqli_fetch_assoc($total_berita_result)['total'];

$published_berita_query = "SELECT COUNT(*) as total FROM berita WHERE status = 'published'";
$published_berita_result = mysqli_query($koneksi, $published_berita_query);
$published_berita = mysqli_fetch_assoc($published_berita_result)['total'];

// Retribusi stats
$pending_payment_query = "SELECT COUNT(*) as total FROM pembayaran_retribusi WHERE status = 'pending'";
$pending_payment_result = mysqli_query($koneksi, $pending_payment_query);
$pending_payment = mysqli_fetch_assoc($pending_payment_result)['total'];

// Get latest document applications
$pengajuan_query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, pd.tanggal_pengajuan, pd.status,
                    u.nama, jd.nama_dokumen
                    FROM pengajuan_dokumen pd
                    JOIN users u ON pd.user_id = u.user_id
                    JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                    ORDER BY pd.tanggal_pengajuan DESC
                    LIMIT 10";
$pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

// Get latest users
$users_query = "SELECT user_id, nama, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5";
$users_result = mysqli_query($koneksi, $users_query);

// Get latest payments
$payments_query = "SELECT pr.pembayaran_id, pr.tanggal_bayar, pr.jumlah_bayar, pr.status, pr.nomor_referensi,
                  u.nama, jr.nama_retribusi
                  FROM pembayaran_retribusi pr
                  JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
                  JOIN users u ON tr.user_id = u.user_id
                  JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                  ORDER BY pr.tanggal_bayar DESC
                  LIMIT 5";
$payments_result = mysqli_query($koneksi, $payments_query);

// Get latest news
$berita_query = "SELECT berita_id, judul, kategori, status, tanggal_publikasi FROM berita ORDER BY created_at DESC LIMIT 5";
$berita_result = mysqli_query($koneksi, $berita_query);

// Get latest anggaran
$anggaran_query = "SELECT anggaran_id, tahun_anggaran, periode, total_anggaran, status 
                  FROM anggaran_desa
                  ORDER BY created_at DESC
                  LIMIT 5";
$anggaran_result = mysqli_query($koneksi, $anggaran_query);

// Get pengajuan by month (for chart)
$monthly_pengajuan_query = "SELECT 
                           DATE_FORMAT(tanggal_pengajuan, '%Y-%m') as month,
                           COUNT(*) as total
                           FROM pengajuan_dokumen
                           WHERE tanggal_pengajuan >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(tanggal_pengajuan, '%Y-%m')
                           ORDER BY month ASC";
$monthly_pengajuan_result = mysqli_query($koneksi, $monthly_pengajuan_query);
$monthly_data = [];

if ($monthly_pengajuan_result) {
    while ($row = mysqli_fetch_assoc($monthly_pengajuan_result)) {
        $month_name = date("M Y", strtotime($row['month'] . "-01"));
        $monthly_data[$month_name] = $row['total'];
    }
}

// Get status distribution (for pie chart)
$status_distribution_query = "SELECT status, COUNT(*) as total FROM pengajuan_dokumen GROUP BY status";
$status_distribution_result = mysqli_query($koneksi, $status_distribution_query);
$status_data = [];

if ($status_distribution_result) {
    while ($row = mysqli_fetch_assoc($status_distribution_result)) {
        $status_data[$row['status']] = $row['total'];
    }
}

// Prepare variables for page
$page_title = "Dashboard Admin";
$current_page = "dashboard";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Dashboard <?php echo ($user_role == 'kepala_desa') ? 'Kepala Desa' : 'Administrator'; ?></h2>
        <p>Selamat datang, <?php echo htmlspecialchars($user_name); ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats-card primary">
                <div class="stats-icon">📄</div>
                <div class="stats-info">
                    <h3>Total Pengajuan</h3>
                    <p class="stats-value"><?php echo $total_pengajuan; ?></p>
                </div>
            </div>
            <div class="stats-card warning">
                <div class="stats-icon">⏳</div>
                <div class="stats-info">
                    <h3>Menunggu Diproses</h3>
                    <p class="stats-value"><?php echo $menunggu; ?></p>
                </div>
            </div>
            <div class="stats-card info">
                <div class="stats-icon">🔄</div>
                <div class="stats-info">
                    <h3>Sedang Diproses</h3>
                    <p class="stats-value"><?php echo $proses; ?></p>
                </div>
            </div>
            <div class="stats-card success">
                <div class="stats-icon">✅</div>
                <div class="stats-info">
                    <h3>Selesai</h3>
                    <p class="stats-value"><?php echo $selesai; ?></p>
                </div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stats-card">
                <div class="stats-icon">👥</div>
                <div class="stats-info">
                    <h3>Total Pengguna</h3>
                    <p class="stats-value"><?php echo $total_users; ?></p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">📰</div>
                <div class="stats-info">
                    <h3>Total Berita</h3>
                    <p class="stats-value"><?php echo $total_berita; ?></p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">💲</div>
                <div class="stats-info">
                    <h3>Pembayaran Pending</h3>
                    <p class="stats-value"><?php echo $pending_payment; ?></p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">🏢</div>
                <div class="stats-info">
                    <h3>Tipe Dokumen</h3>
                    <p class="stats-value">
                        <?php 
                        $doc_types_query = "SELECT COUNT(*) as total FROM jenis_dokumen WHERE is_active = TRUE";
                        $doc_types_result = mysqli_query($koneksi, $doc_types_query);
                        echo mysqli_fetch_assoc($doc_types_result)['total'];
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-container">
        <div class="chart-card">
            <h3>Statistik Pengajuan Bulanan</h3>
            <canvas id="applicationChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Distribusi Status Pengajuan</h3>
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="data-card">
        <div class="card-header">
            <h3>Pengajuan Dokumen Terbaru</h3>
            <a href="pengajuan.php" class="btn btn-sm">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>No. Pengajuan</th>
                        <th>Nama Pemohon</th>
                        <th>Jenis Dokumen</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($pengajuan_result) > 0) {
                        while ($row = mysqli_fetch_assoc($pengajuan_result)) {
                            // Format date
                            $tanggal = date('d-m-Y', strtotime($row['tanggal_pengajuan']));
                            
                            // Determine status class
                            $status_class = "";
                            switch ($row['status']) {
                                case 'diajukan':
                                    $status_class = "status-pending";
                                    $status_text = "Menunggu";
                                    break;
                                case 'verifikasi':
                                    $status_class = "status-processing";
                                    $status_text = "Verifikasi";
                                    break;
                                case 'proses':
                                    $status_class = "status-processing";
                                    $status_text = "Diproses";
                                    break;
                                case 'selesai':
                                    $status_class = "status-completed";
                                    $status_text = "Selesai";
                                    break;
                                case 'ditolak':
                                    $status_class = "status-rejected";
                                    $status_text = "Ditolak";
                                    break;
                                default:
                                    $status_class = "status-pending";
                                    $status_text = "Menunggu";
                            }
                            
                            echo '<tr class="data-row" data-url="pengajuan_detail.php?id=' . $row['pengajuan_id'] . '">';
                            echo '<td>' . htmlspecialchars($row['nomor_pengajuan']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama_dokumen']) . '</td>';
                            echo '<td>' . $tanggal . '</td>';
                            echo '<td><span class="status ' . $status_class . '">' . $status_text . '</span></td>';
                            echo '<td><a href="pengajuan_detail.php?id=' . $row['pengajuan_id'] . '" class="btn-sm">Detail</a></td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center">Tidak ada data pengajuan terbaru</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Data Section -->
    <div class="recent-data-grid">
        <!-- Recent Users -->
        <div class="data-card">
            <div class="card-header">
                <h3>Pengguna Terbaru</h3>
                <a href="users.php" class="btn btn-sm">Lihat Semua</a>
            </div>
            <ul class="data-list">
                <?php
                if (mysqli_num_rows($users_result) > 0) {
                    while ($row = mysqli_fetch_assoc($users_result)) {
                        $join_date = date('d-m-Y', strtotime($row['created_at']));
                        echo '<li class="data-item">';
                        echo '<div class="item-avatar">👤</div>';
                        echo '<div class="item-details">';
                        echo '<h4>' . htmlspecialchars($row['nama']) . '</h4>';
                        echo '<p>' . htmlspecialchars($row['email']) . '</p>';
                        echo '<span class="item-meta">' . ucfirst($row['role']) . ' · Terdaftar ' . $join_date . '</span>';
                        echo '</div>';
                        echo '<a href="user_detail.php?id=' . $row['user_id'] . '" class="btn-sm">Detail</a>';
                        echo '</li>';
                    }
                } else {
                    echo '<li class="data-item empty">Tidak ada data pengguna terbaru</li>';
                }
                ?>
            </ul>
        </div>

        <!-- Recent Payments -->
        <div class="data-card">
            <div class="card-header">
                <h3>Pembayaran Terbaru</h3>
                <a href="pembayaran.php" class="btn btn-sm">Lihat Semua</a>
            </div>
            <ul class="data-list">
                <?php
                if (mysqli_num_rows($payments_result) > 0) {
                    while ($row = mysqli_fetch_assoc($payments_result)) {
                        $payment_date = date('d-m-Y', strtotime($row['tanggal_bayar']));
                        
                        // Determine payment status class
                        $payment_status_class = "";
                        switch ($row['status']) {
                            case 'pending':
                                $payment_status_class = "status-pending";
                                $payment_status_text = "Menunggu";
                                break;
                            case 'berhasil':
                                $payment_status_class = "status-completed";
                                $payment_status_text = "Berhasil";
                                break;
                            case 'gagal':
                                $payment_status_class = "status-rejected";
                                $payment_status_text = "Gagal";
                                break;
                            default:
                                $payment_status_class = "status-pending";
                                $payment_status_text = "Menunggu";
                        }
                        
                        echo '<li class="data-item">';
                        echo '<div class="item-avatar">💰</div>';
                        echo '<div class="item-details">';
                        echo '<h4>' . htmlspecialchars($row['nama']) . '</h4>';
                        echo '<p>' . htmlspecialchars($row['nama_retribusi']) . ' · Rp ' . number_format($row['jumlah_bayar'], 0, ',', '.') . '</p>';
                        echo '<span class="item-meta">' . $payment_date . ' · <span class="status ' . $payment_status_class . '">' . $payment_status_text . '</span></span>';
                        echo '</div>';
                        echo '<a href="pembayaran_detail.php?ref=' . $row['nomor_referensi'] . '" class="btn-sm">Detail</a>';
                        echo '</li>';
                    }
                } else {
                    echo '<li class="data-item empty">Tidak ada data pembayaran terbaru</li>';
                }
                ?>
            </ul>
        </div>

        <!-- Recent News -->
        <div class="data-card">
            <div class="card-header">
                <h3>Berita Terbaru</h3>
                <a href="berita.php" class="btn btn-sm">Lihat Semua</a>
            </div>
            <ul class="data-list">
                <?php
                if (mysqli_num_rows($berita_result) > 0) {
                    while ($row = mysqli_fetch_assoc($berita_result)) {
                        $publish_date = $row['tanggal_publikasi'] ? date('d-m-Y', strtotime($row['tanggal_publikasi'])) : "Belum dipublikasikan";
                        
                        // Determine news status class
                        $news_status_class = "";
                        switch ($row['status']) {
                            case 'draft':
                                $news_status_class = "status-pending";
                                $news_status_text = "Draft";
                                break;
                            case 'published':
                                $news_status_class = "status-completed";
                                $news_status_text = "Dipublikasikan";
                                break;
                            case 'archived':
                                $news_status_class = "status-rejected";
                                $news_status_text = "Diarsipkan";
                                break;
                            default:
                                $news_status_class = "status-pending";
                                $news_status_text = "Draft";
                        }
                        
                        echo '<li class="data-item">';
                        echo '<div class="item-avatar">📰</div>';
                        echo '<div class="item-details">';
                        echo '<h4>' . htmlspecialchars($row['judul']) . '</h4>';
                        echo '<p>' . htmlspecialchars($row['kategori']) . '</p>';
                        echo '<span class="item-meta">' . $publish_date . ' · <span class="status ' . $news_status_class . '">' . $news_status_text . '</span></span>';
                        echo '</div>';
                        echo '<a href="berita_edit.php?id=' . $row['berita_id'] . '" class="btn-sm">Edit</a>';
                        echo '</li>';
                    }
                } else {
                    echo '<li class="data-item empty">Tidak ada data berita terbaru</li>';
                }
                ?>
            </ul>
        </div>

        <!-- Recent Budget -->
        <div class="data-card">
            <div class="card-header">
                <h3>Anggaran Terbaru</h3>
                <a href="anggaran.php" class="btn btn-sm">Lihat Semua</a>
            </div>
            <ul class="data-list">
                <?php
                if (mysqli_num_rows($anggaran_result) > 0) {
                    while ($row = mysqli_fetch_assoc($anggaran_result)) {
                        // Format periode
                        $periode_text = "";
                        switch($row['periode']) {
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
                                $periode_text = ucfirst($row['periode']);
                        }
                        
                        // Determine budget status class
                        $budget_status_class = "";
                        switch ($row['status']) {
                            case 'rencana':
                                $budget_status_class = "status-pending";
                                $budget_status_text = "Rencana";
                                break;
                            case 'disetujui':
                                $budget_status_class = "status-processing";
                                $budget_status_text = "Disetujui";
                                break;
                            case 'realisasi':
                                $budget_status_class = "status-processing";
                                $budget_status_text = "Realisasi";
                                break;
                            case 'laporan_akhir':
                                $budget_status_class = "status-completed";
                                $budget_status_text = "Laporan Akhir";
                                break;
                            default:
                                $budget_status_class = "status-pending";
                                $budget_status_text = "Rencana";
                        }
                        
                        echo '<li class="data-item">';
                        echo '<div class="item-avatar">💰</div>';
                        echo '<div class="item-details">';
                        echo '<h4>Anggaran ' . $row['tahun_anggaran'] . ' - ' . $periode_text . '</h4>';
                        echo '<p>Rp ' . number_format($row['total_anggaran'], 0, ',', '.') . '</p>';
                        echo '<span class="item-meta"><span class="status ' . $budget_status_class . '">' . $budget_status_text . '</span></span>';
                        echo '</div>';
                        echo '<a href="anggaran_detail.php?id=' . $row['anggaran_id'] . '" class="btn-sm">Detail</a>';
                        echo '</li>';
                    }
                } else {
                    echo '<li class="data-item empty">Tidak ada data anggaran terbaru</li>';
                }
                ?>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Application Monthly Statistics Chart
    const appCtx = document.getElementById('applicationChart').getContext('2d');
    const appChart = new Chart(appCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                // Output the month labels
                if (!empty($monthly_data)) {
                    echo "'" . implode("', '", array_keys($monthly_data)) . "'";
                } else {
                    echo "'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'";
                }
                ?>
            ],
            datasets: [{
                label: 'Jumlah Pengajuan',
                data: [
                    <?php 
                    // Output the monthly data
                    if (!empty($monthly_data)) {
                        echo implode(", ", array_values($monthly_data));
                    } else {
                        echo "0, 0, 0, 0, 0, 0";
                    }
                    ?>
                ],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderWidth: 2,
                pointBackgroundColor: '#4e73df',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    },
                    padding: 12,
                    cornerRadius: 4
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Status Distribution Pie Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [
                'Menunggu', 'Verifikasi', 'Diproses', 'Selesai', 'Ditolak'
            ],
            datasets: [{
                data: [
                    <?php 
                    // Output the status data with fallbacks for missing statuses
                    echo isset($status_data['diajukan']) ? $status_data['diajukan'] : 0; 
                    echo ", ";
                    echo isset($status_data['verifikasi']) ? $status_data['verifikasi'] : 0;
                    echo ", ";
                    echo isset($status_data['proses']) ? $status_data['proses'] : 0;
                    echo ", ";
                    echo isset($status_data['selesai']) ? $status_data['selesai'] : 0;
                    echo ", ";
                    echo isset($status_data['ditolak']) ? $status_data['ditolak'] : 0;
                    ?>
                ],
                backgroundColor: [
                    '#f6c23e', // Menunggu - warning yellow
                    '#36b9cc', // Verifikasi - info blue
                    '#4e73df', // Diproses - primary blue
                    '#1cc88a', // Selesai - success green
                    '#e74a3b'  // Ditolak - danger red
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        boxWidth: 12
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 12,
                    cornerRadius: 4
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>