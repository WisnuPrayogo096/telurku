<?php
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
?>