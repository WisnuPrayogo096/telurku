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
 * Ini auto-migrasi ringan agar fitur satuan (pcs/kg) & pack size bisa dipakai.
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
        $alterParts[] = "ADD COLUMN unit_type ENUM('pcs','kg') NOT NULL DEFAULT 'pcs' AFTER nama_barang";
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

    // Pastikan tabel detail_penjualan memiliki kolom unit
    $detail_columns = [];
    if ($result = mysqli_query($conn, "SHOW COLUMNS FROM detail_penjualan")) {
        while ($row = mysqli_fetch_assoc($result)) {
            $detail_columns[$row['Field']] = $row;
        }
    }

    $detail_alter = [];
    if (!isset($detail_columns['unit'])) {
        $detail_alter[] = "ADD COLUMN unit ENUM('renteng','pcs') NOT NULL DEFAULT 'pcs' AFTER jumlah";
    }

    if ($detail_alter) {
        mysqli_query($conn, "ALTER TABLE detail_penjualan " . implode(', ', $detail_alter));
    }
}

// Jalankan penyesuaian skema (aman jika sudah pernah dijalankan).
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
    if (!isLoggedIn()) {
        header("Location: login");
        exit();
    }
}

// Format rupiah
function formatRupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Format tanggal
function formatTanggal($tanggal)
{
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
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}
