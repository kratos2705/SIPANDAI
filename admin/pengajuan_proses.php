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

// Check if ID and action are provided
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['action']) || empty($_GET['action'])) {
    $_SESSION['pengajuan_error'] = 'ID Pengajuan atau aksi tidak valid.';
    redirect('pengajuan.php');
}

$pengajuan_id = (int)$_GET['id'];
$action = sanitizeInput($_GET['action']);

// Get application details
$pengajuan_query = "SELECT pd.*, jd.nama_dokumen, u.user_id as pemohon_id, u.nama as pemohon_nama
                   FROM pengajuan_dokumen pd
                   JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
                   JOIN users u ON pd.user_id = u.user_id
                   WHERE pd.pengajuan_id = '$pengajuan_id'";
$pengajuan_result = mysqli_query($koneksi, $pengajuan_query);

// Check if application exists
if (mysqli_num_rows($pengajuan_result) == 0) {
    $_SESSION['pengajuan_error'] = 'Data pengajuan tidak ditemukan.';
    redirect('pengajuan.php');
}

$pengajuan = mysqli_fetch_assoc($pengajuan_result);
$pemohon_id = $pengajuan['pemohon_id'];
$pemohon_nama = $pengajuan['pemohon_nama'];
$dokumen_nama = $pengajuan['nama_dokumen'];
$nomor_pengajuan = $pengajuan['nomor_pengajuan'];

// Determine new status and action description
$new_status = '';
$action_description = '';
$catatan = '';

switch ($action) {
    case 'verify':
        // Check if current status is 'diajukan'
        if ($pengajuan['status'] != 'diajukan') {
            $_SESSION['pengajuan_error'] = 'Pengajuan hanya dapat diverifikasi jika statusnya menunggu.';
            redirect("pengajuan_detail.php?id=$pengajuan_id");
        }
        $new_status = 'verifikasi';
        $action_description = 'memverifikasi';
        $catatan = 'Dokumen telah diverifikasi dan akan diproses.';
        break;
        
    case 'process':
        // Check if current status is 'verifikasi'
        if ($pengajuan['status'] != 'verifikasi') {
            $_SESSION['pengajuan_error'] = 'Pengajuan hanya dapat diproses jika statusnya verifikasi.';
            redirect("pengajuan_detail.php?id=$pengajuan_id");
        }
        $new_status = 'proses';
        $action_description = 'memproses';
        $catatan = 'Dokumen sedang diproses.';
        break;
        
    case 'complete':
        // Check if current status is 'proses'
        if ($pengajuan['status'] != 'proses') {
            $_SESSION['pengajuan_error'] = 'Pengajuan hanya dapat diselesaikan jika statusnya proses.';
            redirect("pengajuan_detail.php?id=$pengajuan_id");
        }
        $new_status = 'selesai';
        $action_description = 'menyelesaikan';
        $catatan = 'Dokumen telah selesai diproses dan siap diambil.';
        break;
        
    case 'reject':
        $new_status = 'ditolak';
        $action_description = 'menolak';
        $catatan = 'Pengajuan ditolak. Silakan hubungi kantor desa untuk informasi lebih lanjut.';
        break;
        
    default:
        $_SESSION['pengajuan_error'] = 'Aksi tidak valid.';
        redirect("pengajuan_detail.php?id=$pengajuan_id");
}

// Optional message from request if provided
if (isset($_GET['message']) && !empty($_GET['message'])) {
    $catatan = sanitizeInput($_GET['message']);
}

// Start transaction
mysqli_begin_transaction($koneksi);

try {
    // Update status
    $update_query = "UPDATE pengajuan_dokumen 
                    SET status = '$new_status', catatan = '$catatan'";
    
    // If status is completed, set completion date
    if ($new_status == 'selesai') {
        $update_query .= ", tanggal_selesai = NOW()";
    }
    
    $update_query .= " WHERE pengajuan_id = '$pengajuan_id'";
    
    if (!mysqli_query($koneksi, $update_query)) {
        throw new Exception("Gagal mengupdate status pengajuan: " . mysqli_error($koneksi));
    }
    
    // Add to history
    $history_query = "INSERT INTO riwayat_pengajuan (pengajuan_id, status, catatan, changed_by)
                     VALUES ('$pengajuan_id', '$new_status', '$catatan', '$user_id')";
    
    if (!mysqli_query($koneksi, $history_query)) {
        throw new Exception("Gagal mencatat riwayat perubahan: " . mysqli_error($koneksi));
    }
    
    // Create notification for the applicant
    $judul_notif = "Status Pengajuan Berubah";
    $pesan_notif = "Pengajuan dokumen $dokumen_nama dengan nomor $nomor_pengajuan telah $action_description.";
    
    if (!empty($catatan)) {
        $pesan_notif .= " Catatan: $catatan";
    }
    
    $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link)
                   VALUES ('$pemohon_id', '$judul_notif', '$pesan_notif', 'pengajuan', 'detail-pengajuan.php?id=$pengajuan_id')";
    
    if (!mysqli_query($koneksi, $notif_query)) {
        throw new Exception("Gagal membuat notifikasi: " . mysqli_error($koneksi));
    }
    
    // Commit transaction
    mysqli_commit($koneksi);
    
    $_SESSION['pengajuan_success'] = "Status pengajuan berhasil diubah menjadi $new_status.";
    redirect("pengajuan_detail.php?id=$pengajuan_id");
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($koneksi);
    $_SESSION['pengajuan_error'] = $e->getMessage();
    redirect("pengajuan_detail.php?id=$pengajuan_id");
}
?>