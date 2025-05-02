<?php
// Include database connection
require_once '../config/koneksi.php';

// Initialize response array
$response = [
    'status' => false,
    'message' => '',
    'persyaratan' => '',
    'estimasi_waktu' => '',
    'deskripsi' => ''
];

// Get jenis_id parameter
$jenis_id = isset($_GET['jenis_id']) ? intval($_GET['jenis_id']) : 0;

// Validate jenis_id
if ($jenis_id <= 0) {
    $response['message'] = 'Parameter jenis dokumen tidak valid';
    echo json_encode($response);
    exit;
}

// Query to get document information
$query = "SELECT nama_dokumen, deskripsi, persyaratan, estimasi_waktu 
          FROM jenis_dokumen 
          WHERE jenis_id = $jenis_id 
          AND is_active = TRUE";
$result = mysqli_query($koneksi, $query);

// Check if query was successful
if (!$result) {
    $response['message'] = 'Error: ' . mysqli_error($koneksi);
    echo json_encode($response);
    exit;
}

// Check if document type exists
if (mysqli_num_rows($result) == 0) {
    $response['message'] = 'Jenis dokumen tidak ditemukan atau tidak aktif';
    echo json_encode($response);
    exit;
}

// Get document data
$dokumen = mysqli_fetch_assoc($result);

// Format persyaratan in HTML
$persyaratan_html = '';
if (!empty($dokumen['persyaratan'])) {
    $persyaratan = explode("\n", $dokumen['persyaratan']);
    
    $persyaratan_html = '<div class="persyaratan-content">';
    $persyaratan_html .= '<p><strong>' . htmlspecialchars($dokumen['nama_dokumen']) . '</strong></p>';
    $persyaratan_html .= '<ul>';
    
    foreach ($persyaratan as $item) {
        $item = trim($item);
        if (!empty($item)) {
            $persyaratan_html .= '<li>' . htmlspecialchars($item) . '</li>';
        }
    }
    
    $persyaratan_html .= '</ul>';
    
    if (!empty($dokumen['estimasi_waktu'])) {
        $persyaratan_html .= '<p><strong>Estimasi waktu:</strong> ' . $dokumen['estimasi_waktu'] . ' hari kerja</p>';
    }
    
    $persyaratan_html .= '</div>';
} else {
    // If no specific requirements, show default ones
    $persyaratan_html = '<div class="persyaratan-content">';
    $persyaratan_html .= '<p><strong>' . htmlspecialchars($dokumen['nama_dokumen']) . '</strong></p>';
    $persyaratan_html .= '<ul>';
    $persyaratan_html .= '<li>Fotokopi KTP</li>';
    $persyaratan_html .= '<li>Fotokopi Kartu Keluarga</li>';
    $persyaratan_html .= '<li>Pas Foto 3x4 (2 lembar)</li>';
    $persyaratan_html .= '<li>Surat Pengantar RT/RW</li>';
    $persyaratan_html .= '</ul>';
    
    if (!empty($dokumen['estimasi_waktu'])) {
        $persyaratan_html .= '<p><strong>Estimasi waktu:</strong> ' . $dokumen['estimasi_waktu'] . ' hari kerja</p>';
    }
    
    $persyaratan_html .= '</div>';
}

// Set response data
$response['status'] = true;
$response['message'] = 'Data dokumen berhasil ditemukan';
$response['persyaratan'] = $persyaratan_html;
$response['estimasi_waktu'] = $dokumen['estimasi_waktu'];
$response['deskripsi'] = $dokumen['deskripsi'];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>