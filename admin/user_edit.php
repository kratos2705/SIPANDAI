<?php
// Include necessary files
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    $_SESSION['login_error'] = 'Anda tidak memiliki akses ke halaman ini.';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get user ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['user_error'] = 'ID Pengguna tidak valid.';
    redirect('warga.php');
}

$user_id = (int)$_GET['id'];

// Get user data
$user_query = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($koneksi, $user_query);

if (mysqli_num_rows($user_result) == 0) {
    $_SESSION['user_error'] = 'Pengguna tidak ditemukan.';
    redirect('warga.php');
}

$user_data = mysqli_fetch_assoc($user_result);

// Initialize variables
$error = [];
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $alamat = sanitizeInput($_POST['alamat']);
    $nomor_telepon = sanitizeInput($_POST['nomor_telepon']);
    $tanggal_lahir = sanitizeInput($_POST['tanggal_lahir']);
    $jenis_kelamin = sanitizeInput($_POST['jenis_kelamin']);
    $role = sanitizeInput($_POST['role']);
    $active = isset($_POST['active']) ? 1 : 0;

    // Validation
    if (empty($nama)) {
        $error[] = 'Nama tidak boleh kosong';
    }

    if (empty($email)) {
        $error[] = 'Email tidak boleh kosong';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error[] = 'Format email tidak valid';
    }

    // Check if email already exists (but not for the current user)
    $check_email_query = "SELECT user_id FROM users WHERE email = '$email' AND user_id != $user_id";
    $check_email_result = mysqli_query($koneksi, $check_email_query);
    if (mysqli_num_rows($check_email_result) > 0) {
        $error[] = 'Email sudah digunakan oleh pengguna lain';
    }

    // Check if password is being updated
    $password_update = '';
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($password) < 6) {
            $error[] = 'Password minimal 6 karakter';
        }

        if ($password !== $confirm_password) {
            $error[] = 'Password dan konfirmasi password tidak sama';
        }

        if (empty($error)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $password_update = ", password = '$hashed_password'";
        }
    }

    // Process upload if there's a file
    $foto_update = '';
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['foto_profil']['name'];
        $filesize = $_FILES['foto_profil']['size'];
        $filetype = $_FILES['foto_profil']['type'];
        $temp = $_FILES['foto_profil']['tmp_name'];

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), $allowed)) {
            $error[] = 'Format file foto tidak valid. Hanya JPG, JPEG, dan PNG yang diizinkan.';
        } else if ($filesize > 2097152) { // 2MB max
            $error[] = 'Ukuran file tidak boleh lebih dari 2MB';
        } else {
            $foto_profil = 'user_' . time() . '_' . $user_data['nik'] . '.' . $ext;
            $upload_dir = '../uploads/profil/';

            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $target = $upload_dir . $foto_profil;
            if (move_uploaded_file($temp, $target)) {
                // Delete old photo if exists
                if (!empty($user_data['foto_profil']) && file_exists($upload_dir . $user_data['foto_profil'])) {
                    unlink($upload_dir . $user_data['foto_profil']);
                }

                $foto_update = ", foto_profil = '$foto_profil'";
            } else {
                $error[] = 'Gagal mengupload file foto';
            }
        }
    }

    // If no errors, update user in database
    if (empty($error)) {
        // Prepare tanggal_lahir for insertion
        $tanggal_lahir_sql = !empty($tanggal_lahir) ? "'$tanggal_lahir'" : "NULL";

        // Update query
        $update_query = "UPDATE users SET 
                        nama = '$nama', 
                        email = '$email', 
                        alamat = '$alamat', 
                        nomor_telepon = '$nomor_telepon', 
                        tanggal_lahir = $tanggal_lahir_sql, 
                        jenis_kelamin = '$jenis_kelamin', 
                        role = '$role', 
                        active = $active
                        $password_update
                        $foto_update
                        WHERE user_id = $user_id";

        $result = mysqli_query($koneksi, $update_query);

        if ($result) {
            // Success
            $_SESSION['user_success'] = 'Data pengguna berhasil diperbarui';
            redirect('warga.php');
        } else {
            // Error
            $error[] = 'Gagal memperbarui data pengguna: ' . mysqli_error($koneksi);
        }
    }
}

// Set page title
$page_title = "Edit Pengguna";

// Include header
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>
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

    /* Form styles */
    .form-horizontal {
        padding: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group h3 {
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 20px;
        color: #343a40;
    }

    .form-control {
        display: block;
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 0.9rem;
        transition: border-color 0.15s ease-in-out;
    }

    .form-control:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .form-control:disabled,
    .form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
    }

    .form-text {
        margin-top: 5px;
        font-size: 0.85rem;
    }

    .text-muted {
        color: #6c757d;
    }

    .form-row {
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
        box-sizing: border-box;
    }

    .required {
        color: #dc3545;
    }

    /* Radio and checkbox styles */
    .radio-group {
        display: flex;
        gap: 20px;
    }

    .radio-inline,
    .checkbox {
        display: flex;
        align-items: center;
        margin-bottom: 0;
        font-weight: 400;
        cursor: pointer;
    }

    .radio-inline input,
    .checkbox input {
        margin-right: 5px;
    }

    /* Current photo styles */
    .current-photo {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }

    .profile-img-preview {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #dee2e6;
        margin-right: 10px;
    }

    .current-photo-label {
        font-size: 0.9rem;
        color: #6c757d;
    }

    /* Action buttons */
    .form-actions {
        margin-top: 30px;
        display: flex;
        gap: 10px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }

        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
            margin-bottom: 15px;
        }

        .radio-group {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<div class="admin-container">
    <!-- Admin Content -->
    <div class="admin-content">
        <div class="admin-header">
            <h2>Edit Pengguna</h2>
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo;
                <a href="warga.php">Data Warga</a> &raquo;
                Edit Pengguna
            </nav>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($error as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="button-container">
            <a href="warga.php" class="btn btn-secondary">
                <span class="btn-icon">↩</span> Kembali
            </a>
            <a href="user_detail.php?id=<?php echo $user_id; ?>" class="btn btn-info">
                <span class="btn-icon">👁️</span> Lihat Detail
            </a>
        </div>

        <div class="data-card">
            <form action="" method="POST" enctype="multipart/form-data" class="form-horizontal">
                <div class="form-group">
                    <h3>Data Pribadi</h3>
                </div>

                <div class="form-group">
                    <label for="nik">NIK</label>
                    <input type="text" id="nik" class="form-control" value="<?php echo htmlspecialchars($user_data['nik']); ?>" readonly disabled>
                    <small class="form-text text-muted">NIK tidak dapat diubah</small>
                </div>

                <div class="form-group">
                    <label for="nama">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" id="nama" name="nama" class="form-control" value="<?php echo htmlspecialchars($user_data['nama']); ?>" placeholder="Nama lengkap sesuai KTP" required>
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" placeholder="Email aktif" required>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="password">Password Baru</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
                        <small class="form-text text-muted">Minimal 6 karakter</small>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="confirm_password">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Ulangi password baru">
                    </div>
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" class="form-control" rows="3" placeholder="Alamat lengkap"><?php echo htmlspecialchars($user_data['alamat']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nomor_telepon">Nomor Telepon</label>
                        <input type="text" id="nomor_telepon" name="nomor_telepon" class="form-control" value="<?php echo htmlspecialchars($user_data['nomor_telepon']); ?>" placeholder="Contoh: 08123456789">
                    </div>

                    <div class="form-group col-md-6">
                        <label for="tanggal_lahir">Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control" value="<?php echo !empty($user_data['tanggal_lahir']) ? date('Y-m-d', strtotime($user_data['tanggal_lahir'])) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <div class="radio-group">
                        <label class="radio-inline">
                            <input type="radio" name="jenis_kelamin" value="Laki-laki" <?php echo ($user_data['jenis_kelamin'] == 'Laki-laki') ? 'checked' : ''; ?>> Laki-laki
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="jenis_kelamin" value="Perempuan" <?php echo ($user_data['jenis_kelamin'] == 'Perempuan') ? 'checked' : ''; ?>> Perempuan
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="foto_profil">Foto Profil</label>
                    <?php if (!empty($user_data['foto_profil'])): ?>
                        <div class="current-photo">
                            <img src="../uploads/profil/<?php echo htmlspecialchars($user_data['foto_profil']); ?>" alt="Foto profil" class="profile-img-preview">
                            <div class="current-photo-label">Foto saat ini</div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="foto_profil" name="foto_profil" class="form-control-file">
                    <small class="form-text text-muted">Format file: JPG, JPEG, PNG. Maksimal 2MB</small>
                </div>

                <div class="form-group">
                    <h3>Informasi Akun</h3>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="role">Peran <span class="required">*</span></label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="warga" <?php echo ($user_data['role'] == 'warga') ? 'selected' : ''; ?>>Warga</option>
                            <option value="admin" <?php echo ($user_data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="kepala_desa" <?php echo ($user_data['role'] == 'kepala_desa') ? 'selected' : ''; ?>>Kepala Desa</option>
                        </select>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="active">Status Akun</label>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="active" name="active" value="1" <?php echo ($user_data['active'] == 1) ? 'checked' : ''; ?>> Aktif
                            </label>
                        </div>
                        <small class="form-text text-muted">Centang untuk mengaktifkan akun pengguna</small>
                    </div>
                </div>

                <div class="form-group">
                    <h3>Informasi Lainnya</h3>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="created_at">Tanggal Registrasi</label>
                        <input type="text" id="created_at" class="form-control" value="<?php echo date('d-m-Y H:i', strtotime($user_data['created_at'])); ?>" readonly disabled>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="updated_at">Terakhir Diperbarui</label>
                        <input type="text" id="updated_at" class="form-control" value="<?php echo date('d-m-Y H:i', strtotime($user_data['updated_at'])); ?>" readonly disabled>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span> Simpan Perubahan
                    </button>
                    <a href="warga.php" class="btn btn-secondary">
                        <span class="btn-icon">↩</span> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>