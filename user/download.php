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

// Sanitize file path - remove any potential directory traversal attempts
$file_name = basename($_GET['file']);

// Get the actual file path on server
$actual_path = realpath('../uploads/hasil/' . $file_name);

// Validate that the file is within the allowed directory
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
$file_size = filesize($actual_path);
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Set appropriate Content-Type based on file extension
$content_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain'
];

$content_type = isset($content_types[$file_extension]) ? $content_types[$file_extension] : 'application/octet-stream';

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $file_size);

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Read and output file
readfile($actual_path);
exit;
?>