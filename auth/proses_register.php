<?php
session_start();

// Include necessary files
require_once '../config/koneksi.php';
require_once '../includes/functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $nama = sanitizeInput($_POST['nama']);
    $nik = sanitizeInput($_POST['nik']);
    $email = sanitizeInput($_POST['email']);
    $nomor_telepon = sanitizeInput($_POST['nomor_telepon']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Save data for form repopulation in case of error
    $_SESSION['register_data'] = [
        'nama' => $nama,
        'nik' => $nik,
        'email' => $email,
        'nomor_telepon' => $nomor_telepon
    ];
    
    // Validate input
    $errors = [];
    
    if (empty($nama)) {
        $errors[] = 'Nama lengkap harus diisi';
    }
    
    if (empty($nik)) {
        $errors[] = 'NIK harus diisi';
    } elseif (strlen($nik) != 16 || !ctype_digit($nik)) {
        $errors[] = 'NIK harus 16 digit angka';
    } else {
        // Check if NIK already exists
        $query = "SELECT user_id FROM users WHERE nik = '$nik'";
        $result = mysqli_query($koneksi, $query);
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'NIK sudah terdaftar';
        }
    }
    
    if (empty($email)) {
        $errors[] = 'Email harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    } else {
        // Check if email already exists
        $query = "SELECT user_id FROM users WHERE email = '$email'";
        $result = mysqli_query($koneksi, $query);
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'Email sudah terdaftar';
        }
    }
    
    if (empty($nomor_telepon)) {
        $errors[] = 'Nomor HP harus diisi';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $nomor_telepon)) {
        $errors[] = 'Nomor HP tidak valid (10-15 digit)';
    } else {
        // Check if phone number already exists
        $query = "SELECT user_id FROM users WHERE nomor_telepon = '$nomor_telepon'";
        $result = mysqli_query($koneksi, $query);
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'Nomor HP sudah terdaftar';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Kata sandi harus diisi';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok';
    }
    
    if (!isset($_POST['terms'])) {
        $errors[] = 'Anda harus menyetujui Ketentuan Layanan dan Kebijakan Privasi';
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        redirect('../index.php');
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Create the user - set default role to 'warga' based on the database schema
    $query = "INSERT INTO users (nama, nik, email, nomor_telepon, password, role, active, created_at)
              VALUES ('$nama', '$nik', '$email', '$nomor_telepon', '$hashed_password', 'warga', TRUE, NOW())";
    
    if (mysqli_query($koneksi, $query)) {
        // Registration successful
        unset($_SESSION['register_data']);
        
        // Get the new user ID
        $user_id = mysqli_insert_id($koneksi);
        
        // Create welcome notification
        $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, created_at)
                       VALUES ('$user_id', 'Selamat Datang di SIPANDAI', 
                       'Terima kasih telah mendaftar di Sistem Informasi Pelayanan Administrasi Desa. Anda dapat mulai menggunakan layanan pengajuan dokumen dan layanan lainnya.', 
                       NOW())";
        mysqli_query($koneksi, $notif_query);
        
        // Set success message
        $_SESSION['register_success'] = 'Registrasi berhasil! Silakan login menggunakan email dan kata sandi Anda.';
        redirect('../index.php');
    } else {
        // Registration failed
        $_SESSION['register_errors'] = ['Registrasi gagal. Silakan coba lagi nanti. Error: ' . mysqli_error($koneksi)];
        redirect('../index.php');
    }
} else {
    // If accessed directly without POST
    redirect('../index.php');
}
?>