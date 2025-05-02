<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Include database connection
require_once '../config/koneksi.php';

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($koneksi, $user_query);

if (!$user_result) {
    die("Error: " . mysqli_error($koneksi));
}

$user = mysqli_fetch_assoc($user_result);

// Handle profile update
$update_message = "";
$update_status = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $nomor_telepon = mysqli_real_escape_string($koneksi, $_POST['nomor_telepon']);
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    
    // Check if email already exists (if changed)
    if ($email != $user['email']) {
        $email_check = "SELECT email FROM users WHERE email = '$email' AND user_id != $user_id";
        $email_result = mysqli_query($koneksi, $email_check);
        
        if (mysqli_num_rows($email_result) > 0) {
            $update_status = "error";
            $update_message = "Email sudah digunakan oleh pengguna lain.";
        }
    }
    
    // If no errors, proceed with update
    if (empty($update_message)) {
        // Handle photo upload if file is selected
        $foto_path = $user['foto_profil']; // Default to current photo
        
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $file_name = $_FILES['foto_profil']['name'];
            $file_size = $_FILES['foto_profil']['size'];
            $file_tmp = $_FILES['foto_profil']['tmp_name'];
            
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check file extension
            if (in_array($file_ext, $allowed_ext)) {
                // Check file size (max 2MB)
                if ($file_size <= 2097152) {
                    $new_file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                    $upload_path = '../uploads/profiles/' . $new_file_name;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists('../uploads/profiles/')) {
                        mkdir('../uploads/profiles/', 0777, true);
                    }
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $foto_path = $new_file_name;
                    } else {
                        $update_status = "error";
                        $update_message = "Gagal mengunggah foto profil.";
                    }
                } else {
                    $update_status = "error";
                    $update_message = "Ukuran file terlalu besar. Maksimal 2MB.";
                }
            } else {
                $update_status = "error";
                $update_message = "Format file tidak diizinkan. Gunakan JPG, JPEG, atau PNG.";
            }
        }
        
        // If no photo upload errors, update profile
        if (empty($update_message)) {
            $update_query = "UPDATE users SET 
                nama = '$nama',
                email = '$email',
                alamat = '$alamat',
                nomor_telepon = '$nomor_telepon',
                tanggal_lahir = '$tanggal_lahir',
                jenis_kelamin = '$jenis_kelamin',
                foto_profil = '$foto_path'
                WHERE user_id = $user_id";
                
            if (mysqli_query($koneksi, $update_query)) {
                $update_status = "success";
                $update_message = "Profil berhasil diperbarui.";
                
                // Update session data
                $_SESSION['nama'] = $nama;
                
                // Refresh user data
                $user_result = mysqli_query($koneksi, $user_query);
                $user = mysqli_fetch_assoc($user_result);
            } else {
                $update_status = "error";
                $update_message = "Gagal memperbarui profil: " . mysqli_error($koneksi);
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        // Check if new passwords match
        if ($new_password == $confirm_password) {
            // Check password strength (at least 8 characters)
            if (strlen($new_password) >= 8) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $password_update = "UPDATE users SET password = '$hashed_password' WHERE user_id = $user_id";
                
                if (mysqli_query($koneksi, $password_update)) {
                    $update_status = "success";
                    $update_message = "Kata sandi berhasil diubah.";
                } else {
                    $update_status = "error";
                    $update_message = "Gagal mengubah kata sandi: " . mysqli_error($koneksi);
                }
            } else {
                $update_status = "error";
                $update_message = "Kata sandi baru harus memiliki minimal 8 karakter.";
            }
        } else {
            $update_status = "error";
            $update_message = "Kata sandi baru tidak cocok dengan konfirmasi.";
        }
    } else {
        $update_status = "error";
        $update_message = "Kata sandi saat ini tidak valid.";
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container profile-container">
    <div class="row">
        <div class="col-lg-3">
            <div class="profile-sidebar">
                <div class="profile-image">
                    <?php if (!empty($user['foto_profil'])): ?>
                        <img src="../uploads/profiles/<?php echo htmlspecialchars($user['foto_profil']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <img src="../assets/img/default-profile.png" alt="Default Profile">
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($user['nama']); ?></h3>
                <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                <ul class="profile-menu">
                    <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profil Saya</a></li>
                    <li><a href="pengajuan-saya.php"><i class="fas fa-file-alt"></i> Pengajuan Saya</a></li>
                    <li><a href="notifikasi.php"><i class="fas fa-bell"></i> Notifikasi</a></li>
                    <li><a href="ubah-password.php"><i class="fas fa-lock"></i> Ubah Kata Sandi</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                </ul>
            </div>
        </div>
        
        <div class="col-lg-9">
            <?php if (!empty($update_message)): ?>
                <div class="alert alert-<?php echo $update_status == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $update_message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="card profile-card">
                <div class="card-header">
                    <h4><i class="fas fa-user"></i> Informasi Profil</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="profil.php" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nama">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nik">NIK (Tidak dapat diubah)</label>
                                    <input type="text" class="form-control" id="nik" value="<?php echo htmlspecialchars($user['nik']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nomor_telepon">Nomor Telepon</label>
                                    <input type="text" class="form-control" id="nomor_telepon" name="nomor_telepon" value="<?php echo htmlspecialchars($user['nomor_telepon']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tanggal_lahir">Tanggal Lahir</label>
                                    <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($user['tanggal_lahir']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jenis_kelamin">Jenis Kelamin</label>
                                    <select class="form-control" id="jenis_kelamin" name="jenis_kelamin">
                                        <option value="Laki-laki" <?php echo $user['jenis_kelamin'] == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                        <option value="Perempuan" <?php echo $user['jenis_kelamin'] == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="alamat">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($user['alamat']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="foto_profil">Foto Profil</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="foto_profil" name="foto_profil">
                                <label class="custom-file-label" for="foto_profil">Pilih file</label>
                                <small class="form-text text-muted">Unggah foto profil baru (JPG, JPEG, PNG. Maks 2MB).</small>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </form>
                </div>
            </div>
            
            <div class="card profile-card mt-4">
                <div class="card-header">
                    <h4><i class="fas fa-lock"></i> Ubah Kata Sandi</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <div class="form-group">
                            <label for="current_password">Kata Sandi Saat Ini</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Kata Sandi Baru</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">Minimal 8 karakter.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-success"><i class="fas fa-key"></i> Ubah Kata Sandi</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile Page Styles */
.profile-container {
    margin-top: 40px;
    margin-bottom: 40px;
}

.profile-sidebar {
    background-color: #fff;
    border-radius: 10px;
    padding: 30px 20px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    text-align: center;
    margin-bottom: 30px;
}

.profile-image {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 20px;
    border: 5px solid #f0f0f0;
}

.profile-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-sidebar h3 {
    margin-bottom: 5px;
    color: #2c3e50;
}

.profile-menu {
    list-style: none;
    padding: 0;
    margin-top: 30px;
    text-align: left;
}

.profile-menu li {
    margin-bottom: 5px;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.profile-menu li a {
    display: block;
    padding: 10px 15px;
    color: #7f8c8d;
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.profile-menu li:hover {
    background-color: #f5f5f5;
}

.profile-menu li:hover a {
    color: #28a745;
}

.profile-menu li.active {
    background-color: #28a745;
}

.profile-menu li.active a {
    color: white;
}

.profile-menu li i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.profile-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.profile-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
}

.profile-card .card-header h4 {
    margin: 0;
    color: #28a745;
    font-size: 1.2rem;
}

.profile-card .card-header h4 i {
    margin-right: 10px;
}

.profile-card .card-body {
    padding: 25px;
}

.form-group label {
    font-weight: 500;
    color: #28a745;
}

.custom-file-label::after {
    background-color: #28a745;
    color: white;
}

.btn-primary {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-primary:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

@media (max-width: 991px) {
    .profile-sidebar {
        margin-bottom: 30px;
    }
}
</style>

<?php
// Include footer
include '../includes/footer.php';
?>