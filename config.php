<?php
session_start();

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
    if (!isset($columns['isi_pax'])) {
        $alterParts[] = "ADD COLUMN isi_pax INT NOT NULL DEFAULT 0 AFTER isi_renteng";
    }
    if (!isset($columns['isi_slop'])) {
        $alterParts[] = "ADD COLUMN isi_slop INT NOT NULL DEFAULT 0 AFTER isi_pax";
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
        owner_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
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
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER role");
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

// Function untuk cek permission
function checkPermission($owner_id)
{
    if (!isLoggedIn()) {
        return false;
    }
    // Jika user adalah anak (role='anak'), bisa akses semua
    if ($_SESSION['role'] == 'anak') {
        return true;
    }
    // Jika user adalah ibu, hanya bisa akses miliknya sendiri
    return $_SESSION['user_id'] == $owner_id;
}

// Redirect jika belum login
function requireLogin()
{
    global $conn;

    if (!isLoggedIn()) {
        header("Location: login");
        exit();
    }

    // Cek apakah session sudah expired (30 hari = 2592000 detik)
    $session_timeout = 30 * 24 * 60 * 60; // 30 hari
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
