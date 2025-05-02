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

// Get current year for default
$current_year = date('Y');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form data
    $tahun_anggaran = isset($_POST['tahun_anggaran']) ? (int)$_POST['tahun_anggaran'] : 0;
    $periode = isset($_POST['periode']) ? sanitizeInput($_POST['periode']) : '';
    $total_anggaran = isset($_POST['total_anggaran']) ? (float)str_replace(['.', ','], ['', '.'], $_POST['total_anggaran']) : 0;
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'rencana';
    $detail_kategori = isset($_POST['kategori']) ? $_POST['kategori'] : [];
    $detail_sub_kategori = isset($_POST['sub_kategori']) ? $_POST['sub_kategori'] : [];
    $detail_uraian = isset($_POST['uraian']) ? $_POST['uraian'] : [];
    $detail_jumlah = isset($_POST['jumlah']) ? $_POST['jumlah'] : [];
    $detail_keterangan = isset($_POST['keterangan']) ? $_POST['keterangan'] : [];

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
    $dokumen_anggaran = '';
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
                $dokumen_anggaran = $new_file_name;
            } else {
                $errors[] = 'Gagal mengupload file';
            }
        }
    }

    // Check if similar budget already exists
    $check_query = "SELECT COUNT(*) as count FROM anggaran_desa 
                    WHERE tahun_anggaran = '$tahun_anggaran' AND periode = '$periode'";
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
            // Insert into anggaran_desa table
            $query = "INSERT INTO anggaran_desa (tahun_anggaran, periode, total_anggaran, status, dokumen_anggaran, created_by) 
                      VALUES ('$tahun_anggaran', '$periode', '$total_anggaran', '$status', '$dokumen_anggaran', '$user_id')";
            
            $result = mysqli_query($koneksi, $query);
            
            if (!$result) {
                throw new Exception('Gagal menyimpan data anggaran');
            }
            
            $anggaran_id = mysqli_insert_id($koneksi);
            
            // Insert details
            for ($i = 0; $i < $detail_count; $i++) {
                $kategori = sanitizeInput($detail_kategori[$i]);
                $sub_kategori = sanitizeInput($detail_sub_kategori[$i]);
                $uraian = sanitizeInput($detail_uraian[$i]);
                $jumlah = (float)str_replace(['.', ','], ['', '.'], $detail_jumlah[$i]);
                $keterangan = sanitizeInput($detail_keterangan[$i]);
                
                $detail_query = "INSERT INTO detail_anggaran (anggaran_id, kategori, sub_kategori, uraian, jumlah_anggaran, keterangan) 
                                VALUES ('$anggaran_id', '$kategori', '$sub_kategori', '$uraian', '$jumlah', '$keterangan')";
                
                $detail_result = mysqli_query($koneksi, $detail_query);
                
                if (!$detail_result) {
                    throw new Exception('Gagal menyimpan detail anggaran');
                }
            }
            
            // Commit transaction
            mysqli_commit($koneksi);
            
            // Set success message and redirect
            $_SESSION['anggaran_success'] = 'Data anggaran berhasil ditambahkan';
            redirect('anggaran.php');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($koneksi);
            $errors[] = $e->getMessage();
        }
    }
}

// Set page title
$page_title = "Tambah Anggaran";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-container">
    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Tambah Anggaran Desa</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; 
                <a href="anggaran.php">Transparansi Anggaran</a> &raquo; 
                Tambah Anggaran
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
            <form action="anggaran_tambah.php" method="POST" enctype="multipart/form-data" id="anggaranForm">
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
                                for ($year = $current_year - 5; $year <= $current_year + 5; $year++) {
                                    $selected = ($year == $current_year) ? 'selected' : '';
                                    echo "<option value=\"$year\" $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="periode">Periode <span class="text-danger">*</span></label>
                            <select name="periode" id="periode" class="form-control" required>
                                <option value="">Pilih Periode</option>
                                <option value="tahunan">Tahunan</option>
                                <option value="semester1">Semester 1</option>
                                <option value="semester2">Semester 2</option>
                                <option value="triwulan1">Triwulan 1</option>
                                <option value="triwulan2">Triwulan 2</option>
                                <option value="triwulan3">Triwulan 3</option>
                                <option value="triwulan4">Triwulan 4</option>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="status">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="rencana">Rencana</option>
                                <?php if ($user_role == 'admin' || $user_role == 'kepala_desa'): ?>
                                <option value="disetujui">Disetujui</option>
                                <option value="realisasi">Realisasi</option>
                                <option value="laporan_akhir">Laporan Akhir</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="total_anggaran">Total Anggaran (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="total_anggaran" id="total_anggaran" class="form-control format-rupiah" placeholder="0" required readonly>
                            <small class="text-muted">Total akan dihitung otomatis dari detail anggaran</small>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="dokumen_anggaran">Dokumen Anggaran</label>
                            <input type="file" name="dokumen_anggaran" id="dokumen_anggaran" class="form-control-file">
                            <small class="text-muted">Format: PDF, DOC, DOCX, XLS, XLSX. Ukuran maksimal 5MB.</small>
                        </div>
                    </div>
                </div>

                <div class="card-header mt-4">
                    <h3>Detail Anggaran</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="detailTable">
                            <thead>
                                <tr>
                                    <th width="20%">Kategori <span class="text-danger">*</span></th>
                                    <th width="20%">Sub Kategori</th>
                                    <th width="25%">Uraian <span class="text-danger">*</span></th>
                                    <th width="15%">Jumlah (Rp) <span class="text-danger">*</span></th>
                                    <th width="15%">Keterangan</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
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
                    <button type="submit" class="btn btn-primary">Simpan Anggaran</button>
                    <a href="anggaran.php" class="btn btn-secondary">Batal</a>
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

    /* Button styling */
    .form-actions {
        margin-top: 1.5rem;
        padding: 1rem;
        background-color: #f8f9fa;
        border-top: 1px solid #efefef;
        text-align: right;
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

        // Add new row
        document.getElementById('addRow').addEventListener('click', function() {
            const tbody = document.querySelector('#detailTable tbody');
            const tr = document.createElement('tr');
            
            tr.innerHTML = `
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
            deleteButton.addEventListener('click', function() {
                const tbody = document.querySelector('#detailTable tbody');
                if (tbody.children.length > 1) {
                    row.remove();
                    calculateTotal();
                } else {
                    alert('Minimal harus ada satu baris detail anggaran');
                }
            });
            
            // Format rupiah on input
            jumlahInput.addEventListener('keyup', function(e) {
                this.value = formatRupiah(this.value);
                calculateTotal();
            });
        }

        // Initialize events for existing rows
        document.querySelectorAll('#detailTable tbody tr').forEach(function(row) {
            initializeRowEvents(row);
        });

        // Form validation
        document.getElementById('anggaranForm').addEventListener('submit', function(e) {
            const tahunAnggaran = document.getElementById('tahun_anggaran').value;
            const periode = document.getElementById('periode').value;
            const totalAnggaran = document.getElementById('total_anggaran').value;
            const detailRows = document.querySelectorAll('#detailTable tbody tr');
            
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
            
            if (detailRows.length === 0) {
                errorMessage += 'Minimal harus ada satu detail anggaran\n';
                isValid = false;
            }
            
            let hasEmptyFields = false;
            detailRows.forEach(function(row, index) {
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