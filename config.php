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

// Create Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

/**
 * Pastikan skema tabel barang memiliki kolom pendukung satuan.
 * Ini auto-migrasi ringan agar fitur satuan (renteng/gram/pcs) bisa dipakai.
 */
function ensureBarangSchema($conn)
{
    $columns = [];
    if ($result = mysqli_query($conn, "SHOW COLUMNS FROM barang")) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = $row;
        }
    }

    $alterParts = [];
    if (!isset($columns['unit_type'])) {
        $alterParts[] = "ADD COLUMN unit_type ENUM('renteng','gram','pcs') NOT NULL DEFAULT 'pcs' AFTER nama_barang";
    }
    if (!isset($columns['isi_renteng'])) {
        $alterParts[] = "ADD COLUMN isi_renteng INT NOT NULL DEFAULT 0 AFTER unit_type";
    }
    if (!isset($columns['harga_jual_renteng'])) {
        $alterParts[] = "ADD COLUMN harga_jual_renteng DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER harga_jual";
    }
    if (!isset($columns['harga_jual_pcs'])) {
        $alterParts[] = "ADD COLUMN harga_jual_pcs DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER harga_jual_renteng";
    }

    if ($alterParts) {
        // Jalankan satu ALTER TABLE untuk semua kolom baru.
        mysqli_query($conn, "ALTER TABLE barang " . implode(', ', $alterParts));
    }

    if (isset($columns['unit_type']) && strpos($columns['unit_type']['Type'], "'renteng'") === false) {
        mysqli_query($conn, "ALTER TABLE barang MODIFY COLUMN unit_type ENUM('pcs','kg','gram','renteng') NOT NULL DEFAULT 'pcs'");
        mysqli_query($conn, "UPDATE barang SET stok = stok * 1000, unit_type = 'gram' WHERE unit_type = 'kg'");
        mysqli_query($conn, "UPDATE barang SET unit_type = 'renteng' WHERE unit_type = 'pcs' AND isi_renteng > 0");
        mysqli_query($conn, "ALTER TABLE barang MODIFY COLUMN unit_type ENUM('renteng','gram','pcs') NOT NULL DEFAULT 'pcs'");
    }

    // Pastikan tabel detail_penjualan memiliki kolom unit
    $detail_columns = [];
    if ($result = mysqli_query($conn, "SHOW COLUMNS FROM detail_penjualan")) {
        while ($row = mysqli_fetch_assoc($result)) {
            $detail_columns[$row['Field']] = $row;
        }
    }

    $detail_alter = [];
    if (!isset($detail_columns['unit'])) {
        $detail_alter[] = "ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'pcs' AFTER jumlah";
    } else {
        // Change from ENUM to VARCHAR if needed
        if (strpos($detail_columns['unit']['Type'], 'enum') !== false) {
            $detail_alter[] = "MODIFY COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'pcs'";
        }
    }

    if ($detail_alter) {
        mysqli_query($conn, "ALTER TABLE detail_penjualan " . implode(', ', $detail_alter));
    }

    // Buat tabel stok_masuk jika belum ada
    $sql_stok_masuk = "CREATE TABLE IF NOT EXISTS stok_masuk (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        barang_id INT NOT NULL,
        jumlah_tambah DECIMAL(10,2) NOT NULL DEFAULT 0,
        harga_beli DECIMAL(10,2) NOT NULL DEFAULT 0,
        harga_jual DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $sql_stok_masuk);
}

// Pastikan tabel users memiliki kolom last_login untuk persistent session
function ensureUsersSchema($conn)
{
    $columns = [];
    if ($result = mysqli_query($conn, "SHOW COLUMNS FROM users")) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = $row;
        }
    }

    if (!isset($columns['last_login'])) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL");
    }
}

// Pastikan tabel penjualan memiliki kolom tanggal sebagai DATETIME
function ensurePenjualanSchema($conn)
{
    $columns = [];
    if ($result = mysqli_query($conn, "SHOW COLUMNS FROM penjualan")) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = $row;
        }
    }

    // Ubah kolom tanggal dari DATE ke DATETIME
    if (isset($columns['tanggal']) && $columns['tanggal']['Type'] === 'date') {
        mysqli_query($conn, "ALTER TABLE penjualan MODIFY COLUMN tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}

// Jalankan penyesuaian skema (aman jika sudah pernah dijalankan).
ensureUsersSchema($conn);
ensurePenjualanSchema($conn);
ensureBarangSchema($conn);

// Function untuk cek login
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/** @deprecated Semua user login punya akses penuh; tetap ada untuk kompatibilitas. */
function checkPermission($owner_id = null)
{
    return isLoggedIn();
}

// Redirect jika belum login
function requireLogin()
{
    global $conn;

    if (!isLoggedIn()) {
        header("Location: login");
        exit();
    }

    $session_timeout = getSessionTimeoutSeconds();
    $user_id = $_SESSION['user_id'];

    // Ambil last_login dari database
    $query = "SELECT last_login FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && $user['last_login']) {
        // Konversi timestamp database ke unix time
        $last_login_time = strtotime($user['last_login']);
        $current_time = time();

        if ($current_time - $last_login_time > $session_timeout) {
            // Session expired, destroy dan redirect ke login
            session_destroy();
            header("Location: login?expired=1");
            exit();
        }
    }
}

// Format rupiah
function formatRupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
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
        return ($modal_satuan / 1000) * $jumlah;
    }

    if ($unit === 'renteng') {
        $jumlah_pcs = $jumlah * max((int)($row['isi_renteng'] ?? 0), 1);
        return $modal_satuan * $jumlah_pcs;
    }

    if ($unit === '1 kg') {
        return ($modal_satuan / 1000) * ($jumlah * 1000);
    }

    return $modal_satuan * $jumlah;
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

    $total_today = 0;
    $q = mysqli_query($conn, "SELECT COALESCE(SUM(total_bayar),0) AS total FROM penjualan WHERE DATE(tanggal) = '$today'");
    if ($q) {
        $total_today = (float)(mysqli_fetch_assoc($q)['total'] ?? 0);
    }

    $total_month = 0;
    $q = mysqli_query($conn, "SELECT COALESCE(SUM(total_bayar),0) AS total FROM penjualan WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$month'");
    if ($q) {
        $total_month = (float)(mysqli_fetch_assoc($q)['total'] ?? 0);
    }

    $total_stok = 0;
    $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM barang");
    if ($q) {
        $total_stok = (int)(mysqli_fetch_assoc($q)['total'] ?? 0);
    }

    $total_pendapatan_today = 0;
    $total_modal_today = 0;
    $q = mysqli_query($conn, "SELECT dp.unit, dp.jumlah, dp.subtotal, b.harga_beli, b.isi_renteng, b.unit_type
        FROM penjualan p
        JOIN detail_penjualan dp ON p.id = dp.penjualan_id
        JOIN barang b ON dp.barang_id = b.id
        WHERE DATE(p.tanggal) = '$today'");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $total_pendapatan_today += (float)$row['subtotal'];
            $total_modal_today += hitungModalDetail($row);
        }
    }
    $total_keuntungan_today = $total_pendapatan_today - $total_modal_today;

    $aset_beli = 0;
    $aset_jual = 0;
    $q = mysqli_query($conn, "SELECT * FROM barang");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $aset_beli += hitungNilaiStokBarang($row, 'harga_beli');
            $aset_jual += hitungNilaiStokBarang($row, 'harga_jual');
        }
    }

    return compact(
        'total_today',
        'total_month',
        'total_stok',
        'total_keuntungan_today',
        'aset_beli',
        'aset_jual'
    );
}
