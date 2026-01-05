<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter
$bulan = $_GET['bulan'] ?? date('Y-m');

// Query transaksi QRIS
$query = "SELECT 
            p.id,
            p.tanggal,
            p.total_bayar,
            GROUP_CONCAT(DISTINCT u.nama) as pemilik_stok
          FROM penjualan p
          JOIN detail_penjualan dp ON p.id = dp.penjualan_id
          JOIN users u ON dp.owner_id = u.id
          WHERE p.metode_bayar = 'qris'
          AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$bulan'";

if ($role == 'ibu') {
    $query .= " AND dp.owner_id = $user_id";
}

$query .= " GROUP BY p.id ORDER BY p.tanggal DESC";

$result = mysqli_query($conn, $query);

// Total QRIS
$total_query = "SELECT SUM(p.total_bayar) as total_qris
                FROM penjualan p";

if ($role == 'ibu') {
    $total_query .= " JOIN detail_penjualan dp ON p.id = dp.penjualan_id
                     WHERE p.metode_bayar = 'qris' 
                     AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$bulan'
                     AND dp.owner_id = $user_id";
} else {
    $total_query .= " WHERE p.metode_bayar = 'qris' 
                      AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$bulan'";
}

$total_result = mysqli_query($conn, $total_query);
$total_qris = mysqli_fetch_assoc($total_result)['total_qris'] ?? 0;

// Hitung jumlah transaksi
$count_transaksi = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan QRIS - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Laporan Pembayaran QRIS</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800">Kembali</a>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <!-- Filter -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Pilih Periode</h2>
            <form method="GET" action="" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-gray-700 font-medium mb-2">Bulan</label>
                    <input type="month" name="bulan" value="<?php echo $bulan; ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                    Tampilkan
                </button>
            </form>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Total Transaksi QRIS</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo $count_transaksi; ?> Transaksi</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Total Penjualan QRIS</h3>
                <p class="text-2xl font-bold text-green-600"><?php echo formatRupiah($total_qris); ?></p>
            </div>
        </div>

        <!-- Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-2">
                <span class="text-2xl">📱</span>
                <div>
                    <h3 class="font-medium text-blue-900 mb-1">Tentang Laporan QRIS</h3>
                    <p class="text-blue-800 text-sm">
                        Laporan ini menampilkan semua transaksi yang dibayar menggunakan QRIS.
                        <?php if ($role == 'ibu'): ?>
                            Anda hanya melihat transaksi yang melibatkan stok Anda.
                        <?php else: ?>
                            Anda dapat melihat semua transaksi QRIS dari kedua pemilik stok.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabel Transaksi -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left">No</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Pemilik Stok</th>
                            <th class="px-4 py-3 text-right">Total Bayar</th>
                            <th class="px-4 py-3 text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?php echo $no++; ?></td>
                                <td class="px-4 py-3"><?php echo formatTanggal($row['tanggal']); ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $owners = explode(',', $row['pemilik_stok']);
                                    foreach ($owners as $owner):
                                    ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium mr-1
                                    <?php echo trim($owner) == 'Ibu' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo trim($owner); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-green-600">
                                    <?php echo formatRupiah($row['total_bayar']); ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="detail_transaksi?id=<?php echo $row['id']; ?>"
                                        class="text-blue-600 hover:underline">
                                        Lihat Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if ($count_transaksi == 0): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    Tidak ada transaksi QRIS untuk bulan ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($count_transaksi > 0): ?>
                        <tfoot class="bg-gray-100">
                            <tr class="font-bold">
                                <td colspan="3" class="px-4 py-3 text-right">TOTAL</td>
                                <td class="px-4 py-3 text-right text-green-600"><?php echo formatRupiah($total_qris); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</body>

</html>