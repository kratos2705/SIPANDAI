<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login terlebih dahulu';
    redirect('../index.php');
}

// Include database connection
require_once '../config/koneksi.php';

// Get user's ID
$user_id = $_SESSION['user_id'];

// Get user details
$user_query = "SELECT nama, alamat, nomor_telepon FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($koneksi, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);

// Check if reference number is provided
if (!isset($_GET['ref']) || empty($_GET['ref'])) {
    $_SESSION['error'] = 'Nomor referensi tidak ditemukan';
    redirect('layanan-pembayaran.php');
}

$reference = mysqli_real_escape_string($koneksi, $_GET['ref']);

// Get payment details using prepared statement
$payment_query = "SELECT pr.*, tr.nominal as tagihan_nominal, tr.denda, 
                  jr.nama_retribusi, jr.periode, tr.tanggal_tagihan, tr.jatuh_tempo,
                  u.nama as konfirmasi_oleh
                  FROM pembayaran_retribusi pr
                  JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
                  JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                  LEFT JOIN users u ON pr.confirmed_by = u.user_id
                  WHERE pr.nomor_referensi = ? 
                  AND tr.user_id = ?
                  LIMIT 1";
$stmt = mysqli_prepare($koneksi, $payment_query);
mysqli_stmt_bind_param($stmt, "si", $reference, $user_id);
mysqli_stmt_execute($stmt);
$payment_result = mysqli_stmt_get_result($stmt);

// Check if payment exists and belongs to the user
if (mysqli_num_rows($payment_result) == 0) {
    $_SESSION['error'] = 'Detail pembayaran tidak ditemukan atau Anda tidak memiliki akses';
    redirect('layanan-pembayaran.php');
}

$payment = mysqli_fetch_assoc($payment_result);

// Get all bills included in this payment
$tagihan_query = "SELECT pr.*, tr.nominal as tagihan_nominal, tr.denda, 
                 jr.nama_retribusi, jr.periode, tr.tanggal_tagihan, tr.jatuh_tempo
                 FROM pembayaran_retribusi pr
                 JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
                 JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                 WHERE pr.nomor_referensi = ?
                 AND tr.user_id = ?";
$stmt = mysqli_prepare($koneksi, $tagihan_query);
mysqli_stmt_bind_param($stmt, "si", $reference, $user_id);
mysqli_stmt_execute($stmt);
$tagihan_result = mysqli_stmt_get_result($stmt);

// Calculate totals
$total_tagihan = 0;
$total_denda = 0;
$admin_fee = 2500; // Admin fee in Rupiah

while ($row = mysqli_fetch_assoc($tagihan_result)) {
    $total_tagihan += $row['tagihan_nominal'];
    $total_denda += $row['denda'];
}

$total_bayar = $total_tagihan + $total_denda + $admin_fee;

// Reset pointer
mysqli_data_seek($tagihan_result, 0);

// Get payment tracking from the log activity table
$tracking_query = "SELECT *
                  FROM log_aktivitas
                  WHERE aktivitas LIKE CONCAT('%', ?, '%')
                  ORDER BY created_at ASC";
$stmt = mysqli_prepare($koneksi, $tracking_query);
mysqli_stmt_bind_param($stmt, "s", $reference);
mysqli_stmt_execute($stmt);
$tracking_result = mysqli_stmt_get_result($stmt);

// Upload payment proof if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {
    // Check if payment is still pending
    if ($payment['status'] !== 'pending') {
        $_SESSION['error'] = 'Pembayaran ini tidak dapat diperbarui karena statusnya sudah ' . ucfirst($payment['status']);
    } else {
        // Check if file is uploaded
        if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_name = $_FILES['bukti_pembayaran']['name'];
            $file_size = $_FILES['bukti_pembayaran']['size'];
            $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Validate file extension
            if (in_array($file_ext, $allowed_ext)) {
                // Validate file size (max 2MB)
                if ($file_size <= 2097152) {
                    // Generate unique file name
                    $new_file_name = 'payment_' . $reference . '_' . time() . '.' . $file_ext;
                    $upload_path = '../uploads/payments/';
                    
                    // Create directory if not exists
                    if (!file_exists($upload_path)) {
                        mkdir($upload_path, 0777, true);
                    }
                    
                    $full_path = $upload_path . $new_file_name;

                    // Upload file
                    if (move_uploaded_file($file_tmp, $full_path)) {
                        // Start transaction
                        mysqli_begin_transaction($koneksi);
                        
                        try {
                            // Update all payment records for this reference
                            $update_query = "UPDATE pembayaran_retribusi 
                                           SET bukti_pembayaran = ? 
                                           WHERE nomor_referensi = ?";
                            $update_stmt = mysqli_prepare($koneksi, $update_query);
                            mysqli_stmt_bind_param($update_stmt, "ss", $new_file_name, $reference);
                            mysqli_stmt_execute($update_stmt);
                            
                            // Log activity
                            $aktivitas = "Mengunggah bukti pembayaran untuk referensi $reference";
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $user_agent = $_SERVER['HTTP_USER_AGENT'];
                            $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent, created_at) 
                                         VALUES (?, ?, ?, ?, NOW())";
                            $log_stmt = mysqli_prepare($koneksi, $log_query);
                            mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $aktivitas, $ip_address, $user_agent);
                            mysqli_stmt_execute($log_stmt);
                            
                            // Create notification for admin
                            $admin_notif = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link, created_at) 
                                          VALUES (1, 'Bukti Pembayaran Baru', ?, 'pembayaran', ?, NOW())";
                            $notif_text = "Bukti pembayaran untuk referensi $reference telah diunggah oleh " . $user_data['nama'] . " dan memerlukan verifikasi.";
                            $notif_link = "admin/verifikasi-pembayaran.php?ref=$reference";
                            $admin_stmt = mysqli_prepare($koneksi, $admin_notif);
                            mysqli_stmt_bind_param($admin_stmt, "ss", $notif_text, $notif_link);
                            mysqli_stmt_execute($admin_stmt);
                            
                            // Commit transaction
                            mysqli_commit($koneksi);
                            
                            $_SESSION['success'] = 'Bukti pembayaran berhasil diunggah';
                            redirect('pembayaran_detail.php?ref=' . $reference);
                        } catch (Exception $e) {
                            // Rollback on error
                            mysqli_rollback($koneksi);
                            $_SESSION['error'] = 'Gagal memperbarui data pembayaran: ' . $e->getMessage();
                        }
                    } else {
                        $_SESSION['error'] = 'Gagal mengunggah file';
                    }
                } else {
                    $_SESSION['error'] = 'Ukuran file terlalu besar (maksimal 2MB)';
                }
            } else {
                $_SESSION['error'] = 'Jenis file tidak diizinkan (hanya JPG, JPEG, PNG, dan PDF)';
            }
        } else {
            $_SESSION['error'] = 'Pilih file bukti pembayaran terlebih dahulu';
        }
    }
}

// Cancel payment if requested
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['confirm']) && $_GET['confirm'] == '1') {
    // Check if payment is still pending
    if ($payment['status'] !== 'pending') {
        $_SESSION['error'] = 'Pembayaran ini tidak dapat dibatalkan karena statusnya sudah ' . ucfirst($payment['status']);
    } else {
        // Start transaction
        mysqli_begin_transaction($koneksi);
        
        try {
            // Update payment status
            $update_payment = "UPDATE pembayaran_retribusi 
                              SET status = 'gagal', catatan = 'Dibatalkan oleh pengguna' 
                              WHERE nomor_referensi = ?";
            $payment_stmt = mysqli_prepare($koneksi, $update_payment);
            mysqli_stmt_bind_param($payment_stmt, "s", $reference);
            mysqli_stmt_execute($payment_stmt);
            
            // Update bill status back to unpaid
            $update_bills = "UPDATE tagihan_retribusi tr
                            JOIN pembayaran_retribusi pr ON tr.tagihan_id = pr.tagihan_id
                            SET tr.status = 'belum_bayar'
                            WHERE pr.nomor_referensi = ?";
            $bills_stmt = mysqli_prepare($koneksi, $update_bills);
            mysqli_stmt_bind_param($bills_stmt, "s", $reference);
            mysqli_stmt_execute($bills_stmt);
            
            // Log activity
            $aktivitas = "Membatalkan pembayaran dengan referensi $reference";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent, created_at) 
                         VALUES (?, ?, ?, ?, NOW())";
            $log_stmt = mysqli_prepare($koneksi, $log_query);
            mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $aktivitas, $ip_address, $user_agent);
            mysqli_stmt_execute($log_stmt);
            
            // Create notification
            $judul = "Pembayaran Dibatalkan";
            $pesan = "Pembayaran dengan nomor referensi $reference telah dibatalkan.";
            $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, created_at) 
                           VALUES (?, ?, ?, 'pembayaran', NOW())";
            $notif_stmt = mysqli_prepare($koneksi, $notif_query);
            mysqli_stmt_bind_param($notif_stmt, "iss", $user_id, $judul, $pesan);
            mysqli_stmt_execute($notif_stmt);
            
            // Commit transaction
            mysqli_commit($koneksi);
            
            $_SESSION['success'] = 'Pembayaran berhasil dibatalkan';
            redirect('layanan-pembayaran.php');
        } catch (Exception $e) {
            // Rollback on error
            mysqli_rollback($koneksi);
            $_SESSION['error'] = 'Gagal membatalkan pembayaran: ' . $e->getMessage();
        }
    }
}

// Include header
$page_title = "Detail Pembayaran";
include '../includes/header.php';
?>

<div class="main-container">
    <section class="page-header">
        <h2>Detail Pembayaran</h2>
        <p>Informasi detail dan status pembayaran</p>
    </section>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
    <?php endif; ?>

    <div class="payment-detail-container">
        <!-- Payment Summary Card -->
        <div class="payment-card">
            <div class="payment-header">
                <h3>Informasi Pembayaran</h3>
                <?php
                $status_class = '';
                $status_text = '';
                
                switch ($payment['status']) {
                    case 'pending':
                        $status_class = 'status-belum';
                        $status_text = 'Menunggu Pembayaran';
                        break;
                    case 'berhasil':
                        $status_class = 'status-lunas';
                        $status_text = 'Pembayaran Berhasil';
                        break;
                    case 'gagal':
                        $status_class = 'status-jatuh-tempo';
                        $status_text = 'Pembayaran Gagal';
                        break;
                    default:
                        $status_class = 'status-proses';
                        $status_text = 'Sedang Diproses';
                }
                ?>
                <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            
            <div class="payment-detail-info">
                <div class="info-group">
                    <label>No. Referensi:</label>
                    <span><?php echo htmlspecialchars($payment['nomor_referensi']); ?></span>
                </div>
                
                <div class="info-group">
                    <label>Tanggal Pembayaran:</label>
                    <span><?php echo date('d-m-Y H:i', strtotime($payment['tanggal_bayar'])); ?></span>
                </div>
                
                <div class="info-group">
                    <label>Metode Pembayaran:</label>
                    <span>
                        <?php 
                            $metode = $payment['metode_pembayaran'];
                            echo ($metode === 'transfer_bank') ? 'Transfer Bank' : 
                                (($metode === 'e_wallet') ? 'E-Wallet' : 
                                (($metode === 'qris') ? 'QRIS' : ucfirst($metode)));
                        ?>
                    </span>
                </div>
                
                <div class="info-group">
                    <label>Total Pembayaran:</label>
                    <span class="total-amount">Rp <?php echo number_format($payment['jumlah_bayar'], 0, ',', '.'); ?></span>
                </div>
                
                <?php if (!empty($payment['bukti_pembayaran'])): ?>
                <div class="info-group">
                    <label>Bukti Pembayaran:</label>
                    <a href="../uploads/payments/<?php echo $payment['bukti_pembayaran']; ?>" target="_blank" class="view-receipt">Lihat Bukti</a>
                </div>
                <?php endif; ?>
                
                <?php if ($payment['status'] === 'berhasil'): ?>
                <div class="info-group">
                    <label>Dikonfirmasi Oleh:</label>
                    <span><?php echo htmlspecialchars($payment['konfirmasi_oleh'] ?? 'Sistem'); ?></span>
                </div>
                
                <div class="info-group">
                    <label>Tanggal Konfirmasi:</label>
                    <span><?php echo date('d-m-Y H:i', strtotime($payment['confirmed_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($payment['catatan'])): ?>
                <div class="info-group">
                    <label>Catatan:</label>
                    <span><?php echo htmlspecialchars($payment['catatan']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Items -->
        <div class="payment-card">
            <div class="payment-header">
                <h3>Detail Tagihan</h3>
            </div>
            
            <div class="payment-items">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Jenis Retribusi</th>
                            <th>Periode</th>
                            <th>Jatuh Tempo</th>
                            <th>Nominal</th>
                            <th>Denda</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        mysqli_data_seek($tagihan_result, 0); // Reset pointer
                        while ($tagihan = mysqli_fetch_assoc($tagihan_result)) {
                            $periode_text = '';
                            switch ($tagihan['periode']) {
                                case 'bulanan':
                                    $periode_text = 'Bulanan - ' . date('F Y', strtotime($tagihan['tanggal_tagihan']));
                                    break;
                                case 'tahunan':
                                    $periode_text = 'Tahunan - ' . date('Y', strtotime($tagihan['tanggal_tagihan']));
                                    break;
                                case 'insidentil':
                                    $periode_text = 'Insidentil';
                                    break;
                                default:
                                    $periode_text = ucfirst($tagihan['periode']);
                            }
                            
                            $subtotal = $tagihan['tagihan_nominal'] + $tagihan['denda'];
                            
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($tagihan['nama_retribusi']) . "</td>";
                            echo "<td>" . $periode_text . "</td>";
                            echo "<td>" . date('d-m-Y', strtotime($tagihan['jatuh_tempo'])) . "</td>";
                            echo "<td>Rp " . number_format($tagihan['tagihan_nominal'], 0, ',', '.') . "</td>";
                            echo "<td>Rp " . number_format($tagihan['denda'], 0, ',', '.') . "</td>";
                            echo "<td>Rp " . number_format($subtotal, 0, ',', '.') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                        <tr>
                            <td colspan="5" class="text-right"><strong>Biaya Admin</strong></td>
                            <td>Rp <?php echo number_format($admin_fee, 0, ',', '.'); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="5" class="text-right"><strong>Total Pembayaran</strong></td>
                            <td><strong>Rp <?php echo number_format($total_bayar, 0, ',', '.'); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Payment Tracking -->
        <div class="payment-card">
            <div class="payment-header">
                <h3>Status Pembayaran</h3>
            </div>
            
            <div class="payment-tracking">
                <div class="tracking-timeline">
                    <?php
                    // Default timeline items if no tracking data
                    if (mysqli_num_rows($tracking_result) == 0) {
                        $timeline_items = [
                            [
                                'date' => date('d-m-Y H:i', strtotime($payment['tanggal_bayar'])),
                                'title' => 'Pembayaran Dibuat',
                                'description' => 'Permintaan pembayaran berhasil dibuat dengan nomor referensi ' . $payment['nomor_referensi'],
                                'status' => 'completed'
                            ],
                            [
                                'date' => '',
                                'title' => 'Menunggu Pembayaran',
                                'description' => 'Silakan lakukan pembayaran sesuai dengan metode yang dipilih',
                                'status' => $payment['status'] !== 'pending' ? 'completed' : 'active'
                            ],
                            [
                                'date' => (!empty($payment['bukti_pembayaran'])) ? 'Bukti Diunggah' : '',
                                'title' => 'Verifikasi Pembayaran',
                                'description' => 'Pembayaran sedang diverifikasi oleh admin',
                                'status' => ($payment['status'] === 'berhasil' || $payment['status'] === 'gagal') ? 'completed' : ((!empty($payment['bukti_pembayaran'])) ? 'active' : 'pending')
                            ],
                            [
                                'date' => ($payment['status'] === 'berhasil') ? date('d-m-Y H:i', strtotime($payment['confirmed_at'])) : '',
                                'title' => 'Pembayaran Selesai',
                                'description' => 'Pembayaran telah dikonfirmasi dan retribusi sudah dibayarkan',
                                'status' => ($payment['status'] === 'berhasil') ? 'completed' : (($payment['status'] === 'gagal') ? 'failed' : 'pending')
                            ]
                        ];
                        
                        foreach ($timeline_items as $item) {
                            echo '<div class="timeline-item timeline-' . $item['status'] . '">';
                            echo '<div class="timeline-badge"></div>';
                            echo '<div class="timeline-content">';
                            echo '<h4>' . $item['title'] . '</h4>';
                            echo '<p>' . $item['description'] . '</p>';
                            if (!empty($item['date'])) {
                                echo '<span class="timeline-date">' . $item['date'] . '</span>';
                            }
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        // Show actual tracking data
                        while ($log = mysqli_fetch_assoc($tracking_result)) {
                            echo '<div class="timeline-item timeline-completed">';
                            echo '<div class="timeline-badge"></div>';
                            echo '<div class="timeline-content">';
                            echo '<h4>' . htmlspecialchars($log['aktivitas']) . '</h4>';
                            echo '<span class="timeline-date">' . date('d-m-Y H:i', strtotime($log['created_at'])) . '</span>';
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Actions Section -->
        <div class="payment-actions">
            <?php if ($payment['status'] === 'pending'): ?>
                <?php if (empty($payment['bukti_pembayaran'])): ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <h3>Unggah Bukti Pembayaran</h3>
                        </div>
                        
                        <div class="payment-upload-form">
                            <form action="pembayaran_detail.php?ref=<?php echo $reference; ?>" method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Bukti Pembayaran (JPG, PNG, PDF max 2MB)</label>
                                    <input type="file" name="bukti_pembayaran" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="upload_bukti" class="btn btn-primary">Unggah Bukti</button>
                                    <a href="pembayaran_detail.php?ref=<?php echo $reference; ?>&action=cancel" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan pembayaran ini?')">Batalkan Pembayaran</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="payment-card">
                        <div class="payment-header">
                            <h3>Tindakan</h3>
                        </div>
                        
                        <div class="payment-info-text">
                            <p>Bukti pembayaran Anda telah diunggah dan sedang dalam proses verifikasi.</p>
                            <p>Proses verifikasi membutuhkan waktu maksimal 1x24 jam kerja.</p>
                            
                            <div class="form-actions">
                                <a href="pembayaran_detail.php?ref=<?php echo $reference; ?>&action=cancel" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan pembayaran ini?')">Batalkan Pembayaran</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($payment['status'] === 'berhasil'): ?>
                <div class="payment-card">
                    <div class="payment-header">
                        <h3>Tindakan</h3>
                    </div>
                    
                    <div class="payment-info-text success-text">
                        <p>Pembayaran Anda telah berhasil dikonfirmasi.</p>
                        <p>Terima kasih atas pembayaran tepat waktu Anda.</p>
                        
                        <div class="form-actions">
                            <a href="cetak_bukti.php?ref=<?php echo $reference; ?>" class="btn btn-primary" target="_blank">Cetak Bukti Pembayaran</a>
                            <a href="layanan-pembayaran.php" class="btn btn-secondary">Kembali ke Daftar Pembayaran</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="payment-card">
                    <div class="payment-header">
                        <h3>Tindakan</h3>
                    </div>
                    
                    <div class="payment-info-text failed-text">
                        <p>Pembayaran ini dibatalkan atau gagal diproses.</p>
                        <?php if (!empty($payment['catatan'])): ?>
                            <p>Catatan: <?php echo htmlspecialchars($payment['catatan']); ?></p>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <a href="layanan-pembayaran.php" class="btn btn-secondary">Kembali ke Daftar Pembayaran</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
    /* 
* Payment Detail Page CSS
* Created for retribution payment system
*/

/* ===== GENERAL STYLES ===== */
:root {
  --primary-color: #0056b3;
  --secondary-color: #6c757d;
  --success-color: #28a745;
  --danger-color: #dc3545;
  --warning-color: #ffc107;
  --info-color: #17a2b8;
  --light-color: #f8f9fa;
  --dark-color: #343a40;
  --border-color: #dee2e6;
  --border-radius: 0.25rem;
  --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  --transition: all 0.3s ease;
}

.main-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ===== PAGE HEADER ===== */
.page-header {
  margin-bottom: 30px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 15px;
}

.page-header h2 {
  font-size: 28px;
  color: var(--dark-color);
  margin-bottom: 5px;
}

.page-header p {
  color: var(--secondary-color);
  font-size: 16px;
  margin: 0;
}

/* ===== ALERTS ===== */
.alert {
  padding: 15px;
  margin-bottom: 20px;
  border: 1px solid transparent;
  border-radius: var(--border-radius);
}

.alert-success {
  background-color: #d4edda;
  border-color: #c3e6cb;
  color: #155724;
}

.alert-danger {
  background-color: #f8d7da;
  border-color: #f5c6cb;
  color: #721c24;
}

/* ===== PAYMENT CARD CONTAINER ===== */
.payment-detail-container {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

.payment-card {
  background-color: white;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: hidden;
  transition: var(--transition);
}

.payment-card:hover {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.payment-header {
  background-color: #f8f9fa;
  padding: 15px 20px;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.payment-header h3 {
  margin: 0;
  font-size: 20px;
  color: var(--dark-color);
}

/* ===== STATUS BADGES ===== */
.status {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.status-belum {
  background-color: #fff3cd;
  color: #856404;
}

.status-lunas {
  background-color: #d4edda;
  color: #155724;
}

.status-jatuh-tempo {
  background-color: #f8d7da;
  color: #721c24;
}

.status-proses {
  background-color: #d1ecf1;
  color: #0c5460;
}

/* ===== PAYMENT DETAIL INFO ===== */
.payment-detail-info {
  padding: 20px;
}

.info-group {
  margin-bottom: 15px;
  display: flex;
  flex-wrap: wrap;
}

.info-group:last-child {
  margin-bottom: 0;
}

.info-group label {
  flex: 0 0 180px;
  font-weight: 600;
  color: var(--secondary-color);
}

.info-group span {
  flex: 1;
  color: var(--dark-color);
}

.total-amount {
  font-weight: bold;
  font-size: 18px;
  color: var(--primary-color) !important;
}

/* ===== PAYMENT ITEMS TABLE ===== */
.payment-items {
  padding: 20px;
  overflow-x: auto;
}

.payment-table {
  width: 100%;
  border-collapse: collapse;
}

.payment-table th, 
.payment-table td {
  padding: 12px 15px;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.payment-table th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: var(--dark-color);
}

.payment-table tbody tr:last-child td {
  border-bottom: none;
}

.payment-table tbody tr:hover {
  background-color: #f8f9fa;
}

.text-right {
  text-align: right !important;
}

.total-row {
  background-color: #f8f9fa;
  font-size: 16px;
}

.total-row td {
  padding-top: 15px;
  padding-bottom: 15px;
}

/* ===== PAYMENT TRACKING ===== */
.payment-tracking {
  padding: 20px;
}

.tracking-timeline {
  position: relative;
  padding-left: 30px;
}

.tracking-timeline:before {
  content: '';
  position: absolute;
  left: 7px;
  top: 0;
  bottom: 0;
  width: 2px;
  background-color: #e9ecef;
}

.timeline-item {
  position: relative;
  margin-bottom: 25px;
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-badge {
  position: absolute;
  left: -30px;
  top: 0;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background-color: white;
  border: 2px solid #e9ecef;
  z-index: 1;
}

.timeline-completed .timeline-badge {
  background-color: var(--success-color);
  border-color: var(--success-color);
}

.timeline-active .timeline-badge {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.timeline-pending .timeline-badge {
  background-color: var(--secondary-color);
  border-color: var(--secondary-color);
}

.timeline-failed .timeline-badge {
  background-color: var(--danger-color);
  border-color: var(--danger-color);
}

.timeline-content {
  padding-bottom: 10px;
}

.timeline-content h4 {
  margin: 0 0 5px 0;
  font-size: 16px;
  color: var(--dark-color);
}

.timeline-content p {
  margin: 0 0 5px 0;
  color: var(--secondary-color);
  font-size: 14px;
}

.timeline-date {
  display: block;
  font-size: 13px;
  color: #adb5bd;
}

/* ===== UPLOAD FORM ===== */
.payment-upload-form {
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--dark-color);
}

.form-control {
  display: block;
  width: 100%;
  padding: 10px 15px;
  font-size: 16px;
  line-height: 1.5;
  color: #495057;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #ced4da;
  border-radius: var(--border-radius);
  transition: var(--transition);
}

.form-control:focus {
  color: #495057;
  background-color: #fff;
  border-color: #80bdff;
  outline: 0;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* ===== FORM ACTIONS ===== */
.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.btn {
  display: inline-block;
  font-weight: 400;
  text-align: center;
  white-space: nowrap;
  vertical-align: middle;
  user-select: none;
  border: 1px solid transparent;
  padding: 10px 20px;
  font-size: 16px;
  line-height: 1.5;
  border-radius: var(--border-radius);
  transition: var(--transition);
  cursor: pointer;
}

.btn:focus, .btn:hover {
  text-decoration: none;
  outline: 0;
}

.btn-primary {
  color: #fff;
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background-color: #0069d9;
  border-color: #0062cc;
}

.btn-secondary {
  color: #fff;
  background-color: var(--secondary-color);
  border-color: var(--secondary-color);
}

.btn-secondary:hover {
  background-color: #5a6268;
  border-color: #545b62;
}

.btn-danger {
  color: #fff;
  background-color: var(--danger-color);
  border-color: var(--danger-color);
}

.btn-danger:hover {
  background-color: #c82333;
  border-color: #bd2130;
}

/* ===== PAYMENT INFO TEXT ===== */
.payment-info-text {
  padding: 20px;
}

.payment-info-text p {
  margin-bottom: 10px;
  color: var(--dark-color);
}

.success-text p {
  color: var(--success-color);
}

.failed-text p {
  color: var(--danger-color);
}

/* ===== LINKS ===== */
.view-receipt {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
}

.view-receipt:hover {
  text-decoration: underline;
}

.view-receipt:before {
  content: '';
  display: inline-block;
  width: 16px;
  height: 16px;
  margin-right: 5px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%230056b3' d='M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (min-width: 768px) {
  .payment-detail-container {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  }
  
  .payment-items, 
  .payment-tracking, 
  .payment-actions {
    grid-column: 1 / -1; /* Span full width */
  }
}

@media (max-width: 576px) {
  .info-group label,
  .info-group span {
    flex: 0 0 100%;
  }
  
  .info-group label {
    margin-bottom: 5px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .form-actions .btn {
    width: 100%;
    margin-bottom: 10px;
  }
}
</style>
<?php include '../includes/footer.php'; ?>