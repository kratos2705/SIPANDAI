<?php

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sipandai_db';

// Create connection
$koneksi = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($koneksi, "utf8");