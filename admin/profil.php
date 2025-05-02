<?php
// Include necessary files
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login terlebih dahulu.';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_nama'];

// Prepare variables for page
$page_title = "Profil Pengguna";
$current_page = "profil";
$success_message = '';
$error_message = '';

// Process form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $nomor_telepon = mysqli_real_escape_string($koneksi, $_POST['nomor_telepon']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    
    // Update query
    $update_query = "UPDATE users SET 
                     nama = '$nama', 
                     email = '$email', 
                     alamat = '$alamat', 
                     nomor_telepon = '$nomor_telepon', 
                     tanggal_lahir = '$tanggal_lahir', 
                     jenis_kelamin = '$jenis_kelamin'
                     WHERE user_id = $user_id";
    
    // Handle profile picture upload if present
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] != 4) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['foto_profil']['name'];
        $file_size = $_FILES['foto_profil']['size'];
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_type = $_FILES['foto_profil']['type'];
        
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed_extensions)) {
            if ($file_size <= 2097152) {
                // Generate unique file name
                $new_file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = 'uploads/profiles/' . $new_file_name;
                
                // Create directory if not exists
                if (!file_exists('uploads/profiles/')) {
                    mkdir('uploads/profiles/', 0777, true);
                }
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Add profile picture to update query
                    $update_query = "UPDATE users SET 
                                    nama = '$nama', 
                                    email = '$email', 
                                    alamat = '$alamat', 
                                    nomor_telepon = '$nomor_telepon', 
                                    tanggal_lahir = '$tanggal_lahir', 
                                    jenis_kelamin = '$jenis_kelamin',
                                    foto_profil = '$new_file_name'
                                    WHERE user_id = $user_id";
                } else {
                    $error_message = "Gagal mengunggah foto profil";
                }
            } else {
                $error_message = "Ukuran file terlalu besar. Maksimal 2MB";
            }
        } else {
            $error_message = "Format file tidak diizinkan. Format yang diizinkan: JPG, JPEG, PNG, GIF";
        }
    }
    
    // Execute update
    if (empty($error_message)) {
        if (mysqli_query($koneksi, $update_query)) {
            // Update session variables
            $_SESSION['user_nama'] = $nama;
            
            // Record activity
            $aktivitas = "Mengubah data profil";
            $deskripsi = "Pengguna memperbarui data profil";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            mysqli_query($koneksi, "INSERT INTO log_aktivitas (user_id, aktivitas, deskripsi, ip_address, user_agent) 
                        VALUES ($user_id, '$aktivitas', '$deskripsi', '$ip_address', '$user_agent')");
            
            $success_message = "Profil berhasil diperbarui!";
        } else {
            $error_message = "Gagal memperbarui profil: " . mysqli_error($koneksi);
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current password from database
    $password_query = "SELECT password FROM users WHERE user_id = $user_id";
    $password_result = mysqli_query($koneksi, $password_query);
    $user_data = mysqli_fetch_assoc($password_result);
    $stored_password = $user_data['password'];
    
    // Verify current password
    if (password_verify($current_password, $stored_password)) {
        // Check if new password and confirm password match
        if ($new_password === $confirm_password) {
            // Password requirements check
            if (strlen($new_password) >= 8) {
                // Hash new password and update
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_password_query = "UPDATE users SET password = '$hashed_password' WHERE user_id = $user_id";
                
                if (mysqli_query($koneksi, $update_password_query)) {
                    // Record activity
                    $aktivitas = "Mengubah password";
                    $deskripsi = "Pengguna mengubah password";
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    mysqli_query($koneksi, "INSERT INTO log_aktivitas (user_id, aktivitas, deskripsi, ip_address, user_agent) 
                                VALUES ($user_id, '$aktivitas', '$deskripsi', '$ip_address', '$user_agent')");
                    
                    $success_message = "Password berhasil diubah!";
                } else {
                    $error_message = "Gagal mengubah password: " . mysqli_error($koneksi);
                }
            } else {
                $error_message = "Password baru minimal 8 karakter";
            }
        } else {
            $error_message = "Password baru dan konfirmasi tidak cocok";
        }
    } else {
        $error_message = "Password saat ini tidak valid";
    }
}

// Get user data
$user_query = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($koneksi, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Profil Pengguna</h2>
        <p>Kelola informasi akun dan password Anda</p>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <span class="alert-icon">✓</span>
        <span class="alert-message"><?php echo $success_message; ?></span>
        <span class="alert-close">×</span>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <span class="alert-icon">⚠</span>
        <span class="alert-message"><?php echo $error_message; ?></span>
        <span class="alert-close">×</span>
    </div>
    <?php endif; ?>

    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-image">
                <?php if (!empty($user['foto_profil'])): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($user['foto_profil']); ?>" alt="Foto Profil">
                <?php else: ?>
                <div class="profile-placeholder">
                    <span>👤</span>
                </div>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($user['nama']); ?></h3>
            <p class="role-badge"><?php echo ucfirst($user['role']); ?></p>
            <ul class="profile-meta">
                <li><strong>NIK:</strong> <?php echo !empty($user['nik']) ? htmlspecialchars($user['nik']) : '-'; ?></li>
                <li><strong>Email:</strong> <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : '-'; ?></li>
                <li><strong>Terdaftar:</strong> <?php echo date('d-m-Y', strtotime($user['created_at'])); ?></li>
                <li><strong>Status:</strong> <span class="status <?php echo $user['active'] ? 'status-completed' : 'status-rejected'; ?>"><?php echo $user['active'] ? 'Aktif' : 'Tidak Aktif'; ?></span></li>
            </ul>
        </div>

        <div class="profile-content">
            <div class="tabs">
                <button class="tab-btn active" data-tab="profile-info">Informasi Profil</button>
                <button class="tab-btn" data-tab="password">Ubah Password</button>
            </div>

            <div class="tab-content active" id="profile-info">
                <form action="" method="POST" enctype="multipart/form-data" class="admin-form">
                    <div class="form-group">
                        <label for="nama">Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nik">NIK</label>
                        <input type="text" id="nik" value="<?php echo htmlspecialchars($user['nik']); ?>" disabled>
                        <small>NIK tidak dapat diubah. Hubungi admin jika ada kesalahan.</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tanggal_lahir">Tanggal Lahir</label>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($user['tanggal_lahir']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="jenis_kelamin">Jenis Kelamin</label>
                            <select id="jenis_kelamin" name="jenis_kelamin">
                                <option value="Laki-laki" <?php echo $user['jenis_kelamin'] == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?php echo $user['jenis_kelamin'] == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($user['alamat']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="nomor_telepon">Nomor Telepon</label>
                        <input type="text" id="nomor_telepon" name="nomor_telepon" value="<?php echo htmlspecialchars($user['nomor_telepon']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="foto_profil">Foto Profil</label>
                        <input type="file" id="foto_profil" name="foto_profil" accept="image/*">
                        <small>Format: JPG, JPEG, PNG, GIF. Maksimal 2MB</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>

            <div class="tab-content" id="password">
                <form action="" method="POST" class="admin-form">
                    <div class="form-group">
                        <label for="current_password">Password Saat Ini</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Password Baru</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small>Minimal 8 karakter</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">Ubah Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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


/* Profile container styles */
.profile-container {
    display: flex;
    gap: 30px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    
}

/* Profile sidebar styles */
.profile-sidebar {
    flex: 0 0 250px;
    background-color: #f8f9fa;
    padding: 25px;
    text-align: center;
    border-right: 1px solid #eaeaea;
}

.profile-image {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 15px;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
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
    background-color: #e9ecef;
    color: #6c757d;
    font-size: 48px;
}

.profile-sidebar h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    color: #333;
}

.role-badge {
    display: inline-block;
    padding: 4px 12px;
    background-color: #e7f3ff;
    color: #0d6efd;
    border-radius: 20px;
    font-size: 13px;
    margin-bottom: 15px;
}

.profile-meta {
    list-style-type: none;
    padding: 0;
    margin: 0;
    text-align: left;
}

.profile-meta li {
    padding: 8px 0;
    border-bottom: 1px solid #eaeaea;
    color: #555;
    font-size: 14px;
}

.profile-meta li:last-child {
    border-bottom: none;
}

.profile-meta li strong {
    display: inline-block;
    width: 90px;
    color: #333;
}

.status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-completed {
    background-color: #dff7e9;
    color: #28a745;
}

.status-rejected {
    background-color: #ffeaed;
    color: #dc3545;
}

/* Profile content styles */
.profile-content {
    flex: 1;
    padding: 25px;
}

/* Tabs styles */
.tabs {
    display: flex;
    border-bottom: 1px solid #eaeaea;
    margin-bottom: 25px;
}

.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    color: #666;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    color: #0d6efd;
}

.tab-btn.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Form styles */
.admin-form {
    max-width: 100%;
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
    font-size: 14px;
}

input[type="text"],
input[type="email"],
input[type="date"],
input[type="password"],
select,
textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    color: #333;
    transition: border-color 0.3s ease;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="date"]:focus,
input[type="password"]:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: #a0c7ff;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
}

input[disabled] {
    background-color: #f2f2f2;
    cursor: not-allowed;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

input[type="file"] {
    padding: 8px 0;
}

small {
    display: block;
    margin-top: 5px;
    color: #777;
    font-size: 12px;
}

.form-actions {
    margin-top: 30px;
}

/* Button styles */
.btn {
    display: inline-block;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    font-size: 14px;
    text-align: center;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #0d6efd;
    color: white;
}

.btn-primary:hover {
    background-color: #0b5ed7;
}

/* Alert styles */
.alert {
    padding: 14px 20px;
    border-radius: 4px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    position: relative;
}

.alert-success {
    background-color: #dff7e9;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background-color: #ffeaed;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-icon {
    margin-right: 10px;
    font-size: 16px;
}

.alert-message {
    flex: 1;
}

.alert-close {
    cursor: pointer;
    font-size: 18px;
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.alert-close:hover {
    opacity: 1;
}

/* Responsive styles */
@media (max-width: 768px) {
    .profile-container {
        flex-direction: column;
    }
    
    .profile-sidebar {
        flex: none;
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #eaeaea;
    }
    
    .form-row {
        flex-direction: column;
        gap: 20px;
    }
    
    .form-row .form-group {
        margin-bottom: 0;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current button
            button.classList.add('active');
            
            // Show the corresponding content
            const tabId = button.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Close alert message
    const alertCloseButtons = document.querySelectorAll('.alert-close');
    alertCloseButtons.forEach(button => {
        button.addEventListener('click', () => {
            const alert = button.closest('.alert');
            alert.style.display = 'none';
        });
    });
});
</script>

<?php
// Include footer
include '../includes/admin-footer.php';
?>