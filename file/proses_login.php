<?php
// Start session
session_start();

// Include database connection
require_once 'koneksi.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $identifier = mysqli_real_escape_string($koneksi, $_POST['loginEmail']);
    $password = $_POST['loginPassword'];
    $remember = isset($_POST['rememberMe']) ? true : false;
    
    // Check if identifier is email or phone number
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        // Identifier is email
        $query = "SELECT user_id, nik, nama, email, password, role, active FROM users WHERE email = '$identifier'";
    } else {
        // Identifier is phone number
        $query = "SELECT user_id, nik, nama, email, password, role, active FROM users WHERE nomor_telepon = '$identifier'";
    }
    
    $result = mysqli_query($koneksi, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify if account is active
        if (!$user['active']) {
            $_SESSION['login_error'] = "Akun Anda tidak aktif. Silakan hubungi administrator.";
            header("Location: index.php");
            exit;
        }
        
        // Verify password (assuming password is hashed with password_hash())
        if (password_verify($password, $user['password'])) {
            // Password is correct, create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['nik'] = $user['nik'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Set remember me cookie if selected (30 days)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30); // 30 days
                
                // Store token in database
                $user_id = $user['user_id'];
                $token_hash = password_hash($token, PASSWORD_DEFAULT);
                
                // Delete any existing remember tokens for this user
                $delete_query = "DELETE FROM user_tokens WHERE user_id = $user_id";
                mysqli_query($koneksi, $delete_query);
                
                // Insert new token
                $insert_query = "INSERT INTO user_tokens (user_id, token, expires) VALUES ($user_id, '$token_hash', FROM_UNIXTIME($expires))";
                mysqli_query($koneksi, $insert_query);
                
                // Set cookies
                setcookie("remember_user", $user['email'], $expires, "/", "", true, true);
                setcookie("remember_token", $token, $expires, "/", "", true, true);
            }
            
            // Log login activity
            $user_id = $user['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) 
                          VALUES ($user_id, 'Login', '$ip', '$user_agent')";
            mysqli_query($koneksi, $log_query);
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;
                case 'kepala_desa':
                    header("Location: kepala_desa/dashboard.php");
                    break;
                case 'warga':
                    header("Location: index.php");
                    break;
                default:
                    header("Location: index.php");
                    break;
            }
            exit;
        } else {
            // Invalid password
            $_SESSION['login_error'] = "Email/Nomor HP atau kata sandi salah!";
            header("Location: index.php");
            exit;
        }
    } else {
        // User not found
        $_SESSION['login_error'] = "Email/Nomor HP atau kata sandi salah!";
        header("Location: index.php");
        exit;
    }
} else {
    // If not a POST request, redirect to home
    header("Location: index.php");
    exit;
}
?>