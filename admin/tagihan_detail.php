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

// Initialize variables
$errors = [];
$success_message = '';

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get tagihan ID from URL
$tagihan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tagihan_id <= 0) {
    $_SESSION['error_message'] = 'ID tagihan tidak valid.';
    redirect('retribusi.php');
}

// Get tagihan details
$query = "SELECT tr.*, u.nama AS nama_warga, u.nik, u.nomor_telepon, u.email, u.alamat,
          jr.nama_retribusi, jr.periode, jr.deskripsi AS deskripsi_retribusi
          FROM tagihan_retribusi tr
          JOIN users u ON tr.user_id = u.user_id
          JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
          WHERE tr.tagihan_id = ?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, 'i', $tagihan_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error_message'] = 'Tagihan tidak ditemukan.';
    redirect('retribusi.php');
}

$tagihan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Get payment history
$query_payments = "SELECT pr.*, u.nama AS confirmed_by_name
                  FROM pembayaran_retribusi pr
                  LEFT JOIN users u ON pr.confirmed_by = u.user_id
                  WHERE pr.tagihan_id = ?
                  ORDER BY pr.tanggal_bayar DESC";
$stmt_payments = mysqli_prepare($koneksi, $query_payments);
mysqli_stmt_bind_param($stmt_payments, 'i', $tagihan_id);
mysqli_stmt_execute($stmt_payments);
$result_payments = mysqli_stmt_get_result($stmt_payments);
$pembayaran = [];
$total_dibayar = 0;

if (mysqli_num_rows($result_payments) > 0) {
    while ($row = mysqli_fetch_assoc($result_payments)) {
        $pembayaran[] = $row;
        if ($row['status'] == 'berhasil') {
            $total_dibayar += $row['jumlah_bayar'];
        }
    }
}
mysqli_stmt_close($stmt_payments);

// Calculate remaining amount
$sisa_tagihan = $tagihan['nominal'] + $tagihan['denda'] - $total_dibayar;

// Get notification history
$query_notif = "SELECT n.*, u.nama AS sent_by_name
               FROM notifikasi n
               LEFT JOIN log_aktivitas la ON la.deskripsi LIKE CONCAT('%', n.notifikasi_id, '%') AND la.aktivitas LIKE 'Mengirim pengingat%'
               LEFT JOIN users u ON la.user_id = u.user_id
               WHERE n.user_id = ? AND n.jenis = 'tagihan' AND n.link LIKE '%tagihan_detail.php?id=" . $tagihan_id . "%'
               ORDER BY n.created_at DESC";
$stmt_notif = mysqli_prepare($koneksi, $query_notif);
mysqli_stmt_bind_param($stmt_notif, 'i', $tagihan['user_id']);
mysqli_stmt_execute($stmt_notif);
$result_notif = mysqli_stmt_get_result($stmt_notif);
$notifikasi = [];

if (mysqli_num_rows($result_notif) > 0) {
    while ($row = mysqli_fetch_assoc($result_notif)) {
        $notifikasi[] = $row;
    }
}
mysqli_stmt_close($stmt_notif);

// Process status update if requested
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $update_query = "UPDATE tagihan_retribusi SET status = ? WHERE tagihan_id = ?";
    $stmt_update = mysqli_prepare($koneksi, $update_query);
    mysqli_stmt_bind_param($stmt_update, 'si', $new_status, $tagihan_id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        // Create notification for user
        $status_text = '';
        switch ($new_status) {
            case 'belum_bayar':
                $status_text = 'Belum Bayar';
                break;
            case 'proses':
                $status_text = 'Dalam Proses';
                break;
            case 'lunas':
                $status_text = 'Lunas';
                break;
            case 'telat':
                $status_text = 'Telat';
                break;
        }
        
        $notif_judul = "Status Tagihan Diperbarui";
        $notif_pesan = "Status tagihan " . $tagihan['nama_retribusi'] . " periode " . date('F Y', strtotime($tagihan['tanggal_tagihan'])) . " telah diperbarui menjadi " . $status_text . ".";
        
        $query_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                        VALUES (?, ?, ?, 'tagihan', '/tagihan_detail.php?id=".$tagihan_id."')";
        $stmt_notif = mysqli_prepare($koneksi, $query_notif);
        mysqli_stmt_bind_param($stmt_notif, 'iss', $tagihan['user_id'], $notif_judul, $notif_pesan);
        mysqli_stmt_execute($stmt_notif);
        mysqli_stmt_close($stmt_notif);
        
        // Log activity
        $aktivitas = "Mengubah status tagihan #" . $tagihan_id . " menjadi " . $status_text;
        $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)";
        $stmt_log = mysqli_prepare($koneksi, $query_log);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        mysqli_stmt_bind_param($stmt_log, 'iss', $user_id, $aktivitas, $ip_address);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);
        
        $success_message = "Status tagihan berhasil diperbarui menjadi " . $status_text . ".";
        
        // Reload tagihan data
        $tagihan['status'] = $new_status;
    } else {
        $errors[] = "Gagal memperbarui status: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt_update);
}

// Process fine update if requested
if (isset($_POST['update_denda'])) {
    $new_denda = str_replace(['Rp', '.', ','], ['', '', '.'], trim($_POST['denda']));
    
    if (!is_numeric($new_denda) || $new_denda < 0) {
        $errors[] = 'Nilai denda tidak valid.';
    } else {
        $update_query = "UPDATE tagihan_retribusi SET denda = ? WHERE tagihan_id = ?";
        $stmt_update = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($stmt_update, 'di', $new_denda, $tagihan_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            // Create notification for user if denda increased
            if ($new_denda > $tagihan['denda']) {
                $notif_judul = "Denda Tagihan Diperbarui";
                $notif_pesan = "Denda untuk tagihan " . $tagihan['nama_retribusi'] . " periode " . date('F Y', strtotime($tagihan['tanggal_tagihan'])) . " telah diperbarui menjadi Rp " . number_format($new_denda, 0, ',', '.') . ".";
                
                $query_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                                VALUES (?, ?, ?, 'tagihan', '/tagihan_detail.php?id=".$tagihan_id."')";
                $stmt_notif = mysqli_prepare($koneksi, $query_notif);
                mysqli_stmt_bind_param($stmt_notif, 'iss', $tagihan['user_id'], $notif_judul, $notif_pesan);
                mysqli_stmt_execute($stmt_notif);
                mysqli_stmt_close($stmt_notif);
            }
            
            // Log activity
            $aktivitas = "Mengubah denda tagihan #" . $tagihan_id . " menjadi Rp " . number_format($new_denda, 0, ',', '.');
            $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($koneksi, $query_log);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($stmt_log, 'iss', $user_id, $aktivitas, $ip_address);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
            
            $success_message = "Denda tagihan berhasil diperbarui.";
            
            // Reload tagihan data
            $tagihan['denda'] = $new_denda;
            // Recalculate remaining amount
            $sisa_tagihan = $tagihan['nominal'] + $tagihan['denda'] - $total_dibayar;
        } else {
            $errors[] = "Gagal memperbarui denda: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt_update);
    }
}

// Send reminder notification if requested
if (isset($_POST['send_reminder'])) {
    $reminder_message = trim($_POST['reminder_message']);
    
    $notif_judul = "Pengingat Pembayaran";
    $notif_pesan = "Anda memiliki tagihan " . $tagihan['nama_retribusi'] . " periode " . date('F Y', strtotime($tagihan['tanggal_tagihan'])) . " yang belum dibayar. ";
    $notif_pesan .= "Jumlah yang harus dibayar: Rp " . number_format($tagihan['nominal'] + $tagihan['denda'], 0, ',', '.') . ". ";
    $notif_pesan .= "Tanggal jatuh tempo: " . date('d-m-Y', strtotime($tagihan['jatuh_tempo'])) . ". ";
    
    if (!empty($reminder_message)) {
        $notif_pesan .= "\n\nPesan tambahan: " . $reminder_message;
    }
    
    $query_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link) 
                    VALUES (?, ?, ?, 'tagihan', '/tagihan_detail.php?id=".$tagihan_id."')";
    $stmt_notif = mysqli_prepare($koneksi, $query_notif);
    mysqli_stmt_bind_param($stmt_notif, 'iss', $tagihan['user_id'], $notif_judul, $notif_pesan);
    
    if (mysqli_stmt_execute($stmt_notif)) {
        // Log activity
        $aktivitas = "Mengirim pengingat tagihan #" . $tagihan_id . " kepada " . $tagihan['nama_warga'];
        $query_log = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, ref_id) VALUES (?, ?, ?, ?)";
        $stmt_log = mysqli_prepare($koneksi, $query_log);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $notif_id = mysqli_insert_id($koneksi);
        mysqli_stmt_bind_param($stmt_log, 'issi', $user_id, $aktivitas, $ip_address, $notif_id);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);
        
        $success_message = "Pengingat tagihan berhasil dikirim.";
    } else {
        $errors[] = "Gagal mengirim pengingat: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt_notif);
}

// Determine status text and class
$status_text = "";
$status_class = "";
switch ($tagihan['status']) {
    case 'belum_bayar':
        $status_text = "Belum Bayar";
        $status_class = "status-pending";
        break;
    case 'proses':
        $status_text = "Proses";
        $status_class = "status-processing";
        break;
    case 'lunas':
        $status_text = "Lunas";
        $status_class = "status-completed";
        break;
    case 'telat':
        $status_text = "Telat";
        $status_class = "status-rejected";
        break;
}

// Determine if payment is overdue
$is_overdue = false;
if (($tagihan['status'] == 'belum_bayar' || $tagihan['status'] == 'telat') && strtotime($tagihan['jatuh_tempo']) < strtotime('now')) {
    $is_overdue = true;
}

// Format dates
$tanggal_tagihan = date('d-m-Y', strtotime($tagihan['tanggal_tagihan']));
$jatuh_tempo = date('d-m-Y', strtotime($tagihan['jatuh_tempo']));
$periode_bulan = date('F Y', strtotime($tagihan['tanggal_tagihan']));

// Prepare variables for page
$page_title = "Detail Tagihan Retribusi";
$current_page = "retribusi";

// Include header and sidebar
include '../includes/admin_sidebar.php';
include '../includes/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h2>Detail Tagihan Retribusi</h2>
        <div class="admin-header-actions">
            <a href="retribusi.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            <?php if ($tagihan['status'] != 'lunas'): ?>
            <a href="pembayaran_create.php?tagihan_id=<?php echo $tagihan_id; ?>" class="btn"><i class="fas fa-money-bill"></i> Tambah Pembayaran</a>
            <?php endif; ?>
            <a href="tagihan_cetak.php?id=<?php echo $tagihan_id; ?>" class="btn btn-outline" target="_blank"><i class="fas fa-print"></i> Cetak</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Sukses!</strong> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <div class="detail-page">
        <div class="row">
            <!-- Tagihan Details -->
            <div class="col-md-8">
                <div class="detail-card">
                    <div class="detail-header">
                        <div class="detail-title">
                            <h3>Tagihan #<?php echo $tagihan_id; ?></h3>
                            <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                        <div class="detail-meta">
                            <span>Dibuat pada <?php echo $tanggal_tagihan; ?></span>
                        </div>
                    </div>

                    <div class="detail-body">
                        <div class="detail-row">
                            <div class="detail-label">Jenis Retribusi</div>
                            <div class="detail-value"><?php echo htmlspecialchars($tagihan['nama_retribusi']); ?> (<?php echo ucfirst($tagihan['periode']); ?>)</div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Periode</div>
                            <div class="detail-value"><?php echo $periode_bulan; ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Tanggal Tagihan</div>
                            <div class="detail-value"><?php echo $tanggal_tagihan; ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Jatuh Tempo</div>
                            <div class="detail-value <?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                <?php echo $jatuh_tempo; ?>
                                <?php if ($is_overdue): ?>
                                <span class="badge badge-danger">Terlambat</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Nominal</div>
                            <div class="detail-value">Rp <?php echo number_format($tagihan['nominal'], 0, ',', '.'); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Denda</div>
                            <div class="detail-value">
                                Rp <?php echo number_format($tagihan['denda'], 0, ',', '.'); ?>
                                <?php if ($tagihan['status'] != 'lunas'): ?>
                                <button type="button" class="btn-sm edit-denda" title="Edit Denda"><i class="fas fa-edit"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Total Tagihan</div>
                            <div class="detail-value fw-bold">Rp <?php echo number_format($tagihan['nominal'] + $tagihan['denda'], 0, ',', '.'); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Total Dibayar</div>
                            <div class="detail-value<?php echo $total_dibayar > 0 ? ' text-success' : ''; ?>">Rp <?php echo number_format($total_dibayar, 0, ',', '.'); ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Sisa Tagihan</div>
                            <div class="detail-value<?php echo $sisa_tagihan > 0 ? ' text-danger' : ' text-success'; ?>">
                                Rp <?php echo number_format($sisa_tagihan, 0, ',', '.'); ?>
                            </div>
                        </div>
                        
                        <?php if ($tagihan['status'] != 'lunas'): ?>
                        <div class="detail-actions">
                            <form method="POST" class="status-form">
                                <div class="form-row">
                                    <div class="col-md-8">
                                        <select name="status" class="form-control">
                                            <option value="belum_bayar" <?php echo $tagihan['status'] == 'belum_bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                                            <option value="proses" <?php echo $tagihan['status'] == 'proses' ? 'selected' : ''; ?>>Proses</option>
                                            <option value="lunas" <?php echo $tagihan['status'] == 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                                            <option value="telat" <?php echo $tagihan['status'] == 'telat' ? 'selected' : ''; ?>>Telat</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="update_status" class="btn-sm">Update Status</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Denda Form (Hidden by default) -->
                <div class="detail-card denda-form" style="display:none;">
                    <div class="detail-header">
                        <h3>Edit Denda Tagihan</h3>
                    </div>
                    <div class="detail-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="denda">Jumlah Denda</label>
                                <input type="text" id="denda" name="denda" class="form-control currency-input" value="<?php echo number_format($tagihan['denda'], 0, ',', '.'); ?>">
                                <small class="form-text">Masukkan jumlah denda tanpa tanda titik atau koma.</small>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-secondary cancel-denda">Batal</button>
                                <button type="submit" name="update_denda" class="btn-primary">Simpan Denda</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payment History -->
                <div class="detail-card">
                    <div class="detail-header">
                        <h3>Riwayat Pembayaran</h3>
                        <?php if ($tagihan['status'] != 'lunas'): ?>
                        <a href="pembayaran_create.php?tagihan_id=<?php echo $tagihan_id; ?>" class="btn-sm"><i class="fas fa-plus"></i> Tambah Pembayaran</a>
                        <?php endif; ?>
                    </div>
                    <div class="detail-body">
                        <?php if (!empty($pembayaran)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jumlah</th>
                                            <th>Metode</th>
                                            <th>Referensi</th>
                                            <th>Status</th>
                                            <th>Dikonfirmasi Oleh</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pembayaran as $bayar): ?>
                                            <tr>
                                                <td><?php echo date('d-m-Y H:i', strtotime($bayar['tanggal_bayar'])); ?></td>
                                                <td>Rp <?php echo number_format($bayar['jumlah_bayar'], 0, ',', '.'); ?></td>
                                                <td><?php echo htmlspecialchars($bayar['metode_pembayaran']); ?></td>
                                                <td><?php echo htmlspecialchars($bayar['nomor_referensi']); ?></td>
                                                <td>
                                                    <?php 
                                                    $payment_status_class = '';
                                                    $payment_status_text = '';
                                                    switch ($bayar['status']) {
                                                        case 'pending':
                                                            $payment_status_class = 'status-pending';
                                                            $payment_status_text = 'Menunggu';
                                                            break;
                                                        case 'berhasil':
                                                            $payment_status_class = 'status-completed';
                                                            $payment_status_text = 'Berhasil';
                                                            break;
                                                        case 'gagal':
                                                            $payment_status_class = 'status-rejected';
                                                            $payment_status_text = 'Gagal';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="status <?php echo $payment_status_class; ?>"><?php echo $payment_status_text; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo $bayar['confirmed_by'] ? htmlspecialchars($bayar['confirmed_by_name']) : '-'; ?>
                                                    <?php if ($bayar['confirmed_at']): ?>
                                                    <br><small><?php echo date('d-m-Y H:i', strtotime($bayar['confirmed_at'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="action-cell">
                                                    <a href="pembayaran_detail.php?id=<?php echo $bayar['pembayaran_id']; ?>" class="btn-action view" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                                    <?php if ($bayar['status'] == 'pending'): ?>
                                                    <a href="pembayaran_konfirmasi.php?id=<?php echo $bayar['pembayaran_id']; ?>" class="btn-action edit" title="Konfirmasi Pembayaran"><i class="fas fa-check"></i></a>
                                                    <?php endif; ?>
                                                    <?php if ($bayar['bukti_pembayaran']): ?>
                                                    <a href="../uploads/bukti_pembayaran/<?php echo $bayar['bukti_pembayaran']; ?>" class="btn-action view" title="Lihat Bukti" target="_blank"><i class="fas fa-file-image"></i></a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-receipt empty-icon"></i>
                                <p>Belum ada data pembayaran</p>
                                <?php if ($tagihan['status'] != 'lunas'): ?>
                                <a href="pembayaran_create.php?tagihan_id=<?php echo $tagihan_id; ?>" class="btn-sm">Tambah Pembayaran</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notification History -->
                <div class="detail-card">
                    <div class="detail-header">
                        <h3>Riwayat Notifikasi</h3>
                        <?php if ($tagihan['status'] != 'lunas'): ?>
                        <button type="button" class="btn-sm send-reminder"><i class="fas fa-bell"></i> Kirim Pengingat</button>
                        <?php endif; ?>
                    </div>
                    <div class="detail-body">
                        <?php if (!empty($notifikasi)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Judul</th>
                                            <th>Pesan</th>
                                            <th>Dibaca</th>
                                            <th>Dikirim Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notifikasi as $notif): ?>
                                            <tr>
                                                <td><?php echo date('d-m-Y H:i', strtotime($notif['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($notif['judul']); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($notif['pesan'])); ?></td>
                                                <td><?php echo $notif['is_read'] ? '<span class="text-success"><i class="fas fa-check"></i> Ya</span>' : '<span class="text-muted"><i class="fas fa-times"></i> Belum</span>'; ?></td>
                                                <td><?php echo $notif['sent_by_name'] ? htmlspecialchars($notif['sent_by_name']) : 'Sistem'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash empty-icon"></i>
                                <p>Belum ada riwayat notifikasi</p>
                                <?php if ($tagihan['status'] != 'lunas'): ?>
                                <button type="button" class="btn-sm send-reminder">Kirim Pengingat</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Warga Details -->
            <div class="col-md-4">
                <div class="detail-card">
                    <div class="detail-header">
                        <h3>Informasi Warga</h3>
                    </div>
                    <div class="detail-body">
                        <div class="warga-info">
                            <h4><?php echo htmlspecialchars($tagihan['nama_warga']); ?></h4>
                            <p class="text-muted">NIK: <?php echo htmlspecialchars($tagihan['nik']); ?></p>
                            
                            <div class="contact-info">
                                <?php if (!empty($tagihan['nomor_telepon'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($tagihan['nomor_telepon']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($tagihan['email'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($tagihan['email']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($tagihan['alamat'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($tagihan['alamat']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-actions mt-3">
                            <a href="../warga/profil.php?id=<?php echo $tagihan['user_id']; ?>" class="btn-outline-secondary btn-block">Lihat Profil Warga</a>
                            <a href="retribusi.php?search=<?php echo urlencode($tagihan['nik']); ?>" class="btn-outline-secondary btn-block">Lihat Semua Tagihan</a>
                        </div>
                    </div>
                </div>
                
                <!-- Other Bills -->
                <?php
                // Get other bills for this user
                $query_other = "SELECT tr.tagihan_id, tr.tanggal_tagihan, tr.jatuh_tempo, tr.nominal, tr.status, tr.denda,
                                jr.nama_retribusi 
                                FROM tagihan_retribusi tr
                                JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                                WHERE tr.user_id = ? AND tr.tagihan_id != ?
                                ORDER BY tr.tanggal_tagihan DESC
                                LIMIT 5";
                $stmt_other = mysqli_prepare($koneksi, $query_other);
                mysqli_stmt_bind_param($stmt_other, 'ii', $tagihan['user_id'], $tagihan_id);
                mysqli_stmt_execute($stmt_other);
                $result_other = mysqli_stmt_get_result($stmt_other);
                $other_bills = [];
                
                if (mysqli_num_rows($result_other) > 0) {
                    while ($row = mysqli_fetch_assoc($result_other)) {
                        $other_bills[] = $row;
                    }
                }
                mysqli_stmt_close($stmt_other);
                ?>
                
                <?php if (!empty($other_bills)): ?>
                <div class="detail-card">
                    <div class="detail-header">
                        <h3>Tagihan Lainnya</h3>
                    </div>
                    <div class="detail-body">
                        <div class="other-bills">
                            <?php foreach ($other_bills as $bill): ?>
                                <?php
                                $bill_status_class = "";
                                $bill_status_text = "";
                                switch ($bill['status']) {
                                    case 'belum_bayar':
                                        $bill_status_class = "status-pending";
                                        $bill_status_text = "Belum Bayar";
                                        break;
                                    case 'proses':
                                        $bill_status_class = "status-processing";
                                        $bill_status_text = "Proses";
                                        break;
                                    case 'lunas':
                                        $bill_status_class = "status-completed";
                                        $bill_status_text = "Lunas";
                                        break;
                                    case 'telat':
                                        $bill_status_class = "status-rejected";
                                        $bill_status_text = "Telat";
                                        break;
                                }
                                ?>
                                <div class="bill-item">
                                    <div class="bill-info">
                                        <div class="bill-title">
                                            <h5><?php echo htmlspecialchars($bill['nama_retribusi']); ?></h5>
                                            <span class="status <?php echo $bill_status_class; ?>"><?php echo $bill_status_text; ?></span>
                                        </div>
                                        <div class="bill-detail">
                                            <div class="bill-date">
                                                <small>Tagihan: <?php echo date('d-m-Y', strtotime($bill['tanggal_tagihan'])); ?></small>
                                                <small>Jatuh Tempo: <?php echo date('d-m-Y', strtotime($bill['jatuh_tempo'])); ?></small>
                                            </div>
                                            <div class="bill-amount">
                                                <strong>Rp <?php echo number_format($bill['nominal'] + $bill['denda'], 0, ',', '.'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bill-actions">
                                        <a href="tagihan_detail.php?id=<?php echo $bill['tagihan_id']; ?>" class="btn-sm">Lihat</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="detail-actions mt-3">
                            <a href="retribusi.php?search=<?php echo urlencode($tagihan['nik']); ?>" class="btn-outline-secondary btn-block">Lihat Semua Tagihan</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Related Information -->
                <div class="detail-card">
                    <div class="detail-header">
                        <h3>Informasi Retribusi</h3>
                    </div>
                    <div class="detail-body">
                        <div class="info-item">
                            <h5>Deskripsi</h5>
                            <p><?php echo !empty($tagihan['deskripsi_retribusi']) ? nl2br(htmlspecialchars($tagihan['deskripsi_retribusi'])) : 'Tidak ada deskripsi.'; ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h5>Periode Pembayaran</h5>
                            <p><?php
                            switch ($tagihan['periode']) {
                                case 'bulanan':
                                    echo 'Dibayarkan setiap bulan';
                                    break;
                                case 'tahunan':
                                    echo 'Dibayarkan setiap tahun';
                                    break;
                                case 'insidentil':
                                    echo 'Pembayaran tidak berkala (insidentil)';
                                    break;
                            }
                            ?></p>
                        </div>
                        
                        <div class="detail-actions mt-3">
                            <a href="jenis_retribusi_form.php?id=<?php echo $tagihan['jenis_retribusi_id']; ?>" class="btn-outline-secondary btn-block">Detail Jenis Retribusi</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send Reminder Modal -->
<div class="modal" id="reminderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Kirim Pengingat Tagihan</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Kirim pengingat kepada warga untuk tagihan yang belum dibayar.</p>
            <form id="reminderForm" action="" method="POST">
                <div class="form-group">
                    <label for="reminder_message">Pesan Tambahan (Opsional):</label>
                    <textarea name="reminder_message" id="reminder_message" class="form-control" rows="4" placeholder="Masukkan pesan tambahan untuk disertakan dalam pengingat..."></textarea>
                </div>
                <div class="form-group">
                    <div class="alert alert-info">
                        <small>
                            <strong>Informasi:</strong> Sistem akan otomatis menyertakan informasi tagihan (jenis, nominal, jatuh tempo) dalam notifikasi.
                        </small>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-modal">Batal</button>
            <button type="button" class="btn" id="sendReminderConfirm">Kirim Pengingat</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format currency inputs
    const currencyInputs = document.querySelectorAll('.currency-input');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d]/g, '');
            if (value !== '') {
                value = parseInt(value, 10).toLocaleString('id-ID');
                this.value = value;
            }
        });
    });
    
    // Auto-hide alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert-success, .alert-danger');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }
    
    // Edit denda functionality
    const editDendaBtn = document.querySelector('.edit-denda');
    const dendaForm = document.querySelector('.denda-form');
    const cancelDendaBtn = document.querySelector('.cancel-denda');
    
    if (editDendaBtn) {
        editDendaBtn.addEventListener('click', function() {
            dendaForm.style.display = 'block';
        });
    }
    
    if (cancelDendaBtn) {
        cancelDendaBtn.addEventListener('click', function() {
            dendaForm.style.display = 'none';
        });
    }
    
    // Reminder modal functionality
    const reminderModal = document.getElementById('reminderModal');
    const sendReminderBtn = document.querySelector('.send-reminder');
    const closeButtons = document.querySelectorAll('.close, .close-modal');
    const sendReminderConfirm = document.getElementById('sendReminderConfirm');
    const reminderForm = document.getElementById('reminderForm');
    
    if (sendReminderBtn) {
        sendReminderBtn.addEventListener('click', function() {
            reminderModal.style.display = 'flex';
        });
    }
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    if (sendReminderConfirm) {
        sendReminderConfirm.addEventListener('click', function() {
            const submitBtn = document.createElement('input');
            submitBtn.type = 'hidden';
            submitBtn.name = 'send_reminder';
            submitBtn.value = '1';
            reminderForm.appendChild(submitBtn);
            reminderForm.submit();
        });
    }
});
</script>

<style>
/* Detail Page Styles */
.detail-page {
    padding: 0;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -0.75rem;
}

.col-md-8 {
    width: 66.666667%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

.col-md-4 {
    width: 33.333333%;
    padding: 0 0.75rem;
    box-sizing: border-box;
}

/* Card Styles */
.detail-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.detail-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.detail-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.detail-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.detail-meta {
    font-size: 0.875rem;
    color: #6c757d;
}

.detail-body {
    padding: 1.25rem;
}

.detail-row {
    display: flex;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid #f1f1f1;
    padding-bottom: 0.75rem;
}

.detail-row:last-child {
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}

.detail-label {
    width: 40%;
    font-weight: 500;
    color: #555;
}

.detail-value {
    width: 60%;
    color: #333;
}

.detail-actions {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e3e6f0;
}

/* Status Badges */
.status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.status-pending {
    background-color: #fff4db;
    color: #d39e00;
}

.status-processing {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-completed {
    background-color: #d4edda;
    color: #155724;
}

.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    border-radius: 4px;
    margin-left: 0.5rem;
}

.badge-danger {
    background-color: #e74a3b;
    color: white;
}

/* Warga Info Styles */
.warga-info h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.contact-info {
    margin-top: 1rem;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.contact-item i {
    width: 1.5rem;
    color: #6c757d;
}

/* Other Bills Styles */
.other-bills {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.bill-item {
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    padding: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bill-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.bill-title h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
}

.bill-detail {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.bill-date {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

/* Info Items */
.info-item {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f1f1;
}

.info-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.info-item h5 {
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
    font-weight: 600;
}

.info-item p {
    margin: 0;
    color: #555;
    font-size: 0.875rem;
}

/* Form Status Update */
.status-form {
    margin-top: 0.75rem;
}

.form-row {
    display: flex;
    gap: 0.5rem;
}

/* Button Styles */
.btn-block {
    display: block;
    width: 100%;
    margin-bottom: 0.5rem;
    text-align: center;
    text-decoration: none;
    padding: 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.btn-outline-secondary {
    border: 1px solid #d1d3e2;
    color: #6c757d;
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fc;
    color: #4e73df;
    border-color: #4e73df;
}

.btn-primary {
    background-color: #4e73df;
    color: white;
    border: none;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    border: none;
}

.btn-sm, .btn-sm:visited {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    background-color: #4e73df;
    color: white;
    border: none;
    transition: all 0.2s;
}

.btn-sm:hover {
    background-color: #2e59d9;
    color: white;
}

.edit-denda {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
    color: #4e73df;
    padding: 0.25rem;
    margin-left: 0.5rem;
}

/* Table Styles */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    background-color: #f8f9fc;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #5a5c69;
    border-bottom: 1px solid #e3e6f0;
}

.admin-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e3e6f0;
    color: #5a5c69;
}

.admin-table tbody tr:hover {
    background-color: #f8f9fc;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

.table-responsive {
    overflow-x: auto;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #6c757d;
}

.empty-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #e3e6f0;
}

/* Alert Messages */
.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: opacity 0.5s;
}

.alert-success {
    background-color: #d4edda;
    border-left: 4px solid #1cc88a;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-left: 4px solid #e74a3b;
    color: #721c24;
}

.alert-info {
    background-color: #d1ecf1;
    border-left: 4px solid #36b9cc;
    color: #0c5460;
}

/* Text Colors */
.text-success {
    color: #1cc88a;
}

.text-danger {
    color: #e74a3b;
}

.text-muted {
    color: #6c757d;
}

.fw-bold {
    font-weight: 700;
}

.mt-3 {
    margin-top: 1rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.modal-content {
    background-color: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.modal-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    padding: 1.25rem;
    border-top: 1px solid #e3e6f0;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.close {
    font-size: 1.5rem;
    font-weight: 700;
    color: #5a5c69;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #e74a3b;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-8, .col-md-4 {
        width: 100%;
        padding: 0;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label, .detail-value {
        width: 100%;
    }
    
    .detail-label {
        margin-bottom: 0.25rem;
    }
    
    .admin-header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .admin-header-actions .btn {
        width: 100%;
        text-align: center;
    }
    
    .form-row {
        flex-direction: column;
    }
}
</style>

<?php
// Include footer
include '../includes/admin-footer.php';
?>