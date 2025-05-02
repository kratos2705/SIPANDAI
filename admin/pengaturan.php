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

// Get current user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_nama'];

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Log the activity
    $aktivitas = 'Mengubah pengaturan sistem';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) 
                  VALUES ('$user_id', '$aktivitas', '$ip_address', '$user_agent')";
    mysqli_query($koneksi, $log_query);
    
    // Process general settings
    if (isset($_POST['general_settings'])) {
        $desa_nama = mysqli_real_escape_string($koneksi, $_POST['desa_nama']);
        $desa_alamat = mysqli_real_escape_string($koneksi, $_POST['desa_alamat']);
        $desa_kecamatan = mysqli_real_escape_string($koneksi, $_POST['desa_kecamatan']);
        $desa_kabupaten = mysqli_real_escape_string($koneksi, $_POST['desa_kabupaten']);
        $desa_provinsi = mysqli_real_escape_string($koneksi, $_POST['desa_provinsi']);
        $desa_kodepos = mysqli_real_escape_string($koneksi, $_POST['desa_kodepos']);
        $desa_telepon = mysqli_real_escape_string($koneksi, $_POST['desa_telepon']);
        $desa_email = mysqli_real_escape_string($koneksi, $_POST['desa_email']);
        $desa_website = mysqli_real_escape_string($koneksi, $_POST['desa_website']);
        
        // Update each setting in settings table
        $settings = [
            'desa_nama' => $desa_nama,
            'desa_alamat' => $desa_alamat,
            'desa_kecamatan' => $desa_kecamatan,
            'desa_kabupaten' => $desa_kabupaten,
            'desa_provinsi' => $desa_provinsi,
            'desa_kodepos' => $desa_kodepos,
            'desa_telepon' => $desa_telepon,
            'desa_email' => $desa_email,
            'desa_website' => $desa_website
        ];
        
        foreach ($settings as $key => $value) {
            $check_query = "SELECT * FROM pengaturan WHERE nama_pengaturan = '$key'";
            $result = mysqli_query($koneksi, $check_query);
            
            if (mysqli_num_rows($result) > 0) {
                $update_query = "UPDATE pengaturan SET nilai_pengaturan = '$value', updated_at = NOW() WHERE nama_pengaturan = '$key'";
                mysqli_query($koneksi, $update_query);
            } else {
                $insert_query = "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('$key', '$value')";
                mysqli_query($koneksi, $insert_query);
            }
        }
        
        $success_message = 'Pengaturan umum berhasil disimpan';
    }
    
    // Process dokumen settings
    if (isset($_POST['dokumen_settings'])) {
        $dokumen_durasi_default = intval($_POST['dokumen_durasi_default']);
        $dokumen_notifikasi = isset($_POST['dokumen_notifikasi']) ? 1 : 0;
        $dokumen_expired_days = intval($_POST['dokumen_expired_days']);
        
        $settings = [
            'dokumen_durasi_default' => $dokumen_durasi_default,
            'dokumen_notifikasi' => $dokumen_notifikasi,
            'dokumen_expired_days' => $dokumen_expired_days
        ];
        
        foreach ($settings as $key => $value) {
            $check_query = "SELECT * FROM pengaturan WHERE nama_pengaturan = '$key'";
            $result = mysqli_query($koneksi, $check_query);
            
            if (mysqli_num_rows($result) > 0) {
                $update_query = "UPDATE pengaturan SET nilai_pengaturan = '$value', updated_at = NOW() WHERE nama_pengaturan = '$key'";
                mysqli_query($koneksi, $update_query);
            } else {
                $insert_query = "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('$key', '$value')";
                mysqli_query($koneksi, $insert_query);
            }
        }
        
        $success_message = 'Pengaturan dokumen berhasil disimpan';
    }
    
    // Process retribusi settings
    if (isset($_POST['retribusi_settings'])) {
        $retribusi_denda_persen = floatval($_POST['retribusi_denda_persen']);
        $retribusi_grace_period = intval($_POST['retribusi_grace_period']);
        $retribusi_auto_notif = isset($_POST['retribusi_auto_notif']) ? 1 : 0;
        
        $settings = [
            'retribusi_denda_persen' => $retribusi_denda_persen,
            'retribusi_grace_period' => $retribusi_grace_period,
            'retribusi_auto_notif' => $retribusi_auto_notif
        ];
        
        foreach ($settings as $key => $value) {
            $check_query = "SELECT * FROM pengaturan WHERE nama_pengaturan = '$key'";
            $result = mysqli_query($koneksi, $check_query);
            
            if (mysqli_num_rows($result) > 0) {
                $update_query = "UPDATE pengaturan SET nilai_pengaturan = '$value', updated_at = NOW() WHERE nama_pengaturan = '$key'";
                mysqli_query($koneksi, $update_query);
            } else {
                $insert_query = "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('$key', '$value')";
                mysqli_query($koneksi, $insert_query);
            }
        }
        
        $success_message = 'Pengaturan retribusi berhasil disimpan';
    }
    
    // Process system settings
    if (isset($_POST['system_settings'])) {
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $debug_mode = isset($_POST['debug_mode']) ? 1 : 0;
        $backup_auto = isset($_POST['backup_auto']) ? 1 : 0;
        $backup_interval = intval($_POST['backup_interval']);
        
        $settings = [
            'maintenance_mode' => $maintenance_mode,
            'debug_mode' => $debug_mode,
            'backup_auto' => $backup_auto,
            'backup_interval' => $backup_interval
        ];
        
        foreach ($settings as $key => $value) {
            $check_query = "SELECT * FROM pengaturan WHERE nama_pengaturan = '$key'";
            $result = mysqli_query($koneksi, $check_query);
            
            if (mysqli_num_rows($result) > 0) {
                $update_query = "UPDATE pengaturan SET nilai_pengaturan = '$value', updated_at = NOW() WHERE nama_pengaturan = '$key'";
                mysqli_query($koneksi, $update_query);
            } else {
                $insert_query = "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('$key', '$value')";
                mysqli_query($koneksi, $insert_query);
            }
        }
        
        $success_message = 'Pengaturan sistem berhasil disimpan';
    }
    
    // Process logo upload
    if (isset($_FILES['desa_logo']) && $_FILES['desa_logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['desa_logo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'logo_desa_' . time() . '.' . $ext;
            $upload_path = '../assets/img/' . $new_filename;
            
            if (move_uploaded_file($_FILES['desa_logo']['tmp_name'], $upload_path)) {
                $logo_path = 'assets/img/' . $new_filename;
                
                $check_query = "SELECT * FROM pengaturan WHERE nama_pengaturan = 'desa_logo'";
                $result = mysqli_query($koneksi, $check_query);
                
                if (mysqli_num_rows($result) > 0) {
                    $update_query = "UPDATE pengaturan SET nilai_pengaturan = '$logo_path', updated_at = NOW() WHERE nama_pengaturan = 'desa_logo'";
                    mysqli_query($koneksi, $update_query);
                } else {
                    $insert_query = "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('desa_logo', '$logo_path')";
                    mysqli_query($koneksi, $insert_query);
                }
                
                $success_message = 'Logo desa berhasil diperbarui';
            } else {
                $error_message = 'Gagal mengunggah logo. Silakan coba lagi.';
            }
        } else {
            $error_message = 'Format file tidak didukung. Gunakan JPG, JPEG atau PNG.';
        }
    }
    
    // Perform database backup if requested
    if (isset($_POST['backup_now'])) {
        // Generate backup filename with timestamp
        $backup_file = '../backups/sipandai_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Create backup directory if it doesn't exist
        if (!file_exists('../backups')) {
            mkdir('../backups', 0755, true);
        }
        
        // Database configuration
        $dbhost = $db_host;
        $dbuser = $db_user;
        $dbpass = $db_pass;
        $dbname = $db_name;
        
        // Command for mysqldump
        $command = "mysqldump --opt -h$dbhost -u$dbuser -p$dbpass $dbname > $backup_file";
        
        // Execute the command
        $output = array();
        $result = exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $success_message = 'Backup database berhasil dibuat: ' . basename($backup_file);
            
            // Log the backup activity
            $aktivitas = 'Membuat backup database: ' . basename($backup_file);
            $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) 
                          VALUES ('$user_id', '$aktivitas', '$ip_address', '$user_agent')";
            mysqli_query($koneksi, $log_query);
        } else {
            $error_message = 'Gagal membuat backup database. Periksa konfigurasi server.';
        }
    }
}

// Get current settings from database


// Get default values or current values
$desa_nama = $settings['desa_nama'] ?? '';
$desa_alamat = $settings['desa_alamat'] ?? '';
$desa_kecamatan = $settings['desa_kecamatan'] ?? '';
$desa_kabupaten = $settings['desa_kabupaten'] ?? '';
$desa_provinsi = $settings['desa_provinsi'] ?? '';
$desa_kodepos = $settings['desa_kodepos'] ?? '';
$desa_telepon = $settings['desa_telepon'] ?? '';
$desa_email = $settings['desa_email'] ?? '';
$desa_website = $settings['desa_website'] ?? '';
$desa_logo = $settings['desa_logo'] ?? 'assets/img/logo5.png';

$dokumen_durasi_default = $settings['dokumen_durasi_default'] ?? 3;
$dokumen_notifikasi = isset($settings['dokumen_notifikasi']) ? $settings['dokumen_notifikasi'] : 1;
$dokumen_expired_days = $settings['dokumen_expired_days'] ?? 7;

$retribusi_denda_persen = $settings['retribusi_denda_persen'] ?? 2.5;
$retribusi_grace_period = $settings['retribusi_grace_period'] ?? 3;
$retribusi_auto_notif = isset($settings['retribusi_auto_notif']) ? $settings['retribusi_auto_notif'] : 1;

$maintenance_mode = isset($settings['maintenance_mode']) ? $settings['maintenance_mode'] : 0;
$debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : 0;
$backup_auto = isset($settings['backup_auto']) ? $settings['backup_auto'] : 0;
$backup_interval = $settings['backup_interval'] ?? 7;

// Prepare variables for page
$page_title = "Pengaturan Sistem";
$current_page = "pengaturan";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Pengaturan Sistem</h2>
        <p>Konfigurasi pengaturan aplikasi SIPANDAI</p>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <span class="closebtn">&times;</span>
        <strong>Berhasil!</strong> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <span class="closebtn">&times;</span>
        <strong>Error!</strong> <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="tabs-container">
        <div class="tabs-header">
            <div class="tab-item active" data-tab="general">Umum</div>
            <div class="tab-item" data-tab="dokumen">Dokumen</div>
            <div class="tab-item" data-tab="retribusi">Retribusi</div>
            <div class="tab-item" data-tab="system">Sistem</div>
            <div class="tab-item" data-tab="backup">Backup & Restore</div>
        </div>

        <div class="tabs-content">
            <!-- General Settings Tab -->
            <div class="tab-pane active" id="general">
                <div class="card">
                    <div class="card-header">
                        <h3>Pengaturan Umum</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="desa_nama">Nama Desa</label>
                                <input type="text" id="desa_nama" name="desa_nama" class="form-control" value="<?php echo htmlspecialchars($desa_nama); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="desa_alamat">Alamat Desa</label>
                                <textarea id="desa_alamat" name="desa_alamat" class="form-control" rows="3"><?php echo htmlspecialchars($desa_alamat); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="desa_kecamatan">Kecamatan</label>
                                    <input type="text" id="desa_kecamatan" name="desa_kecamatan" class="form-control" value="<?php echo htmlspecialchars($desa_kecamatan); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="desa_kabupaten">Kabupaten</label>
                                    <input type="text" id="desa_kabupaten" name="desa_kabupaten" class="form-control" value="<?php echo htmlspecialchars($desa_kabupaten); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="desa_provinsi">Provinsi</label>
                                    <input type="text" id="desa_provinsi" name="desa_provinsi" class="form-control" value="<?php echo htmlspecialchars($desa_provinsi); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="desa_kodepos">Kode Pos</label>
                                    <input type="text" id="desa_kodepos" name="desa_kodepos" class="form-control" value="<?php echo htmlspecialchars($desa_kodepos); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="desa_telepon">Nomor Telepon</label>
                                    <input type="text" id="desa_telepon" name="desa_telepon" class="form-control" value="<?php echo htmlspecialchars($desa_telepon); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="desa_email">Email</label>
                                    <input type="email" id="desa_email" name="desa_email" class="form-control" value="<?php echo htmlspecialchars($desa_email); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="desa_website">Website</label>
                                <input type="url" id="desa_website" name="desa_website" class="form-control" value="<?php echo htmlspecialchars($desa_website); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="desa_logo">Logo Desa</label>
                                <div class="logo-preview">
                                    <img src="<?php echo '../' . $desa_logo; ?>" alt="Logo Desa" id="logoPreview" class="preview-image">
                                </div>
                                <input type="file" id="desa_logo" name="desa_logo" class="form-control-file" accept="image/jpeg,image/png">
                                <small class="form-text text-muted">Format: JPG, JPEG, atau PNG. Ukuran maksimal: 2MB.</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="general_settings" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Dokumen Settings Tab -->
            <div class="tab-pane" id="dokumen">
                <div class="card">
                    <div class="card-header">
                        <h3>Pengaturan Dokumen</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="dokumen_durasi_default">Durasi Proses Default (hari)</label>
                                <input type="number" id="dokumen_durasi_default" name="dokumen_durasi_default" class="form-control" value="<?php echo intval($dokumen_durasi_default); ?>" min="1" required>
                                <small class="form-text text-muted">Durasi default pemrosesan dokumen jika tidak ditentukan.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="dokumen_expired_days">Masa Berlaku Dokumen Hasil (hari)</label>
                                <input type="number" id="dokumen_expired_days" name="dokumen_expired_days" class="form-control" value="<?php echo intval($dokumen_expired_days); ?>" min="1">
                                <small class="form-text text-muted">Jumlah hari sebelum dokumen hasil dianggap kedaluwarsa. Kosongkan jika tidak ada batas.</small>
                            </div>
                            
                            <div class="form-group">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="dokumen_notifikasi" name="dokumen_notifikasi" <?php echo $dokumen_notifikasi ? 'checked' : ''; ?>>
                                    <label for="dokumen_notifikasi">Kirim Notifikasi Status Dokumen</label>
                                </div>
                                <small class="form-text text-muted">Kirim notifikasi kepada pemohon saat status dokumen berubah.</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="dokumen_settings" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Retribusi Settings Tab -->
            <div class="tab-pane" id="retribusi">
                <div class="card">
                    <div class="card-header">
                        <h3>Pengaturan Retribusi</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="retribusi_denda_persen">Persentase Denda Keterlambatan (%)</label>
                                <input type="number" id="retribusi_denda_persen" name="retribusi_denda_persen" class="form-control" value="<?php echo floatval($retribusi_denda_persen); ?>" min="0" step="0.01" required>
                                <small class="form-text text-muted">Persentase denda yang dikenakan untuk keterlambatan pembayaran retribusi.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="retribusi_grace_period">Masa Tenggang (hari)</label>
                                <input type="number" id="retribusi_grace_period" name="retribusi_grace_period" class="form-control" value="<?php echo intval($retribusi_grace_period); ?>" min="0" required>
                                <small class="form-text text-muted">Jumlah hari tenggang sebelum denda keterlambatan dikenakan.</small>
                            </div>
                            
                            <div class="form-group">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="retribusi_auto_notif" name="retribusi_auto_notif" <?php echo $retribusi_auto_notif ? 'checked' : ''; ?>>
                                    <label for="retribusi_auto_notif">Kirim Notifikasi Otomatis</label>
                                </div>
                                <small class="form-text text-muted">Kirim notifikasi otomatis untuk mengingatkan jatuh tempo pembayaran retribusi.</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="retribusi_settings" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- System Settings Tab -->
            <div class="tab-pane" id="system">
                <div class="card">
                    <div class="card-header">
                        <h3>Pengaturan Sistem</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                                    <label for="maintenance_mode">Mode Pemeliharaan</label>
                                </div>
                                <small class="form-text text-muted">Aktifkan mode pemeliharaan untuk menonaktifkan akses publik ke sistem.</small>
                            </div>
                            
                            <div class="form-group">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="debug_mode" name="debug_mode" <?php echo $debug_mode ? 'checked' : ''; ?>>
                                    <label for="debug_mode">Mode Debug</label>
                                </div>
                                <small class="form-text text-muted">Aktifkan mode debug untuk menampilkan informasi error yang lebih detail.</small>
                            </div>
                            
                            <div class="form-group">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="backup_auto" name="backup_auto" <?php echo $backup_auto ? 'checked' : ''; ?>>
                                    <label for="backup_auto">Backup Otomatis</label>
                                </div>
                                <small class="form-text text-muted">Aktifkan backup database otomatis secara berkala.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="backup_interval">Interval Backup (hari)</label>
                                <input type="number" id="backup_interval" name="backup_interval" class="form-control" value="<?php echo intval($backup_interval); ?>" min="1" required>
                                <small class="form-text text-muted">Jumlah hari antara backup otomatis.</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="system_settings" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Backup Tab -->
            <div class="tab-pane" id="backup">
                <div class="card">
                    <div class="card-header">
                        <h3>Backup & Restore Database</h3>
                    </div>
                    <div class="card-body">
                        <div class="backup-info">
                            <div class="info-box">
                                <h4>Informasi Database</h4>
                                <p><strong>Ukuran Database:</strong> 
                                    <?php
                                    // Get database size
                                    $size_query = "SELECT 
                                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                                        FROM information_schema.TABLES 
                                        WHERE table_schema = '$db_name'";
                                    $size_result = mysqli_query($koneksi, $size_query);
                                    $db_size = mysqli_fetch_assoc($size_result)['size'];
                                    echo $db_size . ' MB';
                                    ?>
                                </p>
                                <p><strong>Jumlah Tabel:</strong> 
                                    <?php
                                    // Get table count
                                    $tables_query = "SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE table_schema = '$db_name'";
                                    $tables_result = mysqli_query($koneksi, $tables_query);
                                    $table_count = mysqli_fetch_assoc($tables_result)['count'];
                                    echo $table_count;
                                    ?>
                                </p>
                                <p><strong>Backup Terakhir:</strong> 
                                    <?php
                                    // Get last backup from log
                                    $backup_query = "SELECT created_at FROM log_aktivitas WHERE aktivitas LIKE 'Membuat backup database%' ORDER BY created_at DESC LIMIT 1";
                                    $backup_result = mysqli_query($koneksi, $backup_query);
                                    if (mysqli_num_rows($backup_result) > 0) {
                                        $last_backup = mysqli_fetch_assoc($backup_result)['created_at'];
                                        echo date('d-m-Y H:i:s', strtotime($last_backup));
                                    } else {
                                        echo 'Belum ada backup';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="backup-actions">
                            <form method="POST" action="">
                                <button type="submit" name="backup_now" class="btn btn-primary">Backup Sekarang</button>
                            </form>
                        </div>
                        
                        <div class="backup-list">
                            <h4>Backup Tersedia</h4>
                            <?php
                            $backup_dir = '../backups/';
                            if (file_exists($backup_dir) && is_dir($backup_dir)) {
                                $backup_files = glob($backup_dir . 'sipandai_backup_*.sql');
                                
                                if (!empty($backup_files)) {
                                    echo '<table class="table">';
                                    echo '<thead><tr><th>Nama File</th><th>Tanggal</th><th>Ukuran</th><th>Aksi</th></tr></thead>';
                                    echo '<tbody>';
                                    
                                    // Sort files by modification time, newest first
                                    usort($backup_files, function($a, $b) {
                                        return filemtime($b) - filemtime($a);
                                    });
                                    
                                    foreach ($backup_files as $file) {
                                        $file_name = basename($file);
                                        $file_date = date('d-m-Y H:i:s', filemtime($file));
                                        $file_size = round(filesize($file) / (1024 * 1024), 2) . ' MB';
                                        
                                        echo '<tr>';
                                        echo '<td>' . $file_name . '</td>';
                                        echo '<td>' . $file_date . '</td>';
                                        echo '<td>' . $file_size . '</td>';
                                        echo '<td>';
                                        echo '<a href="../backups/' . $file_name . '" class="btn btn-sm btn-success" download>Download</a> ';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    
                                    echo '</tbody></table>';
                                } else {
                                    echo '<p class="text-muted">Belum ada file backup tersedia.</p>';
                                }
                            } else {
                                echo '<p class="text-muted">Direktori backup tidak ditemukan.</p>';
                            }
                            ?>
                        </div>
                        
                        <div class="restore-section">
                            <h4>Restore Database</h4>
                            <form method="POST" action="restore_database.php" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="restore_file">Upload File Backup</label>
                                    <input type="file" id="restore_file" name="restore_file" class="form-control-file" accept=".sql">
                                    <small class="form-text text-muted">Pilih file SQL untuk memulihkan database. PERHATIAN: Ini akan menimpa data yang ada.</small>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="restore_db" class="btn btn-warning" onclick="return confirm('PERHATIAN: Tindakan ini akan menimpa semua data saat ini dengan data dari backup yang dipilih. Lanjutkan?');">Restore Database</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
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

/* Header Styles */
.admin-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.admin-header h2 {
    color: #2c3e50;
    margin: 0 0 5px 0;
    font-weight: 600;
}

.admin-header p {
    color: #7f8c8d;
    margin: 0;
}

/* Card Styles */
.card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 18px;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -10px;
    margin-left: -10px;
}

.form-row > .form-group {
    padding-left: 10px;
    padding-right: 10px;
}

.col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #34495e;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    font-size: 14px;
    border: 1px solid #dce4ec;
    border-radius: 4px;
    background-color: #fff;
    transition: border-color 0.15s ease-in-out;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: #3498db;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}

.form-control-file {
    display: block;
    width: 100%;
    padding: 8px 0;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-text {
    display: block;
    margin-top: 5px;
    color: #7f8c8d;
    font-size: 12px;
}

/* Button Styles */
.btn {
    display: inline-block;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 8px 16px;
    font-size: 14px;
    line-height: 1.5;
    border-radius: 4px;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    cursor: pointer;
}

.btn-primary {
    color: #fff;
    background-color: #3498db;
    border-color: #3498db;
}

.btn-primary:hover {
    background-color: #2980b9;
    border-color: #2980b9;
}

.btn-success {
    color: #fff;
    background-color: #2ecc71;
    border-color: #2ecc71;
}

.btn-success:hover {
    background-color: #27ae60;
    border-color: #27ae60;
}

.btn-warning {
    color: #fff;
    background-color: #f39c12;
    border-color: #f39c12;
}

.btn-warning:hover {
    background-color: #e67e22;
    border-color: #e67e22;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

/* Alert Styles */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
    position: relative;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.closebtn {
    margin-left: 15px;
    color: inherit;
    font-weight: bold;
    float: right;
    font-size: 20px;
    line-height: 16px;
    cursor: pointer;
    transition: 0.3s;
}

.closebtn:hover {
    color: #000;
}

/* Tabs Styles */
.tabs-container {
    width: 100%;
    margin-bottom: 20px;
}

.tabs-header {
    display: flex;
    overflow-x: auto;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 20px;
}

.tab-item {
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s;
    white-space: nowrap;
    color: #7f8c8d;
    font-weight: 500;
}

.tab-item:hover {
    color: #3498db;
}

.tab-item.active {
    color: #3498db;
    border-bottom-color: #3498db;
}

.tabs-content {
    position: relative;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Toggle Switch Styles */
.toggle-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    margin-bottom: 10px;
}

.toggle-switch input[type="checkbox"] {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch label {
    position: relative;
    display: inline-block;
    padding-left: 50px;
    cursor: pointer;
    margin-bottom: 0;
}

.toggle-switch label:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 40px;
    height: 20px;
    background-color: #ccc;
    border-radius: 34px;
    transition: .4s;
}

.toggle-switch label:after {
    content: '';
    position: absolute;
    left: 3px;
    top: 3px;
    width: 14px;
    height: 14px;
    background-color: white;
    border-radius: 50%;
    transition: .4s;
}

.toggle-switch input:checked + label:before {
    background-color: #3498db;
}

.toggle-switch input:checked + label:after {
    transform: translateX(20px);
}

/* Logo Preview Styles */
.logo-preview {
    margin-bottom: 10px;
    text-align: center;
    border: 1px solid #dce4ec;
    padding: 10px;
    border-radius: 4px;
    background-color: #f8f9fa;
}

.preview-image {
    max-width: 200px;
    height: auto;
}

/* Table Styles */
.table {
    width: 100%;
    margin-bottom: 20px;
    background-color: transparent;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 10px;
    vertical-align: middle;
    border-top: 1px solid #e9ecef;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #e9ecef;
    background-color: #f8f9fa;
    color: #2c3e50;
    font-weight: 600;
}

.table tbody + tbody {
    border-top: 2px solid #e9ecef;
}

/* Backup Info Styles */
.backup-info {
    margin-bottom: 20px;
}

.info-box {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border-left: 4px solid #3498db;
}

.info-box h4 {
    margin-top: 0;
    color: #2c3e50;
}

.backup-actions {
    margin-bottom: 20px;
}

.backup-list,
.restore-section {
    margin-top: 30px;
}

.backup-list h4,
.restore-section h4 {
    margin-bottom: 15px;
    color: #2c3e50;
    font-weight: 600;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .admin-content {
        margin-left: 0;
    }
    
    .form-row > .form-group {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .tabs-header {
        flex-wrap: wrap;
    }
    
    .tab-item {
        flex: 0 0 calc(50% - 2px);
        text-align: center;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 15px;
    }
    
    .tab-item {
        flex: 0 0 100%;
    }
}
</style>
<script>
    // Tab functionality
    const tabItems = document.querySelectorAll('.tab-item');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabItems.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabItems.forEach(item => item.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to current tab
            this.classList.add('active');
            
            // Show corresponding tab pane
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Close alert message
    const closeButtons = document.querySelectorAll('.closebtn');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.opacity = '0';
            setTimeout(() => {
                this.parentElement.style.display = 'none';
            }, 600);
        });
    });
    
    // Logo preview functionality
    document.getElementById('desa_logo').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logoPreview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include '../includes/admin-footer.php'; ?>