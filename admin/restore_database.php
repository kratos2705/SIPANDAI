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
    exit;
}

// Include database connection
require_once '../config/koneksi.php';

// Get current user info
$user_id = $_SESSION['user_id'];

// Check if form was submitted
if (isset($_POST['restore_db']) && isset($_FILES['restore_file'])) {
    $upload_error = false;
    $file = $_FILES['restore_file'];
    
    // Check if file is valid
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = true;
        $_SESSION['error_message'] = 'Terjadi kesalahan saat mengunggah file.';
    }
    
    // Check file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'sql') {
        $upload_error = true;
        $_SESSION['error_message'] = 'Hanya file SQL yang diperbolehkan.';
    }
    
    // Check file size (max 100MB)
    if ($file['size'] > 100 * 1024 * 1024) {
        $upload_error = true;
        $_SESSION['error_message'] = 'Ukuran file terlalu besar. Maksimal 100MB.';
    }
    
    if (!$upload_error) {
        // Create temporary file
        $temp_file = '../temp/' . time() . '_' . $file['name'];
        
        // Create temp directory if it doesn't exist
        if (!file_exists('../temp')) {
            mkdir('../temp', 0755, true);
        }
        
        // Move uploaded file to temp directory
        if (move_uploaded_file($file['tmp_name'], $temp_file)) {
            // Database configuration
            $dbhost = $db_host;
            $dbuser = $db_user;
            $dbpass = $db_pass;
            $dbname = $db_name;
            
            // Command for mysql import
            $command = "mysql -h$dbhost -u$dbuser -p$dbpass $dbname < $temp_file";
            
            // Execute the command
            $output = array();
            exec($command, $output, $return_var);
            
            // Delete temporary file
            unlink($temp_file);
            
            if ($return_var === 0) {
                // Log the restore activity
                $aktivitas = 'Restore database dari file: ' . $file['name'];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                
                $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) 
                              VALUES ('$user_id', '$aktivitas', '$ip_address', '$user_agent')";
                mysqli_query($koneksi, $log_query);
                
                $_SESSION['success_message'] = 'Database berhasil dipulihkan dari file backup.';
            } else {
                $_SESSION['error_message'] = 'Gagal memulihkan database. Periksa file dan coba lagi.';
            }
        } else {
            $_SESSION['error_message'] = 'Gagal mengunggah file. Silakan coba lagi.';
        }
    }
    
    // Redirect back to the settings page
    redirect('pengaturan.php');
} else {
    // If accessed directly without form submission
    $_SESSION['error_message'] = 'Akses tidak valid.';
    redirect('pengaturan.php');
}