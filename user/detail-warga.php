<?php
// user/detail-warga.php - Detailed information about a specific citizen

// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user ID is provided in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to the data-warga.php page if no ID is provided
    header('Location: data-warga.php');
    exit;
}

// Get the user ID from the URL
$user_id = (int)$_GET['id'];

// Fetch user data from database
$query = "SELECT * FROM users WHERE user_id = ? AND role = 'warga' AND active = TRUE";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Check if user exists
if (mysqli_num_rows($result) == 0) {
    // User not found, redirect to data-warga.php
    header('Location: data-warga.php');
    exit;
}

// Get user data
$user = mysqli_fetch_assoc($result);

// Calculate age from date of birth
$birthDate = new DateTime($user['tanggal_lahir']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;

// Mask sensitive information for privacy
$masked_nik = substr($user['nik'], 0, -4) . "XXXX";
$masked_telepon = !empty($user['nomor_telepon']) ? "0XXX-XXX-" . substr($user['nomor_telepon'], -4) : "-";

// Format date of birth
$tanggal_lahir = date('d-m-Y', strtotime($user['tanggal_lahir']));

// Page title for header
$pageTitle = "Detail Warga - SIPANDAI";

// Include header
include '../includes/header.php';
?>

<!-- Main Content -->
<main>
    <section class="page-header">
        <div class="breadcrumb">
            <a href="data-warga.php">Data Warga</a> &raquo; Detail Warga
        </div>
        <h2>Detail Data Warga</h2>
        <p>Informasi lengkap tentang warga desa.</p>
    </section>

    <section class="content-section">
        <div class="warga-detail-card">
            <div class="detail-header">
                <div class="profile-image">
                    <?php if (!empty($user['foto_profil'])): ?>
                        <img src="<?php echo htmlspecialchars('../uploads/profile/' . $user['foto_profil']); ?>" alt="Foto Profil">
                    <?php else: ?>
                        <div class="profile-placeholder">
                            <i class="fa fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($user['nama']); ?></h3>
                    <p class="subtitle">Warga Desa</p>
                </div>
            </div>

            <div class="detail-body">
                <h4>Informasi Pribadi</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">NIK:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($masked_nik); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Jenis Kelamin:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['jenis_kelamin']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tanggal Lahir:</span>
                        <span class="detail-value"><?php echo $tanggal_lahir; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Usia:</span>
                        <span class="detail-value"><?php echo $age; ?> tahun</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Alamat:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['alamat']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Nomor Telepon:</span>
                        <span class="detail-value"><?php echo $masked_telepon; ?></span>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="data-warga.php" class="btn-outline">Kembali</a>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin')): ?>
                    <a href="../admin/edit-warga.php?id=<?php echo $user_id; ?>" class="btn-primary">Edit Data</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- CSS for this page -->
<style>
    .warga-detail-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .detail-header {
        background-color: #f5f7fa;
        padding: 20px;
        display: flex;
        align-items: center;
        border-bottom: 1px solid #e5e9f0;
    }
    
    .profile-image {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 20px;
        background-color: #e5e9f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .profile-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: #7b8a9a;
    }
    
    .profile-info h3 {
        margin: 0 0 5px 0;
        font-size: 24px;
        color: #2d3748;
    }
    
    .subtitle {
        margin: 0;
        color: #64748b;
        font-weight: normal;
    }
    
    .detail-body {
        padding: 20px;
    }
    
    .detail-body h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2d3748;
        border-bottom: 1px solid #e5e9f0;
        padding-bottom: 10px;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px 30px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        margin-bottom: 10px;
    }
    
    .detail-label {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 5px;
    }
    
    .detail-value {
        font-size: 16px;
        color: #2d3748;
    }
    
    .mt-4 {
        margin-top: 25px;
    }
    
    .table-responsive {
        overflow-x: auto;
        margin-bottom: 20px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e5e9f0;
    }
    
    .data-table th {
        background-color: #f5f7fa;
        color: #475569;
        font-weight: 600;
    }
    
    .data-table tr:hover {
        background-color: #f8fafc;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-diajukan, .status-belum_bayar {
        background-color: #e9eef8;
        color: #3b82f6;
    }
    
    .status-verifikasi, .status-proses {
        background-color: #fef3c7;
        color: #d97706;
    }
    
    .status-selesai, .status-lunas {
        background-color: #dcfce7;
        color: #16a34a;
    }
    
    .status-ditolak, .status-telat {
        background-color: #fee2e2;
        color: #ef4444;
    }
    
    .action-buttons {
        margin-top: 30px;
        display: flex;
        gap: 10px;
    }
    
    .btn-outline {
        padding: 8px 15px;
        background-color: transparent;
        border: 1px solid #3b82f6;
        color: #3b82f6;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .btn-outline:hover {
        background-color: #f0f7ff;
    }
    
    .btn-primary {
        padding: 8px 15px;
        background-color: #3b82f6;
        border: 1px solid #3b82f6;
        color: white;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background-color: #2563eb;
    }
    
    .breadcrumb {
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .breadcrumb a {
        color: #64748b;
        text-decoration: none;
    }
    
    .breadcrumb a:hover {
        text-decoration: underline;
    }
    
    .no-data {
        color: #64748b;
        font-style: italic;
        text-align: center;
        padding: 20px;
    }
    
    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
        
        .profile-image {
            width: 80px;
            height: 80px;
        }
    }
</style>

<?php
// Include footer
include '../includes/footer.php';
?>