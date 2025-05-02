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

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['anggaran_error'] = 'ID Anggaran tidak valid.';
    redirect('anggaran.php');
}

$anggaran_id = (int)$_GET['id'];

// Get anggaran data
$anggaran_query = "SELECT * FROM anggaran_desa WHERE anggaran_id = $anggaran_id";
$anggaran_result = mysqli_query($koneksi, $anggaran_query);

if (mysqli_num_rows($anggaran_result) == 0) {
    $_SESSION['anggaran_error'] = 'Data anggaran tidak ditemukan.';
    redirect('anggaran.php');
}

$anggaran = mysqli_fetch_assoc($anggaran_result);

// Check if anggaran is already final
if ($anggaran['status'] == 'laporan_akhir') {
    $_SESSION['anggaran_error'] = 'Anggaran dengan status Laporan Akhir tidak dapat diedit.';
    redirect('anggaran_detail.php?id=' . $anggaran_id);
}

// Get details data
$details_query = "SELECT * FROM detail_anggaran WHERE anggaran_id = $anggaran_id ORDER BY detail_id";
$details_result = mysqli_query($koneksi, $details_query);
$details = [];
while ($row = mysqli_fetch_assoc($details_result)) {
    $details[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form data
    $tahun_anggaran = isset($_POST['tahun_anggaran']) ? (int)$_POST['tahun_anggaran'] : 0;
    $periode = isset($_POST['periode']) ? sanitizeInput($_POST['periode']) : '';
    $total_anggaran = isset($_POST['total_anggaran']) ? (float)str_replace(['.', ','], ['', '.'], $_POST['total_anggaran']) : 0;
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'rencana';
    $detail_ids = isset($_POST['detail_id']) ? $_POST['detail_id'] : [];
    $detail_kategori = isset($_POST['kategori']) ? $_POST['kategori'] : [];
    $detail_sub_kategori = isset($_POST['sub_kategori']) ? $_POST['sub_kategori'] : [];
    $detail_uraian = isset($_POST['uraian']) ? $_POST['uraian'] : [];
    $detail_jumlah = isset($_POST['jumlah']) ? $_POST['jumlah'] : [];
    $detail_keterangan = isset($_POST['keterangan']) ? $_POST['keterangan'] : [];
    $details_to_delete = isset($_POST['delete']) ? $_POST['delete'] : [];

    // Initialize variables for validation
    $errors = [];

    // Validate required fields
    if ($tahun_anggaran <= 0) {
        $errors[] = "Tahun anggaran harus dipilih";
    }
    
    if (empty($periode)) {
        $errors[] = "Periode harus dipilih";
    }
    
    if ($total_anggaran <= 0) {
        $errors[] = "Total anggaran harus diisi dengan nilai lebih dari 0";
    }

    // Validate details
    $detail_count = count($detail_kategori);
    $total_detail = 0;
    
    if ($detail_count == 0) {
        $errors[] = "Minimal harus ada 1 detail anggaran";
    }
    
    for ($i = 0; $i < $detail_count; $i++) {
        if (empty($detail_kategori[$i])) {
            $errors[] = "Kategori pada detail ke-" . ($i + 1) . " harus diisi";
        }
        
        if (empty($detail_uraian[$i])) {
            $errors[] = "Uraian pada detail ke-" . ($i + 1) . " harus diisi";
        }
        
        $jumlah = (float)str_replace(['.', ','], ['', '.'], $detail_jumlah[$i]);
        if ($jumlah <= 0) {
            $errors[] = "Jumlah anggaran pada detail ke-" . ($i + 1) . " harus lebih dari 0";
        }
        $total_detail += $jumlah;
    }
    
    // Check if total matches details
    if (abs($total_anggaran - $total_detail) > 0.01) {
        $errors[] = "Total anggaran ($total_anggaran) tidak sama dengan jumlah detail anggaran ($total_detail)";
    }

    // Upload file if provided
    $dokumen_anggaran = $anggaran['dokumen_anggaran']; // Keep existing document by default
    if (isset($_FILES['dokumen_anggaran']) && $_FILES['dokumen_anggaran']['error'] == 0) {
        $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $file_name = $_FILES['dokumen_anggaran']['name'];
        $file_size = $_FILES['dokumen_anggaran']['size'];
        $file_tmp = $_FILES['dokumen_anggaran']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file extension
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = 'Ekstensi file tidak diizinkan. Upload dokumen dengan format ' . implode(', ', $allowed_ext);
        }
        
        // Check file size (max 5MB)
        if ($file_size > 5000000) {
            $errors[] = 'Ukuran file maksimal 5MB';
        }
        
        if (empty($errors)) {
            // Create upload directory if not exists
            $upload_dir = '../uploads/anggaran/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_file_name = 'anggaran_' . $tahun_anggaran . '_' . $periode . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // Upload file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old file if exists
                if (!empty($anggaran['dokumen_anggaran']) && file_exists($upload_dir . $anggaran['dokumen_anggaran'])) {
                    @unlink($upload_dir . $anggaran['dokumen_anggaran']);
                }
                $dokumen_anggaran = $new_file_name;
            } else {
                $errors[] = 'Gagal mengupload file';
            }
        }
    }

    // Check if similar budget already exists (except current one)
    $check_query = "SELECT COUNT(*) as count FROM anggaran_desa 
                    WHERE tahun_anggaran = '$tahun_anggaran' AND periode = '$periode' 
                    AND anggaran_id != $anggaran_id";
    $check_result = mysqli_query($koneksi, $check_query);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $errors[] = "Anggaran untuk tahun $tahun_anggaran dan periode $periode sudah ada";
    }

    // Process if no errors
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($koneksi);
        try {
            // Update anggaran_desa table
            $query = "UPDATE anggaran_desa SET 
                        tahun_anggaran = '$tahun_anggaran', 
                        periode = '$periode', 
                        total_anggaran = '$total_anggaran', 
                        status = '$status',
                        dokumen_anggaran = '$dokumen_anggaran'
                      WHERE anggaran_id = $anggaran_id";
            
            $result = mysqli_query($koneksi, $query);
            
            if (!$result) {
                throw new Exception('Gagal memperbarui data anggaran');
            }
            
            // Process deleted details
            if (!empty($details_to_delete)) {
                foreach ($details_to_delete as $detail_id) {
                    $detail_id = (int)$detail_id;
                    // First check if this detail has any realizations
                    $check_realisasi = "SELECT COUNT(*) as count FROM realisasi_anggaran WHERE detail_id = $detail_id";
                    $check_result = mysqli_query($koneksi, $check_realisasi);
                    $has_realisasi = mysqli_fetch_assoc($check_result)['count'] > 0;
                    
                    if ($has_realisasi) {
                        throw new Exception('Tidak dapat menghapus detail yang sudah memiliki realisasi');
                    }
                    
                    $delete_query = "DELETE FROM detail_anggaran WHERE detail_id = $detail_id AND anggaran_id = $anggaran_id";
                    $delete_result = mysqli_query($koneksi, $delete_query);
                    
                    if (!$delete_result) {
                        throw new Exception('Gagal menghapus detail anggaran');
                    }
                }
            }
            
            // Process details
            for ($i = 0; $i < $detail_count; $i++) {
                $detail_id = isset($detail_ids[$i]) ? (int)$detail_ids[$i] : 0;
                $kategori = sanitizeInput($detail_kategori[$i]);
                $sub_kategori = sanitizeInput($detail_sub_kategori[$i]);
                $uraian = sanitizeInput($detail_uraian[$i]);
                $jumlah = (float)str_replace(['.', ','], ['', '.'], $detail_jumlah[$i]);
                $keterangan = sanitizeInput($detail_keterangan[$i]);
                
                if ($detail_id > 0) {
                    // Update existing detail
                    // Check if this detail has any realizations
                    $check_realisasi = "SELECT SUM(jumlah_realisasi) as total_realisasi FROM detail_anggaran WHERE detail_id = $detail_id";
                    $check_result = mysqli_query($koneksi, $check_realisasi);
                    $total_realisasi = mysqli_fetch_assoc($check_result)['total_realisasi'] ?? 0;
                    
                    // Cannot reduce budget below already realized amount
                    if ($jumlah < $total_realisasi) {
                        throw new Exception("Jumlah anggaran tidak boleh kurang dari total realisasi (Rp " . number_format($total_realisasi, 0, ',', '.') . ")");
                    }
                    
                    $detail_query = "UPDATE detail_anggaran SET 
                                    kategori = '$kategori', 
                                    sub_kategori = '$sub_kategori', 
                                    uraian = '$uraian', 
                                    jumlah_anggaran = '$jumlah', 
                                    keterangan = '$keterangan' 
                                WHERE detail_id = $detail_id AND anggaran_id = $anggaran_id";
                } else {
                    // Insert new detail
                    $detail_query = "INSERT INTO detail_anggaran (anggaran_id, kategori, sub_kategori, uraian, jumlah_anggaran, keterangan) 
                                    VALUES ('$anggaran_id', '$kategori', '$sub_kategori', '$uraian', '$jumlah', '$keterangan')";
                }
                
                $detail_result = mysqli_query($koneksi, $detail_query);
                
                if (!$detail_result) {
                    throw new Exception('Gagal menyimpan detail anggaran');
                }
            }
            
            // Commit transaction
            mysqli_commit($koneksi);
            
            // Set success message and redirect
            $_SESSION['anggaran_success'] = 'Data anggaran berhasil diperbarui';
            redirect('anggaran_detail.php?id=' . $anggaran_id);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($koneksi);
            $errors[] = $e->getMessage();
        }
    }
}

// Set page title
$page_title = "Edit Anggaran";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">
    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Edit Anggaran Desa</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; 
                <a href="anggaran.php">Transparansi Anggaran</a> &raquo; 
                <a href="anggaran_detail.php?id=<?php echo $anggaran_id; ?>">Detail Anggaran</a> &raquo; 
                Edit Anggaran
            </nav>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="data-card">
            <form action="anggaran_edit.php?id=<?php echo $anggaran_id; ?>" method="POST" enctype="multipart/form-data" id="anggaranForm">
                <div class="card-header">
                    <h3>Informasi Umum</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="tahun_anggaran">Tahun Anggaran <span class="text-danger">*</span></label>
                            <select name="tahun_anggaran" id="tahun_anggaran" class="form-control" required>
                                <option value="">Pilih Tahun</option>
                                <?php
                                // Generate year options (current year - 5 to current year + 5)
                                $current_year = date('Y');
                                for ($year = $current_year - 5; $year <= $current_year + 5; $year++) {
                                    $selected = ($year == $anggaran['tahun_anggaran']) ? 'selected' : '';
                                    echo "<option value=\"$year\" $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="periode">Periode <span class="text-danger">*</span></label>
                            <select name="periode" id="periode" class="form-control" required>
                                <option value="">Pilih Periode</option>
                                <option value="tahunan" <?php echo $anggaran['periode'] == 'tahunan' ? 'selected' : ''; ?>>Tahunan</option>
                                <option value="semester1" <?php echo $anggaran['periode'] == 'semester1' ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="semester2" <?php echo $anggaran['periode'] == 'semester2' ? 'selected' : ''; ?>>Semester 2</option>
                                <option value="triwulan1" <?php echo $anggaran['periode'] == 'triwulan1' ? 'selected' : ''; ?>>Triwulan 1</option>
                                <option value="triwulan2" <?php echo $anggaran['periode'] == 'triwulan2' ? 'selected' : ''; ?>>Triwulan 2</option>
                                <option value="triwulan3" <?php echo $anggaran['periode'] == 'triwulan3' ? 'selected' : ''; ?>>Triwulan 3</option>
                                <option value="triwulan4" <?php echo $anggaran['periode'] == 'triwulan4' ? 'selected' : ''; ?>>Triwulan 4</option>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="status">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="rencana" <?php echo $anggaran['status'] == 'rencana' ? 'selected' : ''; ?>>Rencana</option>
                                <?php if ($user_role == 'admin' || $user_role == 'kepala_desa'): ?>
                                <option value="disetujui" <?php echo $anggaran['status'] == 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                                <option value="realisasi" <?php echo $anggaran['status'] == 'realisasi' ? 'selected' : ''; ?>>Realisasi</option>
                                <option value="laporan_akhir" <?php echo $anggaran['status'] == 'laporan_akhir' ? 'selected' : ''; ?>>Laporan Akhir</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="total_anggaran">Total Anggaran (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="total_anggaran" id="total_anggaran" class="form-control format-rupiah" placeholder="0" value="<?php echo number_format($anggaran['total_anggaran'], 0, ',', '.'); ?>" required readonly>
                            <small class="text-muted">Total akan dihitung otomatis dari detail anggaran</small>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="dokumen_anggaran">Dokumen Anggaran</label>
                            <input type="file" name="dokumen_anggaran" id="dokumen_anggaran" class="form-control-file">
                            <small class="text-muted">Format: PDF, DOC, DOCX, XLS, XLSX. Ukuran maksimal 5MB.</small>
                            <?php if (!empty($anggaran['dokumen_anggaran'])): ?>
                            <div class="mt-2">
                                <a href="../uploads/anggaran/<?php echo $anggaran['dokumen_anggaran']; ?>" target="_blank" class="btn-sm btn-info">
                                    Lihat Dokumen Saat Ini
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-header mt-4">
                    <h3>Detail Anggaran</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Detail anggaran yang sudah memiliki realisasi tidak dapat dihapus dan jumlahnya tidak boleh kurang dari total realisasi.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="detailTable">
                            <thead>
                                <tr>
                                    <th width="18%">Kategori <span class="text-danger">*</span></th>
                                    <th width="17%">Sub Kategori</th>
                                    <th width="25%">Uraian <span class="text-danger">*</span></th>
                                    <th width="15%">Jumlah (Rp) <span class="text-danger">*</span></th>
                                    <th width="15%">Keterangan</th>
                                    <th width="10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_details = false;
                                if (!empty($details)): 
                                    $has_details = true;
                                    foreach ($details as $index => $detail): 
                                        // Check if this detail has any realizations
                                        $detail_id = $detail['detail_id'];
                                        $check_realisasi = "SELECT SUM(jumlah) as total_realisasi FROM realisasi_anggaran WHERE detail_id = $detail_id";
                                        $check_result = mysqli_query($koneksi, $check_realisasi);
                                        $total_realisasi = mysqli_fetch_assoc($check_result)['total_realisasi'] ?? 0;
                                        $has_realisasi = $total_realisasi > 0;
                                ?>
                                <tr id="detail-row-<?php echo $index; ?>">
                                    <input type="hidden" name="detail_id[]" value="<?php echo $detail['detail_id']; ?>">
                                    <td>
                                        <input type="text" name="kategori[]" class="form-control" value="<?php echo htmlspecialchars($detail['kategori']); ?>" required <?php echo $has_realisasi ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" name="sub_kategori[]" class="form-control" value="<?php echo htmlspecialchars($detail['sub_kategori']); ?>" <?php echo $has_realisasi ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" name="uraian[]" class="form-control" value="<?php echo htmlspecialchars($detail['uraian']); ?>" required <?php echo $has_realisasi ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" name="jumlah[]" class="form-control format-rupiah detail-jumlah" placeholder="0" value="<?php echo number_format($detail['jumlah_anggaran'], 0, ',', '.'); ?>" required <?php echo $has_realisasi ? 'data-min="'.$total_realisasi.'"' : ''; ?>>
                                        <?php if ($has_realisasi): ?>
                                        <small class="text-danger">Min: Rp <?php echo number_format($total_realisasi, 0, ',', '.'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan[]" class="form-control" value="<?php echo htmlspecialchars($detail['keterangan']); ?>">
                                    </td>
                                    <td class="text-center">
                                        <?php if (!$has_realisasi): ?>
                                        <button type="button" class="btn-sm btn-danger btn-delete-row" data-detail-id="<?php echo $detail['detail_id']; ?>">✖</button>
                                        <input type="checkbox" name="delete[]" value="<?php echo $detail['detail_id']; ?>" class="d-none delete-checkbox">
                                        <?php else: ?>
                                        <span class="badge badge-warning">Sudah Realisasi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <?php if (!$has_details): ?>
                                <tr>
                                    <input type="hidden" name="detail_id[]" value="0">
                                    <td>
                                        <input type="text" name="kategori[]" class="form-control" required>
                                    </td>
                                    <td>
                                        <input type="text" name="sub_kategori[]" class="form-control">
                                    </td>
                                    <td>
                                        <input type="text" name="uraian[]" class="form-control" required>
                                    </td>
                                    <td>
                                        <input type="text" name="jumlah[]" class="form-control format-rupiah detail-jumlah" placeholder="0" required>
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan[]" class="form-control">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-sm btn-danger btn-delete-row">✖</button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6">
                                        <button type="button" class="btn btn-success btn-sm" id="addRow">+ Tambah Baris</button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="anggaran_detail.php?id=<?php echo $anggaran_id; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Form styling */
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

    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
    }

    .form-group {
        margin-bottom: 1rem;
        padding-right: 10px;
        padding-left: 10px;
    }

    .col-md-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }

    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }

    label {
        display: inline-block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .form-control {
        display: block;
        width: 100%;
        height: calc(2.25rem + 2px);
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus {
        color: #495057;
        background-color: #fff;
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .form-control:disabled,
    .form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
    }

    .form-control-file {
        display: block;
        width: 100%;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .text-muted {
        color: #6c757d !important;
        font-size: 0.85rem;
    }

    /* Alert */
    .alert {
        position: relative;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    /* Table styling */
    .table {
        width: 100%;
        margin-bottom: 1rem;
        background-color: transparent;
        border-collapse: collapse;
    }

    .table-bordered {
        border: 1px solid #dee2e6;
    }

    .table-bordered th,
    .table-bordered td {
        border: 1px solid #dee2e6;
        padding: 0.75rem;
        vertical-align: middle;
    }

    .table-bordered thead th {
        vertical-align: bottom;
        background-color: #f8f9fa;
        font-weight: 600;
    }

    /* Badge */
    .badge {
        display: inline-block;
        padding: 0.25em 0.4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }

    .badge-warning {
        color: #212529;
        background-color: #ffc107;
    }

    /* Button styling */
    .form-actions {
        margin-top: 1.5rem;
        padding: 1rem;
        background-color: #f8f9fa;
        border-top: 1px solid #efefef;
        text-align: right;
    }

    .btn {
        display: inline-block;
        font-weight: 400;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: 0.25rem;
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        text-decoration: none;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: 0.2rem;
    }

    .btn-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }

    .btn-primary:hover {
        color: #fff;
        background-color: #0069d9;
        border-color: #0062cc;
    }

    .btn-secondary {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .btn-secondary:hover {
        color: #fff;
        background-color: #5a6268;
        border-color: #545b62;
    }

    .btn-success {
        color: #fff;
        background-color: #28a745;
        border-color: #28a745;
    }

    .btn-success:hover {
        color: #fff;
        background-color: #218838;
        border-color: #1e7e34;
    }

    .btn-danger {
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }

    .btn-danger:hover {
        color: #fff;
        background-color: #c82333;
        border-color: #bd2130;
    }

    .btn-info {
        color: #fff;
        background-color: #17a2b8;
        border-color: #17a2b8;
    }

    .btn-info:hover {
        color: #fff;
        background-color: #138496;
        border-color: #117a8b;
    }

    /* Utility classes */
    .mt-2 {
        margin-top: 0.5rem !important;
    }

    .mt-4 {
        margin-top: 1.5rem !important;
    }

    .mb-0 {
        margin-bottom: 0 !important;
    }

    .d-none {
        display: none !important;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }

        .col-md-4,
        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to format number as rupiah
        function formatRupiah(angka) {
            var number_string = angka.toString().replace(/[^,\d]/g, ''),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            return rupiah;
        }

        // Function to unformat rupiah to number
        function unformatRupiah(rupiah) {
            return parseFloat(rupiah.replace(/\./g, ''));
        }

        // Function to calculate total
        function calculateTotal() {
            let total = 0;
            const detailJumlah = document.querySelectorAll('.detail-jumlah');
            
            detailJumlah.forEach(function(input) {
                const value = input.value.replace(/\./g, '');
                if (value !== '') {
                    total += parseInt(value);
                }
            });
            
            document.getElementById('total_anggaran').value = formatRupiah(total);
        }

        // Initialize format rupiah for existing inputs
        document.querySelectorAll('.format-rupiah').forEach(function(input) {
            input.addEventListener('keyup', function() {
                this.value = formatRupiah(this.value);
                if (this.classList.contains('detail-jumlah')) {
                    calculateTotal();
                }
            });
        });

        // Add new row
        document.getElementById('addRow').addEventListener('click', function() {
            const tbody = document.querySelector('#detailTable tbody');
            const rowCount = tbody.children.length;
            const tr = document.createElement('tr');
            tr.setAttribute('id', 'detail-row-' + rowCount);
            
            tr.innerHTML = `
                <input type="hidden" name="detail_id[]" value="0">
                <td>
                    <input type="text" name="kategori[]" class="form-control" required>
                </td>
                <td>
                    <input type="text" name="sub_kategori[]" class="form-control">
                </td>
                <td>
                    <input type="text" name="uraian[]" class="form-control" required>
                </td>
                <td>
                    <input type="text" name="jumlah[]" class="form-control format-rupiah detail-jumlah" placeholder="0" required>
                </td>
                <td>
                    <input type="text" name="keterangan[]" class="form-control">
                </td>
                <td class="text-center">
                    <button type="button" class="btn-sm btn-danger btn-delete-row">✖</button>
                </td>
            `;
            
            tbody.appendChild(tr);
            
            // Add event listeners to new elements
            initializeRowEvents(tr);
        });

        // Function to initialize events for a row
        function initializeRowEvents(row) {
            const deleteButton = row.querySelector('.btn-delete-row');
            const jumlahInput = row.querySelector('.detail-jumlah');
            
            // Delete row
            if (deleteButton) {
                deleteButton.addEventListener('click', function() {
                    const detailId = this.getAttribute('data-detail-id');
                    const tbody = document.querySelector('#detailTable tbody');
                    
                    if (tbody.children.length > 1) {
                        if (detailId) {
                            // Mark for deletion instead of removing from DOM
                            const checkbox = row.querySelector('.delete-checkbox');
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                            row.style.display = 'none';
                        } else {
                            row.remove();
                        }
                        calculateTotal();
                    } else {
                        alert('Minimal harus ada satu baris detail anggaran');
                    }
                });
            }
            
            // Format rupiah on input
            if (jumlahInput) {
                jumlahInput.addEventListener('keyup', function(e) {
                    // Check minimum value (for items with realization)
                    const minValue = this.getAttribute('data-min');
                    if (minValue) {
                        const currentValue = unformatRupiah(this.value) || 0;
                        if (currentValue < parseFloat(minValue)) {
                            alert('Jumlah anggaran tidak boleh kurang dari Rp ' + formatRupiah(minValue));
                            this.value = formatRupiah(minValue);
                        }
                    }
                    
                    this.value = formatRupiah(this.value);
                    calculateTotal();
                });
            }
        }

        // Initialize events for existing rows
        document.querySelectorAll('#detailTable tbody tr').forEach(function(row) {
            initializeRowEvents(row);
        });

        // Calculate initial total
        calculateTotal();

        // Form validation
        document.getElementById('anggaranForm').addEventListener('submit', function(e) {
            const tahunAnggaran = document.getElementById('tahun_anggaran').value;
            const periode = document.getElementById('periode').value;
            const totalAnggaran = document.getElementById('total_anggaran').value;
            const visibleRows = Array.from(document.querySelectorAll('#detailTable tbody tr')).filter(row => row.style.display !== 'none');
            
            let isValid = true;
            let errorMessage = '';
            
            if (!tahunAnggaran) {
                errorMessage += 'Tahun anggaran harus dipilih\n';
                isValid = false;
            }
            
            if (!periode) {
                errorMessage += 'Periode harus dipilih\n';
                isValid = false;
            }
            
            if (!totalAnggaran || totalAnggaran === '0') {
                errorMessage += 'Total anggaran harus lebih dari 0\n';
                isValid = false;
            }
            
            if (visibleRows.length === 0) {
                errorMessage += 'Minimal harus ada satu detail anggaran\n';
                isValid = false;
            }
            
            let hasEmptyFields = false;
            visibleRows.forEach(function(row, index) {
                const kategori = row.querySelector('input[name="kategori[]"]').value;
                const uraian = row.querySelector('input[name="uraian[]"]').value;
                const jumlah = row.querySelector('input[name="jumlah[]"]').value;
                
                if (!kategori || !uraian || !jumlah) {
                    hasEmptyFields = true;
                }
            });
            
            if (hasEmptyFields) {
                errorMessage += 'Semua kolom kategori, uraian, dan jumlah harus diisi\n';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Mohon perbaiki kesalahan berikut:\n' + errorMessage);
            }
        });
    });
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>