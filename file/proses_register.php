<?php
// Start session
session_start();

// Include database connection
require_once 'koneksi.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $nik = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    $nomor_telepon = mysqli_real_escape_string($koneksi, $_POST['nomor_telepon']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate data
    $errors = [];
    
    // Validate NIK (should be 16 digits)
    if (!preg_match('/^\d{16}$/', $nik)) {
        $errors[] = "NIK harus terdiri dari 16 digit angka.";
    }
    
    // Check if NIK already exists
    $check_nik = mysqli_query($koneksi, "SELECT user_id FROM users WHERE nik = '$nik'");
    if (mysqli_num_rows($check_nik) > 0) {
        $errors[] = "NIK sudah terdaftar dalam sistem.";
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    
    // Check if email already exists
    $check_email = mysqli_query($koneksi, "SELECT user_id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $errors[] = "Email sudah terdaftar dalam sistem.";
    }
    
    // Validate phone number (simple validation)
    if (!preg_match('/^[0-9]{10,15}$/', $nomor_telepon)) {
        $errors[] = "Nomor telepon harus terdiri dari 10-15 digit angka.";
    }
    
    // Check if phone already exists
    $check_phone = mysqli_query($koneksi, "SELECT user_id FROM users WHERE nomor_telepon = '$nomor_telepon'");
    if (mysqli_num_rows($check_phone) > 0) {
        $errors[] = "Nomor telepon sudah terdaftar dalam sistem.";
    }
    
    // Validate password (minimum 8 characters, at least one number and one letter)
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
        $errors[] = "Kata sandi harus minimal 8 karakter dan mengandung huruf dan angka.";
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Konfirmasi kata sandi tidak sesuai.";
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        $_SESSION['register_data'] = [
            'nama' => $nama,
            'nik' => $nik,
            'email' => $email,
            'nomor_telepon' => $nomor_telepon
        ];
        header("Location: index.php");
        exit;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user into database (as 'warga' role by default)
    $insert_query = "INSERT INTO users (nik, nama, email, password, nomor_telepon, role, active) 
                     VALUES ('$nik', '$nama', '$email', '$hashed_password', '$nomor_telepon', 'warga', TRUE)";
    
    if (mysqli_query($koneksi, $insert_query)) {
        // Registration successful
        $user_id = mysqli_insert_id($koneksi);
        
        // Log registration activity
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent) 
                      VALUES ($user_id, 'Registrasi Akun', '$ip', '$user_agent')";
        mysqli_query($koneksi, $log_query);
        
        // Create welcome notification
        $welcome_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis) 
                          VALUES ($user_id, 'Selamat Datang di SIPANDAI', 
                                 'Terima kasih telah mendaftar di sistem SIPANDAI. Anda sekarang dapat mengakses berbagai layanan administrasi desa secara online.',
                                 'info')";
        mysqli_query($koneksi, $welcome_query);
        
        // Set success message
        $_SESSION['register_success'] = "Pendaftaran berhasil! Silakan login dengan akun yang telah dibuat.";
        header("Location: index.php");
        exit;
    } else {
        // Database error
        $_SESSION['register_errors'] = ["Terjadi kesalahan saat mendaftar. Silakan coba lagi nanti."];
        $_SESSION['register_data'] = [
            'nama' => $nama,
            'nik' => $nik,
            'email' => $email,
            'nomor_telepon' => $nomor_telepon
        ];
        header("Location: index.php");
        exit;
    }
} else {
    // If not a POST request, redirect to home
    header("Location: index.php");
    exit;
}
?>