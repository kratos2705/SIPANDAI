<?php
// Include connection file
require_once 'koneksi.php';

// Initialize response array
$response = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Get search parameter
$search_type = isset($_GET['type']) ? $_GET['type'] : '';
$search_value = isset($_GET['value']) ? $_GET['value'] : '';

// Validate parameters
if (empty($search_type) || empty($search_value)) {
    $response['message'] = 'Parameter pencarian tidak valid';
    echo json_encode($response);
    exit;
}

// Sanitize input
$search_value = mysqli_real_escape_string($koneksi, $search_value);

// Prepare query based on search type
$query = "";
if ($search_type === 'id') {
    // Search by pengajuan_id
    $pengajuan_id = intval($search_value);
    $query = "SELECT p.pengajuan_id, p.nomor_pengajuan, p.tanggal_pengajuan, p.status, p.catatan, p.tanggal_selesai,
                j.nama_dokumen, 
                u.nama, u.nik, u.alamat, u.nomor_telepon, u.email
              FROM pengajuan_dokumen p
              JOIN users u ON p.user_id = u.user_id
              JOIN jenis_dokumen j ON p.jenis_id = j.jenis_id
              WHERE p.pengajuan_id = $pengajuan_id";
} elseif ($search_type === 'nomor') {
    // Search by nomor_pengajuan
    $query = "SELECT p.pengajuan_id, p.nomor_pengajuan, p.tanggal_pengajuan, p.status, p.catatan, p.tanggal_selesai,
                j.nama_dokumen, 
                u.nama, u.nik, u.alamat, u.nomor_telepon, u.email
              FROM pengajuan_dokumen p
              JOIN users u ON p.user_id = u.user_id
              JOIN jenis_dokumen j ON p.jenis_id = j.jenis_id
              WHERE p.nomor_pengajuan = '$search_value'";
} elseif ($search_type === 'nik') {
    // Search by NIK
    $query = "SELECT p.pengajuan_id, p.nomor_pengajuan, p.tanggal_pengajuan, p.status, p.catatan, p.tanggal_selesai,
                j.nama_dokumen, 
                u.nama, u.nik, u.alamat, u.nomor_telepon, u.email
              FROM pengajuan_dokumen p
              JOIN users u ON p.user_id = u.user_id
              JOIN jenis_dokumen j ON p.jenis_id = j.jenis_id
              WHERE u.nik = '$search_value'
              ORDER BY p.tanggal_pengajuan DESC";
} else {
    $response['message'] = 'Tipe pencarian tidak valid';
    echo json_encode($response);
    exit;
}

// Execute query
$result = mysqli_query($koneksi, $query);

// Check if query was successful
if (!$result) {
    $response['message'] = 'Error: ' . mysqli_error($koneksi);
    echo json_encode($response);
    exit;
}

// Check if any results found
if (mysqli_num_rows($result) == 0) {
    $response['message'] = 'Data pengajuan tidak ditemukan';
    echo json_encode($response);
    exit;
}

// If searching by NIK, we might get multiple results
// For this example, we'll just take the most recent one
$pengajuan = mysqli_fetch_assoc($result);
$pengajuan_id = $pengajuan['pengajuan_id'];

// Get timeline data
$timeline_query = "SELECT status, catatan, tanggal_perubahan 
                   FROM riwayat_pengajuan 
                   WHERE pengajuan_id = $pengajuan_id 
                   ORDER BY tanggal_perubahan ASC";
$timeline_result = mysqli_query($koneksi, $timeline_query);

$timeline = [];
if ($timeline_result && mysqli_num_rows($timeline_result) > 0) {
    while ($row = mysqli_fetch_assoc($timeline_result)) {
        $timeline[] = [
            'tanggal' => $row['tanggal_perubahan'],
            'status' => $row['status'],
            'keterangan' => $row['catatan']
        ];
    }
}

// Get document attachments
$dokumen_query = "SELECT nama_file, path_file, jenis_persyaratan 
                  FROM dokumen_persyaratan 
                  WHERE pengajuan_id = $pengajuan_id";
$dokumen_result = mysqli_query($koneksi, $dokumen_query);

$dokumen = [];
if ($dokumen_result && mysqli_num_rows($dokumen_result) > 0) {
    while ($row = mysqli_fetch_assoc($dokumen_result)) {
        $dokumen[] = [
            'nama' => $row['nama_file'],
            'path' => $row['path_file'],
            'jenis' => $row['jenis_persyaratan']
        ];
    }
}

// Prepare response data
$response_data = [
    'pengajuan_id' => $pengajuan['pengajuan_id'],
    'nomor_pengajuan' => $pengajuan['nomor_pengajuan'],
    'tanggal_pengajuan' => $pengajuan['tanggal_pengajuan'],
    'jenis_dokumen' => $pengajuan['nama_dokumen'],
    'status' => $pengajuan['status'],
    'catatan' => $pengajuan['catatan'],
    'tanggal_selesai' => $pengajuan['tanggal_selesai'],
    'user' => [
        'nama' => $pengajuan['nama'],
        'nik' => $pengajuan['nik'],
        'alamat' => $pengajuan['alamat'],
        'telepon' => $pengajuan['nomor_telepon'],
        'email' => $pengajuan['email']
    ],
    'timeline' => $timeline,
    'dokumen' => $dokumen
];

// Set success response
$response['status'] = true;
$response['message'] = 'Data pengajuan berhasil ditemukan';
$response['data'] = $response_data;

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);