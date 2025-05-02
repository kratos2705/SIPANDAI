<?php
// Include connection file
require_once '../config/koneksi.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize response array
$response = [
    'status' => false,
    'message' => '',
    'pengajuan_id' => null
];

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate required fields
    $required_fields = ['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 
                       'status_perkawinan', 'pekerjaan', 'alamat', 'rt', 'rw', 'telepon', 
                       'jenis_dokumen', 'keperluan'];
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $response['message'] = 'Field berikut harus diisi: ' . implode(', ', $missing_fields);
        echo json_encode($response);
        exit;
    }
    
    // Validate NIK format
    $nik = $_POST['nik'];
    if (!preg_match('/^\d{16}$/', $nik)) {
        $response['message'] = 'NIK harus terdiri dari 16 digit angka';
        echo json_encode($response);
        exit;
    }
    
    // Validate phone number
    $telepon = $_POST['telepon'];
    if (!preg_match('/^\d{10,13}$/', str_replace(' ', '', $telepon))) {
        $response['message'] = 'Nomor telepon tidak valid';
        echo json_encode($response);
        exit;
    }
    
    // Check file upload
    if (!isset($_FILES['dokumen_pendukung']) || empty($_FILES['dokumen_pendukung']['name'][0])) {
        $response['message'] = 'Dokumen pendukung harus diunggah';
        echo json_encode($response);
        exit;
    }
    
    // Check user is logged in
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Anda harus login terlebih dahulu';
        echo json_encode($response);
        exit;
    }
    
    // Sanitize user input
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $nik = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $tempat_lahir = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $agama = mysqli_real_escape_string($koneksi, $_POST['agama']);
    $status_perkawinan = mysqli_real_escape_string($koneksi, $_POST['status_perkawinan']);
    $pekerjaan = mysqli_real_escape_string($koneksi, $_POST['pekerjaan']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $rt = mysqli_real_escape_string($koneksi, $_POST['rt']);
    $rw = mysqli_real_escape_string($koneksi, $_POST['rw']);
    $dusun = mysqli_real_escape_string($koneksi, isset($_POST['dusun']) ? $_POST['dusun'] : '');
    $telepon = mysqli_real_escape_string($koneksi, $_POST['telepon']);
    $email = mysqli_real_escape_string($koneksi, isset($_POST['email']) ? $_POST['email'] : '');
    $jenis_dokumen = mysqli_real_escape_string($koneksi, $_POST['jenis_dokumen']);
    $keperluan = mysqli_real_escape_string($koneksi, $_POST['keperluan']);
    $user_id = $_SESSION['user_id'];
    
    // Generate unique application number with better uniqueness
    $date_part = date('Ymd');
    $random_part = substr(md5(uniqid(rand(), true)), 0, 6);
    $nomor_pengajuan = 'DOK-' . $date_part . '-' . $random_part;
    
    // Start a transaction
    mysqli_begin_transaction($koneksi);
    
    try {
        // Update user data if needed
        $update_user_query = "UPDATE users SET 
                            nama = IF('$nama' <> '', '$nama', nama),
                            nik = IF('$nik' <> '', '$nik', nik),
                            alamat = IF('$alamat' <> '', '$alamat RT $rt RW $rw $dusun', alamat),
                            nomor_telepon = IF('$telepon' <> '', '$telepon', nomor_telepon),
                            email = IF('$email' <> '', '$email', email),
                            tanggal_lahir = IF('$tanggal_lahir' <> '', '$tanggal_lahir', tanggal_lahir)
                            WHERE user_id = '$user_id'";
        
        if (!mysqli_query($koneksi, $update_user_query)) {
            throw new Exception("Error updating user data: " . mysqli_error($koneksi));
        }
        
        // Get jenis_id from jenis_dokumen table
        $get_jenis_id_query = "SELECT jenis_id FROM jenis_dokumen WHERE jenis_id = '$jenis_dokumen' LIMIT 1";
        $jenis_result = mysqli_query($koneksi, $get_jenis_id_query);
        
        if (mysqli_num_rows($jenis_result) > 0) {
            $jenis_row = mysqli_fetch_assoc($jenis_result);
            $jenis_id = $jenis_row['jenis_id'];
        } else {
            throw new Exception("Jenis dokumen tidak ditemukan");
        }
        
        // Insert into pengajuan_dokumen table
        $insert_pengajuan_query = "INSERT INTO pengajuan_dokumen (user_id, jenis_id, nomor_pengajuan, catatan, status) 
                                  VALUES ('$user_id', '$jenis_id', '$nomor_pengajuan', '$keperluan', 'diajukan')";
        
        if (!mysqli_query($koneksi, $insert_pengajuan_query)) {
            throw new Exception("Error creating application: " . mysqli_error($koneksi));
        }
        
        $pengajuan_id = mysqli_insert_id($koneksi);
        
        // Insert into riwayat_pengajuan table
        $insert_riwayat_query = "INSERT INTO riwayat_pengajuan (pengajuan_id, status, catatan, changed_by) 
                                VALUES ('$pengajuan_id', 'diajukan', 'Pengajuan dokumen baru', '$user_id')";
        
        if (!mysqli_query($koneksi, $insert_riwayat_query)) {
            throw new Exception("Error creating history: " . mysqli_error($koneksi));
        }
        
        // Handle file uploads
        $fileCount = count($_FILES['dokumen_pendukung']['name']);
        $upload_dir = '../uploads/dokumen_persyaratan/';
        
        // Create directory if not exists
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        $uploadedFiles = [];
        $totalSize = 0;
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        
        // Calculate total size first
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['dokumen_pendukung']['error'][$i] == 0) {
                $totalSize += $_FILES['dokumen_pendukung']['size'][$i];
            }
        }
        
        if ($totalSize > $maxSize) {
            throw new Exception("Total file size exceeds 5MB limit");
        }
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['dokumen_pendukung']['error'][$i] == 0) {
                $fileName = $_FILES['dokumen_pendukung']['name'][$i];
                $fileType = $_FILES['dokumen_pendukung']['type'][$i];
                $fileTmpName = $_FILES['dokumen_pendukung']['tmp_name'][$i];
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($fileType, $allowed_types)) {
                    throw new Exception("File type not allowed: " . $fileName);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = $nik . '_' . $pengajuan_id . '_' . $i . '_' . time() . '.' . $fileExtension;
                $targetFile = $upload_dir . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($fileTmpName, $targetFile)) {
                    // Insert file info into dokumen_persyaratan table
                    $insert_file_query = "INSERT INTO dokumen_persyaratan (pengajuan_id, nama_file, path_file, jenis_persyaratan) 
                                        VALUES ('$pengajuan_id', '" . mysqli_real_escape_string($koneksi, $fileName) . "', 
                                        '" . mysqli_real_escape_string($koneksi, $targetFile) . "', 'pendukung')";
                    
                    if (!mysqli_query($koneksi, $insert_file_query)) {
                        throw new Exception("Error saving file information: " . mysqli_error($koneksi));
                    }
                    
                    $uploadedFiles[] = $fileName;
                } else {
                    throw new Exception("Error uploading file: " . $fileName);
                }
            } else if ($_FILES['dokumen_pendukung']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                throw new Exception("Error with file upload: " . getUploadErrorMessage($_FILES['dokumen_pendukung']['error'][$i]));
            }
        }
        
        // Create notification for admin
        $notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link, created_at) 
                       VALUES (1, 'Pengajuan Dokumen Baru', 'Pengajuan dokumen baru dengan nomor $nomor_pengajuan telah masuk.', 
                       'pengajuan', 'pengajuan-detail.php?id=$pengajuan_id', NOW())";
        
        if (!mysqli_query($koneksi, $notif_query)) {
            // Log error but don't fail the transaction
            error_log("Error creating admin notification: " . mysqli_error($koneksi));
        }
        
        // Create notification for user
        $user_notif_query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link, created_at) 
                          VALUES ('$user_id', 'Pengajuan Berhasil', 'Pengajuan dokumen Anda dengan nomor $nomor_pengajuan telah berhasil dibuat.', 
                          'pengajuan', 'status-pengajuan.php?id=$pengajuan_id', NOW())";
        
        if (!mysqli_query($koneksi, $user_notif_query)) {
            // Log error but don't fail the transaction
            error_log("Error creating user notification: " . mysqli_error($koneksi));
        }
        
        // Commit the transaction
        mysqli_commit($koneksi);
        
        // Set success response
        $response['status'] = true;
        $response['message'] = 'Pengajuan dokumen berhasil disimpan dengan nomor pengajuan: ' . $nomor_pengajuan;
        $response['pengajuan_id'] = $pengajuan_id;
        
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        mysqli_rollback($koneksi);
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Helper function for file upload errors
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "File size exceeds server limit";
        case UPLOAD_ERR_FORM_SIZE:
            return "File size exceeds form limit";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}
?>