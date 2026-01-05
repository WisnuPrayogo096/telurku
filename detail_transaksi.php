<?php
require_once 'config.php';
requireLogin();

$id = $_GET['id'] ?? 0;

// Ambil data penjualan
$query = "SELECT * FROM penjualan WHERE id = $id";
$result = mysqli_query($conn, $query);
$penjualan = mysqli_fetch_assoc($result);

if (!$penjualan) {
    header("Location: index");
    exit();
}

// Ambil detail items
$detail_query = "SELECT 
                    dp.*,
                    b.nama_barang,
                    u.nama as owner_nama
                 FROM detail_penjualan dp
                 JOIN barang b ON dp.barang_id = b.id
                 JOIN users u ON dp.owner_id = u.id
                 WHERE dp.penjualan_id = $id";

$detail_result = mysqli_query($conn, $detail_query);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Detail Transaksi</h1>
            <a href="javascript:history.back()" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800">Kembali</a>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-4xl">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="text-gray-600 text-sm">Tanggal Transaksi</p>
                    <p class="font-medium"><?php echo formatTanggal($penjualan['tanggal']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Metode Pembayaran</p>
                    <p class="font-medium">
                        <span class="px-3 py-1 rounded-full text-sm
                            <?php echo $penjualan['metode_bayar'] == 'qris' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo strtoupper($penjualan['metode_bayar']); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="border-t pt-4">
                <h3 class="font-bold text-lg mb-4">Item Terjual</h3>
                <div class="space-y-3">
                    <?php while ($item = mysqli_fetch_assoc($detail_result)): ?>
                        <div class="flex justify-between items-start border-b pb-3">
                            <div class="flex-1">
                                <p class="font-medium"><?php echo $item['nama_barang']; ?></p>
                                <p class="text-sm text-gray-600">
                                    Pemilik:
                                    <span class="px-2 py-0.5 rounded-full text-xs
                                    <?php echo $item['owner_nama'] == 'Ibu' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo $item['owner_nama']; ?>
                                    </span>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <?php echo $item['jumlah']; ?> x <?php echo formatRupiah($item['harga_satuan']); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium"><?php echo formatRupiah($item['subtotal']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="mt-6 pt-4 border-t">
                    <div class="flex justify-between items-center">
                        <p class="text-xl font-bold">TOTAL</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo formatRupiah($penjualan['total_bayar']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center">
            <button onclick="window.print()" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                🖨️ Cetak Struk
            </button>
        </div>
    </div>

    <style>
        @media print {

            nav,
            button {
                display: none;
            }

            body {
                background: white;
            }
        }
    </style>
</body>

</html>