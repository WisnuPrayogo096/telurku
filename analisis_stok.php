<?php
require_once 'config.php';
requireLogin();

// 1. BARANG HABIS
$query_habis = "SELECT b.* FROM barang b WHERE b.stok <= 0 ORDER BY b.nama_barang ASC";
$result_habis = mysqli_query($conn, $query_habis);

// 2. HAMPIR HABIS — ambang disesuaikan per satuan
$query_hampir_habis = "SELECT b.* FROM barang b
    WHERE b.stok > 0
    AND (
        (b.unit_type = 'gram' AND b.stok < 5000)
        OR (b.unit_type = 'renteng' AND b.isi_renteng > 0 AND b.stok < b.isi_renteng)
        OR (b.unit_type NOT IN ('gram', 'renteng') AND b.stok < 10)
        OR (b.unit_type = 'renteng' AND (b.isi_renteng IS NULL OR b.isi_renteng = 0) AND b.stok < 10)
    )
    ORDER BY b.stok ASC, b.nama_barang ASC";
$result_hampir_habis = mysqli_query($conn, $query_hampir_habis);

function getMovingItems($conn, $interval_days, $is_fast, $limit = 10)
{
    $order_dir = $is_fast ? 'DESC' : 'ASC';
    $having = $is_fast ? 'HAVING total_terjual > 0' : '';
    $stok_filter = $is_fast ? '' : 'AND b.stok > 0';

    $query = "SELECT b.id, b.nama_barang, b.unit_type, b.isi_renteng, b.stok,
              COALESCE(SUM(
                  CASE
                      WHEN p.id IS NULL THEN 0
                      WHEN dp.unit = 'renteng' THEN dp.jumlah * GREATEST(b.isi_renteng, 1)
                      WHEN dp.unit = '1 kg' THEN dp.jumlah * 1000
                      WHEN dp.unit IN ('gram', 'gram (custom)') THEN dp.jumlah
                      ELSE dp.jumlah
                  END
              ), 0) AS total_terjual
              FROM barang b
              LEFT JOIN detail_penjualan dp ON b.id = dp.barang_id
              LEFT JOIN penjualan p ON dp.penjualan_id = p.id
                  AND p.tanggal >= DATE_SUB(NOW(), INTERVAL $interval_days DAY)
              WHERE 1=1 $stok_filter
              GROUP BY b.id, b.nama_barang, b.unit_type, b.isi_renteng, b.stok
              $having
              ORDER BY total_terjual $order_dir, b.nama_barang ASC
              LIMIT $limit";

    return mysqli_query($conn, $query);
}

function formatTerjualLabel($row)
{
    $qty = formatQty($row['total_terjual']);
    if (($row['unit_type'] ?? '') === 'renteng' && (int)($row['isi_renteng'] ?? 0) > 0) {
        $renteng = formatQty($row['total_terjual'] / max((int)$row['isi_renteng'], 1));
        return $qty . ' pcs (~' . $renteng . ' renteng)';
    }
    return $qty . ' ' . unitLabel($row['unit_type'] ?? 'pcs');
}

function formatStokLabel($row)
{
    if (($row['unit_type'] ?? '') === 'renteng' && (int)($row['isi_renteng'] ?? 0) > 0) {
        return formatQty($row['stok'] / max((int)$row['isi_renteng'], 1)) . ' renteng';
    }
    return formatQty($row['stok']) . ' ' . unitLabel($row['unit_type'] ?? 'pcs');
}

$fast_mingguan = getMovingItems($conn, 7, true);
$fast_bulanan = getMovingItems($conn, 30, true);
$slow_mingguan = getMovingItems($conn, 7, false);
$slow_bulanan = getMovingItems($conn, 30, false);

$pageTitle = 'Analisis Stok - Toko Rahmat Jaya';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">';
require_once 'includes/head.php';
$navTitle = 'Analisis Stok';
$navBackUrl = 'index';
require_once 'includes/navbar.php';
?>

<div class="app-container max-w-6xl">
    <div class="app-tabs">
        <button type="button" id="btn-tab-alert" onclick="showTab('tab-alert')" class="app-tab active">
            <i class="ph ph-warning-circle"></i> Alert Stok
        </button>
        <button type="button" id="btn-tab-fast" onclick="showTab('tab-fast')" class="app-tab">
            <i class="ph ph-rocket"></i> Fast Moving
        </button>
        <button type="button" id="btn-tab-slow" onclick="showTab('tab-slow')" class="app-tab">
            <i class="ph ph-snail"></i> Slow Moving
        </button>
    </div>

    <div id="tab-alert" class="tab-content">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="app-panel overflow-hidden border-t-4 border-t-red-500">
                <div class="app-panel-header !bg-red-50">
                    <span class="app-panel-title text-red-800"><i class="ph ph-x-circle"></i> Barang Habis</span>
                </div>
                <div class="p-4">
                <table class="w-full analisis-table" id="tableHabis">
                    <thead><tr class="text-left text-sm text-gray-600"><th>Barang</th><th></th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_habis) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result_habis)): ?>
                                <tr>
                                    <td class="py-2 font-medium"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                    <td class="py-2 text-right"><a href="stok_masuk" class="btn btn-secondary !py-1 !px-2 text-xs">Restock</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="py-4 text-center text-gray-500">Tidak ada barang habis.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <div class="app-panel overflow-hidden border-t-4 border-t-orange-400">
                <div class="app-panel-header !bg-orange-50">
                    <span class="app-panel-title text-orange-800"><i class="ph ph-warning"></i> Hampir Habis</span>
                </div>
                <div class="p-4">
                <table class="w-full analisis-table" id="tableHampir">
                    <thead><tr class="text-left text-sm text-gray-600"><th>Barang</th><th class="text-right">Sisa</th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_hampir_habis) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result_hampir_habis)): ?>
                                <tr>
                                    <td class="py-2 font-medium"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                    <td class="py-2 text-right text-orange-600 font-semibold"><?php echo formatStokLabel($row); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="py-4 text-center text-gray-500">Stok masih aman.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <div id="tab-fast" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php
            $fast_tables = [
                ['id' => 'fastMinggu', 'title' => 'Top Laris (7 Hari)', 'result' => $fast_mingguan],
                ['id' => 'fastBulan', 'title' => 'Top Laris (30 Hari)', 'result' => $fast_bulanan],
            ];
            foreach ($fast_tables as $ft):
            ?>
            <div class="app-panel overflow-hidden">
                <div class="app-panel-header !bg-emerald-50">
                    <span class="app-panel-title text-emerald-800"><?php echo $ft['title']; ?></span>
                </div>
                <div class="p-4">
                <table class="w-full analisis-table" id="<?php echo $ft['id']; ?>">
                    <thead><tr class="text-sm text-gray-600"><th>Barang</th><th class="text-right">Terjual</th></tr></thead>
                    <tbody>
                        <?php if (mysqli_num_rows($ft['result']) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($ft['result'])): ?>
                                <tr>
                                    <td class="py-2"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                    <td class="py-2 text-right font-semibold text-green-600"><?php echo formatTerjualLabel($row); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="py-4 text-center text-gray-500">Belum ada penjualan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="tab-slow" class="tab-content hidden">
        <p class="app-alert app-alert-info mb-4">
            Barang dengan penjualan paling sedikit dalam periode tertentu (stok &gt; 0).
        </p>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php
            $slow_tables = [
                ['id' => 'slowMinggu', 'title' => 'Kurang Laku (7 Hari)', 'result' => $slow_mingguan],
                ['id' => 'slowBulan', 'title' => 'Kurang Laku (30 Hari)', 'result' => $slow_bulanan],
            ];
            foreach ($slow_tables as $st):
            ?>
            <div class="app-panel overflow-hidden">
                <div class="app-panel-header">
                    <span class="app-panel-title"><?php echo $st['title']; ?></span>
                </div>
                <div class="p-4">
                <table class="w-full analisis-table" id="<?php echo $st['id']; ?>">
                    <thead><tr class="text-sm text-gray-600"><th>Barang</th><th class="text-center">Stok</th><th class="text-right">Terjual</th></tr></thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($st['result'])): ?>
                            <tr>
                                <td class="py-2"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                <td class="py-2 text-center text-gray-600"><?php echo formatStokLabel($row); ?></td>
                                <td class="py-2 text-right font-semibold <?php echo $row['total_terjual'] == 0 ? 'text-red-500' : 'text-gray-700'; ?>"><?php echo formatTerjualLabel($row); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.app-tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.remove('hidden');
    document.getElementById('btn-' + tabId).classList.add('active');
}
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="assets/js/datatables-default.js"></script>
<script>
$(function() {
    $('.analisis-table').each(function() {
        if ($(this).find('tbody tr td[colspan]').length) return;
        initDefaultDataTable(this);
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
