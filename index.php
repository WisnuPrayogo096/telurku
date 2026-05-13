<?php
require_once 'config.php';
requireLogin();

// Hitung statistik
$user_id = (int)$_SESSION['user_id'];
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

// Total Keuntungan Hari Ini
$query_detail_today = "SELECT dp.unit, dp.jumlah, dp.subtotal, b.harga_beli, b.isi_renteng, b.isi_pax, b.isi_slop, b.unit_type
                       FROM penjualan p 
                       JOIN detail_penjualan dp ON p.id = dp.penjualan_id 
                       JOIN barang b ON dp.barang_id = b.id 
                       WHERE p.tanggal = '$today'";
if ($role != 'anak') {
    $query_detail_today .= " AND dp.owner_id = $user_id";
}
$result_detail_today = mysqli_query($conn, $query_detail_today);
$total_modal_today = 0;
$total_pendapatan_today = 0; // Hanya pendapatan barang milik user tsb

while ($row = mysqli_fetch_assoc($result_detail_today)) {
    $total_pendapatan_today += $row['subtotal'];
    $modal_satuan = $row['harga_beli'];
    $jumlah_pcs = $row['jumlah'];

    if ($row['unit_type'] === 'gram' || $row['unit'] === 'gram' || $row['unit'] === 'gram (custom)') {
        $total_modal_today += (($modal_satuan / 1000) * $row['jumlah']);
        continue;
    }

    if ($row['unit'] === 'renteng') {
        $jumlah_pcs = $row['jumlah'] * max($row['isi_renteng'], 1);
    } elseif ($row['unit'] === 'pax') {
        $jumlah_pcs = $row['jumlah'] * max($row['isi_pax'], 1);
    } elseif ($row['unit'] === 'slop') {
        $jumlah_pcs = $row['jumlah'] * max($row['isi_slop'], 1);
    }

    $total_modal_today += ($modal_satuan * $jumlah_pcs);
}
$total_keuntungan_today = max($total_pendapatan_today - $total_modal_today, 0);

// Aset Beli (Total Harga Barang Masuk) & Aset Jual (Total Omset Potensial)
if ($role == 'anak') {
    $query_aset = "SELECT 
                   SUM(CASE WHEN unit_type = 'gram' THEN (stok / 1000) * harga_beli ELSE stok * harga_beli END) as aset_beli,
                   SUM(CASE WHEN unit_type = 'gram' THEN (stok / 1000) * harga_jual ELSE stok * harga_jual END) as aset_jual
                   FROM barang";
} else {
    $query_aset = "SELECT
                   SUM(CASE WHEN unit_type = 'gram' THEN (stok / 1000) * harga_beli ELSE stok * harga_beli END) as aset_beli,
                   SUM(CASE WHEN unit_type = 'gram' THEN (stok / 1000) * harga_jual ELSE stok * harga_jual END) as aset_jual
                   FROM barang WHERE owner_id = $user_id";
}
$result_aset = mysqli_query($conn, $query_aset);
$row_aset = mysqli_fetch_assoc($result_aset);
$aset_beli = $row_aset['aset_beli'] ?? 0;
$aset_jual = $row_aset['aset_jual'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Toko Rahmat Jaya</title>
    <link rel="icon" type="image/png" sizes="16x16" href="icons/16×16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/32×32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="icons/48×48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/192×192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/512×512.png">
    <link rel="apple-touch-icon" href="icons/180×180.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>

<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Toko Rahmat Jaya</h1>
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <h3 class="text-gray-600 text-sm font-semibold mb-1">Penjualan Hari Ini</h3>
                <p class="text-2xl font-bold text-gray-800"><?php echo formatRupiah($total_today); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <h3 class="text-gray-600 text-sm font-semibold mb-1">Keuntungan Hari Ini</h3>
                <p class="text-2xl font-bold text-gray-800"><?php echo formatRupiah($total_keuntungan_today); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                <h3 class="text-gray-600 text-sm font-semibold mb-1">Jumlah Item Stok</h3>
                <p class="text-2xl font-bold text-gray-800"><?php echo $total_stok; ?> Item</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <h3 class="text-gray-600 text-sm font-semibold mb-1">Penjualan Bulan Ini</h3>
                <p class="text-2xl font-bold text-gray-800"><?php echo formatRupiah($total_month); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                <h3 class="text-gray-600 text-sm font-semibold mb-1">Total Aset (Harga Beli)</h3>
                <p class="text-2xl font-bold text-gray-800" title="Total Modal Barang Saat Ini"><?php echo formatRupiah($aset_beli); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-500">
                <h3 class="text-gray-600 text-sm font-semibold mb-1">Total Potensi Omset (Harga Jual)</h3>
                <p class="text-2xl font-bold text-gray-800" title="Total Nilai Jual Barang Saat Ini"><?php echo formatRupiah($aset_jual); ?></p>
            </div>
        </div>

        <!-- Menu Utama -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="barang" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition text-center group border border-transparent hover:border-blue-200">
                <div class="text-4xl mb-3 text-blue-600 group-hover:scale-110 transition-transform"><i class="ph ph-package"></i></div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">Data Barang</h2>
                <p class="text-sm text-gray-500">Kelola master stok barang</p>
            </a>

            <a href="stok_masuk" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition text-center group border border-transparent hover:border-green-200">
                <div class="text-4xl mb-3 text-green-600 group-hover:scale-110 transition-transform"><i class="ph ph-archive-box"></i></div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">Stok Masuk (Restock)</h2>
                <p class="text-sm text-gray-500">Tambah stok & riwayat masuk</p>
            </a>

            <a href="penjualan" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition text-center group border border-transparent hover:border-emerald-200">
                <div class="text-4xl mb-3 text-emerald-600 group-hover:scale-110 transition-transform"><i class="ph ph-hand-coins"></i></div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">Kasir Penjualan</h2>
                <p class="text-sm text-gray-500">Catat transaksi penjualan</p>
            </a>

            <a href="laporan" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition text-center group border border-transparent hover:border-purple-200">
                <div class="text-4xl mb-3 text-purple-600 group-hover:scale-110 transition-transform"><i class="ph ph-chart-line"></i></div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">Laporan Penjualan</h2>
                <p class="text-sm text-gray-500">Lihat rekapan & keuntungan</p>
            </a>

            <a href="analisis_stok" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition text-center group border border-transparent hover:border-orange-200">
                <div class="text-4xl mb-3 text-orange-500 group-hover:scale-110 transition-transform"><i class="ph ph-trend-up"></i></div>
                <h2 class="text-lg font-bold text-gray-800 mb-1">Analisis Stok</h2>
                <p class="text-sm text-gray-500">Pantau barang laris & habis</p>
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