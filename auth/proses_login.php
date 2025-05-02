<?php
session_start();

// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get login credentials
    $login = sanitizeInput($_POST['loginEmail']);
    $password = $_POST['loginPassword'];
    $remember = isset($_POST['rememberMe']) ? true : false;
    
    // Validate input
    if (empty($login) || empty($password)) {
        $_SESSION['login_error'] = 'Email/No. HP dan kata sandi harus diisi';
        redirect('../index.php?login_status=failed');
        exit;
    }
    
    // Check if login is email or phone number
    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $condition = "email = ?";
        $param_type = "s";
        $param_value = $login;
    } else {
        $condition = "nomor_telepon = ?";
        $param_type = "s";
        $param_value = $login;
    }
    
    // Query to check user - Use prepared statement for better security
    $query = "SELECT user_id, nama, password, role, active FROM users WHERE $condition LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, $param_type, $param_value);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Check if account is active
        if (!$user['active']) {
            $_SESSION['login_error'] = 'Akun Anda tidak aktif. Silakan hubungi admin.';
            redirect('../index.php?login_status=failed');
            exit;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Clear any existing session data first
            session_unset();
            session_regenerate_id(true); // Generate new session ID for security
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Set remember me cookie if checked
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30); // 30 days
                
                // Store token in database with prepared statement
                $token_query = "INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, FROM_UNIXTIME(?))";
                $token_stmt = mysqli_prepare($koneksi, $token_query);
                mysqli_stmt_bind_param($token_stmt, "isi", $user['user_id'], $token, $expires);
                mysqli_stmt_execute($token_stmt);
                
                // Set cookie
                setcookie('remember_token', $token, $expires, '/', '', true, true); // More secure cookie
            }
            
            // Update last login time
            $update_query = "UPDATE users SET updated_at = NOW() WHERE user_id = ?";
            $update_stmt = mysqli_prepare($koneksi, $update_query);
            mysqli_stmt_bind_param($update_stmt, "i", $user['user_id']);
            mysqli_stmt_execute($update_stmt);
            
            // Log activity
            $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, 'Login', ?)";
            $log_stmt = mysqli_prepare($koneksi, $log_query);
            mysqli_stmt_bind_param($log_stmt, "is", $user['user_id'], $_SERVER['REMOTE_ADDR']);
            mysqli_stmt_execute($log_stmt);
            
            // Set success message
            $_SESSION['login_success'] = 'Login berhasil. Selamat datang, ' . $user['nama'] . '!';
            
            // Redirect based on role with strict access control
            if ($user['role'] == 'admin' || $user['role'] == 'kepala_desa') {
                $_SESSION['admin_access'] = true; // Special flag for admin access
                $_SESSION['user_access'] = false; // Ensure user access is false
                redirect('../admin/dashboard.php?login_status=success');
                exit;
            } else if ($user['role'] == 'warga') {
                $_SESSION['user_access'] = true; // Special flag for user access
                $_SESSION['admin_access'] = false; // Ensure admin access is false
                redirect('../user/dashboard.php?login_status=success');
                exit;
            } else {
                // Fallback for undefined roles
                $_SESSION['login_error'] = 'Peran pengguna tidak valid';
                // Clear session data for security
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['login_error'] = 'Peran pengguna tidak valid';
                redirect('../index.php?login_status=failed');
                exit;
            }
        } else {
            $_SESSION['login_error'] = 'Kata sandi salah';
            redirect('../index.php?login_status=failed');
            exit;
        }
    } else {
        $_SESSION['login_error'] = 'Akun tidak ditemukan';
        redirect('../index.php?login_status=failed');
        exit;
    }
} else {
    // If accessed directly without POST
    redirect('../index.php');
    exit;
}