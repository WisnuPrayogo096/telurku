<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

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
                    b.unit_type
                 FROM detail_penjualan dp
                 JOIN barang b ON dp.barang_id = b.id
                 WHERE dp.penjualan_id = $id";

$detail_result = mysqli_query($conn, $detail_query);
$pageTitle = 'Detail Transaksi - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Detail Transaksi';
$navBackUrl = 'javascript:history.back()';
require_once 'includes/navbar.php';
?>

<div class="app-container max-w-4xl">
    <div class="app-panel mb-6">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-receipt text-amber-600"></i> Detail Transaksi #<?php echo $id; ?></span>
        </div>
        <div class="app-panel-body">
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="text-gray-600 text-sm">Tanggal Transaksi</p>
                    <p class="font-medium"><?php echo formatTanggal($penjualan['tanggal']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Metode Pembayaran</p>
                    <p class="font-medium">
                        <span class="badge <?php echo $penjualan['metode_bayar'] == 'qris' ? 'badge-blue' : 'badge-green'; ?>">
                            <?php echo strtoupper($penjualan['metode_bayar']); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="border-t pt-4 overflow-x-auto">
                <h3 class="font-bold text-lg mb-4">Item Terjual</h3>
                <table class="w-full" id="detailItemTable">
                    <thead class="bg-gray-100 text-sm">
                        <tr>
                            <th class="px-3 py-2 text-left">Barang</th>
                            <th class="px-3 py-2 text-center">Qty</th>
                            <th class="px-3 py-2 text-right">Harga</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = mysqli_fetch_assoc($detail_result)): ?>
                            <tr class="border-b text-sm">
                                <td class="px-3 py-2 font-medium"><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                                <td class="px-3 py-2 text-center"><?php echo formatQty($item['jumlah']); ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></td>
                                <td class="px-3 py-2 text-right"><?php echo formatRupiah($item['harga_satuan']); ?></td>
                                <td class="px-3 py-2 text-right font-medium"><?php echo formatRupiah($item['subtotal']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div class="mt-6 pt-4 border-t">
                    <div class="flex justify-between items-center">
                        <p class="text-xl font-bold">TOTAL</p>
                        <p class="text-2xl font-extrabold text-amber-600"><?php echo formatRupiah($penjualan['total_bayar']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center">
        <button type="button" onclick="window.print()" class="btn btn-secondary">
            <i class="ph ph-printer"></i> Cetak Struk
        </button>
    </div>
</div>

    <style>@media print{nav,button{display:none}body{background:#fff}}</style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatables-default.js"></script>
    <script>
    $(function() {
        initDefaultDataTable('#detailItemTable');
    });
    </script>
<?php require_once 'includes/footer.php'; ?>
