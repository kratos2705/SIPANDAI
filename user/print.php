
<?php
// Include necessary functions and components
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login terlebih dahulu';
    header('Location: ../index.php');
    exit;
}

// Check if file parameter is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    $_SESSION['error'] = 'Parameter file tidak valid';
    header('Location: dashboard.php');
    exit;
}

// Sanitize file path
$file_path = $_GET['file'];

// Determine if the file is in uploads directory
if (strpos($file_path, '../uploads/hasil/') !== 0) {
    // Jika tidak dimulai dengan uploads/hasil/, asumsi ini adalah path relatif
    // yang mungkin perlu dikoreksi
    $_SESSION['error'] = 'Akses file tidak valid';
    header('Location: dashboard.php');
    exit;
}

// Get the actual file path on server
// Karena file ada di uploads/hasil/ dan saat ini kita di user/print.php
// kita perlu naik satu folder menggunakan ../
$uploads_dir = realpath('../uploads/hasil/');
if ($actual_path === false || strpos($actual_path, $uploads_dir) !== 0) {
    $_SESSION['error'] = 'Akses file tidak valid';
    header('Location: dashboard.php');
    exit;
}
// Check if file exists
if (!file_exists($actual_path)) {
    $_SESSION['error'] = 'File tidak ditemukan';
    header('Location: dashboard.php');
    exit;
}

// Get file information
$file_name = basename($actual_path);
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Check if file is PDF (we'll only support printing PDFs directly)
if ($file_extension === 'pdf') {
    // Set header to display PDF inline (for printing)
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $file_name . '"');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read and output file
    readfile($actual_path);
    exit;
} else {
    // Redirect for non-PDF files (they can't be directly printed in browser)
    $_SESSION['error'] = 'Hanya file PDF yang dapat dicetak langsung';
    header('Location: dashboard.php');
    exit;
}
?>
