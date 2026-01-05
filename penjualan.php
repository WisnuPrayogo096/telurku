<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

// Proses Penjualan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_jual'])) {
    $tanggal = $_POST['tanggal'];
    $metode_bayar = $_POST['metode_bayar'];
    $barang_ids = $_POST['barang_id'];
    $jumlahs = $_POST['jumlah'];

    $total_bayar = 0;
    $items = [];

    // Validasi dan hitung total
    foreach ($barang_ids as $index => $barang_id) {
        if (empty($barang_id)) continue;

        $jumlah = $jumlahs[$index];
        if ($jumlah <= 0) continue;

        // Ambil data barang
        $query = "SELECT * FROM barang WHERE id = $barang_id";
        $result = mysqli_query($conn, $query);
        $barang = mysqli_fetch_assoc($result);

        if (!$barang) {
            $error = "Barang tidak ditemukan!";
            break;
        }

        if ($barang['stok'] < $jumlah) {
            $error = "Stok {$barang['nama_barang']} tidak cukup!";
            break;
        }

        $subtotal = $barang['harga_jual'] * $jumlah;
        $total_bayar += $subtotal;

        $items[] = [
            'barang_id' => $barang_id,
            'jumlah' => $jumlah,
            'harga_satuan' => $barang['harga_jual'],
            'subtotal' => $subtotal,
            'owner_id' => $barang['owner_id']
        ];
    }

    // Simpan transaksi
    if (empty($error) && count($items) > 0) {
        mysqli_begin_transaction($conn);

        try {
            // Insert penjualan
            $query = "INSERT INTO penjualan (tanggal, total_bayar, metode_bayar) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sds", $tanggal, $total_bayar, $metode_bayar);
            mysqli_stmt_execute($stmt);
            $penjualan_id = mysqli_insert_id($conn);

            // Insert detail dan update stok
            foreach ($items as $item) {
                $query = "INSERT INTO detail_penjualan (penjualan_id, barang_id, jumlah, harga_satuan, subtotal, owner_id) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param(
                    $stmt,
                    "iiiddi",
                    $penjualan_id,
                    $item['barang_id'],
                    $item['jumlah'],
                    $item['harga_satuan'],
                    $item['subtotal'],
                    $item['owner_id']
                );
                mysqli_stmt_execute($stmt);

                // Update stok
                $query = "UPDATE barang SET stok = stok - ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ii", $item['jumlah'], $item['barang_id']);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);
            $success = "Penjualan berhasil disimpan! Total: " . formatRupiah($total_bayar);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Gagal menyimpan transaksi!";
        }
    }
}

// Ambil data barang
$barang_query = "SELECT b.*, u.nama as owner_nama FROM barang b 
                 JOIN users u ON b.owner_id = u.id 
                 WHERE b.stok > 0 
                 ORDER BY b.nama_barang";
$barang_result = mysqli_query($conn, $barang_query);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Transaksi Penjualan</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded hover:bg-blue-800">Kembali</a>
        </div>
    </nav>

    <div class="container mx-auto p-4">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Form Penjualan</h2>
            <form method="POST" action="" id="formPenjualan">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Tanggal</label>
                        <input type="date" name="tanggal" required
                            value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Metode Pembayaran</label>
                        <select name="metode_bayar" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="tunai">Tunai</option>
                            <option value="qris">QRIS</option>
                        </select>
                    </div>
                </div>

                <div id="itemContainer">
                    <div class="border rounded-lg p-4 mb-4 item-row">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 font-medium mb-2">Pilih Barang</label>
                                <select name="barang_id[]" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 barang-select">
                                    <option value="">-- Pilih Barang --</option>
                                    <?php
                                    mysqli_data_seek($barang_result, 0);
                                    while ($barang = mysqli_fetch_assoc($barang_result)):
                                    ?>
                                        <option value="<?php echo $barang['id']; ?>"
                                            data-harga="<?php echo $barang['harga_jual']; ?>"
                                            data-stok="<?php echo $barang['stok']; ?>">
                                            <?php echo $barang['nama_barang']; ?> (<?php echo $barang['owner_nama']; ?>) - Stok: <?php echo $barang['stok']; ?> - <?php echo formatRupiah($barang['harga_jual']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Jumlah</label>
                                <input type="number" name="jumlah[]" min="1"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 jumlah-input">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="tambahItem()"
                    class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 mb-4">
                    + Tambah Item
                </button>

                <div class="border-t pt-4">
                    <button type="submit" name="proses_jual"
                        class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 text-lg font-medium">
                        Proses Penjualan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function tambahItem() {
            const container = document.getElementById('itemContainer');
            const newItem = container.children[0].cloneNode(true);

            // Reset values
            newItem.querySelectorAll('select, input').forEach(el => {
                el.value = '';
            });

            container.appendChild(newItem);
        }
    </script>
</body>

</html>