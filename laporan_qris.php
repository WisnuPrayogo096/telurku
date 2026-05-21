<?php
require_once 'config.php';
requireLogin();

// Filter (escape untuk prevent SQL injection)
$bulan = mysqli_real_escape_string($conn, $_GET['bulan'] ?? date('Y-m')); // Menggunakan timezone GMT+7 dari config.php

// Query transaksi QRIS
$query = "SELECT 
            p.id,
            p.tanggal,
            p.total_bayar
          FROM penjualan p
          WHERE p.metode_bayar = 'qris'
          AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$bulan'";

$query .= " GROUP BY p.id ORDER BY p.tanggal DESC";

$result = mysqli_query($conn, $query);

// Total QRIS
$total_query = "SELECT SUM(p.total_bayar) as total_qris
                FROM penjualan p
                WHERE p.metode_bayar = 'qris' 
                AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$bulan'";

$total_result = mysqli_query($conn, $total_query);
$total_qris = mysqli_fetch_assoc($total_result)['total_qris'] ?? 0;

// Hitung jumlah transaksi
$count_transaksi = mysqli_num_rows($result);
$pageTitle = 'Laporan QRIS - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Laporan Pembayaran QRIS';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
?>

<div class="app-container">
    <div class="app-panel mb-6">
        <div class="app-panel-header">
            <span class="app-panel-title"><i class="ph ph-calendar text-amber-600"></i> Pilih Periode</span>
        </div>
        <div class="app-panel-body">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="app-label">Bulan</label>
                    <input type="month" name="bulan" value="<?php echo $bulan; ?>" class="app-input">
                </div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto"><i class="ph ph-magnifying-glass"></i> Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="stat-grid !grid-cols-1 md:!grid-cols-2 mb-6">
        <div class="stat-card stat-card--purple">
            <div class="stat-card-icon"><i class="ph ph-qr-code"></i></div>
            <div class="stat-card-label">Transaksi QRIS</div>
            <div class="stat-card-value"><?php echo $count_transaksi; ?></div>
            <div class="stat-card-hint">Bulan <?php echo $bulan; ?></div>
        </div>
        <div class="stat-card stat-card--green">
            <div class="stat-card-icon"><i class="ph ph-wallet"></i></div>
            <div class="stat-card-label">Total QRIS</div>
            <div class="stat-card-value"><?php echo formatRupiah($total_qris); ?></div>
            <div class="stat-card-hint">Akumulasi pembayaran QRIS</div>
        </div>
    </div>

    <div class="app-alert app-alert-info mb-6">
        <i class="ph ph-device-mobile text-xl"></i>
        <span>Menampilkan semua transaksi dengan metode pembayaran <strong>QRIS</strong>.</span>
    </div>

    <div class="app-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full" id="qrisTable">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left">No</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-right">Total Bayar</th>
                            <th class="px-4 py-3 text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr class="border-b hover:bg-amber-50/40">
                                <td class="px-4 py-3"><?php echo $no++; ?></td>
                                <td class="px-4 py-3"><?php echo formatTanggal($row['tanggal']); ?></td>
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
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    Tidak ada transaksi QRIS untuk bulan ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($count_transaksi > 0): ?>
                        <tfoot class="bg-gray-100">
                            <tr class="font-bold">
                                <td colspan="2" class="px-4 py-3 text-right">TOTAL</td>
                                <td class="px-4 py-3 text-right text-green-600"><?php echo formatRupiah($total_qris); ?></td>
                                <td></td>
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
            $('#qrisTable').DataTable({
                pageLength: 25,
                order: [[1, 'desc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/id.json' }
            });
        });
    </script>
<?php require_once 'includes/footer.php'; ?>
