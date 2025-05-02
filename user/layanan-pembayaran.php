<?php
// Function to detect file path
function getBasePath()
{
    if (file_exists('../includes/functions.php')) {
        return '../';
    } elseif (file_exists('includes/functions.php')) {
        return '';
    } else {
        die("Tidak dapat menemukan path includes. Silakan hubungi administrator.");
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determine correct path
$base_path = getBasePath();

// Include necessary files
require_once $base_path . 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Anda harus login terlebih dahulu';
    redirect($base_path . 'index.php');
}

// Include database connection
require_once $base_path . 'config/koneksi.php';

// Get user's ID
$user_id = $_SESSION['user_id'];

// Get user details
$user_query = "SELECT nama, alamat, nomor_telepon FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($koneksi, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);

// Get all retribution types
$jenis_retribusi_query = "SELECT * FROM jenis_retribusi WHERE is_active = TRUE ORDER BY nama_retribusi ASC";
$jenis_retribusi_result = mysqli_query($koneksi, $jenis_retribusi_query);

// Get user's active retribution bills
$tagihan_query = "SELECT tr.tagihan_id, tr.tanggal_tagihan, tr.jatuh_tempo, tr.nominal, tr.status, tr.denda,
                 jr.nama_retribusi, jr.periode, jr.jenis_retribusi_id
                 FROM tagihan_retribusi tr
                 JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                 WHERE tr.user_id = ? AND tr.status != 'lunas'
                 ORDER BY tr.jatuh_tempo ASC";
$stmt = mysqli_prepare($koneksi, $tagihan_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$tagihan_result = mysqli_stmt_get_result($stmt);
$has_bills = mysqli_num_rows($tagihan_result) > 0;

// Calculate late fees for overdue bills
$today = date('Y-m-d');
$update_bills = false;

while ($row = mysqli_fetch_assoc($tagihan_result)) {
    $tagihan_id = $row['tagihan_id'];
    $jatuh_tempo = $row['jatuh_tempo'];
    $status = $row['status'];
    $nominal = $row['nominal'];
    $denda = $row['denda'];

    // Check if bill is overdue and status is still 'belum_bayar'
    if ($status == 'belum_bayar' && $jatuh_tempo < $today) {
        // Calculate days overdue
        $days_overdue = floor((strtotime($today) - strtotime($jatuh_tempo)) / (60 * 60 * 24));

        // Apply late fee logic (e.g., 1% per day, max 20%)
        $late_fee_percentage = min($days_overdue * 0.01, 0.2); // 1% per day, max 20%
        $new_denda = round($nominal * $late_fee_percentage);

        // Only update if the late fee has changed
        if ($new_denda != $denda) {
            $update_query = "UPDATE tagihan_retribusi SET denda = ?, status = 'telat' WHERE tagihan_id = ?";
            $update_stmt = mysqli_prepare($koneksi, $update_query);
            mysqli_stmt_bind_param($update_stmt, "di", $new_denda, $tagihan_id);
            mysqli_stmt_execute($update_stmt);
            $update_bills = true;
        }
    }
}

// Refresh the bills query if any were updated
if ($update_bills) {
    mysqli_data_seek($tagihan_result, 0); // Reset the result pointer
    mysqli_stmt_execute($stmt); // Re-execute the query
    $tagihan_result = mysqli_stmt_get_result($stmt);
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bayar_sekarang'])) {
    // Get selected bills
    $selected_bills = isset($_POST['selected_tagihan']) ? $_POST['selected_tagihan'] : [];
    $metode_pembayaran = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'transfer_bank';

    if (empty($selected_bills)) {
        $payment_error = "Pilih minimal satu tagihan untuk dibayar";
    } else {
        // Calculate total payment
        $total_bayar = 0;
        $tagihan_ids = [];

        foreach ($selected_bills as $tagihan_id) {
            $tagihan_id = (int)$tagihan_id;
            $tagihan_query = "SELECT nominal, denda FROM tagihan_retribusi WHERE tagihan_id = ? AND user_id = ?";
            $bill_stmt = mysqli_prepare($koneksi, $tagihan_query);
            mysqli_stmt_bind_param($bill_stmt, "ii", $tagihan_id, $user_id);
            mysqli_stmt_execute($bill_stmt);
            $tagihan_result = mysqli_stmt_get_result($bill_stmt);

            if ($tagihan = mysqli_fetch_assoc($tagihan_result)) {
                $total_bayar += $tagihan['nominal'] + $tagihan['denda'];
                $tagihan_ids[] = $tagihan_id;
            }
        }

        // Add admin fee
        $biaya_admin = 2500;
        $total_bayar += $biaya_admin;

        // Generate reference number
        $nomor_referensi = 'PAY-' . date('YmdHis') . '-' . mt_rand(1000, 9999);

        // Start transaction
        mysqli_begin_transaction($koneksi);

        try {
            // Insert payment record for each bill
            foreach ($tagihan_ids as $tagihan_id) {
                $query = "INSERT INTO pembayaran_retribusi (tagihan_id, jumlah_bayar, metode_pembayaran, nomor_referensi, status, tanggal_bayar)
                         VALUES (?, ?, ?, ?, 'pending', NOW())";
                $payment_stmt = mysqli_prepare($koneksi, $query);
                mysqli_stmt_bind_param($payment_stmt, "idss", $tagihan_id, $total_bayar, $metode_pembayaran, $nomor_referensi);
                mysqli_stmt_execute($payment_stmt);

                // Update bill status
                $update_query = "UPDATE tagihan_retribusi SET status = 'proses' WHERE tagihan_id = ?";
                $update_stmt = mysqli_prepare($koneksi, $update_query);
                mysqli_stmt_bind_param($update_stmt, "i", $tagihan_id);
                mysqli_stmt_execute($update_stmt);
            }

            // Create notification
            $judul = "Pembayaran Retribusi";
            $pesan = "Pembayaran retribusi dengan nomor referensi $nomor_referensi sedang diproses. Total pembayaran: Rp " . number_format($total_bayar, 0, ',', '.');
            $query = "INSERT INTO notifikasi (user_id, judul, pesan, jenis, link, created_at) 
                     VALUES (?, ?, ?, 'pembayaran', ?, NOW())";
            $notif_stmt = mysqli_prepare($koneksi, $query);
            $link = 'user/pembayaran_detail.php?ref=' . $nomor_referensi;
            mysqli_stmt_bind_param($notif_stmt, "isss", $user_id, $judul, $pesan, $link);
            mysqli_stmt_execute($notif_stmt);

            // Log activity
            $aktivitas = "Melakukan pembayaran retribusi dengan nomor referensi $nomor_referensi";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $query = "INSERT INTO log_aktivitas (user_id, aktivitas, ip_address, user_agent, created_at) 
                     VALUES (?, ?, ?, ?, NOW())";
            $log_stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $aktivitas, $ip_address, $user_agent);
            mysqli_stmt_execute($log_stmt);

            // Commit transaction
            mysqli_commit($koneksi);

            // Set success message and redirect
            $_SESSION['payment_success'] = "Pembayaran sedang diproses. Nomor referensi: $nomor_referensi";
            redirect($base_path . 'user/pembayaran_detail.php?ref=' . $nomor_referensi);
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($koneksi);
            $payment_error = "Terjadi kesalahan saat memproses pembayaran: " . $e->getMessage();
        }
    }
}

// Include header
$page_title = "Pembayaran Retribusi";
include $base_path . 'includes/header.php';
?>

<div class="main-container">
    <section class="page-header">
        <h2>Pembayaran Retribusi</h2>
        <p>Bayar retribusi anda secara online</p>
    </section>

    <!-- Info Box -->
    <div class="info-box">
        <p>Pembayaran retribusi dapat dilakukan melalui e-banking, m-banking, atau transfer bank. Pembayaran akan diverifikasi dalam 1x24 jam kerja.</p>
    </div>

    <?php if (isset($payment_error)): ?>
        <div class="alert alert-danger">
            <?php echo $payment_error; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['payment_success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['payment_success']; ?>
        </div>
        <?php unset($_SESSION['payment_success']); ?>
    <?php endif; ?>

    <!-- Retribution Content -->
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" id="payment-form">
        <div class="retribusi-container">
            <!-- Retribution List -->
            <div class="retribusi-list">
                <h2>Daftar Tagihan</h2>

                <div class="retribusi-filter">
                    <div>
                        <select id="filter-type">
                            <option value="all">Semua Jenis</option>
                            <option value="bulanan">Bulanan</option>
                            <option value="tahunan">Tahunan</option>
                            <option value="insidentil">Insidentil</option>
                        </select>
                    </div>
                    <div class="search-box">
                        <input type="text" id="search-input" placeholder="Cari berdasarkan nama/kode...">
                        <button type="button" id="search-btn">Cari</button>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="retribusi-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" class="retribusi-checkbox"></th>
                                <th>Kode</th>
                                <th>Jenis Retribusi</th>
                                <th>Kategori</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Nominal</th>
                                <th>Denda</th>
                                <th>Total</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($has_bills) {
                                mysqli_data_seek($tagihan_result, 0); // Reset pointer
                                while ($row = mysqli_fetch_assoc($tagihan_result)) {
                                    $tagihan_id = $row['tagihan_id'];
                                    $nama_retribusi = $row['nama_retribusi'];
                                    $periode = $row['periode'];
                                    $jatuh_tempo = date('d-m-Y', strtotime($row['jatuh_tempo']));
                                    $status = $row['status'];
                                    $nominal = $row['nominal'];
                                    $denda = $row['denda'];
                                    $total = $nominal + $denda;

                                    // Generate code
                                    $kode = 'RT';
                                    if ($periode == 'bulanan') {
                                        $kode .= 'B';
                                    } elseif ($periode == 'tahunan') {
                                        $kode .= 'T';
                                    } else {
                                        $kode .= 'I';
                                    }
                                    $kode .= '-' . date('Y-m', strtotime($row['tanggal_tagihan']));

                                    // Status classes and text
                                    $status_class = '';
                                    $status_text = '';
                                    $disabled = '';
                                    $checkbox_disabled = '';

                                    switch ($status) {
                                        case 'belum_bayar':
                                            $status_class = 'status-belum';
                                            $status_text = 'Belum Dibayar';
                                            break;
                                        case 'proses':
                                            $status_class = 'status-proses';
                                            $status_text = 'Diproses';
                                            $disabled = 'disabled';
                                            $checkbox_disabled = 'disabled';
                                            break;
                                        case 'lunas':
                                            $status_class = 'status-lunas';
                                            $status_text = 'Lunas';
                                            $disabled = 'disabled';
                                            $checkbox_disabled = 'disabled';
                                            break;
                                        case 'telat':
                                            $status_class = 'status-jatuh-tempo';
                                            $status_text = 'Jatuh Tempo';
                                            break;
                                    }

                                    // Check if bill is overdue
                                    $today = new DateTime();
                                    $tempo = new DateTime($row['jatuh_tempo']);
                                    if ($status == 'belum_bayar' && $today > $tempo) {
                                        $status_class = 'status-jatuh-tempo';
                                        $status_text = 'Jatuh Tempo';
                                    }

                                    echo "<tr>";
                                    echo "<td><input type='checkbox' name='selected_tagihan[]' value='$tagihan_id' class='retribusi-checkbox' $checkbox_disabled></td>";
                                    echo "<td>$kode</td>";
                                    echo "<td>$nama_retribusi</td>";
                                    echo "<td><span class='tag tag-$periode'>" . ucfirst($periode) . "</span></td>";
                                    echo "<td>$jatuh_tempo</td>";
                                    echo "<td><span class='tag $status_class'>$status_text</span></td>";
                                    echo "<td>Rp " . number_format($nominal, 0, ',', '.') . "</td>";
                                    echo "<td>Rp " . number_format($denda, 0, ',', '.') . "</td>";
                                    echo "<td>Rp " . number_format($total, 0, ',', '.') . "</td>";
                                    echo "<td>";
                                    echo "<button type='button' class='action-btn' onclick='selectSingleBill($tagihan_id)' $disabled>Bayar</button>";

                                    // Tambahkan tombol lihat proses jika status adalah 'proses'
                                    if ($status == 'proses') {
                                        // Ambil nomor referensi dari tabel pembayaran_retribusi
                                        $ref_query = "SELECT nomor_referensi FROM pembayaran_retribusi WHERE tagihan_id = ? ORDER BY pembayaran_id DESC LIMIT 1";
                                        $ref_stmt = mysqli_prepare($koneksi, $ref_query);
                                        mysqli_stmt_bind_param($ref_stmt, "i", $tagihan_id);
                                        mysqli_stmt_execute($ref_stmt);
                                        $ref_result = mysqli_stmt_get_result($ref_stmt);
                                        if ($ref_data = mysqli_fetch_assoc($ref_result)) {
                                            $nomor_ref = $ref_data['nomor_referensi'];

                                            echo " <a href='{$base_path}user/pembayaran_detail.php?ref={$nomor_ref}' class='view-progress-btn'>Lihat Proses</a>";
                                        }
                                    }

                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>Tidak ada tagihan aktif saat ini.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($has_bills && mysqli_num_rows($tagihan_result) > 10): ?>
                    <ul class="pagination">
                        <li><a href="#">&laquo;</a></li>
                        <li class="active"><a href="#">1</a></li>
                        <li><a href="#">2</a></li>
                        <li><a href="#">3</a></li>
                        <li><a href="#">&raquo;</a></li>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Payment Summary -->
            <div class="payment-summary">
                <h2>Ringkasan Pembayaran</h2>

                <div class="summary-items" id="summary-items">
                    <div class="empty-state">Pilih tagihan untuk dibayar</div>
                </div>

                <div class="total-amount">
                    <div class="summary-item">
                        <span class="summary-label">Total Pembayaran</span>
                        <span class="summary-value" id="total-payment">Rp 0</span>
                    </div>
                </div>

                <div class="payment-options">
                    <h3>Metode Pembayaran</h3>

                    <div class="payment-method">
                        <input type="radio" id="bank-transfer" name="payment_method" value="transfer_bank" checked>
                        <label for="bank-transfer">
                            Transfer Bank
                            <img src="<?php echo $base_path; ?>assets/img/bank-icons.png" alt="Bank Icons">
                        </label>
                    </div>

                    <div class="payment-method">
                        <input type="radio" id="e-wallet" name="payment_method" value="e_wallet">
                        <label for="e-wallet">
                            E-Wallet
                            <img src="<?php echo $base_path; ?>assets/img/e-wallet-icons.png" alt="E-Wallet Icons">
                        </label>
                    </div>

                    <div class="payment-method">
                        <input type="radio" id="qris" name="payment_method" value="qris">
                        <label for="qris">
                            QRIS
                            <img src="<?php echo $base_path; ?>assets/img/qris-icon.png" alt="QRIS Icon">
                        </label>
                    </div>
                </div>

                <button type="button" class="pay-btn" id="bayarBtn" disabled>Bayar Sekarang</button>
            </div>
        </div>
    </form>

    <!-- Riwayat Pembayaran Terbaru -->
    <div class="recent-payments">
        <h2>Riwayat Pembayaran Terbaru</h2>
        <div style="overflow-x: auto;">
            <table class="payment-history-table">
                <thead>
                    <tr>
                        <th>No. Referensi</th>
                        <th>Tanggal</th>
                        <th>Jenis Retribusi</th>
                        <th>Metode</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query untuk mengambil riwayat pembayaran terbaru
                    $history_query = "SELECT pr.pembayaran_id, pr.tagihan_id, pr.tanggal_bayar, pr.jumlah_bayar,
                                      pr.metode_pembayaran, pr.status, pr.nomor_referensi,
                                      jr.nama_retribusi
                                      FROM pembayaran_retribusi pr
                                      JOIN tagihan_retribusi tr ON pr.tagihan_id = tr.tagihan_id
                                      JOIN jenis_retribusi jr ON tr.jenis_retribusi_id = jr.jenis_retribusi_id
                                      WHERE tr.user_id = ?
                                      ORDER BY pr.tanggal_bayar DESC
                                      LIMIT 5";
                    $history_stmt = mysqli_prepare($koneksi, $history_query);
                    mysqli_stmt_bind_param($history_stmt, "i", $user_id);
                    mysqli_stmt_execute($history_stmt);
                    $history_result = mysqli_stmt_get_result($history_stmt);

                    if (mysqli_num_rows($history_result) > 0) {
                        while ($payment = mysqli_fetch_assoc($history_result)) {
                            $status_class = '';
                            $status_text = '';

                            switch ($payment['status']) {
                                case 'pending':
                                    $status_class = 'status-proses';
                                    $status_text = 'Diproses';
                                    break;
                                case 'berhasil':
                                    $status_class = 'status-lunas';
                                    $status_text = 'Berhasil';
                                    break;
                                case 'gagal':
                                    $status_class = 'status-jatuh-tempo';
                                    $status_text = 'Gagal';
                                    break;
                            }

                            echo "<tr>";
                            echo "<td>{$payment['nomor_referensi']}</td>";
                            echo "<td>" . date('d-m-Y H:i', strtotime($payment['tanggal_bayar'])) . "</td>";
                            echo "<td>{$payment['nama_retribusi']}</td>";
                            echo "<td>" . ucfirst(str_replace('_', ' ', $payment['metode_pembayaran'])) . "</td>";
                            echo "<td>Rp " . number_format($payment['jumlah_bayar'], 0, ',', '.') . "</td>";
                            echo "<td><span class='tag $status_class'>$status_text</span></td>";
                            echo "<td><a href='{$base_path}user/pembayaran_detail.php?ref={$payment['nomor_referensi']}' class='view-btn'>Detail</a></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Belum ada riwayat pembayaran.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="view-all-link">
            <a href="<?php echo $base_path; ?>user/riwayat_pembayaran.php">Lihat Semua Riwayat</a>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Pembayaran -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closePaymentModal">&times;</span>
        <h2 class="modal-title">Konfirmasi Pembayaran</h2>
        <div class="modal-body">
            <p>Anda akan melakukan pembayaran sebesar <strong id="modal-total">Rp 0</strong> untuk:</p>
            <ul id="modal-items" style="list-style-type: disc; margin-left: 20px; margin-top: 10px;"></ul>
            <p style="margin-top: 15px;">Metode pembayaran: <strong id="modal-payment-method">Transfer Bank</strong></p>
            <p style="margin-top: 15px;">Anda akan diarahkan ke halaman pembayaran. Lanjutkan?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="cancelBtn">Batal</button>
            <button type="submit" form="payment-form" name="bayar_sekarang" class="pay-btn" style="width: auto;">Lanjutkan</button>
        </div>
    </div>
</div>

<!-- Modal Status Pembayaran -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeStatusModal">&times;</span>
        <h2 class="modal-title">Status Pembayaran</h2>
        <div class="modal-body" id="status-timeline">
            <!-- Timeline akan diisi oleh JavaScript -->
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.retribusi-checkbox:not([disabled])');
        const summaryItems = document.getElementById('summary-items');
        const totalPayment = document.getElementById('total-payment');
        const paymentModal = document.getElementById('paymentModal');
        const closePaymentModal = document.getElementById('closePaymentModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const bayarBtn = document.getElementById('bayarBtn');
        const modalTotal = document.getElementById('modal-total');
        const modalItems = document.getElementById('modal-items');
        const modalPaymentMethod = document.getElementById('modal-payment-method');
        const searchInput = document.getElementById('search-input');
        const searchBtn = document.getElementById('search-btn');
        const filterType = document.getElementById('filter-type');
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');

        // Select all checkbox functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSummary();
            });
        }

        // Individual checkbox changes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSummary);
        });

        // Search functionality
        if (searchBtn) {
            searchBtn.addEventListener('click', filterTable);
        }

        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    filterTable();
                }
            });
        }

        // Filter by type
        if (filterType) {
            filterType.addEventListener('change', filterTable);
        }

        // Payment method change
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                const methodName = this.parentElement.querySelector('label').textContent.trim().split('\n')[0];
                if (modalPaymentMethod) {
                    modalPaymentMethod.textContent = methodName;
                }
            });
        });

        // Pay button click
        if (bayarBtn && paymentModal) {
            bayarBtn.addEventListener('click', function() {
                paymentModal.style.display = 'block';
            });
        }

        // Close modal
        if (closePaymentModal && paymentModal) {
            closePaymentModal.addEventListener('click', function() {
                paymentModal.style.display = 'none';
            });
        }

        if (cancelBtn && paymentModal) {
            cancelBtn.addEventListener('click', function() {
                paymentModal.style.display = 'none';
            });
        }

        // Close modal when clicking outside
        if (paymentModal) {
            window.addEventListener('click', function(e) {
                if (e.target === paymentModal) {
                    paymentModal.style.display = 'none';
                }
            });
        }

        // Update summary based on selected bills
        function updateSummary() {
            if (!summaryItems || !totalPayment) return;

            const selectedBills = getSelectedBills();
            let total = 0;
            let adminFee = 2500; // Admin fee in Rupiah

            summaryItems.innerHTML = '';

            if (selectedBills.length === 0) {
                summaryItems.innerHTML = '<div class="empty-state">Pilih tagihan untuk dibayar</div>';
                totalPayment.textContent = 'Rp 0';
                bayarBtn.disabled = true;
                return;
            }

            if (modalItems) {
                modalItems.innerHTML = '';
            }

            selectedBills.forEach(bill => {
                // Get bill details from the table row
                const row = bill.closest('tr');
                const jenis = row.cells[2].textContent;
                const nominal = row.cells[6].textContent.replace('Rp ', '').replace(/\./g, '');
                const denda = row.cells[7].textContent.replace('Rp ', '').replace(/\./g, '');
                const total_item = parseInt(nominal) + parseInt(denda);

                // Add to summary
                const item = document.createElement('div');
                item.className = 'summary-item';
                item.innerHTML = `
                <span class="summary-label">${jenis}</span>
                <span class="summary-value">Rp ${formatNumber(total_item)}</span>
                `;
                summaryItems.appendChild(item);

                // Add to modal list
                if (modalItems) {
                    const modalItem = document.createElement('li');
                    modalItem.textContent = `${jenis} - Rp ${formatNumber(total_item)}`;
                    modalItems.appendChild(modalItem);
                }

                // Add to total
                total += total_item;
            });

            // Add admin fee
            const feeItem = document.createElement('div');
            feeItem.className = 'summary-item';
            feeItem.innerHTML = `
            <span class="summary-label">Biaya Admin</span>
            <span class="summary-value">Rp ${formatNumber(adminFee)}</span>
            `;
            summaryItems.appendChild(feeItem);

            // Add admin fee to modal list
            if (modalItems) {
                const modalFeeItem = document.createElement('li');
                modalFeeItem.textContent = `Biaya Admin - Rp ${formatNumber(adminFee)}`;
                modalItems.appendChild(modalFeeItem);
            }

            // Update total
            total += adminFee;
            totalPayment.textContent = `Rp ${formatNumber(total)}`;

            if (modalTotal) {
                modalTotal.textContent = `Rp ${formatNumber(total)}`;
            }

            // Enable pay button
            bayarBtn.disabled = false;
        }

        // Filter table based on search input and filter type
        function filterTable() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const filterValue = filterType ? filterType.value : 'all';
            const rows = document.querySelectorAll('.retribusi-table tbody tr');

            rows.forEach(row => {
                const kode = row.cells[1].textContent.toLowerCase();
                const jenis = row.cells[2].textContent.toLowerCase();
                const kategori = row.cells[3].textContent.toLowerCase();

                const matchesSearch = kode.includes(searchTerm) || jenis.includes(searchTerm);
                const matchesFilter = filterValue === 'all' || kategori.toLowerCase().includes(filterValue);

                row.style.display = matchesSearch && matchesFilter ? '' : 'none';
            });
        }

        // Get all selected bill checkboxes
        function getSelectedBills() {
            return Array.from(document.querySelectorAll('.retribusi-checkbox:checked:not([disabled]):not(#select-all)'));
        }

        // Format number with thousand separator
        function formatNumber(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    });

    // Function to select a single bill
    function selectSingleBill(id) {
        // Uncheck all first
        document.querySelectorAll('.retribusi-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });

        // Check the specific one
        const checkbox = document.querySelector(`.retribusi-checkbox[value="${id}"]`);
        if (checkbox) {
            checkbox.checked = true;

            // Trigger change event to update summary
            const event = new Event('change');
            checkbox.dispatchEvent(event);

            // Scroll to payment section
            document.querySelector('.payment-summary').scrollIntoView({
                behavior: 'smooth'
            });
        }
    }
</script>

<style>
    /* Variables */
:root {
    --primary: #2E7D32;
    --secondary: #4CAF50;
    --light: #E8F5E9;
    --dark: #1B5E20;
    --accent: #FF9800;
    --text-dark: #212121;
    --text-light: #FFFFFF;
    --text-gray: #757575;
    --border: #BDBDBD;
    --danger: #F44336;
    --warning: #FFC107;
    --success: #4CAF50;
    --info: #2196F3;
    --shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    --border-radius: 4px;
    --transition: all 0.3s ease;
}

/* Global Styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Roboto', Arial, sans-serif;
    line-height: 1.6;
    color: var(--text-dark);
    background-color: #f5f5f5;
}

.main-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header {
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--primary);
}

.page-header h2 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 5px;
}

.page-header p {
    color: var(--text-gray);
    font-size: 16px;
}

/* Info Box */
.info-box {
    background-color: var(--light);
    border-left: 4px solid var(--primary);
    padding: 15px;
    margin-bottom: 25px;
    border-radius: var(--border-radius);
}

.info-box p {
    margin: 0;
    color: var(--dark);
}

/* Alerts */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: var(--border-radius);
    font-weight: 500;
}

.alert-danger {
    background-color: #FFEBEE;
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

.alert-success {
    background-color: #E8F5E9;
    color: var(--success);
    border-left: 4px solid var(--success);
}

/* Retribution Container */
.retribusi-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

@media (max-width: 900px) {
    .retribusi-container {
        grid-template-columns: 1fr;
    }
}

/* Retribution List */
.retribusi-list {
    background: var(--text-light);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 20px;
}

.retribusi-list h2 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 24px;
}

/* Retribution Filter */
.retribusi-filter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.retribusi-filter select {
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    background-color: var(--text-light);
    color: var(--text-dark);
    width: 150px;
    font-size: 14px;
}

.search-box {
    display: flex;
}

.search-box input {
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: var(--border-radius) 0 0 var(--border-radius);
    width: 250px;
    font-size: 14px;
}

.search-box button {
    padding: 10px 15px;
    background-color: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 0 var(--border-radius) var(--border-radius) 0;
    cursor: pointer;
    transition: var(--transition);
}

.search-box button:hover {
    background-color: var(--dark);
}

/* Retribution Table */
.retribusi-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.retribusi-table th,
.retribusi-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.retribusi-table th {
    background-color: var(--light);
    color: var(--dark);
    font-weight: 600;
    position: sticky;
    top: 0;
}

.retribusi-table tr:hover {
    background-color: #f9f9f9;
}

.retribusi-table .text-center {
    text-align: center;
}

/* Retribution Checkbox */
.retribusi-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Status Tags */
.tag {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.tag-bulanan {
    background-color: #E3F2FD;
    color: #1976D2;
}

.tag-tahunan {
    background-color: #E8F5E9;
    color: #388E3C;
}

.tag-insidentil {
    background-color: #FFF3E0;
    color: #E64A19;
}

.status-belum {
    background-color: #E0E0E0;
    color: #616161;
}

.status-proses {
    background-color: #E3F2FD;
    color: #1976D2;
}

.status-lunas {
    background-color: #E8F5E9;
    color: #388E3C;
}

.status-jatuh-tempo {
    background-color: #FFEBEE;
    color: #D32F2F;
}

/* Action Buttons */
.action-btn {
    background-color: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: var(--border-radius);
    padding: 6px 12px;
    font-size: 13px;
    cursor: pointer;
    transition: var(--transition);
}

.action-btn:hover {
    background-color: var(--dark);
}

.action-btn:disabled {
    background-color: #9E9E9E;
    cursor: not-allowed;
}

.view-progress-btn {
    display: inline-block;
    background-color: var(--accent);
    color: var(--text-light);
    border: none;
    border-radius: var(--border-radius);
    padding: 6px 12px;
    font-size: 13px;
    text-decoration: none;
    margin-left: 5px;
    transition: var(--transition);
}

.view-progress-btn:hover {
    background-color: #F57C00;
}

/* Pagination */
.pagination {
    display: flex;
    list-style: none;
    justify-content: center;
    margin-top: 20px;
}

.pagination li {
    margin: 0 5px;
}

.pagination a {
    display: block;
    padding: 8px 12px;
    text-decoration: none;
    color: var(--text-dark);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.pagination a:hover {
    background-color: var(--light);
}

.pagination .active a {
    background-color: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

/* Payment Summary */
.payment-summary {
    background: var(--text-light);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 20px;
    position: sticky;
    top: 20px;
}

.payment-summary h2 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 24px;
}

.summary-items {
    margin-bottom: 20px;
}

.empty-state {
    text-align: center;
    color: var(--text-gray);
    padding: 30px 0;
    font-style: italic;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px dashed var(--border);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    color: var(--text-dark);
}

.summary-value {
    font-weight: 500;
}

.total-amount {
    background-color: var(--light);
    padding: 15px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.total-amount .summary-item {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.total-amount .summary-label {
    font-weight: 600;
}

.total-amount .summary-value {
    font-weight: 700;
    color: var(--primary);
    font-size: 18px;
}

/* Payment Options */
.payment-options {
    margin-bottom: 20px;
}

.payment-options h3 {
    color: var(--text-dark);
    margin-bottom: 15px;
    font-size: 18px;
}

.payment-method {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.payment-method:hover {
    border-color: var(--primary);
    background-color: var(--light);
}

.payment-method input[type="radio"] {
    margin-right: 10px;
    accent-color: var(--primary);
}

.payment-method label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    cursor: pointer;
}

.payment-method img {
    height: 24px;
}

/* Pay Button */
.pay-btn {
    width: 100%;
    padding: 12px;
    background-color: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: var(--border-radius);
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.pay-btn:hover {
    background-color: var(--dark);
}

.pay-btn:disabled {
    background-color: #9E9E9E;
    cursor: not-allowed;
}

/* Recent Payments */
.recent-payments {
    background: var(--text-light);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 20px;
    margin-bottom: 40px;
}

.recent-payments h2 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 24px;
}

/* Payment History Table */
.payment-history-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.payment-history-table th,
.payment-history-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.payment-history-table th {
    background-color: var(--light);
    color: var(--dark);
    font-weight: 600;
}

.payment-history-table tr:hover {
    background-color: #f9f9f9;
}

.view-btn {
    display: inline-block;
    background-color: var(--secondary);
    color: var(--text-light);
    border: none;
    border-radius: var(--border-radius);
    padding: 6px 12px;
    font-size: 13px;
    text-decoration: none;
    transition: var(--transition);
}

.view-btn:hover {
    background-color: var(--primary);
}

.view-all-link {
    text-align: center;
    margin-top: 20px;
}

.view-all-link a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.view-all-link a:hover {
    color: var(--dark);
    text-decoration: underline;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.modal-content {
    background-color: var(--text-light);
    margin: 10% auto;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    width: 500px;
    max-width: 90%;
    position: relative;
}

.close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-gray);
    cursor: pointer;
}

.close:hover {
    color: var(--text-dark);
}

.modal-title {
    color: var(--primary);
    margin-bottom: 15px;
    font-size: 22px;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-secondary {
    padding: 10px 20px;
    background-color: #9E9E9E;
    color: var(--text-light);
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.btn-secondary:hover {
    background-color: #757575;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .retribusi-filter {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .retribusi-filter select {
        width: 100%;
    }
    
    .search-box {
        width: 100%;
    }
    
    .search-box input {
        width: calc(100% - 80px);
    }
    
    .retribusi-container {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 5% auto;
    }
}
</style>

<?php include '../includes/footer.php'; ?>