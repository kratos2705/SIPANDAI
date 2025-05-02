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
    exit;
}

// Include database connection
require_once '../config/koneksi.php';

// Get current date for filename
$date = date('Y-m-d');
$filename = "log_aktivitas_$date.csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Initialize filters
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_activity = isset($_GET['activity']) ? $_GET['activity'] : '';
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Build query with filters
$query_conditions = [];
$query_params = [];

// Base query
$base_query = "SELECT la.log_id, la.aktivitas, la.deskripsi, la.ip_address, la.user_agent, la.created_at, 
               u.nama, u.email, u.role
               FROM log_aktivitas la
               LEFT JOIN users u ON la.user_id = u.user_id";

// Apply filters
if (!empty($filter_user)) {
    $query_conditions[] = "(u.nama LIKE ? OR u.user_id = ?)";
    $query_params[] = "%$filter_user%";
    $query_params[] = $filter_user;
}

if (!empty($filter_activity)) {
    $query_conditions[] = "la.aktivitas LIKE ?";
    $query_params[] = "%$filter_activity%";
}

if (!empty($filter_date_start)) {
    $query_conditions[] = "DATE(la.created_at) >= ?";
    $query_params[] = $filter_date_start;
}

if (!empty($filter_date_end)) {
    $query_conditions[] = "DATE(la.created_at) <= ?";
    $query_params[] = $filter_date_end;
}

// Combine conditions
$where_clause = '';
if (!empty($query_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $query_conditions);
}

// Final query
$query = $base_query . $where_clause . " ORDER BY la.created_at DESC";
$stmt = mysqli_prepare($koneksi, $query);

if (!empty($query_params)) {
    $types = str_repeat('s', count($query_params));
    mysqli_stmt_bind_param($stmt, $types, ...$query_params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel CSV encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add header row
fputcsv($output, [
    'No',
    'ID Log',
    'Tanggal & Waktu',
    'Nama Pengguna',
    'Email',
    'Role',
    'Aktivitas',
    'Deskripsi',
    'IP Address',
    'Browser',
    'OS'
]);

// Add data rows
if (mysqli_num_rows($result) > 0) {
    $row_number = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        // Format datetime
        $tanggal = date('d-m-Y H:i:s', strtotime($row['created_at']));
        
        // Extract browser and OS info
        $user_agent = $row['user_agent'];
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
        
        // Write row
        fputcsv($output, [
            $row_number++,
            $row['log_id'],
            $tanggal,
            $row['nama'] ?? 'System',
            $row['email'] ?? '-',
            ucfirst($row['role'] ?? '-'),
            $row['aktivitas'],
            $row['deskripsi'],
            $row['ip_address'],
            $browser ?: 'Unknown',
            $os ?: 'Unknown'
        ]);
    }
}

// Close output stream
fclose($output);
exit;
?>