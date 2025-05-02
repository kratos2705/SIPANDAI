<?php
/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has admin role
 * 
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'kepala_desa');
}

/**
 * Check if user has admin access privileges
 * 
 * @return bool True if user has admin access, false otherwise
 */
function hasAdminAccess() {
    return isLoggedIn() && isAdmin() && isset($_SESSION['admin_access']) && $_SESSION['admin_access'] === true;
}

/**
 * Check if user has user access privileges
 * 
 * @return bool True if user has user access, false otherwise
 */
function hasUserAccess() {
    return isLoggedIn() && $_SESSION['user_role'] === 'warga' && isset($_SESSION['user_access']) && $_SESSION['user_access'] === true;
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has at least one of the specified roles, false otherwise
 */
function hasRole($roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}
/**
 * Redirect to specified URL
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if current page matches the given filename
 * 
 * @param string $page Page filename to check against
 * @return string 'active' if current page matches, empty string otherwise
 */
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}

/**
 * Format date to Indonesia format
 * 
 * @param string $date Date string in MySQL format (Y-m-d)
 * @param bool $withTime Whether to include time in the output
 * @return string Formatted date
 */
function formatDateIndo($date, $withTime = false) {
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];
    
    $timestamp = strtotime($date);
    
    if ($withTime) {
        $format = 'l, j F Y H:i';
    } else {
        $format = 'l, j F Y';
    }
    
    $formatted = date($format, $timestamp);
    $formatted = strtr($formatted, $hari);
    $formatted = strtr($formatted, $bulan);
    
    return $formatted;
}

/**
 * Clean and sanitize input data
 * 
 * @param string $data Data to be sanitized
 * @param bool $allowHtml Whether to allow HTML tags
 * @return string Sanitized data
 */
function sanitizeInput($data, $allowHtml = false) {
    global $koneksi;
    
    $data = trim($data);
    
    if (!$allowHtml) {
        $data = strip_tags($data);
    }
    
    if ($koneksi) {
        $data = mysqli_real_escape_string($koneksi, $data);
    }
    
    return $data;
}

/**
 * Generate application status badge HTML
 * 
 * @param string $status Application status
 * @return string HTML for status badge
 */
function getStatusBadge($status) {
    $statusClass = '';
    $statusText = '';
    
    switch ($status) {
        case 'diajukan':
            $statusClass = 'status-pending';
            $statusText = 'Menunggu';
            break;
        case 'verifikasi':
            $statusClass = 'status-processing';
            $statusText = 'Verifikasi';
            break;
        case 'proses':
            $statusClass = 'status-processing';
            $statusText = 'Diproses';
            break;
        case 'selesai':
            $statusClass = 'status-completed';
            $statusText = 'Selesai';
            break;
        case 'ditolak':
            $statusClass = 'status-rejected';
            $statusText = 'Ditolak';
            break;
        default:
            $statusClass = 'status-pending';
            $statusText = 'Menunggu';
    }
    
    return '<span class="status ' . $statusClass . '">' . $statusText . '</span>';
}

/**
 * Generate random application number
 * 
 * @return string Random application number with format YYYY-MM-XXXXX
 */
function generateApplicationNumber() {
    $year = date('Y');
    $month = date('m');
    $random = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    return $year . '-' . $month . '-' . $random;
}

/**
 * Check if file is allowed to be uploaded
 * 
 * @param string $filename Filename to check
 * @param array $allowedExtensions Array of allowed file extensions
 * @return bool True if file is allowed, false otherwise
 */
function isFileAllowed($filename, $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf']) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExtensions);
}

/**
 * Truncate text to specified length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length before truncation
 * @param string $suffix Suffix to add when text is truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}