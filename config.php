<?php
// Sesi browser bertahan 30 hari (sama dengan batas login ulang tanpa password).
$session_lifetime = 30 * 24 * 60 * 60;
ini_set('session.gc_maxlifetime', (string)$session_lifetime);
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Set timezone to GMT+7 (Jakarta, Indonesia)
date_default_timezone_set('Asia/Jakarta');

// Database Configuration
define('DB_HOST', '10.18.3.69');
define('DB_USER', 'simsatsetroot');
define('DB_PASS', '17082013');
define('DB_NAME', 'db_wisnu');

// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'db_telurku');

// Create Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

// Schema sudah fixed via migration, tidak perlu auto-ensure lagi

// Function untuk cek login
function isLoggedIn()
{
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    return restoreRememberedLogin();
}

/** @deprecated Semua user login punya akses penuh; tetap ada untuk kompatibilitas. */
function checkPermission($owner_id = null)
{
    return isLoggedIn();
}

// Redirect jika belum login
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login");
        exit();
    }
}

// Format rupiah - tampilkan 2 desimal untuk support angka desimal
function formatRupiah($angka)
{
    return "Rp " . number_format($angka, 2, ',', '.');
}

function unitLabel($unit)
{
    if ($unit === 'gram' || $unit === 'kg') {
        return 'gram';
    }
    if ($unit === 'renteng') {
        return 'renteng';
    }
    return 'pcs';
}

function unitTypeLabel($unit)
{
    if ($unit === 'renteng') {
        return 'Ecer & Renteng/Slop';
    }
    if ($unit === 'gram' || $unit === 'kg') {
        return 'Gram / Timbang';
    }
    return 'PCS Kemasan';
}

function formatQty($qty)
{
    return rtrim(rtrim(number_format((float)$qty, 3, ',', '.'), '0'), ',');
}

// Format tanggal
function formatTanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $tanggal = substr($tanggal, 0, 10);

    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $split = explode('-', $tanggal);

    // Pastikan format Y-m-d valid (3 bagian)
    if (count($split) < 3) {
        return $tanggal; // Kembalikan apa adanya jika format tidak sesuai
    }

    $bulanIndex = (int)$split[1];
    if ($bulanIndex < 1 || $bulanIndex > 12) {
        return $tanggal;
    }

    return $split[2] . ' ' . $bulan[$bulanIndex] . ' ' . $split[0];
}

// Get current datetime dengan timezone GMT+7 (Jakarta)
function getDateTime()
{
    return date('Y-m-d H:i:s');
}

// Get current date dengan timezone GMT+7 (Jakarta)
function getCurrentDate()
{
    return date('Y-m-d');
}

/** Durasi login ulang tanpa password (detik). */
function getSessionTimeoutSeconds()
{
    return 30 * 24 * 60 * 60; // 30 hari
}

function rememberCookieName()
{
    return 'telurku_remember';
}

function getRememberTokenLifetime()
{
    return getSessionTimeoutSeconds();
}

function setRememberCookie($token, $expires)
{
    setcookie(rememberCookieName(), $token, [
        'expires' => $expires,
        'path' => '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie()
{
    setcookie(rememberCookieName(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
}

function loginUser($conn, $user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['login_time'] = time();

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + getRememberTokenLifetime());
    $user_id = (int)$user['id'];

    $query = "UPDATE users SET last_login = NOW(), remember_token_hash = ?, remember_token_expires_at = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssi", $token_hash, $expires_at, $user_id);
    mysqli_stmt_execute($stmt);

    setRememberCookie($token, time() + getRememberTokenLifetime());
}

function restoreRememberedLogin()
{
    global $conn;

    $token = $_COOKIE[rememberCookieName()] ?? '';
    if ($token === '') {
        return false;
    }

    $token_hash = hash('sha256', $token);
    $query = "SELECT id, username, nama FROM users WHERE remember_token_hash = ? AND remember_token_expires_at > NOW() LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $token_hash);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) {
        clearRememberCookie();
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['login_time'] = time();
    return true;
}

function logoutUser($conn)
{
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        $query = "UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
    }

    clearRememberCookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
}

/**
 * Hitung modal (HPP) dari satu baris detail penjualan.
 */
function hitungModalDetail($row)
{
    $modal_satuan = (float)($row['harga_beli'] ?? 0);
    $jumlah = (float)($row['jumlah'] ?? 0);
    $unit = $row['unit'] ?? 'pcs';
    $unit_type = $row['unit_type'] ?? 'pcs';

    if ($unit_type === 'gram' || $unit === 'gram' || $unit === 'gram (custom)') {
        return hargaBeliGramSatuan($modal_satuan) * $jumlah;
    }

    if ($unit === 'renteng') {
        $jumlah_pcs = $jumlah * max((int)($row['isi_renteng'] ?? 0), 1);
        return $modal_satuan * $jumlah_pcs;
    }

    return $modal_satuan * $jumlah;
}

function hitungKeuntunganDetail($row)
{
    if ((float)($row['harga_beli'] ?? 0) <= 0) {
        return 0;
    }

    return (float)($row['subtotal'] ?? 0) - hitungModalDetail($row);
}

/**
 * Konversi qty detail penjualan ke satuan stok (pcs/gram) — kebalikan dari penjualan.php.
 */
function hitungJumlahPcsDetailPenjualan(array $detail, array $barang): float
{
    $jumlah = (float)($detail['jumlah'] ?? 0);
    $unit = $detail['unit'] ?? 'pcs';

    if ($unit === 'renteng') {
        return $jumlah * max((int)($barang['isi_renteng'] ?? 1), 1);
    }

    return $jumlah;
}

/**
 * Batalkan satu baris detail penjualan dan kembalikan stok barang.
 *
 * @return array{ok: bool, message: string}
 */
function hapusDetailPenjualan(mysqli $conn, int $detail_id): array
{
    $query = "SELECT dp.*, b.isi_renteng, b.unit_type, b.nama_barang
              FROM detail_penjualan dp
              JOIN barang b ON dp.barang_id = b.id
              WHERE dp.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Gagal memuat data penjualan!'];
    }

    mysqli_stmt_bind_param($stmt, "i", $detail_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detail = mysqli_fetch_assoc($result);

    if (!$detail) {
        return ['ok' => false, 'message' => 'Data penjualan tidak ditemukan!'];
    }

    $penjualan_id = (int)$detail['penjualan_id'];
    $barang_id = (int)$detail['barang_id'];
    $jumlah_pcs = hitungJumlahPcsDetailPenjualan($detail, $detail);
    $subtotal = (float)$detail['subtotal'];
    $nama_barang = $detail['nama_barang'];

    mysqli_begin_transaction($conn);

    try {
        $stmt_stok = mysqli_prepare($conn, "UPDATE barang SET stok = stok + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_stok, "di", $jumlah_pcs, $barang_id);
        mysqli_stmt_execute($stmt_stok);

        $stmt_del = mysqli_prepare($conn, "DELETE FROM detail_penjualan WHERE id = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $detail_id);
        mysqli_stmt_execute($stmt_del);

        $stmt_count = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM detail_penjualan WHERE penjualan_id = ?");
        mysqli_stmt_bind_param($stmt_count, "i", $penjualan_id);
        mysqli_stmt_execute($stmt_count);
        $count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
        $remaining = (int)($count_row['cnt'] ?? 0);

        if ($remaining === 0) {
            $stmt_penjualan = mysqli_prepare($conn, "DELETE FROM penjualan WHERE id = ?");
            mysqli_stmt_bind_param($stmt_penjualan, "i", $penjualan_id);
            mysqli_stmt_execute($stmt_penjualan);
        } else {
            $stmt_update = mysqli_prepare($conn, "UPDATE penjualan SET total_bayar = GREATEST(total_bayar - ?, 0) WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "di", $subtotal, $penjualan_id);
            mysqli_stmt_execute($stmt_update);
        }

        mysqli_commit($conn);

        return [
            'ok' => true,
            'message' => 'Penjualan "' . $nama_barang . '" berhasil dibatalkan. Stok barang telah dikembalikan.',
        ];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Gagal membatalkan penjualan!'];
    }
}

/**
 * Batalkan seluruh transaksi penjualan (semua item) dan kembalikan stok.
 *
 * @return array{ok: bool, message: string}
 */
function hapusPenjualan(mysqli $conn, int $penjualan_id): array
{
    $query = "SELECT dp.*, b.isi_renteng, b.unit_type, b.nama_barang
              FROM detail_penjualan dp
              JOIN barang b ON dp.barang_id = b.id
              WHERE dp.penjualan_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Gagal memuat data transaksi!'];
    }

    mysqli_stmt_bind_param($stmt, "i", $penjualan_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $details = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $details[] = $row;
    }

    if (empty($details)) {
        return ['ok' => false, 'message' => 'Transaksi #' . $penjualan_id . ' tidak ditemukan!'];
    }

    $item_count = count($details);

    mysqli_begin_transaction($conn);

    try {
        $stmt_stok = mysqli_prepare($conn, "UPDATE barang SET stok = stok + ? WHERE id = ?");

        foreach ($details as $detail) {
            $jumlah_pcs = hitungJumlahPcsDetailPenjualan($detail, $detail);
            $barang_id = (int)$detail['barang_id'];
            mysqli_stmt_bind_param($stmt_stok, "di", $jumlah_pcs, $barang_id);
            mysqli_stmt_execute($stmt_stok);
        }

        $stmt_del_detail = mysqli_prepare($conn, "DELETE FROM detail_penjualan WHERE penjualan_id = ?");
        mysqli_stmt_bind_param($stmt_del_detail, "i", $penjualan_id);
        mysqli_stmt_execute($stmt_del_detail);

        $stmt_del_penjualan = mysqli_prepare($conn, "DELETE FROM penjualan WHERE id = ?");
        mysqli_stmt_bind_param($stmt_del_penjualan, "i", $penjualan_id);
        mysqli_stmt_execute($stmt_del_penjualan);

        mysqli_commit($conn);

        return [
            'ok' => true,
            'message' => 'Transaksi #' . $penjualan_id . ' (' . $item_count . ' item) berhasil dibatalkan. Stok barang telah dikembalikan.',
        ];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Gagal membatalkan transaksi!'];
    }
}

/**
 * Batalkan beberapa baris detail penjualan sekaligus.
 *
 * @param int[] $detail_ids
 * @return array{ok: bool, message: string}
 */
function hapusDetailPenjualanBulk(mysqli $conn, array $detail_ids): array
{
    $detail_ids = array_values(array_unique(array_filter(array_map('intval', $detail_ids))));
    if (empty($detail_ids)) {
        return ['ok' => false, 'message' => 'Tidak ada item yang dipilih!'];
    }

    $placeholders = implode(',', array_fill(0, count($detail_ids), '?'));
    $query = "SELECT dp.*, b.isi_renteng, b.unit_type, b.nama_barang
              FROM detail_penjualan dp
              JOIN barang b ON dp.barang_id = b.id
              WHERE dp.id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Gagal memuat data penjualan!'];
    }

    $types = str_repeat('i', count($detail_ids));
    mysqli_stmt_bind_param($stmt, $types, ...$detail_ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $details = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $details[] = $row;
    }

    if (empty($details)) {
        return ['ok' => false, 'message' => 'Data penjualan tidak ditemukan!'];
    }

    $penjualan_updates = [];

    mysqli_begin_transaction($conn);

    try {
        $stmt_stok = mysqli_prepare($conn, "UPDATE barang SET stok = stok + ? WHERE id = ?");
        $stmt_del = mysqli_prepare($conn, "DELETE FROM detail_penjualan WHERE id = ?");

        foreach ($details as $detail) {
            $detail_id = (int)$detail['id'];
            $penjualan_id = (int)$detail['penjualan_id'];
            $jumlah_pcs = hitungJumlahPcsDetailPenjualan($detail, $detail);
            $barang_id = (int)$detail['barang_id'];
            $subtotal = (float)$detail['subtotal'];

            mysqli_stmt_bind_param($stmt_stok, "di", $jumlah_pcs, $barang_id);
            mysqli_stmt_execute($stmt_stok);

            mysqli_stmt_bind_param($stmt_del, "i", $detail_id);
            mysqli_stmt_execute($stmt_del);

            if (!isset($penjualan_updates[$penjualan_id])) {
                $penjualan_updates[$penjualan_id] = 0;
            }
            $penjualan_updates[$penjualan_id] += $subtotal;
        }

        $stmt_update = mysqli_prepare($conn, "UPDATE penjualan SET total_bayar = GREATEST(total_bayar - ?, 0) WHERE id = ?");
        $stmt_count = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM detail_penjualan WHERE penjualan_id = ?");
        $stmt_del_penjualan = mysqli_prepare($conn, "DELETE FROM penjualan WHERE id = ?");

        foreach ($penjualan_updates as $penjualan_id => $subtotal_dropped) {
            mysqli_stmt_bind_param($stmt_update, "di", $subtotal_dropped, $penjualan_id);
            mysqli_stmt_execute($stmt_update);

            mysqli_stmt_bind_param($stmt_count, "i", $penjualan_id);
            mysqli_stmt_execute($stmt_count);
            $count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
            $remaining = (int)($count_row['cnt'] ?? 0);

            if ($remaining === 0) {
                mysqli_stmt_bind_param($stmt_del_penjualan, "i", $penjualan_id);
                mysqli_stmt_execute($stmt_del_penjualan);
            }
        }

        mysqli_commit($conn);

        return [
            'ok' => true,
            'message' => count($details) . ' item penjualan berhasil dibatalkan. Stok barang telah dikembalikan.',
        ];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Gagal membatalkan penjualan!'];
    }
}

function hargaBeliGramSatuan($harga_beli)
{
    $harga_beli = (float)$harga_beli;
    if ($harga_beli <= 0) {
        return 0;
    }

    return $harga_beli < 1000 ? $harga_beli : $harga_beli / 1000;
}

/**
 * Ringkasan penjualan berdasarkan rentang waktu.
 */
function getSalesSummaryByDateRange($conn, $start, $end)
{
    $summary = [
        'total_penjualan' => 0,
        'total_modal' => 0,
        'total_keuntungan' => 0,
    ];

    $query = "SELECT dp.unit, dp.jumlah, dp.subtotal, b.harga_beli, b.isi_renteng, b.unit_type
        FROM penjualan p
        JOIN detail_penjualan dp ON p.id = dp.penjualan_id
        JOIN barang b ON dp.barang_id = b.id
        WHERE p.tanggal >= ? AND p.tanggal < ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return $summary;
    }

    mysqli_stmt_bind_param($stmt, "ss", $start, $end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $summary['total_penjualan'] += (float)$row['subtotal'];
        $summary['total_modal'] += hitungModalDetail($row);
        $summary['total_keuntungan'] += hitungKeuntunganDetail($row);
    }

    return $summary;
}

/**
 * Nilai aset stok saat ini (harga beli / potensi jual).
 */
function hitungNilaiStokBarang($row, $field = 'harga_beli')
{
    $stok = (float)($row['stok'] ?? 0);
    $unit_type = $row['unit_type'] ?? 'pcs';
    $isi_renteng = max((int)($row['isi_renteng'] ?? 0), 0);

    if ($unit_type === 'gram') {
        if ($field === 'harga_beli') {
            return $stok * hargaBeliGramSatuan($row['harga_beli'] ?? 0);
        }

        return ($stok / 1000) * (float)($row[$field] ?? 0);
    }

    if ($unit_type === 'renteng' && $isi_renteng > 0 && $field !== 'harga_beli') {
        $harga_renteng = (float)($row['harga_jual_renteng'] ?? 0);
        $harga_pcs = (float)(($row['harga_jual_pcs'] ?? 0) > 0 ? $row['harga_jual_pcs'] : ($row['harga_jual'] ?? 0));
        $renteng_penuh = (int)floor($stok / $isi_renteng);
        $sisa_pcs = $stok - ($renteng_penuh * $isi_renteng);
        if ($harga_renteng > 0) {
            return ($renteng_penuh * $harga_renteng) + ($sisa_pcs * $harga_pcs);
        }
        return $stok * $harga_pcs;
    }

    $harga = (float)($row['harga_beli'] ?? 0);
    if ($field !== 'harga_beli') {
        $harga = (float)(($row['harga_jual_pcs'] ?? 0) > 0 ? $row['harga_jual_pcs'] : ($row['harga_jual'] ?? 0));
    }

    return $stok * $harga;
}

/**
 * Statistik dashboard.
 */
function getDashboardStats($conn)
{
    $today = getCurrentDate();
    $month = date('Y-m');
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
    $next_month = date('Y-m', strtotime($month . '-01 +1 month'));

    $today_summary = getSalesSummaryByDateRange($conn, $today, $tomorrow);
    $total_today = $today_summary['total_penjualan'];

    $month_summary = getSalesSummaryByDateRange($conn, "$month-01", "$next_month-01");
    $total_month = $month_summary['total_penjualan'];

    $total_stok = 0;
    $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM barang");
    if ($q) {
        $total_stok = (int)(mysqli_fetch_assoc($q)['total'] ?? 0);
    }

    $total_keuntungan_today = $today_summary['total_keuntungan'];

    $aset_beli = 0;
    $aset_jual = 0;
    $q = mysqli_query($conn, "SELECT * FROM barang");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $aset_beli += hitungNilaiStokBarang($row, 'harga_beli');
            $aset_jual += hitungNilaiStokBarang($row, 'harga_jual');
        }
    }

    $total_keuntungan_month = $month_summary['total_keuntungan'];

    return compact(
        'total_today',
        'total_month',
        'total_stok',
        'total_keuntungan_today',
        'total_keuntungan_month',
        'aset_beli',
        'aset_jual'
    );
}

/**
 * Dapatkan data grafik penjualan & keuntungan harian untuk 30 hari terakhir.
 */
function getDailyChartData($conn, $days = 30)
{
    $labels = [];
    $sales_data = [];
    $profit_data = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d/m', strtotime($date));

        $tomorrow = date('Y-m-d', strtotime($date . ' +1 day'));
        $summary = getSalesSummaryByDateRange($conn, $date, $tomorrow);
        $sales_data[] = $summary['total_penjualan'];
        $profit_data[] = $summary['total_keuntungan'];
    }

    return compact('labels', 'sales_data', 'profit_data');
}

/**
 * Dapatkan data grafik penjualan & keuntungan bulanan untuk 12 bulan terakhir.
 */
function getMonthlyChartData($conn, $months = 12)
{
    $labels = [];
    $sales_data = [];
    $profit_data = [];
    $month_names = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

    for ($i = $months - 1; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_arr = explode('-', $month);
        $labels[] = $month_names[(int)$month_arr[1]];

        $next_month = date('Y-m', strtotime($month . '-01 +1 month'));
        $summary = getSalesSummaryByDateRange($conn, "$month-01", "$next_month-01");
        $sales_data[] = $summary['total_penjualan'];
        $profit_data[] = $summary['total_keuntungan'];
    }

    return compact('labels', 'sales_data', 'profit_data');
}
