<?php
require_once 'config.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter owner
$where_owner_and = ($role == 'anak') ? "" : "AND b.owner_id = $user_id";

// 1. BARANG HABIS
$query_habis = "SELECT b.*, u.nama as owner_nama
                FROM barang b
                JOIN users u ON b.owner_id = u.id
                WHERE b.stok <= 0
                $where_owner_and
                ORDER BY b.nama_barang ASC";
$result_habis = mysqli_query($conn, $query_habis);

// 2. HAMPIR HABIS (Stok > 0 tapi < 10 pcs, atau < 5000 gram)
$query_hampir_habis = "SELECT b.*, u.nama as owner_nama
                       FROM barang b
                       JOIN users u ON b.owner_id = u.id
                       WHERE b.stok > 0
                       AND ((b.unit_type = 'gram' AND b.stok < 5000) OR (b.unit_type != 'gram' AND b.stok < 10))
                       $where_owner_and
                       ORDER BY b.stok ASC, b.nama_barang ASC";
$result_hampir_habis = mysqli_query($conn, $query_hampir_habis);

// Fungsi Helper Query Penjualan (Fast/Slow Moving)
function getMovingItems($conn, $where_owner_and, $interval_days, $is_fast, $limit = 10) {
    // Jika fast moving: barang yang terjual paling banyak.
    // Jika slow moving: barang yang paling sedikit terjual (termasuk yang 0).
    
    $order_dir = $is_fast ? "DESC" : "ASC";
    $stok_filter = $is_fast ? "" : "AND b.stok > 0";
    
    $query = "SELECT b.nama_barang, b.unit_type, b.stok, u.nama as owner_nama,
              COALESCE(SUM(CASE
                  WHEN p.id IS NULL THEN 0
                  WHEN dp.unit = 'renteng' THEN dp.jumlah * GREATEST(b.isi_renteng, 1)
                  WHEN dp.unit = 'pax' THEN dp.jumlah * GREATEST(b.isi_pax, 1)
                  WHEN dp.unit = 'slop' THEN dp.jumlah * GREATEST(b.isi_slop, 1)
                  WHEN dp.unit = '1 kg' THEN dp.jumlah * 1000
                  ELSE dp.jumlah
              END), 0) as total_terjual
              FROM barang b
              JOIN users u ON b.owner_id = u.id
              LEFT JOIN detail_penjualan dp ON b.id = dp.barang_id
              LEFT JOIN penjualan p ON dp.penjualan_id = p.id AND p.tanggal >= DATE_SUB(CURDATE(), INTERVAL $interval_days DAY)
              WHERE 1=1
              $where_owner_and
              $stok_filter
              GROUP BY b.id
              ORDER BY total_terjual $order_dir, b.nama_barang ASC
              LIMIT $limit";
              
    return mysqli_query($conn, $query);
}

// 3. FAST MOVING
$fast_mingguan = getMovingItems($conn, $where_owner_and, 7, true);
$fast_bulanan = getMovingItems($conn, $where_owner_and, 30, true);

// 4. SLOW MOVING
$slow_mingguan = getMovingItems($conn, $where_owner_and, 7, false);
$slow_bulanan = getMovingItems($conn, $where_owner_and, 30, false);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Stok - TELURKU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
                el.classList.add('border-transparent', 'text-gray-500');
            });
            document.getElementById(tabId).classList.remove('hidden');
            document.getElementById('btn-' + tabId).classList.remove('border-transparent', 'text-gray-500');
            document.getElementById('btn-' + tabId).classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        }
    </script>
</head>

<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-600 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold flex items-center gap-2"><i class="ph ph-trend-up"></i> Analisis Stok</h1>
            <a href="index" class="bg-blue-700 px-4 py-2 rounded-lg hover:bg-blue-800 transition flex items-center gap-2">
                <i class="ph ph-arrow-left"></i> Kembali
            </a>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-6xl">
        <!-- Tab Navigation -->
        <div class="flex border-b mb-6 bg-white rounded-t-lg shadow-sm overflow-x-auto">
            <button id="btn-tab-alert" onclick="showTab('tab-alert')" class="tab-btn px-6 py-4 font-semibold border-b-2 border-blue-500 text-blue-600 bg-blue-50 flex items-center gap-2 whitespace-nowrap transition">
                <i class="ph ph-warning-circle text-lg"></i> Alert Stok
            </button>
            <button id="btn-tab-fast" onclick="showTab('tab-fast')" class="tab-btn px-6 py-4 font-semibold border-b-2 border-transparent text-gray-500 hover:bg-gray-50 flex items-center gap-2 whitespace-nowrap transition">
                <i class="ph ph-rocket text-lg"></i> Fast Moving (Laris)
            </button>
            <button id="btn-tab-slow" onclick="showTab('tab-slow')" class="tab-btn px-6 py-4 font-semibold border-b-2 border-transparent text-gray-500 hover:bg-gray-50 flex items-center gap-2 whitespace-nowrap transition">
                <i class="ph ph-snail text-lg"></i> Slow Moving (Lama Terjual)
            </button>
        </div>

        <!-- TAB: ALERT STOK -->
        <div id="tab-alert" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Stok Habis -->
                <div class="bg-white rounded-lg shadow-md border-t-4 border-red-500 overflow-hidden">
                    <div class="p-4 bg-red-50 border-b flex items-center gap-2">
                        <i class="ph ph-x-circle text-red-600 text-xl"></i>
                        <h2 class="font-bold text-red-800">Barang Habis (Stok 0)</h2>
                    </div>
                    <div class="p-4">
                        <?php if (mysqli_num_rows($result_habis) > 0): ?>
                            <ul class="divide-y">
                                <?php while ($row = mysqli_fetch_assoc($result_habis)): ?>
                                    <li class="py-3 flex justify-between items-center">
                                        <div>
                                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['owner_nama'] ?? ''); ?></div>
                                        </div>
                                        <a href="stok_masuk" class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200 transition">Restock</a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-4">Aman. Tidak ada barang yang habis.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hampir Habis -->
                <div class="bg-white rounded-lg shadow-md border-t-4 border-orange-400 overflow-hidden">
                    <div class="p-4 bg-orange-50 border-b flex items-center gap-2">
                        <i class="ph ph-warning text-orange-600 text-xl"></i>
                        <h2 class="font-bold text-orange-800">Hampir Habis</h2>
                    </div>
                    <div class="p-4">
                        <?php if (mysqli_num_rows($result_hampir_habis) > 0): ?>
                            <ul class="divide-y">
                                <?php while ($row = mysqli_fetch_assoc($result_hampir_habis)): ?>
                                    <li class="py-3 flex justify-between items-center">
                                        <div>
                                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['owner_nama'] ?? ''); ?></div>
                                        </div>
                                        <div class="text-sm font-bold text-orange-600 bg-orange-100 px-3 py-1 rounded">
                                            Sisa: <?php echo formatQty($row['stok']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-4">Aman. Stok masih cukup untuk semua barang.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: FAST MOVING -->
        <div id="tab-fast" class="tab-content hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Mingguan -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 bg-green-50 border-b flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="ph ph-lightning text-green-600 text-xl"></i>
                            <h2 class="font-bold text-green-800">Top Laris (7 Hari Terakhir)</h2>
                        </div>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2">Barang</th>
                                    <th class="px-4 py-2 text-right">Total Terjual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $has_fast_mingguan = false; ?>
                                <?php while ($row = mysqli_fetch_assoc($fast_mingguan)): ?>
                                    <?php if ($row['total_terjual'] > 0): ?>
                                    <?php $has_fast_mingguan = true; ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['owner_nama']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-green-600">
                                            <?php echo formatQty($row['total_terjual']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                                <?php if (!$has_fast_mingguan): ?>
                                    <tr>
                                        <td colspan="2" class="px-4 py-6 text-center text-gray-500">Belum ada barang terjual dalam 7 hari terakhir</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bulanan -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 bg-blue-50 border-b flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="ph ph-star text-blue-600 text-xl"></i>
                            <h2 class="font-bold text-blue-800">Top Laris (30 Hari Terakhir)</h2>
                        </div>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2">Barang</th>
                                    <th class="px-4 py-2 text-right">Total Terjual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $has_fast_bulanan = false; ?>
                                <?php while ($row = mysqli_fetch_assoc($fast_bulanan)): ?>
                                    <?php if ($row['total_terjual'] > 0): ?>
                                    <?php $has_fast_bulanan = true; ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['owner_nama']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-blue-600">
                                            <?php echo formatQty($row['total_terjual']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                                <?php if (!$has_fast_bulanan): ?>
                                    <tr>
                                        <td colspan="2" class="px-4 py-6 text-center text-gray-500">Belum ada barang terjual dalam 30 hari terakhir</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: SLOW MOVING -->
        <div id="tab-slow" class="tab-content hidden">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded shadow-sm text-yellow-800 text-sm">
                <i class="ph ph-info font-bold"></i> Menampilkan barang yang <b>paling sedikit (atau belum pernah) terjual</b> dalam periode waktu tertentu. Berguna untuk evaluasi produk yang kurang laku.
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Mingguan -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 bg-gray-50 border-b flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="ph ph-hourglass-low text-gray-600 text-xl"></i>
                            <h2 class="font-bold text-gray-800">Kurang Laku (7 Hari Terakhir)</h2>
                        </div>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2">Barang</th>
                                    <th class="px-4 py-2 text-center">Stok Saat Ini</th>
                                    <th class="px-4 py-2 text-right">Total Terjual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($slow_mingguan)): ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['owner_nama']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-600">
                                            <?php echo formatQty($row['stok']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold <?php echo $row['total_terjual'] == 0 ? 'text-red-500' : 'text-gray-700'; ?>">
                                            <?php echo formatQty($row['total_terjual']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bulanan -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 bg-gray-50 border-b flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="ph ph-calendar-blank text-gray-600 text-xl"></i>
                            <h2 class="font-bold text-gray-800">Kurang Laku (30 Hari Terakhir)</h2>
                        </div>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2">Barang</th>
                                    <th class="px-4 py-2 text-center">Stok Saat Ini</th>
                                    <th class="px-4 py-2 text-right">Total Terjual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($slow_bulanan)): ?>
                                    <tr class="border-b">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['owner_nama']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-600">
                                            <?php echo formatQty($row['stok']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold <?php echo $row['total_terjual'] == 0 ? 'text-red-500' : 'text-gray-700'; ?>">
                                            <?php echo formatQty($row['total_terjual']) . ' ' . unitLabel($row['unit_type']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
