<?php
// user/data-warga.php - User interface for viewing citizen data (read-only)

// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';
// require_once '../includes/session.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Page title for header
$pageTitle = "Data Warga - SIPANDAI";

// Include header
include '../includes/header.php';
?>

<!-- Main Content -->
<main>
    <section class="page-header">
        <h2>Pencarian Data Warga</h2>
        <p>Sistem informasi pencarian data warga desa yang terintegrasi.</p>
    </section>

    <section class="content-section">
        <div class="search-box">
            <form action="" method="GET">
                <input type="text" name="keyword" class="search-input" placeholder="Cari berdasarkan nama, NIK, atau nomor KK...">
                <button type="submit" class="search-button">Cari</button>
            </form>
        </div>

        <div class="search-filters">
            <form action="" method="GET">
                <div>
                    <span class="filter-label">Filter:</span>
                    <select name="dusun" class="filter-select">
                        <option value="">Semua Dusun</option>
                        <?php
                        // Get dusun list from database
                        $query = "SELECT DISTINCT alamat FROM users WHERE alamat LIKE '%Dusun%' ORDER BY alamat";
                        $result = mysqli_query($koneksi, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<option value='" . htmlspecialchars($row['alamat']) . "'>" . htmlspecialchars($row['alamat']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <select name="gender" class="filter-select">
                        <option value="">Semua Jenis Kelamin</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-filter">Terapkan Filter</button>
                </div>
            </form>
        </div>

        <div class="search-results">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>NIK</th>
                        <th>Nama Lengkap</th>
                        <th>Jenis Kelamin</th>
                        <th>Tempat, Tgl Lahir</th>
                        <th>Alamat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Set up pagination
                    $limit = 10;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $start = ($page - 1) * $limit;
                    
                    // Base query - for normal users, only show basic public information
                    $sql = "SELECT * FROM users WHERE role = 'warga' AND active = TRUE";
                    
                    // Add search filters if provided
                    if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
                        $keyword = mysqli_real_escape_string($koneksi, $_GET['keyword']);
                        $sql .= " AND (nama LIKE '%$keyword%' OR nik LIKE '%$keyword%')";
                    }
                    
                    if (isset($_GET['dusun']) && !empty($_GET['dusun'])) {
                        $dusun = mysqli_real_escape_string($koneksi, $_GET['dusun']);
                        $sql .= " AND alamat = '$dusun'";
                    }
                    
                    if (isset($_GET['gender']) && !empty($_GET['gender'])) {
                        $gender = mysqli_real_escape_string($koneksi, $_GET['gender']);
                        $sql .= " AND jenis_kelamin = '$gender'";
                    }
                    
                    // Add ordering and limit
                    $sql .= " ORDER BY nama ASC LIMIT $start, $limit";
                    
                    $result = mysqli_query($koneksi, $sql);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Format tanggal lahir
                            $tanggal_lahir = date('d-m-Y', strtotime($row['tanggal_lahir']));
                            
                            // Mask the NIK for privacy (show only last 4 digits)
                            $masked_nik = substr($row['nik'], 0, -4) . "XXXX";
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($masked_nik); ?></td>
                        <td><?php echo htmlspecialchars($row['nama']); ?></td>
                        <td><?php echo htmlspecialchars($row['jenis_kelamin']); ?></td>
                        <td><?php echo "-, " . $tanggal_lahir; ?></td>
                        <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                        <td>
                            <a href="detail-warga.php?id=<?php echo $row['user_id']; ?>" class="btn-outline">Lihat</a>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>Tidak ada data warga</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="pagination">
                <?php
                // Count total records for pagination
                $count_query = "SELECT COUNT(*) as total FROM users WHERE role = 'warga' AND active = TRUE";
                $count_result = mysqli_query($koneksi, $count_query);
                $count_row = mysqli_fetch_assoc($count_result);
                $total_pages = ceil($count_row['total'] / $limit);
                
                // Generate pagination links
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active_class = ($i == $page) ? 'active' : '';
                    echo "<a href='?page=$i" . 
                        (isset($_GET['keyword']) ? '&keyword=' . urlencode($_GET['keyword']) : '') . 
                        (isset($_GET['dusun']) ? '&dusun=' . urlencode($_GET['dusun']) : '') . 
                        (isset($_GET['gender']) ? '&gender=' . urlencode($_GET['gender']) : '') . 
                        "' class='page-link $active_class'>$i</a>";
                }
                ?>
            </div>
        </div>
    </section>
</main>

<?php
// Include footer
include '../includes/footer.php';
?>