<?php
session_start();

// Database Configuration
define('DB_HOST', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');

// Create Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

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
