<?php
/**
 * Admin-specific functions
 */

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if user is staff
 * 
 * @return bool True if user is staff, false otherwise
 */
function isStaff() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'petugas');
}

/**
 * Redirect if user is not allowed to access the page
 * 
 * @param bool $adminOnly Whether the page is admin only
 * @return void
 */
function requireAccess($adminOnly = false) {
    if (!isLoggedIn()) {
        $_SESSION['login_error'] = 'Anda harus login terlebih dahulu';
        redirect('../index.php');
    }
    
    if ($adminOnly && !isAdmin()) {
        redirect('../index.php');
    }
    
    if (!$adminOnly && !isStaff()) {
        redirect('../index.php');
    }
}

/**
 * Log admin activity
 * 
 * @param string $activity Activity description
 * @param string $module Module name
 * @return bool True if logging successful, false otherwise
 */
function logActivity($activity, $module = 'general') {
    global $koneksi;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $activity = sanitizeInput($activity);
    $module = sanitizeInput($module);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO log_aktivitas (user_id, aktivitas, modul, ip_address, waktu)
              VALUES ('$user_id', '$activity', '$module', '$ip_address', NOW())";
    
    return mysqli_query($koneksi, $query);
}

/**
 * Get application statistics
 * 
 * @param string $period Period ('today', 'week', 'month', 'year')
 * @return array Statistics data
 */
function getApplicationStats($period = 'month') {
    global $koneksi;
    
    switch ($period) {
        case 'today':
            $where = "WHERE DATE(tanggal_pengajuan) = CURDATE()";
            break;
        case 'week':
            $where = "WHERE YEARWEEK(tanggal_pengajuan, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'year':
            $where = "WHERE YEAR(tanggal_pengajuan) = YEAR(CURDATE())";
            break;
        case 'month':
        default:
            $where = "WHERE MONTH(tanggal_pengajuan) = MONTH(CURDATE()) AND YEAR(tanggal_pengajuan) = YEAR(CURDATE())";
    }
    
    $stats = [
        'total' => 0,
        'diajukan' => 0,
        'verifikasi' => 0,
        'proses' => 0,
        'selesai' => 0,
        'ditolak' => 0
    ];
    
    // Get total applications
    $query = "SELECT COUNT(*) as total FROM pengajuan_dokumen $where";
    $result = mysqli_query($koneksi, $query);
    if ($result) {
        $stats['total'] = mysqli_fetch_assoc($result)['total'];
    }
    
    // Get counts by status
    $statuses = ['diajukan', 'verifikasi', 'proses', 'selesai', 'ditolak'];
    
    foreach ($statuses as $status) {
        $query = "SELECT COUNT(*) as count FROM pengajuan_dokumen 
                 $where AND status = '$status'";
        $result = mysqli_query($koneksi, $query);
        if ($result) {
            $stats[$status] = mysqli_fetch_assoc($result)['count'];
        }
    }
    
    return $stats;
}

/**
 * Get application types statistics
 * 
 * @param string $period Period ('today', 'week', 'month', 'year')
 * @return array Statistics data by document type
 */
function getDocumentTypeStats($period = 'month') {
    global $koneksi;
    
    switch ($period) {
        case 'today':
            $where = "WHERE DATE(pd.tanggal_pengajuan) = CURDATE()";
            break;
        case 'week':
            $where = "WHERE YEARWEEK(pd.tanggal_pengajuan, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'year':
            $where = "WHERE YEAR(pd.tanggal_pengajuan) = YEAR(CURDATE())";
            break;
        case 'month':
        default:
            $where = "WHERE MONTH(pd.tanggal_pengajuan) = MONTH(CURDATE()) AND YEAR(pd.tanggal_pengajuan) = YEAR(CURDATE())";
    }
    
    $query = "SELECT jd.nama_dokumen, COUNT(*) as count 
              FROM pengajuan_dokumen pd
              JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id
              $where
              GROUP BY pd.jenis_id
              ORDER BY count DESC";
    
    $result = mysqli_query($koneksi, $query);
    $stats = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stats[] = $row;
        }
    }
    
    return $stats;
}

/**
 * Generate a report file
 * 
 * @param string $reportType Type of report ('applications', 'users', 'statistics')
 * @param array $params Additional parameters for the report
 * @return string|bool Path to the generated file or false on failure
 */
function generateReport($reportType, $params = []) {
    global $koneksi;
    
    // Define the report directory
    $reportDir = '../reports/';
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }
    
    // Generate report file name
    $timestamp = date('YmdHis');
    $fileName = "{$reportType}_{$timestamp}.csv";
    $filePath = $reportDir . $fileName;
    
    // Create file handle
    $file = fopen($filePath, 'w');
    if (!$file) {
        return false;
    }
    
    switch ($reportType) {
        case 'applications':
            // Headers
            fputcsv($file, ['ID', 'Nomor Pengajuan', 'Nama Pemohon', 'Jenis Dokumen', 'Tanggal Pengajuan', 'Status']);
            
            // Build the query
            $query = "SELECT pd.pengajuan_id, pd.nomor_pengajuan, u.nama, jd.nama_dokumen, 
                      pd.tanggal_pengajuan, pd.status
                      FROM pengajuan_dokumen pd
                      JOIN users u ON pd.user_id = u.user_id
                      JOIN jenis_dokumen jd ON pd.jenis_id = jd.jenis_id";
            
            // Add any filters from params
            if (isset($params['status']) && !empty($params['status'])) {
                $status = sanitizeInput($params['status']);
                $query .= " WHERE pd.status = '$status'";
            }
            
            if (isset($params['date_from']) && !empty($params['date_from'])) {
                $dateFrom = sanitizeInput($params['date_from']);
                $query .= (strpos($query, 'WHERE') !== false) ? " AND" : " WHERE";
                $query .= " pd.tanggal_pengajuan >= '$dateFrom'";
            }
            
            if (isset($params['date_to']) && !empty($params['date_to'])) {
                $dateTo = sanitizeInput($params['date_to']);
                $query .= (strpos($query, 'WHERE') !== false) ? " AND" : " WHERE";
                $query .= " pd.tanggal_pengajuan <= '$dateTo'";
            }
            
            $query .= " ORDER BY pd.tanggal_pengajuan DESC";
            
            // Execute the query and write results
            $result = mysqli_query($koneksi, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    fputcsv($file, [
                        $row['pengajuan_id'],
                        $row['nomor_pengajuan'],
                        $row['nama'],
                        $row['nama_dokumen'],
                        $row['tanggal_pengajuan'],
                        $row['status']
                    ]);
                }
            }
            break;
            
        case 'users':
            // Headers
            fputcsv($file, ['ID', 'Nama', 'Email', 'NIK', 'Nomor Telepon', 'Tanggal Registrasi', 'Status']);
            
            // Build the query
            $query = "SELECT user_id, nama, email, nik, nomor_telepon, tanggal_registrasi, status
                      FROM users";
            
            // Add any filters from params
            if (isset($params['status']) && !empty($params['status'])) {
                $status = sanitizeInput($params['status']);
                $query .= " WHERE status = '$status'";
            }
            
            $query .= " ORDER BY tanggal_registrasi DESC";
            
            // Execute the query and write results
            $result = mysqli_query($koneksi, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    fputcsv($file, [
                        $row['user_id'],
                        $row['nama'],
                        $row['email'],
                        $row['nik'],
                        $row['nomor_telepon'],
                        $row['tanggal_registrasi'],
                        $row['status']
                    ]);
                }
            }
            break;
            
        default:
            fclose($file);
            return false;
    }
    
    fclose($file);
    
    // Log the activity
    logActivity("Generated report: $reportType", 'reports');
    
    return $fileName;
}