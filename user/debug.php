<?php
// File debug yang dapat ditempatkan di direktori root atau user untuk mendiagnosis masalah
// Tempatkan file ini di direktori yang sama dengan file pembayaran_detail.php

// Mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Diagnostik SIPANDAI</h1>";

// Tampilkan informasi server dan jalur
echo "<h2>Informasi Server:</h2>";
echo "<pre>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "</pre>";

// Periksa file yang penting
echo "<h2>Pemeriksaan File:</h2>";
echo "<ul>";

// Daftar file yang akan diperiksa keberadaannya
$files_to_check = [
    "pembayaran_detail.php",
    "pembayaran-detail.php",
    "../includes/header.php",
    "../includes/footer.php",
    "../includes/functions.php",
    "../config/koneksi.php"
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<li style='color:green'>File <strong>$file</strong> ditemukan</li>";
    } else {
        echo "<li style='color:red'>File <strong>$file</strong> TIDAK DITEMUKAN</li>";
    }
}
echo "</ul>";

// Periksa sesi
echo "<h2>Data Sesi:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Periksa koneksi database jika diperlukan
echo "<h2>Tes Koneksi Database:</h2>";
try {
    if (file_exists("../config/koneksi.php")) {
        include_once "../config/koneksi.php";
        if (isset($koneksi)) {
            echo "<p style='color:green'>Koneksi database berhasil</p>";
            
            // Periksa tabel yang terkait
            $tables = ["pembayaran_retribusi", "tagihan_retribusi", "jenis_retribusi", "users"];
            echo "<h3>Status Tabel:</h3>";
            echo "<ul>";
            foreach ($tables as $table) {
                $result = mysqli_query($koneksi, "SHOW TABLES LIKE '$table'");
                if (mysqli_num_rows($result) > 0) {
                    echo "<li style='color:green'>Tabel <strong>$table</strong> ditemukan</li>";
                } else {
                    echo "<li style='color:red'>Tabel <strong>$table</strong> TIDAK DITEMUKAN</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red'>Koneksi database gagal: variabel \$koneksi tidak tersedia</p>";
        }
    } else {
        echo "<p style='color:red'>Tidak dapat memuat file koneksi database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Tampilkan parameter GET dan POST
echo "<h2>Parameter GET:</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>Parameter POST:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

// Instruksi untuk fix
echo "<h2>Kemungkinan Solusi:</h2>";
echo "<ol>";
echo "<li>Pastikan nama file konsisten (gunakan underscore '_' atau hyphen '-')</li>";
echo "<li>Periksa path untuk semua include dan require</li>";
echo "<li>Pastikan direktori uploads/payments sudah dibuat dan memiliki hak akses yang tepat</li>";
echo "<li>Cek error log PHP untuk detail lebih lanjut</li>";
echo "</ol>";

echo "<p>Error log PHP biasanya berada di: ";
echo ini_get('error_log') ?: "Path default server";
echo "</p>";
?>