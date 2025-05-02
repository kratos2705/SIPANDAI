<?php
// Include necessary files
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'kepala_desa')) {
    echo '<p class="error">Anda tidak memiliki akses.</p>';
    exit;
}

// Include database connection
require_once '../config/koneksi.php';

// Check if log ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<p class="error">ID Log tidak valid.</p>';
    exit;
}

$log_id = $_GET['id'];

// Get log details
$query = "SELECT la.*, u.nama, u.role, u.email
          FROM log_aktivitas la
          LEFT JOIN users u ON la.user_id = u.user_id
          WHERE la.log_id = ?";

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, 'i', $log_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo '<p class="error">Data log tidak ditemukan.</p>';
    exit;
}

$log = mysqli_fetch_assoc($result);

// Format datetime
$tanggal = date('d-m-Y H:i:s', strtotime($log['created_at']));

// Format user agent for better readability
$user_agent = $log['user_agent'];
$browser = '';
$os = '';

// Extract browser information
if (preg_match('/(MSIE|Trident|Edge|Chrome|Safari|Firefox|Opera|OPR)\/?\s*(\d+(\.\d+)*)/', $user_agent, $matches)) {
    $browser = $matches[1] . ' ' . $matches[2];
}

// Extract OS information
if (preg_match('/(Windows NT|Mac OS X|Linux|Android|iOS)\s*([0-9\._]+)?/', $user_agent, $matches)) {
    $os = $matches[1];
    if (isset($matches[2])) {
        $os .= ' ' . $matches[2];
    }
}
?>

<div class="log-detail">
    <div class="detail-section">
        <h4>Informasi Umum</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Log ID:</span>
                <span class="detail-value"><?php echo $log_id; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Waktu:</span>
                <span class="detail-value"><?php echo $tanggal; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">IP Address:</span>
                <span class="detail-value"><?php echo htmlspecialchars($log['ip_address']); ?></span>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h4>Pengguna</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Nama:</span>
                <span class="detail-value"><?php echo htmlspecialchars($log['nama'] ?? 'System'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo htmlspecialchars($log['email'] ?? '-'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Role:</span>
                <span class="detail-value"><?php echo ucfirst($log['role'] ?? '-'); ?></span>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h4>Aktivitas</h4>
        <div class="detail-grid">
            <div class="detail-item full-width">
                <span class="detail-label">Jenis Aktivitas:</span>
                <span class="detail-value"><?php echo htmlspecialchars($log['aktivitas']); ?></span>
            </div>
            <div class="detail-item full-width">
                <span class="detail-label">Deskripsi:</span>
                <span class="detail-value"><?php echo nl2br(htmlspecialchars($log['deskripsi'] ?? '-')); ?></span>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h4>Informasi Perangkat</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Browser:</span>
                <span class="detail-value"><?php echo htmlspecialchars($browser ?: 'Unknown'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Sistem Operasi:</span>
                <span class="detail-value"><?php echo htmlspecialchars($os ?: 'Unknown'); ?></span>
            </div>
            <div class="detail-item full-width">
                <span class="detail-label">User Agent:</span>
                <div class="user-agent-box">
                    <code><?php echo htmlspecialchars($user_agent); ?></code>
                </div>
            </div>
        </div>
    </div>
</div>