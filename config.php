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
