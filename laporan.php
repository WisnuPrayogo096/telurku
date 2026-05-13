<?php
require_once 'config.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter (escape untuk prevent SQL injection)
$dari_tanggal = mysqli_real_escape_string($conn, $_GET['dari'] ?? date('Y-m-01'));
$sampai_tanggal = mysqli_real_escape_string($conn, $_GET['sampai'] ?? date('Y-m-d'));

// Query laporan berdasarkan permission
if ($role == 'anak') {
    // Anak bisa lihat semua
    $where_owner = "";
} else {
    // Ibu hanya lihat miliknya
    $where_owner = "AND dp.owner_id = $user_id";
}

$query = "SELECT 
            p.tanggal,
            b.nama_barang,
            u.nama as owner_nama,
            dp.unit,
            dp.jumlah,
            dp.harga_satuan,
            dp.subtotal
          FROM detail_penjualan dp
          JOIN penjualan p ON dp.penjualan_id = p.id
          JOIN barang b ON dp.barang_id = b.id
          JOIN users u ON dp.owner_id = u.id
          WHERE DATE(p.tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
          $where_owner
          ORDER BY p.tanggal DESC, p.id DESC";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/16×16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/32×32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="icons/48×48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/192×192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/512×512.png">
    <link rel="apple-touch-icon" href="icons/180×180.png">
    <title>Laporan Penjualan - Toko Rahmat Jaya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Laporan Penjualan</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800">Kembali</a>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <!-- Filter -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Filter Laporan</h2>
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Dari Tanggal</label>
                        <input type="date" name="dari" value="<?php echo $dari_tanggal; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Sampai Tanggal</label>
                        <input type="date" name="sampai" value="<?php echo $sampai_tanggal; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Laporan -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left">Waktu Transaksi</th>
                            <th class="px-4 py-3 text-left">Nama Barang</th>
                            <th class="px-4 py-3 text-center">Unit/Satuan</th>
                            <th class="px-4 py-3 text-center">Qty</th>
                            <th class="px-4 py-3 text-right">Harga Satuan</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grand_total_penjualan = 0;

                        while ($row = mysqli_fetch_assoc($result)):
                            $grand_total_penjualan += $row['subtotal'];
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-600"><?php echo date('d/m/Y H:i', strtotime($row['tanggal'])); ?></td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                    <!-- <div class="text-xs text-gray-500">Pemilik: <?php echo $row['owner_nama']; ?></div> -->
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs"><?php echo htmlspecialchars($row['unit']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-center font-medium"><?php echo formatQty($row['jumlah']); ?></td>
                                <td class="px-4 py-3 text-right text-gray-600"><?php echo formatRupiah($row['harga_satuan']); ?></td>
                                <td class="px-4 py-3 text-right font-medium text-blue-600"><?php echo formatRupiah($row['subtotal']); ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Tidak ada data barang keluar untuk periode ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <tfoot class="bg-gray-100">
                            <tr class="font-bold">
                                <td colspan="5" class="px-4 py-3 text-right">TOTAL NILAI PENJUALAN</td>
                                <td class="px-4 py-3 text-right text-blue-700"><?php echo formatRupiah($grand_total_penjualan); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</body>

</html>