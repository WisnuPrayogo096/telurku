<?php
require_once 'config.php';
requireLogin();

// Filter (escape untuk prevent SQL injection)
$dari_tanggal = mysqli_real_escape_string($conn, $_GET['dari'] ?? getCurrentDate());
$sampai_tanggal = mysqli_real_escape_string($conn, $_GET['sampai'] ?? getCurrentDate());

$query = "SELECT 
            p.tanggal,
            b.nama_barang,
            dp.unit,
            dp.jumlah,
            dp.harga_satuan,
            dp.subtotal
          FROM detail_penjualan dp
          JOIN penjualan p ON dp.penjualan_id = p.id
          JOIN barang b ON dp.barang_id = b.id
          WHERE DATE(p.tanggal) BETWEEN '$dari_tanggal' AND '$sampai_tanggal'
          ORDER BY p.tanggal DESC, p.id DESC";

$result = mysqli_query($conn, $query);
$pageTitle = 'Laporan Penjualan - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Laporan Penjualan';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
?>

<div class="app-container">
    <div class="app-panel mb-6">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-funnel text-amber-600"></i> Filter Laporan</span>
        </div>
        <div class="app-panel-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="app-label">Dari Tanggal</label>
                    <input type="date" name="dari" value="<?php echo $dari_tanggal; ?>" class="app-input">
                </div>
                <div>
                    <label class="app-label">Sampai Tanggal</label>
                    <input type="date" name="sampai" value="<?php echo $sampai_tanggal; ?>" class="app-input">
                </div>
                <button type="submit" class="btn btn-primary w-full md:w-auto"><i class="ph ph-magnifying-glass"></i> Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="app-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full" id="laporanTable">
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
                            <tr class="border-b hover:bg-amber-50/40">
                                <td class="px-4 py-3 text-sm text-gray-600"><?php echo date('d/m/Y H:i', strtotime($row['tanggal'])); ?></td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                    <!-- <div class="text-xs text-gray-500">Pemilik: <?php echo $row['owner_nama']; ?></div> -->
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-blue"><?php echo htmlspecialchars($row['unit']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-center font-medium"><?php echo formatQty($row['jumlah']); ?></td>
                                <td class="px-4 py-3 text-right text-gray-600"><?php echo formatRupiah($row['harga_satuan']); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-amber-700"><?php echo formatRupiah($row['subtotal']); ?></td>
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
                                <td class="px-4 py-3 text-right text-amber-700 text-lg"><?php echo formatRupiah($grand_total_penjualan); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
    </div>
</div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script>
        $(function() {
            $('#laporanTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/id.json' }
            });
        });
    </script>
<?php require_once 'includes/footer.php'; ?>
