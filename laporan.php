<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter
$dari_tanggal = $_GET['dari'] ?? date('Y-m-01');
$sampai_tanggal = $_GET['sampai'] ?? date('Y-m-d');
$metode = $_GET['metode'] ?? 'semua';

// Query laporan berdasarkan permission
if ($role == 'anak') {
    // Anak bisa lihat semua
    $where_owner = "";
} else {
    // Ibu hanya lihat miliknya
    $where_owner = "AND dp.owner_id = $user_id";
}

$where_metode = ($metode != 'semua') ? "AND p.metode_bayar = '$metode'" : "";

$query = "SELECT 
            DATE(p.tanggal) as tanggal,
            u.nama as owner_nama,
            COUNT(DISTINCT p.id) as jumlah_transaksi,
            SUM(dp.subtotal) as total_penjualan,
            SUM(dp.subtotal - (b.harga_beli * dp.jumlah)) as keuntungan
          FROM detail_penjualan dp
          JOIN penjualan p ON dp.penjualan_id = p.id
          JOIN users u ON dp.owner_id = u.id
          JOIN barang b ON dp.barang_id = b.id
          WHERE p.tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
          $where_owner
          $where_metode
          GROUP BY DATE(p.tanggal), dp.owner_id
          ORDER BY p.tanggal DESC, u.nama";

$result = mysqli_query($conn, $query);

// Total keseluruhan
$total_query = "SELECT 
                  SUM(dp.subtotal) as total_penjualan,
                  SUM(dp.subtotal - (b.harga_beli * dp.jumlah)) as total_keuntungan
                FROM detail_penjualan dp
                JOIN penjualan p ON dp.penjualan_id = p.id
                JOIN barang b ON dp.barang_id = b.id
                WHERE p.tanggal BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
                $where_owner
                $where_metode";

$total_result = mysqli_query($conn, $total_query);
$total_data = mysqli_fetch_assoc($total_result);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - TELURKU</title>
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
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Metode Pembayaran</label>
                        <select name="metode" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="semua" <?php echo ($metode == 'semua') ? 'selected' : ''; ?>>Semua</option>
                            <option value="tunai" <?php echo ($metode == 'tunai') ? 'selected' : ''; ?>>Tunai</option>
                            <option value="qris" <?php echo ($metode == 'qris') ? 'selected' : ''; ?>>QRIS</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Total Penjualan</h3>
                <p class="text-2xl font-bold text-blue-600">
                    <?php echo formatRupiah($total_data['total_penjualan'] ?? 0); ?>
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-600 text-sm mb-2">Total Keuntungan</h3>
                <p class="text-2xl font-bold text-green-600">
                    <?php echo formatRupiah($total_data['total_keuntungan'] ?? 0); ?>
                </p>
            </div>
        </div>

        <!-- Tabel Laporan -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Pemilik Stok</th>
                            <th class="px-4 py-3 text-center">Jumlah Transaksi</th>
                            <th class="px-4 py-3 text-right">Total Penjualan</th>
                            <th class="px-4 py-3 text-right">Keuntungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grand_total_penjualan = 0;
                        $grand_total_keuntungan = 0;

                        while ($row = mysqli_fetch_assoc($result)):
                            $grand_total_penjualan += $row['total_penjualan'];
                            $grand_total_keuntungan += $row['keuntungan'];
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?php echo formatTanggal($row['tanggal']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-sm font-medium
                                    <?php echo $row['owner_nama'] == 'Ibu' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo $row['owner_nama']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center"><?php echo $row['jumlah_transaksi']; ?></td>
                                <td class="px-4 py-3 text-right font-medium"><?php echo formatRupiah($row['total_penjualan']); ?></td>
                                <td class="px-4 py-3 text-right font-medium text-green-600"><?php echo formatRupiah($row['keuntungan']); ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    Tidak ada data untuk periode ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>