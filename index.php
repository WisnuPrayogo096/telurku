<?php
require_once 'config.php';
requireLogin();

// Hitung statistik
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Total penjualan hari ini
$today = date('Y-m-d');
$query_today = "SELECT SUM(total_bayar) as total FROM penjualan WHERE tanggal = '$today'";
$result_today = mysqli_query($conn, $query_today);
$total_today = mysqli_fetch_assoc($result_today)['total'] ?? 0;

// Total penjualan bulan ini
$month = date('Y-m');
$query_month = "SELECT SUM(total_bayar) as total FROM penjualan WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$month'";
$result_month = mysqli_query($conn, $query_month);
$total_month = mysqli_fetch_assoc($result_month)['total'] ?? 0;

// Jumlah item stok (sesuai permission)
if ($role == 'anak') {
    $query_stok = "SELECT COUNT(*) as total FROM barang";
} else {
    $query_stok = "SELECT COUNT(*) as total FROM barang WHERE owner_id = $user_id";
}
$result_stok = mysqli_query($conn, $query_stok);
$total_stok = mysqli_fetch_assoc($result_stok)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>

<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">TELURKU</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm">Halo, <?php echo $_SESSION['nama']; ?></span>
                <button type="button" id="btnLogout" class="bg-red-500 px-4 py-2 rounded hover:bg-red-600">
                    Keluar
                </button>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <!-- Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Penjualan Hari Ini</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo formatRupiah($total_today); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Penjualan Bulan Ini</h3>
                <p class="text-2xl font-bold text-green-600"><?php echo formatRupiah($total_month); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Jumlah Item Stok</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo $total_stok; ?> Item</p>
            </div>
        </div>

        <!-- Menu Utama -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="barang" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition text-center">
                <div class="text-4xl mb-4 text-blue-600"><i class="ph ph-package"></i></div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Data Barang</h2>
                <p class="text-gray-600">Kelola stok barang</p>
            </a>

            <a href="penjualan" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition text-center">
                <div class="text-4xl mb-4 text-green-600"><i class="ph ph-hand-coins"></i></div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Transaksi Penjualan</h2>
                <p class="text-gray-600">Catat penjualan baru</p>
            </a>

            <a href="laporan" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition text-center">
                <div class="text-4xl mb-4 text-purple-600"><i class="ph ph-chart-line"></i></div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Laporan Penjualan</h2>
                <p class="text-gray-600">Lihat rekapan penjualan</p>
            </a>

            <a href="laporan_qris" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition text-center">
                <div class="text-4xl mb-4 text-orange-500"><i class="ph ph-device-mobile"></i></div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Laporan QRIS</h2>
                <p class="text-gray-600">Rekapan pembayaran QRIS</p>
            </a>
        </div>
    </div>

    <script>
    // SweetAlert2: Konfirmasi logout
    const btnLogout = document.getElementById('btnLogout');
    btnLogout?.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Keluar dari aplikasi?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, keluar',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        });
        if (result.isConfirmed) {
            window.location.href = 'logout';
        }
    });
    </script>
</body>

</html>